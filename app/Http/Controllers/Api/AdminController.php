<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Facultad; // Necesario para crear un nuevo Académico
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext; // Usaremos Client Credential Flow

class AdminController extends Controller
{
    /**
     * Define los IDs de los roles administrativos/de coordinación.
     * Solo estos roles (o un super-admin) pueden usar esta función.
     */
    private $rolesPermitidos = [5, 6, 7, 8]; // Roles administrativos

    /**
     * Mapea y limpia los datos de Graph.
     */
    private function mapUserData(array $userGraphData): array
    {
        // Reutilizamos la lógica de mapeo que ya tienes en LoginController
        return [
            'mail' => $userGraphData['mail'] ?? null,
            'first_name' => $userGraphData['givenName'] ?? ($userGraphData['names'][0]['first'] ?? null),
            'last_name' => $userGraphData['surname'] ?? ($userGraphData['names'][0]['last'] ?? null),
            // Nota: Aquí se necesitan los datos completos que obtienes en el callback para createUser
            'matricula' => $userGraphData['employeeId'] ?? null,
            'facultad' => $userGraphData['department'] ?? null,
            'campus' => $userGraphData['streetAddress'] ?? null,
            'programa_educativo' => $userGraphData['officeLocation'] ?? null,
        ];
    }
    
    /**
     * Obtiene el token de autenticación de Client Credentials Flow (App-only token).
     * Esto requiere que las credenciales de la aplicación tengan el permiso User.Read.All.
     * @return string
     */
    private function getClientCredentialToken(): string
    {
        $tenantId = config('azure.authority'); // Esto debería ser el ID del tenant o el nombre del tenant
        $clientId = config('azure.appId');
        $clientSecret = config('azure.appSecret');
        $tokenEndpoint = $tenantId . config('azure.tokenEndpoint');
        
        $response = (new \GuzzleHttp\Client())->post($tokenEndpoint, [
            'form_params' => [
                'client_id' => $clientId,
                'scope' => 'https://graph.microsoft.com/.default', // Scope para Client Credentials
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
            ],
        ]);
        
        $data = json_decode($response->getBody()->getContents(), true);
        return $data['access_token'];
    }

