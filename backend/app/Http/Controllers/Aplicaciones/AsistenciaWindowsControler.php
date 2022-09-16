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
use Symfony\Component\CssSelector\Node\FunctionNode;
use Validator;


class AsistenciaWindowsControler extends Controller
{
    public function __construct()
    {
        $this->middleware( 'jwt', 
        ['except' =>
            [
                'obtenerConfiguracionMasivoAsitencias',
                'GenerarAsistenciasMasivoSuinpac',
                'ObtenerEmpleadoInte',
                'ObtenerDatosEmpleadosInt',
                'ObtenerEmpleadosMasivoInt',
                'ObtenerBitacoraChecadorInt',
                'RegitrarChecadorInt',
                'EnviarRespuestaSuinpac'
            ]
        ]);

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
    public function ObtenerEmpleadoInte( Request $request ){
        //FIXME: Metodo de pruebas insercion de datos
        $datos = $request->all();
        $Rules = ['Cliente'=>'required|numeric'];
        $validator = Validator::make($datos, $Rules);
        if ( $validator->fails() ) {
            return [
                'Status'=>false,
                'Error'=> $validator->messages(),
                'code'=> 403
            ];
        }
        $Cliente = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $bitacota = DB::table('ChecadorBitacora')->select(['*'])->get();
        if($bitacota){
            return [
                'Status'=>true,
                'Data'=> $bitacota,
                'code'=> 200
            ];
        }else{
            return [
                'Status'=>false,
                'Error'=> $bitacota,
                'code'=> 403
            ];
        }
    }
    public function ObtenerDatosEmpleadosInt( Request $request ){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required',
            'Empleado'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de completar los campo",
                'Code'=>223
            ]);
        }
        $cliente = $request->Cliente;
        $PuestoEmpleado = $request->Empleado;
        Funciones::selecionarBase($cliente);
        $table = 'EmpleadoFotografia';
        $result = DB::table('Persona')->select("Persona.id as idEmpleado",DB::raw('CONCAT(Persona.Nombre," ",Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) AS Nombre'),'Persona.Nfc_uid','Persona.idChecadorApp as idChecador','Persona.N_umeroDeEmpleado as NoEmpleado','PuestoEmpleado.NombreDelCargo As Cargo','AreasAdministrativas.Descripci_on as AreaAdministrativa','Cat_alogoPlazaN_omina.Descripci_on AS NombrePlaza','TipoTrabajador.Descripci_on as Trabajador' ,DB::raw('CONCAT("https://suinpac.com/",CelaRepositorio.Ruta) as Foto'))
            ->join('PuestoEmpleado','Persona.id','=','PuestoEmpleado.Empleado')
            ->join('TipoTrabajador','TipoTrabajador.id','=','PuestoEmpleado.TipoTrabajador')
            ->join('PlantillaN_ominaCliente','PlantillaN_ominaCliente.id','=','PuestoEmpleado.PlantillaN_ominaCliente')
            ->join('Cat_alogoPlazaN_omina','Cat_alogoPlazaN_omina.id','=','PlantillaN_ominaCliente.Cat_alogoPlazaN_omina')
            ->join('AreasAdministrativas','AreasAdministrativas.id','=','PlantillaN_ominaCliente.AreaAdministrativa')
            ->leftjoin('ChecadorEmpleadoFotografia','ChecadorEmpleadoFotografia.idEmpleado','=',"PuestoEmpleado.id")
            ->leftjoin('CelaRepositorio','CelaRepositorio.idRepositorio','=',"ChecadorEmpleadoFotografia.idRepositorio")
            ->where('Persona.Cliente','=',$cliente)->where('PuestoEmpleado.Estatus','=','1')
            #->where('Persona.idChecadorApp','=',$checador)
            ->where('Persona.id','=',$PuestoEmpleado)
            ->get();
        if(sizeof($result)>0){
            return $result;
        }else{            
            $arrayTemp = array();
            $data = [
                "idEmpleado"=>-1,
                "Nombre"=>"null",
                "Nfc_uid"=>"null",
                "idChecador"=>-1,
                "NoEmpleado"=>-1,
                "Cargo"=>"null",
                "AreaAdministrativa"=>"null",
                "NombrePlaza"=>"null",
                "Trabajador"=>"null",
                "Foto"=>"null",
            ];
            array_push($arrayTemp, $data);
            return $arrayTemp;
        }
    }
    public function ObtenerEmpleadosMasivoInt(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente' => 'required'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de completar los campo",
                'Code'=>223
            ]);
        }
        $Cliente = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $result = DB::table('Persona')->select("Persona.id as idEmpleado",DB::raw('CONCAT(Persona.Nombre," ",Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) AS Nombre'),'Persona.Nfc_uid','Persona.idChecadorApp as idChecador','Persona.N_umeroDeEmpleado as NoEmpleado','PuestoEmpleado.NombreDelCargo As Cargo','AreasAdministrativas.Descripci_on as AreaAdministrativa','Cat_alogoPlazaN_omina.Descripci_on AS NombrePlaza','TipoTrabajador.Descripci_on as Trabajador' ,DB::raw('CONCAT("https://suinpac.com/",CelaRepositorio.Ruta) as Foto'))
        ->join('PuestoEmpleado','Persona.id','=','PuestoEmpleado.Empleado')
        ->join('TipoTrabajador','TipoTrabajador.id','=','PuestoEmpleado.TipoTrabajador')
        ->join('PlantillaN_ominaCliente','PlantillaN_ominaCliente.id','=','PuestoEmpleado.PlantillaN_ominaCliente')
        ->join('Cat_alogoPlazaN_omina','Cat_alogoPlazaN_omina.id','=','PlantillaN_ominaCliente.Cat_alogoPlazaN_omina')
        ->join('AreasAdministrativas','AreasAdministrativas.id','=','PlantillaN_ominaCliente.AreaAdministrativa')
        ->join('ChecadorEmpleadoFotografia','ChecadorEmpleadoFotografia.idEmpleado','=',"PuestoEmpleado.id")
        ->join('CelaRepositorio','CelaRepositorio.idRepositorio','=',"ChecadorEmpleadoFotografia.idRepositorio")
        ->where('Persona.Cliente','=',$Cliente)->where('PuestoEmpleado.Estatus','=','1')
        #->where('Persona.idChecadorApp','=',$checador)
        #->where('Persona.id','=',$PuestoEmpleado)
        ->get();
        if(sizeof($result) > 0 ){
            return $result;
        }else{
            $arrayTemp = array();
            $data = [
                "idEmpleado"=>-1,
                "Nombre"=>"null",
                "Nfc_uid"=>"null",
                "idChecador"=>-1,
                "NoEmpleado"=>-1,
                "Cargo"=>"null",
                "AreaAdministrativa"=>"null",
                "NombrePlaza"=>"null",
                "Trabajador"=>"null",
                "Foto"=>"null",
            ];
            array_push($arrayTemp, $data);
            return $arrayTemp;
        }
    }
    function ObtenerBitacoraChecadorInt(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required|string'            
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de completar los campo",
                'Code'=>223
            ]);
        }
        $cliente = $request->Cliente;
        Funciones::selecionarBase($cliente);
        //NOTE: hacemos la consulta de los datos  para todos los dispositivos
        $result = DB::table('ChecadorBitacora')->select('*')->where('Tarea','>',99)->where('Tarea','<',200)->limit(1)->get();
        if($result){
            if(sizeof($result)>0){
                return $result;
            }else{
                //de esta manera se puede manejar mejor los errores en c#
                $errorData = array();
                $error = ["id"=> -1,
                            "idChecador"=> -1,
                            "Tarea"=>-1,
                            "Descripcion"=>"null"];
                array_push($errorData,$error);
                            return $errorData;
            }                
        }else{
            $errorData = array();
            $error = ["id"=> -1,
                        "idChecador"=> -1,
                        "Tarea"=> "Error al hacer la consulta",
                        "Descripcion"=>"Error al hacer la consulta"];
            array_push($errorData,$error);
                        return $errorData;
        }
    }
    function RegitrarChecadorInt(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required|string',
            'Nombre'=>'required|string',
            'Direccion'=>'required|string',
            'Sector'=>'required|string',
            'Puerto'=>'required|string',
            'Contrasenia'=>'required|string',
            'Usuario'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de completar los campo",
                'Code'=>223
            ]);
        }
        $cliente = $request->Cliente;
        $nombre = $request->Nombre;
        $direccion = $request->Direccion;
        $sector = $request->Sector;
        $puerto = $request->Puerto;
        $contrasenia = $request->Contrasenia;
        $usuario = $request->Usuario;
        Funciones::selecionarBase($cliente);
        $idChecador = DB::table('Checador')->insertGetId([
            'Nombre'=>$nombre,
            'Cliente'=>$cliente,
            'UID'=>'',
            'Estado'=>1,
            'FechaAlta'=>date('Y-m-d H:i:s'),
            'UltimaConexion'=>date('Y-m-d H:i:s'),
            'Password'=>$contrasenia,
            'AplicaSector'=>$sector == -1 ? 0 : $sector,
            'Banner'=> 0,
            'Ip'=>$direccion,
            'Puerto'=>$puerto,
            'Usuario'=>$usuario ]);
        if($idChecador){
            if($sector == -1){
                return response()->json([
                    'Status'=>true,
                    'Mensaje'=>$idChecador,
                    'Code'=>200
                ]);
            }else{
                $resultSector = DB::table('ChecadorSector')->where('id','=',$sector)->update(['idChecador'=>$idChecador]);
                return response()->json([
                    'Status'=>true,
                    'Mensaje'=>$idChecador,
                    'Code'=>200
                ]);
            }
        }else{
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Error al registrar dispositivo",
                'Code'=>223
            ]);
        }
    }
    function EnviarRespuestaSuinpac(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=> 'required|string',
            'Tarea'=>'required|string',
            'Checador'=>'required|string',
            'RTarea'=>'required|string',
            'DTarea'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de completar los campo",
                'Code'=>223
            ]);
        }
        $cliente = $request->Cliente;
        $tarea = $request->Tarea;
        $checador = $request->Checador;
        $rtarea = $request->RTarea;
        $dtarea = $request->DTarea;
        Funciones::selecionarBase($cliente);
        //NOTE: Eliminamos la tarea codigo 100>
        $result = DB::table('ChecadorBitacora')->where('id','=',$tarea)->delete();
        //NOTE: Creamos la respuesta de la tareas
        $request = DB::table('ChecadorBitacora')->insert(['idChecador'=>$checador,'Tarea'=>$rtarea,'Descripcion'=> $dtarea,'FechaTupla'=>date('y-m-d H:i:s')]);
        if($request){
            return response()->json([
                'Status'=> true ,
                'Mensaje'=>"Respuesta enviada...",
                'Code'=>200
            ]);
        }else{
            return response()->json([
                'Status'=> true ,
                'Mensaje'=>$request + "\n" + $result,
                'Code'=>203
            ]);
        }
    }
}
