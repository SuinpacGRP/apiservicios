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


class LuminariasController extends Controller
{
    public function __construct(){
        $this->middleware( 'jwt', ['except' => 
        [
            'obtenerCatalogoAreas',
            'GuardarCiudadano',
            'ObtenerMunicipio',
            'GuardarReporte',
            'ObtenerListaReportes',
            'ObtenerDatosCiudadano',
            'EditarCiudadano',
            'ObtenerReporte',
            'calcularConsumo']]);
    }
    //NOTE: tengo que hace un login por cada controlador :/

    public function obtenerCatalogoAreas(Request $request) {
        $Cliente = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $catalogo = DB::table("CatalogoSoporte")->select("*")->get();
        if($catalogo){
            if(sizeof($catalogo)>0){
                return [
                    'Status'=>true,
                    'Catalogo'=> $catalogo,
                    'Code'=>200
                ];
            }else{
                return [
                    'Status'=>false,
                    'Catalogo'=> [],
                    'code'=> 404
                ];
            }
        }else{
            return [
                'Status'=>false,
                'Catalogo'=> $catalogo,
                'code'=> 403
            ];
        }
    }
    public function GuardarCiudadano(Request $request){
        $datos = $request->all();
        $Cliente = $request->Cliente;
        $CURP = $request->curp;
        $Nombres = $request->Nombres;
        $Paterno = $request->Paterno;
        $Materno = $request->Materno;
        $Telefono = $request->Telefono;
        $Email = $request->Email;
        $RFC = $request->rfc;
        $date =  new \DateTime();
        $fechaActual = $date->format('Y-m-d H:i:s');
        $rules = [
            "Cliente"=>"required|string",
            "curp"=>"required|string",
            "Nombres"=>"required|string",
            "Paterno"=>"required|string",
            "Materno"=>"required|string",
            "Telefono"=>"required|string",
            "Email"=>"required|string",
            "rfc"=>"required|string",
        ];
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return [
                'Status'=>false,
                'Error'=> $validator->messages() ,
                'code'=> 403
            ];
        }
        Funciones::selecionarBase($Cliente);
        //NOTE: buscamos si el la CURP ya esta registrada
        $valido = DB::table("Ciudadano")->select("id")->where('Curp','=',$CURP)->get();
        if(sizeof($valido)==0){
            //INDEV: hacemos la insercion del ciudadano
            $inserted = db::table("Ciudadano")->insert([
                'id'=>null,
                'Nombre'=>$Nombres,
                'ApellidoPaterno'=>$Paterno,
                'ApellidoMaterno'=>$Materno,
                'Curp'=>$CURP,
                'Telefono'=>$Telefono,
                'CorreoElectronico'=>$Email,
                'FechaTupla'=>$fechaActual
            ]);
            $datosCliente = DB::table("Ciudadano")->select("id")->where("Curp","=",$CURP)->get();
            if($datosCliente){
                return [
                    "Status"=>true,
                    "Mensaje"=> $datosCliente,
                    "Code"=>200,
                ];
            }else{
                return [
                    "Status"=>false,
                    "Error"=>"Error al insertar Ciudadano",
                    "Code"=>403
                ];
            }
        }else{
            return [
                "Status"=>false,
                "Error"=>"CURP ya utilizada",
                "Code"=>423
            ];
        }
        
    }
    public function ObtenerMunicipio(Request $request){
        $ListaCliente = DB::table("Cliente")
                            ->select("Cliente.id","Cliente.Descripci_on as Municipio","EntidadFederativa.Nombre") 
                            ->join("DatosFiscalesCliente","Cliente.DatosFiscales","=","DatosFiscalesCliente.id")
                            ->join("EntidadFederativa","DatosFiscalesCliente.EntidadFederativa","=","EntidadFederativa.id")
                            ->where("Estatus","=",1)
                            ->where("EsMunicipio","=",1)
                            ->whereNotIn("Cliente.id",[63,64,65])
                            ->get();
        if( $ListaCliente ){ //NOTE: si no hay error en la consulta
            if(sizeof($ListaCliente) > 0 ){
                return [
                    'Status'=>true,
                    'Catalogo'=> $ListaCliente,
                    'Code'=>200
                ];
            }else{
                return [
                    'Status'=>false,
                    'Catalogo'=> $ListaCliente,
                    'code'=> 404
                ];
            }
        }else{
            return [
                'Status'=>false,
                'Mensaje'=> $ListaCliente,
                'code'=> 403
            ];
        }
    }
    public function GuardarReporte(Request $request){
        $datos = $request->all();
        $TemaCatalogo = $request->Tema;
        $Descripcion = $request->Descripcion;
        $UbicacionGPS = $request->gps;
        $Direccion = $request->direccion;
        $Referencia = $request->Referencia;
        $ciudadano = $request->Ciudadano;
        $Cliente = $request->Cliente;
        $Evidencia = $request->Evidencia;
        $date =  new \DateTime();
        $fechaActual = $date->format('Y-m-d H:i:s');
        $rules = [
            "Tema" => "required|string",
            "Descripcion"=>"required|string",
            "gps"=>"required|string",
            "direccion"=>"required|string",
            "Ciudadano"=>"required|numeric",
            "Cliente"=>"required|numeric",
            "Referencia"=>"required|string"
        ];
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return [
                'Status'=>false,
                'Error'=> $validator->messages() ,
                'code'=> 403
            ];
        }
        //NOTE: seleccionamos la base de datos
        Funciones::selecionarBase($Cliente);
        $insertado = db::table("Atenci_onCiudadana")->insert([
            'id'=>null,
            'TemaCatalogo'=>$TemaCatalogo,
            'FechaTupla'=>$fechaActual,
            "Estatus"=>1,
            "FechaProceso"=>null,
            "FechaAtendida"=>null,
            "FechaRechazada"=>null,
            "FechaSolucion"=>null,
            "Descripci_on"=>$Descripcion,
            "Ubicaci_onGPS"=>$UbicacionGPS,
            "Ubicaci_onEscrita"=>$Direccion,
            "Referencia"=>$Referencia,
            "ServidorPublico"=>null,
            "Ciudadano"=>$ciudadano,
            "MotivoRechazo"=>null,
            "Codigo"=>uniqid(),
            "ServidorPublicoRechazo"=>null,
            "ServidorPublicoAtendio"=>null,
        ]);
        if($insertado){
            //NOTE: insertamos las imagenes
            $idRegistro = DB::table("Atenci_onCiudadana")
                                ->select("id")
                                ->where("Ciudadano",'=',$ciudadano)
                                ->orderBy('FechaTupla','desc')->first();      
            if($idRegistro){
                $this->SubirImagen($Evidencia,$idRegistro->id,strval($fechaActual),$Cliente);
                return [
                    'Status'=>true,
                    'Mensaje'=> "OK",
                    'code'=> 200
                ];
            }else{
                //NOTE: no se inserto el reporte
                return [
                    'Status'=>true,
                    'Mensaje'=> "OK",
                    'code'=> 404
                ];
            }
        }else{
            return [
                'Status'=>false,
                'Error'=>$insertado,
                'code'=> 403
            ];
        }
    }
    public function SubirImagen($imagenes,$idRegistro,$Fecha,$Cliente){
        $arregloNombre = array();
        $arregloSize = array();
        $arregloRuta = array();
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
            Storage::disk('repositorio')->put($Cliente."/".$ruta."/".$imageName, base64_decode($image));
            #return $size;
            #return "repositorio/FotosAgua".$imageName;
            array_push($arregloNombre,$imageName);
            array_push($arregloSize,$size_in_bytes);
            array_push($arregloRuta,"repositorio/".$Cliente."/".$ruta."/".$imageName);
        }
        Funciones::selecionarBase($Cliente);
        //insertamos las rutas en celaRepositorio
        $contador = 0;
        //NOTE: se inserta en las evidencias del reporte
        foreach($arregloRuta as $ruta){
            $datos = DB::table("AtencionCiudadanaEvidencia")->insert([
                'id'=>null,
                'idAtencionCiudadana'=>$idRegistro,
                'TipoArchivo'=>null,
                'Ruta'=>$ruta,
                'FechaTupla'=>$Fecha,
                'NombreOriginal'=>$arregloNombre[$contador],
                'Size'=> $arregloSize[$contador],
                'Tipo'=>1,
                'Usuario'=>null,
                'Descripci_on'=>null
            ]);
            $contador++;
        }
    }
    public function ObtenerListaReportes(Request $request){
        $datos = $request->all();
        $Cliente = $request->Cliente;
        $Ciudadano = $request->Ciudadano;

        $rules = [
            'Cliente'=>"required|string",
            'Ciudadano'=>"required|string",
        ];

        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return [
                'Status'=>false,
                'Error'=> $validator->messages() ,
                'code'=> 403
            ];
        }
        Funciones::selecionarBase($Cliente);
        $Reportes = DB::table('Atenci_onCiudadana')
                                ->select(
                                        "CatalogoSoporte.descripci_on AS Tema", 
                                        "Atenci_onCiudadana.*",
                                        DB::raw("( SELECT CONCAT_WS(' ',Ciudadano.Nombre, Ciudadano.ApellidoPaterno , Ciudadano.ApellidoMaterno ) FROM  Ciudadano WHERE id = Atenci_onCiudadana.Ciudadano ) as Nombre"),
                                        "AreasAdministrativas.Descripci_on as Area",
                                        DB::raw("(SELECT GROUP_CONCAT(Ruta) FROM AtencionCiudadanaEvidencia WHERE idAtencionCiudadana = Atenci_onCiudadana.id ) as Rutas"),
                                        DB::raw("(SELECT CONCAT_WS(' ',Persona.Nombre,Persona.ApellidoPaterno,Persona.ApellidoMaterno) FROM Persona JOIN PuestoEmpleado ON (PuestoEmpleado.Empleado = Persona.id ) WHERE PuestoEmpleado.id = Atenci_onCiudadana.ServidorPublico ) as ServidorPublicoNombre"),
                                        DB::raw("(SELECT Persona.Tel_efonoCelular FROM PuestoEmpleado JOIN Persona ON (PuestoEmpleado.Empleado = Persona.id) WHERE PuestoEmpleado.id = Atenci_onCiudadana.ServidorPublico) as Telefono")
                                    )
                                ->join("CatalogoSoporte","CatalogoSoporte.id","=","Atenci_onCiudadana.TemaCatalogo")
                                ->join("Ciudadano","Ciudadano.id","=","Atenci_onCiudadana.Ciudadano")
                                ->join("AreasAdministrativas","AreasAdministrativas.id","=","CatalogoSoporte.idArea")
                                ->where("Atenci_onCiudadana.Ciudadano","=",$Ciudadano)->orderBy('FechaTupla','DESC')
                                ->get();
        if($Reportes){
            //NOTE: Se retornan los datos 
            if(sizeof($Reportes)>0){
                return [
                    "Status"=>true,
                    "Mensaje"=>$Reportes,
                    "Code"=>200
                ];
            }else{
                return [
                    "Status"=>true,
                    "Mensaje"=>"Sin registros",
                    "Code"=>404
                ];
            }
        }else{
            //NOTE: Error en la consulta
            return [
                "Status"=>false,
                "Mensaje"=> "Error en los datos",
                "Code"=>403
            ];
        }
    }
    public function ObtenerDatosCiudadano(Request $request){
        $datos = $request->all();
        $CURP = $request->Curp;
        $Cliente = $request->Cliente;

        $rules = [
            'Cliente'=>"required|string",
            'Curp'=>"required|string"
        ];
        
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return [
                'Status'=>false,
                'Error'=> $validator->messages() ,
                'code'=> 403
            ];
        }
        Funciones::selecionarBase($Cliente);
        $Ciudadano = DB::table('Ciudadano')
                        ->select('*')
                        ->where("Curp","=","".$CURP."")->get();
        if($Ciudadano){
            if(sizeof($Ciudadano)>0){
                return [
                    'Status' => true,
                    'Mensaje' => $Ciudadano,
                    'Code' => 200
                ]; 
            }else{
                return [
                    'Status' => true,
                    'Code' => 404
                ]; 
            }

        }else{
            return [
                'Status' => false,
                'Code' => 403
            ];
        }
    }
    public function EditarCiudadano (Request $request){ 
	    //Update Ciudadano SET Telefono = "7474906628" , CorreoElectronico = "hectorramirezrch@gmail.com" WHERE Curp = "RACH950920HGRMNC05";
        $datos = $request->all();
        $Cliente = $request->Cliente;
        $CURP = $request->Curp;
        $Telefono = $request->Telefono;
        $Email = $request->Email;
        $rules = [
            'Cliente'=>"required|string",
            'Curp'=>"required|string",
            'Telefono'=>"required|string",
            'Email'=>"required|string"
        ];
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return [
                'Status'=>false,
                'Error'=> $validator->messages() ,
                'code'=> 403
            ];
        }
        Funciones::selecionarBase($Cliente);
        //NOTE: hacemos el update del empleado 
        $actualizado = DB::table('Ciudadano')->where('Curp','=',$CURP)->update(['Telefono' => $Telefono, 'CorreoElectronico' =>$Email]);
        if($actualizado){
            //NOTE: Hacemos la consulta de los nuevo datos
            $ciudadano = DB::table('Ciudadano')->select('*')->where('Curp','=',$CURP)->get();
            return [
                'Status'=>true,
                'Mensaje'=>$ciudadano,
                'Code'=>200
            ];
        }else{
            return [
                'Status'=>false,
                'Mensaje'=>"Sin Cambios",
                'Code'=>404
            ];
        }
    }
    public function ObtenerReporte(Request $request){
        $datos = $request->all();
        $Cliente = $request->Cliente;
        $Ciudadano = $request->Ciudadano;
        $Reporte = $request->Reporte;
        $rules = [
            'Cliente'=>"required|string",
            'Ciudadano'=>"required|string",
            'Reporte'=>"required|string"
        ];
        Funciones::selecionarBase($Cliente);
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return [
                'Status'=>false,
                'Error'=> $validator->messages() ,
                'code'=> 403
            ];
        }
        //NOTE: validamos consulta  SELECT * FROM Atenci_onCiudadana WHERE id =54;

        $Reportes = DB::table('Atenci_onCiudadana')
                            ->select(
                                    "CatalogoSoporte.descripci_on AS Tema", 
                                    "Atenci_onCiudadana.*",
                                    "AreasAdministrativas.Descripci_on as Area",
                                    DB::raw("(SELECT GROUP_CONCAT(Ruta) FROM AtencionCiudadanaEvidencia WHERE idAtencionCiudadana = Atenci_onCiudadana.id ) as Rutas"),
                                    DB::raw("(SELECT CONCAT_WS(' ',Persona.Nombre,Persona.ApellidoPaterno,Persona.ApellidoMaterno) FROM Persona JOIN PuestoEmpleado ON (PuestoEmpleado.Empleado = Persona.id ) WHERE PuestoEmpleado.id = Atenci_onCiudadana.ServidorPublico ) as ServidorPublicoNombre"),
                                    DB::raw("(SELECT Persona.Tel_efonoCelular FROM PuestoEmpleado JOIN Persona ON (PuestoEmpleado.Empleado = Persona.id) WHERE PuestoEmpleado.id = Atenci_onCiudadana.ServidorPublico) as Telefono")
                                )
                            ->join("CatalogoSoporte","CatalogoSoporte.id","=","Atenci_onCiudadana.TemaCatalogo")
                            ->join("Ciudadano","Ciudadano.id","=","Atenci_onCiudadana.Ciudadano")
                            ->join("AreasAdministrativas","AreasAdministrativas.id","=","CatalogoSoporte.idArea")
                            ->where("Atenci_onCiudadana.Ciudadano","=",$Ciudadano)
                            ->where("Atenci_onCiudadana.id","=",$Reporte)
                            ->get();
        if($Reportes){
            return [
                'Status'=>true,
                'Mensaje'=> $Reportes,
                'code'=> 200
            ];
        }else{
            return [
                'Status'=>false,
                'Mensaje'=> $Reportes,
                'code'=> 404
            ];
        }
    }
    function ObtenConsumo($TipoToma, $Cantidad, $Cliente, $ejercicioFiscal){

        $ConfiguracionCalculo= $this->ObtenValorPorClave("CalculoAgua", $Cliente);
        if($ConfiguracionCalculo==1){


        //echo "<p>".$Cantidad."</p>";
        //if($ejercicioFiscal==2013 OR $ejercicioFiscal==2014 OR $ejercicioFiscal==2015 )
        //	$ejercicioFiscal=2012;
        $maximoValor=Funciones::ObtenValor("SELECT MAX(mts3) as Maximo FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND  TipoTomaAguaPotable=".$TipoToma, "Maximo");
        //if($Cantidad<10){
        //	$Cantidad=10;
        //}
        if($Cantidad>($maximoValor-1)){
        $restante=$Cantidad-($maximoValor-1);
        $Cantidad=$maximoValor;
        //echo "<p>"."SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND TipoTomaAguaPotable=".$TipoToma." AND mts3<=".intval($Cantidad)."</p>";

        $Importe100 = Funciones::ObtenValor("SELECT Importe as Suma FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND   TipoTomaAguaPotable=".$TipoToma." AND mts3=".intval($Cantidad), "Suma");
        $ValorDeMasDe100=$Importe100*$restante;

        $cantidad100 = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua  WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND TipoTomaAguaPotable=".$TipoToma." AND mts3>0 AND mts3<".intval($Cantidad), "Suma");

        $ImporteTotal = $cantidad100+$ValorDeMasDe100;

        }else{

        if($Cantidad!=0){
        $ImporteTotal = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND  TipoTomaAguaPotable=".$TipoToma." AND  mts3>0 AND mts3<=".intval($Cantidad), "Suma");
        }
        else{
            $ImporteTotal = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND  TipoTomaAguaPotable=".$TipoToma." AND  mts3=0", "Suma");

        }

        }

        return $this->truncateFloat($ImporteTotal,2);

        }else
        {
        $maximoValor=Funciones::ObtenValor("SELECT MAX(mts3) as Maximo FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND  TipoTomaAguaPotable=".$TipoToma, "Maximo");

        if($Cantidad>$maximoValor){
            $Importe = Funciones::ObtenValor("SELECT Importe as Suma FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND   TipoTomaAguaPotable=".$TipoToma." AND mts3=".intval($maximoValor), "Suma");
            $ImporteTotal=$Importe*$Cantidad;

        }else{
            $Importe = Funciones::ObtenValor("SELECT Importe as Suma FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND   TipoTomaAguaPotable=".$TipoToma." AND mts3=".intval($Cantidad), "Suma");
            $ImporteTotal=$Importe*$Cantidad;
        }
        if($Cantidad==0){
            $ImporteTotal = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND  TipoTomaAguaPotable=".$TipoToma." AND  mts3=0", "Suma");
        }

        return $this->truncateFloat($ImporteTotal,2);

        }
        }


    function ObtenValorPorClave($Clave, $Cliente){
        return Funciones::ObtenValor("SELECT Valor FROM ClienteDatos WHERE Cliente=".$Cliente." AND Indice='".$Clave."'", "Valor");

    }
    function truncateFloat($number, $digitos)
        {
            $raiz = 10;
            $multiplicador = pow ($raiz,$digitos);
            $resultado = ((int)($number * $multiplicador)) / $multiplicador;
            return number_format($resultado, $digitos,'.','');

        }
}
