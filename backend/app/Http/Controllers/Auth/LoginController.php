<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    /*public function login(){
        $credentials = $this->validate(request(), [
            'Usuario' => 'required|string',
            'Contrase_na' => 'required|string',
        ]);
        #$credentials['Contrase_na'] = md5( $credentials['Contrase_na'] );
        #return $credentials;

        #$results = DB::select('select * from CelaUsuario where Usuario = "' . $credentials['Usuario'] . '" and Contrase_na = "' . $credentials['Contrase_na'] . '"');
        #return $results;

        #if( Auth::attempt($credentials) ){
        if( Auth::attempt(['Usuario' => $credentials['Usuario'], 'password' => $credentials['Contrase_na']]) ){
            return "Has iniciado sesion correctamente";
        }else{
            return "Error Auth";
        }

    }*/

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
    use AuthenticatesUsers;
    protected $redirectTo = '/home';
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function username()
    {
        return 'Usuario';
    }


}