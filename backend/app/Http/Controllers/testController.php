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
use Illuminate\Auth\Events\Validated;
use Symfony\Component\CssSelector\Node\FunctionNode;

class testController extends Controller
{
    public function __construct(){
        $this->middleware( 'jwt', ['except' =>
        [
            'verificar_usuario_sistema',
            'listaCredenciales',
            'ObtenerFormato',
            'descargarLicencia',
            'ObtenerEmpleadosMasivoIntV2',
            'ObtenerEmpleadosMasivoIntV2CHECK',
            'ObtenerSectorCompleto',
            'ObtenerPeridoValidoDeclaracionInicial']] );
    }
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
            'Licencia' => ''
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
        Funciones::selecionarBase(32);
        $idDoble = DB::table('Padr_onDeAguaLectura')->select('id')
        ->where('Padr_onAgua','=',173907)
        ->where('Mes','=',11)
        ->where('A_no','=',2022)->orderBy('id')->limit(1)->get();
        /*$formatos = DB::select('SELECT
        ( SELECT CONCAT("https://suinpac.com/",Ruta) FROM CelaRepositorioC WHERE idRepositorio = ( SELECT Formato FROM CredencialFormato WHERE idCredencialFormato = '.$Licencia.')) AS Frente,
        ( SELECT CONCAT("https://suinpac.com/",Ruta) FROM CelaRepositorioC WHERE idRepositorio = ( SELECT FormatoAtras FROM CredencialFormato WHERE idCredencialFormato = '.$Licencia.')) AS Atras');*/
        return response()->json([
            "Status"  => true,
            "Code" => 200,
            'Mensaje' => $idDoble[0]->id,
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
    public function multarToma( Request $request ){
        $datos = $request->all();
        $rules = [
            'Usuario' => 'required|numeric',
            'Padron' => 'required|numeric',
            'Cliente'=>'required|numeric',
            'Debug'=>'required|numeric',
            'Total' => 'required|numeric',
            'Observacion'=> 'required|string',
            'Evidencia'=>'required',
            'Cordenadas'=>'required'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages()."\n Algunos datos del contrato no fueron enviados correctamente",
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        //NOTE: Obtener los datos del request
        $Usuario = $request->Usuario;
        $Padron = $request->Padron;
        $Cliente = $request->Cliente;
        $Debug = $request->Debug;
        $Total = $request->Total;
        $Observacion = $request->Observacion;
        //NOTE: Datos de ubicacion y evidencia
        $Evidencias = $request->Evidencia;
        $Coordenadas = $request->Cordenadas;
        //INDEV: hacemos la peticion a suinpac para realizar la multa
        $url = "https://hectordev.suinpac.dev/AplicacionDocumentos/MultarPadronAguaPotableAplicacion.php";
        $datosPost = array(
                "Usuario" => $Usuario,
                "idPadron" => $Padron,
                "Cliente" => $Cliente,
                "Debug" => $Debug,
                "Ejercicio" =>date('Y'),
                "Cotizaci_onInsert" => "Cotizaci_onInsert",
                "ConceptoCobroValor" => "5601",
                "Total" => $Total,
                'observaciones' => $Observacion
            );
            $options = array(
                'http' => array(
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($datosPost),
                )
            );
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $jsonRespuesta = explode("\n",$result);
        $respuesta = $jsonRespuesta[sizeof($jsonRespuesta)-1];
        $respuestaObjeto = json_decode($respuesta);
        //NOTE: verificamos que la multa se haya hecho
        if( $respuestaObjeto->Estatus ){
            Funciones::selecionarBase($Cliente);
            //NOTE: aqui empezamos la insercion a la tabla de control para la app
            $idControlMulta = DB::table('Padr_onAguaPotableMultasUbicacion')
            ->insertGetId([
                'id'=>null,
                'idCotizacion'=>$respuestaObjeto->Mensaje,
                'idPadron'=>$Padron,
                'Monto'=>$Total,
                'Usuario'=>$Usuario,
                'Coordenada'=>json_encode($Coordenadas),
                'Fotos'=>"",
                'FechaTupla'=>date("Y-m-d H:i:s")
            ]);
            //NOTE: insertamos las evidencias de la multa
            //NOTE: Subimos las imagenes de las evidencias con un ciclo
            $arregloFotosCela = [];
            foreach( $Evidencias as $Foto ){
                $idFoto = $this->SubirImagenV3($Foto,$respuestaObjeto->Mensaje,$Cliente,$Usuario,"Padr_onAguaPotableMultasUbicacion");
                array_push($arregloFotosCela,$idFoto);
            }
            #$idfotoToma = $this->SubirImagenV3($Evidencia['Toma'],$idControlMulta,$Cliente,$Usuario,"Padr_onAguaPotableMultasUbicacion","Toma");
            #$idFotoFachada = $this->SubirImagenV3($Evidencia['Fachada'],$idControlMulta,$Cliente,$Usuario,"Padr_onAguaPotableMultasUbicacion","Fachada");
            #$idFotoCalle = $this->SubirImagenV3($Evidencia['Calle'],$idControlMulta,$Cliente,$Usuario,"Padr_onAguaPotableMultasUbicacion","Calle");
            //NOTE: Creamos el el objeto que se va a guardar
            #$arregloFotosCela = array("Toma"=>$idfotoToma, "Fachada"=>$idFotoFachada, "Calle"=>$idFotoCalle);
            $updateControl = DB::table('Padr_onAguaPotableMultasUbicacion')
                            ->where('id',$idControlMulta)
                            ->update([ 'Fotos'=> json_encode($arregloFotosCela)]);

            return response()->json([
                'Status'=>$respuestaObjeto->Estatus,
                'Mensaje'=>$respuestaObjeto->Mensaje,
                'Code'=>$respuestaObjeto->Code
            ]);
        }else{
            return response()->json([
                'Status'=>$respuestaObjeto->Estatus,
                'Mensaje'=>$respuestaObjeto->Mensaje,
                'Code'=>$respuestaObjeto->Code
            ]);
        }
    }
    #Metodo para validar que el contrato no tenga mas de una multa
    public function ObtnerContratosMulta( Request $request ){
        $datos = $request->all();
        $rules = [
            'Padron' => 'required|numeric',
            'Cliente'=>'required|numeric',
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages()."\n Datos insuficientes",
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        $Cliente = $request->Cliente;
        $Padron = $request->Padron;
        Funciones::selecionarBase($Cliente);
        $TotalMultas = DB::table('ConceptoAdicionalesCotizaci_on')->
            select('Cotizaci_on.FolioCotizaci_on')->
                join('Cotizaci_on','Cotizaci_on.id','=','ConceptoAdicionalesCotizaci_on.Cotizaci_on')->
                join('Padr_onAguaPotable','Padr_onAguaPotable.id','=','Cotizaci_on.Padr_on')->
                    where('ConceptoAdicionales','=',5601)->
                    where('ConceptoAdicionalesCotizaci_on.Estatus','=',0)->
                    where('Padr_onAguaPotable.id','=',$Padron)->
                    orderBy('Cotizaci_on.FechaTupla')->
                    groupBy('ConceptoAdicionalesCotizaci_on.Cotizaci_on')->count();
        return response()->json([
            'Status' => true,
            'Mensaje'=> $TotalMultas ,
            'Code' => 200 //Mensaje 223 campos incorrectos
        ]);
    }
    #NOTE:Metodo de carga de imagenes
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
    #NOTE: Seccion de pruebas para asistencias
    public function ObtenerEmpleadosMasivoIntV2(Request $request){
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
        //INDEV: nueva consulta
        /*"SELECT
        Persona.id as idEmpleado,
        CONCAT_WS(" ",Persona.Nombre,Persona.ApellidoPaterno,Persona.ApellidoMaterno) as nombre,
        Persona.Nfc_uid,
        Persona.idChecadorApp,
        ( SELECT CONCAT("https://suinpac.com/",CelaRepositorio.Ruta) FROM EmpleadoFotografia JOIN CelaRepositorio on (CelaRepositorio.idTabla = EmpleadoFotografia.id) WHERE CelaRepositorio.Tabla = "EmpleadoFotografia" AND EmpleadoFotografia.idTipoFotografia = 6 AND EmpleadoFotografia.idPersona = Persona.id ) as Foto
        FROM Persona "*/
        $result = DB::table('Persona')->
            select("Persona.id as idEmpleado",
                    DB::raw('CONCAT_WS(" ",Persona.Nombre,Persona.ApellidoPaterno,Persona.ApellidoMaterno) as nombre'),
                    "Persona.Nfc_uid",
                    "Persona.idChecadorApp",
                    DB::raw('( SELECT CONCAT("https://suinpac.com/",CelaRepositorio.Ruta) FROM EmpleadoFotografia JOIN CelaRepositorio on (CelaRepositorio.idTabla = EmpleadoFotografia.id) WHERE CelaRepositorio.Tabla = "EmpleadoFotografia" AND EmpleadoFotografia.idTipoFotografia = 6 AND EmpleadoFotografia.idPersona = Persona.id ) as Foto'))
            ->where("Persona.Estatus","=","1")
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
    #NOTE: Seccion de pruebas para asistencias
    public function ObtenerEmpleadosMasivoIntV2CHECK(Request $request){
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
        //INDEV: nueva consulta
        /*"SELECT
        Persona.id as idEmpleado,
        CONCAT_WS(" ",Persona.Nombre,Persona.ApellidoPaterno,Persona.ApellidoMaterno) as nombre,
        Persona.Nfc_uid,
        Persona.idChecadorApp,
        ( SELECT CONCAT("https://suinpac.com/",CelaRepositorio.Ruta) FROM EmpleadoFotografia JOIN CelaRepositorio on (CelaRepositorio.idTabla = EmpleadoFotografia.id) WHERE CelaRepositorio.Tabla = "EmpleadoFotografia" AND EmpleadoFotografia.idTipoFotografia = 6 AND EmpleadoFotografia.idPersona = Persona.id ) as Foto
        FROM Persona "*/

        $result = DB::table('Persona')->
            select("Persona.id as idEmpleado",
                    DB::raw('Persona.Nfc_uid AS nfc'),
                    DB::raw('CONCAT_WS(" ",Persona.Nombre,Persona.ApellidoPaterno,Persona.ApellidoMaterno) as nombre'),
                    "Persona.Nfc_uid",
                    "Persona.idChecadorApp",
                    DB::raw('( SELECT CONCAT("https://suinpac.com/",CelaRepositorio.Ruta) FROM EmpleadoFotografia JOIN CelaRepositorio on (CelaRepositorio.idTabla = EmpleadoFotografia.id) WHERE CelaRepositorio.Tabla = "EmpleadoFotografia" AND EmpleadoFotografia.idTipoFotografia = 6 AND EmpleadoFotografia.idPersona = Persona.id ) as Foto'))
            ->where("Persona.Estatus","=","1")
            ->where('Persona.Cliente','=',$Cliente)
            ->whereNull('Persona.Nfc_uid')
            ->get();
        #$result = DB::table('Persona')->select("Persona.id as idEmpleado",
        #                                DB::raw('Persona.Nfc_uid AS nfc'),
        #                                DB::raw('CONCAT(Persona.Nombre," ",Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) AS Nombre'),
        #                                'Persona.Nfc_uid','Persona.idChecadorApp as idChecador','Persona.N_umeroDeEmpleado as NoEmpleado',
        #                                'PuestoEmpleado.NombreDelCargo As Cargo','AreasAdministrativas.Descripci_on as AreaAdministrativa',
        #                                'Cat_alogoPlazaN_omina.Descripci_on AS NombrePlaza','TipoTrabajador.Descripci_on as Trabajador' ,
        #                                DB::raw('( SELECT CONCAT(CelaRepositorio.Ruta) FROM EmpleadoFotografia JOIN CelaRepositorio on (CelaRepositorio.idTabla = EmpleadoFotografia.id) WHERE CelaRepositorio.Tabla = "EmpleadoFotografia" AND EmpleadoFotografia.idTipoFotografia = 6 AND EmpleadoFotografia.idPersona = Persona.id ) as rutaF'),
        #                                DB::raw('( SELECT CONCAT("https://suinpac.com/",CelaRepositorio.Ruta,"_cortada") FROM EmpleadoFotografia JOIN CelaRepositorio on (CelaRepositorio.idTabla = EmpleadoFotografia.id) WHERE CelaRepositorio.Tabla = "EmpleadoFotografia" AND EmpleadoFotografia.idTipoFotografia = 6 AND EmpleadoFotografia.idPersona = Persona.id ) as Foto'))
        #    ->join('PuestoEmpleado','Persona.id','=','PuestoEmpleado.Empleado')
        #    ->join('TipoTrabajador','TipoTrabajador.id','=','PuestoEmpleado.TipoTrabajador')
        #    ->join('PlantillaN_ominaCliente','PlantillaN_ominaCliente.id','=','PuestoEmpleado.PlantillaN_ominaCliente')
        #    ->join('Cat_alogoPlazaN_omina','Cat_alogoPlazaN_omina.id','=','PlantillaN_ominaCliente.Cat_alogoPlazaN_omina')
        #    ->join('AreasAdministrativas','AreasAdministrativas.id','=','PlantillaN_ominaCliente.AreaAdministrativa')
        #    ->leftjoin('ChecadorEmpleadoFotografia','ChecadorEmpleadoFotografia.idEmpleado','=',"PuestoEmpleado.id")
        #    ->leftjoin('CelaRepositorio','CelaRepositorio.idRepositorio','=',"ChecadorEmpleadoFotografia.idRepositorio")
        #    ->where('Persona.Cliente','=',$Cliente)->where('PuestoEmpleado.Estatus','=','1')#->where('Persona.Nfc_uid','!=','')
        #    #->whereNull('Persona.Nfc_uid')
        #    ->orderBy('Persona.id','ASC')
        #    #->limit(100)
        #    #->where('Persona.idChecadorApp','=',$checador)
        #    ->get();
        if(sizeof($result) > 0 ){
            //if ($cliente==31) {
                //$nfcC = array_map('trim', explode(",", $result["Nfc_uid"]));
                //if (count($nfcC)>1) {
                //    $result['Nfc_uid']=$nfcC[0];
                //}
                //$result['resultM']=$nfcC[0];
            //}
            #testController::cortarImagenes($result->rutaF);
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

    public function cortarImagenes($ruta){
        // Ruta de la imagen original
        $rutaOrigen = 'https://suinpac.com/'.$ruta;

        // Cargar la imagen original
        $imagenOriginal = imagecreatefromjpeg($rutaOrigen);

        // Obtener el ancho y alto originales
        $anchoOriginal = imagesx($imagenOriginal);
        $altoOriginal = imagesy($imagenOriginal);

        // Nuevo ancho deseado
        $nuevoAncho = 840;

        // Calcular la nueva altura manteniendo la proporción
        $nuevoAlto = (int) ($altoOriginal * ($nuevoAncho / $anchoOriginal));

        // Crear una nueva imagen con el nuevo tamaño
        $nuevaImagen = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

        // Redimensionar la imagen original en la nueva imagen
        imagecopyresampled($nuevaImagen, $imagenOriginal, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $anchoOriginal, $altoOriginal);

        // Ruta donde se guardará la imagen reducida
        $rutaDestino = $ruta.'_cortada';

        // Guardar la imagen reducida
        imagejpeg($nuevaImagen, $rutaDestino, 90); // 90 es la calidad de compresión (0-100)
        // Liberar memoria
        imagedestroy($imagenOriginal);
        imagedestroy($nuevaImagen);
    }

    #NOTE: funcion para obtener sector completo
    public function ObtenerSectorCompleto( Request $request ){
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
        $Sector = $request->Sector;
        $Usuario = $request->Usuario;
        //$Mes = $request->Mes;
        //$Anio = $request->Anio;
        Funciones::selecionarBase($Cliente);
        #
        #SELECT * FROM Padr_onAguaPotable WHERE Estatus = 1 AND Sector = 63
        if($Cliente==69){
        $idUsuario= DB::select("SELECT idUsuario FROM CelaUsuario WHERE Usuario='".$Usuario."'");
        $Ruta = DB::select("SELECT Ruta FROM Padr_onAguaPotableSectoresLecturistas WHERE idLecturista=".$idUsuario[0]->idUsuario." and idSector=".$Sector);
        $ContratosSector = DB::table('Padr_onAguaPotable')
            ->select("Padr_onAguaPotable.id",
                "Padr_onAguaPotable.ContratoVigente",
                "Padr_onAguaPotable.ContratoAnterior",
                "Padr_onAguaPotable.Medidor",
                "Padr_onAguaPotable.Domicilio",
                "Padr_onAguaPotable.NumeroDomicilio",
                "Padr_onAguaPotable.Ruta",
                "Padr_onAguaPotable.M_etodoCobro",
                "Padr_onAguaPotable.Consumo",
                "Padr_onAguaPotable.Estatus",
                "Padr_onAguaPotable.Cuenta",
                "Padr_onAguaPotable.Diametro",
                "Padr_onAguaPotable.Manzana",
                "Padr_onAguaPotable.Lote",
                DB::raw('COALESCE(CONCAT(Contribuyente.Nombres," ",Contribuyente.ApellidoPaterno," ",Contribuyente.ApellidoMaterno),Contribuyente.NombreComercial) as Contribuyente'),
                DB::raw('(SELECT if((SELECT ROUND(SUM(pt.Consumo)/12) FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = Padr_onAguaPotable.id ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12) < 20, (SELECT ptt.Consumo FROM Padr_onAguaPotable ptt WHERE ptt.id = Padr_onAguaPotable.id),(SELECT ROUND(SUM(pttt.Consumo)/12) FROM Padr_onDeAguaLectura pttt WHERE pttt.Padr_onAgua = Padr_onAguaPotable.id ORDER BY pttt.A_no DESC, pttt.Mes DESC LIMIT 12))) as Promedio'),
                "Municipio.Nombre as Municipio",
                "Localidad.Nombre as Localidad",
                "TipoTomaAguaPotable.Concepto as TipoToma",
                DB::raw("(SELECT Padr_onDeAguaLectura.TipoToma FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as Toma"),
                DB::raw("(SELECT Padr_onDeAguaLectura.A_no FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as A_no"),
                DB::raw("(SELECT Padr_onDeAguaLectura.Mes FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as Mes"),
                DB::raw("(SELECT Padr_onDeAguaLectura.Consumo FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as consumoAnterior"),
                DB::raw("(SELECT Padr_onDeAguaLectura.Status FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as Status"),
                DB::raw("(SELECT Padr_onDeAguaLectura.LecturaAnterior FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as LecturaAnterior"),
                DB::raw("(SELECT Padr_onDeAguaLectura.LecturaActual FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as LecturaActual"),
                DB::raw('(SELECT CONCAT_WS(" ",Padr_onAguaCatalogoAnomalia.clave,"-",Padr_onAguaCatalogoAnomalia.descripci_on) FROM Padr_onAguaCatalogoAnomalia WHERE  Padr_onAguaCatalogoAnomalia.id = (SELECT Padr_onDeAguaLectura.Observaci_on FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no,Padr_onDeAguaLectura.Mes DESC LIMIT 1 )) as Observaci_on')
                )
                #->join('Padr_onDeAguaLectura','Padr_onDeAguaLectura.Padr_onAgua','=','Padr_onAguaPotable.id') #JOIN Padr_onDeAguaLectura on ( Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id )
                ->join('Contribuyente','Padr_onAguaPotable.Contribuyente','=','Contribuyente.id' )
                ->join('Municipio','Padr_onAguaPotable.Municipio','=','Municipio.id')
                ->join('Localidad','Padr_onAguaPotable.Localidad','=','Localidad.id' )
                ->join('TipoTomaAguaPotable','TipoTomaAguaPotable.id','=','Padr_onAguaPotable.TipoToma')
                #->leftjoin('Padr_onAguaCatalogoAnomalia','Padr_onAguaCatalogoAnomalia.id','=','Padr_onDeAguaLectura.Observaci_on')
                    #->where('Padr_onAguaPotable.Estatus','=',1)
                    ->where('Sector','=',$Sector)
                    ->where('Ruta','=',$Ruta[0]->Ruta)
                    #->where('Mes','=',$Mes)
                    #->where('A_no','=',$Anio)
                    ->get();
        }else{
            $ContratosSector = DB::table('Padr_onAguaPotable')
            ->select("Padr_onAguaPotable.id",
                "Padr_onAguaPotable.ContratoVigente",
                "Padr_onAguaPotable.ContratoAnterior",
                "Padr_onAguaPotable.Medidor",
                "Padr_onAguaPotable.Domicilio",
                "Padr_onAguaPotable.NumeroDomicilio",
                "Padr_onAguaPotable.Ruta",
                "Padr_onAguaPotable.M_etodoCobro",
                "Padr_onAguaPotable.Consumo",
                "Padr_onAguaPotable.Estatus",
                "Padr_onAguaPotable.Cuenta",
                "Padr_onAguaPotable.Diametro",
                "Padr_onAguaPotable.Manzana",
                "Padr_onAguaPotable.Lote",
                DB::raw('COALESCE(CONCAT(Contribuyente.Nombres," ",Contribuyente.ApellidoPaterno," ",Contribuyente.ApellidoMaterno),Contribuyente.NombreComercial) as Contribuyente'),
                DB::raw('(SELECT if((SELECT ROUND(SUM(pt.Consumo)/12) FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = Padr_onAguaPotable.id ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12) < 20, (SELECT ptt.Consumo FROM Padr_onAguaPotable ptt WHERE ptt.id = Padr_onAguaPotable.id),(SELECT ROUND(SUM(pttt.Consumo)/12) FROM Padr_onDeAguaLectura pttt WHERE pttt.Padr_onAgua = Padr_onAguaPotable.id ORDER BY pttt.A_no DESC, pttt.Mes DESC LIMIT 12))) as Promedio'),
                "Municipio.Nombre as Municipio",
                "Localidad.Nombre as Localidad",
                "TipoTomaAguaPotable.Concepto as TipoToma",
                DB::raw("(SELECT Padr_onDeAguaLectura.TipoToma FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as Toma"),
                DB::raw("(SELECT Padr_onDeAguaLectura.A_no FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as A_no"),
                DB::raw("(SELECT Padr_onDeAguaLectura.Mes FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as Mes"),
                DB::raw("(SELECT Padr_onDeAguaLectura.Consumo FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as Consumo"),
                DB::raw("(SELECT Padr_onDeAguaLectura.Status FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as Status"),
                DB::raw("(SELECT Padr_onDeAguaLectura.LecturaAnterior FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as LecturaAnterior"),
                DB::raw("(SELECT Padr_onDeAguaLectura.LecturaActual FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as LecturaActual"),
                DB::raw('(SELECT CONCAT_WS(" ",Padr_onAguaCatalogoAnomalia.clave,"-",Padr_onAguaCatalogoAnomalia.descripci_on) FROM Padr_onAguaCatalogoAnomalia WHERE  Padr_onAguaCatalogoAnomalia.id = (SELECT Padr_onDeAguaLectura.Observaci_on FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no,Padr_onDeAguaLectura.Mes DESC LIMIT 1 )) as Observaci_on')
                )
                #->join('Padr_onDeAguaLectura','Padr_onDeAguaLectura.Padr_onAgua','=','Padr_onAguaPotable.id') #JOIN Padr_onDeAguaLectura on ( Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id )
                ->join('Contribuyente','Padr_onAguaPotable.Contribuyente','=','Contribuyente.id' )
                ->join('Municipio','Padr_onAguaPotable.Municipio','=','Municipio.id')
                ->join('Localidad','Padr_onAguaPotable.Localidad','=','Localidad.id' )
                ->join('TipoTomaAguaPotable','TipoTomaAguaPotable.id','=','Padr_onAguaPotable.TipoToma')
                #->leftjoin('Padr_onAguaCatalogoAnomalia','Padr_onAguaCatalogoAnomalia.id','=','Padr_onDeAguaLectura.Observaci_on')
                    #->where('Padr_onAguaPotable.Estatus','=',1)
                    ->where('Sector','=',$Sector)
                    #->where('Mes','=',$Mes)
                    #->where('A_no','=',$Anio)
                    ->get();
        }
        //NOTE: recorremos la lista para ingresar el primerdio
        $Prune = 0;
        $PadronContratos = [];
        foreach($ContratosSector as $Padron){
            //NOTE: Obtememos el promedio de la toma
            #SELECT ROUND(SUM(Consumo)/12) as Promedio FROM Padr_onDeAguaLectura WHERE Padr_onAgua = 149325 ORDER BY A_no DESC, Mes DESC LIMIT 12;
            //$objPromedio = DB::select('SELECT ROUND(SUM(Consumo)/12) as Promedio FROM ( SELECT pt.Consumo FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = '.($Padron->id).' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12 ) AS A');
            //$promedio = $objPromedio[0]->Promedio;
            $promedio = 0;
            if( $promedio == 0 ){
                $tipoToma = DB::select("SELECT Consumo FROM Padr_onAguaPotable WHERE id=".$Padron->id);
                $promedio = $tipoToma[0]->Consumo;
            }
            $data = array(
                "A_no"=>$Padron->A_no,
                "Consumo"=>$Padron->Consumo,
                "ContratoAnterior"=>$Padron->ContratoAnterior,
                "ContratoVigente"=>$Padron->ContratoVigente,
                "Contribuyente"=>$Padron->Contribuyente,
                "Cuenta"=>$Padron->Cuenta,
                "Diametro"=>$Padron->Diametro,
                "Domicilio"=>$Padron->Domicilio,
                "Estatus"=>$Padron->Estatus,
                "LecturaActual"=>$Padron->LecturaActual,
                "LecturaAnterior"=>$Padron->LecturaAnterior,
                "Localidad"=>$Padron->Localidad,
                "Lote"=>$Padron->Lote,
                "M_etodoCobro"=>$Padron->M_etodoCobro,
                "Manzana"=>$Padron->Manzana,
                "Medidor"=>$Padron->Medidor,
                "Mes"=>$Padron->Mes,
                "Municipio"=>$Padron->Municipio,
                "NumeroDomicilio"=>$Padron->NumeroDomicilio,
                "Observaci_on"=>$Padron->Observaci_on,
                "Ruta"=>$Padron->Ruta,
                "Status"=>$Padron->Status,
                "TipoToma"=>$Padron->TipoToma,
                "id"=>$Padron->id,
                "Toma"=>$Padron->Toma,
                "Promedio"=>$promedio
            );
            array_push($PadronContratos,$data);
        }
        return response()->json([
            'Status'=>true,
            'Mensaje'=> $PadronContratos,
            #'test' => $Prune,
            'Code'=>200
        ]);
    }
    public function ObtenerSectoresConfigurados ( Request $request ) {
        /**
         * SELECT Padr_onAguaPotableSector.Sector FROM Padr_onAguaPotableSectoresLecturistas
            JOIN Padr_onAguaPotableSector on (Padr_onAguaPotableSector.id = Padr_onAguaPotableSectoresLecturistas.idSector )
            WHERE idLecturista = 4001 AND Padr_onAguaPotableSectoresLecturistas.`Local` = 1;
            Padr_onAguaPotableSector.Sector as id, CONCAT_WS(" - ",Padr_onAguaPotableSector.Sector,Padr_onAguaPotableSector.Nombre) as Sector
         */
        $datos = $request->all();
        $rules = [
            'Cliente' => 'required|numeric',
            'Usuario' => 'required|numeric'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>$validator->messages()."\n Algunos datos del contrato no fueron enviados correctamente",
                'Code'=>223
            ]);
        }
        $Cliente = $request->Cliente;
        $Usuario = $request->Usuario;
        Funciones::selecionarBase($Cliente);
        $listaCliente = DB::table('Padr_onAguaPotableSectoresLecturistas')->
                            select(DB::raw('Padr_onAguaPotableSector.Sector as id'),DB::raw('CONCAT_WS(" - ",Padr_onAguaPotableSector.Sector,Padr_onAguaPotableSector.Nombre) as Sector'))->
                            join('Padr_onAguaPotableSector','Padr_onAguaPotableSector.id','=','Padr_onAguaPotableSectoresLecturistas.idSector')->
                            where('idLecturista','=',$Usuario)->where('Padr_onAguaPotableSectoresLecturistas.Local','=',1)->get();
        if(sizeof($listaCliente)>0){
            return response()->json([
                'Status'=>true,
                'Mensaje'=>$listaCliente,
                'Code'=>200
            ]);
        }else{
            return response()->json([
                'Status'=>false,
                'Mensaje'=>$listaCliente,
                'Code'=>100
            ]);
        }

    }
    #NOTE: funcion que obtiene los promedios de los contratos de agua

    #NOTE: Cambiar a prod
    public function ObtenerAnomaliasAgua( Request $request ){
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
        $resultAnomalias = DB::table('Padr_onAguaCatalogoAnomalia')->select("id","clave","descripci_on","AplicaFoto")->get();
        if(sizeof($resultAnomalias) > 0){
            return response()->json([
                'Status'=>true,
                'Datos'=>$resultAnomalias,
                'Code'=>200
            ]);
        }else{
            return response()->json([
                'Status'=>false,
                'Mesaje'=>"Datos no encontrados",
                'Code'=>200
            ]);
        }
    }
    public function ObtenerConfiguracionesAgua(Request $request){
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
        $TipoLectura = DB::table('ClienteDatos')->select('valor')->where('Cliente','=',$Cliente)->where('Indice','=',"ConfiguracionFechaDeLectura")->get();
        $bloquearCampos = DB::table('ClienteDatos')->select('valor')->where('Cliente','=',$Cliente)->where('Indice','=',"BloquerComposAppLecturaAgua")->get();
        return response()->json([
            'Status'=>true,
            'TipoLectura'=>$TipoLectura[0]->Valor,
            'BloquarCampos'=>$bloquearCampos[0]->Valor,
            'Code'=>200
        ]);
    }
    //NOTE: verificas is el empleado puede hacer otra declaracion inicial
    public function ObtenerPeridoValidoDeclaracionInicial( Request $request ) {
        $datos = $request->all();
        //Verifica el cliente
        $rules = [
            'Cliente' => 'required|numeric',
            'Clave' => 'required|string'
        ];
        $Cliente = $request->Cliente;
        $Clave = $request->Clave;
        $validator = Validator::make($datos, $rules);
        Funciones::selecionarBase($Cliente);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de completar los campo",
                'Code' => 223
            ]);
        }
        $datosFiscales = DB::table('DatosFiscales')->select('id')->where('RFC', '=' , $Clave )->get()->toArray();
        $arregloIdDatosFiscales = [];
        foreach($datosFiscales as $idFiscal){
            array_push($arregloIdDatosFiscales,$idFiscal->id);
        }
        //INDEV: Obtenemos el Empleado Activo para el puesto Actual
        $personaActiva = DB::table('Persona')->select('id')->whereIn('DatosFiscales',$arregloIdDatosFiscales)->where('EstatusPersona','=',1)->get();
        if(sizeof($personaActiva) < 1 ){ // el empleado no existe no se puede hacer nada ( Este caso no puede llegar a suceder  )
            return response()->json([
                'Status'=>true,
                'Valido'=> false,
                'Mensaje'=> "El empleado no exite en el cliente seleccionado id: ".$Cliente,
                'Code' => 200
            ]);
        }
        $personaBaja = DB::table('Persona')->select('id')->whereIn('DatosFiscales',$arregloIdDatosFiscales)->where('EstatusPersona','=',0)->orderBy('FechaInicio','DESC')->limit(1)->get();
        if(!$personaBaja){ //NOTE: el empleado esta dado de alta solo una vez se jusga por la cantidad de declaraciones del tipo inicial
            return response()->json([
                'Status'=>true,
                'Valido'=> false,
                'Mensaje'=> "El empleado no tiene historial",
                'Code' => 200
            ]);
        }
        $PuestoEmpleadoActivo = DB::table('PuestoEmpleado')->select('FechaInicioCargo','FechaTerminaci_onCargo')->where('Empleado','=',$personaActiva[0]->id)->get();
        $PuestoEmpleadoBaja = DB::table('PuestoEmpleado')->select('FechaInicioCargo','FechaTerminaci_onCargo')->where('Empleado','=',$personaBaja[0]->id)->orderBy('FechaTerminaci_onCargo','DESC')->limit(1)->get();
        $FechaPuestoBajaFinal = new \DateTime($PuestoEmpleadoBaja[0]->FechaTerminaci_onCargo);
        $fechaPuestoAltaInical = new \DateTime($PuestoEmpleadoActivo[0]->FechaInicioCargo);
        $diferencia = $fechaPuestoAltaInical->diff($FechaPuestoBajaFinal);
        if($datosFiscales){
            return response()->json([
                'Status'=>true,
                'Valido'=> $diferencia->days >= 61, //Si se dio
                'Mensaje'=> 'Anios: '.$diferencia->y." Meses: ".$diferencia->m." Dias: ".$diferencia->d." Total de dias: ".$diferencia->days."\n".'Fecha Baja: '. $PuestoEmpleadoBaja[0]->FechaTerminaci_onCargo ." Fecha Alta Nuevo:".$PuestoEmpleadoActivo[0]->FechaInicioCargo,
                'Code' => 200
            ]);
        }else{
            return response()->json([
                'Status'=>false,
                'Valido'=> false,
                'Mensaje'=>$datosFiscales,
                'Code' => 400
            ]);
        }

    }

}
