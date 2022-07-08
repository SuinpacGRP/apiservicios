<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use App\Cliente;
use App\Funciones;
use Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JWTFactory;
use App\User;

class testController extends Controller
{
    public function __construct(){
        $this->middleware( 'jwt', ['except' => ['verificar_usuario_sistema']] );
    }
    //
    public function verificar_usuario_sistema(Request $request){
        $cadenaMovil =  "kcjMRg2kYw8vbnfWHtBTh59BzPKfQz";
        if($request->cadena === $cadenaMovil){
            $usuario = "suinpacmovilasistencia";
            $pass = "Android13072020$";
            JWTAuth::factory()->setTTL(600);
            #$result = DB::select('SELECT * FROM CelaUsuario WHERE Usuario LIKE "suinpacmovilasistencia"');
            if ( !$token = auth()->attempt( ['Usuario' => $usuario, 'password' => $pass, 'EstadoActual' => 1] ) ) {
                return response()->json([
                    "Status"   => "false",
                    "Estatus" => $token,
                    'Mensaje' => 'RFC/Entidad Incorrectos',
                ]);
            }
            return response()->json([
                "Status"   => "true",
                "Estatus" => true,
                "token" => $token
            ]);
        }else{
            return response()->json([
                "Status"   => "true",
                "Estatus" => "Falta algo",
                "token" => $token
            ]);
        }
    } 
    public function verificar_usuario_sistemaV2(Request $request){
        $cadenaMovil =  "kcjMRg2kYw8vbnfWHtBTh59BzPKfQz";
        if($request->Cadena === $cadenaMovil){
            $credencial = $request->only('Usuario', 'password');
        }
    }

}
