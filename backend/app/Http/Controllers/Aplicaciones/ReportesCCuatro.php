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

class ReportesCCuatro extends Controller
{
    public function __construct(){ $this->middleware( 'jwt', ['except' => [
        'ObtenerTamanio',
        'realizarReporteC4',
        'RealizarReporteBotonRosa',
        'ActualizarCoordenadas',
        'RegistrarCiudadano',
        'ValidarCiudadano',
        'ActualizarDatosCiudadano',
        'ActualizarDatosPersonales',
        'ObtenerDatosDomicilio',
        'ActualizarDatosDomicilio',
        'ObtenerDatosContacto',
        'ActualizarDatosContacto'
        ]] ); }

    public function realizarReporteC4(Request $request){
        $datos = $request->all();
        $rules = [
            //'Cliente'=>'required|numeric',
            //'Nombre'=>'required|string',
            //'Telefono'=>'required|string',
            //'Problema'=>'required|string',
            //'Evidencia'=>'',
            'Locacion'=>'required|string',
            //'Direccion'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages(),
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        //INDEV: obtenemos los datos 
        $Cliente = $request->Cliente;
        $Nombre = $request->Nombre;
        $Telefono = $request->Telefono;
        $Evidencia = $request->Evidencia; //NOTE: es para las fotos
        $Locacion = $request->Locacion;
        $Direccion = $request->Direccion;
        $Problema = $request->Problema;
        $Estado = 1; // 1 = Realizada, 2 = Antendida, 0 = Eliminada
        $Fecha = date("Y/m/d");
        $fechaHora = date("Y-m-d H:i:s");
        //Seleccionamos el cliente
        Funciones::selecionarBase($Cliente);
        $idRepororte = DB::table('Reporte_C4')->insertGetId([
            'id' => null,
            'Nombre' => $Nombre , 
            'Telefono' => $Telefono, 
            'Problema' => $Problema, 
            'Fotos' => null, 
            'Ubicaci_onGPS' => $Locacion, 
            'Ubicaci_onEscrita' => $Direccion, 
            'Estado' => $Estado ,
            'Fecha' => $Fecha, 
            'FechaTupla' => $fechaHora,
            'Notificado' => 1,
        ]);
        $arregloFotos = [];
        $Fotos = "";
        foreach ($Evidencia as $item){
            $idFoto = $this->SubirImagenV3($item,$idRepororte,$Cliente,4866,"Reporte_C4");
            $Fotos .= "Foto " .($idFoto);
            array_push($arregloFotos,$idFoto);
        }
        //INDEV: actualizamos los datos de la foto $idRepororte
        if( sizeof($arregloFotos) > 0 ){
            $result = DB::table('Reporte_C4')->where('id','=',$idRepororte)->update(['Fotos'=> json_encode( $arregloFotos ) ]);
        }
        if(isset($idRepororte)){
            return [
                'Code'=>200,
                'Mensaje'=>"Ok",
            ];
        }else{
            return [
                'Code'=>403,
                'Mensaje'=>"Error al insertar reporte"
            ];
        }
    }
    public function SubirImagenV3($arregloFoto,$idRegistro,$Cliente,$usuario,$nombreTabla = 'Padr_onAguaPotableRLecturas'){
        $arregloNombre = array();
        $arregloSize = array();
        $arregloRuta = array();
        $datosRepo = "";
        $ruta = date("Y/m/d");
        $fechaHora = date("Y-m-d H:i:s");
            #return $arregloFoto;
            $image_64 = $arregloFoto; //your base64 encoded data
            $extension = explode('/', explode(':', substr($image_64, 0, strpos($image_64, ';')))[1])[1];   // .jpg .png .pdf
            $replace = substr($image_64, 0, strpos($image_64, ',')+1);
            // find substring fro replace here eg: data:image/png;base64,
            $image = str_replace($replace, '', $image_64);
            $image = str_replace(' ', '+', $image);
            $imageName = 'FotoAgua'.uniqid().'.'.$extension;
            #return base64_decode($image);
            $size_in_bytes = (int) (strlen(rtrim($image_64, '=')) * 3 / 4);
            $size_in_kb    = $size_in_bytes / 1024;
            $size_in_mb    = $size_in_kb / 1024;
            #return $size_in_bytes;
            Storage::disk('repositorio')->put($Cliente."/".$ruta."/".$imageName, base64_decode($image));
            #return $size;
            #return "repositorio/FotosAgua".$imageName;
            array_push($arregloNombre,$imageName);
            array_push($arregloSize,$size_in_bytes);
            array_push($arregloRuta,"repositorio/".$Cliente."/".$ruta."/".$imageName);
    
        Funciones::selecionarBase($Cliente);
        //insertamos las rutas en celaRepositorio
        //NOTE: se inserta en las evidencias del reporte
        foreach($arregloRuta as $ruta){
            $idRepositorio = DB::table("CelaRepositorio")->insertGetId([
                'Tabla'=>$nombreTabla,
                'idTabla'=>$idRegistro,
                'Ruta'=> $ruta,
                'Descripci_on'=>'Fotos de la aplicacion Agua',
                'idUsuario'=>$usuario,
                'FechaDeCreaci_on'=>$fechaHora,
                'Estado'=>1,
                'Reciente'=>1,
                'NombreOriginal'=>$arregloNombre[0],
                'Size'=>$arregloSize[0]
            ]);
        }
        return $idRepositorio;
    }
    public function RealizarReporteBotonRosa(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required|numeric',
            'Ciudadano'=>'required|numeric',
            'Tipo'=>'required|numeric',
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages(),
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        $Cliente = $request->Cliente;
        $Ciudadano = $request->Ciudadano;
        $Tipo = $request->Tipo;
        $fechaHora = date("Y-m-d H:i:s");
        $Fecha = date("Y/m/d");
        Funciones::selecionarBase($Cliente);
        $insertReporte = DB::table('HistorialReportesC4')->insertGetId([
            'idCiudadano'=>$Ciudadano,
            'TipoReporte'=>$Tipo,
            'FechaReporte'=>$fechaHora,
            'Fecha'=>$Fecha,
            'Estatus'=>1
        ]);
        if($insertReporte){
            return response()->json([
                'Status' => true,
                'Mensaje'=>$insertReporte,
                'Code' => 200 //Mensaje 223 campos incorrectos
            ]);
        }else{
            return response()->json([
                'Status' => false,
                'Mensaje'=>"Error al subir reporte",
                'Code' => 224 //Mensaje 223 campos incorrectos
            ]);
        }
    }
    public function ActualizarCoordenadas(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>"required|numeric",
            'Reporte'=>"required|string",
            'Direccion'=>'',
            'Ubicacion'=>'required|string',
            'Ciudadano'=>"required|numeric"
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages(),
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        
        $Ubicacion = $request->Ubicacion;
        $Direccion = $request->Direccion;
        $Reporte = $request->Reporte;
        $Cliente = $request->Cliente;
        $Ciudadano = $request->Ciudadano;
        $fechaHora = date("Y-m-d H:i:s");        
        Funciones::selecionarBase($Cliente);
        $Historial = DB::table('HistorialUbicacionReporteC4')->insert(
            ['Ubicacion_GPS'=>$Ubicacion,
             'idCiudadano'=>$Ciudadano,
             'FechaTupla'=>$fechaHora,
             'Direccion'=>$Direccion,
             'Reporte'=>$Reporte
            ]);
        if($Historial){
            $EstadoTracker = DB::table('HistorialReportesC4')->select('Estatus')->where('id','=',$Reporte )->get();
            return response()->json([
                'Status' => true,
                'Mensaje'=>"Actualizado",
                'Estado'=>$EstadoTracker[0]->Estatus,
                'Code' => 200 //Mensaje 223 campos incorrectos
            ]);
        }else{
            return response()->json([
                'Status' => false,
                'Mensaje'=>"Error al actualzar datos",
                'Code' => 224 //Mensaje 223 campos incorrectos
            ]);
        }
    }
    public function RegistrarCiudadano( Request $request ){
        $datos = $request->all();
        $rules =  [
            'Personales'=>"required|string",
            'Domicilio'=>"required|string",
            'Contactos'=>"required|string"
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages(),
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        $DatosPersonales = json_decode($request->Personales);
        $DatosDomicilio = json_decode($request->Domicilio);
        $datosContact = json_decode($request->Contactos);
        $Cliente = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        //NOTE: Verificamos que la CURP Este registrada
        $result = strtoupper($DatosPersonales->CURP);
        $datos = DB::select("SELECT ('".(strtoupper($DatosPersonales->CURP))."' = ( SELECT Curp FROM Ciudadano WHERE Curp = '".(strtoupper($DatosPersonales->CURP))."')) as Valido");
        if($datos[0]->Valido == 1 ){
            //NOTE: La CURP ya esta registrada en la app
            return [
                'Status'=>false,
                'Mensaje'=>'La CURP ingresada ya esta registrada',
                'Code'=>403
            ];
        }else{
            //NOTE: Extraemos los datos del json
            $Nombre = $DatosPersonales->Nombre;
            $ApellidoPaterno = $DatosPersonales->ApellidoP;
            $ApellidoMaterno = $DatosPersonales->ApellidoM;
            $Curp = $DatosPersonales->CURP;
            $Email = $DatosPersonales->Email;
            $Telefono = $DatosPersonales->Telefono;
            //NOTE: Extraemos los datos del domiclio del ciudadano
            $Localidad = $DatosDomicilio->Localidad;
            $Calle = $DatosDomicilio->Calle;
            $Numero = $DatosDomicilio->Numero;
            $Colonia = $DatosDomicilio->Colonia;
            $CodigoPostal = $DatosDomicilio->CodigoPostal;
            //NOTE: Extraemos los datos de los contactos de emergencia ( Primer Contacto )
            $ContactoUnoNombre = $datosContact->UnoNombre;
            $ContactoUnoTelefono = $datosContact->UnoTelefono;
            $ContactoUnoDireccion = $datosContact->UnoDireccion;
            $ContactoUnoEmail = $datosContact->UnoEmail;
            //NOTE: Extaemos los datos de los contactos de emergencia ( segundo Contacto )
            $ContactoDosNombre = $datosContact->DosNombre;
            $ContactoDosTelefono = $datosContact->DosTelefono;
            $ContactoDosDireccion = $datosContact->DosDireccion;
            $ContactoDosEmail = $datosContact->DosEmail;
            //pass encriptada
            $pass = $this->encript($DatosPersonales->Password);

            $idCiudadano = db::table("Ciudadano")->insertGetId([
                'id'=>null,
                'Nombre'=>$Nombre,
                'ApellidoPaterno'=>$ApellidoPaterno,
                'ApellidoMaterno'=>$ApellidoMaterno,
                'Curp'=>$Curp,
                'Telefono'=>$Telefono,
                'CorreoElectronico'=>$Email,
                'FechaTupla'=>date('Y-m-d H:i:s'),
                'TipoCiudadano'=>2,
                'Localidad'=>$Localidad,
                'Calle'=>$Calle,
                'Numero'=>$Numero,
                'Colonia'=>$Colonia,
                'CodigoPostal'=>$CodigoPostal,
                'Password'=>$pass
            ]);
            if($idCiudadano != 0){
                //NOTE: insertamos el primer contacto de emergencia
                $idContactUno = db::table("ContactosEmergenciaC4")->insertGetId([
                    'id'=>null,
                    'idCiudadano'=>$idCiudadano,
                    'Nombre'=>$ContactoUnoNombre,
                    'Telefono'=>$ContactoUnoTelefono,
                    'Direccion'=>$ContactoUnoDireccion,
                    'CorreoElectronico'=>$ContactoUnoEmail
                ]);
                //NOTE: insertamos el segundo ontacto de emergencia
                $idContactoDos = db::table("ContactosEmergenciaC4")->insertGetId([
                    'id'=>null,
                    'idCiudadano'=>$idCiudadano,
                    'Nombre'=>$ContactoDosNombre,
                    'Telefono'=>$ContactoDosTelefono,
                    'Direccion'=>$ContactoDosDireccion,
                    'CorreoElectronico'=>$ContactoDosEmail
                ]);
                return [
                    'Status'=>true,
                    'Mensaje'=>'Registro Exitoso',
                    'Ciudadano'=>$idCiudadano,
                    'Code'=>200
                ];
            }else{
                return [
                    'Status'=>true,
                    'Mensaje'=>'Error al registrar ciudadano',
                    'Code'=>402
                ];
            }
        }
    }
    public function ValidarCiudadano( Request $request ){
        $datos = $request->all();
        $rules =  [
            'Curp'=>"required|string",
            'Password'=>"required|string",
            'Cliente'=>"required|numeric"
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages(),
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        //NOTE: Validamos los datos del ciudadano tipo 2
        $curp = $request->Curp;
        $pass = $request->Password;
        Funciones::selecionarBase($request->Cliente);
        $Password = $this->encript($pass);
        $idCiudadano = DB::select("SELECT id FROM Ciudadano WHERE Curp ='$curp' AND TipoCiudadano = 2 AND Password = '$Password'");
        if( sizeof($idCiudadano) > 0 ){
            $Ciudadano = DB::table('Ciudadano')->select("Nombre as Nombre",
                                                    "ApellidoPaterno as ApellidoP",
                                                    "ApellidoMaterno as ApellidoM",
                                                    "Curp as CURP",
                                                    "CorreoElectronico as Email",
                                                    "Telefono as Telefono")
                                                    ->where( "id","=", $idCiudadano[0]->id )->get();
            if($idCiudadano != null ){
                return [
                        'Status'=>true,
                        'id' => $idCiudadano[0]->id,
                        'Ciudadano'=>$Ciudadano[0]];
            }else{
                return [
                    'Status'=>false,
                    'id' => 0,
                    'Ciudadano'=>null];
            }    
        }else{
            return [
                'Status'=>false,
                'id' => 0,
                'Ciudadano'=>null];
        }
        //NOTE: Obtenemos los datos del ciudadano 
        
    }
    public function ActualizarDatosCiudadano( Request $request ){
        $datos = $request->all();
        $rules =  [
            'Personales'=>"required|string",
            'Domicilio'=>"required|string",
            'Contactos'=>"required|string",
            'Cliente'=>"required|numeric"
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages(),
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        $DatosPersonales = json_decode($request->Personales);
        $DatosDomicilio = json_decode($request->Domicilio);
        $datosContact = json_decode($request->Contactos);
        $Cliente = $request->Cliente;
        //NOTE: Extraemos los datos del json
        $Nombre = $DatosPersonales->Nombre;
        $ApellidoPaterno = $DatosPersonales->ApellidoP;
        $ApellidoMaterno = $DatosPersonales->ApellidoM;
        $Curp = $DatosPersonales->CURP;
        $Email = $DatosPersonales->Email;
        $Telefono = $DatosPersonales->Telefono;
        //NOTE: Extraemos los datos del domiclio del ciudadano
        $Localidad = $DatosDomicilio->Localidad;
        $Calle = $DatosDomicilio->Calle;
        $Numero = $DatosDomicilio->Numero;
        $Colonia = $DatosDomicilio->Colonia;
        $CodigoPostal = $DatosDomicilio->CodigoPostal;
        //NOTE: Extraemos los datos de los contactos de emergencia ( Primer Contacto )
        $ContactoUnoNombre = $datosContact->UnoNombre;
        $ContactoUnoTelefono = $datosContact->UnoTelefono;
        $ContactoUnoDireccion = $datosContact->UnoDireccion;
        $ContactoUnoEmail = $datosContact->UnoEmail;
        //NOTE: Extaemos los datos de los contactos de emergencia ( segundo Contacto )
        $ContactoDosNombre = $datosContact->DosNombre;
        $ContactoDosTelefono = $datosContact->DosTelefono;
        $ContactoDosDireccion = $datosContact->DosDireccion;
        $ContactoDosEmail = $datosContact->DosEmail;


        Funciones::selecionarBase($Cliente);
        //NOTE: Obtenemos el id del ciudadano SELECT id FROM Ciudadano WHERE Curp = "RACH950920HGRMNC05";
        $idCiudadano = DB::table('Ciudadano')->select('id')->where('Curp','=',$Curp)->get();
        //NOTE: Actualizamos los datos personales encriptamos password
        $pass = $this->encript($DatosPersonales->Password);
        $personales = DB::table('Ciudadano')
            ->where('id','=',$idCiudadano[0]->id )->update(
                [
                    'Nombre'=>$Nombre,
                    'ApellidoPaterno'=>$ApellidoPaterno,
                    'ApellidoMaterno'=>$ApellidoMaterno,
                    'Curp'=>$Curp,
                    'Telefono'=>$Telefono,
                    'CorreoElectronico'=>$Email,
                    'FechaTupla'=>date('Y-m-d H:i:s'),
                    'TipoCiudadano'=>2,
                    'Localidad'=>$Localidad,
                    'Calle'=>$Calle,
                    'Numero'=>$Numero,
                    'Colonia'=>$Colonia,
                    'CodigoPostal'=>$CodigoPostal,
                    'Password'=>$pass
                ]);
        
        if ($personales){
            //NOTE: borramos los contactos del ciudadano
            $deleteDatos = DB::table('ContactosEmergenciaC4')->where('idCiudadano','=',$idCiudadano[0]->id)->delete();
            //NOTE: insertamos el primer contacto de emergencia
            $idContactUno = db::table("ContactosEmergenciaC4")->insertGetId([
                'id'=>null,
                'idCiudadano'=>$idCiudadano[0]->id,
                'Nombre'=>$ContactoUnoNombre,
                'Telefono'=>$ContactoUnoTelefono,
                'Direccion'=>$ContactoUnoDireccion,
                'CorreoElectronico'=>$ContactoUnoEmail
            ]);
            //NOTE: insertamos el segundo ontacto de emergencia
            $idContactoDos = db::table("ContactosEmergenciaC4")->insertGetId([
                'id'=>null,
                'idCiudadano'=>$idCiudadano[0]->id,
                'Nombre'=>$ContactoDosNombre,
                'Telefono'=>$ContactoDosTelefono,
                'Direccion'=>$ContactoDosDireccion,
                'CorreoElectronico'=>$ContactoDosEmail
            ]);
            return [
                'Status'=>true,
                'Mensaje'=>'Ciudadano Actualizado',
                'Ciudadano'=>$idCiudadano[0]->id,
                'Code'=>200
            ];
        }else{
            return [
                'Status'=>false,
                'Mensaje'=>'Error al actualziar ciudadano',
                'Code'=>203
            ];
        }
    }
    public function ObtenerDatosDomicilio( Request $request ){
        $datos = $request->all();
        $rules = [
            'Cliente'=>"required|numeric",
            'Ciudadano'=>"required|string"
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages(),
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        $Cliente = $request->Cliente;
        $Ciudadano = $request->Ciudadano;
        Funciones::selecionarBase($Cliente);
        //INDEV: SELECT Localidad,Calle,Numero,Colonia,CodigoPostal FROM Ciudadano  WHERE id = 1742;
        $DatosDomiclio = DB::table('Ciudadano')
            ->select('Localidad','Calle','Numero','Colonia','CodigoPostal')
            ->where('id','=',$Ciudadano)->get();
            return [
                'Status'=>sizeof($DatosDomiclio) > 0,
                'Datos'=>$DatosDomiclio
            ];
    }
    public function ActualizarDatosDomicilio( Request $request ){
        $datos = $request->all();
        $rules = [
            'Cliente'=>"required|numeric",
            'Ciudadano'=>"required|numeric",
            'DatosDomiclio'=>"required|string",


        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages(),
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        $Cliente = $request->Cliente;
        $Ciudadano = $request->Ciudadano;
        $Domcilio = json_decode($request->DatosDomiclio);
        $Localida = $Domcilio->Localidad;
        $Calle = $Domcilio->Calle;
        $Numero = $Domcilio->Numero;
        $Colonia = $Domcilio->Colonia;
        $CodigoPostal = $Domcilio->CodigoPostal;
        Funciones::selecionarBase($Cliente);
        $result = DB::table('Ciudadano')->where('id','=',$Ciudadano)->update(
                ['Localidad'=> $Localida,
                 'Calle'=>$Calle,
                 'Numero'=>$Numero,
                 'Colonia'=>$Colonia,
                 'CodigoPostal'=>$CodigoPostal
                ]);

        if($result){
            return [ 'Status' => true ];
        }else{
            return [ 'Status' => false, 'Mensaje'=>$result ];
        }

    }
    public function ObtenerDatosContacto( Request $request ){
        $datos = $request->all();
        $rules = [ 'Cliente'=>"required|numeric", 'Ciudadano'=>"required|numeric"];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages(),
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        $Cliente = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $Ciudadano = $request->Ciudadano;
        $Contactos = DB::table('ContactosEmergenciaC4')->select('*')->where('idCiudadano','=',$Ciudadano)->get();
        if($Contactos){
            return [
                'Status'=>true,
                'Data'=>$Contactos,
                'Code'=>200
            ];
        }else{
            return [
                'Status'=>false,
                'Error'=>$Contactos,
                'Code'=>203
            ];
        }
    }
    public function ActualizarDatosContacto( Request $request ){
        $datos = $request->all();
        $rules = [ 
            'Cliente'=>"required|numeric", 
            'Ciudadano'=>"required|numeric",
            'Contactos'=>"required|string"
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages(),
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        $Cliente = $request->Cliente;
        $Ciudadano = $request->Ciudadano;
        $DatosPersonales = json_decode($request->Contactos);
        //FIXME: esta es la peroforma de resilver pero lo hice rapido :c
        //NOTE:Extraemos los contactos del ciudadano UNO
        $UnoId = $DatosPersonales->UnoId;
        $UnoNombre = $DatosPersonales->UnoNombre;
        $UnoTelefono = $DatosPersonales->UnoTelefono;
        $UnoEmail = $DatosPersonales->UnoEmail;
        $UnoDireccion = $DatosPersonales->UnoDireccion;
        //NOTE: Extaremos los contados DOS
        $DosId = $DatosPersonales->DosId;
        $DosNombre = $DatosPersonales->DosNombre;
        $DosTelefono = $DatosPersonales->DosTelefono;
        $DosEmail = $DatosPersonales->DosEmail;
        $DosDireccion = $DatosPersonales->DosDireccion;
        Funciones::selecionarBase($Cliente);
        $unoResult = DB::table('ContactosEmergenciaC4')
                        ->where('id','=',$UnoId)
                        ->update(['Nombre'=>  $UnoNombre,
                                  'Telefono'=>  $UnoTelefono,
                                  'Direccion'=>  $UnoDireccion,
                                  'CorreoElectronico'=>  $UnoEmail]);
        $dosResult = DB::table('ContactosEmergenciaC4')
                        ->where('id','=',$DosId)
                        ->update(['Nombre'=>  $DosNombre,
                                  'Telefono'=>  $DosTelefono,
                                  'Direccion'=>  $DosDireccion,
                                  'CorreoElectronico'=>  $DosEmail]);
        if( $unoResult && $dosResult ){
            return [
                'Status'=>true,
                'Code'=>200
            ];
        }else{
            return [
                'Status'=>false,
                'ContactoUno'=>$unoResult,
                'ContactoDos'=>$dosResult
            ];
        }

    }
    public function ActualizarDatosPersonales( Request $request ){
        $datos = $request->all();
        $rules = [
            'Cliente'=>"required|numeric", 
            'Ciudadano'=>"required|numeric",
            'Personales'=>"required|string"
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages(),
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        $Cliente = $request->Cliente;
        $Ciudadano = $request->Ciudadano;
        $Personales = json_decode($request->Personales);
        //NOTE: Extraermos los datos personales
        $Nombre = $Personales->Nombre;
        $ApellidoP = $Personales->ApellidoP;
        $ApellidoM = $Personales->ApellidoM;
        $CURP = $Personales->CURP;
        $Email = $Personales->Email;
        $Telefono = $Personales->Telefono;
        $Password = $Personales->Password;
        Funciones::selecionarBase($Cliente);
        //NOTE: Actualizamos los datos del ciudadano
        $updatePersonales = DB::table('Ciudadano')
                        ->where('id','=',$Ciudadano)
                        ->update(['Nombre'=>  $Nombre,
                                  'ApellidoPaterno'=>  $ApellidoP,
                                  'ApellidoMaterno'=>  $ApellidoM,
                                  'Telefono'=>  $Telefono,
                                  'Curp'=>  $CURP,
                                  'CorreoElectronico'=>$Email
                                ]);
        //NOTE: Acctualizamos la contraseña del ciudadano
        if($Password != ""){
            //NOTE: encriptamos la pass y la guardamos
            $contrasenia = $this->encript($Password);
            $updatePass = DB::table('Ciudadano')
                        ->where('id','=',$Ciudadano)
                        ->update(['Password'=> $contrasenia]);
            if($updatePass ){
                return [
                    'Status'=>true,
                    'Code'=>200,
                ];
            }else{
                return [
                    'Status'=>false,
                    'Code'=>203,
                    'Data'=>$updatePersonales,
                    'update'=>$updatePass
                ];
            }
        }else{
            if($updatePersonales){
                return [
                    'Status'=>true,
                    'Code'=>200,
                ];
            }else{
                return [
                    'Status'=>false,
                    'Code'=>203,
                    'Error'=>$updatePersonales,
                    
                ];
            }
        }
        
    }
    const SLT = '4839eafada9b36e4e43c832365de12de';
    function encript($texto){
        return hash('sha256',self::SLT . $texto);
    }
}
