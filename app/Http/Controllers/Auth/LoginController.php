<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
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
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function callback(Request $request)
    {
        // Validar el 'state'
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

            // 2. Usamos el cliente HTTP más básico (Guzzle) para llamar a la API
            $httpClient = new \GuzzleHttp\Client();
            $response = $httpClient->get('https://graph.microsoft.com/v1.0/me', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken->getToken()
                ]
            ]);

            // 3. Convertimos la respuesta JSON a un array
            $userGraphArray = json_decode($response->getBody()->getContents(), true);

            $user = User::where('email', $userGraphArray['mail'])->first();

            if ($user === null) {
                $user = $this->createUser($userGraphArray);
            }

            if (!$user) {
                return redirect('http://localhost:5173/login?error=User+not+found+in+local+DB');
            }

            Auth::login($user);

            // Redirigimos a la ruta CORRECTA de tu frontend
            return redirect('http://localhost:5173/ConsultarTramites');

            } catch (\Exception $e) {
                \Log::error('MS Graph Callback Error: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
                return redirect('http://localhost:5173/login?error=Authentication+failed&errorDetail=' . urlencode('Error processing user data. Check logs.'));
            }
        }
        $error = $request->query('error_description') ?? $request->query('error');
        return redirect('http://localhost:5173/login?error=' . urlencode($error));
    }

    /**
     * Crear un nuevo usuario.
     * Según si es acádemico o estudiante.
     *
     * @param  array  $user
     * 
     */
    private function createUser($userGraphArray)
    {
        try {
        DB::beginTransaction();
        $fullName = ($userGraphArray['givenName'] ?? '') . ' ' . ($userGraphArray['surname'] ?? '');
        
        // 1. Creamos el usuario principal
        $newUser = User::create([
            'name'       => $fullName,
            'first_name' => $userGraphArray['givenName'] ?? '',
            'last_name'  => $userGraphArray['surname'] ?? '',
            'email'      => $userGraphArray['mail'],
            'password'   => \Illuminate\Support\Facades\Hash::make(uniqid())
        ]);
        $idUsuarioDB = $newUser->id;

        // 2. Insertamos los datos personales
        DB::table('datos_personales')->insert([
            'NombreDatosPersonales'          => capitalizeFirst($userGraphArray['givenName'] ?? ''),
            'ApellidoPaternoDatosPersonales' => capitalizeFirst($userGraphArray['surname'] ?? ''),
            'ApellidoMaternoDatosPersonales' => '',
            'user_id'                        => $idUsuarioDB,
            'CreatedBy'                      => 1,
            'UpdatedBy'                      => 1
        ]);

        // 3. Lógica para estudiantes/académicos
        if (strpos($userGraphArray['mail'], '@estudiantes.uv.mx') !== false || strpos($userGraphArray['mail'], '@egresados.uv.mx') !== false) {

            $emailParts = explode('@', $userGraphArray['mail']);
            $localPart = $emailParts[0];
            $matricula = ltrim($localPart, 'z');

            $isEgresado = strpos($userGraphArray['mail'], '@egresados.uv.mx') !== false;

            DB::table('role_usuario')->insert([
                'user_id'   => $idUsuarioDB,
                'role_id'   => $isEgresado ? 4 : 3,
                'CreatedBy' => 1,
                'UpdatedBy' => 1
            ]);

            DB::table('estudiantes')->insert([
                'matriculaEstudiante' => $matricula,
                'user_id'             => $idUsuarioDB,
                'CreatedBy'           => 1,
                'UpdatedBy'           => 1
            ]);
        } else {
            DB::table('academicos')->insert([
                'NoPersonalAcademico' => $userGraphArray['employeeId'] ?? 'N/A',
                'RfcAcademico'        => '',
                'user_id'             => $idUsuarioDB,
                'CreatedBy'           => 1,
                'UpdatedBy'           => 1
            ]);

            DB::table('role_usuario')->insert([
                'user_id'   => $idUsuarioDB,
                'role_id'   => 2,
                'CreatedBy' => 1,
                'UpdatedBy' => 1
            ]);
        }

        DB::commit();
        return $newUser;
    
        } catch (\Throwable $throwable) {
            DB::rollBack();
            throw $throwable;
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
