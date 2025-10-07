<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\Campus;
use App\Models\Facultad;
use App\Models\ProgramaEducativo;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\AuthorizationCodeContext;
use Microsoft\Kiota\Authentication\PhpLeagueAuthenticationProvider;
use Microsoft\Kiota\Http\GuzzleRequestAdapter;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    public function register() 
    {
        return view("auth.register");
    }

    /**
     * Cerrar sesion del usuario
     * * @return \Illuminate\Http\Response
     */
    public function logout(Request $request) 
    {
        // Cierra la sesión del guard 'web'
        Auth::guard('web')->logout();

        // Invalida la sesión actual para que no pueda ser reutilizada
        $request->session()->invalidate();

        // Regenera el token CSRF como medida de seguridad
        $request->session()->regenerateToken();

        // Devuelve una respuesta HTTP 204 "No Content".
        return response()->noContent();
    }

    /**
     * Retorna la pagina de login del Sistemafca
     * 
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function login() 
    {
        return view("auth.login");
    }

    /**
     * Validar el input del usuario e intentar iniciar sesion con Microsoft Graph
     * * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function attempt(Request $request)
    {
        return $this->msGraph($request->input('name'));
    }

    // Dudas preguntas, gran parte del código del login con Graph fue adaptado de este repositorio de Microsoft
    // https://github.com/microsoftgraph/msgraph-sample-phpapp/tree/main/graph-tutorial
    
    /**
     * Redireccionar a la pagina de login de Microsoft con los parametros necesarios
     * 
     * @param string $name nombre de usuario
     * @return \Illuminate\Http\RedirectResponse
     */
    private function msGraph($name) 
    {
        $oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId'                => config('azure.appId'),
            'clientSecret'            => config('azure.appSecret'),
            'redirectUri'             => config('azure.redirectUri'),
            'urlAuthorize'            => config('azure.authority').config('azure.authorizeEndpoint'),
            'urlAccessToken'          => config('azure.authority').config('azure.tokenEndpoint'),
            'urlResourceOwnerDetails' => '',
            'scopes'                  => config('azure.scopes'),
        ]);

        // redireccionar directo a la pagina de login de la UV y saltarse la de microsoft
        $authUrl = $oauthClient->getAuthorizationUrl([
            'prompt' => 'login',
            'login_hint' => $this->getEmail($name)
        ]);

        // Salvar el estado para validar en callback
        session(['oauthState' => $oauthClient->getState()]);

        // Redireccionar a la pagina de login de Microsoft
        return redirect()->away($authUrl);
    } 


    /**
     * Una vez se hace el login en la pagina de Microsoft, se redirige a esta ruta
     * * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function callback(Request $request)
    {
        // Validar el 'state' para seguridad
        $expectedState = session('oauthState');
        $request->session()->forget('oauthState');
        $providedState = $request->query('state');
        
        if (!isset($expectedState) || !isset($providedState) || $expectedState != $providedState) {
            return redirect('http://localhost:5173/login?error=Invalid+state');
        }

        $authCode = $request->query('code');
        
        if (isset($authCode)) {
            $oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
                'clientId'                => config('azure.appId'),
                'clientSecret'            => config('azure.appSecret'),
                'redirectUri'             => config('azure.redirectUri'),
                'urlAuthorize'            => config('azure.authority').config('azure.authorizeEndpoint'),
                'urlAccessToken'          => config('azure.authority').config('azure.tokenEndpoint'),
                'urlResourceOwnerDetails' => '',
                'scopes'                  => config('azure.scopes'),
            ]);

            try {
                // 1. Obtenemos el token de acceso
                $accessToken = $oauthClient->getAccessToken('authorization_code', [
                    'code' => $authCode
                ]);
                $token = $accessToken->getToken();

                // 2. Usamos Guzzle para hacer las dos llamadas a la API de Graph
                $httpClient = new \GuzzleHttp\Client();

                // Primera llamada al endpoint '/me' para obtener datos básicos
                $responseMe = $httpClient->get('https://graph.microsoft.com/v1.0/me', [
                    'headers' => ['Authorization' => 'Bearer ' . $token]
                ]);
                $userMe = json_decode($responseMe->getBody()->getContents(), true);

                // Segunda llamada al endpoint '/me/profile' para los detalles
                $responseProfile = $httpClient->get('https://graph.microsoft.com/beta/me/profile', [
                    'headers' => ['Authorization' => 'Bearer ' . $token]
                ]);
                $userProfile = json_decode($responseProfile->getBody()->getContents(), true);

                // 3. Combinamos toda la información en un solo array, priorizando los datos detallados
                $userGraphArray = [
                    'mail'               => $userMe['mail'] ?? null,
                    'first_name'         => $userProfile['names'][0]['first'] ?? $userMe['givenName'] ?? null,
                    'last_name'          => $userProfile['names'][0]['last'] ?? $userMe['surname'] ?? null,
                    'matricula'          => $userProfile['positions'][0]['detail']['employeeId'] ?? null,
                    'programa_educativo' => $userProfile['positions'][0]['detail']['company']['officeLocation'] ?? $userMe['officeLocation'] ?? null,
                    'facultad'           => $userProfile['positions'][0]['detail']['company']['department'] ?? null,
                    'campus'             => $userProfile['positions'][0]['detail']['company']['address']['street'] ?? null,
                ];

                $user = User::where('email', $userGraphArray['mail'])->first();

                if ($user === null) {
                    $user = $this->createUser($userGraphArray);
                }

                if (!$user) {
                    return redirect('http://localhost:5173/login?error=User+creation+failed+in+DB');
                }

                Auth::login($user);
                return redirect('http://localhost:5173/ConsultarTramites');

            } catch (\Exception $e) {
                \Log::error('MS Graph Callback Error: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
                return redirect('http://localhost:5173/login?error=Authentication+failed&errorDetail=' . urlencode('Error processing user data from Graph. Check logs.'));
            }
        }
        
        $error = $request->query('error_description') ?? $request->query('error');
        return redirect('http://localhost:5173/login?error=' . urlencode($error));
    }

    /**
     * Crear un nuevo usuario.
     * Según si es acádemico o estudiante.
     *
     * @param  array  $userData
     * @return \App\Models\User|null
     */
    private function createUser($userData)
    {
        try {
            DB::beginTransaction();

            // --- LÓGICA DE BÚSQUEDA Y CREACIÓN EN CATÁLOGOS ---
            $campus = \App\Models\Campus::firstOrCreate(
                ['nombreCampus' => capitalizeFirst($userData['campus'] ?? 'Desconocido')]
            );

            $facultad = \App\Models\Facultad::firstOrCreate(
                ['nombreFacultad' => capitalizeFirst($userData['facultad'] ?? 'Desconocida')],
                ['idCampus' => $campus->idCampus]
            );

            $programaEducativo = \App\Models\ProgramaEducativo::firstOrCreate(
                ['nombrePE' => capitalizeFirst($userData['programa_educativo'] ?? 'Desconocido')],
                ['facultad_id' => $facultad->idFacultad]
            );

            // --- CREACIÓN DEL USUARIO Y SUS RELACIONES ---
            $newUser = User::create([
                'name'       => ($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''),
                'first_name' => $userData['first_name'] ?? '',
                'last_name'  => $userData['last_name'] ?? '',
                'email'      => $userData['mail'],
                'password'   => \Illuminate\Support\Facades\Hash::make(uniqid())
            ]);
            
            $idUsuarioDB = $newUser->id;

            if (strpos($userData['mail'], '@estudiantes.uv.mx') !== false || strpos($userData['mail'], '@egresados.uv.mx') !== false) {
                $isEgresado = strpos($userData['mail'], '@egresados.uv.mx') !== false;

                DB::table('role_usuario')->insert(['user_id' => $idUsuarioDB, 'role_id' => $isEgresado ? 4 : 3]);

                DB::table('estudiantes')->insert([
                    'user_id'             => $idUsuarioDB,
                    'idPE'                => $programaEducativo->idPE,
                    'matriculaEstudiante' => $userData['matricula'] ?? 'PENDIENTE',
                    'grupoEstudiante'     => 'N/A', // Aún no tenemos este dato
                ]);
            } else { // Si es académico
                DB::table('role_usuario')->insert(['user_id' => $idUsuarioDB, 'role_id' => 2]);
                DB::table('academicos')->insert([
                    'user_id'             => $idUsuarioDB,
                    'idFacultad'          => $facultad->idFacultad,
                    'NoPersonalAcademico' => $userData['matricula'] ?? 'N/A', // Asumimos que 'matricula' también es su ID
                ]);
            }
            
            DB::commit();
            return $newUser;

        } catch (\Throwable $throwable) {
            DB::rollBack();
            \Log::error('Create User DB Error: ' . $throwable->getMessage());
            return null;
        }
    }


    /**
     * Obtener el email de un usuario segun si es academico, estudiante o egresado
     * Si se introduce un email, entonces se retorna igual
     * 
     * @param string $name nombre de usuario
     * @return string email del usuario
     */
    private function getEmail(string $name) : string 
    {
        if (strpos($name, '@') !== false) {
            return $name;
        }
        elseif (preg_match('/^zs\d+$/i', $name)) {
            return "$name@estudiantes.uv.mx";
        } 
        elseif (preg_match('/^s\d+$/i', $name)) {
            return  "z$name@estudiantes.uv.mx";
        } 
        elseif (preg_match('/^gs\d+$/i', $name)) {
            return "$name@egresados.uv.mx";
        }

        return "$name@uv.mx";
    }  
}
