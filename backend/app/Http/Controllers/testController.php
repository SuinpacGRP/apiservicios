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
        $this->middleware( 'jwt', ['except' => ['verificar_usuario_sistema','listaCredenciales','ObtenerFormato','descargarLicencia']] );
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
    public function listaCredenciales( Request $request ){
        //SELECT idCredencialTipo as Tipo ,Formato, FormatoAtras, Descripcion, PlantillaHTML FROM CredencialFormato WHERE idCredencialFormato in ();
        $lista = DB::table('CredencialFormato')
            ->select("idCredencialTipo as Tipo" ,"Formato", "FormatoAtras", "Descripcion", "PlantillaHTML",'idCredencialFormato')
            ->whereIn('idCredencialFormato',array(30,25,55))
            ->get();
        return response()->json([
            "Status"  => true,
            "Estatus" => 200,
            'Mensaje' => $lista,
            "Error"   => null
        ]);
    }
    public function ObtenerFormato( Request $request ){
        $datos = $request->all();
        $rules = [
            'Licencia' => 'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'mensaje'=>'Formato no encontrado',
                'Code'=>203
            ]);
        }
        $Licencia = $request->Licencia;
        $formatos = DB::select('SELECT 
        ( SELECT CONCAT("https://suinpac.com/",Ruta) FROM CelaRepositorioC WHERE idRepositorio = ( SELECT Formato FROM CredencialFormato WHERE idCredencialFormato = '.$Licencia.')) AS Frente,
        ( SELECT CONCAT("https://suinpac.com/",Ruta) FROM CelaRepositorioC WHERE idRepositorio = ( SELECT FormatoAtras FROM CredencialFormato WHERE idCredencialFormato = '.$Licencia.')) AS Atras');
        return response()->json([
            "Status"  => true,
            "Code" => 200,
            'Mensaje' => $formatos,
            "Error"   => null
        ]);
    }
    public function descargarLicencia(Request $request ){
        $datos = $request->all();
        $direccion = $request->Ruta;
        $formatedUrl = str_replace('repositorio',"",$direccion);
        $data = Storage::disk('repositorio') -> get($formatedUrl);
        $encodeLogo = base64_encode($data); 
        return response()->json([
            'Status'=>true,
            'Data'=>$encodeLogo,
            'Code'=> 200
        ]);
    }
}
