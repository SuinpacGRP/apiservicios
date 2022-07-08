<?php

namespace App\Http\Controllers\PortalPago;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;



use DateTime;
use App\Cliente;
use App\Funciones;
use App\FuncionesCaja;
use App\Modelos\PadronAguaLectura;
use App\Modelos\PadronAguaPotable;
use App\ModelosNotarios\Observaciones;
use App\Libs\Wkhtmltopdf;
use Illuminate\Support\Facades\DB;
use \Illuminate\Support\Facades\Config;

use Illuminate\Support\Facades\Storage;
class LicenciaFuncionamientoController extends Controller
{

    /* ! Se asigna el middleware  al costructor
    */
    public function __construct()
    {
        $this->middleware( 'jwt', ['except' => ['getToken']] );
    }


    public function validarExisteLicenciaFuncionamiento(Request $request)
    {
        $cliente = $request->Cliente;
        $folio = $request->Folio;
        Funciones::selecionarBase($cliente);
        
        $licencia=DB::select("select *,P.id as idPadr_onLicencia from Padr_onLicencia P join Contribuyente  C  on P.Contribuyente=C.id where P.Folio=".$folio." and P.Cliente=".$cliente);
       
        if(count($licencia)>0){

        $idPadron=$licencia[0]->id;
        $esExpedicion = false;
        $esRefrendo = false;
       $tieneLecturas = Funciones::ObtenValor("SELECT COUNT(id) AS total FROM Padr_onLicenciaHistorial WHERE Padr_onLicencia = ".$idPadron, "total");
        $FolioAnterior = Funciones::ObtenValor("SELECT COALESCE(p.FolioAnterior, '-1') as FolioAnterior FROM Padr_onLicencia p WHERE p.id = ".$idPadron, 'FolioAnterior' );
        
        $a_no = Funciones::ObtenValor("SELECT (SELECT A_no FROM Padr_onLicenciaHistorial WHERE Padr_onLicencia = pl.id ORDER BY A_no ASC LIMIT 1) AS A_no FROM Padr_onLicencia pl WHERE pl.id =". $idPadron, "A_no");
        
        if( $tieneLecturas == 1 ){
            if( $FolioAnterior == "-1" ){
                $esExpedicion = true;
                $esRefrendo = false;
            }else{
                $esExpedicion = false;
                $esRefrendo = true;
            }
        }elseif( $tieneLecturas > 1 ){
            $esRefrendo = true;
        }elseif( $FolioAnterior == "-1" ){
            $esExpedicion = true;
        }else{
            $esRefrendo = true;
        }

        if($esRefrendo){
            return response()->json([
                'success' => '1',
                'licencia'=>$licencia
            ], 200);
        }
        else{
            //no es refrendo
            return response()->json([
                'success' => '0',
                
            ], 200);
        }
    }else{
        return response()->json([
            'success' => '0',
        ], 200);
    }

    }




public static function generaEstadoDeCuentaOficial(Request $request){

    $idPadron=$request->IdPadron;
    $idLectura=$request->IdLectura;
    $cliente=$request->Cliente;

    Funciones::selecionarBase($cliente);
        
    $DatosPadron = Funciones::ObtenValor("SELECT *,
            pc.id,
            pc.Folio as Cuenta, 
            pc.Domicilio AS DomicilioLicencia, 
            pc.FolioAnterior as CuentaAnterior,
            (SELECT Nombre FROM EntidadFederativa WHERE id=d.EntidadFederativa) as Estado,
            (SELECT c.Nombre FROM Municipio c where c.id = pc.Municipio) AS MunicipioLicencia,
            (SELECT c.Nombre FROM Localidad c where c.id = pc.Localidad) AS LocalidadLicencia,
            CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno, c.NombreComercial ) as Propietario,
            (SELECT (SELECT Descripci_on FROM Giro WHERE id = GiroDetalle.idGiro) FROM GiroDetalle WHERE GiroDetalle.Cliente=pc.Cliente AND GiroDetalle.id=pc.GiroDetalle) AS Giro
        FROM Padr_onLicencia pc
            INNER JOIN Contribuyente c ON (c.id = pc.Contribuyente)
            INNER JOIN DatosFiscales d ON (d.id = c.DatosFiscales)
        WHERE
            pc.id=".$idPadron);
    
    $GirosExtras = Funciones::ObtenValor("SELECT COUNT(pld.id) AS total FROM Padr_onLicenciaDetalle pld WHERE idPadron = ".$idPadron, 'total');
    
    $DatosCliente = Funciones::ObtenValor("SELECT *, m.Nombre as Municipio, e.Nombre as Estado, cr.Ruta  as Logotipo
        FROM Cliente c 
        INNER JOIN DatosFiscalesCliente d ON (d.id=c.DatosFiscales) 
        INNER JOIN EntidadFederativa e ON (e.id=d.EntidadFederativa)
        INNER JOIN Municipio m ON (m.id=d.Municipio)
        INNER JOIN CelaRepositorioC cr ON (cr.idRepositorio=c.Logotipo)
        WHERE c.id=". $cliente);

    $tablaDatos = LicenciaFuncionamientoController::obtieneDatosLecturaLicencia($idPadron, $idLectura, $cliente);

      
    $htmlGlobal = '
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml">
        <link href="'.asset(Storage::url(env('RESOURCES').'bootstrap.min.css')).'" rel="stylesheet">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <title>ServicioEnLinea.mx</title>
        </head>
        <body>
            <table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="787px">
                <tr>
                    <td colspan="2" width="33.5%">
                        <img height="200px" src="'.asset($DatosCliente->Logotipo).'">
                    </td>
                    
                    <td colspan="4"  width="66.5%" align="right">
                        '.$DatosCliente->NombreORaz_onSocial.'<br />
                        Domicilio Fiscal: '.$DatosCliente->Calle.' '.$DatosCliente->N_umeroExterior.'<br />
                        '.$DatosCliente->Colonia.', C.P. '.$DatosCliente->C_odigoPostal.'<br />
                        '.$DatosCliente->Municipio.', '.$DatosCliente->Estado.'<br />
                        RFC: '.$DatosCliente->RFC.'	
                        <br/><br/>
                        <span style="font-size: 20px;>Estado de Cuenta</span> <br />
                        <span  style="font-size: 12px;"><b>Estado de Cuenta</b>: <span  style="color:#ff0000; font-size: 20px;">'.$idLectura.'</span></span>			
                    </td>
                </tr>
                <tr>
                    <td colspan="6" align="right">
                        <img width="787px"  height="1px" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'"> 
                    </td>
                </tr>
            </table>
            <table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="787px">
		        <tr>
                    <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
                        <br/><b>Datos de la Licencia</b><br />
                        <br/><b>Propietario: </b> ' .$DatosPadron->Propietario.'<br />'.
                        '<b>Ubicaci&oacute;n: </b> '.$DatosPadron->DomicilioLicencia.'<br/>
                        <b>Localidad: </b> '        .$DatosPadron->LocalidadLicencia.'<br/>
                        <b>Municipio: </b> '        .$DatosPadron->MunicipioLicencia.'<br/>
                        <b>Folio: </b> '            .$DatosPadron->Cuenta .' <br />
                        <b>Folio Anterior: </b> '   .$DatosPadron->CuentaAnterior .'<br/>
                        <b>Giro: </b> '             .$DatosPadron->Giro .'<br/>
                        '.($GirosExtras>0?'<b>Giros Anexos: </b> '.$GirosExtras.'<br/>':'').'
                    </td>
                    
                    <td colspan="3"  width="50%" style="vertical-align:top;" v-align="top">
                        <br/><b>Datos de Facturaci&oacute;n</b><br />
                        <br/><b>Razon Social:</b> '.$DatosPadron->NombreORaz_onSocial.'<br />
                        <b>RFC:</b> '.$DatosPadron->RFC.'<br />
                        <b>Direcci&oacute;n:</b> '.$DatosPadron->Calle.' '.$DatosPadron->N_umeroExterior.'
                        '.$DatosPadron->Colonia.', C.P. '.$DatosPadron->C_odigoPostal.'<br />
                        '.$DatosPadron->Municipio.', '.$DatosPadron->Estado.'.<br />
                    </td>
		        </tr>
                <tr>
                    <td colspan="6">
                        <br/><img width="787px" height="1px" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'"><br /> &nbsp;
                    </td>
		        </tr>	
            </table>
	
            <table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="787px">
                <tr>
                    <td colspan="6"><br />
                        <table class="table table-sm" style="padding:-35px 0 0 0;margin:-10px 0 0 0; font-size:12px;" border="0" width="787px">
                            <tr>
                                <td width="40%" align="center"><b>Concepto</b></td>
                                <td width="10%" align="center"><b>A&ntilde;o</b></td>
                                <td width="12%" align="center"><b>Valor</b></td>
                                <td width="13%" align="center"><b>Pro - Bomberos</b></td>
                                <td width="13%" align="center"><b>Contribución Estatal</b></td>
                                <td width="12%" align="center"><b>Total</b></td>
                            </tr>
                            '.$tablaDatos.'
                        </table>
                    </td>
                </tr>
            </table>
        </body>
    </html>';
    #precode($htmlGlobal,1,1);

    include( app_path() . '/Libs/Wkhtmltopdf.php' );
try {
    $nombre = uniqid().$idPadron;
    #$wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'page_width'=>120,'page_height'=>200,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
    $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'FooterStyleLeft'=>'Grupo Piacza - SUINPAC Contable', 'FooterStyleCenter'=>'Pag. [page] de [toPage]','margins' => ['top' => 10, 'left' => 10,'right'=>10,'bottom'=>10]));
    $wkhtmltopdf->setHtml($htmlGlobal);
    //$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, $nombre.".pdf");		
    $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $nombre . ".pdf");
    //return "repositorio/temporal/" . $nombre . ".pdf";
    return response()->json([
        'success' => 1,
        'ruta' => "repositorio/temporal/" . $nombre . ".pdf"
    ], 200);
    
} catch (Exception $e) {
    echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
}
    
}



