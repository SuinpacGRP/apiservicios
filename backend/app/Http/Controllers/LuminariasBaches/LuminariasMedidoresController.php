<?php

namespace App\Http\Controllers\LuminariasBaches;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
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


class LuminariasMedidoresController extends Controller
{
    public function __construct(){
        $this->middleware( 'jwt', ['except' => 
        ['pruebaControladorGrupo',
        'verificar_Usuario',
        'insertarTareaAsistenciasRelleno']]);
    }

    public function pruebaControladorGrupo(Request $request){
        return "OK, OK";
    }
        //Metodo para verificar usuario Prueba de modificado
    public function verificar_Usuario(Request $request){
        $usuario =$request->usuario;
        $pass = $request->passwd;

        $credentials = $request->all();

        $rules = [
            'usuario'     => 'required|string',
            'passwd' => 'required|string',
        ];

        $validator = Validator::make($credentials, $rules);

        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => 'Error',
                'Error' => $validator->messages()
            ]);
        }
        #echo Carbon::now()->addMinutes(2)->timestamp;
        JWTAuth::factory()->setTTL(600);
        /*$token = auth()->attempt( ['Usuario' => $credentials['Usuario'], 'password' => $credentials['Contrase_na'], 'EstadoActual' => 1] );
        return $token;*/

        if ( !$token = auth()->attempt( ['Usuario' => $credentials['usuario'], 'password' => $credentials['passwd'], 'EstadoActual' => 1] ) ) {
            return response()->json([
                'Status' => false,
                'msj' => 'Usuario o Contraseña Incorrectos',
            ]);
        }

        $usuario = auth()->user();

            //Actualizas el valor de tu array usuario en cliente, si tenias el -1 te pones el 20
            #$usuario->Cliente = 40;
            return response()->json([
                'Status' => true,
                'msj' => 'Inicio de sesión',
                'token' => $token ,
                'datosUsuario' =>$usuario,
                'cliente'=>$usuario->Cliente,
                'idUsuario'=>$usuario->idUsuario
            ]);
    }
    public function VerificarSession(Request $request){
        $datos = $request->all();
        $Cliente = $request->Cliente;
        $rules = [
            'Cliente' => "required|string",
        ];
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return [
                'Status'=>false,
                'Error'=> $validator->messages() ,
                'code'=> 403
            ];
        }
        $Estatus = DB::table("Cliente")->select("Estatus")->where("id","=",$Cliente)->get();
        return [
            'Status'=>true,
            'Mensaje'=> $Estatus,
            'code'=> 200
        ];
    }
    public function CargarHistorialLuminaria(Request $request){
        $datos = $request->all();
        $rules = [
            "Cliente"=>"required|string"
        ];
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => false,
                'Error' => "Datos incompletos",
            ]);
        }
        $Cliente = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $hitorialLuminarias = DB::select("SELECT pl.id AS Padron, pl.ClaveLuminaria, IF(ContratoVigente = '','Sin Contraro', ContratoVigente) AS Contrato, h.Clasificaci_on AS clasificacion, tp.Descripci_on AS Tipo, h.Tipo AS id_Tipo
                                            FROM Padr_onLuminarias as pl
                                                LEFT JOIN Padr_onLuminariasHistorialLuminaria AS h ON (pl.id = h.id_Padr_on)
                                                LEFT JOIN TipoLuminarias tp ON ( tp.id_Luminaria = h.Tipo )
                                                GROUP BY pl.id");
        if($hitorialLuminarias){
            return response()->json([
                'Status'  => true,
                'result' => $hitorialLuminarias,
            ]);
        }else{
            return response()->json([
                'Status'  => false,
                'Error' => $hitorialLuminarias,
            ]);
        }
    }
    public function verificarRolLuminaria(Request $request){
        $datos = $request->all();
        $rules = [
            'Usuario'=> 'required|string',
            'Cliente'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => 'Error',
                'Error' => $datos
            ]);
        }
        $Usuario = $request->Usuario;
        $Cliente = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $result = DB::table('CelaUsuario')
                        ->select("Estatus")
                        ->join('PuestoEmpleado','CelaUsuario.idEmpleado','PuestoEmpleado.Empleado')
                        ->join('PlantillaN_ominaCliente','PuestoEmpleado.PlantillaN_ominaCliente','PlantillaN_ominaCliente.id')
                        ->where('PuestoEmpleado.Estatus','=',1)
                        ->where('CelaUsuario.EstadoActual','=',1)
                        ->where('CelaUsuario.Rol','=',421)
                        ->where('CelaUsuario.idUsuario','=',$Usuario)
                        ->where('CelaUsuario.Cliente','=',$Cliente)->get();
        if($result){
            return response()->json([
                'Status'=>true,
                'Mensaje'=>$result,
                'Code'=> 200
              ]);          
        }else{
            return response()->json([
                'Status'  => 'Error',
                'Error' => "Error al revisar las credenciales"
            ]);
        }
    }
    public function ObtenerCatlogosLuminarias(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => 'Error',
                'Error' => "Datos incompletos",
            ]);
        }
        $Cliente = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        //Catalogo de estado fisico
        $estadoFisico = DB::table('EstadoF_isico')->select("*")->get();
        //Catalogo de Tipo de luminarias
        $TipoLuminarias = DB::table('TipoLuminarias')->select("*")->get();

        return response()->json([
            'Status'=>true,
            'EstadoFisico'=>$estadoFisico,
            'TipoLuminaria'=>$TipoLuminarias,
            'Code'=> 200
          ]);   
    }
    public function ObtenerDatosCliente(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required|string'
        ];
        //Validador
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => 'Error',
                'Error' => $datos
            ]);
        }
        //Variables
        $Cliente = $request->Cliente;
        //Se toman los datos de SUINPAC general
        $municipio = DB::table("Cliente")
                        ->select("DatosFiscalesCliente.Municipio", "Municipio.Nombre","Cliente.EsMunicipio")
                        ->join("DatosFiscalesCliente","Cliente.DatosFiscales","=","DatosFiscalesCliente.id")
                        ->leftjoin("Municipio","DatosFiscalesCliente.Municipio","=","Municipio.id")
                        ->where('Cliente.id','=',$Cliente)
                        ->where('Cliente.Estatus',"=","1")
                        ->get();
        if($municipio){
            return response()->json([
                'Status'=>true,
                'Municipio'=>$municipio,
                'Code'=> 200
              ]);  
        }
    }
    public function GuardarLuminaria(Request $request){
        $datos = $request->all();
        $TipoPadron = $request->TipoPadron;
        if($TipoPadron == 1){
            $rules = [
                "Clave" => "required|string",
                "Municipio" => "required|string",
                "Localidad" => "required|string",
                "Cliente" => "required",
                "Latitud" => "required|string",
                "Longitud" => "required|string",
                "Voltaje" => "required|string",
                "Calsificacion" => "required|string",
                "Tipo" => "required|string",
                #"Evidencia" =>"required|string",  //Arreglo de fotos
                "Fecha" => "required|string",
                "Usuario" => "required|string",
                "LecturaActual" => "required|string",
                "LecturaAnterior" => "required|string",
                "Consumo" => "required|string",
                "Estado" => "required|string",
            ];
        }else{
            $rules = [
                "Clave" => "required|string",
                "Municipio" => "required|string",
                "Localidad" => "required|string",
                "Cliente" => "required|string",
                "Latitud" => "required",
                "Longitud" => "required",
                "Fecha" => "required|string",
                "Usuario" => "required|string",
                "LecturaActual" => "required|string",
                "LecturaAnterior" => "required",
                "Consumo" => "required",
                "Estado" => "required|string",
                #"Evidencia" =>"required|string",  //Arreglo de fotos
            ];
        }
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => false,
                'Error' => "Prueba de cambio",
                'Rules'=>$validator->messages(),
            ]);
        }
        //'Municipio'=>$this->SubirImagen($request->Fotos), llamada al metodo de carga
        $Cliente = $request->Cliente;
        //Datos para la insercion al padron
        $ClaveLuminaria = $request->Clave;
        $Municipio = $request->Municipio;
        $Localidad = $request->Localidad;
        $Ubicacion = $request->Ubicacion;
        $Estatus = $request->Estatus;
        $Latitud = $request->Latitud;
        $Longitud = $request->Longitud;
        $FechaTupla = date("Y-m-d H:i:s");
        //Datos para el historial de luminarias
        $Estado = $request->Estado;
        $Voltaje = $request->Voltaje;
        $Clasificacion = $request->Calsificacion;
        $Tipo = $request->Tipo;
        $Observacion = $request->Observacion;
        $Usuario = $request->Usuario;
        $Evidencia = $request->Evidencia;
        //Datos para el historial de los medidores
        $LecturaActual = $request->LecturaActual;
        $LecturaAnterior = $request->LecturaAnterior;
        $Consumo = $request->Consumo;
        $Contrato = $request->Contrato;
        Funciones::selecionarBase($Cliente);
        //Insertamos el registro al padron
        $result = DB::table("Padr_onLuminarias")->insert([
            'ClaveLuminaria'=>$ClaveLuminaria,
            'Municipio'=>$Municipio,
            'Localidad'=>$Localidad,
            'Ubicaci_on'=>$Ubicacion,
            'Cliente'=>$Cliente,
            'Estatus'=>1,
            'Tipo'=>$TipoPadron,
            'Latitud'=>strval($Latitud),
            'Longitud'=>strval($Longitud),
            'FechaTupla'=>strval($FechaTupla),
            'ContratoVigente'=>strval($Contrato)
        ]);
        //Verificamos el tipo de registor
        if($result){
            $ultimoId = DB::table('Padr_onLuminarias')->orderBy('id', 'desc')->first();
            if($TipoPadron == 1){ //Es Luminaria solo es para dar de alta en el padron
                $historial = DB::table('Padr_onLuminariasHistorialLuminaria')->insert([
                    'id_Padr_on'=>$ultimoId->id,
                    'id_Estado'=>$Estado,
                    'Voltaje'=>$Voltaje,
                    'Clasificaci_on'=>$Clasificacion,
                    'Tipo'=>$Tipo,
                    'Observaci_on'=>$Observacion,
                    'Evidencia'=>"",
                    'Fecha'=>strval($FechaTupla),
                    'Usuario'=>$Usuario
                ]);
                //Obtenemos el id del registro
                $idHistorial = DB::table('Padr_onLuminariasHistorialLuminaria')->orderBy('id', 'desc')->first();
                //Subimos las imagenes y obtenemos un json con los id
                $jsonRepo = $this->SubirImagen($Evidencia,$idHistorial->id,$Usuario,strval($FechaTupla),$Cliente);
                $actualizarDatos = DB:: table('Padr_onLuminariasHistorialLuminaria')->where('id', $idHistorial->id)->update([
                    'Evidencia'=>$jsonRepo
                ]);
            }else{          //Es Medidor solo es para dar de alta en el padron
                $historial = DB::table('Padr_onLuminariasMedidorHistorial')->insert([
                    'id_Padr_on'=>$ultimoId->id,
                    'id_Estado'=>$Estado,
                    'LecturaActual'=>$LecturaActual,
                    'LecturaAnterior'=>$LecturaAnterior,
                    'Consumo'=>$Consumo,
                    'Usuario'=>$Usuario,
                    'Observaci_on'=>$Observacion,
                    'Evidencia'=>"",
                    'Fecha'=>strval($FechaTupla)
                ]); 
                //Obtener el id del registro
                $idHistorial = DB::table('Padr_onLuminariasMedidorHistorial')->orderBy('id', 'desc')->first();
                //Subimos las imagenes y obtenemos un json con los id
                $jsonRepo = $this->SubirImagen($Evidencia,$idHistorial->id,$Usuario,strval($FechaTupla),$Cliente);
                $actualizarDatos = DB:: table('Padr_onLuminariasMedidorHistorial')->where('id', $idHistorial->id)->update([
                    'Evidencia'=>$jsonRepo
                ]);
            }
            return response()->json([
                'Status'=>true,
                'Mensaje'=>'OK',
                'Code'=> 200
              ]);  
        }else{
            return response()->json([
                'Status'=>false,
                'Mensaje'=>'Error al insertar registro',
                'Code'=> 224
              ]);  
        }
        
    }
    public function SubirImagen($imagenes,$idRegistro,$Usuario,$Fecha,$Cliente){
        $arregloNombre = array();
        $arregloSize = array();
        $arregloRuta = array();
        $arrayRepositorio = array();
        $ruta = date("Y/m/d");
        foreach ($imagenes as $arregloFoto){
            #return $arregloFoto;
            $image_64 = $arregloFoto; //your base64 encoded data
            $extension = explode('/', explode(':', substr($image_64, 0, strpos($image_64, ';')))[1])[1];   // .jpg .png .pdf
            $replace = substr($image_64, 0, strpos($image_64, ',')+1);
            // find substring fro replace here eg: data:image/png;base64,
            $image = str_replace($replace, '', $image_64);
            $image = str_replace(' ', '+', $image);
            $imageName = 'Luminaria'.uniqid().'.'.$extension;
            #return base64_decode($image);
            $size_in_bytes = (int) (strlen(rtrim($image_64, '=')) * 3 / 4);
            $size_in_kb    = $size_in_bytes / 1024;
            $size_in_mb    = $size_in_kb / 1024;
            #return $size_in_bytes;
            Storage::disk('repositorio')->put("/Luminaria/".$ruta."/".$imageName, base64_decode($image));
            #return $size;
            #return "repositorio/FotosAgua".$imageName;
            array_push($arregloNombre,$imageName);
            array_push($arregloSize,$size_in_bytes);
            array_push($arregloRuta,"repositorio/Luminaria/".$ruta."/".$imageName);
        }
        Funciones::selecionarBase($Cliente);
        //insertamos las rutas en celaRepositorio
        $contador = 0;
        foreach($arregloRuta as $ruta){
            $agregarRuta = DB::table("CelaRepositorio")->insert([
                'Tabla'=>'Padr_onLuminarias',
                'idTabla'=>$idRegistro,
                'Ruta'=> $ruta,
                'Descripci_on'=>'Aplicacion Luminarias',
                'idUsuario'=>$Usuario,
                'FechaDeCreaci_on'=>strval($Fecha),
                'Estado'=>1,
                'Reciente'=>1,
                'NombreOriginal'=>$arregloNombre[$contador],
                'Size'=>$arregloSize[$contador]
            ]);
            $ultimoCela=Funciones::ObtenValor("SELECT idRepositorio FROM CelaRepositorio ORDER BY idRepositorio DESC","idRepositorio");
            array_push($arrayRepositorio,$ultimoCela);
            $contador++;
        }
        $guardarUsuario = DB::table('CelaAccesos')->insert([
            'FechaDeAcceso'=> strval($Fecha),
            'idUsuario' => $Usuario,
            'Tabla' => 'Padr_onLuminarias',
            'IdTabla' => $idRegistro,
            'Acci_on' => 2
        ]);
        return json_encode($arrayRepositorio);
    }
    public function ObtenerLocalidadesMunicipio(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>"required|string",
            'Municipio'=>"required|string",
        ];
        //Validando los campos
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => false,
                'Error' => "Prueba de cambio",
                'Rules'=>$TipoPadron == 1
            ]);
        }
        $Cliente = $request->Cliente;
        $Municipio = $request->Municipio;
        Funciones::selecionarBase($Cliente);
        $resultMunicipios = DB::table("Localidad")->select("id","Nombre")->where("Municipio","=",$Municipio)->get();
        if($resultMunicipios){
            return response()->json([
                'Status'=>true,
                'Mensaje'=>$resultMunicipios,
                'Code'=> 200
              ]);  
        }else{
            return response()->json([
                'Status'=>false,
                'Mensaje'=>'Error al consultar sus localidades',
                'Code'=> 224
              ]);  
        }
    }
    public function GuardarHistorialLuminaria (Request $request){
        $datos = $request->all();
        $rules = [
            #"Clave"=>"required|string",
            #"Tipo"=>"required|string",
            #"Clasificacion"=>"required|string",
            #"Estado"=>"required|string",
            #"Cliente"=>"required|string",
            #"Voltaje"=>"required|string",
            #"Evidencia"=>"required|string",
            #"Latitud"=>"required|string",
            #"Longitud"=>"required|string",
            #"Usuario"=>"required|string",
            #"Fecha"=>"required|string",
            #"Observacion"=>"required|string"
        ];
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => false,
                'Error' => $datos,
                'Rules'=>$rules
            ]);
        }
        $Clave = $request->Clave;
        $Cliente = $request->Cliente;
        $Tipo = $request->Tipo;
        $Clasificacion = $request->Clasificacion;
        $Estado = $request->Estado;
        $Voltaje = $request->Voltaje;
        $Evidencia = $request->Evidencia;
        $Usuario = $request->Usuario;
        $FechaTupla = date("Y-m-d H:i:s");
        $Observacion = $request->Observacion;
        Funciones::selecionarBase($Cliente);
        $idPadron = DB::table("Padr_onLuminarias")->select("id")->where("ClaveLuminaria","=",$Clave)->get();
        $historial = DB::table('Padr_onLuminariasHistorialLuminaria')->insert([
            'id_Padr_on'=>$idPadron[0]->id,
            'id_Estado'=>$Estado,
            'Voltaje'=>$Voltaje,
            'Clasificaci_on'=>$Clasificacion,
            'Tipo'=>$Tipo,
            'Observaci_on'=>$Observacion,
            'Evidencia'=>"",
            'Fecha'=>strval($FechaTupla),
            'Usuario'=>$Usuario
        ]);
        if($historial){
            $idHistorial = DB::table('Padr_onLuminariasHistorialLuminaria')->orderBy('id', 'desc')->first();
            $jsonRepo = $this->SubirImagen($Evidencia,$idHistorial->id,$Usuario,strval($FechaTupla),$Cliente);
            $actualizarDatos = DB::table('Padr_onLuminariasHistorialLuminaria')
                                    ->where('id', $idHistorial->id)
                                    ->update([
                                            'Evidencia'=>$jsonRepo
                                            ]);
            return response()->json([
                'Status'=>true,
                'Mensaje'=>$Evidencia,
                'Code'=> 200
                ]); 
        }else{
            return response()->json([
                'Status'  => false,
                'Error' => "Error al insertar historial",
                'Rules'=>$TipoPadron == 1
            ]);
        }
        
    }
    public function GuardarHistoriallMedidor (Request $request){
        $datos = $request->all();
        $rules = [
            'Clave'=>"required|string",
            'Cliente'=>"required|string",
            'Estado' => "required|string",
            'LecturaActual'=>"required|string",
            'LecturaAnterior'=>"required|string",
            #'Consumo'=>"required|number",
            'Usuario'=>"required|string",
            #'Observacion'=>"required|string",
            #'Evidencia'=>"required|string",
            #'Fecha'=>"required|string"
        ];
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => false,
                'Error' => "Campos Vacios!!",
                'Rules'=>$rules
            ]);
        }
        $Cliente = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $Clave = $request->Clave;
        $Estado = $request->Estado;
        $LecturaActual = $request->LecturaActual;
        $LecturaAnterior = $request->LecturaAnterior;
        $Consumo = $request->Consumo;
        $Usuario = $request->Usuario;
        $Observacion = $request->Observacion;
        $Evidencia = $request->Evidencia;
        $FechaTupla = $request->Fecha;
        //Obtenemos el id del padron
        $idPadron = DB::table("Padr_onLuminarias")->select("id")->where("ClaveLuminaria","=",$Clave)->get();
        $historial = DB::table('Padr_onLuminariasMedidorHistorial')->insert([
            'id_Padr_on'=>$idPadron[0]->id,
            'id_Estado'=>$Estado,
            'LecturaActual'=>$LecturaActual,
            'LecturaAnterior'=>$LecturaAnterior,
            'Consumo'=>$Consumo,
            'Usuario'=>$Usuario,
            'Observaci_on'=>$Observacion,
            'Evidencia'=>"",
            'Fecha'=>strval($FechaTupla)
        ]); 
        if($historial){
            //Obtener el id del registro
            $idHistorial = DB::table('Padr_onLuminariasMedidorHistorial')->orderBy('id', 'desc')->first();
            //Subimos las imagenes y obtenemos un json con los id
            $jsonRepo = $this->SubirImagen($Evidencia,$idHistorial->id,$Usuario,strval($FechaTupla),$Cliente);
            $actualizarDatos = DB:: table('Padr_onLuminariasMedidorHistorial')->where('id', $idHistorial->id)->update([
                'Evidencia'=>$jsonRepo
        ]);
        return response()->json([
            'Status'=>true,
            'Mensaje'=>"Ok",
            'Code'=> 200
            ]); 
        }else{
            return response()->json([
                'Status'  => false,
                'Error' => "Error al insertar historial",
                'Rules'=>$TipoPadron == 1
            ]);
        }
    }
    public function CargarHistorialMedidor(Request $request){
        $datos = $request->all();
        $rules = [
            "Cliente"=>"required|string"
        ];
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => false,
                'Error' => "Prueba de cambio",
                'Rules'=>$TipoPadron == 1
            ]);
        }
        $Cliente = $request->Cliente;
        $UltimaFecha =(date("Y"))."-".(date("m") - 1)."-01";
        Funciones::selecionarBase($Cliente);
        //Cambiamos la consulta de historial
        $resultHistorialMedidor = DB::select('SELECT A.id_Padr_on AS Padron, pl.ClaveLuminaria, IF(ContratoVigente = "","Sin Contraro", ContratoVigente) AS Contrato, LecturaActual FROM Padr_onLuminariasMedidorHistorial as A 
        LEFT JOIN Padr_onLuminarias pl ON (pl.id = A.id_Padr_on)
        WHERE A.Fecha IN(SELECT MAX(B.Fecha) FROM Padr_onLuminariasMedidorHistorial AS B WHERE A.id_Padr_on=B.id_Padr_on) 
        GROUP BY A.id_Padr_on;
        ');
        if($resultHistorialMedidor){
            return response()->json([
                'Status'  => true,
                'result' => $resultHistorialMedidor,
            ]);
        }else{
            return response()->json([
                'Status'  => false,
                'Error' => $resultHistorialMedidor
            ]);
        }
    }
    public function insertarTareaAsistenciasRelleno( Request $request ){
        $datos = $request -> all();
        $rules = [
            'Cliente'=>"required|string",
            'Checador'=>"required|string",
        ];
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => false,
                'Error' => "Prueba de cambio",
                'Rules'=>$validator->messages()
            ]);
        }
        $Cliente = $request->Cliente;
        $Checador = $request->Checador;
        //INDEV: NOTE: verificamos si hay ua tarea igual en el checador
        Funciones::selecionarBase($Cliente);
        //NOTE: la tarea 4 es para insercion masiva de asistencias
        $listaTareas = DB::table("ChecadorBitacora")->select('id')->where('Tarea','=','4')->where('idChecador','=',$Checador)->get();
        if( sizeof($listaTareas) > 0 ){
            return 400;
        }else{
            $insertarTarea = DB::table("ChecadorBitacora")->insertGetId([
                'idChecador'=> $Checador,
                'Tarea'=> 4,
                'Descripcion'=>'Insercion de asistencias masivas',
            ]);
            if($insertarTarea != 0 ){
                return 200;
            }else{
                return 403;
            }
        }

        
    }
}