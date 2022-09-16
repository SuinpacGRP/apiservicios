<?php

namespace App\Http\Controllers\PortalNotarios;

use App\Funciones;
use App\CelaRepositorio;
use App\Modelos\Cotizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\ModelosNotarios\Observaciones;
use App\ModelosNotarios\PadronCatastral;
use App\ModelosNotarios\TramitesISAINotarios;
use App\Modelos\PadronCatastralTramitesISAINotarios;
use App\Libs\LibNubeS3;
use App\FuncionesFirma;
use App\Libs\Wkhtmltopdf;

use Illuminate\Support\Facades\Storage;
class PortalNotariosController extends Controller
{
    /**
     * ! Se asigna el middleware  al costructor
    */
    public function __construct()
    {
        $this->middleware( 'jwt', ['except' => ['getToken']] );
       
    }

    public function  validarAcceso(Request $request){

        $cliente=$request->Forma3DCC1;
        $correo=$request->correo;
        $id_padron=$request->Forma3DCC2;
        $clave_catastral=$request->claveCatastral;
        $cuenta_predial=$request->cuentaPredial;
        $id_cotizacion=$request->Forma3DCC3;
        //return $request;
        try {
            Funciones::selecionarBase($cliente);

        } catch (Exception $e) {
            return response()->json([
                'success' => '-1',
               
            ], 200);
        }

       #return $request;
       /*$Padron=DB::select("SELECT
            PC.id AS IdPadron,
            PC.Comprador AS IdComprador,
            PC.Cuenta AS ClaveCatastral,
            CONCAT_WS(' ' ,PC.Ubicaci_on, PC.Colonia) AS Ubicacion,
            PC.CuentaAnterior AS CuentaPredial,
            PCN.IdCotizacionISAI AS IdCotazacion,
            PCN.id as IdTramite,
            (SELECT CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial)) FROM `Contribuyente` c WHERE c.id=PC.Contribuyente ) AS Propietario, 
            C.CorreoElectr_onico, 
            PCN.IdCotizacionForma3,
            (SELECT NP.Correos FROM NotariosPublicos NP WHERE NP.Correos LIKE '".$correo."' ) AS CorreoNotario
        FROM
            Padr_onCatastral PC
            INNER JOIN Padr_onCatastralTramitesISAINotarios PCN ON ( PCN.IdPadron = PC.id ) 
            INNER JOIN Contribuyente C ON (C.id=PC.Contribuyente)
        WHERE
            PC.id =".$id_padron."
            AND PC.Cliente = ".$cliente."
            AND PC.Cuenta = '".$clave_catastral."'
            AND CuentaAnterior='".$cuenta_predial."'
            AND PCN.IdCotizacionForma3 =".$id_cotizacion." 
            HAVING ( C.CorreoElectr_onico LIKE 'notariapublicauno@gmail.com' OR  CorreoNotario LIKE 'notariapublicauno@gmail.com')"
            );*/


        $Padron = DB::table('Padr_onCatastral as PC')
            ->select('PC.id AS IdPadron', 'PC.Comprador AS IdComprador', 'PC.Cuenta AS ClaveCatastral', 'PC.CuentaAnterior AS CuentaPredial', 'PCN.IdCotizacionISAI AS IdCotazacion', 'PCN.id as IdTramite', 'C.CorreoElectr_onico as CorreoContribuyente', 'PCN.IdCotizacionForma3','PC.ValorPericial AS ValorPericial','PC.ValorFiscal AS ValorFiscal')
            ->selectRaw("CONCAT_WS(' ' ,PC.Ubicaci_on, PC.Colonia) AS Ubicacion")
            ->selectRaw("(SELECT CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial)) FROM Contribuyente c WHERE c.id=PC.Contribuyente ) AS Propietario")
            ->selectRaw("(SELECT NP.Correos FROM NotariosPublicos NP WHERE NP.Correos LIKE '$correo' ) AS CorreoNotario")
            ->join('Padr_onCatastralTramitesISAINotarios as PCN', 'PCN.IdPadron', '=', 'PC.id')
            ->join('Contribuyente as C', 'C.id', '=', 'PC.Contribuyente')
            ->where('PC.id', $id_padron)
            ->where('PC.Cliente', $cliente)
            ->where('PC.Cuenta', $clave_catastral)
            ->where('PC.CuentaAnterior', $cuenta_predial)
            ->where('PCN.IdCotizacionForma3', $id_cotizacion)
           # ->orHavingRaw("CorreoContribuyente LIKE '$correo' OR CorreoNotario LIKE '$correo'")
            ->get();
            
     if($Padron && count($Padron) > 0){
        return response()->json([
            'success' => '1',
            'cliente'=> $Padron
        ], 200);
     }else{
        return response()->json([
            'success' => '0',
           
        ], 200);
     }
   }