    /**
     * Busca usuarios académicos en Microsoft Graph.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchGraphUsers(Request $request)
    {
        // Autorización: solo un rol administrativo puede buscar
        if (!Auth::user()->roles()->whereIn('role_id', $this->rolesPermitidos)->exists()) {
             return response()->json(['message' => 'No autorizado. Se requiere un rol administrativo.'], 403);
        }

        $search = $request->input('search');
        if (empty($search) || strlen($search) < 3) {
            return response()->json(['message' => 'Ingrese al menos 3 caracteres.'], 422);
        }
        
        try {
            // 1. Obtener Token de la aplicación (Client Credentials Flow)
            $token = $this->getClientCredentialToken();
            $httpClient = new \GuzzleHttp\Client();

            // 2. Construir la consulta de búsqueda avanzada en Graph
            // Buscamos por displayName, mail y userPrincipalName
            $filter = urlencode("startswith(displayName, '{$search}') or startswith(mail, '{$search}') or startswith(userPrincipalName, '{$search}')");

            $response = $httpClient->get("https://graph.microsoft.com/v1.0/users?\$filter={$filter}&\$select=id,displayName,mail,userPrincipalName", [
                'headers' => ['Authorization' => 'Bearer ' . $token]
            ]);
            
            $users = json_decode($response->getBody()->getContents(), true)['value'];
            
            // Filtramos solo por el dominio UV si es necesario, y mapeamos
            $academicos = collect($users)->filter(function ($user) {
                // Asumimos que los académicos NO son estudiantes ni egresados
                return (
                    isset($user['mail']) && 
                    !strpos($user['mail'], '@estudiantes.uv.mx') && 
                    !strpos($user['mail'], '@egresados.uv.mx')
                );
            })->map(function ($user) {
                return [
                    'id' => $user['id'],
                    'displayName' => $user['displayName'] ?? 'N/A',
                    'mail' => $user['mail'] ?? $user['userPrincipalName'],
                ];
            })->values();

            return response()->json($academicos);
            
        } catch (\Exception $e) {
            \Log::error('MS Graph Search Error: ' . $e->getMessage());
            // Si el error es de permisos, la configuración de la aplicación es incorrecta
            return response()->json(['message' => 'Error en la búsqueda. Verifique la configuración de permisos (User.Read.All).'], 500);
        }
    }

    /**
     * Asigna un rol administrativo a un usuario (creándolo si no existe en la DB).
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignAcademicoRole(Request $request)
    {
        // 1. Autorización: solo roles administrativos pueden asignar
        if (!Auth::user()->roles()->whereIn('role_id', $this->rolesPermitidos)->exists()) {
            return response()->json(['message' => 'No autorizado. Se requiere un rol administrativo para asignar roles.'], 403);
        }

        // 2. Validación
        $request->validate([
            'email' => 'required|email',
            'role_id' => [
                'required', 
                'integer', 
                Rule::in($this->rolesPermitidos) // Asegura que solo se asignen roles administrativos
            ],
        ]);

        $email = $request->input('email');
        $roleId = $request->input('role_id');
        $user = User::where('email', $email)->first();
        
        // --- 3. Lógica de Existencia/Creación de Usuario ---
        if (!$user) {
            // Si el usuario NO existe en la DB, ¡DEBEMOS crearlo con datos completos de Graph!
            try {
                // Obtenemos el token con Client Credentials
                $token = $this->getClientCredentialToken();
                $httpClient = new \GuzzleHttp\Client();

                // 3.1. Obtener datos detallados (similar al callback del LoginController)
                $responseMe = $httpClient->get("https://graph.microsoft.com/v1.0/users/{$email}", [
                    'headers' => ['Authorization' => 'Bearer ' . $token],
                    'query' => ['$select' => 'id,displayName,mail,userPrincipalName,givenName,surname,officeLocation,department'],
                ]);
                $userMe = json_decode($responseMe->getBody()->getContents(), true);

                $responseProfile = $httpClient->get("https://graph.microsoft.com/beta/users/{$email}/profile", [
                    'headers' => ['Authorization' => 'Bearer ' . $token]
                ]);
                $userProfile = json_decode($responseProfile->getBody()->getContents(), true);

                // Mapear los datos de Graph. NOTA: Las propiedades de 'profile' no siempre están llenas.
                $userGraphArray = [
                    'mail' => $userMe['mail'] ?? null,
                    'first_name' => $userProfile['names'][0]['first'] ?? $userMe['givenName'] ?? null,
                    'last_name' => $userProfile['names'][0]['last'] ?? $userMe['surname'] ?? null,
                    'matricula' => $userProfile['positions'][0]['detail']['employeeId'] ?? null,
                    'programa_educativo' => $userProfile['positions'][0]['detail']['company']['officeLocation'] ?? $userMe['officeLocation'] ?? null,
                    'facultad' => $userProfile['positions'][0]['detail']['company']['department'] ?? $userMe['department'] ?? null,
                    'campus' => $userProfile['positions'][0]['detail']['company']['address']['street'] ?? null,
                ];

                // Reutilizamos la lógica de createUser, forzando la creación como académico (rol 2) si tiene correo @uv.mx
                // Ya que estamos asignando un rol administrativo (5-8), el rol inicial de "académico" (2) es implícito
                $user = $this->createUserAsAcademico($userGraphArray);

                if (!$user) {
                    return response()->json(['message' => 'Fallo al crear el usuario y su perfil de Académico en la DB.'], 500);
                }

            } catch (\Exception $e) {
                \Log::error('MS Graph User Fetch Error: ' . $e->getMessage());
                return response()->json(['message' => 'No se pudo obtener la información detallada del usuario de Graph para crearlo.'], 500);
            }
        }
        
        // --- 4. Asignación de Rol Local ---
        try {
            DB::beginTransaction();

            // Desasigna el rol de Académico Genérico (2) y otros roles administrativos (5-8)
            $user->roles()->detach([2, 5, 6, 7, 8]);
            
            // Asigna el nuevo rol administrativo y el rol base de Académico (2)
            $user->roles()->attach([2, $roleId]);

            DB::commit();
            return response()->json([
                'message' => "Rol {$roleId} asignado correctamente a {$user->name}.",
                'user_id' => $user->id,
                'email' => $user->email,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al asignar rol en API: ' . $e->getMessage());
            return response()->json(['message' => 'Fallo la asignación del rol en la base de datos local.'], 500);
        }
    }

    /**
     * Reutiliza y adapta la lógica de createUser del LoginController para académicos.
     */
    private function createUserAsAcademico($userData)
    {
        try {
            DB::beginTransaction();

            // Lógica de BÚSQUEDA Y CREACIÓN EN CATÁLOGOS (Campus, Facultad)
            $campus = \App\Models\Campus::firstOrCreate(
                ['nombreCampus' => capitalizeFirst($userData['campus'] ?? 'Desconocido')]
            );

            $facultad = \App\Models\Facultad::firstOrCreate(
                ['nombreFacultad' => capitalizeFirst($userData['facultad'] ?? 'Desconocida')],
                ['idCampus' => $campus->idCampus]
            );

            // CREACIÓN DEL USUARIO
            $newUser = User::create([
                'name' => ($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''),
                'first_name' => $userData['first_name'] ?? '',
                'last_name' => $userData['last_name'] ?? '',
                'email' => $userData['mail'],
                'password' => \Illuminate\Support\Facades\Hash::make(uniqid())
            ]);
            
            $idUsuarioDB = $newUser->id;

            // ASIGNACIÓN DE ROL BASE ACADÉMICO (ID 2)
            DB::table('role_usuario')->insert(['user_id' => $idUsuarioDB, 'role_id' => 2]);
            
            // CREACIÓN DEL REGISTRO DE ACADÉMICO
            DB::table('academicos')->insert([
                'user_id' => $idUsuarioDB,
                'idFacultad' => $facultad->idFacultad,
                'NoPersonalAcademico' => $userData['matricula'] ?? 'PENDIENTE',
            ]);
            
            DB::commit();
            return $newUser;

        } catch (\Throwable $throwable) {
            DB::rollBack();
            \Log::error('Create User DB Error from Admin: ' . $throwable->getMessage());
            return null;
        }
    }
}