<?php

namespace App\Http\Controllers\Aplicaciones;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JWTAuth;
use JWTFactory;
use App\User;
use App\Cliente;
use App\Funciones;
use Validator;


class AsistenciaWindowsControler extends Controller
{
    public function __construct()
    {
        $this->middleware( 'jwt', ['except' => ['obtenerConfiguracionMasivoAsitencias','GenerarAsistenciasMasivoSuinpac']] );

    }

    public function obtenerConfiguracionMasivoAsitencias( Request $request ){
        $data = $request->all();
        $Rules = [
            "Cliente"=>"required|string"
        ];
        $Cliente = $request->Cliente;
        $validator = Validator::make($data, $Rules);
        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => 'Error',
                'Error' => $validator->messages(),
            ]);
        }
        //NOTE: obtenemos los datos de la configuracion (SELECT * FROM  ClienteDatos WHERE Cliente = 40 AND Indice = "InsercionAsistencias";)
        $Condfiguracion = DB::table('ClienteDatos')->select('Valor')
                                ->where('Cliente','=',$Cliente)
                                ->where('Indice','=',"InsercionAsistencias")->get();
        
        if($Condfiguracion){
            return $Condfiguracion[0]->Valor;
        }else{
            return "23:30:00";
        }
    }
    //NOTE: Funcion de prueba para hacer los rellenos de horarios
    public function GenerarAsistenciasMasivoSuinpac(Request $request){
        $datos = $request->all();
        $Rules = [
            'Cliente'=>'required|numeric',
            'Fecha'=>'required|string',
            'Dia'=>'required|numeric'
        ];
        $validator = Validator::make($datos, $Rules);
        if ( $validator->fails() ) {
            return [
                'Status'=>false,
                'Error'=> $validator->messages() ,
                'code'=> 403
            ];
        }
        $Cliente = $request->Cliente;
        $Fecha = $request->Fecha;
        $Dia = $request->Dia;

        $url = "https://suinadm.suinpac.dev/GenerarAsistenciaMasivoCheccador.php";
        $datosPost = array(
            "Cliente" => $Cliente,
            "Fecha" => $Fecha,
            "Dia" => $Dia,
            'Usuario'=>4947,
            "Ejercicio"=>date('Y')
        );
        $options = array(
            'http'=> array(
                'header'=>"Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($datosPost),
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url,false,$context);
        return [
            'Status'=>true,
            'Result'=>$result
        ];
    }
}
