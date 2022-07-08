<?php

namespace App\Http\Controllers\PadronComercios;

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

class PadronComerciosController extends Controller
{
    public function __construct(){
        $this->middleware( 'jwt', ['except' => [
            'verificar_Usuario',
            'verificarAsistencia',
            'subirFotoEmpleadoTiny',
            'obtenerEmpleados',
            'obtenerEmpleadosGeneral',
            'horarioEmpleado',
            'verificarAsistenciaV4',
            'obtenerEmpleadoTareaV2',
            'registrarAsistenciaChecador',
            'verificarRolBaches',
            'userVoleto',
            'ObtenerCatalogo']] );
    }


        //INDEV: Metodos la aplicacion del padron simple
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

        public function ObtenerCatalogo(Request $request) {
            $Cliente = $request->cliente;
            $catalogo = DB::table("CatalogoSoporte")->select("*");
            if($catalogo){
                return responce()->json([
                    'Status'=>true,
                    'Catalogo'=>$catalogo
                ]);    
            }else{
                return responce()->json([
                    'Status'=>false,
                    'Catalogo'=>$catalogo
                ]);
            }
        }
        public function verificarRolPadronSimple(Request $request){
            $datos = $request->all();
            $rules = [
                'Usuario'=> 'required|string',
                'Cliente'=>'required|string'
            ]; 
            $validator = Validator::make($datos, $rules);
            if($validator->fails()){
                return responce()->json([
                    'Status'=>"Error",
                    'Error'=>$validator->messages()
                ]);
            }
            //Verificamos el rol de usuarios (Padr_onSimple)
            $Usuario = $request->Usuario;
            $Cliente = $request->Cliente; 
            Funciones::selecionarBase($Cliente);
            $result = DB::table('CelaUsuario')
                            ->select("Estatus")
                            ->join('PuestoEmpleado','CelaUsuario.idEmpleado','PuestoEmpleado.Empleado')
                            ->join('PlantillaN_ominaCliente','PuestoEmpleado.PlantillaN_ominaCliente','PlantillaN_ominaCliente.id')
                            ->where('PuestoEmpleado.Estatus','=',1)
                            ->where('CelaUsuario.EstadoActual','=',1)
                            ->where('CelaUsuario.Rol','=',430)
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
                                    'Status'=>false,
                                    'Mensaje'=>"Usuario no valido",
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
        public function verificarRolBaches(Request $request){
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
                            ->where('CelaUsuario.Rol','=',431)
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
        //
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
        //Metodo de prueba
        public function verificarAsistenciaOLD(Request $request){
            //Datos que necesito idGrupoEmpleado
            $idGrupo = $request->Grupo;
            $Cliente = $request->Cliente;
            $Hora = $request->Hora;
            $found = false;
            $actual = strtotime(date('Y-m-d').' '.$Hora);
            Funciones::selecionarBase($Cliente);
            //Buscamos al empleado por medio del idGrupoPersona
            $idEmpleado = DB::table('Asistencia_GrupoPersona')
                            ->select('PuestoEmpleado')
                            ->where('id','=',$idGrupo)->get();
            //Buscamos el horario del empleaos
            $dia = date('N');
            $result = DB::table('Asistencia_GrupoPersona')
                ->select(
                    'Asistencia_EmpleadoHorarioDetalle.HoraEntrada',
                    'Asistencia_EmpleadoHorarioDetalle.HoraSalida',
                    )
                ->leftjoin('Asistencia_EmpleadoHorario','Asistencia_EmpleadoHorario.idAsistencia_GrupoPersona','Asistencia_GrupoPersona.id')
                ->leftjoin('Asistencia_EmpleadoHorarioDetalle','Asistencia_EmpleadoHorario.id','Asistencia_EmpleadoHorarioDetalle.idAsistencia_EmpleadoHorario')
                ->where('Asistencia_GrupoPersona.PuestoEmpleado','=',$idEmpleado[0]->PuestoEmpleado)
                ->where('Asistencia_GrupoPersona.Estatus','=',"1")
                ->where('Asistencia_EmpleadoHorario.Dia','=',$dia)->get();
            //Obtenemos el historial de asistencias de hoy
            $historial = DB::table('Asistencia_')
                            ->select(DB::raw("CONCAT(DATE_FORMAT(NOW(),'%Y-%m-%d'),' ',Asistencia_Detalle.Hora) as Historial"),'Asistencia_Detalle.Tipo')
                            ->join('Asistencia_Detalle','Asistencia_.id','=','Asistencia_Detalle.idAsistencia')
                            ->where('Asistencia_.Fecha','=',date("Y-m-d"))
                            ->where('Asistencia_.idAsistenciaGrupoPersona','=',$idGrupo)
                            ->get();
            $horaSalidaAuxiliar = null;
            $registroFinal = null;
            foreach($result as $item){
                $horaEntrada = strtotime(date('Y-m-d').' '.$item->HoraEntrada. " - 30 minutes");
                $horaSalida = strtotime(date('Y-m-d').' '.$item->HoraSalida);
                foreach($historial as $historia){
                    $registro = strtotime($historia->Historial);
                    if($registro >= $horaEntrada && $registro <= $horaSalida ){
                        //Se verifican las entradas
                        if($actual >= $horaEntrada && $actual <= $horaSalida){ 
                            $found =  true;
                        }
                    }else{
                        if($horaSalidaAuxiliar != null){
                            if($registro >= $horaSalidaAuxiliar && $registro <= $horaEntrada){
                                //Funciona para el multiple horarrio
                                if($actual >= $horaSalidaAuxiliar && $actual <= $horaEntrada){
                                    $found = true;
                                }
                            }
                        }
                    }
                    $registroFinal = $historia;
                }
                $horaSalidaAuxiliar = $horaSalida;
            }
            if( $registroFinal != null ){
            $verificacionFinal = $registroFinal->Historial;
            //Validamos la salida anterior
            if(!$found){
                if(strtotime($verificacionFinal) >= $horaSalidaAuxiliar ){
                    //Verificamos el registro actual
                    if($actual >= $horaSalidaAuxiliar){
                        $found = true;
                    }
                }
            }
            }
            return response()->json([
                'Status'  => true,
                'encontrado' => $found ? "Encontrado": "No encontrado",
                'result' => $registroFinal,
                'SalidaFinal'=> date('H:i:s',$horaSalidaAuxiliar)
            ]);
            
        }
        public function verificarAsistenciaV2(Request $request){
            //Datos que necesito idGrupoEmpleado 
            $found = false;
            $idGrupoPersona = $request->Grupo;
            $Cliente = $request->Cliente;
            $Fecha = $request->Fecha;
            Funciones::selecionarBase($Cliente);
            //Buscamos si existe alguna asistencia ya registrada el dia de hoy
            $result = DB::table('Asistencia_')
                            ->select('id')
                            ->where('idAsistenciaGrupoPersona','=',$idGrupoPersona)
                            ->where('Fecha','=',$Fecha)->get();
            
            if(sizeof($result)>0){
                return $result[0]->id;
            }else{
                return "-1";
            }
        
            
        }
        public function subirFotoEmpleadoTiny (Request $request){
            $foto = $request->foto;
            $cliente  = $request->cliente;
            $nombreFoto = $request->nombre;
            $metaDataFoto = explode('-',$nombreFoto);
            $ruta = date("Y/m/d");
            $Fecha = date("Y-m-d H:i:s");
            #return $arregloFoto;
            $image_64 = $foto; //your base64 encoded data
            $extension = explode('/', explode(':', substr($image_64, 0, strpos($image_64, ';')))[1])[1];   // .jpg .png .pdf
            $replace = substr($image_64, 0, strpos($image_64, ',')+1);
            // find substring fro replace here eg: data:image/png;base64,
            $image = str_replace($replace, '', $image_64);
            $image = str_replace(' ', '+', $image);
            $imageName = 'Foto'.uniqid().'.'.$extension;
            #return base64_decode($image);
            $size_in_bytes = (int) (strlen(rtrim($image_64, '=')) * 3 / 4);
            $size_in_kb    = $size_in_bytes / 1024;
            $size_in_mb    = $size_in_kb / 1024;
            #return $size_in_bytes;
            $FullRuta = "/RecursoChecador/".$ruta."/".$imageName;
            Storage::disk('repositorio')->put($FullRuta, base64_decode($image));
            #return "repositorio/FotosAgua".$imageName;
            /*
            array_push($arregloNombre,$imageName);
            array_push($arregloSize,$size_in_bytes);
            array_push($arregloRuta,"repositorio/Luminaria/".$ruta."/".$imageName);
            */
            Funciones::selecionarBase($cliente);
            #$idtiny = DB::table("ChecadorEmpleadoFotografia")->select("id")->orderBy("FechaTupla",'desc')->first();
            $agregarRuta = DB::table("CelaRepositorio")->insert([
                'Tabla'=>'ChecadorEmpleadoFotografia',
                'idTabla'=>"ChecadorEmpleadoFotografia",
                'Ruta'=> "repositorio".$FullRuta,
                'Descripci_on'=>'Aplicacion Checador Fotografia',
                'idUsuario'=>3933,
                'FechaDeCreaci_on'=>strval($Fecha),
                'Estado'=>1,
                'Reciente'=>1,
                'NombreOriginal'=>$imageName,
                'Size'=>$size_in_bytes
            ]);
            $resultRepositorio = DB::table("CelaRepositorio")
            ->select("idRepositorio")
            ->where('Tabla','=','ChecadorEmpleadoFotografia')
            ->orderBy('FechaDeCreaci_on','desc')
            ->first();
            $agregarTinyEmpleado = DB::table("ChecadorEmpleadoFotografia")->insert([
                'id'=>null,
                'idRepositorio'=>$resultRepositorio->idRepositorio,
                'Nombre'=>$imageName,
                'Tama_nio'=>$size_in_bytes,
                'idEmpleado'=>$metaDataFoto[0],
                'FechaTupla'=>$Fecha
            ]);
            return $FullRuta;
        }
        public function userVoleto(Request $request){
            $date =  new \DateTime();
            $fechaActual = $date->format('Y-m-d H:i:s');
            $cadena = $request->cadena;
            $value = Funciones::DecodeThis2($cadena);
            $values = explode('/*/',$value);
            $cliente = "-1";
            $tikect = "-1";
            foreach($values as $item){
                if(str_contains($item,'Cliente')){
                    $metaData = explode("&",$item);
                    if(sizeof($metaData) >= 2){
                        $rawCliente = explode("=",$metaData[0]);
                        if(sizeof($rawCliente) >= 2){
                            $cliente = $rawCliente[1];
                        }
                        $rawPago = explode("=",$metaData[1]);
                        if(sizeof($rawPago) >= 2){
                            $tikect = $rawPago[1];
                        }
                    }
                }
            }
            /*
                SELECT b.id, b.Ticket , l.Nombre, m.Nombre FROM Pago p INNER JOIN PagoTicket pt ON(pt.Pago=p.id)
                INNER JOIN Boletos b ON(b.Ticket=pt.id)
                JOIN Contribuyente c ON (c.id = p.Contribuyente)
                JOIN Localidad l ON (l.id = c.Localidad_c)
                JOIN Municipio m ON (m.id = l.Municipio)
                WHERE p.id=512578;
            */ 
            if($cliente != "-1" || $tikect != "-1"){
                Funciones::selecionarBase($cliente);
                $idBoleto = DB::table('Pago')->select("Boletos.Tombola","Boletos.id","Boletos.Ticket","Municipio.Nombre","Boletos.FechaTombola")
                                ->join('PagoTicket','PagoTicket.Pago','=','Pago.id')
                                ->join('Boletos','Boletos.Ticket','=','PagoTicket.id')
                                ->join('Contribuyente','Contribuyente.id','=','Pago.Contribuyente')
                                ->leftjoin('Localidad','Localidad.id','=','Contribuyente.Localidad_c')
                                ->leftjoin('Municipio','Municipio.id','=','Localidad.Municipio')
                                ->where('Pago.id','=',$tikect)->get();

                if(sizeof($idBoleto)){
                    if(($idBoleto[0]->Tombola == 0)){ 
                        $completo = DB::table('Boletos')->where('id','=',$idBoleto[0]->id)->update(['Tombola'=>1,'FechaTombola'=>$date]);
                        if($completo){
                            $idBoleto[0]->FechaTombola = $date->format('Y-m-d H:i:s');
                            return response()->json([
                                'Status'  => true,
                                'Result' => $idBoleto, //NOTE: se Registro
                                'Code' => 200
                            ]);
                        }
                    }else{
                        return response()->json([
                            'Status'  => true,
                            'Result' => $idBoleto, //NOTE: Boleto ya usado
                            'Code' => 423
                        ]);
                    }
                }else{
                    return response()->json([
                        'Status'  => false,
                        'Result' => $value, //NOTE: Boleto no encontrado
                        'Code' => 404
                    ]);
                }
            }else{
                return response()->json([
                    'Status'  => false,
                    'Result' => null, //NOTE: Boleto no valido
                    'Code' => 403
                ]);
            }
        }
}