public static function obtieneDatosLecturaLicencia($Padron, $Lectura, $cliente){
    
    $resultados = "";
    
    $a_no             = Funciones::ObtenValor("SELECT a_no FROM Padr_onLicenciaHistorial WHERE id = " . $Lectura, "a_no");
    $tieneLecturas    = Funciones::ObtenValor("SELECT COUNT(id) AS total FROM Padr_onLicenciaHistorial WHERE Padr_onLicencia = ".$Padron, "total");
    $tieneParaCotizar = Funciones::ObtenValor("SELECT COUNT(id) AS total FROM Padr_onLicenciaHistorial WHERE Padr_onLicencia = ".$Padron." AND Status IN (0,1)", "total");
    $FolioAnterior    = Funciones::ObtenValor("SELECT COALESCE(p.FolioAnterior, '-1') as FolioAnterior FROM Padr_onLicencia p WHERE p.id = ".$Padron, 'FolioAnterior' );
    
    $esExpedicion = false;
    $esRefrendo = false;
    
    if( $tieneLecturas != 0 ){
        if( $tieneLecturas == 1 ){
            if( $tieneParaCotizar != 0 && $tieneParaCotizar == 1){

                if( $FolioAnterior == "-1" ){
                    $esExpedicion = true;
                    $esRefrendo = false;
                }else{
                    $esExpedicion = false;
                    $esRefrendo = true;
                }
            }
        }else{
            if( $tieneParaCotizar != 0 && $tieneParaCotizar >= 1){
                $esRefrendo = true;
            }
        }
    }
    
    $concepto = '';
    $sumaAnios = 0;
    $sumaPrecio = 0;
    $sumaBomberos = 0;
    $sumaContribucion = 0;
    $sumaTotal = 0;
    
    if($tieneLecturas != 0){
        if( $esExpedicion ){
            //el concepto es expedicion
            $concepto = LicenciaFuncionamientoController::getConceptoLicencia( 'Expedicion',  $cliente );
        }elseif( $esRefrendo ){
            //el concepto es refrendo
            $concepto = LicenciaFuncionamientoController::getConceptoLicencia( 'Refrendo',  $cliente );
        }
        
        $Consulta = "SELECT DISTINCT
                plh.A_no,
                ccc.CRI AS CRI,
                ca.Importe AS Importe,
                pl.id AS idPadronlicencia,
                ca.BaseCalculo AS BaseCalculo,
                ccc.id AS idConceptoCobroCaja,
                ccc.Descripci_on AS Descripcion,
                pl.Contribuyente AS idContribuyente,
                pl.ConceptoCobroCaja AS idConceptoAdicional
            FROM
                Padr_onLicencia pl
                INNER JOIN Padr_onLicenciaHistorial plh ON ( pl.id = plh.Padr_onLicencia )
                INNER JOIN ConceptoCobroCaja ccc ON ( ccc.id = ".$concepto." )
                INNER JOIN ConceptoRetencionesAdicionales cra ON ( cra.Concepto = ccc.id )
                INNER JOIN ConceptoRetencionesAdicionalesCliente crac ON ( cra.id = crac.ConceptoRetencionesAdicionales)
                INNER JOIN ConceptoAdicionales ca ON (ca.ConceptoRetencionesAdicionalesCliente = crac.id)
            WHERE 
                ca.Status = 1
                AND pl.id = ".$Padron."
                AND plh.A_no <= ".$a_no."
                AND ca.Cliente = ".$cliente. "
                AND crac.Cliente = ".$cliente. 
                " ORDER BY plh.A_no DESC";
        
        $Result =  DB::select($Consulta);

        foreach(  $Result as $Record){
            $existe = Funciones::ObtenValor("SELECT id FROM Padr_onLicenciaHistorial WHERE Padr_onLicencia = $Padron AND Status IN (0,1) AND A_no =" . $Record->A_no, "id");
            
            if($existe != 'NULL'){
                
                $datosLicencia = Funciones::ObtenValor("SELECT  gi.Descripci_on as  GiroDescripcion,
                        p.id, p.GiroDetalle, g.ImporteExpedicion, g.ImporteRefrendo,
                        (SELECT count(ph.id) FROM Padr_onLicenciaHistorial ph WHERE ph.Padr_onLicencia = p.id ) as Conteo, 
                        COALESCE(p.FolioAnterior, '-1') as  FolioAnterior
                    FROM Padr_onLicencia p 
                        INNER JOIN GiroDetalle g ON (g.id = p.GiroDetalle)
                        INNER JOIN Giro gi ON (gi.id = g.idGiro)
                    WHERE p.id=".$Padron );

                $datosLicenciaDetalle = Funciones::ObtenValor( "SELECT GROUP_CONCAT(gi.Descripci_on SEPARATOR '<br/>') as girosDetalle,
                        sum(g.ImporteExpedicion) as ImporteExpedicion, sum(g.ImporteRefrendo) ImporteRefrendo
                    FROM Padr_onLicenciaDetalle p 
                        INNER JOIN GiroDetalle g ON ( g.id = p.idLicencia )
                        INNER JOIN Giro gi ON ( gi.id = g.idGiro )
                    WHERE p.idPadron=".$Padron );

                $sumaExpedicion = $datosLicencia->ImporteExpedicion+ $datosLicenciaDetalle->ImporteExpedicion;
                $sumaRefrendo  = $datosLicencia->ImporteRefrendo + $datosLicenciaDetalle->ImporteRefrendo;
                
                if( $esExpedicion ){
                    $sumaAnios   += $sumaExpedicion;
                    $bomberos     = 0;
                    $contribucion = 0;
                    
                    $conceptos = LicenciaFuncionamientoController::ObtieneImporteyConceptos2($Record->idConceptoCobroCaja, $sumaExpedicion,$cliente);
                    #precode($conceptos,1,1);
                    if($conceptos->NumAdicionales != 0){
                        for($i = 1; $i <= $conceptos['NumAdicionales']; $i++){
                            if($conceptos['adicionales'.$i]['idAdicional'] == 7){
                                $contribucion = floatval( str_replace(",","", $conceptos['adicionales'.$i]['Resultado'] ) );
                                #$contribucion = $conceptos['adicionales'.$i]['Resultado'];
                            }
                            if($conceptos['adicionales'.$i]['idAdicional'] == 6){
                                $bomberos = floatval( str_replace(",","", $conceptos['adicionales'.$i]['Resultado'] ) );
                                #$bomberos = $conceptos['adicionales'.$i]['Resultado'];
                            }
                        }
                    }
                    
                    $sumaBomberos += $bomberos;
                    $sumaContribucion += $contribucion;
                    $sumaAnio = $sumaExpedicion + $bomberos + $contribucion;
                    $sumaTotal += $sumaAnio;
                    
                    $resultados .= "<tr>
                                <td align='left'>"  .$Record->Descripcion."</td>
                                <td align='center'>".$Record->A_no."</td>
                                <td align='right'>" .number_format($sumaExpedicion,2)."</td>
                                <td align='right'>".number_format($bomberos,2)."</td>
                                <td align='right'>".number_format($contribucion,2)."</td>
                                <td align='right'>" .number_format($sumaAnio,2)."</td>
                            </tr>";
                    
                }elseif( $esRefrendo ){
                    $sumaAnios   += $sumaRefrendo;
                    $bomberos     = 0;
                    $contribucion = 0;
                   
                    #$conceptos = ObtenerImporteYConceptos($Record['idConceptoCobroCaja'], 1, $sumaRefrendo);
                    $conceptos = LicenciaFuncionamientoController::ObtieneImporteyConceptos2($Record->idConceptoCobroCaja, $sumaRefrendo,$cliente);
                    
                    if($conceptos['NumAdicionales'] != 0){
                        for($i = 1; $i <= $conceptos['NumAdicionales']; $i++){
                            if($conceptos['adicionales'.$i]['idAdicional'] == 7){
                                $contribucion = floatval( str_replace(",","", $conceptos['adicionales'.$i]['Resultado'] ) );
                            }
                            if($conceptos['adicionales'.$i]['idAdicional'] == 6){
                                $bomberos = floatval( str_replace(",","", $conceptos['adicionales'.$i]['Resultado'] ) );
                            }
                        }
                    }
                    
                    $sumaBomberos += $bomberos;
                    $sumaContribucion += $contribucion;
                    $sumaAnio = $sumaRefrendo + $bomberos + $contribucion;
                    $sumaTotal += $sumaAnio;
                    
                    $resultados .= "<tr>
                                <td align='left'>"  .$Record->Descripcion."</td>
                                <td align='center'>".$Record->A_no."</td>
                                <td align='right'>" .number_format($sumaRefrendo,2)."</td>
                                <td align='right'>".number_format($bomberos,2)."</td>
                                <td align='right'>".number_format($contribucion,2)."</td>
                                <td align='right'>" .number_format($sumaAnio,2)."</td>
                            </tr>";
                }
            }
        }
    }


    
    
    $resultados.="<tr>
                    <td align='right' colspan='2'><b>Totales</b></td>
                    <td align='right'><b>".number_format($sumaAnios,2)."</b></td>
                    <td align='right'><b>".number_format($sumaBomberos,2)."</b></td>
                    <td align='right'><b>".number_format($sumaContribucion,2)."</b></td>
                    <td align='right'><b>".number_format($sumaTotal,2)."</b></td>
                </tr>
                <tr><td colspan='11'>&nbsp;</td></tr>";
							
    $resultados.="
            </table>
            <table style='padding:-35px 0 0 0;margin:-10px 0 0 0;' border='0' width='787px'>
                <tr>
                    <td colspan='11'><img width='787px' height='1px' src='".asset(Storage::url(env('IMAGES') . 'barraColores.png'))."'> <br /> </td>
                </tr>
                <tr>
                    <td align='right' colspan='9'><br /><span style='font-size: 20px; font-weight: bold;'>Total a Pagar</span><br /><br /></td>
                    <td align='right'  colspan='2'><br /><b><span style='font-size: 20px; font-weight: bold;'>$ ".number_format($sumaTotal,2)."</span></b><br /><br /></td>
                </tr>

                <tr>
                    <td colspan='11'><br />
                        <img width='787px' height='1px' src='".asset(Storage::url(env('IMAGES') . 'barraColores.png'))."'>
                    </td>
                </tr>
                <tr>
                    <td colspan='11' align='right'>
                        <span style='font-size:12px;'>Expedici&oacute;n: ".date('d/m/Y H:i:s')."</span> <span style='font-size:12px;'>S. en Línea</span>
                    </td>
                </tr>
                <tr>
                    <td colspan='11' align='right'>
                        <span style='font-size:12px;'>Documento Informativo expedido a petici&oacute;n del Contribuyente, no es un requerimiento de pago.</span>
                    </td>
                </tr>
             ";

            
    return $resultados;
}



public static function getConceptoLicencia($tipo, $cliente){
    $concepto = 0;

    switch ($tipo) {
        case 'Expedicion':
            switch ($cliente) {
                case 35:
                    $concepto = 5869;
                    break;
                case 27:
                    $concepto = 5999;
                    break;
                default:
                    $concepto = 2420;
                    break;
            }
            break;
        case 'Refrendo':
            switch ($cliente) {
                case 35:
                    $concepto = 5868;
                    break;
                case 27:
                    $concepto = 6000;
                    break;
                default:
                    $concepto = 2400;
                    break;
            }
            break;
    }

    return $concepto;
}



public static function ObtieneImporteyConceptos2($idConcepto, $montobase,$cliente){
    $anio=date('Y');
    $datosConcepto=Funciones::ObtenValor("SELECT c3.`Importe` as import, c3.`BaseCalculo` as TipobaseCalculo
    FROM `ConceptoCobroCaja` c 
    INNER JOIN `ConceptoRetencionesAdicionales` c1 ON ( c.id = c1.`Concepto`  )  
        INNER JOIN `ConceptoRetencionesAdicionalesCliente` c2 ON ( c1.id = c2.`ConceptoRetencionesAdicionales`  )  
            INNER JOIN `ConceptoAdicionales` c3 ON ( c2.id = c3.`ConceptoRetencionesAdicionalesCliente`  )  
    WHERE c3.Cliente=".$cliente." AND c3.EjercicioFiscal=".$anio." AND c2.Cliente=".$cliente." AND c.id = ".$idConcepto );
    
    $Datos['baseCalculo']=$datosConcepto->TipobaseCalculo;
    
    if($datosConcepto->TipobaseCalculo==2){
        $eldato=str_replace(",","",$datosConcepto->import);
        $elotrodato=str_replace(",","",$montobase);
        $Datos['importe']=$eldato*$elotrodato/100;
        $Datos['punitario']=floatval(str_replace(",","",$datosConcepto->import));
    }else{
        $Datos['importe']=$montobase*floatval(str_replace(",","",$datosConcepto->import));
        $Datos['punitario']=floatval(str_replace(",","",$datosConcepto->import));
    }
   
    $ConsultaSelect="SELECT * FROM RetencionesAdicionales
    WHERE id
    IN (SELECT RetencionAdicional
    FROM `ConceptoCobroCaja` c 
    INNER JOIN `ConceptoRetencionesAdicionales` c1 ON ( c.id = c1.`Concepto`  )  
        INNER JOIN `ConceptoRetencionesAdicionalesCliente` c2 ON ( c1.id = c2.`ConceptoRetencionesAdicionales`  )  
            INNER JOIN `ConceptoAdicionales` c3 ON ( c2.id = c3.`ConceptoRetencionesAdicionalesCliente`  )  
    WHERE c.id = ".$idConcepto.")";
    $ResultadoInserta = DB::select($ConsultaSelect);
    $i=0;
    
    foreach($ResultadoInserta as $filas){	
        if(isset($filas->id)){
        $filasV2= Funciones::Configuraci_onAdicionales($filas,$cliente); 
      
        $filas->id = $filasV2->id;
        
        $filas->Descripci_on =  $filasV2->Descripci_on;
        $filas->PlanDeCuentas =  $filasV2->PlanDeCuentas;
        $filas->Proveedor =  $filasV2->Proveedor;
        $filas->Porcentaje =  $filasV2->Porcentaje;
        $filas->ConceptoCobro =  $filasV2->ConceptoCobro;
       
        } 
        $i++;
        if($Datos['baseCalculo']==1){ //importe
            $idConceptoOperacion=number_format((floatval(str_replace(",","",$Datos['importe']))*floatval($filas->Porcentaje / 100 )  ),2);
            
        }
        if($Datos['baseCalculo']==2){ //porcentaje
            
            $idConceptoOperacion=number_format((floatval(str_replace(",","",$Datos['importe']))*floatval($filas->Porcentaje) / 100 ),2);
        }
        if($Datos['baseCalculo']==3){ // SDG
            $zona=Funciones::ObtenValor("SELECT ZonaEconomica FROM Cliente WHERE id=".$cliente, "ZonaEconomica");
            $sdg=Funciones::ObtenValor("SELECT ".$zona." FROM SalariosM_inimos WHERE EjercicioFiscal=".$anio, $zona);
            
            $Datos['importe']=$datosConcepto->import*$sdg;
            $Datos['punitario']=$sdg;

            $calculoimporte= floatval(str_replace(",","",$sdg)) * floatval(str_replace(",","",$datosConcepto->import));
            $idConceptoOperacion=number_format((floatval($calculoimporte)*floatval($filas->Porcentaje) / 100 ),2);
        }		
        $Datos['adicionales'.$i]['TipoBase']=$Datos['baseCalculo'];			
        $Datos['adicionales'.$i]['MontoBase']=$Datos['punitario'];			
        $Datos['adicionales'.$i]['idAdicional']=$filas->id;
        $Datos['adicionales'.$i]['Descripcion']=$filas->Descripci_on;
        $Datos['adicionales'.$i]['Resultado']=$idConceptoOperacion;
        
                            
            
    }
    $Datos['NumAdicionales']=$i;
    
    //echo "<pre>";
    //print_r($Datos);
    //echo "</pre>";
    //obtenemos los datos de los adicionales
    //select Descripci_on from RetencionesAdicionales where id in (select RetencionAdicional from ConceptoRetencionesAdicionales where Concepto=10 and id in (select ConceptoRetencionesAdicionales from ConceptoRetencionesAdicionalesCliente where id in (select ConceptoRetencionesAdicionalesCliente from ConceptoAdicionales where Cliente=1 and AreaRecaudadora=2)))
    
    return ($Datos);	
}



public function obtenerGirosLicenciFuncionamiento(Request $request)
{
    $cliente = $request->Cliente;
    $idPadron = $request->IdPadron;

    Funciones::selecionarBase($cliente);
    
    $giros = array();

    $queryPrincipal = "SELECT
		gd.id AS GiroDetalle,
		g.Descripci_on AS Giro,
		( SELECT COUNT(*) FROM Padr_onLicenciaDetalle WHERE idPadron = pl.id ) AS GirosAnexos,
        gd.ImporteRefrendo as Importe,
        gd.LicenciasAdicionales
    FROM Padr_onLicencia pl
        INNER JOIN GiroDetalleEjercicioFiscal gdef ON ( gdef.GiroDetalle = pl.GiroDetalle )
        INNER JOIN GiroDetalle gd ON ( gd.id = gdef.GiroDetalle )
        INNER JOIN Giro g ON ( g.id = gd.idGiro )
    WHERE
        pl.id = ".$idPadron."
        AND pl.Cliente = ".$cliente."
        AND gdef.EjercicioFiscal = ".date('Y')."
        AND gdef.Estatus = 1
        AND gd.Cliente = ".$cliente;
        
    $giroPrincipal = DB::select($queryPrincipal);
    
    if ( $giroPrincipal[0]->GirosAnexos != 0 ){
        $objetoGiro = array("Id"=>$giroPrincipal[0]->GiroDetalle, "Descripcion"=> $giroPrincipal[0]->Giro,"Importe"=>$giroPrincipal[0]->Importe,"LicenciasAdicionales"=>$giroPrincipal[0]->LicenciasAdicionales);
        //$json = json_encode($objetoGiro);

        $giros[] =  $objetoGiro;

        $queryGiros = "SELECT
                gd.id AS GiroDetalle,
                gd.idGiro,
                g.Descripci_on AS Giro,
                gd.ImporteRefrendo as Importe,
                gd.LicenciasAdicionales
            FROM Padr_onLicencia pl
                INNER JOIN Padr_onLicenciaDetalle pld ON ( pld.idPadron = pl.id ) 
                INNER JOIN GiroDetalleEjercicioFiscal gdef ON ( gdef.GiroDetalle = pld.idLicencia )
                INNER JOIN GiroDetalle gd ON ( gd.id = gdef.GiroDetalle )
                INNER JOIN Giro g ON ( g.id = gd.idGiro )
            WHERE
                pl.id = ".$idPadron."
                AND pl.Cliente = ".$cliente."
                AND gdef.EjercicioFiscal = ".date('Y')."
                AND gdef.Estatus = 1
                AND gd.Cliente = ".$cliente;

        $ejecutaSQL = DB::select( $queryGiros );

        foreach( $ejecutaSQL as $registro ) {

            $objetoGiro = array("Id"=>$registro->GiroDetalle, "Descripcion"=> $registro->Giro,"Importe"=>$registro->Importe,"LicenciasAdicionales"=>$registro->LicenciasAdicionales);
           // $json = json_encode($objetoGiro);

            $giros[] =  $objetoGiro;
        }
    }else{
        $objetoGiro = array("Id"=>$giroPrincipal[0]->GiroDetalle, "Descripcion"=> $giroPrincipal[0]->Giro,"Importe"=>$giroPrincipal[0]->Importe,"LicenciasAdicionales"=>$giroPrincipal[0]->LicenciasAdicionales);
        //$json = json_encode($objetoGiro);

        $giros[] =  $objetoGiro;
    }
        return response()->json([
        'success' => '1',
        'giros'=>$giros
    ], 200);


}


public function generarSolicitudEcologiaPutamadre(Request $request){
    $cliente = $request->Cliente;
    

    return response()->json([
        'success' => '1',
        'giros'=>$cliente
    ], 200);
    
}



}