    public function  validarAccesoCambioCorreo(Request $request){

        $cliente=$request->Forma3DCC1;
        $correo=$request->correo;
        $id_padron=$request->Forma3DCC2;
        $clave_catastral=$request->claveCatastral;
        $cuenta_predial=$request->cuentaPredial;
        $id_cotizacion=$request->Forma3DCC3;
        //return $request;
        try {
            Funciones::selecionarBase($cliente);

        } catch (Exception $e) {
            return response()->json([
                'success' => '-1',

            ], 200);
        }

        $correoOriginal = Funciones::ObtenValor("SELECT c.CorreoElectr_onico FROM Padr_onCatastral pc
                                    INNER JOIN Contribuyente c ON (pc.Contribuyente = c.id)
                                    WHERE pc.CuentaAnterior ='".$cuenta_predial."' AND pc.Cuenta ='".$clave_catastral."' AND pc.id ='".$id_padron."' 
                                    ","CorreoElectr_onico");

        if($correoOriginal != "" || $correoOriginal != null){
            $arregloCorreos = explode(";",$correoOriginal);

            $bandera = false;

            foreach($arregloCorreos as $value){
                if($value == $correo){
                    $bandera = true;

                }
            }
            $correos="";
            if(!$bandera){
                $correos=$correoOriginal.";".$correo;

                $consultaUpdate = "UPDATE Contribuyente c
             INNER JOIN Padr_onCatastral pc ON (pc.Contribuyente = c.id)
             SET c.CorreoElectr_onico = '".$correos."'
             WHERE pc.CuentaAnterior ='".$cuenta_predial."' AND pc.Cuenta ='".$clave_catastral."'";
                DB::update($consultaUpdate);

            }

        }else{
            $consultaUpdate = "UPDATE Contribuyente c
             INNER JOIN Padr_onCatastral pc ON (pc.Contribuyente = c.id)
             SET c.CorreoElectr_onico = '".$correo."'
             WHERE pc.CuentaAnterior ='".$cuenta_predial."' AND pc.Cuenta ='".$clave_catastral."'";
            DB::update($consultaUpdate);
        }


        #return $request;
        /*$Padron=DB::select("SELECT
             PC.id AS IdPadron,
             PC.Comprador AS IdComprador,
             PC.Cuenta AS ClaveCatastral,
             CONCAT_WS(' ' ,PC.Ubicaci_on, PC.Colonia) AS Ubicacion,
             PC.CuentaAnterior AS CuentaPredial,
             PCN.IdCotizacionISAI AS IdCotazacion,
             PCN.id as IdTramite,
             (SELECT CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial)) FROM `Contribuyente` c WHERE c.id=PC.Contribuyente ) AS Propietario,
             C.CorreoElectr_onico,
             PCN.IdCotizacionForma3,
             (SELECT NP.Correos FROM NotariosPublicos NP WHERE NP.Correos LIKE '".$correo."' ) AS CorreoNotario
         FROM
             Padr_onCatastral PC
             INNER JOIN Padr_onCatastralTramitesISAINotarios PCN ON ( PCN.IdPadron = PC.id )
             INNER JOIN Contribuyente C ON (C.id=PC.Contribuyente)
         WHERE
             PC.id =".$id_padron."
             AND PC.Cliente = ".$cliente."
             AND PC.Cuenta = '".$clave_catastral."'
             AND CuentaAnterior='".$cuenta_predial."'
             AND PCN.IdCotizacionForma3 =".$id_cotizacion."
             HAVING ( C.CorreoElectr_onico LIKE 'notariapublicauno@gmail.com' OR  CorreoNotario LIKE 'notariapublicauno@gmail.com')"
             );*/


        $Padron = DB::table('Padr_onCatastral as PC')
            ->select('PC.id AS IdPadron', 'PC.Comprador AS IdComprador', 'PC.Cuenta AS ClaveCatastral', 'PC.CuentaAnterior AS CuentaPredial', 'PCN.IdCotizacionISAI AS IdCotazacion', 'PCN.id as IdTramite', 'C.CorreoElectr_onico as CorreoContribuyente', 'PCN.IdCotizacionForma3','PC.ValorPericial AS ValorPericial','PC.ValorFiscal AS ValorFiscal')
            ->selectRaw("CONCAT_WS(' ' ,PC.Ubicaci_on, PC.Colonia) AS Ubicacion")
            ->selectRaw("(SELECT CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial)) FROM Contribuyente c WHERE c.id=PC.Contribuyente ) AS Propietario")
            ->selectRaw("(SELECT NP.Correos FROM NotariosPublicos NP WHERE NP.Correos LIKE '$correo' GROUP BY NP.Correos) AS CorreoNotario")
            ->join('Padr_onCatastralTramitesISAINotarios as PCN', 'PCN.IdPadron', '=', 'PC.id')
            ->join('Contribuyente as C', 'C.id', '=', 'PC.Contribuyente')
            ->where('PC.id', $id_padron)
            ->where('PC.Cliente', $cliente)
            #->where('PC.Cuenta', $clave_catastral)
            ->whereRaw("REPLACE(PC.Cuenta,'-','') = ?",$clave_catastral)
            ->where('PC.CuentaAnterior', $cuenta_predial)
            ->where('PCN.IdCotizacionForma3', $id_cotizacion)
            # ->orHavingRaw("CorreoContribuyente LIKE '$correo' OR CorreoNotario LIKE '$correo'")
            ->get();

        if($Padron && count($Padron) > 0){
            return response()->json([
                'success' => '1',
                'cliente'=> $Padron,
                'clave catas' => $clave_catastral,
                'cuenta_pre' => $cuenta_predial,
                'correoOriginal' => $correoOriginal,

            ], 200);
        }else{
            return response()->json([
                'success' => '0',

            ], 200);
        }
    }



   public function getClienteNombre(Request $request){
         
    $cliente=$request->Cliente;
    
    Funciones::selecionarBase($cliente);

    $Cliente = DB::table('Cliente')
     ->where('id',$cliente)
     ->value('Descripci_on');
    
    

     return response()->json([
         'success' => '1',
         'cliente'=>$Cliente
         
     ], 200);
}

   public function  agregraObservacion(Request $request){
    Funciones::selecionarBase($request->Cliente);
    $observacion= new Observaciones;
    $observacion->id=null;

    $observacion->IdDocumento=$request->IdDocumento;
    $observacion->Observacion=$request->Observacion;
    $observacion->Origen=$request->Origen;
    $observacion->EstatusTercero=$request->EstatusTercero;
    $observacion->FechaTercero=$request->FechaTercero;
    $observacion->IdCatalogoDocumento=$request->IdCatalogoDocumento;
    $observacion->IdTramite=$request->IdTramite;
    $observacion->EstatusCatastro=0;

    $observacion->save();
   if($observacion){
    return response()->json([
        'success' => '1'
    ], 200);
   }
    
   }
   
    public function  modificarObservacion(Request $request){
        $cliente=$request->Cliente;
        $idObservacion=$request->IdObservacion;
        $status=$request->Status;
        Funciones::selecionarBase( $cliente);
        
        $observacion = Observaciones::where('id', $idObservacion)
        ->update(['EstatusTercero' => $status]);

        if($observacion){
            return response()->json([
                'success' => '1',
            ], 200);
        }else{
            return response()->json([
                'success' => '0',
            ], 200);
        }
    }

    public function obtenerDocumentosAyuntamiento(Request $request){
        $Cliente   = $request->Cliente;
        $IdPadron  = $request->idPadron;
        $IdTramite = $request->idTramite;
    
        Funciones::selecionarBase($Cliente);
    
        $Rutas = array();
    
        $Tramite = DB::table('Padr_onCatastralTramitesISAINotarios')->where('Id', $IdTramite)->first();
        
        $Forma = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
            ->select('Id', 'IdCotizacion', 'EstatusCatastro', 'EstatusTercero')
            ->where([
                ['IdTipoDocumento', '1'],
                ['ControlVersion', '1'],
                ['IdTramite', $IdTramite]
            ])->orderByDesc('Id')->get();
        
        if ( $Forma && count($Forma) > 0  ) {
            $Forma = $Forma[0];
            
            $DatosDocumento = Cotizacion::from('Cotizaci_on as c')
                ->select('ccc.Descripci_on as Concepto', 'c.id', 'c.FolioCotizaci_on', 'd.NombreORaz_onSocial as Nombre', 'ccc.Tiempo', 'd.id as did', 'cont.id as contid', 'x.uuid')
                ->selectRaw('(SELECT a.Descripci_on FROM AreasAdministrativas a WHERE a.id=c.AreaAdministrativa) AS Area')
                ->selectRaw('COALESCE((SELECT ec.id FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),(SELECT ec.id FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN EncabezadoContabilidad ec ON(ec.Pago=cac.Pago) WHERE cac.Cotizaci_on=c.id  LIMIT 1)) AS idContabilidad')
                ->selectRaw('COALESCE((SELECT N_umeroP_oliza FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),(SELECT ec.N_umeroP_oliza FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN EncabezadoContabilidad ec ON(ec.Pago=cac.Pago) WHERE cac.Cotizaci_on=c.id  LIMIT 1)) AS NumPoliza')
                ->selectRaw('COALESCE((SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),(SELECT DATE(ec.FechaP_oliza) FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN EncabezadoContabilidad ec ON(ec.Pago=cac.Pago) WHERE cac.Cotizaci_on=c.id  LIMIT 1)) AS Fechapago')
                ->selectRaw('(SELECT cd.Ruta FROM CatalogoDocumentos cd WHERE ccc.CatalogoDocumento =cd.id) AS DocumentoRuta')
                ->selectRaw('(SELECT cd.Nombre FROM CatalogoDocumentos cd WHERE ccc.CatalogoDocumento =cd.id) AS NombreDocumento')
                ->join('XMLIngreso as x', 'x.idCotizaci_on', '=', 'c.id')
                ->join('ConceptoAdicionalesCotizaci_on as cac', 'cac.Cotizaci_on', '=', 'c.id')
                ->join('ConceptoCobroCaja as ccc', 'ccc.id', '=', 'cac.ConceptoAdicionales')
                ->join('Contribuyente as cont', 'cont.id', '=', 'c.Contribuyente')
                ->join('DatosFiscales as d', 'd.id', '=', 'cont.DatosFiscales')
                ->where([
                    ['c.id', $Tramite->IdCotizacionForma3],
                    ['c.Cliente', $Cliente],
                    ['cac.Origen', '!=', "PAGO"],
                ])
                ->whereNotNull('ccc.CatalogoDocumento' )
                ->whereNull('cac.Adicional')
                ->first();

            $NombreArchivo = "Reporte_Forma3DCC".$DatosDocumento->idContabilidad.$DatosDocumento->id.".pdf";
            $s3 = new LibNubeS3($Cliente);
                
            $VerificarTramite = PadronCatastralTramitesISAINotarios::selectRaw('COUNT(Id) AS ParaFirma')
                    ->where('IdCotizacionForma3', $Tramite->IdCotizacionForma3)
                    ->whereNotNull('IdCotizacionISAI')
                    ->value('ParaFirma');
            
            if($VerificarTramite > 0){
                $Tabla   = "Reporte_Forma3DCC"; 
                $idTabla = $DatosDocumento['idContabilidad'].$DatosDocumento['id'];

                $NumeroDocumentos = CelaRepositorio::selectRaw('count(idRepositorio) as numero')
                    ->where([
                        ['Tabla', $Tabla],
                        ['idTabla', $DatosDocumento->idContabilidad.$DatosDocumento->id],
                    ])->value('numero');

                $urls = CelaRepositorio::where([
                        ["Tabla", $Tabla],
                        ["idTabla", $DatosDocumento->idContabilidad.$DatosDocumento->id]
                    ])->orderByDesc('idRepositorio')->get();

                if($NumeroDocumentos){
                    switch($NumeroDocumentos){
                        case 0:
                            $url = ReporteForma3DCC::generar($Tramite->IdCotizacionForma3, $Cliente, date("Y"));

                        break;

                        case 1:
                            $firmado = true;
                            foreach($urls as $url){
                                if($url->NombreOriginal == 'noexiste.pdf'){
                                    $firmado = false;
                                    break;
                                }
                            }
                            if($firmado)
                                $url = $urls[0]->Ruta;

                        break;

                        case 2:
                            $firmado = true;
                            foreach($urls as $url){
                                if($url->NombreOriginal == 'noexiste.pdf'){
                                    $firmado = false;
                                    break;
                                }
                            }
                            if($firmado){
                             $url =   FuncionesFirma::ObtenerDocumentoFirmado($idTabla, $Tabla, $s3, 0, $Cliente);

                             
                            }else
                                $url = CelaRepositorio::select('Ruta')->where([["Tabla", $Tabla ],["idTabla",$idTabla],['NombreOriginal', '!=', 'noexiste.pdf']])->value('Ruta');

                        break;
                    }
                }else
                    $url = ReporteForma3DCC::generar($Tramite->IdCotizacionForma3, $Cliente, date("Y"));

            }else
                $url = ReporteForma3DCC::generar($Tramite->IdCotizacionForma3, $Cliente, date("Y"));


            $NombreDocumento = DB::table('TipoDocumentoTramiteISAI')
            ->where('Id','1')
            ->select('Id','Nombre','Prioridad')
            ->first();

            $Rutas[1] = Array(
                "Ruta" => $url,
                "IdDocumentoControl" => $Forma->Id,
                "EstatusCatastro" => $Forma->EstatusCatastro,
                "EstatusTercero" => $Forma->EstatusTercero,
                "Nombre"=>$NombreDocumento->Nombre,
                "IdCotizacion"=>$Tramite->IdCotizacionForma3,
                "Prioridad"=>$NombreDocumento->Prioridad,
                "IdTipoDocumento"    =>$NombreDocumento->Id,
               
            );
        }
    
        $consultaDB = "SELECT *, TIMESTAMPDIFF(DAY, NOW(), DD.FechaVencimiento) AS DiasRestantes
            FROM ( SELECT c.id,
                    COALESCE( (SELECT ec.id FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1), (SELECT ec.id FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as idContabilidad ,
                    COALESCE((SELECT N_umeroP_oliza FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1), (SELECT ec.N_umeroP_oliza FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as NumPoliza ,
                    COALESCE((SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),  (SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as Fechapago,
                    ADDDATE( COALESCE((SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),  (SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1)), INTERVAL 180 DAY) as FechaVencimiento,
                    (SELECT cd.Ruta FROM CatalogoDocumentos cd WHERE ccc.CatalogoDocumento =cd.id  ) DocumentoRuta,
                    (SELECT cd.Nombre FROM CatalogoDocumentos cd WHERE ccc.CatalogoDocumento =cd.id  ) NombreDocumento
                FROM Cotizaci_on c
                    INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
                    INNER JOIN ConceptoAdicionalesCotizaci_on cac ON (cac.Cotizaci_on=c.id)
                    INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=cac.ConceptoAdicionales)
                WHERE
                    c.Padr_on = $IdPadron AND ccc.CatalogoDocumento IN ( 7, 3 )
                    AND c.Cliente = $Cliente 
                    AND cac.Adicional IS NULL
                    AND Origen = 'PAGO' HAVING Fechapago < FechaVencimiento
            ) as DD";

        #return $consultaDB;

        $Documentos = DB::select( $consultaDB );
                
        foreach ($Documentos as $valor){
            if($valor->DocumentoRuta == 'DeslindeCatastralFirma.php'){
                $name = 'DeslindeCatastral';
                $s3 = new LibNubeS3($Cliente);
                
                $idTabla = $valor->idContabilidad . $valor->id;
               
                $IdDocControl = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
                    ->select('Id', 'EstatusCatastro', 'EstatusTercero')
                    ->where('IdTipoDocumento', '2')->where('IdTramite', $IdTramite)
                    ->orderByDesc('Id')->first();#->take(1)->get();
    
                    $NombreDocumento = DB::table('TipoDocumentoTramiteISAI')
                    ->where('Id','2')
                    ->select('Id','Nombre','Prioridad')
                   ->first();
                $NumeroDocumentos = CelaRepositorio::selectRaw('count(idRepositorio) as numero')
                    ->where([
                        ['Tabla', $name],
                        ['idTabla', $valor->idContabilidad.$valor->id],
                    ])->value('numero');

                $urls = CelaRepositorio::where([
                        ["Tabla", $name],
                        ["idTabla", $valor->idContabilidad.$valor->id]
                    ])->orderByDesc('idRepositorio')->get();
                #return $urls;
                if($NumeroDocumentos){
                    switch($NumeroDocumentos){
                        case 0:
                            $url = "";
                        break;

                        case 1:
                            $firmado = true;
                            foreach($urls as $url){
                                if($url->NombreOriginal == 'noexiste.pdf'){
                                    $firmado = false;
                                    break;
                                }
                            }
                            if($firmado)
                                $url = $urls[0]->Ruta;
                        break;

                        case 2:
                            $firmado = true;
                            foreach($urls as $url){
                                if($url->NombreOriginal == 'noexiste.pdf'){
                                    $firmado = false;
                                    break;
                                }
                            }
                            
                            if($firmado)
                                $url = FuncionesFirma::ObtenerDocumentoFirmado($idTabla, $name, $s3, 0, $Cliente);
                               
                            else
                                $url = CelaRepositorio::select('Ruta')->where([["Tabla", $name], ["idTabla", $valor->idContabilidad.$valor->id],['NombreOriginal', '!=', 'noexiste.pdf']])->value('Ruta');
                        break;
                    }
                }else
                    $url = "";

                if($IdDocControl){
                    if ( $url != "" ){
                        $Rutas[2] = Array(
                            "Ruta"               => $url,
                            "IdDocumentoControl" => $IdDocControl->Id,
                            "EstatusCatastro"    => $IdDocControl->EstatusCatastro,
                            "EstatusTercero"     => $IdDocControl->EstatusTercero,
                            "DiasRestantes"      => $valor->DiasRestantes,
                            "FechaVencimiento"   => $valor->FechaVencimiento,
                            "Nombre"             => $NombreDocumento->Nombre,
                            "IdCotizacion"       => $valor->id,
                            "Prioridad"          =>$NombreDocumento->Prioridad,
                            "IdTipoDocumento"    =>$NombreDocumento->Id
                        );
                    }
                }
            }

            if($valor->DocumentoRuta=='Padr_onCatastralConstanciaNoAdeudoOK.php'){
                $name = 'ConstanciaNoAdeudoPredial';
                $s3 = new LibNubeS3($Cliente);
                $idTabla = $valor->idContabilidad . $valor->id;
                $IdDocControl = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
                    ->select('Id', 'EstatusCatastro', 'EstatusTercero')
                    ->where('IdTipoDocumento', '3')->where('ControlVersion', '1')->where('IdTramite', $IdTramite)
                    ->orderByDesc('Id')->first();
    
                    $NombreDocumento = DB::table('TipoDocumentoTramiteISAI')
                    ->where('Id','3')
                    ->select('Id','Nombre','Prioridad')
                   ->first();
                $NumeroDocumentos = CelaRepositorio::selectRaw('count(idRepositorio) as numero')
                    ->where([
                        ['Tabla', $name],
                        ['idTabla', $valor->idContabilidad.$valor->id],
                    ])->value('numero');
                
                $urls = CelaRepositorio::where([
                        ["Tabla", $name],
                        ["idTabla", $valor->idContabilidad.$valor->id]
                    ])->orderByDesc('idRepositorio')->get();

                if($NumeroDocumentos){
                    switch($NumeroDocumentos){
                        case 0:
                            $url = "";
                        break;

                        case 1:
                            $firmado = true;
                            foreach($urls as $url){
                                if($url->NombreOriginal == 'noexiste.pdf'){
                                    $firmado = false;
                                    break;
                                }
                            }
                            if($firmado)
                                $url = $urls[0]->Ruta;
                        break;

                        case 2:
                            $firmado = true;
                            foreach($urls as $url){
                                if($url->NombreOriginal == 'noexiste.pdf'){
                                    $firmado = false;
                                    break;
                                }
                            }
                            if($firmado)
                             $url =   FuncionesFirma::ObtenerDocumentoFirmado($idTabla, $name, $s3, 0, $Cliente);
                            else
                                $url = CelaRepositorio::select('Ruta')->where([["Tabla", $name], ["idTabla", $valor->idContabilidad.$valor->id],['NombreOriginal', '!=', 'noexiste.pdf']])->value('Ruta');
                                #$url = CelaRepositorio::where([["Tabla", $name],["idTabla", $valor->idContabilidad.$valor->id]])->first()->value('Ruta');
                        break;
                    }
                }else
                    $url = "";

                if($IdDocControl){
                    if ( $url != "" ){
                        $Rutas[3] = Array(
                            "Ruta"               => $url,
                            "IdDocumentoControl" => $IdDocControl->Id,
                            "EstatusCatastro"    => $IdDocControl->EstatusCatastro,
                            "EstatusTercero"     => $IdDocControl->EstatusTercero,
                            "DiasRestantes"      => $valor->DiasRestantes,
                            "FechaVencimiento"   => $valor->FechaVencimiento,
                            "Nombre"             => $NombreDocumento->Nombre,
                            "IdCotizacion"       => $valor->id,
                            "Prioridad"          =>$NombreDocumento->Prioridad,
                            "IdTipoDocumento"    =>$NombreDocumento->Id
                        );
                    }
                }
            }
           
        }

        if( count($Rutas) < 3 ){
            
            $Documentos = DB::table('Padr_onCatastralTramitesISAINotarios as tn')
                ->join('Padr_onCatastralTramitesISAINotariosDocumentos as pct', 'tn.Id', '=', 'pct.IdTramite')
                ->where('pct.Origen', '1')
                ->where('pct.ControlVersion', '1')
                ->where('pct.IdTramite', $IdTramite)
                ->whereIn('IdTipoDocumento', [ 2, 3 ])
                ->get();
            
            foreach ($Documentos as $valor){
                $descripcion = DB::table('TipoDocumentoTramiteISAI')
                ->where('Id', "$valor->IdTipoDocumento")
                ->select('Id','Nombre','Prioridad')
                ->first();
                $IdRepositorio = CelaRepositorio::where("Tabla", $valor->IdTipoDocumento)
                    ->where("idTabla", $valor->IdPadron )
                    ->where("Descripci_on", "$descripcion->Nombre")
                    ->orderByDesc('idRepositorio')
                    ->value("idRepositorio");
                
                $IdDocControl = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
                    ->select('Id', 'EstatusCatastro', 'EstatusTercero')
                    ->where('IdTipoDocumento', $valor->IdTipoDocumento)
                    ->where('IdTramite', $IdTramite)
                    ->orderByDesc('Id')->first();
                
                if($IdRepositorio && $IdRepositorio != ''){
                    if($IdDocControl){
                        if( !array_key_exists (  $valor->IdTipoDocumento ,  $Rutas ) ){
                            $Rutas[ $valor->IdTipoDocumento ] = Array(
                                "Ruta"               => $this->ObtieneRutaVisualizaPDF($IdRepositorio), 
                                "IdDocumentoControl" => $IdDocControl->Id, 
                                "EstatusCatastro"    => $IdDocControl->EstatusCatastro, 
                                "EstatusTercero"     => $IdDocControl->EstatusTercero, 
                                "DiasRestantes"      => 180, 
                                "FechaVencimiento"   => date( "Y-m-d", strtotime(date("Y-m-d")."+ 180 days") ),
                                "Nombre"             =>$descripcion->Nombre,
                                "Prioridad"=>$descripcion->Prioridad,
                                "IdTipoDocumento"    =>$descripcion->Id
                             );
                        }
                    }
                }else{
                    if( !array_key_exists (  $valor->IdTipoDocumento ,  $Rutas ) ){
                        $Rutas[ $valor->IdTipoDocumento ] = Array(
                            "Ruta"               => '', 
                            "IdDocumentoControl" => $IdDocControl->Id, 
                            "EstatusCatastro"    => $IdDocControl->EstatusCatastro, 
                            "EstatusTercero"     => $IdDocControl->EstatusTercero, 
                            "DiasRestantes"      => 180, 
                            "FechaVencimiento"   => date( "Y-m-d", strtotime(date("Y-m-d")."+ 180 days") ),
                            "Nombre"             =>$descripcion->Nombre,
                            "Prioridad"=>$descripcion->Prioridad,
                            "IdTipoDocumento"    =>$descripcion->Id
                         );
                    }
                }
    
            }
        }
    
        return $Rutas;
    }
    public function obtenerDocumentosAyuntamientoMario(Request $request){
        $Cliente   = $request->Cliente;
        $IdPadron  = $request->idPadron;
        $IdTramite = $request->idTramite;
    
        #return $request;
        Funciones::selecionarBase($Cliente);
    
        $Rutas = array();
    
        #$Tramite= ObtenValor("SELECT * FROM Padr_onCatastralTramitesISAINotarios WHERE Id=$IdTramite");
        $Tramite = DB::table('Padr_onCatastralTramitesISAINotarios')->where('Id', $IdTramite)->first();
    
        $Forma = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
            ->select('Id', 'IdCotizacion', 'EstatusCatastro', 'EstatusTercero')
            ->where([
                ['IdTipoDocumento', '1'],
                ['ControlVersion', '1'],
                ['IdTramite', $IdTramite]
            ])->orderByDesc('Id')->get();
        #return $Forma;
        if ( $Forma && count($Forma) > 0  ) {
            $Forma = $Forma[0];
    
            $Forma3DCC = DB::table('Cotizaci_on as c')->select('c.id')
                ->selectRaw('COALESCE( (SELECT ec.id FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1), (SELECT ec.id FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as idContabilidad')
                #->selectRaw('COALESCE((SELECT N_umeroP_oliza FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1), (SELECT ec.N_umeroP_oliza FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as NumPoliza')
                #->selectRaw('COALESCE((SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),  (SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as Fechapago')
                #->selectRaw('(SELECT cd.Ruta FROM CatalogoDocumentos cd WHERE ccc.CatalogoDocumento =cd.id  ) as DocumentoRuta')
                #->selectRaw('(SELECT cd.Nombre FROM CatalogoDocumentos cd WHERE ccc.CatalogoDocumento =cd.id ) as NombreDocumento')
                ->join('XMLIngreso as x', 'x.idCotizaci_on', '=', 'c.id')
                ->join('ConceptoAdicionalesCotizaci_on as cac', 'cac.Cotizaci_on', '=', 'c.id')
                ->join('ConceptoCobroCaja as ccc', 'ccc.id', '=', 'cac.ConceptoAdicionales')
                ->where([
                    ['c.Padr_on', $IdPadron],
                    ['c.id', $Tramite->IdCotizacionForma3],
                    #['c.id', (isset($Forma->IdCotizacion) ? $Forma->IdCotizacion : 0) ],
                    ['c.Cliente', $Cliente],
                    ['cac.Origen', "PAGO"],
                ])
                ->whereIn('ccc.CatalogoDocumento', [ 29 ] )
                ->whereNull('cac.Adicional')
                ->get();
    
            foreach ($Forma3DCC as $valor1){
                $name = "Reporte_Forma3DCC";
                $url= ReporteForma3DCC::generar($Tramite->IdCotizacionForma3, $Cliente, date("Y"));
                #$url= ReporteForma3DCC::generar($Forma->IdCotizacion, $Cliente, date("Y"));
                //$url = CelaRepositorio::where("Tabla", $name)->where("idTabla", $valor1->idContabilidad . $valor1->id )->value("Ruta");
                $NombreDocumento = DB::table('TipoDocumentoTramiteISAI')
                    ->where('Id','1')
                    ->select('Id','Nombre','Prioridad')
                    ->first();
                $Rutas[1] = Array(
                    "Ruta" => $url,
                    "IdDocumentoControl" => $Forma->Id,
                    "EstatusCatastro" => $Forma->EstatusCatastro,
                    "EstatusTercero" => $Forma->EstatusTercero,
                    "Nombre"=>$NombreDocumento->Nombre,
                    "IdCotizacion"=>$Tramite->IdCotizacionForma3,
                    "Prioridad"=>$NombreDocumento->Prioridad,
                    "IdTipoDocumento"    =>$NombreDocumento->Id
                );
            }
        }
    
        $consultaDB = "SELECT *, TIMESTAMPDIFF(DAY, NOW(), DD.FechaVencimiento) AS DiasRestantes 
            FROM 
                ( SELECT c.id,
                        COALESCE( (SELECT ec.id FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1), (SELECT ec.id FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as idContabilidad ,  
                        COALESCE((SELECT N_umeroP_oliza FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1), (SELECT ec.N_umeroP_oliza FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as NumPoliza , 
                        COALESCE((SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),  (SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as Fechapago,
                        ADDDATE( COALESCE((SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),  (SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1)), INTERVAL 180 DAY) as FechaVencimiento, 
                        (SELECT cd.Ruta FROM CatalogoDocumentos cd WHERE ccc.CatalogoDocumento =cd.id  ) DocumentoRuta,
                        (SELECT cd.Nombre FROM CatalogoDocumentos cd WHERE ccc.CatalogoDocumento =cd.id  ) NombreDocumento
                    FROM Cotizaci_on c
                        INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
                        INNER JOIN ConceptoAdicionalesCotizaci_on cac ON (cac.Cotizaci_on=c.id)
                        INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=cac.ConceptoAdicionales)
                    WHERE
                        c.Padr_on = $IdPadron
                        AND ccc.CatalogoDocumento IN ( 7, 3 ) 
                        AND c.Cliente = $Cliente
                        AND cac.Adicional IS NULL 
                        AND Origen = 'PAGO' HAVING Fechapago < FechaVencimiento 
                ) as DD";
    
        $Documentos = DB::select($consultaDB);
        #return $Documentos;
                
        foreach ($Documentos as $valor){
            if($valor->DocumentoRuta == 'DeslindeCatastralFirma.php'){
                $name='DeslindeCatastral';
    
                $IdDocControl = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
                    ->select('Id', 'EstatusCatastro', 'EstatusTercero')
                    ->where('IdTipoDocumento', '2')->where('IdTramite', $IdTramite)
                    ->orderByDesc('Id')->first();#->take(1)->get();
    
                $NombreDocumento = DB::table('TipoDocumentoTramiteISAI')
                ->where('Id','2')
                ->select('Id','Nombre','Prioridad')
               ->first();
                
                $url = CelaRepositorio::where("Tabla", $name)->where("idTabla", $valor->idContabilidad . $valor->id )->value("Ruta");
               /* $urls = CelaRepositorio::where("Tabla", $name)
                    ->where("idTabla", $valor->idContabilidad . $valor->id )
                    ->orderAsc('id')
                    ->get();*/

                //return 'Demo';
                /*for($urls as $url){

                }*/
                if($IdDocControl){
                    $Rutas[2] = Array(
                        "Ruta"               => $url, 
                        "IdDocumentoControl" => $IdDocControl->Id,
                        "EstatusCatastro"    => $IdDocControl->EstatusCatastro, 
                        "EstatusTercero"     => $IdDocControl->EstatusTercero, 
                        "DiasRestantes"      => $valor->DiasRestantes,
                        "FechaVencimiento"   => $valor->FechaVencimiento,
                        "Nombre"             => $NombreDocumento->Nombre,
                        "IdCotizacion"       =>$valor->id,
                        "Prioridad"          =>$NombreDocumento->Prioridad,
                        "IdTipoDocumento"    =>$NombreDocumento->Id
                    );
                }
            }
           
            if($valor->DocumentoRuta=='Padr_onCatastralConstanciaNoAdeudoOK.php'){
                $name='ConstanciaNoAdeudoPredial';
    
                $IdDocControl = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
                    ->select('Id', 'EstatusCatastro', 'EstatusTercero')
                    ->where('IdTipoDocumento', '3')->where('ControlVersion', '1')->where('IdTramite', $IdTramite)
                    ->orderByDesc('Id')->first();
    
                $NombreDocumento = DB::table('TipoDocumentoTramiteISAI')
                ->where('Id','3')
                ->select('Id','Nombre','Prioridad')
                ->first();
    
                $url = CelaRepositorio::where("Tabla", $name)->where("idTabla", $valor->idContabilidad . $valor->id )->value("Ruta");
                if($IdDocControl){
                    $Rutas[3] = Array(
                        "Ruta"               => $url, 
                        "IdDocumentoControl" => $IdDocControl->Id,
                        "EstatusCatastro"    => $IdDocControl->EstatusCatastro, 
                        "EstatusTercero"     => $IdDocControl->EstatusTercero, 
                        "DiasRestantes"      => $valor->DiasRestantes,
                        "FechaVencimiento"   => $valor->FechaVencimiento,
                        "Nombre"             => $NombreDocumento->Nombre,
                        "IdCotizacion"       =>$valor->id,
                        "Prioridad"          =>$NombreDocumento->Prioridad,
                        "IdTipoDocumento"    =>$NombreDocumento->Id
                        
                    );
                }  
            }
           
        }
    
        #return $Rutas;
        if( count($Rutas) < 3 ){
            
            $Documentos = DB::table('Padr_onCatastralTramitesISAINotarios as tn')
                ->join('Padr_onCatastralTramitesISAINotariosDocumentos as pct', 'tn.Id', '=', 'pct.IdTramite')
                ->where('pct.Origen', '1')
                ->where('pct.ControlVersion', '1')
                ->where('pct.IdTramite', $IdTramite)
                ->whereIn('IdTipoDocumento', [ 2, 3 ])
                ->get();
            #return $Documentos;
            foreach ($Documentos as $valor){
                $descripcion = DB::table('TipoDocumentoTramiteISAI')
                ->where('Id', "$valor->IdTipoDocumento")
                ->select('Id','Nombre','Prioridad')
                ->first();
    
                $IdRepositorio = CelaRepositorio::where("Tabla", $valor->IdTipoDocumento)
                    ->where("idTabla", $valor->IdPadron )
                    ->where("Descripci_on", "$descripcion->Nombre")
                    ->orderByDesc('idRepositorio')
                    ->value("idRepositorio");
                
                $IdDocControl = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
                    ->select('Id', 'EstatusCatastro', 'EstatusTercero')
                    ->where('IdTipoDocumento', $valor->IdTipoDocumento)
                    ->where('IdTramite', $IdTramite)
                    ->orderByDesc('Id')->first();
                
                if($IdRepositorio && $IdRepositorio != ''){
                    if($IdDocControl){
                        if( !array_key_exists (  $valor->IdTipoDocumento ,  $Rutas ) ){
                            $Rutas[ $valor->IdTipoDocumento ] = Array(
                                "Ruta"               => $this->ObtieneRutaVisualizaPDF($IdRepositorio), 
                                "IdDocumentoControl" => $IdDocControl->Id, 
                                "EstatusCatastro"    => $IdDocControl->EstatusCatastro, 
                                "EstatusTercero"     => $IdDocControl->EstatusTercero, 
                                "DiasRestantes"      => 180, 
                                "FechaVencimiento"   => date( "Y-m-d", strtotime(date("Y-m-d")."+ 180 days") ),
                                "Nombre"             =>$descripcion->Nombre,
                                "Prioridad"=>$descripcion->Prioridad,
                                "IdTipoDocumento"    =>$descripcion->Id
                            );
                        }
                    }
                }else{
                    if( !array_key_exists (  $valor->IdTipoDocumento ,  $Rutas ) ){
                        $Rutas[ $valor->IdTipoDocumento ] = Array(
                            "Ruta"               => '', 
                            "IdDocumentoControl" => $IdDocControl->Id, 
                            "EstatusCatastro"    => $IdDocControl->EstatusCatastro, 
                            "EstatusTercero"     => $IdDocControl->EstatusTercero, 
                            "DiasRestantes"      => 180, 
                            "FechaVencimiento"   => date( "Y-m-d", strtotime(date("Y-m-d")."+ 180 days") ),
                            "Nombre"             =>$descripcion->Nombre,
                            "Prioridad"=>$descripcion->Prioridad,
                            "IdTipoDocumento"    =>$descripcion->Id
                        );
                    }
                }
    
            }
        }
    
        return $Rutas;
    }
    public function obtenerDocumentosAyuntamiento2(Request $request){
        $Cliente   = $request->Cliente;
        $IdPadron  = $request->idPadron;
        $IdTramite = $request->idTramite;
    
        #return $request;
        Funciones::selecionarBase($Cliente);
    
        $Rutas = array();
    
        #$Tramite= ObtenValor("SELECT * FROM Padr_onCatastralTramitesISAINotarios WHERE Id=$IdTramite");
        $Tramite = DB::table('Padr_onCatastralTramitesISAINotarios')->where('Id', $IdTramite)->first();
    
        $Forma = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
            ->select('Id', 'IdCotizacion', 'EstatusCatastro', 'EstatusTercero')
            ->where([
                ['IdTipoDocumento', '1'],
                ['ControlVersion', '1'],
                ['IdTramite', $IdTramite]
            ])->orderByDesc('Id')->get();
        #return $Forma;
        if ( $Forma && count($Forma) > 0  ) {
            $Forma = $Forma[0];
    
           /* $Forma3DCC = DB::table('Cotizaci_on as c')
                ->select('ccc.Descripci_on as Concepto',
                'c.id', 'c.FolioCotizaci_on', 'd.NombreORaz_onSocial as Nombre','ccc.Tiempo', 
                'd.id as did', 'cont.id as contid')
                ->selectRaw('COALESCE( (SELECT ec.id FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1), (SELECT ec.id FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as idContabilidad')
                ->selectRaw('COALESCE((SELECT N_umeroP_oliza FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1), (SELECT ec.N_umeroP_oliza FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as NumPoliza')
                ->selectRaw('COALESCE((SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),  (SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as Fechapago')
                ->selectRaw('(SELECT cd.Ruta FROM CatalogoDocumentos cd WHERE ccc.CatalogoDocumento =cd.id  ) as DocumentoRuta')
                ->selectRaw('(SELECT cd.Nombre FROM CatalogoDocumentos cd WHERE ccc.CatalogoDocumento =cd.id ) as NombreDocumento')
                ->join('XMLIngreso as x', 'x.idCotizaci_on', '=', 'c.id')
                ->join('ConceptoAdicionalesCotizaci_on as cac', 'cac.Cotizaci_on', '=', 'c.id')
                ->join('ConceptoCobroCaja as ccc', 'ccc.id', '=', 'cac.ConceptoAdicionales')
                ->join('Contribuyente as cont','cont.id','=','c.Contribuyente')
                ->join('DatosFiscales as d','d.id','=','cont.DatosFiscales')
                ->where([
                    ['c.Padr_on', $IdPadron],
                    ['c.id', $Tramite->IdCotizacionForma3],
                    #['c.id', (isset($Forma->IdCotizacion) ? $Forma->IdCotizacion : 0) ],
                    ['c.Cliente', $Cliente],
                    ['cac.Origen', "PAGO"],
                ])
                ->whereIn('ccc.CatalogoDocumento', [ 29 ] )
                ->whereNull('ccc.CatalogoDocumento')
                ->whereNull('cac.Adicional')
                ->get();*/
                $consulta ="SELECT  ccc.Descripci_on as Concepto,
                c.id, c.FolioCotizaci_on, d.NombreORaz_onSocial as Nombre, ccc.Tiempo, 
                d.id as did, cont.id contid, 
                (SELECT a.Descripci_on FROM AreasAdministrativas a WHERE a.id=c.AreaAdministrativa  ) Area,
                COALESCE((SELECT ec.id FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),(SELECT ec.id FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN EncabezadoContabilidad ec ON(ec.Pago=cac.Pago) WHERE cac.Cotizaci_on=c.id  LIMIT 1)) as idContabilidad ,  
                COALESCE((SELECT N_umeroP_oliza FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),(SELECT ec.N_umeroP_oliza FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN EncabezadoContabilidad ec ON(ec.Pago=cac.Pago) WHERE cac.Cotizaci_on=c.id  LIMIT 1)) as NumPoliza , 
                COALESCE((SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),(SELECT DATE(ec.FechaP_oliza) FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN EncabezadoContabilidad ec ON(ec.Pago=cac.Pago) WHERE cac.Cotizaci_on=c.id  LIMIT 1)) as Fechapago ,
                (	SELECT cd.Ruta
                    FROM CatalogoDocumentos cd
                    WHERE 
                        ccc.CatalogoDocumento =cd.id  ) DocumentoRuta,
                (	SELECT cd.Nombre
                    FROM CatalogoDocumentos cd
                    WHERE 
                        ccc.CatalogoDocumento =cd.id  ) NombreDocumento,
                        x.uuid
            FROM Cotizaci_on c
            INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
            INNER JOIN ConceptoAdicionalesCotizaci_on cac ON (cac.Cotizaci_on=c.id)
            INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=cac.ConceptoAdicionales)
            INNER JOIN Contribuyente cont ON (cont.id=c.Contribuyente)
            INNER JOIN DatosFiscales d ON (d.id=cont.DatosFiscales)
            WHERE
            c.id=".$Tramite->IdCotizacionForma3." AND 
            ccc.CatalogoDocumento IS NOT NULL AND
            c.Cliente=".$Cliente." AND
            cac.Adicional IS NULL AND
            Origen!='PAGO'";
            $Forma3DCC =DB::select($consulta);
                //return $Forma3DCC;
            foreach ($Forma3DCC as $valor1){
                $name = "Reporte_Forma3DCC";
                //$ConsultaVerificarTramite="SELECT COUNT(Id) ParaFirma FROM Padr_onCatastralTramitesISAINotarios WHERE IdCotizacionISAI IS NOT NULL AND IdCotizacionForma3=$Tramite->IdCotizacionForma3";
               //  $VerificarTramite2 = DB::select($ConsultaVerificarTramite);
             
                $VerificarTramite = DB::table('Padr_onCatastralTramitesISAINotarios')
                ->whereNotNull('IdCotizacionISAI')
                ->where('IdCotizacionForma3',"$Tramite->IdCotizacionForma3")
                ->value(DB::raw('COUNT(Id) as ParaFirma'));

                if($VerificarTramite>0){
                    //Select count(idRepositorio) as numero FROM CelaRepositorio WHERE Tabla='Reporte_Forma3DCC' and idTabla=".$DatosDocumento['idContabilidad'].$DatosDocumento['id'],"numero"
                    $NumeroDocumentos = DB::table('CelaRepositorio')
                    ->where('Tabla',$valor1->idContabilidad . $valor1->id)
                    ->value(DB::raw('count(idRepositorio) as numero'));
                    
                    $Existe = TRUE;
                    if($NumeroDocumentos == 0){
                        $Existe = FALSE;  
                       $url= ReporteForma3DCC::generar2($Tramite->IdCotizacionForma3, $Cliente, date("Y"));
                      
                    }
                   
                    $Tabla = "Reporte_Forma3DCC"; 
                    $idTabla = $valor1->idContabilidad . $valor1->id; 
                   
                    $Descripci_on="Forma 3DCC Aviso para cambio de propietario";
                    ReporteForma3DCC::ObtenerDocumentoFirmado();
                   // include_once('ObtenPDFFirmadoNoJSDocumentosOficiales.php');
                    
                }else{
                   $url= ReporteForma3DCC::generar2($Tramite->IdCotizacionForma3, $Cliente, date("Y"));

                }


                #$url= ReporteForma3DCC::generar($Forma->IdCotizacion, $Cliente, date("Y"));
                //$url = CelaRepositorio::where("Tabla", $name)->where("idTabla", $valor1->idContabilidad . $valor1->id )->value("Ruta");
                $NombreDocumento = DB::table('TipoDocumentoTramiteISAI')
                    ->where('Id','1')
                    ->value('Nombre');
                $Rutas[1] = Array(
                    "Ruta" => $url,
                    "IdDocumentoControl" => $Forma->Id,
                    "EstatusCatastro" => $Forma->EstatusCatastro,
                    "EstatusTercero" => $Forma->EstatusTercero,
                    "Nombre"=>$NombreDocumento,
                    "IdCotizacion"=>$Tramite->IdCotizacionForma3,
                    "verificar tramite "=> $VerificarTramite,
                    "verificar tramite 2 "=> $VerificarTramite2 
                );
            }
        }
    
        $consultaDB = "SELECT *, TIMESTAMPDIFF(DAY, NOW(), DD.FechaVencimiento) AS DiasRestantes 
            FROM 
                ( SELECT c.id,
                        COALESCE( (SELECT ec.id FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1), (SELECT ec.id FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as idContabilidad ,  
                        COALESCE((SELECT N_umeroP_oliza FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1), (SELECT ec.N_umeroP_oliza FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as NumPoliza , 
                        COALESCE((SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),  (SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as Fechapago,
                        ADDDATE( COALESCE((SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),  (SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1)), INTERVAL 180 DAY) as FechaVencimiento, 
                        (SELECT cd.Ruta FROM CatalogoDocumentos cd WHERE ccc.CatalogoDocumento =cd.id  ) DocumentoRuta,
                        (SELECT cd.Nombre FROM CatalogoDocumentos cd WHERE ccc.CatalogoDocumento =cd.id  ) NombreDocumento
                    FROM Cotizaci_on c
                        INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
                        INNER JOIN ConceptoAdicionalesCotizaci_on cac ON (cac.Cotizaci_on=c.id)
                        INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=cac.ConceptoAdicionales)
                    WHERE
                        c.Padr_on = $IdPadron
                        AND ccc.CatalogoDocumento IN ( 7, 3 ) 
                        AND c.Cliente = $Cliente
                        AND cac.Adicional IS NULL 
                        AND Origen = 'PAGO' HAVING Fechapago < FechaVencimiento 
                ) as DD";
    
        $Documentos = DB::select($consultaDB);
        #return $Documentos;
                
        foreach ($Documentos as $valor){
            if($valor->DocumentoRuta == 'DeslindeCatastralFirma.php'){
                $name='DeslindeCatastral';
    
                $IdDocControl = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
                    ->select('Id', 'EstatusCatastro', 'EstatusTercero')
                    ->where('IdTipoDocumento', '2')->where('IdTramite', $IdTramite)
                    ->orderByDesc('Id')->first();#->take(1)->get();
    
                $NombreDocumento = DB::table('TipoDocumentoTramiteISAI')->where('Id','2')->value('Nombre');
                
                $url = CelaRepositorio::where("Tabla", $name)->where("idTabla", $valor->idContabilidad . $valor->id )->value("Ruta");
                if($IdDocControl){
                    if( $url ){
                        $Rutas[2] = Array(
                            "Ruta"               => $url,
                            "IdDocumentoControl" => $IdDocControl->Id,
                            "EstatusCatastro"    => $IdDocControl->EstatusCatastro,
                            "EstatusTercero"     => $IdDocControl->EstatusTercero,
                            "DiasRestantes"      => $valor->DiasRestantes,
                            "FechaVencimiento"   => $valor->FechaVencimiento,
                            "Nombre"             => $NombreDocumento,
                            "IdCotizacion"               =>$valor->id
                        );
                    }
                }
            }
           
            if($valor->DocumentoRuta=='Padr_onCatastralConstanciaNoAdeudoOK.php'){
                $name='ConstanciaNoAdeudoPredial';
    
                $IdDocControl = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
                    ->select('Id', 'EstatusCatastro', 'EstatusTercero')
                    ->where('IdTipoDocumento', '3')->where('ControlVersion', '1')->where('IdTramite', $IdTramite)
                    ->orderByDesc('Id')->first();
    
                $NombreDocumento = DB::table('TipoDocumentoTramiteISAI')->where('Id','3')->value('Nombre');
    
                $url = CelaRepositorio::where("Tabla", $name)->where("idTabla", $valor->idContabilidad . $valor->id )->value("Ruta");
                if($IdDocControl){
                    if ( $url ){
                        $Rutas[3] = Array(
                            "Ruta"               => $url,
                            "IdDocumentoControl" => $IdDocControl->Id,
                            "EstatusCatastro"    => $IdDocControl->EstatusCatastro,
                            "EstatusTercero"     => $IdDocControl->EstatusTercero,
                            "DiasRestantes"      => $valor->DiasRestantes,
                            "FechaVencimiento"   => $valor->FechaVencimiento,
                            "Nombre"             => $NombreDocumento,
                            "IdCotizacion"       => $valor->id,
                        );
                    }
                }
            }
           
        }
    
        #return $Rutas;
        if( count($Rutas) < 3 ){
            
            $Documentos = DB::table('Padr_onCatastralTramitesISAINotarios as tn')
                ->join('Padr_onCatastralTramitesISAINotariosDocumentos as pct', 'tn.Id', '=', 'pct.IdTramite')
                ->where('pct.Origen', '1')
                ->where('pct.ControlVersion', '1')
                ->where('pct.IdTramite', $IdTramite)
                ->whereIn('IdTipoDocumento', [ 2, 3 ])
                ->get();
            #return $Documentos;
            foreach ($Documentos as $valor){
                $descripcion = DB::table('TipoDocumentoTramiteISAI')->where('Id', "$valor->IdTipoDocumento")->value('Nombre');
    
                $IdRepositorio = CelaRepositorio::where("Tabla", $valor->IdTipoDocumento)
                    ->where("idTabla", $valor->IdPadron )
                    ->where("Descripci_on", "$descripcion")
                    ->orderByDesc('idRepositorio')
                    ->value("idRepositorio");
                
                $IdDocControl = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
                    ->select('Id', 'EstatusCatastro', 'EstatusTercero')
                    ->where('IdTipoDocumento', $valor->IdTipoDocumento)
                    ->where('IdTramite', $IdTramite)
                    ->orderByDesc('Id')->first();
                
                if($IdRepositorio && $IdRepositorio != ''){
                    if($IdDocControl){
                        if( !array_key_exists (  $valor->IdTipoDocumento ,  $Rutas ) ){
                            $Rutas[ $valor->IdTipoDocumento ] = Array(
                                "Ruta"               => $this->ObtieneRutaVisualizaPDF($IdRepositorio), 
                                "IdDocumentoControl" => $IdDocControl->Id, 
                                "EstatusCatastro"    => $IdDocControl->EstatusCatastro, 
                                "EstatusTercero"     => $IdDocControl->EstatusTercero, 
                                "DiasRestantes"      => 180, 
                                "FechaVencimiento"   => date( "Y-m-d", strtotime(date("Y-m-d")."+ 180 days") ),
                                "Nombre"             =>$descripcion
                            );
                        }
                    }
                }else{
                    if( !array_key_exists (  $valor->IdTipoDocumento ,  $Rutas ) ){
                        $Rutas[ $valor->IdTipoDocumento ] = Array(
                            "Ruta"               => '', 
                            "IdDocumentoControl" => $IdDocControl->Id, 
                            "EstatusCatastro"    => $IdDocControl->EstatusCatastro, 
                            "EstatusTercero"     => $IdDocControl->EstatusTercero, 
                            "DiasRestantes"      => 180, 
                            "FechaVencimiento"   => date( "Y-m-d", strtotime(date("Y-m-d")."+ 180 days") ),
                            "Nombre"             =>$descripcion
                        );
                    }
                }
    
            }
        }
    
        return $Rutas;
    }


    public function obtenerDocumentosNotarios(Request $request){
        $Cliente   = $request->Cliente;
        $IdTramite = $request->idTramite;

        #return $request;
        Funciones::selecionarBase($Cliente);

        $Documentos = DB::table('Padr_onCatastralTramitesISAINotarios as tn')
            ->join('Padr_onCatastralTramitesISAINotariosDocumentos as pct', 'tn.Id', '=', 'pct.IdTramite')
            ->join('TipoDocumentoTramiteISAI as tdt', 'tdt.Id','=','pct.IdTipoDocumento')
            ->where(
                [
                    ['pct.Origen',  '=', '2'],
                    ['pct.IdTramite',  '=', $IdTramite],
                    ['tdt.Requerido',  '=', 1]
                    
                    ,
                ]
            )
            ->orderBy('tdt.Prioridad','asc')
            ->get();

        #return $Documentos;
       //return $Documentos;
        $Rutas = array();
        #$otros = array();
        $nombreDocumento;
        foreach ($Documentos as $documento){
            $descripcion = DB::table('TipoDocumentoTramiteISAI')
            ->where('Id', "$documento->IdTipoDocumento" )
            ->select('Id','Nombre','Prioridad')
            ->first();
           $nombreDocumento=  $documento->Nombre;
            $IdRepositorio = CelaRepositorio::where([
                    ['Tabla', $documento->IdTipoDocumento],
                    ['idTabla', $documento->IdPadron],
                    ['Descripci_on',  "$descripcion->Nombre" ],
                ])
                ->orderByDesc('idRepositorio')
                ->value('idRepositorio');

            $IdDocControl = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
                ->where('IdTipoDocumento', $documento->IdTipoDocumento)
                ->where('IdTramite', $IdTramite)
                ->orderByDesc('Id')
                ->value('Id');

            /*$otros[] = array( 
                    'Descripcion' => $descripcion, 
                    'idRepositorio' => $IdRepositorio, 
                    'idDoccontrol' => $IdDocControl,
                );*/

            $Rutas[ $documento->IdTipoDocumento ] = Array(
                    "Ruta" => $this->ObtieneRutaVisualizaPDF($IdRepositorio),
                    "IdDocumentoControl"=>$IdDocControl,
                    'IdTipoDocumento'=>$documento->IdTipoDocumento,
                    'Nombre'=>$nombreDocumento,
                    'Prioridad'=>$descripcion->Prioridad,
                    'IdTipoDocumento'=>$descripcion->Id
                );
        }

        return $Rutas; 
        #return json_decode($Rutas); 
        
        #$books = json_decode($json);
        return response()->json([
            'success' => '1',
            'cliente'=> $Rutas
        ], 200);
    }
    public function obtenerDocumentoNotarios(Request $request){
        $Cliente   = $request->Cliente;
        $IdTramite = $request->idTramite;
        $IdTipoDocumento=$request->idTipoDocumento;
        #return $request;
        Funciones::selecionarBase($Cliente);

        $Documentos = DB::table('Padr_onCatastralTramitesISAINotarios as tn')
            ->join('Padr_onCatastralTramitesISAINotariosDocumentos as pct', 'tn.Id', '=', 'pct.IdTramite')
            ->where(
                [
                    ['pct.Origen',  '=', '2'],
                    ['pct.IdTramite',  '=', $IdTramite],
                    ['pct.IdTipoDocumento', '=', $IdTipoDocumento],
                    ['pct.ControlVersion','=','1']
                ]
            )->get();

        #return $Documentos;

        $Rutas = array();
        $IdDocControl=0;
        #$otros = array();
        foreach ($Documentos as $documento){
            $descripcion = DB::table('TipoDocumentoTramiteISAI')->where('Id', "$documento->IdTipoDocumento" )->value('Nombre');

            $IdRepositorio = CelaRepositorio::where([
                    ['Tabla', $documento->IdTipoDocumento],
                    ['idTabla', $documento->IdPadron],
                    ['Descripci_on',  "$descripcion" ],
                ])
                ->orderByDesc('idRepositorio')
                ->value('idRepositorio');

            $IdDocControl = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
                ->where('IdTipoDocumento', $documento->IdTipoDocumento)
                ->where('IdTramite', $IdTramite)
                ->orderByDesc('Id')
                ->value('Id');

            /*$otros[] = array( 
                    'Descripcion' => $descripcion, 
                    'idRepositorio' => $IdRepositorio, 
                    'idDoccontrol' => $IdDocControl,
                );*/

            $Rutas[ $documento->IdTipoDocumento ] = Array(
                    "Ruta" => $this->ObtieneRutaVisualizaPDF($IdRepositorio),
                    "IdDocumentoControl"=>$IdDocControl,
                    "r"=>$IdRepositorio
                );
        }
        $EstatusDocumento = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
        ->select("EstatusCatastro", "EstatusTercero")
        ->where('id', $IdDocControl)
        ->orderByDesc('FechaTupla')
        ->first();
        //return $Rutas; 
        #return json_decode($Rutas); 
        
        #$books = json_decode($json);
        return response()->json([
            'success' => '1',
            'cliente'=> $Rutas,
            'estatus'=>$EstatusDocumento,
            
        ], 200);
    }

    public function ObtieneRutaVisualizaPDF($idRepositorio){
        $Datos = CelaRepositorio::where('idRepositorio', $idRepositorio)->first();
        //return $idRepositorio;
        $host = $_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/";

        if( !file_exists("repositorio/temporal/") ){
            #mkdir("repositorio/temporal/",0755, true);
        }

        if( isset( $Datos['Ruta']) ){
            $TempFile = "repositorio/temporal/" . $Datos['NombreOriginal'];
            $Content = file_get_contents( $Datos['Ruta'] );
            file_put_contents( $TempFile, $Content);	
        }else{
            $TempFile = "";
        }

        
        
        return $TempFile;
    }

    public function subirArchivo(){
       
      $destino="public/".$_FILES["archivo1"]["name"];

      $archivoNombre=$_FILES["archivo1"]["name"];

       move_uploaded_file($_FILES["archivo1"]["tmp_name"],$destino);
             
         
    }
    public function getObservacion(Request $request){
         
        $cliente=$request->Cliente;
        $IdCatalogoDocumento=$request->IdCatalogoDocumento;
        $IdTramite=$request->IdTramite;

        Funciones::selecionarBase($cliente);
            
            $Observacion = Observaciones::select('id','Observacion','EstatusCatastro',"EstatusTercero","FechaTupla")
                ->where('IdCatalogoDocumento',$IdCatalogoDocumento)
                ->where('IdTramite',$IdTramite)
                ->orderByDesc('FechaTupla')
                ->get()
                ;
                //return $cliente;
                return response()->json([
                    'success' => '1',
                    'observacion'=>  $Observacion
                ], 200);
    }
    public function getStatusDocumento(Request $request){
        $Cliente   = $request->Cliente;
        $IdDocumento = $request->IdDocumento;
        
        #return $request;
        Funciones::selecionarBase($Cliente);

        $Documento = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
        ->select("EstatusCatastro", "EstatusTercero")
        ->where('id', $IdDocumento)
        ->orderByDesc('FechaTupla')
        ->get();

        return response()->json([
            'success' => '1',
            'estatusDocumento'=>  $Documento
        ], 200);

      }

      public function getStatusDocumentoPrueba(Request $request){
        $Cliente   = $request->Cliente;
        $IdTramite = $request->IdTramite;
        $IdTipoDocumento=$request->IdTipoDocumento;
        
        #return $request;
        Funciones::selecionarBase($Cliente);
     
        $EstatusDocumento = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
        ->select("Id","EstatusCatastro", "EstatusTercero")
        ->where('IdTipoDocumento', $IdTipoDocumento)
        ->where('IdTramite', $IdTramite)
        ->orderByDesc('FechaTupla')
        ->first();

        return response()->json([
            'success' => '1',
            'estatusDocumento'=>  $EstatusDocumento
        ], 200);

      }

      public function modificarStatusDocumento(Request $request){
        $Cliente   = $request->Cliente;
        $IdDocumento = $request->IdDocumento;
        $Estatus=$request->Estatus;
        
        
      
        #return $request;
        Funciones::selecionarBase($Cliente);

        $Documento = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
        ->where('id', $IdDocumento)
        ->update(["EstatusTercero"=>$Estatus]);
        
        

        return response()->json([
            'success' => '1',
            'estatusDocumento'=>  $Documento
        ], 200);

      }

      
   
      public function  modificarExtrasISAI(Request $request){
        $cliente=$request->Cliente;
        $idTramite=$request->IdTramite;
        $extra= $request->Extra;
        Funciones::selecionarBase( $cliente);
       // return $extra;
        $ResExtra = TramitesISAINotarios::where('id',$idTramite)
        ->update(['DatosExtra' => $extra]);

          return response()->json([
                'success' => '1',
            ], 200);
        
    }
    public function  modificarDatosContactoISAI(Request $request){
        $cliente=$request->Cliente;
        $idTramite=$request->IdTramite;
        $extra= $request->Extra;
        Funciones::selecionarBase( $cliente);
       // return $extra;
        $ResExtra = TramitesISAINotarios::where('id',$idTramite)
        ->update(['DatosContacto' => $extra]);

          return response()->json([
                'success' => '1',
            ], 200);
        
    }
    public function getCorreo_Celular(Request $request){

        $cliente=$request->Cliente;
        $cuentaAnterior=$request->CuentaAnterior;
        $cuenta=$request->Cuenta;

        Funciones::selecionarBase($cliente);

        $datos = Funciones::ObtenValor("SELECT c.CorreoElectr_onico, c.Tel_efonoCelular FROM Padr_onCatastral pc
                                            INNER JOIN Contribuyente c ON (pc.Contribuyente = c.id)
                                            WHERE pc.CuentaAnterior = '".$cuentaAnterior."' AND pc.Cuenta ='".$cuenta."';
                                            ");



        return response()->json([
            'success' => '1',
            'correo' =>$datos->CorreoElectr_onico,
            'celular'=>$datos->Tel_efonoCelular

        ], 200);
    }

    public function  modificarDatosContactoISAIYContribuyente(Request $request){
        $cliente=$request->Cliente;
        $idTramite=$request->IdTramite;
        $extra= $request->Extra;
        $correo = $request->correo;
        $telefono = $request->telefono;
        $cuentaAnterior = $request->CuentaAnterior;
        $cuenta = $request->Cuenta;
        Funciones::selecionarBase( $cliente);
        // return $extra;
        $ResExtra = TramitesISAINotarios::where('id',$idTramite)
            ->update(['DatosContacto' => $extra]);

        $datos = Funciones::ObtenValor("SELECT c.CorreoElectr_onico,c.Tel_efonoCelular FROM Padr_onCatastral pc 
                                            INNER JOIN Contribuyente c ON (pc.Contribuyente = c.id)
                                            WHERE pc.CuentaAnterior = '".$cuentaAnterior."' AND pc.Cuenta = '".$cuenta."'");

        if($datos->CorreoElectr_onico != "" || $datos->CorreoElectr_onico != null){
            $arregloCorreos = explode(";",$datos->CorreoElectr_onico);

            $bandera = false;

            foreach($arregloCorreos as $value){
                if($value == $correo){
                    $bandera = true;

                }
            }
            $correos="";
            if(!$bandera){
                $correos=$datos->CorreoElectr_onico.";".$correo;

                $consultaUpdate = "UPDATE Contribuyente c
             INNER JOIN Padr_onCatastral pc ON (pc.Contribuyente = c.id)
             SET c.CorreoElectr_onico = '".$correos."'
             WHERE pc.CuentaAnterior ='".$cuentaAnterior."' AND pc.Cuenta ='".$cuenta."'";
                DB::update($consultaUpdate);

            }

        }else{
            $consultaUpdate = "UPDATE Contribuyente c
             INNER JOIN Padr_onCatastral pc ON (pc.Contribuyente = c.id)
             SET c.CorreoElectr_onico = '".$correo."'
             WHERE pc.CuentaAnterior ='".$cuentaAnterior."' AND pc.Cuenta ='".$cuenta."'";
            DB::update($consultaUpdate);
        }

        if($datos->Tel_efonoCelular != "" || $datos->Tel_efonoCelular != null){
            $arregloTelefonos = explode(";",$datos->Tel_efonoCelular);

            $bandera = false;

            foreach($arregloTelefonos as $value){
                if($value == $telefono){
                    $bandera = true;

                }
            }
            $telefonos="";
            if(!$bandera){
                $telefonos=$datos->Tel_efonoCelular.";".$telefono;

                $consultaUpdate = "UPDATE Contribuyente c
             INNER JOIN Padr_onCatastral pc ON (pc.Contribuyente = c.id)
             SET c.Tel_efonoCelular = '".$telefonos."'
             WHERE pc.CuentaAnterior ='".$cuentaAnterior."' AND pc.Cuenta ='".$cuenta."'";
                DB::update($consultaUpdate);

            }

        }else{
            $consultaUpdate = "UPDATE Contribuyente c
             INNER JOIN Padr_onCatastral pc ON (pc.Contribuyente = c.id)
             SET c.Tel_efonoCelular = '".$telefono."'
             WHERE pc.CuentaAnterior ='".$cuentaAnterior."' AND pc.Cuenta ='".$cuenta."'";
            DB::update($consultaUpdate);
        }




        return response()->json([
            'success' => '1',
        ], 200);

    }

    public function getDatosExtra(Request $request){
        $Cliente   = $request->Cliente;
      
        $IdTramite = $request->IdTramite;
        
        #return $request;
        Funciones::selecionarBase($Cliente);
        
        $datosExtra = TramitesISAINotarios::where('id', $IdTramite )
        ->value("DatosExtra");

        #return $datosExtra;
        
        return response()->json([
            'success' => '1',
            'datosExtra'=>  $datosExtra
        ], 200);

      }
      public function getDatosContacto(Request $request){
        $Cliente   = $request->Cliente;
      
        $IdTramite = $request->IdTramite;
        
        #return $request;
        Funciones::selecionarBase($Cliente);
        
        $datosExtra = TramitesISAINotarios::where('id', $IdTramite )
        ->value("DatosContacto");

        #return $datosExtra;
        
        return response()->json([
            'success' => '1',
            'datosExtra'=>  $datosExtra
        ], 200);

      }


      public function getDatosExtras(Request $request){
        $Cliente   = $request->Cliente;
      
        $IdTramite = $request->IdTramite;
        
        #return $request;
        Funciones::selecionarBase($Cliente);
        
        $datosExtra = DB::table('Padr_onCatastralTramitesISAINotarios')->get();

        dd($datosExtra);

        return $datosExtra;
        
        return response()->json([
            'success' => '1',
            'datosExtra'=>  $datosExtra
        ], 200);

      }
      public function eliminarObservacion(Request $request){
        $Cliente   = $request->Cliente;
      
        $IdObservacion= $request->IdObservacion;
        

        #return $request;
        Funciones::selecionarBase($Cliente);
        
        $observacion = Observaciones::where('id', '=', $IdObservacion)->delete();
        
        return response()->json([
            'success' => '1',
            
        ], 200);

      }


      


    public function  obtenerDocumentosCatalogo(Request $request){
        $cliente=$request->Cliente;
        $origen=$request->Origen;
        
        Funciones::selecionarBase( $cliente);
       // return $extra;
       $Documento = DB::table('TipoDocumentoTramiteISAI')
       ->select("id", "Nombre","Prioridad")
       ->where('Origen', $origen)
       ->where('Requerido', 1)
       
       ->get();
       return response()->json([
        
        'documentos'=>$Documento
    ], 200);

        
    }

    public function  obtenerDocumentosCatalogo2(Request $request){
        $cliente=$request->Cliente;
        $origen=$request->Origen;
        
        Funciones::selecionarBase( $cliente);
       // return $extra;
       $Documento = DB::table('TipoDocumentoTramiteISAI')
       ->select("id", "Nombre","Prioridad")
       ->where('Origen', $origen)
       ->where('Requerido', 1)
       ->orderBy('Prioridad','asc')
       ->get();
       return response()->json([
        
        'documentos'=>$Documento
    ], 200);

        
    }


    public function  obtenerDatosComprador(Request $request){
        $cliente=$request->Cliente;
        $IdPadron=$request->IdPadron;
        $Comprador="";
        Funciones::selecionarBase( $cliente);
       // return $extra;
       $P="select Comprador from Padr_onCatastral where id=".$IdPadron;
       
       $IdComprador=DB::table("Padr_onCatastral")
       ->where("id",$IdPadron)
       ->value("Comprador");
       if(isset($IdComprador)){
        $Comprador= DB::table('Contribuyente')
        ->select("Nombres","ApellidoPaterno","ApellidoMaterno")
         ->where('Id', $IdComprador)
        ->get();
        return response()->json([
            'success'=>1,
            'comprador'=>$Comprador,
            'idComprador'=>$IdComprador
        ], 200);
       }else{
        return response()->json([
            'success'=>0
        ], 200); 
       }
      
       
   

        
    }

    

    public function  obtenerDatosFiscales(Request $request){
        $cliente=$request->Cliente;
        $idComprador=$request->IdComprador;
        
        Funciones::selecionarBase( $cliente);
      
       $DatosFiscales = DB::table('Contribuyente as C')
       ->join('DatosFiscales as DF', 'C.DatosFiscales','=','DF.id')
       ->select("DF.id", "DF.RFC","DF.NombreORaz_onSocial","DF.EntidadFederativa","DF.Municipio","DF.Localidad","DF.Colonia","DF.Calle","DF.N_umeroInterior","DF.N_umeroExterior","DF.C_odigoPostal","DF.Referencia","DF.R_egimenFiscal")
       ->where('C.Id', $idComprador)
       ->first();
       return response()->json([
        'success' => '1',
        'datosFiscales'=>$DatosFiscales
    ], 200);

        
    }


    public function  obtenerDatosFiscalesCopia(Request $request){
        $cliente=$request->Cliente;
        $idComprador=$request->IdComprador;

        Funciones::selecionarBase( $cliente);

        $DatosFiscales = DB::table('Contribuyente as C')
            ->join('DatosFiscales as DF', 'C.DatosFiscales','=','DF.id')
            ->select("DF.id", "DF.RFC","DF.NombreORaz_onSocial","DF.EntidadFederativa","DF.Municipio","DF.Localidad","DF.Colonia","DF.Calle","DF.N_umeroInterior","DF.N_umeroExterior","DF.C_odigoPostal","DF.Referencia","DF.R_egimenFiscal")
            ->where('C.Id', $idComprador)
            ->first();

        return $DatosFiscales;

    }

    public function  obtenerRegimen(Request $request){
        
        $idRegimen=$request->IdRegimen;
        $cliente=$request->Cliente;
       
        
        Funciones::selecionarBase( $cliente);
        $DatosFiscales = DB::table('RegimenFiscal')
       ->select("id","Descripci_on")
       ->get();
       return response()->json([
        'success' => '1',
        'datosFiscales'=>$DatosFiscales
    ], 200);
   }

   public function  modificarDatosFiscales(Request $request){
    $cliente=$request->Cliente;
    $idContribuyente=$request->IdContribuyente;
    $RFC=$request->RFC;
    $NombreORaz_onSocial=$request->NombreORaz_onSocial;
    $EntidadFederativa=$request->EntidadFederativa;
    $Pais=$request->Pais;
    $Municipio=$request->Municipio;
    $Localidad=$request->Localidad;
    $Colonia=$request->Colonia;
    $Calle=$request->Calle;
    $N_umeroInterior=$request->N_umeroInterior;
    $N_umeroExterior=$request->N_umeroExterior;
    $C_odigoPostal=$request->C_odigoPostal;
    $Referencia=$request->Referencia;
    $R_egimenFiscal=$request->R_egimenFiscal;
    Funciones::selecionarBase( $cliente);
    
   $Res= DB::table('DatosFiscales as DF')
   ->join('Contribuyente as C', 'C.DatosFiscales','=','DF.Id')
   ->where('C.Id', $idContribuyente)
   ->update([ "DF.RFC"=>$RFC,
   "DF.NombreORaz_onSocial"=>$NombreORaz_onSocial,
   "DF.EntidadFederativa"=>$EntidadFederativa,
   "DF.Municipio"=>$Municipio,
   "DF.Localidad"=>$Localidad,
   "DF.Colonia"=>$Colonia,
   "DF.Calle"=>$Calle,
   "DF.Pa_is"=>$Pais,
   "DF.N_umeroInterior"=>$N_umeroInterior,
   "DF.N_umeroExterior"=>$N_umeroExterior,
   "DF.C_odigoPostal"=>$C_odigoPostal,
   "DF.Referencia"=>$Referencia,
   "DF.R_egimenFiscal"=>$R_egimenFiscal]);
    
   $ResExtra= DB::table('CelaAccesos')->insert([
    ['idAcceso' => null, 
    'FechaDeAcceso' =>date('Y-m-d H:i:s'),
    'idUsuario' => 3667,
    'Tabla'=>'Contribuyente',
    'IdTabla' =>$idContribuyente,
    'Acci_on'=>5,
    'Descripci_onCela'=>'Modificacion via API']]);

        return response()->json([
            'success' => '1',
        ], 200);
  
}

public function  obtenerObservacionesPendientes(Request $request){
    $cliente=$request->Cliente;
    $idTramite=$request->IdTramite;
   
    Funciones::selecionarBase( $cliente);
  
   $DatosFiscales = DB::table('Padr_onCatastralTramitesISAINotariosObservaciones')
   ->select("IdCatalogoDocumento", DB::raw("COUNT(id) AS ObservacionesPendientes"))
   ->where('IdTramite','=', $idTramite)
   ->where(function ($query) {
    $query->where('EstatusTercero',"=",'0')
    ->orWhere('EstatusCatastro',"=",'0');
    })
  ->groupBy('IdCatalogoDocumento')
   ->get();
   return response()->json([
    'success' => '1',
        'datosFiscales'=>$DatosFiscales
    ], 200);

    
}

public function  estatusTramite(Request $request){
    $cliente=$request->Cliente;
    $idTramite=$request->IdTramite;
  //return $request;
   
    Funciones::selecionarBase( $cliente);
    /*
  $estatusTramite=DB::select("SELECT IF(DocCompletos>=DocumentosRequeridos, 1, 0) AS Completo 
    FROM ( SELECT (SELECT COUNT(DISTINCT pti.IdTipoDocumento) FROM Padr_onCatastralTramitesISAINotariosDocumentos pti
    WHERE pti.IdTramite=pct.id AND pti.IdTipoDocumento NOT IN (1)) AS TotalDocumentos, (SELECT COUNT(DISTINCT pti.IdTipoDocumento) FROM Padr_onCatastralTramitesISAINotariosDocumentos pti 
    WHERE pti.IdTramite=pct.id AND pti.EstatusTercero=1 AND pti.EstatusCatastro=1) AS DocCompletos, (SELECT COUNT(id) FROM TipoDocumentoTramiteISAI WHERE Requerido=1) AS DocumentosRequeridos
    FROM Padr_onCatastralTramitesISAINotarios pct INNER JOIN Padr_onCatastral pc ON (pc.id=pct.IdPadron) WHERE pc.Cliente=".$cliente." AND pct.id=".$idTramite.") AS T");
 */
$c="SELECT  IF ( DocumentosRequeridos=CompletoCatastro AND  CompletoTercero=DocumentosRequeridos,'1','0') AS TramiteCompleto
	FROM ( SELECT
	pct.id AS IdTramite,
	(SELECT SUM(EstatusCatastro) FROM Padr_onCatastralTramitesISAINotariosDocumentos as pctd join TipoDocumentoTramiteISAI as tdt on tdt.Id=pctd.IdTipoDocumento WHERE IdTramite=pct.Id AND ControlVersion=1 and tdt.Requerido=1) AS CompletoCatastro,
	(SELECT SUM(EstatusTercero) FROM Padr_onCatastralTramitesISAINotariosDocumentos as pctd join TipoDocumentoTramiteISAI as tdt on tdt.Id=pctd.IdTipoDocumento WHERE IdTramite=pct.Id AND ControlVersion=1 and tdt.Requerido=1) AS CompletoTercero,
	(SELECT COUNT(id) FROM TipoDocumentoTramiteISAI where Requerido=1) AS DocumentosRequeridos
  FROM Padr_onCatastralTramitesISAINotarios pct WHERE pct.Id=$idTramite ) AS T";
    
$estatusTramite=DB::select($c);
 
 return response()->json([
    'success' => '1',
        'estatusTramite'=>$estatusTramite
    ], 200);
 
}


    public function  estatusTramiteCopia(Request $request){
        $cliente=$request->Cliente;
        $idTramite=$request->IdTramite;
        $cotizacion=0;

        Funciones::selecionarBase( $cliente);
        /*
      $estatusTramite=DB::select("SELECT IF(DocCompletos>=DocumentosRequeridos, 1, 0) AS Completo
        FROM ( SELECT (SELECT COUNT(DISTINCT pti.IdTipoDocumento) FROM Padr_onCatastralTramitesISAINotariosDocumentos pti
        WHERE pti.IdTramite=pct.id AND pti.IdTipoDocumento NOT IN (1)) AS TotalDocumentos, (SELECT COUNT(DISTINCT pti.IdTipoDocumento) FROM Padr_onCatastralTramitesISAINotariosDocumentos pti
        WHERE pti.IdTramite=pct.id AND pti.EstatusTercero=1 AND pti.EstatusCatastro=1) AS DocCompletos, (SELECT COUNT(id) FROM TipoDocumentoTramiteISAI WHERE Requerido=1) AS DocumentosRequeridos
        FROM Padr_onCatastralTramitesISAINotarios pct INNER JOIN Padr_onCatastral pc ON (pc.id=pct.IdPadron) WHERE pc.Cliente=".$cliente." AND pct.id=".$idTramite.") AS T");
     */

            $cotizacion=Funciones::ObtenValor("SELECT IF (pct.IdCotizacionISAI IS NOT NULL,'1','0')  AS Cotizacion FROM Padr_onCatastralTramitesISAINotarios as pct INNER JOIN Padr_onCatastral as pc on (pct.IdPadron=pc.id) WHERE pct.Id=".$idTramite,'Cotizacion');

            $c="SELECT  IF ( DocumentosRequeridos=CompletoCatastro AND  CompletoTercero=DocumentosRequeridos,'1','0') AS TramiteCompleto
	        FROM ( SELECT
	        pct.id AS IdTramite,
	        (SELECT SUM(EstatusCatastro) FROM Padr_onCatastralTramitesISAINotariosDocumentos as pctd join TipoDocumentoTramiteISAI as tdt on tdt.Id=pctd.IdTipoDocumento WHERE IdTramite=pct.Id AND ControlVersion=1 and tdt.Requerido=1) AS CompletoCatastro,
	        (SELECT SUM(EstatusTercero) FROM Padr_onCatastralTramitesISAINotariosDocumentos as pctd join TipoDocumentoTramiteISAI as tdt on tdt.Id=pctd.IdTipoDocumento WHERE IdTramite=pct.Id AND ControlVersion=1 and tdt.Requerido=1) AS CompletoTercero,
	        (SELECT COUNT(id) FROM TipoDocumentoTramiteISAI where Requerido=1) AS DocumentosRequeridos
            FROM Padr_onCatastralTramitesISAINotarios pct WHERE pct.Id=$idTramite ) AS T";

        $estatusTramite=DB::select($c);
        //$cotizacion=DB::select($conCotizacion);

        return response()->json([
            'success' => '1',
            'estatusTramite'=>$estatusTramite,
            'cotizacion' => $cotizacion,
        ], 200);

    }


public function  totalISAI(Request $request){
    $cliente=$request->Cliente;
    $idPadron=$request->IdPadron;
    $idCotizacionForma=$request->IdCotizacionForma;
    $UrlOrdenPagoISAI="";
    
    Funciones::selecionarBase($cliente);
    
    $idCotizacion = DB::table('Padr_onCatastralTramitesISAINotarios')
    ->where("IdPadron",$idPadron)
    ->where("IdCotizacionForma3",$idCotizacionForma)
    ->value('IdCotizacionISAI');
    
    $total=DB::select("SELECT sum(importe) as TotalISAI FROM ConceptoAdicionalesCotizaci_on WHERE ConceptoAdicionalesCotizaci_on.Cotizaci_on='".$idCotizacion."'  AND Padre is NULL ");
    if($idCotizacion){
        $UrlOrdenPagoISAI=PortalNotariosController::obtenerOrdenPagoISAI($cliente,$idCotizacion);
    }
    $ConsultaConceptos ="SELECT c.id as idConceptoCotizacion, co.TipoContribuci_on as TipoContribuci_on, sum(co.Importe) as Importe, co.Adicional, if(co.Adicional is not null,COUNT(co.Cantidad),co.Cantidad) as Cantidad ,
                        (SELECT Padr_on FROM Cotizaci_on WHERE id=co.Cotizaci_on) AS idPadron,'' AS idCC, co.Cotizaci_on AS Cotizaci_on, CURDATE() AS FechaActualV5, sum(co.Importe) as total,
                        (SELECT Padr_on FROM Cotizaci_on WHERE id=co.Cotizaci_on) AS Padr_on, 11 AS Tipo,(SELECT Cliente FROM Cotizaci_on WHERE id=co.Cotizaci_on) AS Cliente, 
                        co.MontoBase as MontoBase, if(co.Adicional is  null,c.Descripci_on,(SELECT Descripci_on FROM RetencionesAdicionales WHERE RetencionesAdicionales.id=co.Adicional )) as DescripcionConcepto
                        FROM ConceptoAdicionalesCotizaci_on co INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id )
                        WHERE co.Cotizaci_on = ".$idCotizacion." and co.Estatus in (0,-1) GROUP BY co.ConceptoAdicionales LIMIT 6";
    $ejecutaConceptos=DB::select($ConsultaConceptos);
    $OTrosImportes=0;

    foreach($ejecutaConceptos as $filaConcepto){
        $filaConcepto->EjercicioFiscal = date("Y");
        try {
            Funciones::ObtenerMultaDinamica($filaConcepto);
        } catch (Exception $e) {
        }
        if(isset($filaConcepto->Multa_Concepto) && $filaConcepto->Multa_Concepto>0)
            $OTrosImportes+=$filaConcepto->Multa_Importe+$filaConcepto->recargo;
    }
    
    return response()->json([
        'success' => '1',
            'total'=>($total[0]->TotalISAI+$OTrosImportes),
            'rutaOrdenPagoISAI'=>$UrlOrdenPagoISAI
        
    ], 200);

    
}

public function  obtenerPuntosCardinales(Request $request){
    $cliente=$request->Cliente;
    $idCotizacion=$request->IdCotizacion;
    Funciones::selecionarBase( $cliente);
    $puntosCarninales=DB::select("SELECT id,Nombre FROM PuntoCardinal");
 
   return response()->json([
    'success' => '1',
        'puntosCarninales'=>$puntosCarninales
    ], 200);

    
}
public function  obtenerPuntosCardinales1(Request $request){
    $cliente=$request->Cliente;
    $idCotizacion=$request->IdCotizacion;
    Funciones::selecionarBase( $cliente);
    $puntosCarninales=DB::select("SELECT id,Nombre FROM PuntoCardinal");

    DB::table('PuntoCardinal')->insert([
        ['id' => null, 
        'Colindancia' => 0,
        'idPadr_onCatastral'=>0,
        'idPuntoCardinal'=>2]
    ]);
   return response()->json([
    'success' => '1',
        'puntosCarninales'=>$puntosCarninales
    ], 200);

    
}

public function eliminarColindancia(Request $request){
    $Cliente   = $request->Cliente;
  
    $IdColindancia= $request->IdColindancia;
    

    #return $request;
    Funciones::selecionarBase($Cliente);
    $eliminacion=DB::table('Padr_onColindancia')->where('id', '=', $IdColindancia)->delete();
    if($eliminacion){
        return response()->json([
            'success' => '1',
            
        ], 200);
    }else{
        return response()->json([
            'success' => '0',
            
        ], 200);
    }

  }


  public function modificarColindancia(Request $request){
    $Cliente   = $request->Cliente;
    $IdColindancia= $request->IdColindancia;
    $colindancia=$request->Colindancia;
    $idPuntoCardinal=$request->IdPuntoCardinal;

    
    Funciones::selecionarBase($Cliente);
    
    DB::table('Padr_onColindancia')
    ->where('id', $IdColindancia)
    ->update(['Colindancia' =>$colindancia,
    'idPuntoCardinal'=>$idPuntoCardinal]);
    
        return response()->json([
            'success' => '1',
            
        ], 200);
    
    
  }

  public function  obtenerColindancia(Request $request){
    $cliente=$request->Cliente;
    $idPadron=$request->IdPadron;
   
    Funciones::selecionarBase( $cliente);
    $Colindancias= DB::table('Padr_onColindancia')
    ->where('idPadr_onCatastral',$idPadron)
    ->get();
    
   return response()->json([
    'success' => '1',
    'colindancias'=>$Colindancias
    ], 200);

    
}

public function  addColindancia(Request $request){
    $cliente=$request->Cliente;
    $idPadron=$request->IdPadron;
    $colindancia=$request->Colindancia;
    $idPuntoCardinal=$request->IdPuntoCardinal;

    Funciones::selecionarBase( $cliente);
    DB::table('Padr_onColindancia')->insert([
        ['id' => null, 
        'Colindancia' =>$colindancia,
        'idPadr_onCatastral'=>$idPadron,
        'idPuntoCardinal'=>$idPuntoCardinal]
    ]);
   return response()->json([
    'success' => '1'
    ], 200);

    
}

    public function ValidarPredioAptoParaTramiteISAI(Request $request) {

        $idPadron = $request->idPadron;
        $Cliente = $request->cliente;
        
        Funciones::selecionarBase($Cliente);

        $DatosPredio = DB::table("Padr_onCatastral")->where('id', $idPadron)->first();
        $Estatus=0;
        
            $MesActual = date('n');
            $AnioActual = date('Y');
            $Meses = [1=>1, 2=>1, 3=>2, 4=>2, 5=>3, 6=>3, 7=>4, 8=>4, 9=>5, 10=>5, 11=>6, 12=>6];
            $Estatus=0;
            $TipoPredio=Funciones::ObtenValor("SELECT pc.TipoPredio FROM Padr_onCatastral pc WHERE pc.Cliente = ".$Cliente." AND pc.id = ".$idPadron, 'TipoPredio');
            $DeFecha= $TipoPredio==10? "" : " AND CONCAT(pch.A_no,pch.Mes) >= CONCAT(YEAR(NOW())-6,1) ";
            $DiaVencimiento=16;
            $MesVence = [1=>1, 2=>3, 3=>5, 4=>7, 5=>9, 6=>11];
            $FechaActual=strtotime(date("Y-m-d H:i:00",time()));
            $Validacion= Funciones::ObtenValor( "SELECT 
            ( SELECT MAX( A_no ) FROM Padr_onCatastralHistorial pch  WHERE pch.Padr_onCatastral = pc.id AND pch.`Status` in (2,3)  AND pch.A_no=YEAR(NOW()) ) as Anio,
            ( SELECT MAX( DISTINCT Mes ) FROM Padr_onCatastralHistorial pch  WHERE pch.Padr_onCatastral = pc.id AND pch.`Status` in (2,3) AND pch.A_no=YEAR(NOW())  ) as Bimestre,
            ( SELECT COUNT(id) FROM Padr_onCatastralHistorial pch  WHERE pch.Padr_onCatastral = pc.id AND pch.`Status` IN (0,1) ".$DeFecha." AND CONCAT(pch.A_no,pch.Mes) <=".$AnioActual.$Meses[$MesActual] ." and  DAY(NOW())>15) as TotalNoPagadas,
            ( SELECT COALESCE(SUM(id), -1) FROM Padr_onCatastralHistorial pch  WHERE pch.Padr_onCatastral = pc.id) as ValidaTengaLecturas,
            ( SELECT CONCAT('".$DiaVencimiento."',MIN( Mes ),MIN( A_no ) ) FROM Padr_onCatastralHistorial pch  WHERE pch.Padr_onCatastral = pc.id AND pch.`Status` in (0,1)  AND pch.A_no=YEAR(NOW()) ) as AnioMesActualNoPagado
            FROM Padr_onCatastral pc WHERE pc.TipoPredio=".$TipoPredio." AND pc.Cliente =".$Cliente." AND pc.id = ".$idPadron.";" );    
            
                if ( intval($Validacion->ValidaTengaLecturas) == -1 )
                    $Estatus = 2;
                else{
                    if( $Meses[$MesActual] <= $Validacion->Bimestre  && intval($Validacion->Anio) == intval($AnioActual) && intval($Validacion->TotalNoPagadas)==0){
                        $Estatus = 1;
                    }else{
                        $Estatus = 0;
                        $FechaValida=strtotime(date($AnioActual.'-'.str_pad($MesVence[$Meses[intval(date('m'))]], 2, "0", STR_PAD_LEFT).'-'.$DiaVencimiento." H:i:00"));
                        if(intval($DiaVencimiento.$Meses[intval(date('m'))].date('Y'))==$Validacion->AnioMesActualNoPagado && $FechaActual<=$FechaValida && intval($Validacion->TotalNoPagadas)==0)
                            $Estatus = 1;
                    }
                }
         
        return response()->json([
                'success' => '1',
                'estatus'=>$Estatus
                ], 200);
        
    }

    function formaPagada(Request $request) {

        $idPadron = $request->idPadron;
        $idCotizacionForma3=$request->idCotizacionForma3;
        $cliente = $request->cliente;
        
        Funciones::selecionarBase($cliente);

         $Forma3DCC="SELECT  c.id,
        COALESCE( (SELECT ec.id FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1), (SELECT ec.id FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as idContabilidad
        FROM Cotizaci_on c
        INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
        INNER JOIN ConceptoAdicionalesCotizaci_on cac ON (cac.Cotizaci_on=c.id)
        INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=cac.ConceptoAdicionales)
        WHERE
        c.Padr_on = ".$idPadron."
        AND c.id=".$idCotizacionForma3."
        AND ccc.CatalogoDocumento IN (29) AND
        cac.Adicional IS NULL AND
        Origen='PAGO'";
         
        $DatosPredio = DB::select($Forma3DCC);
       
        if(count($DatosPredio)>0){
            //forma pagada
            return response()->json([
                'success' => '1',
            
                ], 200);
        }else{
            //forma no pagada
            return response()->json([
                'success' => '0'
                ], 200);
        }
           
        
        
    }


    function obtenerCertificadoCatastral(Request $request) {
        $idPadron = $request->idPadron;
        $cliente = $request->cliente;
        Funciones::selecionarBase($cliente);
        $consultaDeslinde="SELECT  *, TIMESTAMPDIFF(DAY, NOW(), DD.FechaVencimiento) AS DiasRestantes FROM ( SELECT c.id,
        COALESCE( (SELECT ec.id FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1), (SELECT ec.id FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as idContabilidad ,  
        COALESCE((SELECT N_umeroP_oliza FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1), (SELECT ec.N_umeroP_oliza FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as NumPoliza , 
        COALESCE((SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),  (SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1) ) as Fechapago,
                                ADDDATE( COALESCE((SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),  (SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE (ec.Pago=cac.Pago) AND cac.Cotizaci_on=c.id  LIMIT 1)), INTERVAL 180 DAY) as FechaVencimiento, 
        (SELECT cd.Ruta FROM CatalogoDocumentos cd WHERE ccc.CatalogoDocumento =cd.id  ) DocumentoRuta,
        (SELECT cd.Nombre FROM CatalogoDocumentos cd WHERE ccc.CatalogoDocumento =cd.id  ) NombreDocumento
        FROM Cotizaci_on c
        INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
        INNER JOIN ConceptoAdicionalesCotizaci_on cac ON (cac.Cotizaci_on=c.id)
        INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=cac.ConceptoAdicionales)
        WHERE c.Padr_on = ".$idPadron." AND ccc.CatalogoDocumento = 4 AND cac.Adicional IS NULL AND Origen = 'PAGO' HAVING Fechapago < FechaVencimiento ) DD ORDER BY id desc limit 1";
        
        $Documentos = DB::select($consultaDeslinde);
        $Rutas;
        $consulta;
       
        foreach ($Documentos as $valor){
            if($valor->DocumentoRuta == 'CertificadoCatastral.php'){
                $name = 'CertificadoCatastral';
                $s3 = new LibNubeS3($cliente);
                $idTabla = $valor->idContabilidad . $valor->id;
                $NumeroDocumentos = CelaRepositorio::selectRaw('count(idRepositorio) as numero')
                    ->where([
                        ['Tabla', $name],
                        ['idTabla', $valor->idContabilidad.$valor->id],
                    ])->value('numero');

                $urls = CelaRepositorio::where([
                        ["Tabla", $name],
                        ["idTabla", $valor->idContabilidad.$valor->id]
                    ])->orderByDesc('idRepositorio')->get();
                #return $urls;
                if($NumeroDocumentos){
                    switch($NumeroDocumentos){
                        case 0:
                            $url = "";
                        break;

                        case 1:
                            $firmado = true;
                            foreach($urls as $url){
                                if($url->NombreOriginal == 'noexiste.pdf'){
                                    $firmado = false;
                                    break;
                                }
                            }
                            if($firmado)
                                $url = $urls[0]->Ruta;
                        break;

                        case 2:
                            $firmado = true;
                            foreach($urls as $url){
                                if($url->NombreOriginal == 'noexiste.pdf'){
                                    $firmado = false;
                                    break;
                                }
                            }
                            if($firmado)
                                $url = FuncionesFirma::ObtenerDocumentoFirmado($idTabla, $name, $s3, 0, $cliente);
                            else
                                $url = CelaRepositorio::select('Ruta')->where([["Tabla", $name], ["idTabla", $valor->idContabilidad.$valor->id],['NombreOriginal', '!=', 'noexiste.pdf']])->value('Ruta');
                        break;
                    }
                }else
                    $url = "";
                return response()->json([
                    'success' => '1',
                    'CertificadoCatastral'=>$url,
                    'IdCotizacion'=>$valor->id,], 200);
            }
        }
    }

   function getDatosUbicacion(Request $request){
        $idPadron = $request->idPadron;
        $cliente = $request->cliente;
        
        Funciones::selecionarBase($cliente);
        $ubicacion=DB::table("Padr_onCatastral as P")
        ->join("Contribuyente as C","C.id","=","P.Contribuyente")
        ->where("P.id",$idPadron)
        ->select("Municipio","Localidad")
        ->first();
        return response()->json([
             'success' => '1',
             "ubicacion"=> $ubicacion
            ], 200);
    
   }

   public function  insertarNotificacion(Request $request){
    $cliente=$request->Cliente;
    $idTramite=$request->IdTramite;
    $notificacion=$request->Notificacion;
    $idDocumentoCatalogo=$request->IdDocumentoCatalogo;

    Funciones::selecionarBase( $cliente);
   
    DB::table('Padr_onCatastralTramitesISAINotariosNoficaciones')->insert([
        ['id' => null, 
        'Notificacion' => $notificacion,
        'IdTramite'=>$idTramite,
        'IdDocumentoCatalogo'=>$idDocumentoCatalogo,
        
       ]
    ]);
}


public function  obtenerOrdenPagoISAI($cliente,$idCotizacion){
    
   /* $cliente=$request->Cliente;
    $idCotizacion=$request->IdCotizacion;*/
   
    Funciones::selecionarBase( $cliente);
   
    $datosCotizacion=DB::table("Cotizaci_on")
    ->WHERE("id",$idCotizacion)
    ->get();
   
    #precode($datosCotizacion,1);
    $idPadron=$datosCotizacion[0]->Padr_on;
    $Recibo= PortalNotariosController::ReciboServicioISAI($datosCotizacion);
    include( app_path() . '/Libs/Wkhtmltopdf.php' );
    $htmlGlobal= $Recibo['html']."".$Recibo['saltos']."".$Recibo['html'];
            try {
                $nombre = uniqid() . "_" . $idCotizacion;
                #$wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'page_width'=>120,'page_height'=>200,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
                $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'margins' => ['top' => 10, 'left' => 10,'right'=>10,'bottom'=>10]));
                $wkhtmltopdf->setHtml( $htmlGlobal);
                //$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, $nombre.".pdf");		
                $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $nombre . ".pdf");
                //return "repositorio/temporal/" . $nombre . ".pdf";
               /* return response()->json([
                    'success' => '1',
                    'ruta' => "repositorio/temporal/" . $nombre . ".pdf",
                ]);*/
                return  "repositorio/temporal/" . $nombre . ".pdf";
            } catch (Exception $e) {
                echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
            }	
    
    
}

public function ReciboOrdenPagoISAI($datosCotizacion) {
        $Recibo ="";

        switch ($datosCotizacion['Tipo']) {
            case 1:
                $Recibo = ReciboServicio($datosCotizacion);
                break;
            case 2:
                $Recibo = ReciboAguaOPD($datosCotizacion);
                break;
            case 3:
                $Recibo = ReciboPredial($datosCotizacion);
                break;
            case 4:
                $Recibo = ReciboLicenciaFuncionamiento($datosCotizacion);
                break;
            case 9:
                $Recibo = ReciboAguaOPD($datosCotizacion);
                break;
            case 10:
                $Recibo = ReciboPredial($datosCotizacion);
                break;
            case 11:
                $Recibo = ReciboISAI($datosCotizacion);
                break;
            case 12: //Licencia de Desarrollo urbano
                $Recibo = ReciboServicio($datosCotizacion);
                break;
            
            
        }
        return $Recibo['html']."".$Recibo['saltos']."".$Recibo['html'];
    }





public function ReciboServicioISAI($Cotizaci_on){        
    $Cliente= $Cotizaci_on[0]->Cliente;
    $CTotalPagar = 0;
    $ConsultaDatosPago="SELECT if(c1.PersonalidadJur_idica=1,IF( CONCAT(c1.Nombres,' ',c1.ApellidoPaterno,' ',c1.ApellidoMaterno) IS NOT NULL AND CONCAT(c1.Nombres,' ',c1.ApellidoPaterno,' ',c1.ApellidoMaterno)!='',CONCAT(c1.Nombres,' ',c1.ApellidoPaterno,' ',c1.ApellidoMaterno),  d.NombreORaz_onSocial),d.NombreORaz_onSocial)  ContribuyenteNombre, d.RFC, d.NombreORaz_onSocial, d.EntidadFederativa, d.Localidad, d.Municipio, d.Colonia, d.Calle, d.N_umeroExterior, d.C_odigoPostal, c1.id as Contribuyente
                ,c1.Calle_c,c1.Localidad_c,c1.Municipio_c,c1.Colonia_c,c1.Rfc,c1.EntidadFederativa_c
                FROM Contribuyente c1 INNER JOIN DatosFiscales d ON ( c1.DatosFiscales = d.id  )  
                WHERE c1.id=".$Cotizaci_on[0]->Contribuyente;
    $DatosPago= DB::select($ConsultaDatosPago);
    // $DatosFiscales=$DatosPago[0]->RFC."<br> Calle ".ucwords(strtolower($DatosPago[0]->Calle))."<br> Colonia ".ucwords(strtolower($DatosPago[0]->Colonia))."<br> ".ucwords(strtolower((is_numeric($DatosPago[0]->Localidad)?DB::table("Localidad")->where("id",$DatosPago[0]->Localidad)->value("Nombre"):$DatosPago[0]->Localidad)))." ".ucwords(strtolower((is_numeric($DatosPago[0]->Municipio)?DB::table("Municipio")->where("id",$DatosPago[0]->Municipio)->value("Nombre"):$DatosPago[0]->Municipio)))." ".ucwords(strtolower((is_numeric($DatosPago['EntidadFederativa'])?ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago['EntidadFederativa'], "Nombre"):$DatosPago['EntidadFederativa'])));
    //$datosDeContribuyente=$DatosPago['Rfc']."<br> Calle ".ucwords(strtolower($DatosPago['Calle_c']))."<br> Colonia ".ucwords(strtolower($DatosPago['Colonia_c']))."<br> ".ucwords(strtolower((is_numeric($DatosPago['Localidad_c'])?ObtenValor("select Nombre from Localidad where id=".$DatosPago['Localidad_c'], "Nombre"):$DatosPago['Localidad_c'])))." ".ucwords(strtolower((is_numeric($DatosPago['Municipio_c'])?ObtenValor("select Nombre from Municipio where id=".$DatosPago['Municipio_c'], "Nombre"):$DatosPago['Municipio_c'])))." ".ucwords(strtolower((is_numeric($DatosPago['EntidadFederativa_c'])?ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago['EntidadFederativa_c'], "Nombre"):$DatosPago['EntidadFederativa_c']))); 
    $DatosFiscales=$DatosPago[0]->RFC."<br> Calle ".ucwords(strtolower($DatosPago[0]->Calle))."<br> Colonia ".ucwords(strtolower($DatosPago[0]->Colonia))."<br> ".ucwords(strtolower((is_numeric($DatosPago[0]->Localidad)?DB::table("Localidad")->where("id",$DatosPago[0]->Localidad)->value("Nombre"):$DatosPago[0]->Localidad)))." ".ucwords(strtolower((is_numeric($DatosPago[0]->Municipio)?DB::table("Municipio")->where("id",$DatosPago[0]->Municipio)->value("Nombre"):$DatosPago[0]->Municipio)))." ".ucwords(strtolower((is_numeric($DatosPago[0]->EntidadFederativa)?DB::table("EntidadFederativa")->where("id",$DatosPago[0]->EntidadFederativa)->value("Nombre"):$DatosPago[0]->EntidadFederativa)));    
    $datosDeContribuyente=$DatosPago[0]->RFC."<br> Calle ".ucwords(strtolower($DatosPago[0]->Calle_c))."<br> Colonia ".ucwords(strtolower($DatosPago[0]->Colonia_c))."<br> ".ucwords(strtolower((is_numeric($DatosPago[0]->Localidad_c)?DB::table("Localidad")->where("id",$DatosPago[0]->Localidad_c)->value("Nombre"):$DatosPago[0]->Localidad_c)))." ".ucwords(strtolower((is_numeric($DatosPago[0]->Municipio_c)?DB::table("Municipio")->where("id",$DatosPago[0]->Municipio_c)->value("Nombre"):$DatosPago[0]->Municipio_c)))." ".ucwords(strtolower((is_numeric($DatosPago[0]->EntidadFederativa_c)?DB::table("EntidadFederativa")->where("id",$DatosPago[0]->EntidadFederativa_c)->value("Nombre"):$DatosPago[0]->EntidadFederativa_c)));
    $hojamembretada=DB::table("CelaRepositorio")
                    ->where("CelaRepositorio.idRepositorio",DB::raw("(select HojaMembretada from Cliente where id=".$Cliente.")"))
                    ->value("Ruta");
    $ConsultaCliente=("select C.id, Descripci_on,DF.RFC,DF.Calle,DF.N_umeroExterior,DF.Colonia,DF.C_odigoPostal, DF.CorreoElectr_onico, (select Nombre from Localidad L where DF.Localidad=L.id) as Localidad, (select Ruta from CelaRepositorioC where CelaRepositorioC.idRepositorio=C.Logotipo) as Logo from Cliente C INNER JOIN DatosFiscalesCliente DF on DF.id=C.DatosFiscales where C.id=$Cliente");
    $Cliente=DB::select($ConsultaCliente);
    $ConsultaCuentaBancaria=("SELECT (SELECT Nombre FROM Banco B WHERE B.id = CB.Banco) as Banco, N_umeroCuenta, Clabe from CuentaBancaria CB WHERE CuentaDeRecaudacion=1 and CB.Cliente=".$Cliente[0]->id." limit 1");
    $CuentaBancaria=DB::select($ConsultaCuentaBancaria);
    if(!$CuentaBancaria) {
        $Banco = "";
        $N_umeroCuenta = "";
        $Clabe = "";
    }else{
        $Banco = $CuentaBancaria[0]->Banco;
        $N_umeroCuenta = $CuentaBancaria[0]->N_umeroCuenta;
        $Clabe = $CuentaBancaria[0]->Clabe;
    }
    
    $ConsultaDatosFiscalesC="(select * from DatosFiscalesCliente where DatosFiscalesCliente.id=(select DatosFiscales from Cliente where id=".$Cliente[0]->id."))";
    $DatosFiscalesC=DB::select($ConsultaDatosFiscalesC);
    $LugarDePago=(is_numeric($DatosFiscalesC[0]->Municipio)?DB::table("Municipio")->where("id",$DatosFiscalesC[0]->Municipio)->value("Nombre"):$DatosFiscalesC[0]->Municipio)." ".(is_numeric($DatosFiscalesC[0]->EntidadFederativa)?DB::table("EntidadFederativa")->where("id",$DatosFiscalesC[0]->EntidadFederativa)->value("Nombre"):$DatosFiscalesC[0]->EntidadFederativa);
    $tamanio_dehoja="735px"; //735 ideal
    $N_umeroP_oliza='';
    $ConsultaConceptos = "SELECT c.id as idConceptoCotizacion, co.TipoContribuci_on as TipoContribuci_on, sum(co.Importe) as Importe, co.Adicional, if(co.Adicional is not null,COUNT(co.Cantidad),co.Cantidad) as Cantidad ,
                        (SELECT Padr_on FROM Cotizaci_on WHERE id=co.Cotizaci_on) AS idPadron,'' AS idCC, co.Cotizaci_on AS Cotizaci_on, CURDATE() AS FechaActualV5, sum(co.Importe) as total,
                        (SELECT Padr_on FROM Cotizaci_on WHERE id=co.Cotizaci_on) AS Padr_on, 11 AS Tipo,(SELECT Cliente FROM Cotizaci_on WHERE id=co.Cotizaci_on) AS Cliente, 
                        co.MontoBase as MontoBase, if(co.Adicional is  null,c.Descripci_on,(SELECT Descripci_on FROM RetencionesAdicionales WHERE RetencionesAdicionales.id=co.Adicional )) as DescripcionConcepto
                        FROM ConceptoAdicionalesCotizaci_on co INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id )
                        WHERE co.Cotizaci_on = ".$Cotizaci_on[0]->id." and co.Estatus in (0,-1) GROUP BY co.ConceptoAdicionales LIMIT 6";
    #precode($ConsultaConceptos,1,1);
    $Conceptos="";
    $ejecutaConceptos=DB::select($ConsultaConceptos);
    $redondeo=2;
    $contadorConceptos=0;
    $OTrosImportes=0;
    foreach($ejecutaConceptos as $filaConcepto){
        $filaConcepto->EjercicioFiscal = date("Y");
        try {
            Funciones::ObtenerMultaDinamica($filaConcepto);
        } catch (Exception $e) {
        }
        //PortalNotariosController::ReciboServicioISAI($datosCotizacion);
        $Conceptos.='<tr>
                        <td width="10%"><center>'.number_format($filaConcepto->Cantidad,2).'</center></td>
                        <td width="75%" colspan="4">'.substr( $filaConcepto->DescripcionConcepto ,0, 80).'</td>
                        <td align="right" width="15%" class="valorUnitario">$ '.number_format(floatval($filaConcepto->Importe), $redondeo,'.', ',').'</td>
                    </tr>';
        if(isset($filaConcepto->Multa_Concepto) && $filaConcepto->Multa_Concepto>0){
            $Conceptos .= '<tr >
                            <td width="10%"  ><center>' . number_format(1, 2) . '</center></td>
                            <td width="75%" colspan="4">' . substr(Funciones::ObtenValor("SELECT c.Descripci_on FROM ConceptoCobroCaja c WHERE c.id=".$filaConcepto->Multa_Concepto,"Descripci_on"), 0, 80) . '</td>
                            <td align="right" width="15%" class="valorUnitario">$ ' . number_format(floatval($filaConcepto->Multa_Importe), $redondeo, '.', ',') . '</td>
                        </tr>';

            $Conceptos .= '<tr >
                            <td width="10%"  ><center>' . number_format(1, 2) . '</center></td>
                            <td width="75%" colspan="4">' . substr("Recargos", 0, 80) . '</td>
                            <td align="right" width="15%" class="valorUnitario">$ ' . number_format(floatval($filaConcepto->recargo), $redondeo, '.', ',') . '</td>
                        </tr>';
            $OTrosImportes+=$filaConcepto->Multa_Importe+$filaConcepto->recargo;
        }  
        $contadorConceptos++;
    }
    
    $contadorConceptos = 8- $contadorConceptos;
    $Saltos = "";
    for($i=0;$i<$contadorConceptos;$i++)
        $Saltos.="<br>";
    
    $Area=DB::table("Cotizaci_on AS c")
            ->select(DB::raw("(SELECT a.Descripci_on FROM AreasAdministrativas as a  WHERE a.id=c.AreaAdministrativa) as Area"))
            ->where("c.id","=",$Cotizaci_on[0]->id)
            ->value("Area");
    
    //$ConsultaImporte = "SELECT SUM(cac.Importe) as Importe FROM ConceptoAdicionalesCotizaci_on cac WHERE cac.Cotizaci_on=".$Cotizaci_on[0]->id." and cac.Estatus IN(0,-1)";
    
    
    $Importe = $OTrosImportes + DB::table("ConceptoAdicionalesCotizaci_on")
    ->select(DB::raw("SUM(Importe) as Importe"))
    ->where("Cotizaci_on",$Cotizaci_on[0]->id)
    ->value("Importe");
    
    $fecha = date("Y-m-d");
    $DescuentoT=0;
    $Decuento = DB::table("XMLIngreso")->WHERE( "idCotizaci_on",$Cotizaci_on[0]->id)->value("DatosExtra");
    $DatosExtras= json_decode($Decuento,true);
    
    if(isset($DatosExtras['Descuento']) && $DatosExtras['Descuento']!="" && $Importe>0)
        $Importe-= $DatosExtras['Descuento'];
    $Vigencia ="<table style='padding:-35px 0 0 0;margin:-10px 0 0 0;' border='0' width='787px'>
                    <tr>
                        <td colspan='11'><br /> 
                            <img width='787px' height='1px' src='".asset(Storage::url(env('IMAGES') . 'barraColores.png')) ."'>
                        </td>
                    </tr>
                    <tr>
                        <td colspan='11' align='right'>
                            <span style='font-size:12px;'>Expedici&oacute;n: ".date('d/m/Y H:i:s')."</span> - <span style='font-size:12px;'></span> <span style='font-size:12px;'>".' '.$Cotizaci_on[0]->Usuario."</span>
                        </td>
                    </tr>
                    <tr>
                        <td colspan='11' align='right'></td>
                    </tr>
                </table>";
                   
    $ImportePagoAux=  number_format($Importe,2);
    $letras=utf8_decode(PortalNotariosController::num2letras($ImportePagoAux,0,0)." pesos  ");
    $ultimo = substr (strrchr ($ImportePagoAux, "."), 1, 2); //recupero lo que este despues del decimal
    if($ultimo=="")
    $ultimo="00";
    $importePagoLetra = $letras." ".$ultimo."/100 M.N.";
    setlocale(LC_TIME,"es_MX.UTF-8");
    $FechaCotizacion=strftime("%d de ",strtotime($Cotizaci_on[0]->Fecha)).ucfirst(strftime("%B de %Y",strtotime($Cotizaci_on[0]->Fecha)));

    $HTML ='<html lang="es">
            <head>
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                .contenedor{
                    height:735px;
                    width: 975px;
                    /*border: 1px solid red;*/
                }
                body{
                    font-size: 12px;
                }
                .main_container{
                    padding-top:15px;
                    padding-left:5px;
                    z-index: 99;
                    background-size: cover;
                    width:975px;
                    height:735px;
                    position:relative;
                }
                table{
                    font-size: 14px;
                }
                .break{
                    display: block;
                    clear: both;
                    page-break-after: always;
                }
                h1 {
                    font-size: 300%;
                }
                .table1 > thead > tr > th, 
                .table1>tbody>tr>td> {
                    padding: 2px 5px 2px 2px !important;
                }
                .table-bordered>tbody>tr>td {
                    border: 0px solid #ddd;
                }
            </style>
            </head>
            <div  >
            <body >
            <table style="height: 50px;" width="787px" class="table">
                <tbody>
                    <tr>
                        <td width="20%" align="center"><img src="'.asset($Cliente[0]->Logo).'" alt="Logo del cliente" style="height: 120px;"></td>
                        <td style="text-align: right;">
                            <p>'.$Cliente[0]->Descripci_on.'<br>Domicilio Fiscal: Calle '.$Cliente[0]->Calle.' No. '.$Cliente[0]->N_umeroExterior.'<br>Colonia '.$Cliente[0]->Colonia.' Codigo Postal '.$Cliente[0]->C_odigoPostal.'<br>'.$Cliente[0]->Localidad.', Guerrero<br>RFC: '.$Cliente[0]->RFC.'</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <table style="height: 50px;" width="787px" class="table">
                <tbody>
                    <tr>
                        <td  style="text-align: left;" width="20%">
                            <p><span style="color:red; font-size:18px;"> '.$Area.'</span></p>
                        </td>
                        <td  style="text-align: right;" width="20%">
                            <p><span style="color:red; font-size:18px;">Trámite ISAI</span></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <img style="width: 787px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />
            <br>
            <table style="height: 50px;" width="787px" class="table">
                <tbody>
                    <tr>
                        <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
                            <b>Nombre de Contribuyente: </b><br>'.$DatosPago[0]->ContribuyenteNombre.'<br>
                        </td>
                        <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
                            <b>Razon Social: </b><br>'.$DatosPago[0]->NombreORaz_onSocial.'
                        </td>
                    </tr>
                </tbody>
            </table>
            <hr>
            <table style="height: 50px;" width="787px" class="table">
                <tbody>
                '.$Conceptos.'
                </tbody>
            </table>
            '.$Saltos.'
            <img style="width: 787px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')) .'" alt="Mountain View" />
            <br>
            <table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="787px">
                <br>
                <tr>
                    <td colspan="6">
                        <span  style="  font-size: 20px;">Folio: <span style="color:red;"> '.$Cotizaci_on[0]->FolioCotizaci_on.'</span></span></span>
                    </td>
                    <td colspan="6" align="right">
                        <span  style=" font-size: 20px;">Total: <span style="color:red;"> '. number_format($Importe,2).'</span></span></span>
                    </td>   
                </tr>
            </table>
            '.$Vigencia.'
            </body>
            </div>
            </html>';
    $arr['html']=$HTML;
    $arr['saltos']="<strong>_ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _</strong><br><br>";
    return $arr ;
}

public function ReciboServicio($Cotizaci_on){
        
    $Cliente= $Cotizaci_on[0]->Cliente;
            $CTotalPagar = 0;
            $ConsultaDatosPago="SELECT if(c1.PersonalidadJur_idica=1,IF( CONCAT(c1.Nombres,' ',c1.ApellidoPaterno,' ',c1.ApellidoMaterno) IS NOT NULL AND CONCAT(c1.Nombres,' ',c1.ApellidoPaterno,' ',c1.ApellidoMaterno)!='',CONCAT(c1.Nombres,' ',c1.ApellidoPaterno,' ',c1.ApellidoMaterno),  d.NombreORaz_onSocial),d.NombreORaz_onSocial)  ContribuyenteNombre, d.RFC, d.NombreORaz_onSocial, d.EntidadFederativa, d.Localidad, d.Municipio, d.Colonia, d.Calle, d.N_umeroExterior, d.C_odigoPostal, c1.id as Contribuyente
                ,c1.Calle_c,c1.Localidad_c,c1.Municipio_c,c1.Colonia_c,c1.Rfc,c1.EntidadFederativa_c
    FROM Contribuyente c1  
                            INNER JOIN DatosFiscales d ON ( c1.DatosFiscales = d.id  )  
            WHERE c1.id=".$Cotizaci_on[0]->Contribuyente;
   $DatosPago= DB::select($ConsultaDatosPago);
   // $DatosFiscales=$DatosPago[0]->RFC."<br> Calle ".ucwords(strtolower($DatosPago[0]->Calle))."<br> Colonia ".ucwords(strtolower($DatosPago[0]->Colonia))."<br> ".ucwords(strtolower((is_numeric($DatosPago[0]->Localidad)?DB::table("Localidad")->where("id",$DatosPago[0]->Localidad)->value("Nombre"):$DatosPago[0]->Localidad)))." ".ucwords(strtolower((is_numeric($DatosPago[0]->Municipio)?DB::table("Municipio")->where("id",$DatosPago[0]->Municipio)->value("Nombre"):$DatosPago[0]->Municipio)))." ".ucwords(strtolower((is_numeric($DatosPago['EntidadFederativa'])?ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago['EntidadFederativa'], "Nombre"):$DatosPago['EntidadFederativa'])));
   
    //$datosDeContribuyente=$DatosPago['Rfc']."<br> Calle ".ucwords(strtolower($DatosPago['Calle_c']))."<br> Colonia ".ucwords(strtolower($DatosPago['Colonia_c']))."<br> ".ucwords(strtolower((is_numeric($DatosPago['Localidad_c'])?ObtenValor("select Nombre from Localidad where id=".$DatosPago['Localidad_c'], "Nombre"):$DatosPago['Localidad_c'])))." ".ucwords(strtolower((is_numeric($DatosPago['Municipio_c'])?ObtenValor("select Nombre from Municipio where id=".$DatosPago['Municipio_c'], "Nombre"):$DatosPago['Municipio_c'])))." ".ucwords(strtolower((is_numeric($DatosPago['EntidadFederativa_c'])?ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago['EntidadFederativa_c'], "Nombre"):$DatosPago['EntidadFederativa_c'])));
 
    $DatosFiscales=$DatosPago[0]->RFC."<br> Calle ".ucwords(strtolower($DatosPago[0]->Calle))."<br> Colonia ".ucwords(strtolower($DatosPago[0]->Colonia))."<br> ".ucwords(strtolower((is_numeric($DatosPago[0]->Localidad)?DB::table("Localidad")->where("id",$DatosPago[0]->Localidad)->value("Nombre"):$DatosPago[0]->Localidad)))." ".ucwords(strtolower((is_numeric($DatosPago[0]->Municipio)?DB::table("Municipio")->where("id",$DatosPago[0]->Municipio)->value("Nombre"):$DatosPago[0]->Municipio)))." ".ucwords(strtolower((is_numeric($DatosPago[0]->EntidadFederativa)?DB::table("EntidadFederativa")->where("id",$DatosPago[0]->EntidadFederativa)->value("Nombre"):$DatosPago[0]->EntidadFederativa)));
    
    $datosDeContribuyente=$DatosPago[0]->RFC."<br> Calle ".ucwords(strtolower($DatosPago[0]->Calle_c))."<br> Colonia ".ucwords(strtolower($DatosPago[0]->Colonia_c))."<br> ".ucwords(strtolower((is_numeric($DatosPago[0]->Localidad_c)?DB::table("Localidad")->where("id",$DatosPago[0]->Localidad_c)->value("Nombre"):$DatosPago[0]->Localidad_c)))." ".ucwords(strtolower((is_numeric($DatosPago[0]->Municipio_c)?DB::table("Municipio")->where("id",$DatosPago[0]->Municipio_c)->value("Nombre"):$DatosPago[0]->Municipio_c)))." ".ucwords(strtolower((is_numeric($DatosPago[0]->EntidadFederativa_c)?DB::table("EntidadFederativa")->where("id",$DatosPago[0]->EntidadFederativa_c)->value("Nombre"):$DatosPago[0]->EntidadFederativa_c)));
    
    $hojamembretada=DB::table("CelaRepositorio")
    ->where("CelaRepositorio.idRepositorio",DB::raw("(select HojaMembretada from Cliente where id=".$Cliente.")"))
    ->value("Ruta");
    
    $ConsultaCliente=("select C.id, Descripci_on,DF.RFC,DF.Calle,DF.N_umeroExterior,DF.Colonia,DF.C_odigoPostal, DF.CorreoElectr_onico, (select Nombre from Localidad L where DF.Localidad=L.id) as Localidad, (select Ruta from CelaRepositorioC where CelaRepositorioC.idRepositorio=C.Logotipo) as Logo from Cliente C INNER JOIN DatosFiscalesCliente DF on DF.id=C.DatosFiscales where C.id=$Cliente");
    
    $Cliente=DB::select($ConsultaCliente);
    
    $ConsultaCuentaBancaria=("SELECT (SELECT Nombre FROM Banco B WHERE B.id = CB.Banco) as Banco, N_umeroCuenta, Clabe from CuentaBancaria CB WHERE CuentaDeRecaudacion=1 and CB.Cliente=".$Cliente[0]->id." limit 1");
    $CuentaBancaria=DB::select($ConsultaCuentaBancaria);
    
    
    if(!$CuentaBancaria) {
    $Banco = "";
    $N_umeroCuenta = "";
    $Clabe = "";
    }
    else{
    $Banco = $CuentaBancaria[0]->Banco;
    $N_umeroCuenta = $CuentaBancaria[0]->N_umeroCuenta;
    $Clabe = $CuentaBancaria[0]->Clabe;
    
    }
    
    $ConsultaDatosFiscalesC="(select * from DatosFiscalesCliente where DatosFiscalesCliente.id=(select DatosFiscales from Cliente where id=".$Cliente[0]->id."))";
    $DatosFiscalesC=DB::select($ConsultaDatosFiscalesC);
    
    $LugarDePago=(is_numeric($DatosFiscalesC[0]->Municipio)?DB::table("Municipio")->where("id",$DatosFiscalesC[0]->Municipio)->value("Nombre"):$DatosFiscalesC[0]->Municipio)." ".(is_numeric($DatosFiscalesC[0]->EntidadFederativa)?DB::table("EntidadFederativa")->where("id",$DatosFiscalesC[0]->EntidadFederativa)->value("Nombre"):$DatosFiscalesC[0]->EntidadFederativa);

    $tamanio_dehoja="735px"; //735 ideal
    $N_umeroP_oliza='';
    $ConsultaConceptos =
    "SELECT co.TipoContribuci_on as TipoContribuci_on, sum(co.Importe) as Importe, 
    co.Adicional, if(co.Adicional is not null,COUNT(co.Cantidad),co.Cantidad) as Cantidad ,
    co.MontoBase as MontoBase,
    if(co.Adicional is  null,c.Descripci_on,(SELECT Descripci_on FROM RetencionesAdicionales WHERE RetencionesAdicionales.id=co.Adicional )) as DescripcionConcepto
    FROM ConceptoAdicionalesCotizaci_on co 
    INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id )
    WHERE co.Cotizaci_on = ".$Cotizaci_on[0]->id." and co.Estatus in (0,-1) 
    GROUP BY co.ConceptoAdicionales LIMIT 6";
    #precode($ConsultaConceptos,1,1);
    $Conceptos="";
    $ejecutaConceptos=DB::select($ConsultaConceptos);
    
    $redondeo=2;
    $contadorConceptos=0;
    foreach($ejecutaConceptos as $filaConcepto){
        $Conceptos.='<tr >
                                                                                <td width="10%"  ><center>'.number_format($filaConcepto->Cantidad,2).'</center></td>
                                                                                <td width="75%" colspan="4">'.substr( $filaConcepto->DescripcionConcepto ,0, 80).'</td>
                                                                                <td align="right" width="15%" class="valorUnitario">$ '.number_format(floatval($filaConcepto->Importe), $redondeo,'.', ',').'</td>
                                                                                </tr>';
        
        $contadorConceptos++;
        
    }
    
    $contadorConceptos = 8- $contadorConceptos;
    $Saltos = "";
    for($i=0;$i<$contadorConceptos;$i++)
        $Saltos.="<br>";
    
    $Area=DB::table("Cotizaci_on AS c")
    ->select(DB::raw("(SELECT a.Descripci_on FROM AreasAdministrativas as a  WHERE a.id=c.AreaAdministrativa) as Area"))
    ->where("c.id","=",$Cotizaci_on[0]->id)
    ->value("Area");
    
    //$ConsultaImporte = "SELECT SUM(cac.Importe) as Importe FROM ConceptoAdicionalesCotizaci_on cac WHERE cac.Cotizaci_on=".$Cotizaci_on[0]->id." and cac.Estatus IN(0,-1)";
    
    $Importe = DB::table("ConceptoAdicionalesCotizaci_on")
    ->select(DB::raw("SUM(Importe) as Importe"))
    ->where("Cotizaci_on",$Cotizaci_on[0]->id)
    ->value("Importe");

    
    $fecha = date("Y-m-d");
    $DescuentoT=0;
    $Decuento = DB::table("XMLIngreso")->WHERE( "idCotizaci_on",$Cotizaci_on[0]->id)->value("DatosExtra");
    
    $DatosExtras= json_decode($Decuento,true);
    
    if(isset($DatosExtras['Descuento']) && $DatosExtras['Descuento']!="" && $Importe>0)
        $Importe-= $DatosExtras['Descuento'];
    
    
    $Vigencia="
                    <table style='padding:-35px 0 0 0;margin:-10px 0 0 0;' border='0' width='787px'>
                    <tr>
                        <td colspan='11'><br /> 
                            <img width='787px' height='1px' src='".asset(Storage::url(env('IMAGES') . 'barraColores.png')) ."'>
                        </td>
                    </tr>

                    <tr>
                        <td colspan='11' align='right'>
                                                        <span style='font-size:12px;'>Expedici&oacute;n: ".date('d/m/Y H:i:s')."</span> - <span style='font-size:12px;'></span> <span style='font-size:12px;'>".' '.$Cotizaci_on[0]->Usuario."</span>

                        </td>
                    </tr>
                    
                    <tr>
                        <td colspan='11' align='right'>
                            
                        </td>
                    </tr>
                    </table>
                    ";
                   
    $ImportePagoAux=  number_format($Importe,2);
    $letras=utf8_decode(PortalNotariosController::num2letras($ImportePagoAux,0,0)." pesos  ");
    $ultimo = substr (strrchr ($ImportePagoAux, "."), 1, 2); //recupero lo que este despues del decimal
    if($ultimo=="")
    $ultimo="00";
    $importePagoLetra = $letras." ".$ultimo."/100 M.N.";
    setlocale(LC_TIME,"es_MX.UTF-8");
    $FechaCotizacion=strftime("%d de ",strtotime($Cotizaci_on[0]->Fecha)).ucfirst(strftime("%B de %Y",strtotime($Cotizaci_on[0]->Fecha)));

    $HTML ='
    <html lang="es">
    <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
    .contenedor{
        height:735px;
        width: 975px;
        /*border: 1px solid red;*/
    }
    
    body{
        font-size: 12px;
    }
    

    .main_container{

        padding-top:15px;
        padding-left:5px;
        z-index: 99;
        background-size: cover;
        width:975px;
        height:735px;
        position:relative;

    }
    table{
        font-size: 14px;
    }
    .break{
        display: block;
        clear: both;
        page-break-after: always;
    }
    h1 {
        font-size: 300%;
    }
                        .table1 > thead > tr > th, 
                                                    .table1>tbody>tr>td> {
                                                        padding: 2px 5px 2px 2px !important;
                                                    }

                                                    .table-bordered>tbody>tr>td {
                                                        border: 0px solid #ddd;
                                                    }

    </style>
    </head>
    <div  >
    <body >

    <table style="height: 50px;" width="787px" class="table">
    <tbody>
    <tr>
    <td width="20%" align="center"><img src="'.asset($Cliente[0]->Logo).'" alt="Logo del cliente" style="height: 120px;"></td>
    <td style="text-align: right;">
        <p>'.$Cliente[0]->Descripci_on.'<br>Domicilio Fiscal: Calle '.$Cliente[0]->Calle.' No. '.$Cliente[0]->N_umeroExterior.'<br>Colonia '.$Cliente[0]->Colonia.' Codigo Postal '.$Cliente[0]->C_odigoPostal.'<br>'.$Cliente[0]->Localidad.', Guerrero<br>RFC: '.$Cliente[0]->RFC.'</p>
    </td>
    </tr>
    </tbody>

    </table>

    <table style="height: 50px;" width="787px" class="table">
    <tbody>

    <tr>
    <td  style="text-align: left;" width="20%">
    <p><span style="color:red; font-size:18px;"> '.$Area.'</span></p>
        </td>

    <td  style="text-align: right;" width="20%">
    <p><span style="color:red; font-size:18px;">Servicios Diversos</span></p>
        </td>
    </tr>
    </tbody>
    </table>

    <img style="width: 787px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />
    <br>
    <table style="height: 50px;" width="787px" class="table">
    <tbody>
    <tr>
        <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
                    <b>Nombre de Contribuyente: </b><br>'.$DatosPago[0]->ContribuyenteNombre.'<br>
            
                    </td>
                    <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
        <b>Razon Social: </b><br>'.$DatosPago[0]->NombreORaz_onSocial.'
    </td>
    
    </tr>
    </tbody>
    </table>
    <hr>
    <table style="height: 50px;" width="787px" class="table">
    <tbody>
    '.$Conceptos.'
    </tbody>
    </table>
    '.$Saltos.'
    <img style="width: 787px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')) .'" alt="Mountain View" />
    <br>
    <table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="787px">
    <br>
    <tr>
    <td colspan="6">
    <span  style="  font-size: 20px;">Folio: <span style="color:red;"> '.$Cotizaci_on[0]->FolioCotizaci_on.'</span></span></span>
    </td>
    <td colspan="6" align="right">
    <span  style=" font-size: 20px;">Total: <span style="color:red;"> '. number_format($Importe,2).'</span></span></span>
    </td>   
    </tr>
    </table>
    '.$Vigencia.'

    </body>
    </div>
    </html>

    ';

    $arr['html']=$HTML;
    $arr['saltos']="<strong>_ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _</strong><br><br>";
    return $arr ;

}


public function num2letras($num, $fem = true, $dec = true) { 
    $matuni[2]  = "dos"; 
    $matuni[3]  = "tres"; 
    $matuni[4]  = "cuatro"; 
    $matuni[5]  = "cinco"; 
    $matuni[6]  = "seis"; 
    $matuni[7]  = "siete"; 
    $matuni[8]  = "ocho"; 
    $matuni[9]  = "nueve"; 
    $matuni[10] = "diez"; 
    $matuni[11] = "once"; 
    $matuni[12] = "doce"; 
    $matuni[13] = "trece"; 
    $matuni[14] = "catorce"; 
    $matuni[15] = "quince"; 
    $matuni[16] = "dieciseis"; 
    $matuni[17] = "diecisiete"; 
    $matuni[18] = "dieciocho"; 
    $matuni[19] = "diecinueve"; 
    $matuni[20] = "veinte"; 
    $matunisub[2] = "dos"; 
    $matunisub[3] = "tres"; 
    $matunisub[4] = "cuatro"; 
    $matunisub[5] = "quin"; 
    $matunisub[6] = "seis"; 
    $matunisub[7] = "sete"; 
    $matunisub[8] = "ocho"; 
    $matunisub[9] = "nove"; 
    
    $matdec[2] = "veint"; 
    $matdec[3] = "treinta"; 
    $matdec[4] = "cuarenta"; 
    $matdec[5] = "cincuenta"; 
    $matdec[6] = "sesenta"; 
    $matdec[7] = "setenta"; 
    $matdec[8] = "ochenta"; 
    $matdec[9] = "noventa"; 
    $matsub[3]  = 'mill'; 
    $matsub[5]  = 'bill'; 
    $matsub[7]  = 'mill'; 
    $matsub[9]  = 'trill'; 
    $matsub[11] = 'mill'; 
    $matsub[13] = 'bill'; 
    $matsub[15] = 'mill'; 
    $matmil[4]  = 'millones'; 
    $matmil[6]  = 'billones'; 
    $matmil[7]  = 'de billones'; 
    $matmil[8]  = 'millones de billones'; 
    $matmil[10] = 'trillones'; 
    $matmil[11] = 'de trillones'; 
    $matmil[12] = 'millones de trillones'; 
    $matmil[13] = 'de trillones'; 
    $matmil[14] = 'billones de trillones'; 
    $matmil[15] = 'de billones de trillones'; 
    $matmil[16] = 'millones de billones de trillones'; 

    $num= trim((string)@$num); 
    if ($num[0] == '-') { 
        $neg = 'menos '; 
        $num = substr($num, 1); 
    }else 
    $neg = ''; 
    while ($num == '0') $num = substr($num, 1); 
    if ($num < '1' or $num > 9) $num = '0' . $num; 
    $zeros = true; 
    $punt = false; 
    $ent = ''; 
    $fra = ''; 
    for ($c = 0; $c < strlen($num); $c++) { 
        $n = $num[$c]; 
        if (! (strpos(".,'''", $n) === false)) { 
            if ($punt) break; 
            else{ 
                $punt = true; 
                continue; 
            } 
        
        }elseif (! (strpos('0123456789', $n) === false)) { 
            if ($punt) { 
                if ($n != '0') $zeros = false; 
                    $fra .= $n; 
            }else 
        
            $ent .= $n; 
        }else 			
            break; 		
    } 
    $ent = '     ' . $ent; 
    if ($dec and $fra and ! $zeros) { 
        $fin = ' punto'; 
        for ($n = 0; $n < strlen($fra); $n++) { 
            if (($s = $fra[$n]) == '0') 
                $fin .= ' cero'; 
            elseif ($s == '1') 
                $fin .= $fem ? ' una' : ' un'; 
            else 
                $fin .= ' ' . $matuni[$s]; 
        } 
    }else 
        $fin = ''; 
    if ((int)$ent === 0) return 'Cero ' . $fin; 
    $tex = ''; 
    $sub = 0; 
    $mils = 0; 
    $neutro = false; 
    while ( ($num = substr($ent, -3)) != '   ') { 
        $ent = substr($ent, 0, -3); 
        if (++$sub < 3 and $fem) { 
                $matuni[1] = 'una'; 
                $subcent = 'as'; 
        }else{ 
            $matuni[1] = $neutro ? 'un' : 'uno'; 
            $subcent = 'os'; 
        } 
        $t = ''; 
        $n2 = substr($num, 1); 
        if ($n2 == '00') { 
        }elseif ($n2 < 21) 
            $t = ' ' . $matuni[(int)$n2]; 
        elseif ($n2 < 30) { 
            $n3 = $num[2]; 
            if ($n3 != 0) $t = 'i' . $matuni[$n3]; 
                $n2 = $num[1]; 
            $t = ' ' . $matdec[$n2] . $t; 
        }else{ 
            $n3 = $num[2]; 
            if ($n3 != 0) $t = ' y ' . $matuni[$n3]; 
            $n2 = $num[1]; 
            $t = ' ' . $matdec[$n2] . $t; 
        } 
        $n = $num[0]; 
        if ($n == 1) { 
            if($num == 100){
                                $t = ' cien' . $t;
                            }else{
                                $t = ' ciento' . $t; 
                            } 
        }elseif ($n == 5){ 
            $t = ' ' . $matunisub[$n] . 'ient' . $subcent . $t; 
        }elseif ($n != 0){ 
            $t = ' ' . $matunisub[$n] . 'cient' . $subcent . $t; 
        } 
        if ($sub == 1) { 
        }elseif (! isset($matsub[$sub])) { 
    
            if ($num == 1) { 
                $t = ' mil'; 
            }elseif ($num > 1){ 
                $t .= ' mil'; 
            } 
        }elseif ($num == 1) { 
            $t .= ' ' . $matsub[$sub] . 'on'; 
        }elseif ($num > 1){ 
            $t .= ' ' . $matsub[$sub] . 'ones'; 
        }   
        if ($num == '000') $mils ++; 
        elseif ($mils != 0) { 
            if (isset($matmil[$sub])) $t .= ' ' . $matmil[$sub]; 
            $mils = 0; 
        } 
        $neutro = true; 
        $tex = $t . $tex; 
    } 
    $tex = $neg . substr($tex, 1) . $fin; 
    return ucfirst($tex); 
}

}




##========================================================================================
##                                                                                      ##
##               su mario de jose estuvo aqui 2 veces  -> 14 Noviembre -> 10:08         ##
##                                                                                      ##
##========================================================================================