<?php

namespace App\Http\Controllers;

use Validator;
use JWTAuth;
use App\Cliente;

class AuthController extends Controller
{
    /**
     * ! Se asigna el middleware  al costructor
     */
    public function __construct()
    {
        $this->middleware( 'jwt', [ 'except' => ['loggin', 'login'] ] );
    }

    /**
     * ! Obtiene solamente el token.
     */
    public function loggin()
    {
        $credentials = request(['Usuario', 'Contrase_na']);
        
        $rules = [
            'Usuario'     => 'required|string',
            'Contrase_na' => 'required|string',
        ];

        $validator = Validator::make($credentials, $rules);

        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => 'Error',
                'Error' => $validator->messages()
            ]);
        }
        #echo Carbon::now()->addMinutes(10)->timestamp;
        JWTAuth::factory()->setTTL(60);//Pedro Lopez Pacheco 13 de junio de 2022, modificacion para que el token dure 60 minutos segun la doc de laravel JWT
        #JWTAuth::factory()->setTTL(Carbon::now()->addMinutes(10)->timestamp);

        if ( !$token = auth()->attempt( ['Usuario' => $credentials['Usuario'], 'password' => $credentials['Contrase_na'], 'EstadoActual' => 1] ) ) {
            return response()->json([
                'Status' => 'Error',
                'Error' => 'Usuario o Contraseña Incorrectos',
            ]);
        }

        return $token;
        // Dentro de mi variable $user voy a generar el token con los datos del usuario mediante JWTAuth::toUser() metodo nativo de la librería que instanciamos:
        #$user = JWTAuth::toUser($token);return $user;
    }

    /**
     * ! Obtiene un token con los datos de autenticacion.
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request(['Usuario', 'Contrase_na']);
        
        $rules = [
            'Usuario'     => 'required|string',
            'Contrase_na' => 'required|string',
        ];

        $validator = Validator::make($credentials, $rules);
        
        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => 'Error',
                'Error' => $validator->messages()
            ]);
        }
        JWTAuth::factory()->setTTL(60);//Carlos Dircio 30 de marzo, modificacion para que el token dure 60 minutos segun la doc de laravel JWT
        #echo Carbon::now()->addMinutes(2)->timestamp;
        #JWTAuth::factory()->setTTL(10);
        #JWTAuth::factory()->setTTL(1);
        /*$token = auth()->attempt( ['Usuario' => $credentials['Usuario'], 'password' => $credentials['Contrase_na'], 'EstadoActual' => 1] );
        return $token;*/

        if ( !$token = auth()->attempt( ['Usuario' => $credentials['Usuario'], 'password' => $credentials['Contrase_na'], 'EstadoActual' => 1] ) ) {
            return response()->json([
                'Status' => 'Error',
                'Error' => 'Usuario o Contraseña Incorrectos',
            ]);
        }

        return $this->respondWithToken($token);
        // Dentro de mi variable $user voy a generar el token con los datos del usuario mediante JWTAuth::toUser() metodo nativo de la librería que instanciamos:
        #$user = JWTAuth::toUser($token);return $user;
        // Retorno los datos dentro de un token en formato JSON:
        #return response()->json( compact('token', 'user') );
    }

    public function loginV2()
    {
        return response()->json( ['Message' => 'PRUEBA ENTRANTE'] );
    }
    

    /**
     * ! Obtiene los datos del usuario autenticado.
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        $user = JWTAuth::parseToken()->authenticate();

        #$user->NombreCompleto = utf8_decode($user->NombreCompleto);
        return $user;
        #return response()->json( auth()->user() );
    }

    /**
     * ! Obtiene los datos del token
     * @return \Illuminate\Http\JsonResponse
     */
    public function payload()
    {
        return response()->json( auth()->payload() );
    }

    /**
     * ! Cierra Sesion del Usuario (Ivalida el token).
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();
        return response()->json( ['Message' => 'Sesion Cerrada'] );
    }

    /**
     * ! Refresca el token.
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        JWTAuth::factory()->setTTL(50);//Pedro Lopez Pacheco 13 de junio de 2022, modificacion para que el token dure 50 minutos segun la doc de laravel JWT
        return $this->respondWithToken( auth()->refresh() );
        #return auth()->refresh();
    }

    /**
     * ! Obtiene un json con datos del token.
     * @param string $token
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken( $token )
    {
        $usuario = auth()->user();
        
        $cliente = Cliente::select('Descripci_on AS ClienteNombre')
            ->where('id', $usuario->Cliente )
            ->value('ClienteNombre');

        #$usuario->NombreCompleto = utf8_decode( $usuario->NombreCompleto );
        #$usuario->ClienteNombre = utf8_decode( $cliente );
        #$usuario->NombreCompleto =  $usuario->NombreCompleto;
        $usuario->ClienteNombre = $cliente;

        return response()->json([
            'token'      => $token,
            'token_type' => 'bearer',
            #'expires_in' => auth()->factory()->getTTL(),
            'user'       => $usuario,
        ]);
    }
}