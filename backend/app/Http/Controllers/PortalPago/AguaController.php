<?php

namespace App\Http\Controllers\PortalPago;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libs\Wkhtmltopdf;
use App\Funciones;
use App\FuncionesCaja;
use Illuminate\Support\Facades\DB;
use \Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use DateTime;

class AguaController extends Controller
{
     /**
     * ! Se asigna el middleware  al costructor
     */
    public function __construct()
    {
        $this->middleware( 'jwt', ['except' => ['getToken']] );
    }

    function conceptoAbreviado($concepto){
        $conceptoAbreviado = '';

        switch($concepto){
            case 'Alcantarillado Sanitario (Derechos)':
                #$conceptoAbreviado = 'Alcantarillado Sanitario';
                $conceptoAbreviado = 'Alc. Sanitario';
                break;
            case 'Saneamiento (Derechos)':
                $conceptoAbreviado = 'Saneamiento';
                break;
            case 'Pro - Redes (Derechos) 15%':
                $conceptoAbreviado = 'Pro - Redes';
                break;
            case 'Impuesto al Valor Agregado (IVA)':
                $conceptoAbreviado = 'IVA';
                break;
            case 'Alcantarillado planta tratadora con desalinizadora. (Organismo Público)':
                $conceptoAbreviado = 'Alcant. PT. Desalizadora';
                break;
            case 'Saneamiento planta tratadora con desalinizadora. (Organismo Público)':
                $conceptoAbreviado = 'Saneam. PT. Desalizadora';
                break;
            default:
                $conceptoAbreviado = $concepto;
                break;
        }

        return $conceptoAbreviado;
    }

    public function validarExisteCuentaAgua(Request $request){
        $contrato = intval($request->Contrato);
        $cliente = intval($request->Cliente);
        error_log("Fecha: ". date("Y-m-d H:i:s") . " Se accede a la funcion de validarExisteCuentaAgua 'Contrato' => $contrato, 'cliente' => $cliente \t" , 3, "/var/log/suinpac/LogCajero.log");
        Funciones::selecionarBase($cliente);
        $DatosContrato=Funciones::ObtenValor("SELECT pa.id, pa.Estatus, (SELECT Descripci_on FROM EstatusAgua WHERE id = pa.Estatus) AS EstatusTXT, 
            (SELECT Concepto FROM TipoTomaAguaPotable WHERE id = pa.TipoToma) AS TipoToma, c.id as idContribuyente,
            CONCAT_WS(' ', pa.Domicilio, pa.Colonia, pa.SuperManzana, pa.Manzana, pa.Lote) AS Domicilio,
            IF(c.PersonalidadJur_idica = 1, CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial) AS Nombre 
        FROM Padr_onAguaPotable pa 
        INNER JOIN Contribuyente c ON (pa.Contribuyente = c.id) 
        WHERE pa.ContratoVigente=".$contrato." and pa.Cliente=".$cliente);
        
     if(intval($DatosContrato->Estatus)!=2 && $DatosContrato->Estatus!=1){
        error_log("Fecha: ". date("Y-m-d H:i:s") . " Termina la funcion de validarExisteCuentaAgua 'success' => '2', 'ID Contrato'=>".$DatosContrato->id." \n" , 3, "/var/log/suinpac/LogCajero.log");
        return response()->json([
            'success' => '2',
            'contrato'=>$DatosContrato
        ], 200);
     }else{
        error_log("Fecha: ". date("Y-m-d H:i:s") . " Termina la funcion de validarExisteCuentaAgua 'success' => '1', 'ID Contrato'=>".$DatosContrato->id." \n" , 3, "/var/log/suinpac/LogCajero.log");
        return response()->json([
            'success' => '1',
            'contrato'=>$DatosContrato
        ], 200);
     }

    }

    public function validarExisteCuentaAguaCopia(Request $request){
        $contrato = $request->Contrato;
        $cliente = $request->Cliente;

        #return $request;
        Funciones::selecionarBase($cliente);

        $contribuyente=Funciones::ObtenValor("SELECT C.id as Contribuyente FROM Padr_onAguaPotable PAP JOIN Contribuyente C on PAP.Contribuyente=C.id where PAP.ContratoVigente=".$contrato." and PAP.Cliente=".$cliente);
        if (!isset($contribuyente->Contribuyente)){ //sino se encuentra la cuenta retorna estatus 0 #2021-08-05
            return response()->json([
                'success' => '0',
                're' => $contribuyente
            ], 200);
        }
        $nombre=DB::select("select if(c.PersonalidadJur_idica=1,IF( CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno)
        IS NOT NULL AND CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno)!='',CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno),
        CONCAT(d.NombreORaz_onSocial)),CONCAT(d.NombreORaz_onSocial)) as nombre from  Contribuyente c  INNER JOIN DatosFiscales d ON(d.id=c.DatosFiscales) where c.id = ".$contribuyente->Contribuyente);

        $contrato=Funciones::ObtenValor("SELECT PAP.id,PAP.Estatus,CONCAT(C.Nombres,' ',C.ApellidoPaterno,' ',C.ApellidoMaterno) as Nombre, (select Concepto from TipoTomaAguaPotable T where T.id= PAP.TipoToma) as TipoToma, C.id as idContribuyente,PAP.Domicilio,
       C.Nombres as Nombres, C.ApellidoPaterno as AP, C.ApellidoMaterno as AM
         FROM Padr_onAguaPotable PAP
        join Contribuyente C on PAP.Contribuyente=C.id where PAP.ContratoVigente=".$contrato." and PAP.Cliente=".$cliente );

     if(intval($contrato->Estatus)!=2 && $contrato->Estatus!=1){

        switch($contrato->Estatus){

            case 2:
                $contrato->Estatus='Cortado';
                break;
            case 3:
                $contrato->Estatus='Baja Temporal';
                break;
            case 4:
                $contrato->Estatus='Baja Permanente';
                break;
            case 5:
                $contrato->Estatus='Inactivo';
                break;
            case 6:
                $contrato->Estatus='Nueva';
                break;
            case 9:
                $contrato->Estatus='Sin toma';
                break;
            case 10:
                $contrato->Estatus='Multada';
                break;

        }

        return response()->json([
            'success' => '2',
            'contrato'=>$contrato,
            'nombre' =>$nombre,
        ], 200);
     }else{
        return response()->json([
            'success' => '1',
            'contrato'=>$contrato,
            'nombre' => $nombre,
        ], 200);
     }

    }


    //pegue

    function GenerarReciboOficialSemapaIndividual($idPadron, $cliente, $tipo = 0){

        $html =  AguaController::GenerarReciboOficialSemapa($idPadron, $cliente, 1, false, false);

        $htmlGlobal ='<html>
            <head>
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <!--<link href="'.asset("bootstrap/css/bootstrap.min.css").'" rel="stylesheet">-->
            </head>

            <body>
            <style>
                td {
                    font-family: "Arial";
                    font-size: 7.5pt;
                }
                th {
                    font-family: "Arial", serif;
                    font-size: 8pt;
                }
                table {
                    position: relative;
                }
                .contenedor {
                    width: 100%;
                    height: 100%;
                }
                .items{
                    text-align: center;
                    vertical-align: top;
                    width: 8.4%;;
                }
                .items2{
                    text-align: center;
                    vertical-align: middle;
                    width: 8.4%;;
                }
                .centradoBtm{
                    text-align: center;
                    vertical-align: bottom;
                }
                .bordeR{
                    border: black 1px solid;
                    border-radius: 5px;
                }
                .bordeG{
                    border: black 1px solid;
                }
                .bordeE{
                    border-bottom: black 1px dotted;
                }
                .tablaTop {
                    position: relative;
                    top:-85px;
                    left:0px;
                    border:none;
                }
                .tablaC{
                    border-collapse: collapse;
                    border-radius: 5px;
                    border-style: hidden;
                    box-shadow: 0 0 0 1px #000;
                }
                .break{
                    clear: both;
                    display: block;
                    page-break-after: always;
                }
            </style>

            <div class="contenedor">
               <table WIDTH="100%" HEIGHT="100%"  border="0" style="border-collapse: collapse;">
                    <tbody>
                        <tr>
                        <td align="center"  valign="middle" class="bordeE" WIDTH="100%" HEIGHT="33.3%">
                        '.$html.'
                        </td>
                        </tr>
                        <tr>
                            <td align="center" valign="middle" WIDTH="100%" HEIGHT="33.3%">

                            </td>
                        </tr>
                        <tr>
                            <td align="center" valign="middle" WIDTH="100%" HEIGHT="33.3%">

                            </td>
                        </tr>
                    </tbody>
               </table>
            </div>
            </html>';

            include( app_path() . '/Libs/Wkhtmltopdf.php' );
            try {
                $nombre = uniqid() . "_" . $idPadron;
                #$wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'page_width'=>120,'page_height'=>200,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
                $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'orientation'=>'Portrait', 'margins' => array('top' => 3, 'left' => 5, 'right' => 5, 'bottom'=>2)));
                $wkhtmltopdf->setHtml($htmlGlobal);
                //$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, $nombre.".pdf");
                $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $nombre . ".pdf");
                //return "repositorio/temporal/" . $nombre . ".pdf";
                return response()->json([
                    'success' => '1',
                    'ruta' => "repositorio/temporal/" . $nombre . ".pdf",
                ]);
            } catch (Exception $e) {
                echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
            }
    }

    //pegue

    function GenerarReciboOficialSemapa($idPadron, $cliente, $tipo = 0, $retornarPagado = false, $sellos){

       // global $Conexion;

        $DatosCliente = Funciones::ObtenValor("SELECT *, m.Nombre as Municipio, e.Nombre as Estado, cr.Ruta as Logo
             FROM Cliente c
                 INNER JOIN DatosFiscales     d  ON (d.id=c.DatosFiscales)
                 INNER JOIN EntidadFederativa e  ON (e.id=d.EntidadFederativa)
                 INNER JOIN Municipio         m  ON (m.id=d.Municipio)
                 INNER JOIN CelaRepositorioC   cr ON (cr.idRepositorio=c.Logotipo)
             WHERE c.id=". $cliente);

         $idLectura =  Funciones::ObtenValor( "SELECT id FROM Padr_onDeAguaLectura WHERE Padr_onAgua = " . $idPadron . " AND `Status` = 1 ORDER BY A_no DESC, Mes DESC LiMIT 0, 1", "id");

         $estaPagado = FALSE;
         if ($idLectura == 'NULL' || $idLectura == '') {
             $estaPagado = TRUE;

             if( $retornarPagado )
                 return "";

             $idLectura = Funciones::ObtenValor("SELECT MAX(id) as id FROM Padr_onDeAguaLectura WHERE Padr_onAgua =" . $idPadron . " AND `Status` = 2", "id");
         }

         $corteSA = false;
         $corteSAD = false;
         $adeudos = "";
         $adeudo = Funciones::ObtenValor("SELECT COUNT(id) as adeudo FROM Padr_onDeAguaLectura WHERE Padr_onAgua = " . $idPadron . " AND `Status` = 1", "adeudo");
         if ( $adeudo != 'NULL' ) {
             $adeudo = intval($adeudo);
             $adeudos = "" . $adeudo;

             if($adeudo > 3 && $adeudo <= 10 && $sellos){
                 $corteSA = true;
             }
             if($adeudo > 10 && $sellos){
                 $corteSAD = true;
             }

         }

         $cuentasPapas = Funciones::ObtenValor("SELECT GROUP_CONCAT(p.id) as CuentasPapas FROM Padr_onAguaPotable p WHERE p.id=(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1) and p.Cliente=" . $cliente, 'CuentasPapas');
         $arrayPapas = explode(",", $cuentasPapas);

         $tam = count($arrayPapas);
         $esPapa = FALSE;


         //Recorro todos los papas
         for ($i = 0; $i < $tam; $i++) {
             if ($idPadron == $arrayPapas[$i]) {
                 $esPapa = TRUE;
                 break;
             }
         }

         $anomalia = Funciones::ObtenValor("SELECT pac.descripci_on FROM Padr_onDeAguaLectura pal INNER JOIN Padr_onAguaCatalogoAnomalia pac ON pal.Observaci_on = pac.id WHERE pal.id=" . $idLectura, "descripci_on");
         $tieneAnomalia = TRUE;

         if ($anomalia == 'NULL') {
             $anomalia = '';
             $tieneAnomalia = FALSE;
         }

         $DatosPadron = Funciones::ObtenValor("SELECT
             d.RFC,
             pa.Ruta,
             pa.Lote,
             pa.Cuenta,
             pa.Sector,
             pa.Manzana,
             pa.Consumo,
             pa.Colonia,
             pa.Medidor,
             pa.Diametro,
             pa.TipoToma,
             pa.Domicilio,
             pa.Giro as Giro,
             pa.M_etodoCobro,
             pa.SuperManzana,
             c.C_odigoPostal_c,
             c.Colonia_c,
             pa.ContratoVigente,
             d.NombreORaz_onSocial,
             COALESCE ( c.NombreComercial, NULL ) AS NombreComercialPadron,
             #( SELECT Descripci_on FROM Giro g WHERE g.id = pa.Giro ) AS Giro,
             (SELECT Nombre FROM Localidad WHERE id = pa.Localidad) AS Localidad,
             ( SELECT COALESCE ( Nombre, '' ) FROM Municipio m WHERE m.id = d.Municipio ) AS Municipio,
             ( SELECT COALESCE ( Nombre, '' ) FROM EntidadFederativa e WHERE e.id = d.EntidadFederativa ) AS Estado,
             COALESCE ( CONCAT( c.Nombres, ' ', c.ApellidoPaterno, ' ', c.ApellidoMaterno ), NULL ) AS ContribuyentePadron
             #CONVERT(BINARY CONVERT(COALESCE ( CONCAT( c.Nombres, ' ', c.ApellidoPaterno, ' ', c.ApellidoMaterno ), NULL ) USING latin1) USING utf8) AS ContribuyentePadron
         FROM Padr_onAguaPotable pa
             INNER JOIN Contribuyente c ON (c.id=pa.Contribuyente)
             INNER JOIN DatosFiscales d ON (d.id=c.DatosFiscales)
         WHERE pa.id= " . $idPadron);
         #COALESCE ( CONCAT( c.Nombres, ' ', c.ApellidoPaterno, ' ', c.ApellidoMaterno ), NULL ) AS ContribuyentePadron

         if ($DatosPadron->ContribuyentePadron == 'NULL' || empty($DatosPadron->ContribuyentePadron) || strlen($DatosPadron->ContribuyentePadron) <= 2)
             $contribuyente = ucwords( strtolower( utf8_decode($DatosPadron["NombreComercialPadron"]) ) );
         else
             $contribuyente = ucwords( strtolower( utf8_decode($DatosPadron->ContribuyentePadron) ) );
             #$contribuyente = utf8_decode( ucwords( strtolower( $DatosPadron["ContribuyentePadron"]) ) );

         if ( isset($DatosPadron->TipoToma) )
             $consultaToma = Funciones::ObtenValor("SELECT Concepto FROM TipoTomaAguaPotable  WHERE id = " . $DatosPadron->TipoToma, 'Concepto');
         else
             $consultaToma = 'NULL';

         if ($consultaToma == 'NULL')
             $tipoToma = '0';
         else
             $tipoToma = utf8_decode($consultaToma);

         $folio = Funciones::ObtenValor("SELECT Cuenta FROM Padr_onAguaPotable WHERE id = " . $idPadron, "Cuenta");

         if ($estaPagado) {
             $DatosParaRecibo = obtieneDatosLectura($idLectura);
             $mesActual = Funciones::ObtenValor("SELECT Mes FROM Padr_onDeAguaLectura WHERE id = " . $idLectura, 'Mes');
         } else
             $DatosParaRecibo = Funciones::ObtenValor("SELECT * FROM Padr_onDeAguaLectura WHERE id = " . $idLectura);

         if ($tieneAnomalia || $esPapa) {
             $lecturaAnterior = $DatosParaRecibo->LecturaAnterior;
             $lecturaActual   = $DatosParaRecibo->LecturaActual;
             $lecturaConsumo  = $DatosParaRecibo->Consumo;
         } else {
             $lecturaAnterior = intval($DatosParaRecibo->LecturaAnterior);
             $lecturaActual   = intval($DatosParaRecibo->LecturaActual);
             $lecturaConsumo  = $lecturaActual - $lecturaAnterior;
         }

         $consumo = "";
         if( isset( $DatosPadron->M_etodoCobro )  ){
             if ( $DatosPadron->M_etodoCobro == 1 )
                 $consumo = $DatosPadron->Consumo;

             if( $DatosPadron->M_etodoCobro == 2)
                 $consumo = $DatosParaRecibo->Consumo;
         }

         $meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

         $mesCobro = Funciones::ObtenValor("SELECT LPAD(pl.Mes, 2, 0 ) as MesEjercicio FROM Padr_onAguaPotable pa
         INNER JOIN Padr_onDeAguaLectura pl ON (pa.id=pl.Padr_onAgua)
         INNER JOIN TipoTomaAguaPotable t ON (t.id=" . (is_null($DatosPadron->TipoToma) ? "pa" : "pl") . ".TipoToma)
         WHERE pl.id=" . $idLectura . " ORDER BY pl.A_no DESC, pl.Mes DESC LIMIT 0, 1", "MesEjercicio");

         $a_noCobro = Funciones::ObtenValor("SELECT pl.A_no as a_noEjercicio FROM Padr_onAguaPotable pa
             INNER JOIN Padr_onDeAguaLectura pl ON (pa.id=pl.Padr_onAgua)
             INNER JOIN TipoTomaAguaPotable t ON (t.id=" . (is_null($DatosPadron->TipoToma) ? "pa" : "pl") . ".TipoToma)
             WHERE pl.id=" . $idLectura . " ORDER BY pl.A_no DESC, pl.Mes DESC LIMIT 0, 1", "a_noEjercicio");

         $diaLimite = 15;
         if( isset($DatosPadron->TipoToma) && $DatosPadron->TipoToma == 4 ){
             if( $mesCobro == 12){
                 if( date('m') == 12 ){
                     $fechaLimite = $diaLimite . '/' . intval(1) . '/' . ( date('Y') + 1 );
                     $fechaCorte = $diaLimite + 1 . '/' . intval(1) . '/' . ( date('Y') + 1 );
                 }elseif( date('m') == 1){
                     $fechaLimite = $diaLimite . '/' . intval(1) . '/' . date('Y');
                     $fechaCorte = $diaLimite + 1 . '/' . intval(1) . '/' .date('Y');
                 }else{
                     $fechaLimite = $diaLimite . '/' . date('m') . '/' . date('Y');
                     $fechaCorte = $diaLimite + 1 . '/' . date('m') . '/' .date('Y');
                 }
             }else{
                 $fechaLimite = $diaLimite . '/' . intval($mesCobro + 1) . '/' . date('Y');
                 $fechaCorte = $diaLimite + 1 . '/' . intval($mesCobro + 1) . '/' . date('Y');
             }
         }else{
             if( $mesCobro == 12){
                 if( date('m') == 12 ){
                     $fechaLimite = $diaLimite . '/' . intval(1) . '/' . ( date('Y') + 1 );
                     $fechaCorte = $diaLimite + 1 . '/' . intval(1) . '/' . ( date('Y') + 1 );
                 }elseif( date('m') == 1){
                     $fechaLimite = $diaLimite . '/' . intval(1) . '/' . date('Y');
                     $fechaCorte = $diaLimite + 1 . '/' . intval(1) . '/' .date('Y');
                 }else{
                     $fechaLimite = $diaLimite . '/' . date('m') . '/' . date('Y');
                     $fechaCorte = $diaLimite + 1 . '/' . date('m') . '/' .date('Y');
                 }
             }else{
                 $fechaLimite = $diaLimite . '/' . intval($mesCobro + 1) . '/' . date('Y');
                 $fechaCorte = $diaLimite + 1 . '/' . intval($mesCobro + 1) . '/' . date('Y');
             }
         }

     $resultado = DB::select("SELECT Mes, A_no FROM Padr_onDeAguaLectura WHERE Padr_onAgua = " . $idPadron . " AND `Status` = 1 ORDER BY A_no DESC, Mes DESC");

     $fechasPeriodo = array();
     $contador = 0;

     foreach ( $resultado as $valor ) {
         $fechasPeriodo[$contador] = get_object_vars($valor);
         $contador++;
     }

     $periodo = "";
         if ( count($fechasPeriodo) >= 2 ) {

             $mesInicio = $fechasPeriodo[ count($fechasPeriodo) -1 ]['Mes'];
             $mesFin = $fechasPeriodo[0]['Mes'];

             $a_noInicio = $fechasPeriodo[ count($fechasPeriodo) -1 ]['A_no'];
             $a_noFin =  $fechasPeriodo[0]['A_no'];

             $periodo = $meses[$mesInicio - 1] . " $a_noInicio - " . $meses[$mesFin - 1] . " $a_noFin";

         }else{
             if( count($fechasPeriodo) == 1 ){
                 $mesInicio = $fechasPeriodo[0]['Mes'];
                 $a_noInicio = $fechasPeriodo[0]['A_no'];

                 $periodo = $meses[$mesInicio - 1] . " $a_noInicio - " . $meses[$mesInicio - 1] . " $a_noInicio";

             }else{
                 $fechaPagada = DB::select("SELECT Mes, A_no FROM Padr_onDeAguaLectura WHERE Padr_onAgua = " . $idPadron . " AND `Status` = 2 ORDER BY A_no DESC, Mes DESC LIMIT 1");

                 $mesInicio = $fechaPagada[0]['Mes'];
                 $a_noInicio = $fechaPagada[0]['A_no'];

                 $periodo = $meses[$mesInicio - 1] . " $a_noInicio - " . $meses[$mesInicio - 1] . " $a_noInicio";
             }
         }

         $DatosHistoricos = DB::select("SELECT Consumo, Mes, A_no FROM Padr_onDeAguaLectura WHERE Mes = " . $mesCobro . " AND Padr_onAgua =" . $idPadron . " AND A_no < DATE_FORMAT( CURDATE(), '%Y') ORDER BY FechaLectura DESC LIMIT 3");


         $datosHistoricosTabla = '';
         foreach ($DatosHistoricos as $valor) {
             $datosHistoricosTabla .=
                 '<tr>
                     <td>' . $meses[$valor->Mes - 1] . '-' . $valor->A_no . '</td>
                     <td class="derecha">' . intval($valor->Consumo) . ' M3</td>
                 </tr>';
         }

         $descuentos  = Funciones::ObtenValor("SELECT PrivilegioDescuento FROM Padr_onAguaPotable WHERE id = " . $idPadron . " AND PrivilegioDescuento != 0", "PrivilegioDescuento");
         $esDescuento = FALSE;
         $descuento   = 0;
         $descNombre  = "";

         if ($descuentos != "NULL") {
             $esDescuento = TRUE;
             $descNombre = Funciones::tipoDescuento($descuentos);
         } else
             $esDescuento = FALSE;

         $Cotizaciones = Funciones::ObtenValor("SELECT GROUP_CONCAT(id) as Cotizaci_ones
             FROM
                 Cotizaci_on
             WHERE
                 Padr_on = " . $idPadron . "
                 and ( SELECT sum( importe ) FROM ConceptoAdicionalesCotizaci_on WHERE ConceptoAdicionalesCotizaci_on.Cotizaci_on = Cotizaci_on.id AND Padre IS NULL )>0 GROUP by Padr_on ORDER BY id DESC", 'Cotizaci_ones');
         #and (SELECT e.id FROM EncabezadoContabilidad e WHERE e.Pago IS NOT NULL AND e.Cotizaci_on = Cotizaci_on.id LIMIT 1 ) is null

         $FilaConceptosTotales  = "";

         if( $estaPagado ) goto sinCalcular;

         if ($Cotizaciones == "NULL") {
             return "";
         }

         $DescuentoGeneralCotizaciones = 0;
         $SaldoDescontadoGeneralTodo   = 0;

         $Descuentos = AguaController::ObtenerDescuentoConceptoRecibo($Cotizaciones, $cliente);
         $SaldosActuales = AguaController::ObtenerDescuentoPorSaldoAnticipadoRecibo($Cotizaciones, $Descuentos['ImporteNetoADescontar'], $Descuentos['Conceptos'], $cliente);

         $ConsultaConceptos="SELECT c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad, c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario,
         (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional, co.A_no, COALESCE(co.Mes, '01') as Mes, ct.Tipo, c.TipoToma
         FROM ConceptoAdicionalesCotizaci_on co
         INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
         INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )
         WHERE  co.Cotizaci_on IN( " .$Cotizaciones. ") and Estatus=0 ORDER BY co.A_no DESC, COALESCE(co.Mes, '01') DESC ,  co.id ASC ";

         $ResultadoConcepto  = DB::select($ConsultaConceptos);
         $ConceptosCotizados = '';
         $totalConcepto      = 0;
         $indexConcepto      = 0;

         setlocale(LC_TIME, "es_MX.UTF-8");

         $sumaSaldos          = 0;
         $sumaRecargos        = 0;
         $sumaDescuentos      = 0;
         $sumaTotalFinal      = 0;
         $sumaActualizaciones = 0;

         $RegistroConcepto = $ResultadoConcepto[0];

         $consumoMesActual = array();

         if (empty($RegistroConcepto->Adicional)) {
             $consumoMesActual["Consumo"] = str_replace(",", "", $RegistroConcepto->total);
         } else {
             $consumoMesActual[$RegistroConcepto->Adicional] = str_replace(",", "", $RegistroConcepto->total);
         }

         $ActualizacionesYRecargosFunciones = AguaController::ObtenerRecargosYActualizacionesPorConceptoRecibo($RegistroConcepto->ConceptoCobro, $Cotizaciones, $cliente);

         $importeNeto = $sub_total = str_replace(",", "", $RegistroConcepto->total);

         $Actualizaci_on = str_replace(",", "", $ActualizacionesYRecargosFunciones['Actualizaciones']);
         $Recargos = str_replace(",", "", $ActualizacionesYRecargosFunciones['Recargos']);

         $DescuentoGeneralCotizaciones += $Descuento = str_replace(",", "", $Descuentos[$RegistroConcepto->ConceptoCobro]);
         $SaldoDescontadoGeneralTodo += $saldo = str_replace(",", "",$SaldosActuales[$RegistroConcepto->ConceptoCobro]);

         $sub_total = ($sub_total);

         $sumaActualizaciones=str_replace(",", "",$sumaActualizaciones);
         $sumaRecargos=str_replace(",", "",$sumaRecargos);
         $sumaActualizaciones += $Actualizaci_on;
         $sumaRecargos += $Recargos;

         $Descuento = number_format(str_replace(",", "",$Descuentos[$RegistroConcepto->ConceptoCobro]), 2);
         $sumaDescuentos=str_replace(",", "",$sumaDescuentos);
         $sumaDescuentos += str_replace(",", "",$Descuento);
         $sumaSaldos=str_replace(",", "",$sumaSaldos);
         $sumaSaldos += str_replace(",", "",$saldo);

         $subtotal = number_format(str_replace(",", "", $sub_total) , 2);
         $subtotal = str_replace(",", "", $subtotal);
         $sumaTotalFinal = str_replace(",", "",$sumaTotalFinal);
         $sumaTotalFinal += $subtotal+$Recargos+$Actualizaci_on;

         $totalConcepto                                  = $RegistroConcepto->total;
         $ConceptosCotizados                             = $RegistroConcepto->idConceptoCotizacion . ',';
         $ConceptoPadre[$indexConcepto]['id']            = $RegistroConcepto->idConceptoCotizacion;
         $ConceptoPadre[$indexConcepto]['Mes']           = $RegistroConcepto->Mes;
         $ConceptoPadre[$indexConcepto]['A_no']          = $RegistroConcepto->A_no;
         $ConceptoPadre[$indexConcepto]['Total']         = 0;
         $ConceptoPadre[$indexConcepto]['Nombre']        = $RegistroConcepto->NombreConcepto;
         $ConceptoPadre[$indexConcepto]['TipoPredio']    = $RegistroConcepto->Tipo;
         $ConceptoPadre[$indexConcepto]['idConcepto']    = $RegistroConcepto->ConceptoCobro;
         $ConceptoPadre[$indexConcepto]['FechaConcepto'] = $RegistroConcepto->A_no . "-" . $RegistroConcepto->Mes . "-01";

         $conceptosNombresMes     = array(); #Conceptos del mes actual
         $adicionalesNombresMes   = array(); #Adicionales del mes actual
         $conceptosNombres        = array(); #Conceptos meses de adeudo - Consumo
         $adicionalesNombres      = array(); #Adicionales nombres meses de adeudo
         $adicionalesValores      = array(); #Adicionales valores meses de adeudo
         $conceptosOtrosNombres   = array(); #Otros conceptos nombres
         $conceptosOtrosValores   = array(); #Otros conceptos valores
         $adicionalesOtrosNombres = array(); #Otros adicionales nombres
         $adicionalesOtrosValores = array(); #Otros adicionales valores
         $recargosActualizaciones = array(); # Recargos y actualizaciones

         $sumaConceptosA = 0;
         $sumaAdicionalesA = 0;
         $i=0;

        foreach ($ResultadoConcepto as $RegistroConcepto) {
            if($i!=0){
             $ActualizacionesYRecargosFunciones = AguaController::ObtenerRecargosYActualizacionesPorConceptoRecibo($RegistroConcepto->ConceptoCobro, $Cotizaciones, $cliente);

             $importeNeto = $sub_total = str_replace(",", "", $RegistroConcepto->total);

             $Actualizaci_on = str_replace(",", "", $ActualizacionesYRecargosFunciones['Actualizaciones']);
             $Recargos = str_replace(",", "", $ActualizacionesYRecargosFunciones['Recargos']);

             $DescuentoGeneralCotizaciones += $Descuento = str_replace(",", "", $Descuentos[$RegistroConcepto->ConceptoCobro]);
             $SaldoDescontadoGeneralTodo += $saldo = str_replace(",", "",$SaldosActuales[$RegistroConcepto->ConceptoCobro]);

             $sub_total = ($sub_total);
             $sub_total=str_replace(",", "", $sub_total);

             $sumaActualizaciones=str_replace(",", "",$sumaActualizaciones);
             $sumaRecargos=str_replace(",", "",$sumaRecargos);
             $sumaActualizaciones += $Actualizaci_on;
             $sumaRecargos += $Recargos;

             $Descuento = number_format($Descuentos[$RegistroConcepto->ConceptoCobro], 2);
             $sumaDescuentos += $Descuento;
             $sumaSaldos = str_replace(",", "",$sumaSaldos);
             $sumaSaldos += str_replace(",", "",$saldo);

             $subtotal = number_format(str_replace(",", "", $sub_total) , 2);
             $subtotal = str_replace(",", "", $subtotal);
             $sumaSaldos =str_replace(",", "", $sumaSaldos);
             $sumaTotalFinal += $subtotal+$Recargos+$Actualizaci_on;

             if (empty($RegistroConcepto->Adicional)) {
                 //Es concepto
                 $ConceptoPadre[$indexConcepto]['Total'] = $totalConcepto;

                 $totalConcepto = $RegistroConcepto->total;
                 $indexConcepto++;

                 $ConceptoPadre[$indexConcepto]['id']            = $RegistroConcepto->idConceptoCotizacion;
                 $ConceptoPadre[$indexConcepto]['Mes']           = $RegistroConcepto->Mes;
                 $ConceptoPadre[$indexConcepto]['A_no']          = $RegistroConcepto->A_no;
                 $ConceptoPadre[$indexConcepto]['Nombre']        = $RegistroConcepto->NombreConcepto;
                 $ConceptoPadre[$indexConcepto]['TipoPredio']    = $RegistroConcepto->Tipo;
                 $ConceptoPadre[$indexConcepto]['idConcepto']    = $RegistroConcepto->ConceptoCobro;
                 $ConceptoPadre[$indexConcepto]['FechaConcepto'] = $RegistroConcepto->A_no . "-" . $RegistroConcepto->Mes . "-01";

                 if (empty($RegistroConcepto->TipoToma)) {
                     $conceptosOtrosNombres[] = $RegistroConcepto->NombreConcepto;
                     $conceptosOtrosValores[] = $subtotal;
                 } else {
                     //if ($RegistroConcepto['A_no'] == $a_noCobro && $RegistroConcepto['Mes'] == $mesCobro) {
                       //  $conceptosNombresMes[$RegistroConcepto['ConceptoCobro']] = $subtotal;
                   //  } else {
                     $sumaConceptosA = str_replace(",", "", $sumaConceptosA);
                     $sumaConceptosA += $subtotal; //Para el consumo de meses anteriores
                     //}
                 }
             } else {
                 //Es adicional
                 $totalConcepto += $RegistroConcepto->total;

                 if (empty($RegistroConcepto->TipoToma)) {
                     $adicionalesOtrosNombres[] = $RegistroConcepto->Adicional;
                     $adicionalesOtrosValores[] = $subtotal;
                 } else {
                     if ($RegistroConcepto->A_no == $a_noCobro && $RegistroConcepto->Mes == $mesCobro) {
                         $adicionalesNombresMes[$RegistroConcepto->Adicional] = $subtotal;
                     } else {
                         $adicionalesNombres[] = $RegistroConcepto->Adicional;
                         $adicionalesValores[] = $subtotal;
                     }
                 }
             }
             $ConceptosCotizados .= $RegistroConcepto->idConceptoCotizacion . ',';
            }
            $i++;
         }

         if ($sumaConceptosA > 0) {
             $conceptosNombres["Consumo"] = $sumaConceptosA;
         }

         $contar = array();
         $i = 0;
         foreach ($adicionalesNombres as $value) {
             if (isset($contar[$value])) {
                 // si ya existe, le añadimos uno
                 $contar[$value] = str_replace(",", "", $contar[$value]);
                 $contar[$value] += str_replace(",", "", $adicionalesValores[$i]);
             } else {
                 // si no existe lo añadimos al array
                 $contar[$value] = str_replace(",", "", $adicionalesValores[$i]);
             }
             $i++;
         }

         $conceptosOtros = array();
         $j = 0;
         foreach ($conceptosOtrosNombres as $value) {
             $concepto = str_replace(",", "", $value);

             if( isset( $conceptosOtros[$concepto] ) )
                 $conceptosOtros[ $concepto ] += str_replace(",", "",$conceptosOtrosValores[$j]);
             else
                 $conceptosOtros[ $concepto ] = str_replace(",", "",$conceptosOtrosValores[$j]);

             $j++;
         }

         $adicionalesOtros = array();
         $k = 0;
         foreach ($adicionalesOtrosNombres as $value) {
             $adicional = str_replace(",", "", $value);
             $adicional = str_replace("%", "", $adicional);

             if ( isset($adicionalesOtros[$adicional]) )
                 $adicionalesOtros[$adicional] += str_replace( ",", "", $adicionalesOtrosValores[$k] );
             else
                 $adicionalesOtros[$adicional] = str_replace( ",", "", $adicionalesOtrosValores[$k] );

             $k++;
         }

         $ConceptoPadre[$indexConcepto]['Total'] = $totalConcepto;
         $ConceptosCotizados = substr_replace($ConceptosCotizados, '', -1);

         //uno los arrays
         $array_mes    = array_merge($consumoMesActual, $adicionalesNombresMes);
         $array_otros  = array_merge($conceptosOtros, $adicionalesOtros);
         $array_rezago = array_merge($conceptosNombres, $contar);

         $totalMes       = 0;
         $totalOtros     = 0;
         $totalRezago    = 0;
         $totalConsumo   = 0;
         $totalFinal     = $sumaTotalFinal;
         $totalesFinales = $sumaActualizaciones + $sumaRecargos;
         $totalesActRec  = $sumaActualizaciones + $sumaRecargos;

         if (empty($array_rezago)) {
             foreach ($array_mes as $key => $value) {
                 if($key == "Consumo") {
                     $totalConsumo += str_replace(",", "", $value);
                 }else{
                     $FilaConceptosTotales .= '
                     <tr>
                         <td colspan="4">&nbsp;</td>
                         <td class="bordeG">' . AguaController::conceptoAbreviado( utf8_decode($key) ) . '</td>
                         <td class="bordeG" align="right">' . (number_format($value, 2)) . '</td>
                     </tr>';
                 }

                 $totalMes += str_replace(",", "", $value);
             }
         } else {
             foreach ($array_mes as $key => $value) {
                 if( isset( $array_rezago[$key] ) ){
                     if($key == "Consumo") {
                         $totalConsumo += str_replace(",", "", $value);
                     }else{
                         $FilaConceptosTotales .= '
                             <tr>
                                 <td colspan="4">&nbsp;</td>
                                 <td class="bordeG">' . AguaController::conceptoAbreviado( utf8_decode($key) ) . '</td>
                                 <td class="bordeG" align="right" >' . (number_format($value, 2)) . '</td>
                             </tr>';
                     }

                     $totalMes     = str_replace(",", "", $totalMes);
                     $totalMes    += str_replace(",", "", $value);
                     $totalRezago  = str_replace(",", "", $totalRezago);
                     $totalRezago += str_replace(",", "", $array_rezago[$key]);
                 }else{
                     if($key == "Consumo") {
                         $totalConsumo += str_replace(",", "", $value);
                     }else{
                         $FilaConceptosTotales .= '
                             <tr>
                                 <td colspan="4">&nbsp;</td>
                                 <td class="bordeG">' . AguaController::conceptoAbreviado( utf8_decode($key) ) . '</td>
                                 <td class="bordeG" align="right" >' . (number_format($value, 2)) . '</td>
                             </tr>';
                     }

                     $totalMes     = str_replace(",", "", $totalMes);
                     $totalMes    += str_replace(",", "", $value);
                 }
             }
         }

         $rezagosTotales = $totalRezago;

         if (!empty($array_otros)) {
             foreach ($array_otros as $key => $value) {

                 $FilaConceptosTotales .= '
                     <tr>
                         <td colspan="4">&nbsp;</td>
                         <td CLASS="bordeG">' . AguaController::conceptoAbreviado( utf8_decode($key) ) . '</td>
                         <td CLASS="bordeG" align="center" >' . (number_format($value, 2)) . '</td>
                     </tr>';

                 $totalOtros = str_replace(",", "", $totalOtros);
                 $totalOtros +=  str_replace(",", "", $value);
             }
         }

         sinCalcular:

         $fechaActual = date("Y-m-d");
         $auxMes = array("", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");

         if ($estaPagado) {
             $totalFinal = str_replace(",", "", $DatosParaRecibo['AdeudoCompleto']);

             $FilaConceptosTotales .= '
                     <tr>
                         <td colspan="4">&nbsp;</td>
                         <td class="bordeG">Adeudo Completo</td>
                         <td class="bordeG" align="center" >' . (number_format(str_replace(",", "", $totalFinal), 2))  . '</td>
                     </tr>';

             $totalRezago = 0;
             $totalMes = 0;

             goto finCalculos;
         }

         $saldo = Funciones::ObtenValor("SELECT SaldoNuevo FROM Padr_onAguaHistoricoAbono WHERE idPadron = " . $idPadron . " ORDER BY id DESC ", "SaldoNuevo");
         $saldoNuevo = 0;
         if($saldo != "NULL"){
             //Si el saldo es menor a lo que se debe
             if($saldo < $sumaSaldos){
                 $saldoNuevo = 0;
             }
             #Si el saldo es mayor a lo se se debe
             if($saldo > $totalFinal){
                 $saldoNuevo = $saldo - $sumaSaldos;
             }
         }

         $decimales = 0;
         if (is_float($totalFinal) && $totalFinal > 0) {
             #En caso de que el total sea decimal - Se toma el numero despues del punto
             $exp = explode(".", $totalFinal);
             #Se asigna el numero tomado
             if(isset($exp[1]))
                 $decimales = "0." . $exp[1];
             else
                 $decimales = "0";
         }

         $estaAjustado = FALSE;
         $ajuste = 0;
         $ajusteFinal = 0;

         if ( is_float( $totalFinal ) && $totalFinal > 0 ){
             $ajuste = $decimales;
             $ajusteFinal = intval($totalFinal);
             $estaAjustado = TRUE;
         }else{
             $ajusteFinal = str_replace(",", "",$totalFinal);
         }

         $totalFinal = intval( $totalFinal );

         if ($totalesFinales > 0) {
             $totalRezago += str_replace(",", "", $totalesFinales);

             $FilaConceptosTotales .= '
                 <tr>
                     <td colspan="4">&nbsp;</td>
                     <td class="bordeG" >Act. y Recargos</td>
                     <td class="bordeG" align="right">' . number_format($totalesActRec, 2)  . '</td>
                 </tr>';
         }

         if ($estaAjustado) {
             //$FilaConceptosTotales .= '<tr>
              //           <td>Redondeo</td>
              //       </tr>';
           //  $FilaConceptosTotales3 .= '<tr>
             //            <td class="derecha">
               //              ' . $ajuste . '
                 //        </td>
                 //    </tr>';
         }

         $totalFinal = str_replace(",", "", $ajusteFinal);

         finCalculos:

         if( $estaPagado )
             $totalFinal = 0;

         $FilaConceptosTotales .= '
                 <tr>
                     <td colspan="4">&nbsp;</td>
                     <td class="bordeG">Redondeo</td>
                     <td class="bordeG" align="right">' . $decimales  . '</td>
                 </tr>
                 <tr>
                     <td colspan="4">&nbsp;</td>
                     <td class="bordeG">Total</td>
                     <td class="bordeG" align="right">' . number_format($totalFinal, 2)  . '</td>
                 </tr>';

         //cantidad con letra y total
         $letras = utf8_decode(Funciones::num2letras($totalFinal, 0, 0));
         $ultimoArr = explode(".", number_format($totalFinal, 2)); //recupero lo que este despues del decimal
         $ultimo = $ultimoArr[1];
         if ($ultimo == "")
             $ultimo = "00";
         $letras = $letras . " pesos " . $ultimo . "/100 M.N.";

         $nombreComercial = $DatosPadron->NombreORaz_onSocial;
         $metodoCobro = Funciones::ObtenValor(" select Descripci_on as Nombre from M_etodoCobroAguaPotable WHERE id=".$DatosPadron->M_etodoCobro, "Nombre");

         if( strlen($nombreComercial) > 0 && strlen($nombreComercial) > 55 ){
             $nombreComercial = substr($nombreComercial, 0, strlen($nombreComercial) / 2) . '<br>' . substr($nombreComercial, strlen($nombreComercial) / 2, strlen($nombreComercial) );
         }

         $domicilio = ucwords( strtolower( utf8_decode($DatosPadron->Domicilio ) ) );

         $giro = '';
         #$giro = $DatosPadron['Giro'];
         if($DatosPadron->Giro != ''){
             $giro = Funciones::ObtenValor("SELECT Descripci_on AS Nombre FROM Giro WHERE id = " . $DatosPadron->Giro, "Nombre");

             if($giro != 'NULL'){
                 $giro = $DatosPadron->Giro.': '.$giro;

                 $longitud = strlen($giro);

                 if($longitud >= 100){
                     $giro = substr($giro, 0, 95);
                 }
             }
         }
         $rutaBarcode = 'https://suinpac.com/lib/barcode2.php?f=png&text=' . (isset($DatosPadron->ContratoVigente) ? $DatosPadron->ContratoVigente : '') . '&codetype=Code128&orientation=Horizontal&size=40&print=false "';

         $DIR_IMG = 'imagenes/';

         $htmlGlobal = '<div>
             <table  WIDTH="100%" border="0">
                 <tr>
                     <td width="20%" height="45px" align="center">     
                     <!--<img  width="130px" src="https://tixtla.servicioenlinea.mx/express/img/logo_semapa_p1.jpeg"> --!>         
                     <img  width="130px" src="'.asset(Storage::url( $DIR_IMG.'logo_semapa_p1.jpeg')).'">

                     </td>
                     <td align="center">
                         Municipio de Tixtla de Guerrero, Guerrero. <br>
                         Servicios Municipales de Agua Potable y Alcantarillado. <br>
                         Av. Independencia No. 01, Colonia Centro, C.P. 39170, Telefono (754) 47-4-20-30
                     </td>
                     <td width="20%" align="center" valign="top">

                         <img width="85px" style="position: absolute; top:-20px; left:620px; border:none;"
                             src="'.asset("/".$DatosCliente->Logo).'">
                     </td>
                 </tr>
             </table>
             <br>
             <table WIDTH="100%" border="0">
                 <tr>
                     <td class="bordeR" valign="top" width="45%" height="55px">
                         '. $contribuyente .'<br>
                         '.($domicilio) .' <br>
                         '.$DatosPadron->Colonia_c.'
                     </td>
                     <td width="25%" class="bordeR">
                         Contrato: '.$DatosPadron->ContratoVigente.' <br>
                         Ruta: '.$DatosPadron->Ruta.' <br>
                         Registro: '.$DatosPadron->Cuenta.' <br>
                         Toma: '.$tipoToma.' - '.$metodoCobro.'
                     </td>
                     <td width="30%" class="bordeR">
                         Folio: <b style="color: red">'.$idLectura.'</b> <br>
                         Mes de Pago: '.$meses[intval($mesCobro)-1].' <br>
                         Paguese Antes de: '.$fechaLimite.' <br>
                         Periodo: '.$periodo.'
                     </td>
                 </tr>
             </table>
             <br>
             <table class="tablaC" WIDTH="100%" HEIGHT="120px" border="0">
                 <tr>
                     <td colspan="2" class="items2" style="border: black 1px solid;">Lecturas</td>
                     <td rowspan="2" class="items2 bordeG">Consumo del <br> Mes en M3</td>
                     <td rowspan="2" class="items2 bordeG">Adeudo Anterior</td>
                     <td rowspan="2" class="items2 bordeG">Importe del Mes</td>
                     <td rowspan="2" class="items2" style="border-radius: 5px; border: black 1px solid;">Subtotal</td>
                 </tr>
                 <tr>
                     <td class="items2 bordeG">Anterior</td>
                     <td class="items2 bordeG">Actual</td>
                 </tr>
                 <tr>
                     <td class="items bordeG">'.intval($lecturaAnterior).'</td>
                     <td class="items bordeG">'.intval($lecturaActual).'</td>
                     <td class="items bordeG">'.intval($consumo).' </td>
                     <td align="right" class="bordeG">'.number_format($rezagosTotales, 2).'</td>
                     <td align="right" class="bordeG">'.number_format($totalConsumo, 2).'</td>
                     <td align="right" class="bordeG">'.number_format(($rezagosTotales+$totalConsumo), 2).'</td>
                 </tr>
                  <tr>
                  <td colspan="6">
                         <table WIDTH="100%" style="position: absolute; top:45px; left:0px; border:none;" border="0">
                             <tr>
                                 <td colspan="4"><strong>Observaciones:</strong></td>
                                 <td colspan="2">&nbsp;</td>
                             </tr>
                             <tr>
                                 <td colspan="4">- El pago de este recibo, no lo exime de adeudos anteriores.</td>
                                 <td colspan="2">&nbsp;</td>
                             </tr>
                             <tr>
                                 <td colspan="4">'.utf8_decode("- Atenta suplica, evite la suspensión pagando oportunamente su servicio.").'</td>
                                 <td colspan="2">&nbsp;</td>
                             </tr>
                             <tr>
                                 <td colspan="4">'.utf8_decode("- Estimado usuario, si requiere factura proporcione sus datos a la administración de la SEMAPA").'.</td>
                                 <td colspan="2">&nbsp;</td>
                             </tr>
                         </table>
                     </td>
                 </tr>
                 '.$FilaConceptosTotales.'
                 <tr>
                 <td style="border-radius: 5px; border: black 1px solid;" colspan="4">Importe: ('.$letras.')</td>
                 <td colspan="2" align="center"  width="100px" height="30px">
                     <img width="200px" height="25px" style="position: absolute; bottom: 2px; right: 20px"
                     src="'.$rutaBarcode.'" >
                 </td>
                 </tr>
             </table>

             <!--<div class="tablaTop"></div>-->
         </div>';
     /*
         include( app_path() . '/Libs/Wkhtmltopdf.php' );
             try {
                 $nombre = uniqid() . "_" . $idLectura;
                 #$wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'page_width'=>120,'page_height'=>200,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
                 $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'margins'=>array('top'=>1,'left'=>1,'right'=>1)));
                 $wkhtmltopdf->setHtml($htmlGlobal);
                 //$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, $nombre.".pdf");
                 $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $nombre . ".pdf");
                 //return "repositorio/temporal/" . $nombre . ".pdf";
                 return response()->json([
                     'success' => '1',
                     'ruta' => "repositorio/temporal/" . $nombre . ".pdf",
                 ]);
             } catch (Exception $e) {
                 echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
             }*/
             return $htmlGlobal;
    }


    //pegueF

    public function estadoCuentaAguaAnterior(Request $request)
    {
        $idPadron = $request->IdPadron;
        $cliente = $request->Cliente;

        #return $request;
        Funciones::selecionarBase($cliente);

        switch ($cliente){
            case 20:
               # $url2 = AguaController::GenerarReciboOficialCapachIndividual( $idPadron, $cliente );

                #return $url2;
                #$url2 = GenerarReciboOficialCapach2( $idPadron, $cliente );
                #$url2 = GenerarReciboOficialCapach20($_GET['clave'], $cliente );
                #$url3 = GenerarReciboOficialAnualCapachIndividual($idPadron, $cliente);
            break;

            case 25:
                $url2 = GenerarReciboOficialTecpan( $idPadron, $cliente );
#               return $url2;
            break;

            case 31:
                #$url2 = generaReciboOficialCapaz( $idPadron, $cliente );
                #$url2 = generaReciboOficialCapazIndividual( $idPadron, $cliente );

                $url2 = AguaController::GenerarReciboOficialSemapaIndividual( $idPadron, $cliente );
               return $url2 ;
               // $url3 = GenerarReciboOficialAnualCapazIndividual($idPadron, $cliente);

                #precode($idPadron, 1);
            break;

            case 32:
                #$url2 = generaReciboOficialCapaz( $idPadron, $cliente );
                #$url2 = generaReciboOficialCapazIndividual( $idPadron, $cliente );

                $url2 = AguaController::GenerarReciboOficialCapazIndividual( $idPadron, $cliente );
               return $url2 ;
               // $url3 = GenerarReciboOficialAnualCapazIndividual($idPadron, $cliente);

                #precode($idPadron, 1);
            break;
        }

    }




    public function estadoCuentaAgua(Request $request)
    {
        $idPadron = $request->IdPadron;
        $cliente = $request->Cliente;
        $reciboConvenio= $request->Convenio;
        #return $request;
        Funciones::selecionarBase($cliente);

        switch ($cliente){
            case 20:
                /*$url2="";
                if($reciboConvenio==0){
                    $url2 = AguaController::GenerarReciboOficialCapachIndividual( $idPadron, $cliente );
                }else{
                    $convenio = Funciones::ObtenValor("SELECT COUNT(*) AS total FROM Padr_onConvenio WHERE idPadron = $idPadron AND Estatus = 1", "total");
                    if( $convenio > 0 ){
                        $url2 = AguaController::GenerarReciboOficialCapachIndividual( $idPadron, $cliente, true );
                    }
                }
                return $url2;*/
                #$url2 = GenerarReciboOficialCapach2( $idPadron, $cliente );
                #$url2 = GenerarReciboOficialCapach20($_GET['clave'], $cliente );
                #$url3 = GenerarReciboOficialAnualCapachIndividual($idPadron, $cliente);
            break;
            case 25:#Tecpan
                /*$url2 = GenerarReciboOficialTecpan( $idPadron, $cliente );
                return $url2;*/
            break;
            case 31:#Recibo de Agua Tixtla
                $url = AguaController::GenerarReciboIndividual( $idPadron, $cliente );
                return $url ;
                #$url2 = generaReciboOficialCapaz( $idPadron, $cliente );
                #$url2 = generaReciboOficialCapazIndividual( $idPadron, $cliente );
                #$url2 = AguaController::GenerarReciboOficialSemapaIndividual( $idPadron, $cliente );
                #$url3 = GenerarReciboOficialAnualCapazIndividual($idPadron, $cliente);
                #precode($idPadron, 1);
            break;
            case 32:#Recibo de Agua CAPAZ
                $url = AguaController::GenerarReciboIndividual( $idPadron, $cliente );
                return $url ;
                #$url2 = generaReciboOficialCapaz( $idPadron, $cliente );
                #$url2 = generaReciboOficialCapazIndividual( $idPadron, $cliente );
                #$url3 = GenerarReciboOficialAnualCapazIndividual($idPadron, $cliente);
                #precode($idPadron, 1);
            break;
            case 49:#Recibo de Agua Teloloapan
                $url = AguaController::GenerarReciboIndividual( $idPadron, $cliente );
               return $url ;
            break;
            case 50:#Recibo de Agua Huitzuco
                $url = AguaController::GenerarReciboIndividual( $idPadron, $cliente );
               return $url ;
            break;
            case 55:#Recibo de Agua Ayutla
                $url = AguaController::GenerarReciboIndividual( $idPadron, $cliente );
               return $url ;
            break;
            case 68:#Recibo de Agua Quechultenango
                $url = AguaController::GenerarReciboIndividual( $idPadron, $cliente );
               return $url ;
            break;
        }

    }


    function  GenerarReciboOficialCapachIndividual($idPadron, $cliente , $convenio=false, $tipo=0){
        if( $convenio ){

            $html1 = AguaController::GenerarReciboOficialCapach($idPadron, $cliente, true, 1, false, false,"USUARIO");
            $html2 = AguaController::GenerarReciboOficialCapach($idPadron, $cliente, true, 1, false, false, "CAJA");

        }else{

            $html1 = AguaController::GenerarReciboOficialCapach($idPadron, $cliente, false, 1, false, false, "USUARIO");
            $html2 = AguaController::GenerarReciboOficialCapach($idPadron, $cliente, false, 1, false, false, "CAJA");
        }


        $htmlGlobal ='<html dir="ltr">
        <head>
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link href="'. asset(Storage::url(env('RESOURCES').'bootstrap.min.css')).'">
        </head>

        <body>
        <style>

            .centrado {
                text-align: center;
            }
            .derecha {
                text-align: right;
            }
            .izquierda {
            text-align: left;
            }
            .letras{
                font-family: "Arial", serif;
                font-size: 6pt;
            }
            .giro{
                font-family: "Arial", serif;
                font-size: 8px;
            }
            .numeros{
                font-family: "Arial", serif;
                font-size: 6pt;
            }
            td {
                font-family: "Arial";
                font-size: 8.6px;
            }
            #titulo{
                font-family: "Arial", serif;
                font-size: 10pt;
                font-weight: bold;
            }
            .negritas{
                font-family: "Arial", serif;
                font-size: 6.3pt;
                font-weight: bold;
            }

            .direccion{
                font-family: "Arial", serif;
                font-size: 5.6pt;

            }
            .usuario{
                font-family: "Arial", serif;
                font-size: 4.5pt;
            }

            .contenedor {
                width: 100%;
                height: 100%;
            }
            /*table, th, td {
                border: 1px solid black;
                border-collapse: collapse;
            }*/
            .mayusculas{
                text-transform: uppercase;
            }
            table, tr, td, th, tbody, thead, tfoot {
                page-break-inside: avoid !important;
            }
            .completo{
                border: 1px solid #FFFFFF;
                clear: both;
                display: block;
                page-break-inside: avoid;
            }
            table.report-container div.contenedor {
                page-break-inside: avoid;
            }
            .bordeRojo{
                border: red 2px solid;
            }
            .CSA{
                color: red;
                font-size: 19px;
                font-weight: bold;
            }
            .CSAD{
                color: red;
                font-size: 18px;
                font-weight: bold;
            }
            .marcaCSA {
                z-index: 1;
                top: 35px;
                left: -70px;
                display: flex;
                position: relative;
                align-items: center;
                justify-content: center;
                transform: rotate(50deg);
                -o-transform: rotate(50deg);
                -moz-transform: rotate(50deg);
                -webkit-transform: rotate(50deg);
            }
            .marcaCSAD {
                z-index: 1;
                top: 15px;
                left: -83px;
                display: flex;
                position: relative;
                align-items: center;
                justify-content: center;
                transform: rotate(50deg);
                -o-transform: rotate(50deg);
                -moz-transform: rotate(50deg);
                -webkit-transform: rotate(50deg);
            }
            .barra{
                top: -70px;
                left: 130px;
                z-index: 1;
                display: flex;
                position:relative;
                transform: rotate(90deg);
                -o-transform: rotate(90deg);
                -moz-transform: rotate(90deg);
                -webkit-transform: rotate(90deg)
            }
            .sobre{
                left: -60px;
                z-index: 1;
                display: flex;
                position:relative;
            }
        </style>
        <div class="contenedor" >
            <table WIDTH="100%" HEIGHT="100%" class="completo">
                <tbody>
                    <tr>
                        <td WIDTH="48%" HEIGHT="50%" style="overflow:hidden; white-space:nowrap; text-overflow: ellipsis;">'
            .$html1.
            '</td>

                        <td WIDTH="2%"></td>

                        <td WIDTH="50%" HEIGHT="50%" style="overflow:hidden; white-space:nowrap; text-overflow: ellipsis;">'
            .$html2.
            '</td>
                    </tr>

                    <tr>
                        <td WIDTH="48.5%" HEIGHT="12px" style="overflow:hidden; white-space:nowrap; text-overflow: ellipsis;"></td>
                        <td WIDTH="3.5%"></td>
                        <td WIDTH="48%" HEIGHT="12px" style="overflow:hidden; white-space:nowrap; text-overflow: ellipsis;"></td>
                    </tr>

                    <tr>
                        <td WIDTH="48.5%" HEIGHT="50%" style="overflow:hidden; white-space:nowrap; text-overflow: ellipsis;">

                        </td>

                        <td WIDTH="3.5%"></td>

                        <td WIDTH="48%" HEIGHT="50%" style="overflow:hidden; white-space:nowrap; text-overflow: ellipsis;">

                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </body>
    </html>';



       if ($tipo == 1) {
            return $htmlGlobal;
        } else {
           include_once( app_path() . '/Libs/Wkhtmltopdf.php' );
            try {
                $nombre = uniqid('', false) . "_" . $idPadron;
                #$wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'page_width'=>120,'page_height'=>200,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
                $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'orientation'=>'Portrait', 'margins' => array('top' => 3, 'left' => 5, 'right' => 5, 'bottom'=>2)));
                $wkhtmltopdf->setHtml($htmlGlobal);
                //$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, $nombre.".pdf");
                $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $nombre . ".pdf");
                //return "repositorio/temporal/" . $nombre . ".pdf";
                return response()->json([
                    'success' => '1',
                    'ruta' => "repositorio/temporal/" . $nombre . ".pdf",
                ]);
            } catch (Exception $e) {
                echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
            }
        }



    }

    function GenerarReciboOficialCapach($idPadron, $cliente, $convenio = false, $tipo = 0, $retornarPagado = false, $sellos, $tipoRecibo ,$leyenda = false){

        $DatosCliente = Funciones::ObtenValor("SELECT *, m.Nombre as Municipio, e.Nombre as Estado, cr.Ruta as Logo
        FROM Cliente c
            INNER JOIN DatosFiscales     d  ON (d.id=c.DatosFiscales)
            INNER JOIN EntidadFederativa e  ON (e.id=d.EntidadFederativa)
            INNER JOIN Municipio         m  ON (m.id=d.Municipio)
            INNER JOIN CelaRepositorio   cr ON (cr.idRepositorio=c.Logotipo)
        WHERE c.id=". $cliente);

        $idLectura = Funciones::ObtenValor( "SELECT id FROM Padr_onDeAguaLectura WHERE Padr_onAgua = " . $idPadron . " AND Status = 1 ORDER BY A_no DESC, Mes DESC LiMIT 0, 1", "id");

        $estaPagado = FALSE;
        if ($idLectura == 'NULL' || $idLectura == '') {
            $estaPagado = TRUE;

            if( $retornarPagado )
                return "";

            $idLectura = Funciones::ObtenValor("SELECT MAX(id) as id FROM Padr_onDeAguaLectura WHERE Padr_onAgua =" . $idPadron . " AND Status = 2", "id");
        }

        $corteSA = false;
        $corteSAD = false;
        $adeudos = "";
        $adeudo = Funciones::ObtenValor("SELECT COUNT(id) as adeudo FROM Padr_onDeAguaLectura WHERE Padr_onAgua = " . $idPadron . " AND Status = 1", "adeudo");
        if ( $adeudo != 'NULL' ) {
            $adeudo = intval($adeudo);
            $adeudos = "" . $adeudo;

            if($adeudo > 3 && $adeudo <= 10 && $sellos){
                $corteSA = true;
            }
            if($adeudo > 10 && $sellos){
                $corteSAD = true;
            }

        }

        $cuentasPapas = Funciones::ObtenValor("SELECT GROUP_CONCAT(p.id) as CuentasPapas FROM Padr_onAguaPotable p WHERE p.id=(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1) and p.Cliente=" . $cliente, 'CuentasPapas');
        $arrayPapas = explode(",", $cuentasPapas);

        $tam = count($arrayPapas);
        $esPapa = FALSE;

        //Recorro todos los papas
        for ($i = 0; $i < $tam; $i++) {
            if ($idPadron == $arrayPapas[$i]) {
                $esPapa = TRUE;
                break;
            }
        }

        $anomalia = Funciones::ObtenValor("SELECT pac.descripci_on FROM Padr_onDeAguaLectura pal INNER JOIN Padr_onAguaCatalogoAnomalia pac ON pal.Observaci_on = pac.id WHERE pal.id=" . $idLectura, "descripci_on");
        $tieneAnomalia = TRUE;

        if ($anomalia == 'NULL') {
            $anomalia = '';
            $tieneAnomalia = FALSE;
        }

        $DatosPadron = Funciones::ObtenValor("SELECT
        d.RFC,
        pa.Ruta,
        pa.Lote,
        pa.Cuenta,
        pa.Sector,
        pa.Manzana,
        pa.Consumo,
        pa.Colonia,
        pa.Medidor,
        pa.Diametro,
        pa.TipoToma,
        pa.Domicilio2 AS Domicilio2,
        CONCAT(
            'Calle ', COALESCE(pa.Domicilio, ''),
            ' Lote ', COALESCE(pa.Lote, ''),
            ' Colonia ', COALESCE(pa.Colonia, '')
        ) AS Domicilio,
        pa.Giro as Giro,
        pa.M_etodoCobro,
        pa.SuperManzana,
        c.C_odigoPostal_c,
        pa.ContratoVigente,
        d.NombreORaz_onSocial,
        COALESCE ( c.NombreComercial, NULL ) AS NombreComercialPadron,
        #( SELECT Descripci_on FROM Giro g WHERE g.id = pa.Giro ) AS Giro,
        (SELECT Nombre FROM Localidad WHERE id = pa.Localidad) AS Localidad,
        ( SELECT COALESCE ( Nombre, '' ) FROM Municipio m WHERE m.id = d.Municipio ) AS Municipio,
        ( SELECT COALESCE ( Nombre, '' ) FROM EntidadFederativa e WHERE e.id = d.EntidadFederativa ) AS Estado,
        COALESCE ( CONCAT( c.Nombres, ' ', c.ApellidoPaterno, ' ', c.ApellidoMaterno ), NULL ) AS ContribuyentePadron
    FROM Padr_onAguaPotable pa
        INNER JOIN Contribuyente c ON (c.id=pa.Contribuyente)
        INNER JOIN DatosFiscales d ON (d.id=c.DatosFiscales)
    WHERE pa.id= " . $idPadron);

        #TODO Domicilio - Lote, Colonia

        if ($DatosPadron -> ContribuyentePadron == 'NULL' || empty($DatosPadron ->ContribuyentePadron) || strlen($DatosPadron ->ContribuyentePadron) <= 2)
            $contribuyente = utf8_decode($DatosPadron -> NombreORaz_onSocial);
        else
            $contribuyente = utf8_decode($DatosPadron -> ContribuyentePadron);

        $otroConcepto = false;
        if ( isset($DatosPadron ->TipoToma) ){
            if( $DatosPadron->TipoToma == 12 || $DatosPadron->TipoToma == 13 )
                $otroConcepto = true;

            $consultaToma = Funciones::ObtenValor("SELECT Concepto FROM TipoTomaAguaPotable  WHERE id = " . $DatosPadron->TipoToma, 'Concepto');
        }else
            $consultaToma = 'NULL';

        if ($consultaToma == 'NULL')
            $tipoToma = '0';
        else
            $tipoToma = utf8_decode($consultaToma);

        $folio = Funciones::ObtenValor("SELECT Cuenta FROM Padr_onAguaPotable WHERE id = " . $idPadron, "Cuenta");

        if ($estaPagado) {
            $DatosParaRecibo = Funciones::obtieneDatosLectura($idLectura);
            $mesActual = Funciones::ObtenValor("SELECT Mes FROM Padr_onDeAguaLectura WHERE id = " . $idLectura, 'Mes');
        } else
            $DatosParaRecibo = Funciones::ObtenValor("SELECT * FROM Padr_onDeAguaLectura WHERE id = " . $idLectura);

        $DatosParaRecibo=(array)$DatosParaRecibo;

        if ($tieneAnomalia || $esPapa) {
            $lecturaAnterior = $DatosParaRecibo['LecturaAnterior'];
            $lecturaActual   = $DatosParaRecibo['LecturaActual'];
            $lecturaConsumo  = $DatosParaRecibo['Consumo'];
        } else {
            $lecturaAnterior = intval($DatosParaRecibo->LecturaAnterior);
            $lecturaActual   = intval($DatosParaRecibo->LecturaActual);
            $lecturaConsumo  = $lecturaActual - $lecturaAnterior;
        }

        $consumo = "";
        if( isset( $DatosPadron->M_etodoCobro )  ){
            if ( $DatosPadron->M_etodoCobro == 1 )
                $consumo = $DatosPadron->Consumo;

            if( $DatosPadron->M_etodoCobro == 2)
                $consumo = $DatosParaRecibo->Consumo;
        }

        $mesCobro = Funciones::ObtenValor("SELECT LPAD(pl.Mes, 2, 0 ) as MesEjercicio FROM Padr_onAguaPotable pa
        INNER JOIN Padr_onDeAguaLectura pl ON (pa.id=pl.Padr_onAgua)
        INNER JOIN TipoTomaAguaPotable t ON (t.id=" . (is_null($DatosPadron->TipoToma) ? "pa" : "pl") . ".TipoToma)
        WHERE pl.id=" . $idLectura . " ORDER BY pl.A_no DESC, pl.Mes DESC LIMIT 0, 1", "MesEjercicio");

        $a_noCobro = Funciones::ObtenValor("SELECT pl.A_no as a_noEjercicio FROM Padr_onAguaPotable pa
        INNER JOIN Padr_onDeAguaLectura pl ON (pa.id=pl.Padr_onAgua)
        INNER JOIN TipoTomaAguaPotable t ON (t.id=" . (is_null($DatosPadron->TipoToma) ? "pa" : "pl") . ".TipoToma)
        WHERE pl.id=" . $idLectura . " ORDER BY pl.A_no DESC, pl.Mes DESC LIMIT 0, 1", "a_noEjercicio");


        $fechaLimite = AguaController::fechaCorte( $DatosPadron->Ruta, $mesCobro );

        $meses = ["ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE"];

        #$fechasPeriodo = Funciones::ObtenValores("SELECT Mes, A_no FROM Padr_onDeAguaLectura WHERE Padr_onAgua = " . $idPadron . " AND Status = 1 AND EstatusConvenio = ".($convenio?'1':'0')." ORDER BY A_no DESC, Mes DESC");
        $fechasPeriodo = DB::select("SELECT Mes, A_no FROM Padr_onDeAguaLectura WHERE Padr_onAgua = " . $idPadron . " AND Status = 1 AND EstatusConvenio = ".($convenio?'1':'0')." ORDER BY A_no DESC, Mes DESC");


        $periodo = "";
        if ( count($fechasPeriodo) >= 2 ) {

            $mesInicio = $fechasPeriodo[ count($fechasPeriodo) -1 ]->Mes;
            $mesFin = $fechasPeriodo[0]->Mes;

            $a_noInicio = $fechasPeriodo[ count($fechasPeriodo) -1 ]->A_no;
            $a_noFin =  $fechasPeriodo[0]->A_no;

            $periodo = $meses[$mesInicio - 1] . " $a_noInicio - " . $meses[$mesFin - 1] . " $a_noFin";

        }else{
            if( count($fechasPeriodo) == 1 ){
                $mesInicio = $fechasPeriodo[0]->Mes;
                $a_noInicio = $fechasPeriodo[0]->A_no;

                $periodo = $meses[$mesInicio - 1] . " $a_noInicio - " . $meses[$mesInicio - 1] . " $a_noInicio";

            }else{
                $fechaPagada = DB::select("SELECT Mes, A_no FROM Padr_onDeAguaLectura WHERE Padr_onAgua = " . $idPadron . " AND Status = 2 ORDER BY A_no DESC, Mes DESC LIMIT 1");

                $mesInicio = $fechaPagada[0]->Mes;
                $a_noInicio = $fechaPagada[0]->A_no;

                $periodo = $meses[$mesInicio - 1] . " $a_noInicio - " . $meses[$mesInicio - 1] . " $a_noInicio";
            }
        }

        $DatosHistoricos = DB::select("SELECT Consumo, Mes, A_no FROM Padr_onDeAguaLectura WHERE Mes = " . $mesCobro . " AND Padr_onAgua =" . $idPadron . " AND A_no < DATE_FORMAT( CURDATE(), '%Y') ORDER BY FechaLectura DESC LIMIT 3");

        $datosHistoricosTabla = '';
        foreach ($DatosHistoricos as $valor) {
            $datosHistoricosTabla .=
                '<tr>
                <td>' . $meses[$valor->Mes - 1] . '-' . $valor->A_no . '</td>
                <td class="derecha">' . intval($valor->Consumo) . ' M3</td>
            </tr>';
        }

        $descuentos  = Funciones::ObtenValor("SELECT PrivilegioDescuento FROM Padr_onAguaPotable WHERE id = " . $idPadron . " AND PrivilegioDescuento != 0", "PrivilegioDescuento");
        $esDescuento = FALSE;
        $descuento   = 0;
        $descNombre  = "";

        if ($descuentos != "NULL") {
            $esDescuento = TRUE;
            $descNombre = Funciones::tipoDescuento($descuentos);
        } else
            $esDescuento = FALSE;


        /*$Cotizaciones =Funciones::ObtenValor("SELECT GROUP_CONCAT(id) as Cotizaci_ones
        FROM
            Cotizaci_on
        WHERE
            Padr_on = " . $idPadron . "
            and ( SELECT sum( importe ) FROM ConceptoAdicionalesCotizaci_on WHERE ConceptoAdicionalesCotizaci_on.Cotizaci_on = Cotizaci_on.id AND Padre IS NULL AND EstatusConvenioC = 0 )>0 GROUP by Padr_on ORDER BY id DESC", 'Cotizaci_ones');
    */
        $Cotizaciones = Funciones::ObtenValor("
        SELECT GROUP_CONCAT(c.id) as Cotizaci_ones
        FROM Cotizaci_on c
        WHERE c.Padr_on = $idPadron
            AND ( SELECT sum( cac.importe ) FROM ConceptoAdicionalesCotizaci_on cac WHERE cac.Cotizaci_on = c.id AND cac.EstatusConvenioC = " . ($convenio ? '1' : '0') . " AND cac.Padre IS NULL ) > 0
        GROUP by c.Padr_on
        ORDER BY c.id DESC;", 'Cotizaci_ones');
        #and (SELECT e.id FROM EncabezadoContabilidad e WHERE e.Pago IS NOT NULL AND e.Cotizaci_on = Cotizaci_on.id LIMIT 1 ) is null

        $FilaConceptosTotales  = "";
        $FilaConceptosTotales2 = "";
        $FilaConceptosTotales3 = "";
        $FilaConceptosTotales4 = "";

        if( $estaPagado ) goto sinCalcular;

        if ($Cotizaciones == "NULL") {
            return "";
        }

        $DescuentoGeneralCotizaciones = 0;
        $SaldoDescontadoGeneralTodo   = 0;

        $Descuentos = AguaController::ObtenerDescuentoConceptoRecibo($Cotizaciones, $cliente);
        $SaldosActuales = AguaController::ObtenerDescuentoPorSaldoAnticipadoRecibo($Cotizaciones, $Descuentos['ImporteNetoADescontar'], $Descuentos['Conceptos'], $cliente);

        /*$ConsultaConceptos="SELECT c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad, c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario,
                (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional, co.A_no, COALESCE(co.Mes, 1) as Mes, ct.Tipo, c.TipoToma
            FROM ConceptoAdicionalesCotizaci_on co
                INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
                INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )
            WHERE  co.Cotizaci_on IN( " .$Cotizaciones. ") and Estatus=0 ORDER BY co.A_no DESC, COALESCE(co.Mes, 1) DESC ,  co.id ASC ";*/

        $ConsultaConceptos="
        SELECT
            c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad,
            c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario,
            (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional,
            co.A_no, COALESCE(co.Mes, 1) as Mes, ct.Tipo, c.TipoToma
        FROM ConceptoAdicionalesCotizaci_on co
            INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
            INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )
        WHERE co.Cotizaci_on IN( " .$Cotizaciones. ")
            AND co.Estatus = 0
            AND co.EstatusConvenioC = " . ($convenio ? '1' : '0') . "
        ORDER BY co.A_no DESC, COALESCE(co.Mes, 1) DESC, co.id ASC";

        $ResultadoConcepto  = DB::select($ConsultaConceptos);
        $ConceptosCotizados = '';
        $totalConcepto      = 0;
        $indexConcepto      = 0;


        setlocale(LC_TIME, "es_MX.UTF-8");

        $sumaSaldos          = 0;
        $sumaRecargos        = 0;
        $sumaDescuentos      = 0;
        $sumaTotalFinal      = 0;
        $sumaActualizaciones = 0;

        $RegistroConcepto = $ResultadoConcepto[0];

        $consumoMesActual = array();

        if (empty($RegistroConcepto->Adicional)) {
            $consumoMesActual["Consumo"] = str_replace(",", "", $RegistroConcepto->total);
        } else {
            $consumoMesActual[$RegistroConcepto->Adicional] = str_replace(",", "", $RegistroConcepto->total);
        }

        $sub_total = str_replace(",", "", $RegistroConcepto->total);

        if($convenio){
            $ActualizacionesYRecargosFunciones = array('Actualizaciones'=>0,'Recargos'=>0 );
            $Actualizaci_on = str_replace(",", "", $ActualizacionesYRecargosFunciones['Actualizaciones']);
            $Recargos = str_replace(",", "", $ActualizacionesYRecargosFunciones['Recargos']);
        }else{
            $ActualizacionesYRecargosFunciones = AguaController::ObtenerRecargosYActualizacionesPorConceptoRecibo($RegistroConcepto->ConceptoCobro, $Cotizaciones, $cliente);
            $Actualizaci_on = str_replace(",", "", $ActualizacionesYRecargosFunciones['Actualizaciones']);
            $Recargos = str_replace(",", "", $ActualizacionesYRecargosFunciones['Recargos']);

            $DescuentoGeneralCotizaciones += $Descuento = str_replace(",", "", $Descuentos[$RegistroConcepto->ConceptoCobro]);
            $SaldoDescontadoGeneralTodo += $saldo = str_replace(",", "",$SaldosActuales[$RegistroConcepto->ConceptoCobro]);

            $Descuento = number_format(str_replace(",", "",$Descuentos[$RegistroConcepto->ConceptoCobro]), 2);
            $sumaDescuentos=str_replace(",", "",$sumaDescuentos);
            $sumaDescuentos += str_replace(",", "",$Descuento);
            $sumaSaldos=str_replace(",", "",$sumaSaldos);
            $sumaSaldos += str_replace(",", "",$saldo);

        }

        $sub_total = ($sub_total);

        $sumaActualizaciones=str_replace(",", "",$sumaActualizaciones);
        $sumaRecargos=str_replace(",", "",$sumaRecargos);
        $sumaActualizaciones += $Actualizaci_on;
        $sumaRecargos += $Recargos;

        $subtotal = number_format(str_replace(",", "", $sub_total) , 2);
        $subtotal = str_replace(",", "", $subtotal);
        $sumaTotalFinal = str_replace(",", "",$sumaTotalFinal);
        #$sumaTotalFinal += $subtotal+$Recargos+$Actualizaci_on;
        $sumaTotalFinal += $subtotal+$Recargos;

        $totalConcepto                                  = $RegistroConcepto->total;
        $ConceptosCotizados                             = $RegistroConcepto->idConceptoCotizacion . ',';
        $ConceptoPadre[$indexConcepto]['id']            = $RegistroConcepto->idConceptoCotizacion;
        $ConceptoPadre[$indexConcepto]['Mes']           = $RegistroConcepto->Mes;
        $ConceptoPadre[$indexConcepto]['A_no']          = $RegistroConcepto->A_no;
        $ConceptoPadre[$indexConcepto]['Total']         = 0;
        $ConceptoPadre[$indexConcepto]['Nombre']        = $RegistroConcepto->NombreConcepto;
        $ConceptoPadre[$indexConcepto]['TipoPredio']    = $RegistroConcepto->Tipo;
        $ConceptoPadre[$indexConcepto]['idConcepto']    = $RegistroConcepto->ConceptoCobro;
        $ConceptoPadre[$indexConcepto]['FechaConcepto'] = $RegistroConcepto->A_no . "-" . $RegistroConcepto->Mes . "-01";

        $conceptosNombresMes     = array(); #Conceptos del mes actual
        $adicionalesNombresMes   = array(); #Adicionales del mes actual
        $conceptosNombres        = array(); #Conceptos meses de adeudo - Consumo
        $adicionalesNombres      = array(); #Adicionales nombres meses de adeudo
        $adicionalesValores      = array(); #Adicionales valores meses de adeudo
        $conceptosOtrosNombres   = array(); #Otros conceptos nombres
        $conceptosOtrosValores   = array(); #Otros conceptos valores
        $adicionalesOtrosNombres = array(); #Otros adicionales nombres
        $adicionalesOtrosValores = array(); #Otros adicionales valores
        $recargosActualizaciones = array(); # Recargos y actualizaciones

        $sumaConceptosA = 0;
        $sumaAdicionalesA = 0;

        $i=0;

        foreach ($ResultadoConcepto as $RegistroConcepto) {

            if($i!=0) {

                $sub_total = str_replace(",", "", $RegistroConcepto->total);

                if($convenio){
                    $ActualizacionesYRecargosFunciones = array('Actualizaciones'=>0,'Recargos'=>0 );
                    $Actualizaci_on = str_replace(",", "", $ActualizacionesYRecargosFunciones['Actualizaciones']);
                    $Recargos = str_replace(",", "", $ActualizacionesYRecargosFunciones['Recargos']);
                }else{
                    $ActualizacionesYRecargosFunciones = AguaController::ObtenerRecargosYActualizacionesPorConceptoRecibo($RegistroConcepto->ConceptoCobro, $Cotizaciones, $cliente);

                    $Actualizaci_on = str_replace(",", "", $ActualizacionesYRecargosFunciones['Actualizaciones']);
                    $Recargos = str_replace(",", "", $ActualizacionesYRecargosFunciones['Recargos']);

                    $DescuentoGeneralCotizaciones += $Descuento = str_replace(",", "", $Descuentos[$RegistroConcepto->ConceptoCobro]);
                    $SaldoDescontadoGeneralTodo += $saldo = str_replace(",", "",$SaldosActuales[$RegistroConcepto->ConceptoCobro]);

                    $Descuento = number_format($Descuentos[$RegistroConcepto->ConceptoCobro], 2);
                    $sumaDescuentos += $Descuento;
                    $sumaSaldos = str_replace(",", "",$sumaSaldos);
                    $sumaSaldos += str_replace(",", "",$saldo);
                }

                $sub_total = ($sub_total);
                $sub_total=str_replace(",", "", $sub_total);

                $sumaActualizaciones=str_replace(",", "",$sumaActualizaciones);
                $sumaRecargos=str_replace(",", "",$sumaRecargos);
                $sumaActualizaciones += $Actualizaci_on;
                $sumaRecargos += $Recargos;

                $subtotal = number_format(str_replace(",", "", $sub_total) , 2);
                $subtotal = str_replace(",", "", $subtotal);
                $sumaSaldos =str_replace(",", "", $sumaSaldos);
                #$sumaTotalFinal += $subtotal+$Recargos+$Actualizaci_on;
                $sumaTotalFinal += $subtotal+$Recargos;


                if (empty($RegistroConcepto->Adicional)) {
                    //Es concepto
                    $ConceptoPadre[$indexConcepto]['Total'] = $totalConcepto;

                    $totalConcepto = $RegistroConcepto->total;
                    $indexConcepto++;

                    $ConceptoPadre[$indexConcepto]['id']            = $RegistroConcepto->idConceptoCotizacion;
                    $ConceptoPadre[$indexConcepto]['Mes']           = $RegistroConcepto->Mes;
                    $ConceptoPadre[$indexConcepto]['A_no']          = $RegistroConcepto->A_no;
                    $ConceptoPadre[$indexConcepto]['Nombre']        = $RegistroConcepto->NombreConcepto;
                    $ConceptoPadre[$indexConcepto]['TipoPredio']    = $RegistroConcepto->Tipo;
                    $ConceptoPadre[$indexConcepto]['idConcepto']    = $RegistroConcepto->ConceptoCobro;
                    $ConceptoPadre[$indexConcepto]['FechaConcepto'] = $RegistroConcepto->A_no . "-" . $RegistroConcepto->Mes . "-01";

                    if (empty($RegistroConcepto->TipoToma)) {
                        $conceptosOtrosNombres[] = $RegistroConcepto->NombreConcepto;
                        $conceptosOtrosValores[] = $subtotal;
                    } else {
                        /*if ($RegistroConcepto['A_no'] == $a_noCobro && $RegistroConcepto['Mes'] == $mesCobro) {
                            $conceptosNombresMes[$RegistroConcepto['ConceptoCobro']] = $subtotal;
                        } else {*/
                        $sumaConceptosA = str_replace(",", "", $sumaConceptosA);
                        $sumaConceptosA += $subtotal; //Para el consumo de meses anteriores
                        //}
                    }
                } else {
                    //Es adicional
                    $totalConcepto += $RegistroConcepto->total;

                    if (empty($RegistroConcepto->TipoToma)) {
                        $adicionalesOtrosNombres[] = $RegistroConcepto->Adicional;
                        $adicionalesOtrosValores[] = $subtotal;
                    } else {
                        if ($RegistroConcepto->A_no == $a_noCobro && $RegistroConcepto->Mes == $mesCobro) {
                            $adicionalesNombresMes[$RegistroConcepto->Adicional] = $subtotal;
                        } else {
                            $adicionalesNombres[] = $RegistroConcepto->Adicional;
                            $adicionalesValores[] = $subtotal;
                        }
                    }
                }

                $ConceptosCotizados .= $RegistroConcepto->idConceptoCotizacion . ',';

            }
            $i++;
        }

        $SaldoDescontadoGeneralTodo=0;
        if( $SaldoDescontadoGeneralTodo == 0 ){

            if( $esDescuento ){
                $pagoMinimo = Funciones::ObtenValor("SELECT Valor FROM suinpac_general.ClienteDatos WHERE Cliente = $cliente AND Indice = 'PagoMinimo'", "Valor");
                $sumaTotalFinal = str_replace(",", "",$sumaTotalFinal) - str_replace(",", "",$sumaDescuentos);

                if($pagoMinimo != 'NULL'){
                    if( $sumaTotalFinal <= $pagoMinimo ){
                        $sumaTotalFinal = $pagoMinimo;
                    }
                }
            }
        }

        if ($sumaConceptosA > 0) {
            $conceptosNombres["Consumo"] = $sumaConceptosA;
        }

        $contar = array();
        $i = 0;

        foreach ($adicionalesNombres as $value) {
            if (isset($contar[$value])) {
                // si ya existe, le añadimos uno
                $contar[$value] = str_replace(",", "", $contar[$value]);
                $contar[$value] += str_replace(",", "", $adicionalesValores[$i]);
            } else {
                // si no existe lo añadimos al array
                $contar[$value] = str_replace(",", "", $adicionalesValores[$i]);
            }
            $i++;
        }

        $conceptosOtros = array();
        $j = 0;


        foreach ($conceptosOtrosNombres as $value) {
            $concepto = str_replace(",", "", $value);

            if( isset( $conceptosOtros[$concepto] ) )
                $conceptosOtros[ $concepto ] += str_replace(",", "",$conceptosOtrosValores[$j]);
            else
                $conceptosOtros[ $concepto ] = str_replace(",", "",$conceptosOtrosValores[$j]);

            $j++;
        }

        $adicionalesOtros = array();
        $k = 0;

        foreach ($adicionalesOtrosNombres as $value) {
            $adicional = str_replace(",", "", $value);
            $adicional = str_replace("%", "", $adicional);

            if ( isset($adicionalesOtros[$adicional]) )
                $adicionalesOtros[$adicional] += str_replace( ",", "", $adicionalesOtrosValores[$k] );
            else
                $adicionalesOtros[$adicional] = str_replace( ",", "", $adicionalesOtrosValores[$k] );

            $k++;
        }

        $ConceptoPadre[$indexConcepto]['Total'] = $totalConcepto;
        $ConceptosCotizados = substr_replace($ConceptosCotizados, '', -1);

        //uno los arrays
        $array_mes    = array_merge($consumoMesActual, $adicionalesNombresMes);
        $array_otros  = array_merge($conceptosOtros, $adicionalesOtros);
        $array_rezago = array_merge($conceptosNombres, $contar);

        //dd($array_rezago);

        $totalMes       = 0;
        $totalOtros     = 0;
        $totalRezago    = 0;
        $totalFinal     = $sumaTotalFinal;
        #$totalesFinales = $sumaRecargos;
        #$totalesFinales = $sumaActualizaciones + $sumaRecargos;

        if( $convenio ){
            $ActYRec = Funciones::ObtenValor("SELECT Actualizaciones, Recargos, Descuentos FROM Padr_onConvenio WHERE idPadron = $idPadron AND Estatus = 1");
            //TODO: Quitar Actualizaciones...
            $totalesFinales = $ActYRec->Actualizaciones + $ActYRec->Recargos;
            $totalFinal     = ($sumaTotalFinal + $totalesFinales) - str_replace(",", "", number_format($ActYRec->Descuentos, 2) );
        }else{
            $totalesFinales = $sumaRecargos;
        }


        if (empty($array_rezago)) {
            foreach ($array_mes as $key => $value) {
                $FilaConceptosTotales .= '
        <tr>
            <td>' . AguaController::conceptoAbreviado( utf8_decode($key), $otroConcepto ) . '</td>
        </tr>';
                $FilaConceptosTotales3 .= '
        <tr>
            <td class="derecha">' . (number_format($value, 2)) . '</td>
        </tr>';
                $totalMes    += str_replace(",", "", $value);
            }
        } else {
            foreach ($array_mes as $key => $value) {
                if( isset( $array_rezago[$key] ) ){
                    $FilaConceptosTotales .= '
                    <tr>
                        <td>' . AguaController::conceptoAbreviado( utf8_decode($key), $otroConcepto ) . '</td>
                    </tr>';
                    $FilaConceptosTotales3 .= '
                    <tr>
                        <td class="derecha">' . (number_format(str_replace(",", "", $value), 2)) . '</td>
                    </tr>';
                    $FilaConceptosTotales2 .= '
                    <tr>
                        <td>' . AguaController::conceptoAbreviado( utf8_decode($key), $otroConcepto ) . '</td>
                    </tr>';
                    $FilaConceptosTotales4 .= '
                    <tr>
                        <td class="derecha">' . number_format( str_replace(",", "", $array_rezago[$key]), 2)  . '</td>
                    </tr>';

                    $totalMes     = str_replace(",", "", $totalMes);
                    $totalMes    += str_replace(",", "", $value);
                    $totalRezago  = str_replace(",", "", $totalRezago);
                    $totalRezago += str_replace(",", "", $array_rezago[$key]);
                }else{
                    $FilaConceptosTotales .= '
                    <tr>
                        <td>' . AguaController::conceptoAbreviado( utf8_decode($key), $otroConcepto ) . '</td>
                    </tr>';
                    $FilaConceptosTotales3 .= '
                    <tr>
                        <td class="derecha">' . (number_format(str_replace(",", "", $value), 2)) . '</td>
                    </tr>';

                    $totalMes     = str_replace(",", "", $totalMes);
                    $totalMes    += str_replace(",", "", $value);
                }
            }
        }

        if (!empty($array_otros)) {
            foreach ($array_otros as $key => $value) {

                $concepto = conceptoAbreviado( utf8_decode ($key), $otroConcepto );

                $FilaConceptosTotales .= '<tr>
            <td>' . (substr($concepto, 0, 44)) . '</td>
            <td class="derecha"></td>
            <td class="derecha"></td>
            <td class="derecha">' . (number_format($value, 2)) . '</td>
        </tr>';
                $totalOtros = str_replace(",", "", $totalOtros);
                $totalOtros +=  str_replace(",", "", $value);
            }
        }

        sinCalcular:

        $fechaActual = date("Y-m-d");
        $auxMes = array("", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");

        if ($estaPagado) {
            $totalFinal = str_replace(",", "", $DatosParaRecibo['AdeudoCompleto']);
            $FilaConceptosTotales = '<tr>
            <td>Adeudo Completo</td>
        </tr>';
            $FilaConceptosTotales3 = '<tr>
            <td class="derecha">' . (number_format(str_replace(",", "", $totalFinal), 2)) . '</td>
        </tr>';

            $totalRezago = 0;
            $totalMes = 0;

            goto finCalculos;
        }

        $saldo = Funciones::ObtenValor("SELECT SaldoNuevo FROM Padr_onAguaHistoricoAbono WHERE idPadron = " . $idPadron . " ORDER BY id DESC ", "SaldoNuevo");
        $saldoNuevo = 0;
        if($saldo != "NULL"){
            //Si el saldo es menor a lo que se debe
            if($saldo < $sumaSaldos){
                $saldoNuevo = 0;
            }
            #Si el saldo es mayor a lo se se debe
            if($saldo > $totalFinal){
                $saldoNuevo = $saldo - $sumaSaldos;
            }
        }

        $decimales = 0;
        if (is_float($totalFinal) && $totalFinal > 0) {
            #En caso de que el total sea decimal - Se toma el numero despues del punto
            $exp = explode(".", $totalFinal);
            #Se asigna el numero tomado
            if(isset($exp[1]))
                $decimales = "0." . $exp[1];
            else
                $decimales = "0";
        }

        $estaAjustado = FALSE;
        $ajuste = 0;
        $ajusteFinal = 0;

        if ( is_float( $totalFinal ) && $totalFinal > 0 ){
            $ajuste = $decimales;
            $ajusteFinal = intval($totalFinal);
            $estaAjustado = TRUE;
        }else{
            $ajusteFinal = str_replace(",", "",$totalFinal);
        }

        $totalFinal = intval( $totalFinal );

        if ($totalesFinales > 0) {
            $totalRezago += str_replace(",", "", $totalesFinales);
            $FilaConceptosTotales .= '<tr>
                    <td>Recargos</td>
                </tr>';
            $FilaConceptosTotales3 .= '<tr>
                    <td class="derecha">
                        ' . number_format($totalesFinales, 2) . '
                    </td>
                </tr>';
        }

        if ($estaAjustado) {
            $FilaConceptosTotales .= '<tr>
                    <td>Redondeo</td>
                </tr>';
            $FilaConceptosTotales3 .= '<tr>
                    <td class="derecha">
                        ' . number_format($ajuste, 2)  . '
                    </td>
                </tr>';
        }

        $totalFinal = str_replace(",", "", $ajusteFinal);

        finCalculos:

        if( $estaPagado )
            $totalFinal = 0;

        //cantidad con letra y total
        $letras = utf8_decode(Funciones::num2letras($totalFinal, 0, 0) . " pesos");
        $ultimoArr = explode(".", number_format($totalFinal, 2)); //recupero lo que este despues del decimal
        $ultimo = $ultimoArr[1];
        if ($ultimo == "")
            $ultimo = "00";
        $letras = $letras . " " . $ultimo . "/100 M. N.";

        $nombreComercial = $DatosPadron->NombreORaz_onSocial;
        if( strlen($nombreComercial) > 0 && strlen($nombreComercial) > 55 ){
            $nombreComercial = substr($nombreComercial, 0, strlen($nombreComercial) / 2) . '<br>' . substr($nombreComercial, strlen($nombreComercial) / 2, strlen($nombreComercial) );
        }

        $giro = '';

        if($DatosPadron->Giro != '') {
            $giro = Funciones::ObtenValor("SELECT Descripci_on AS Nombre FROM Giro WHERE id = " . $DatosPadron->Giro, "Nombre");

            if ( $giro != 'NULL' ) {
                $giro = $DatosPadron->Giro . ': ' . $giro;

                $longitud = strlen($giro);

                if ( $longitud >= 100 ) {
                    $giro = substr($giro, 0, 95);
                }
            } else {
                $giro = "";
            }
        }

        $DIR_IMG = 'imagenes/';
        //<img height="100" width="300" src="'.asset(.'">

        $rutaLogo=$DatosCliente->Logo;

        $rutaBarcode = 'https://suinpac.com/lib/barcode2.php?f=png&text=' . (isset($DatosPadron->ContratoVigente) ? $DatosPadron->ContratoVigente : '') . '&codetype=Code128&orientation=Horizontal&size=40&print=false alt="Logo del cliente""';

        $htmlGlobal = '<div class="contenedor">

            <!--
            ╔╦╗╦╔╦╗╦ ╦╦  ╔═╗╔═╗
             ║ ║ ║ ║ ║║  ║ ║╚═╗
             ╩ ╩ ╩ ╚═╝╩═╝╚═╝╚═╝
            -->
            <table WIDTH="100%" HEIGHT="103px" cellspacing="0">
                <tbody>
                <tr>
                    <td style="width:10%; class="centrado">
                    <img height="50px" src="'.asset($DatosCliente->Logo).'">
                    </td>
                    <td style="width:90%;" align="center">
                    <table WIDTH="95%" cellpadding="0" cellspacing="0" border="0" style="border: 0px;">
                            <tbody>
                                    <tr>
                                        <td class="negritas">"COMISI&OacuteN DE AGUA POTABLE Y ALCANTARILLADO"</td>
                                    </tr>
                                    <tr>
                                        <td align="center" class="negritas">"DE CHILPANCINGO DE LOS BRAVO."</td>
                                    </tr>
                            </tbody>
                    </table>
                    <table WIDTH="80%" cellpadding="0" cellspacing="0" border="0" style="border: 0px; margin-top: 10px; margin-left: 30px">
                                <tbody>

                                    <tr style="margin-top: 15px">
                                        <td class="direccion" >Calle 16 de Septiembre No. 34</td>
                                    </tr>
                                    <tr>
                                        <td class="direccion">Barrio de San Mateo, C.P. 39022</td>
                                    </tr>
                                    <tr>
                                          <td class="direccion">Chilpancingo de los Bravo, Guerrero</td>
                                    </tr>
                                    <tr>
                                          <td class="negritas">R.F.C. CAP -970301 -AJA</td>
                                    </tr>


                                </tbody>
                    </table>

                    </td>
                </tr>
                </tbody>
            </table>
            <!--
            ╔╗ ╦  ╔═╗╔═╗ ╦ ╦╔═╗  ╦ ╦╔╗╔╔═╗
            ╠╩╗║  ║ ║║═╬╗║ ║║╣   ║ ║║║║║ ║
            ╚═╝╩═╝╚═╝╚═╝╚╚═╝╚═╝  ╚═╝╝╚╝╚═╝
            -->
            <table WIDTH="100%" HEIGHT="10px" cellspacing="0" border="0">
                <tbody>
                 <tr>
                    <td WIDTH="5%" align="center" valign="middle"></td>
                    <td WIDTH="75%"></td>
                    <td WIDTH="20%" align="center"><b style="font-size: 6px;">'.$tipoRecibo.'</b></td>
                </tr>

                </tbody>
            </table>
            <table WIDTH="100%" HEIGHT="90px" cellspacing="0" border="1" bordercolor="#1340C1">
                <tbody>

                <tr>
                    <td WIDTH="5%" align="center" valign="middle" style="background-color: #9CBDF3">
                            <table align="center" WIDTH="90%" cellspacing="0" border="0" style="border: 0px;">

                                <tr style="hidden">

                                </tr>
                                <tr>
                                        <td class="usuario" align="center"><b>U</b></td>
                                </tr>
                                  <tr>
                                        <td class="usuario" align="center"><b>S</b></td>
                                </tr>
                                <tr>
                                        <td class="usuario" align="center"><b>U</b></td>
                                </tr>
                                <tr>
                                        <td class="usuario" align="center"><b>A</b></td>
                                </tr>
                                <tr>
                                        <td class="usuario" align="center"><b>R</b></td>
                                </tr>
                                <tr>
                                        <td class="usuario" align="center"><b>I</b></td>
                                </tr>
                                <tr>
                                        <td class="usuario" align="center"><b>O</b></td>
                                </tr>

                            </table>
                    </td>
                    <td WIDTH="75%" class="mayusculas" valign="top">
                        <div style="overflow:hidden; white-space:nowrap; text-overflow: ellipsis; margin-top: 10px">
                            <table WIDTH="100%" cellspacing="0" border="0" style="border: 0px;">
                                <tbody>
                                    <tr>
                                        <td>'.$contribuyente.'</td>
                                    </tr>
                                    <tr>
                                        <td>'.utf8_decode($DatosPadron->Domicilio) .' '. (isset($DatosPadron->C_odigoPostal_c)? 'C.P. '.$DatosPadron->C_odigoPostal_c:'') . '</td>
                                    </tr>
                                    <tr>
                                        <td>'.utf8_decode($DatosPadron->Localidad).'</td>
                                    </tr>
                                    '.(isset($DatosPadron->RFC)?'<tr><td>RFC: '.$DatosPadron->RFC.'</td></tr>':'').'
                                </tbody>
                            </table>
                        <div>
                    </td>
                    <td WIDTH="20%" align="center"  valign="top" class=" mayusculas">
                        <b style="font-size: 6px;">TIPO DE SERV</b><br><br>'.$tipoToma.'<br>'.intval($consumo).'  M3
                    </td>
                </tr>
                </tbody>
            </table>
            <!--
            ╔╗ ╦  ╔═╗╔═╗ ╦ ╦╔═╗  ╔╦╗╔═╗╔═╗
            ╠╩╗║  ║ ║║═╬╗║ ║║╣    ║║║ ║╚═╗
            ╚═╝╩═╝╚═╝╚═╝╚╚═╝╚═╝  ═╩╝╚═╝╚═╝
            -->
            <table WIDTH="100%" HEIGHT="30px" cellspacing="0" class="" border="1" bordercolor="#1340C1" style="margin-top: 5px">
                <tbody>

                    <tr>
                    <tr>
                        <td class="centrado" valign="middle" style="width: 80px; height: 30px;"><b style="font-size: 6px;">NUMERO DE CUENTA</b><br></br>'.$DatosPadron->Cuenta.'</td>
                        <td class="centrado" valign="middle" style="width: 60px;"><b style="font-size: 6px;">CONTRATO</b><br></br>'.$DatosPadron->ContratoVigente.'</b></td>
                        <td class="centrado" valign="middle" style="width: 60px;"><b style="font-size: 6px;">SUBSIDIO</b><br></br>'.$descNombre.'</td>
                        <td class="centrado" valign="middle" style="width: 50px;"><b style="font-size: 6px;">MESES DE ADEUDO</b><br></br>'.$adeudos.'</td>
                    </tr>

                        <td valign="middle" style="height: 25px;" class="centrado "><b style="font-size: 6px;">LECTURA ANTERIOR</b><br></br>'.intval($lecturaAnterior).'</td>
                        <td valign="middle" class="centrado "><b style="font-size: 6px;">LECTURA ACTUAL</b><br></br>'.intval($lecturaActual).'</td>
                        <td valign="middle" class="centrado " colspan="5" style="font-size: 8px;"><b style="font-size: 6px;">PERIODO</b><br></br>'.$periodo.'</td>
                    </tr>
                </tbody>
            </table>
            <!--
            ╔╗ ╦  ╔═╗╔═╗ ╦ ╦╔═╗  ╔╦╗╦═╗╔═╗╔═╗
            ╠╩╗║  ║ ║║═╬╗║ ║║╣    ║ ╠╦╝║╣ ╚═╗
            ╚═╝╩═╝╚═╝╚═╝╚╚═╝╚═╝   ╩ ╩╚═╚═╝╚═╝
            -->
            <table WIDTH="100%" HEIGHT="105px" cellspacing="0" class="" border="1" bordercolor="#1340C1"  style="margin-top: -2px">
                <tbody>
                    <tr>
                        <td valign="middle" class="centrado" colspan="2"><b style="font-size: 6px;">GIRO</b><br></br>'.utf8_decode($giro).'</td>
                        <td valign="middle" class="centrado" ><b style="font-size: 6px;">FECHA LIMITE DE PAGO</b><br></br>'.$fechaLimite.'</td>
                    </tr>
                    <tr>
                        <td style="width: 40%; background-color: #9CBDF3" class="centrado "><b style="font-size: 7px;">CONCEPTO</b></td>
                        <td style="width: 28%; background-color: #9CBDF3" class="centrado "><b style="font-size: 7px;">IMPORTE</b></td>
                        <td style="width: 26%;" class="centrado "><b style="font-size: 7px;">AVISO</b></td>
                    </tr>
                    <tr>
                        <td style="height: 210px;" valign="middle" >
                            <div style="height:210px; overflow:hidden; white-space:nowrap; text-overflow: ellipsis;">
                                <table WIDTH="100%" style="border-collapse: collapse;">
                                    <tbody>
                                        <tr>
                                            <td><br></td>
                                        </tr>
                                        <tr>
                                            <td><b>Adeudo Anterior</b></td>
                                        </tr>
                                        '.$FilaConceptosTotales2.'
                                    </tbody>
                                </table>
                                <table WIDTH="100%" style="border-collapse: collapse;">
                                    <tbody>
                                        <tr>
                                            <td><b>Mes Actual</b></td>
                                        </tr>
                                        '.$FilaConceptosTotales.'
                                    </tbody>
                                </table>
                            </div>
                        </td>
                        <td class="centrado" valign="middle">
                            <div style="height:210px; overflow:hidden; white-space:nowrap; text-overflow: ellipsis;">
                                <table WIDTH="100%" style="border-collapse: collapse;" >
                                    <tbody>
                                        <tr>
                                            <td><br></td>
                                        </tr>
                                        <tr>
                                            <td><br></td>
                                        </tr>
                                        '.$FilaConceptosTotales4.'
                                    </tbody>
                                </table>
                                <table WIDTH="100%" style="border-collapse: collapse;">
                                    <tbody>
                                        <tr>
                                            <td><br></td>
                                        </tr>
                                        '.$FilaConceptosTotales3.'
                                    </tbody>
                                </table>
                            </div>
                        </td>
                        <td style="width: 160px;" align="right" valign="end">

                            <div class="sobre">
                                <table WIDTH="110px" HEIGHT="150px" border="0" class="">
                                    <tr>
                                        <td align="center" valign="middle" style="font-size: 10px;">
                                            '.($leyenda ? 'ESTIMADO USUARIO: <br> Si usted tiene adeudos vencidos lo invitamos a ponerse al corriente en sus pagos. Visitenos en CAPACH y obtenga importantes beneficios.' : '').'
                                        </td>
                                    </tr>
                                </table>
                            </dv>

                             <img class="barra" width="180" height="30"
                                src="'.$rutaBarcode.'">

                            <!--<div style="width: 160px; white-space:nowrap;">-->
                                '.($convenio
                ? '<div style="width: 250px;
                                            z-index: 1;
                                            top: -55px;
                                            left: -55px;
                                            position: absolute;
                                            align-items: center;
                                            justify-content: center;
                                        ">
                                            <table WIDTH="100%" border="2" class="bordeRojo">
                                                <tr>
                                                    <td align="center">
                                                        <img width="100px" height="60px" src="'. asset($DatosCliente->Logo).'" >
                                                    </td>
                                                    <td WIDTH="150px" align="center" valign="middle" style="color: red; font-size: 23px; font-weight: bold;">Convenio</td>
                                                </tr>
                                            </table>
                                        </dv>'
                :'')
            .($corteSA?'
                                <div class="marcaCSA" style="width: 250px;">
                                    <table WIDTH="100%" border="2" class="bordeRojo">
                                        <tr>
                                            <td align="center" valign="middle"><img width="90px" height="60px" src="'. asset($DatosCliente->Logo).'" alt="Logo"></td>
                                            <td WIDTH="150px" align="center" valign="middle" class="CSA">CORTE DE <br> SERVICIO DE <br> AGUA</td>
                                        </tr>
                                    </table>
                                </dv>
                                ':''
            )
            .($corteSAD?'
                                <div class="marcaCSAD" style="width: 250px;">
                                    <table WIDTH="100%" border="2" class="bordeRojo">
                                        <tr>
                                            <td align="center" valign="middle"><img width="90px" height="60px" src="'. asset($DatosCliente->Logo).'" alt="Logo"></td>
                                            <td WIDTH="150px" align="center" valign="middle" class="CSAD">CORTE DE <br> SERVICIO DE <br> AGUA Y DRENAJE</td>
                                        </tr>
                                    </table>
                                </dv>
                                ':''
            ).'
                            <!--</div>-->
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 36%; background-color: #9CBDF3" class="centrado"><b style="font-size: 8px;" style="text-align: left">TOTAL A <br></br>PAGAR</b></td>
                        <td style="width: 32%;" class="derecha"><b style="font-size: 8.5px; text-align: right">$ '.number_format( $totalFinal, 2 ).'&nbsp;</b></td>
                        <td style="width: 26%;" class="izquierda"><b style="font-size: 8px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;F.E.: ' .date('Y-m-d').'</b></td>
                    </tr>

                </tbody>
            </table>
        </div>';


        if ($tipo == 1) {
            return $htmlGlobal;
        } else {
           include( app_path() . '/Libs/Wkhtmltopdf.php' );
            try {
                $nombre = uniqid('', false) . "_" . $idLectura;
                #$wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'page_width'=>120,'page_height'=>200,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
                $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'margins' => ['top' => 32, 'left' => 45]));

                // $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'margins' => ['top' => 10, 'left' => 10,'right'=>10,'bottom'=>10]));
                $wkhtmltopdf->setHtml($htmlGlobal);
                //$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, $nombre.".pdf");
                $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $nombre . ".pdf");
                return "repositorio/temporal/" . $nombre . ".pdf";

            } catch (Exception $e) {
                echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
            }
        }

    }

    function fechaCorte( $sector, $mesCobro ){
        #return $sector;
        $fecha = '';

        if( $sector == 1 || $sector == 2 || $sector == 3 || $sector == 4) {
            if ($mesCobro == 10){
                $fecha = '30/' . intval($mesCobro + 1) . '/' . intval(date('Y'));
            }else{
                $fecha = AguaController::mesCorte($mesCobro, '08');
                #$fecha = '08/' . intval($mesCobro + 1) . '/' . date('Y');
            }
        }elseif($sector == 7 || $sector == 8 || $sector == 13 || $sector == 14 || $sector == 15 || $sector == 16 || $sector == 20) {
            if ($mesCobro == 10){
                $fecha = '30/' . intval($mesCobro + 1) . '/' . intval(date('Y'));
            }else{
                #$fecha = '13/' . intval($mesCobro + 1) . '/' . date('Y');
                $fecha = AguaController::mesCorte($mesCobro, '13');
            }
        }elseif( $sector == 5 || $sector == 6 ){
            if($mesCobro == 10)
                $fecha = '27/'. intval($mesCobro + 1) .'/' . intval( date('Y') );
            else{
                #$fecha = "23/11/2020";
                $fecha = AguaController::mesCorte($mesCobro, '23');
            }
        }elseif($sector == 9 || $sector == 10 || $sector == 11 || $sector == 12 || $sector == 17 || $sector == 19 || $sector == 21 || $sector == 22 || $sector == 23 || $sector == 24 || $sector == 25 || $sector == 26){
            #$fecha = "27/11/2020";
            $fecha = AguaController::mesCorte($mesCobro, '27');
        }else{
            $fecha = AguaController::mesCorte($mesCobro, '15');
        }

        return $fecha;
    }

    function mesCorte( $mesCobro, $diaLimite ){
        $fechaCorte = '';

        if( $mesCobro == 12 ){
            if( date('m') == 11 ){
                $fechaCorte = '30/'.  date('m')  .'/' . date('Y');
            }elseif( date('m') == 12 ){
                $fechaCorte = $diaLimite . '/01/' . (date('Y')+1);
            }elseif( date('m') == 1){
                $fechaCorte = $diaLimite . '/01/' . date('Y');
            }else{
                $fechaCorte = $diaLimite .'/'. date('m') .'/'. date('Y');
            }
        }else{
            $fechaCorte = $diaLimite .'/'. intval($mesCobro + 1) .'/'. date('Y');
        }

        return $fechaCorte;
    }
    function GenerarReciboIndividual($idPadron, $cliente, $tipo = 0){
        $usuario='usuarioAPISUINPAC';
        $url = 'https://suinpac.com/ReciboAguaPotableAPI.php';
        #$url = 'https://suinpac.com/ReciboAguaPotableAPI.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "idPadron"=>$idPadron,
                "Usuario"=>$usuario,
            ]

        );

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($dataForPost),
            )
        );

        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return response()->json([
            'success' => '1',
            'ruta' => $result,
            'rutaCompleta' => "https://suinpac.com/".$result,
        ]);
    }
    function GenerarReciboOficialCapazIndividual($idPadron, $cliente, $tipo = 0){ //>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>

        $diaLimite = 16;
        #$idLectura = Funciones::ObtenValor("SELECT MAX(id) as id FROM Padr_onDeAguaLectura WHERE Padr_onAgua =" . $idPadron . " AND `Status` = 1", "id");
        $idLectura = Funciones::ObtenValor("SELECT id FROM Padr_onDeAguaLectura WHERE Padr_onAgua = " . $idPadron . " AND `Status` = 1 and EstatusConvenio=0 ORDER BY A_no DESC, Mes DESC LiMIT 0, 1", "id");

        $estaPagado = FALSE;
        $tieneServicio = FALSE;
        if ($idLectura == 'NULL' || $idLectura == '') {
            $consultaServicios = "SELECT Cotizaci_on.id,
                    COALESCE( 
                        (SELECT ec.N_umeroP_oliza FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=Cotizaci_on.id AND ec.Pago IS NOT NULL LIMIT 1), 
                        (SELECT ec.N_umeroP_oliza FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN EncabezadoContabilidad ec ON(ec.Pago=cac.Pago) WHERE cac.Cotizaci_on=Cotizaci_on.id LIMIT 1) 
                    ) as Pago
                FROM Cotizaci_on
                WHERE Padr_on = $idPadron AND Tipo = 16
                HAVING Pago IS NULL ORDER BY Cotizaci_on.id DESC";

            $valor = Funciones::ObtenValor($consultaServicios, "id");
            if ($valor == 'NULL') {
                $estaPagado = TRUE;
            } else {
                $tieneServicio = TRUE;
            }
            $idLectura = Funciones::ObtenValor("SELECT MAX(id) as id FROM Padr_onDeAguaLectura WHERE Padr_onAgua =" . $idPadron . " AND `Status` = 2", "id");
        }

        $adeudos = "";
        $adeudo = Funciones::ObtenValor("SELECT COUNT(id) as adeudo FROM Padr_onDeAguaLectura WHERE Padr_onAgua = " . $idPadron . " AND `Status` = 1", "adeudo");
        if (isset($adeudo)) {
            $adeudo = (int)($adeudo);
            if ($adeudo >= 2) {
                $adeudo = $adeudo - 1;
                $adeudos = "Meses adeudo: " . $adeudo;
            }
        }

        $cuentasPapas = Funciones::ObtenValor("SELECT GROUP_CONCAT(p.id) as CuentasPapas FROM Padr_onAguaPotable p WHERE p.id=(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1) and p.Cliente=" . $cliente, 'CuentasPapas');
        $arrayPapas = explode(",", $cuentasPapas);

        $tam = count($arrayPapas);
        $esPapa = FALSE;

        //Recorro todos los papas
        for ($i = 0; $i < $tam; $i++) {
            if ($idPadron == $arrayPapas[$i]) {
                $esPapa = TRUE;
                break;
            }
        }

        $anomalia = Funciones::ObtenValor("SELECT Observaci_on FROM Padr_onDeAguaLectura WHERE id = " . $idLectura, "Observaci_on");
        #$anomalia =Funciones::ObtenValor("SELECT pac.descripci_on FROM Padr_onDeAguaLectura pal INNER JOIN Padr_onAguaCatalogoAnomalia pac ON ( pal.Observaci_on = pac.id AND pac.id = 41 ) WHERE pal.id=" . $idLectura, "descripci_on");
        $tieneAnomalia = TRUE;

        if ($anomalia == 'NULL') {
            $anomalia = '';
            $tieneAnomalia = FALSE;
        }

        $DatosPadron = Funciones::ObtenValor("SELECT
        d.RFC,
        pa.Ruta,
        pa.Lote,
        pa.Cuenta,
        pa.Sector,
        pa.Manzana,
        pa.Colonia,
        pa.Medidor,
        pa.Diametro,
        pa.TipoToma,
        pa.Domicilio,
        pa.SuperManzana,
        pa.ContratoVigente,
        d.NombreORaz_onSocial,
        c.id AS idContribuyente,
        COALESCE ( c.NombreComercial, NULL ) AS NombreComercialPadron,	
        ( SELECT Descripci_on FROM Giro g WHERE g.id = pa.Giro ) AS Giro,
        ( SELECT COALESCE ( Nombre, '' ) FROM Municipio m WHERE m.id = d.Municipio ) AS Municipio,
        ( SELECT COALESCE ( Nombre, '' ) FROM EntidadFederativa e WHERE e.id = d.EntidadFederativa ) AS Estado,
        #COALESCE ( CONCAT( c.Nombres, ' ', c.ApellidoPaterno, ' ', c.ApellidoMaterno ), NULL ) AS ContribuyentePadron,
        #IF(c.TipoPersona=1,CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno),d.NombreORaz_onSocial) AS ContribuyentePadron
        IF(c.PersonalidadJur_idica=1,CONCAT_WS(' ',c.Nombres,c.ApellidoPaterno,c.ApellidoMaterno), c.NombreComercial) AS ContribuyentePadron
    FROM Padr_onAguaPotable pa
        INNER JOIN Contribuyente c ON (c.id=pa.Contribuyente)
        INNER JOIN DatosFiscales d ON (d.id=c.DatosFiscales)
    WHERE pa.id= " . $idPadron);

        $contribuyente = utf8_decode($DatosPadron->ContribuyentePadron);

        if (isset($DatosPadron->TipoToma))
            $consultaToma = Funciones::ObtenValor("SELECT Concepto FROM TipoTomaAguaPotable  WHERE id = " . $DatosPadron->TipoToma, 'Concepto');
        else
            $consultaToma = 'NULL';

        if ($consultaToma == 'NULL')
            $tipoToma = '0';
        else
            $tipoToma = utf8_decode($consultaToma);

        $folio = Funciones::ObtenValor("SELECT Cuenta FROM Padr_onAguaPotable WHERE id = " . $idPadron, "Cuenta");

        if ($estaPagado) {
            $DatosParaRecibo = Funciones::obtieneDatosLectura($idLectura);
            $mesActual = Funciones::ObtenValor("SELECT Mes FROM Padr_onDeAguaLectura WHERE id = " . $idLectura, 'Mes');
        } else
            $DatosParaRecibo = Funciones::ObtenValor("SELECT * FROM Padr_onDeAguaLectura WHERE id = " . $idLectura);

        if ($tieneAnomalia || $esPapa) {
            if($anomalia == 41)
                $descAnomalia = Funciones::ObtenValor("SELECT descripci_on FROM Padr_onAguaCatalogoAnomalia WHERE id=" . $anomalia, "descripci_on");
            else
                $descAnomalia = '';

            $anomalia = $descAnomalia;
            $lecturaAnterior = $DatosParaRecibo->LecturaAnterior;
            $lecturaActual   = $DatosParaRecibo->LecturaActual;
            $lecturaConsumo  = $DatosParaRecibo->Consumo;
        } else {
            $lecturaAnterior = intval($DatosParaRecibo->LecturaAnterior);
            $lecturaActual   = intval($DatosParaRecibo->LecturaActual);
            $lecturaConsumo  = $lecturaActual - $lecturaAnterior;
        }


        $mesCobro = Funciones::ObtenValor("SELECT LPAD(pl.Mes, 2, 0 ) as MesEjercicio FROM Padr_onAguaPotable pa
            INNER JOIN Padr_onDeAguaLectura pl ON (pa.id=pl.Padr_onAgua)
            INNER JOIN TipoTomaAguaPotable t ON (t.id=" . (is_null($DatosPadron->TipoToma) ? "pa" : "pl") . ".TipoToma)
            WHERE pl.id=" . $idLectura . " ORDER BY pl.A_no DESC, pl.Mes DESC LIMIT 0, 1", "MesEjercicio");

        $a_noCobro = Funciones::ObtenValor("SELECT pl.A_no as a_noEjercicio FROM Padr_onAguaPotable pa
            INNER JOIN Padr_onDeAguaLectura pl ON (pa.id=pl.Padr_onAgua)
            INNER JOIN TipoTomaAguaPotable t ON (t.id=" . (is_null($DatosPadron->TipoToma) ? "pa" : "pl") . ".TipoToma)
            WHERE pl.id=" . $idLectura . " ORDER BY pl.A_no DESC, pl.Mes DESC LIMIT 0, 1", "a_noEjercicio");


        #precode($mesCobro,1,1);
        if( isset($DatosPadron->TipoToma) && $DatosPadron->TipoToma == 4 ){
            $diaLimite = 16;
            if( $mesCobro == 12){
                if( date('m') == 12 ){
                    $fechaLimite = $diaLimite . '/' . intval(1) . '/' . ( date('Y') + 1 );
                    $fechaCorte = $diaLimite + 1 . '/' . intval(1) . '/' . ( date('Y') + 1 );
                }elseif( date('m') == 1){
                    $fechaLimite = $diaLimite . '/' . intval(1) . '/' . date('Y');
                    $fechaCorte = $diaLimite + 1 . '/' . intval(1) . '/' .date('Y');
                }
            }else{
                $fechaLimite = $diaLimite . '/' . intval($mesCobro + 1) . '/' . date('Y');
                $fechaCorte = $diaLimite + 1 . '/' . intval($mesCobro + 1) . '/' . date('Y');
            }
        }else{
            if( $mesCobro == 12){
                if( date('m') == 12 ){
                    $fechaLimite = $diaLimite . '/' . intval(1) . '/' . ( date('Y') + 1 );
                    $fechaCorte = $diaLimite + 1 . '/' . intval(1) . '/' . ( date('Y') + 1 );
                }elseif( date('m') == 1){
                    $fechaLimite = $diaLimite . '/' . intval(1) . '/' . date('Y');
                    $fechaCorte = $diaLimite + 1 . '/' . intval(1) . '/' .date('Y');
                }
            }else{
                $fechaLimite = $diaLimite . '/' . intval($mesCobro + 1) . '/' . date('Y');
                $fechaCorte = $diaLimite + 1 . '/' . intval($mesCobro + 1) . '/' . date('Y');
            }
        }

        $meses = ["ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE"];

        if( intval($mesCobro) == 1 ){
            $fechasPeriodo = DB::select("SELECT FechaLectura FROM Padr_onDeAguaLectura WHERE Padr_onAgua = " . $idPadron . " AND Mes IN (1, 12) AND A_no IN (" . ($a_noCobro - 1) . ", " . $a_noCobro . ") ORDER BY id DESC LIMIT 2");
        }else{
            $fechasPeriodo = DB::select("SELECT FechaLectura FROM Padr_onDeAguaLectura WHERE Padr_onAgua = " . $idPadron . " AND Mes <= " . $mesCobro . " AND A_no = " . $a_noCobro . " ORDER BY id DESC LIMIT 2");
        }
        #$fechasPeriodo = ObtenValores("SELECT FechaLectura FROM Padr_onDeAguaLectura WHERE Padr_onAgua = " . $idPadron . " AND Mes <= " . $mesCobro . " AND A_no = " . $a_noCobro . " ORDER BY id DESC LIMIT 2");

        if (count($fechasPeriodo) == 2) {
            $periodo = date_format(new DateTime($fechasPeriodo[1]->FechaLectura), 'd/m/Y') . " a " . date_format(new DateTime($fechasPeriodo[0]->FechaLectura), 'd/m/Y');
        }
        else{
            if( count($fechasPeriodo) == 1)
                $periodo = $meses[ intval($mesCobro) - 1 ] . ' ' . $a_noCobro;
            else
                $periodo = "";
        }

        $DatosHistoricos = DB::select("SELECT Consumo, Mes, A_no FROM Padr_onDeAguaLectura WHERE Mes = " . $mesCobro . " AND Padr_onAgua =" . $idPadron . " AND A_no < DATE_FORMAT( CURDATE(), '%Y') ORDER BY FechaLectura DESC LIMIT 3");

        $datosHistoricosTabla = '';
        foreach ($DatosHistoricos as $valor) {
            //$lista[] = $fila[$valor->name];
            $datosHistoricosTabla .=
                '<tr>
                    <td>' . $meses[$valor->Mes - 1] . '-' . $valor->A_no . '</td>
                    <td class="derecha">' . intval($valor->Consumo) . ' M3</td>
                </tr>';
        }

        $auxiliarCondicionConvenio = " AND ConceptoAdicionalesCotizaci_on.EstatusConvenioC = 0";
        $Cotizaciones = Funciones::ObtenValor("SELECT GROUP_CONCAT(id) as Cotizaci_ones
        FROM
            Cotizaci_on 
        WHERE
            Padr_on = $idPadron AND Tipo IN( 9,16 )
            and ( 
                SELECT sum( importe ) 
                FROM ConceptoAdicionalesCotizaci_on 
                WHERE ConceptoAdicionalesCotizaci_on.Cotizaci_on = Cotizaci_on.id AND 
                Padre IS NULL AND EstatusConvenioC = 0  
                #AND ConceptoAdicionalesCotizaci_on.ConceptoAdicionales 
                #NOT IN(3475,5830,5571,5572,5573,2845,5569,5570,3084,2820,2831,2929,2624,8173,4968,2619,158,2208,2209,2642,8435,159,3668,3444,1079,7135,5492,6144,8337,8312,8260,3443,6823,7201,7205,8456,8455,242,241,6145,1080,8454,8453,8452,5565,5564)
                #vactor 3475,5830,5571,5572,5573,2845,5569,5570
                #Constancias agua 3084,2820,2831,2929,2624,8173,4968,2619,158,2208,2209,2642,8435,159,3668
                #pipas de Agua 3444,1079,7135,5492,6144,8337,8312,8260,3443,6823,7201,7205,8456,8455,242,241,6145,1080,8454,8453,8452,5565,5564
            ) > 0 
        GROUP by Padr_on ORDER BY id DESC", 'Cotizaci_ones');
        #return $Cotizaciones;

        if ($Cotizaciones == "NULL") {
            #echo $idPadron;
            precode("Sin Cotizaciones.", 1, 1);
        }

        if( $estaPagado ) goto sinCalcular;

        $DescuentoGeneralCotizaciones = 0;
        $SaldoDescontadoGeneralTodo   = 0;

        $Descuentos = AguaController::ObtenerDescuentoConceptoRecibo($Cotizaciones, $cliente);
        $SaldosActuales = AguaController::ObtenerDescuentoPorSaldoAnticipadoRecibo($Cotizaciones, $Descuentos['ImporteNetoADescontar'], $Descuentos['Conceptos'], $cliente);

        $ConsultaConceptos="SELECT c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad, c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario, 
        (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional, co.A_no, COALESCE(co.Mes, 1) as Mes, ct.Tipo, c.TipoToma
        FROM ConceptoAdicionalesCotizaci_on co 
        INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
        INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )
        WHERE  co.Cotizaci_on IN( " . $Cotizaciones . ") and Estatus=0 ORDER BY co.A_no DESC, COALESCE(co.Mes, 1) DESC ,  co.id ASC ";

        $ResultadoConcepto  = DB::select($ConsultaConceptos);

        $ConceptosCotizados = '';
        $totalConcepto      = 0;
        $indexConcepto      = 0;

        setlocale(LC_TIME, "es_MX.UTF-8");

        $sumaSaldos          = 0;
        $sumaRecargos        = 0;
        $sumaDescuentos      = 0;
        $sumaTotalFinal      = 0;
        $sumaActualizaciones = 0;

        $RegistroConcepto = $ResultadoConcepto[0];

        $consumoMesActual = array();

        if (empty($RegistroConcepto->Adicional)) {
            $consumoMesActual['Consumo'] = str_replace(",", "", $RegistroConcepto->total);
        } else {
            $consumoMesActual[$RegistroConcepto->Adicional] = str_replace(",", "", $RegistroConcepto->total);
        }

        $ActualizacionesYRecargosFunciones = AguaController::ObtenerRecargosYActualizacionesPorConceptoRecibo($RegistroConcepto->ConceptoCobro, $Cotizaciones, $cliente);

        $importeNeto = $sub_total = str_replace(",", "", $RegistroConcepto->total);

        $Actualizaci_on = str_replace(",", "", $ActualizacionesYRecargosFunciones['Actualizaciones']);
        $Recargos = str_replace(",", "", $ActualizacionesYRecargosFunciones['Recargos']);

        $DescuentoGeneralCotizaciones += $Descuento = str_replace(",", "", $Descuentos[$RegistroConcepto->ConceptoCobro]);
        $SaldoDescontadoGeneralTodo += $saldo = str_replace(",", "",$SaldosActuales[$RegistroConcepto->ConceptoCobro]);
        #$sub_total = ($sub_total + $Actualizaci_on + $Recargos) - $Descuento - $saldo;
        $sub_total = ($sub_total);
        #$Auxiliar = "Importeneto=" . $importeNeto . " Actualizacion = " . $Actualizaci_on . " Recargos = " . $Recargos . " Descuento = " . $Descuento . " Saldo Descontado = " . $saldo . " Total a Pagar = " . $sub_total ;
        $sumaActualizaciones=str_replace(",", "",$sumaActualizaciones);
        $sumaRecargos=str_replace(",", "",$sumaRecargos);
        $sumaActualizaciones += $Actualizaci_on;
        $sumaRecargos += $Recargos;
        #$Actualizaci_on = number_format($ActualizacionesYRecargosFunciones['Actualizaciones'], 2);
        #$Recargos = number_format($ActualizacionesYRecargosFunciones['Recargos'], 2);

        $Descuento = number_format(str_replace(",", "",$Descuentos[$RegistroConcepto->ConceptoCobro]), 2);
        $sumaDescuentos=str_replace(",", "",$sumaDescuentos);
        $sumaDescuentos += str_replace(",", "",$Descuento);
        $sumaSaldos=str_replace(",", "",$sumaSaldos);
        $sumaSaldos += str_replace(",", "",$saldo);

        $subtotal = number_format(str_replace(",", "", $sub_total) , 2);
        $subtotal = str_replace(",", "", $subtotal);
        $sumaTotalFinal = str_replace(",", "",$sumaTotalFinal);
        $sumaTotalFinal += $subtotal+$Recargos+$Actualizaci_on;

        $totalConcepto                                  = $RegistroConcepto->total;
        $ConceptosCotizados                             = $RegistroConcepto->idConceptoCotizacion . ',';
        $ConceptoPadre[$indexConcepto]['id']            = $RegistroConcepto->idConceptoCotizacion;
        $ConceptoPadre[$indexConcepto]['Mes']           = $RegistroConcepto->Mes;
        $ConceptoPadre[$indexConcepto]['A_no']          = $RegistroConcepto->A_no;
        $ConceptoPadre[$indexConcepto]['Total']         = 0;
        $ConceptoPadre[$indexConcepto]['Nombre']        = $RegistroConcepto->NombreConcepto;
        $ConceptoPadre[$indexConcepto]['TipoPredio']    = $RegistroConcepto->Tipo;
        $ConceptoPadre[$indexConcepto]['idConcepto']    = $RegistroConcepto->ConceptoCobro;
        $ConceptoPadre[$indexConcepto]['FechaConcepto'] = $RegistroConcepto->A_no . "-" . $RegistroConcepto->Mes . "-01";

        $conceptosNombresMes     = array(); #Conceptos del mes actual
        $adicionalesNombresMes   = array(); #Adicionales del mes actual
        $conceptosNombres        = array(); #Conceptos meses de adeudo - Consumo
        $adicionalesNombres      = array(); #Adicionales nombres meses de adeudo
        $adicionalesValores      = array(); #Adicionales valores meses de adeudo
        $conceptosOtrosNombres   = array(); #Otros conceptos nombres
        $conceptosOtrosValores   = array(); #Otros conceptos valores
        $adicionalesOtrosNombres = array(); #Otros adicionales nombres
        $adicionalesOtrosValores = array(); #Otros adicionales valores
        $recargosActualizaciones = array(); # Recargos y actualizaciones
        $conceptoServicio              = array(); #Conceptos de los servicio
        $conceptosServiciosAdicionales = array(); #Conceptos Adicionales de los servicios
        $sumaConceptosA = 0;
        $sumaAdicionalesA = 0;
        $i=0;
        $h=0;
        $ar=[];
        foreach ($ResultadoConcepto as $RegistroConcepto) {

            if($i!=0){
            $ActualizacionesYRecargosFunciones = AguaController::ObtenerRecargosYActualizacionesPorConceptoRecibo($RegistroConcepto->ConceptoCobro, $Cotizaciones, $cliente);

            $importeNeto = $sub_total = str_replace(",", "", $RegistroConcepto->total);

            $Actualizaci_on = str_replace(",", "", $ActualizacionesYRecargosFunciones['Actualizaciones']);
            $Recargos = str_replace(",", "", $ActualizacionesYRecargosFunciones['Recargos']);

            $DescuentoGeneralCotizaciones += $Descuento = str_replace(",", "", $Descuentos[$RegistroConcepto->ConceptoCobro]);
            $SaldoDescontadoGeneralTodo += $saldo = str_replace(",", "",$SaldosActuales[$RegistroConcepto->ConceptoCobro]);
            #$subTotalRecibo = $sub_total - $Descuento - $saldo;
            #$sub_total = ($sub_total + $Actualizaci_on + $Recargos) - $Descuento - $saldo;
            $sub_total = ($sub_total);
            $sub_total=str_replace(",", "", $sub_total);
            #$Auxiliar = "Importeneto=" . $importeNeto . " Actualizacion = " . $Actualizaci_on . " Recargos = " . $Recargos . " Descuento = " . $Descuento . " Saldo Descontado = " . $saldo . " Total a Pagar = " . $sub_total ;
            $sumaActualizaciones=str_replace(",", "",$sumaActualizaciones);
            $sumaRecargos=str_replace(",", "",$sumaRecargos);
            $sumaActualizaciones += $Actualizaci_on;
            $sumaRecargos += $Recargos;
            #$Actualizaci_on = number_format($ActualizacionesYRecargosFunciones['Actualizaciones'], 2);
            #$Recargos = number_format($ActualizacionesYRecargosFunciones['Recargos'], 2);

            $Descuento = number_format($Descuentos[$RegistroConcepto->ConceptoCobro], 2);
            $sumaDescuentos += $Descuento;
            $sumaSaldos = str_replace(",", "",$sumaSaldos);
            $sumaSaldos += str_replace(",", "",$saldo);

            $subtotal = number_format(str_replace(",", "", $sub_total) , 2);
            $subtotal = str_replace(",", "", $subtotal);
            $sumaSaldos =str_replace(",", "", $sumaSaldos);
            $sumaTotalFinal += $subtotal+$Recargos+$Actualizaci_on;
            $ar[$h]=$subtotal."-".$Recargos."-".$Actualizaci_on;
            $h++;
            if (empty($RegistroConcepto->Adicional)) {
                //Es concepto
                $ConceptoPadre[$indexConcepto]['Total'] = $totalConcepto;

                $totalConcepto = $RegistroConcepto->total;
                $indexConcepto++;

                $ConceptoPadre[$indexConcepto]['id']            = $RegistroConcepto->idConceptoCotizacion;
                $ConceptoPadre[$indexConcepto]['Mes']           = $RegistroConcepto->Mes;
                $ConceptoPadre[$indexConcepto]['A_no']          = $RegistroConcepto->A_no;
                $ConceptoPadre[$indexConcepto]['Nombre']        = $RegistroConcepto->NombreConcepto;
                $ConceptoPadre[$indexConcepto]['TipoPredio']    = $RegistroConcepto->Tipo;
                $ConceptoPadre[$indexConcepto]['idConcepto']    = $RegistroConcepto->ConceptoCobro;
                $ConceptoPadre[$indexConcepto]['FechaConcepto'] = $RegistroConcepto->A_no. "-" . $RegistroConcepto->Mes . "-01";

                if (empty($RegistroConcepto->TipoToma)) {
                    $conceptosOtrosNombres[] = $RegistroConcepto->NombreConcepto;
                    $conceptosOtrosValores[] = $subtotal;
                } else {
                    if( empty($RegistroConcepto->A_no) ){
                        $conceptoServicio[$RegistroConcepto->NombreConcepto] = $subtotal;
                    }else{
                        /*if ($RegistroConcepto['A_no'] == $a_noCobro && $RegistroConcepto['Mes'] == $mesCobro) {
                            $conceptosNombresMes[$RegistroConcepto['ConceptoCobro']] = $subtotal;
                        } else {*/
                            $sumaConceptosA = str_replace(",", "", $sumaConceptosA);
                            $sumaConceptosA += $subtotal; //Para el consumo de meses anteriores
                        #}
                    }
                }
            } else {
                //Es adicional
                $totalConcepto += $RegistroConcepto->total;

                if (empty($RegistroConcepto->TipoToma)) {
                    $adicionalesOtrosNombres[] = $RegistroConcepto->Adicional;
                    $adicionalesOtrosValores[] = $subtotal;
                } else {
                    if( empty($RegistroConcepto->A_no) ){
                        $conceptosServiciosAdicionales[$RegistroConcepto->Adicional ]= $subtotal;
                    }else{
                        if ($RegistroConcepto->A_no == $a_noCobro && $RegistroConcepto->Mes == $mesCobro) {
                            $adicionalesNombresMes[$RegistroConcepto->Adicional] = $subtotal;
                        } else {
                            $adicionalesNombres[] = $RegistroConcepto->Adicional;
                            $adicionalesValores[] = $subtotal;
                        }
                    }
                }
            }
            $ConceptosCotizados .= $RegistroConcepto->idConceptoCotizacion . ',';
          }
          $i++;
        }

        $CobradoPorAnticipado = 0;

        if($SaldoDescontadoGeneralTodo>0 && $SaldoDescontadoGeneralTodo<=$sumaTotalFinal){
            #$sumaTotalFinal=str_replace(",", "",$sumaTotalFinal)-str_replace(",", "",$SaldoDescontadoGeneralTodo)-str_replace(",", "",$sumaDescuentos);
            if( ($SaldoDescontadoGeneralTodo + $sumaDescuentos) > $sumaTotalFinal ){
                $sumaTotalFinal = str_replace(",", "",$sumaTotalFinal) - str_replace(",", "",$sumaDescuentos);
                $CobradoPorAnticipado = $sumaTotalFinal;
                $sumaTotalFinal = 0;
            }else{
                $sumaTotalFinal = str_replace(",", "",$sumaTotalFinal) - str_replace(",", "",$SaldoDescontadoGeneralTodo) - str_replace(",", "",$sumaDescuentos);
            }
        }elseif($SaldoDescontadoGeneralTodo>=$sumaTotalFinal)
            $sumaTotalFinal=0;
        elseif($SaldoDescontadoGeneralTodo==0)
            $sumaTotalFinal = str_replace(",", "",$sumaTotalFinal) -str_replace(",", "",$sumaDescuentos);

        if ($sumaConceptosA > 0) {
            $conceptosNombres["Consumo"] = $sumaConceptosA;
        }

        $contar = array();
        $i = 0;
        foreach ($adicionalesNombres as $value) {
            if (isset($contar[$value])) {
                // si ya existe, le añadimos uno
                $contar[$value] = str_replace(",", "", $contar[$value]);
                $contar[$value] += str_replace(",", "", $adicionalesValores[$i]);
            } else {
                // si no existe lo añadimos al array
                $contar[$value] = str_replace(",", "", $adicionalesValores[$i]);
            }
            $i++;
        }

        $conceptosOtros = array();
        $j = 0;
        foreach ($conceptosOtrosNombres as $value) {
            $concepto = str_replace(",", "", $value);

            if( isset( $conceptosOtros[$concepto] ) )
                $conceptosOtros[ $concepto ] += str_replace(",", "",$conceptosOtrosValores[$j]);
            else
                $conceptosOtros[ $concepto ] = str_replace(",", "",$conceptosOtrosValores[$j]);

            $j++;
        }

        $adicionalesOtros = array();
        $k = 0;
        foreach ($adicionalesOtrosNombres as $value) {
            $adicional = str_replace(",", "", $value);
            $adicional = str_replace("%", "", $adicional);

            if ( isset($adicionalesOtros[$adicional]) )
                $adicionalesOtros[$adicional] += str_replace( ",", "", $adicionalesOtrosValores[$k] );
            else
                $adicionalesOtros[$adicional] = str_replace( ",", "", $adicionalesOtrosValores[$k] );

            $k++;
        }

        $ConceptoPadre[$indexConcepto]['Total'] = $totalConcepto;
        $ConceptosCotizados = substr_replace($ConceptosCotizados, '', -1);

        //Buscamos actualizaciones y recargos para los conceptos a pagar
        #$PagoActualizaciones      = 0;
        #$ActualizacionesYRecargos = "";

        //uno los arrays
        $array_mes       = array_merge($consumoMesActual, $adicionalesNombresMes);
        $array_otros     = array_merge($conceptosOtros, $adicionalesOtros);
        $array_rezago    = array_merge($conceptosNombres, $contar);
        $array_servicios = array_merge($conceptoServicio, $conceptosServiciosAdicionales);

        $totalMes       = 0;
        $totalOtros     = 0;
        $totalRezago    = 0;
        $totalFinal     = $sumaTotalFinal;
        $totalesFinales = $sumaActualizaciones + $sumaRecargos;

        $FilaConceptosTotales = "<br>";
        if (empty($array_rezago)) {
            foreach ($array_mes as $key => $value) {
                $FilaConceptosTotales .= '<tr>
                <td>' . utf8_decode($key) . '</td>
                <td class="derecha">' . (number_format($value, 2)) . '</td>
                <td class="derecha">-</td>
                <td class="derecha">' . (number_format($value, 2)) . '</td>
            </tr>';
                $totalMes    += str_replace(",", "", $value);
            }
        } else {
            foreach ($array_mes as $key => $value) {

                $FilaConceptosTotales .= '<tr>
                <td>' . utf8_decode($key) . '</td>
                <td class="derecha">' . (number_format(str_replace(",", "", $value), 2)) . '</td>
                <td class="derecha">' . (number_format(str_replace(",", "", $array_rezago[$key]), 2)) . '</td>
                <td class="derecha">' . (number_format(str_replace(",", "", $value) + str_replace(",", "", $array_rezago[$key]), 2)) . '</td>
            </tr>';
                $totalMes     = str_replace(",", "", $totalMes);
                $totalMes    += str_replace(",", "", $value);
                $totalRezago  = str_replace(",", "", $totalRezago);
                $totalRezago += str_replace(",", "", $array_rezago[$key]);
            }
        }

        $totalServicios = 0;
        if ( !empty($array_servicios)) {

            foreach ($array_servicios as $key => $value) {
                $totalMes        = str_replace(",", "", $totalMes);
                $totalMes       += str_replace(",", "", $value);
                $totalServicios += str_replace(",", "", $value);
            }

            $FilaConceptosTotales .= '<tr>
                <td>Otros Servicios</td>
                <td class="derecha">' . (number_format($totalServicios, 2)) . '</td>
                <td class="derecha"></td>
                <td class="derecha">' . (number_format($totalServicios, 2)) . '</td>
            </tr>';
        }

        if (!empty($array_otros)) {
            foreach ($array_otros as $key => $value) {
                if ($key === "Alcantarillado planta tratadora con desalinizadora. (Organismo Público)") {
                    $concepto = 'Alcant. PT. Desalizadora';
                } elseif ($key === "Saneamiento planta tratadora con desalinizadora. (Organismo Público)") {
                    $concepto = 'Saneam. PT. Desalizadora';
                } else
                    $concepto = utf8_decode ($key);

                $FilaConceptosTotales .= '<tr>
                <td>' . (substr($concepto, 0, 44)) . '</td>
                <td class="derecha"></td>
                <td class="derecha"></td>
                <td class="derecha">' . (number_format($value, 2)) . '</td>
            </tr>';
                $totalOtros = str_replace(",", "", $totalOtros);
                $totalOtros +=  str_replace(",", "", $value);
            }
        }

        $tipoDescuento = Funciones::ObtenValor("SELECT PrivilegioDescuento FROM Padr_onAguaPotable WHERE id = " . $idPadron . " AND PrivilegioDescuento != 0", "PrivilegioDescuento");
        //precode($descuentos,1,1);
        $esDescuento = FALSE;
        $descuento   = 0;
        if ($tipoDescuento != "NULL") {
            #$cantidad * $porcentaje / 100
            $esDescuento = TRUE;
        } else
            $esDescuento = FALSE;

        sinCalcular:

        $fechaActual = date("Y-m-d");
        $auxMes = array("", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");

        if ($estaPagado) {
            $totalFinal = str_replace(",", "", $DatosParaRecibo['AdeudoCompleto']);
            $FilaConceptosTotales = '<tr>
                <td>Adeudo Completo</td>
                <td class="derecha"></td>
                <td class="derecha"></td>
                <td class="derecha">' . (number_format(str_replace(",", "", $totalFinal), 2)) . '</td>
            </tr>';

            $totalRezago = 0;
            $totalMes = 0;

            goto finCalculos;
        }

        $decimales = 0;
        if (is_float($totalFinal) && $totalFinal > 0) {
            #En caso de que el total sea decimal - Se toma el numero despues del punto
            $exp = explode(".", $totalFinal);
            #Se asigna el numero tomado
            if(isset($exp[1]))
                $decimales = "0." . $exp[1];
            else
                $decimales = "0";
        }

        $estaAjustado = FALSE;
        $ajuste = 0;
        $ajusteFinal = 0;

        if ( is_float( $totalFinal ) && $totalFinal > 0 ){
            $ajuste = $decimales;
            $ajusteFinal = intval($totalFinal);
            $estaAjustado = TRUE;
        }else{
            $ajusteFinal = str_replace(",", "",$totalFinal);
        }

        $totalFinal = intval( $totalFinal );

        if ($totalesFinales > 0) {
            $totalRezago += str_replace(",", "", $totalesFinales);
            $FilaConceptosTotales .= '<tr>
                        <td>Actualizaciones y Recargos</td>
                        <td><br></td>
                        <td class="derecha">
                            ' . number_format($totalesFinales, 2) . '
                        </td>
                        <td class="derecha">
                            ' . number_format($totalesFinales, 2) . '
                        </td>
                    </tr>';
        }

        if ($estaAjustado) {
            $FilaConceptosTotales .= '<tr>
                        <td>Redondeo
                        </td>
                        <td><br></td>
                        <td><br></td>
                        <td class="derecha">
                            ' . $ajuste . '
                        </td>
                    </tr>';
        }

        $descNombre = "";
        if ($esDescuento && $sumaDescuentos > 0) {
            #$descNombre =Funciones::ObtenValor("SELECT Nombre FROM TipoDescuentoPersona WHERE id = " . $tipoDescuento, 'Nombre');

            $FilaConceptosTotales .=
                '<tr>
                    <td>' . Funciones::tipoDescuento( $tipoDescuento ) . '</td>
                    <td><br></td>
                    <td><br></td>
                    <td class="derecha">-
                        ' . $sumaDescuentos . '
                    </td>
                </tr>';
        }

        if($CobradoPorAnticipado > 0) {
            $FilaConceptosTotales .= '<tr>
                        <td>Ingresos Cobrados por Anticipado</td>
                        <td><br></td>
                        <td><br></td>
                        <td class="derecha">-
                            ' . $CobradoPorAnticipado . '
                        </td>
                    </tr>';
        }elseif($sumaSaldos > 0) {
            $FilaConceptosTotales .= '<tr>
                        <td>Ingresos Cobrados por Anticipado</td>
                        <td><br></td>
                        <td><br></td>
                        <td class="derecha">-
                            ' . $sumaSaldos . '
                        </td>
                    </tr>';
        }


        $totalFinal = str_replace(",", "", $ajusteFinal);

        finCalculos:

        if( $estaPagado )
            $totalFinal = 0;

        #return $totalFinal;

        if($totalFinal < 0){
            $totalFinal = 0;
        }

        //cantidad con letra y total
        $letras = utf8_decode(Funciones::num2letras($totalFinal, 0, 0) . " pesos");
        $ultimoArr = explode(".", number_format($totalFinal, 2)); //recupero lo que este despues del decimal
        $ultimo = $ultimoArr[1];
        if ($ultimo == "")
            $ultimo = "00";
        $letras = $letras . " " . $ultimo . "/100 M. N.";

        /*if( ($estaPagado && $Cotizaciones == "NULL") || $totalFinal == 0 )
            return "";*/

        $nombreComercial = $DatosPadron->NombreORaz_onSocial;
        if( strlen($nombreComercial) > 0 && strlen($nombreComercial) > 55 ){
            $nombreComercial = substr($nombreComercial, 0, strlen($nombreComercial) / 2) . '<br>' . substr($nombreComercial, strlen($nombreComercial) / 2, strlen($nombreComercial) );
        }

        #return $letras;
        $rutaBarcode = 'https://suinpac.com/lib/barcode2.php?f=png&text=' . (isset($DatosPadron->ContratoVigente) ? $DatosPadron->ContratoVigente : '') . '&codetype=Code128&orientation=Horizontal&size=40&print=false "';

        $DIR_RESOURCE = 'recursos/';
        $DIR_IMG = 'imagenes/';

       // return $letras;
        $htmlGlobal = '<style>
        .centrado {
            text-align: center;

        }
        .derecha {
            text-align: right;
        }
        .izquierda {
            text-align: left;
        }
        .letras{
            font-family: "Arial", serif;
            font-size: 6pt;
        }
        .numeros{
            font-family: "Arial", serif;
            font-size: 6pt;
        }
        td {
            font-family: "Arial";
            font-size: 10pt;
        }
        th {
            font-family: "Arial", serif;
            font-size: 12pt;
        }
        .negritas{
            font-family: "Arial", serif;
            font-size: 8pt;
            font-weight: bold;
        }
        .total{
            font-family: "Arial", serif;
            font-size: 18pt;
            font-weight: bold;
        }
        table {
            position: relative;
        }
        .sobre {
            position:absolute;
            top:0px;
            left:10px;
            border:none;
        }
        .sobre2 {
            position:absolute;
            top:-15px;
            left:20px;
            border:none;
        }
        .marca {
            position: absolute;
            z-index: 1;
            content: "PAGADO";
            font-size: 53px;
            color: rgba(52, 166, 214, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            position: fixed;
            top: 250;
            right: 0;
            bottom: 0;
            left: 0;
        }
        .marco-turquesa{
            background:#01cbe3;
            border-radius: 15px;
            display: block;
            height: 20px;
            width: 150px;
            margin: 0 auto;
            text-align: center;
            padding-top:5px;
        }
        .marco-borde-izq{
            background:#01cbe3;
            border-radius: 15px 0px 0 15px;
            display: block;
            height: 20px;
            margin: 0 auto;
            text-align: center;
            padding-top:5px;
        }
        .marco-borde-der{
            background:#01cbe3;
            border-radius: 0px 15px 15px 0px;
            display: block;
            height: 20px;
            margin: 0 auto;
            text-align: center;
            padding-top:5px;
        }
        .marco-sin-borde{
            background:#01cbe3;
            display: block;
            height: 20px;
            margin: 0 auto;
            text-align: center;
            padding-top:5px;
        }
        .marco-sin-color{

            border-radius: 15px;
            display: block;
            height: 20px;
            width: 80px;
            margin: 0 auto;
            text-align: center;
            padding-top:5px;
        }

        .marco-turquesa-derecha{
            background:#01cbe3;
            border-radius: 15px;
            display: block;
            height: 20px;
            width: 150px;
            text-align: center;
            padding-top:5px;
        }
        .marco-turquesa-derecha-sin-relleno{
            border-style: solid;
            border-color: #01cbe3;
            border-radius: 15px;
            display: block;
            height: 20px;
            width: 150px;
            text-align: center;
            padding-top:5px;
        }
        .marco-derecha{
            display: block;
            margin:0px;
            padding:0px;
            width: 150px;
            text-align: center;

        }

        .marco-folio{
            background:#A9E2F3;
            border-radius: 15px;
            display: irun-in;
            height: 20px;
            width: 70px;
            text-align: center;
            padding-top:5px;
        }
        .color-gris{
            color:#585858;
            font-weight: bold;
        }
        .letras-resaltadas{
            font-weight: bold;
            font-size: 12pt;
        }
        .flex-container {
            width : 100%;
            background-color : #01cbe3;
            display : flex;
            flex-direction : row;
            flex-wrap : wrap;
            border-radius: 15px;
            height: 25px;
        }

        .flex-items {
            background-color : #01cbe3;
            flex-basis : 21%;
            flex-grow : 1;
            padding : 1%;
            margin : 1%;
            border-radius: 15px;
            height: 5px;
        }

        .flex-container-sin-color {
            width : 100%;
            display : flex;
            flex-direction : row;
            flex-wrap : wrap;
            border-radius: 15px;
            height: 25px;
            margin-top: -11px;
            margin-bottom: -4px;
        }

        .flex-items-sin-color {
            flex-basis : 21%;
            flex-grow : 1;
            padding : 1%;
            margin : 1%;
            border-radius: 15px;
        }

         .portada{
            width:100%;
            height:95%;
            position:absolute;
         }
         .azul{
            background:#A9E2F3;
            width:100%;
            height:80px;
            top:60%;
            opacity: 0.5;
            position:absolute;


        }
        .sin-espacio{
            padding:0px;
            margin:0px;
        }


        .es{

            height:140px;
            width:630px;
            color: red;
            margin:75px;
            opacity: 0.5;
            font-size: 130px;
            font-weight: bold;
            position:absolute;
            top:55%;
            left:8%;
            border-style: solid;
            border-color: red;
        }
        .inline-block{
            display:-moz-inline-stack;
            display:inline-block;
            zoom:1;
            *display:inline;
        }
        .marcaAgua {
            color: gray;
            opacity: 0.5; /* Opacidad más baja */
            font-size: 80px; /* Tamaño de fuente más grande */
            position: absolute;
            top: 45%;
            left: 0;
            transform: rotate(-90deg); /* Rotación en diagonal */
            transform-origin: left top; /* Origen de la rotación */
        }
    </style>
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml">
    <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>ServicioEnLinea.mx</title>
    </head>
    <body>
		<img  class="portada" src="'.asset(Storage::url( $DIR_IMG.'reciboCapazFondo.png')).'">
        <div class="azul"></div>

    <div>
    ' . ($estaPagado ? '<div> <span class="es inline-block">PAGADO</span></div>' : '') . '
        <table border="0" width="100%">
            <tr>
                <td rowspan="2" width="45%">
                    <img alt="Smiley face" height="100" width="300" src="'.asset(Storage::url( $DIR_IMG.'capazlogo.png')).'"">
                </td>
                <td width="30%" align="right">
                     </br>
                     <span><span class="color-gris marco-derecha">Adeudo anterior</span><span class="marco-turquesa-derecha">' . number_format($totalRezago, 2, '.', ',') . '</span></span>
                </td>
                <td colspan="2" width="10%" class="centrado">
                      </br>
                      <span class="color-gris">Importe del mes</span><span class="marco-turquesa">' . number_format($totalMes, 2, '.', ',') . '</span>
                </td>
                <td width="20%" align="right" class="marco-derecha" >
                      </br>
                      <span class="color-gris">Total a pagar</span><span class="marco-turquesa-derecha">' . number_format($totalFinal, 2, '.', ','). '</span>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="centrado" >
                     <span class="color-gris">Fecha l&iacute;mite de pago</span><span style="display: block;">' . $fechaLimite . '</span>

                </td>
                <td colspan="2" class="centrado" >
                     <span class="color-gris">Periodo</span><span style="display: block;">' . ($estaPagado ? $auxMes[$mesActual] : $auxMes[$DatosParaRecibo->Mes]) . '</span>
                </td>
            </tr>

            <tr>
                <td  class="letras-resaltadas" colspan="3" >
                  ' . ( ( strlen($contribuyente) <= 70 ) ? $contribuyente : substr($contribuyente, 0, 75) ) .'
                </td>
                <td rowspan="2" colspan="2" align="right">
                    <span  class="color-gris marco-derecha" >N&#176; de contrato </span><span class="marco-turquesa-derecha">' . (isset($DatosPadron->ContratoVigente) ? intval($DatosPadron->ContratoVigente) : '') . '</span>
                </td>
            </tr>
            <tr>
                    <td colspan="3" class="letras-resaltadas">
                    ' . utf8_decode(( ( strlen(utf8_decode($DatosPadron->Domicilio) <= 70 ) ? $DatosPadron->Domicilio : substr($DatosPadron->Domicilio, 0, 75) ))) . '
                    </td>
            </tr>
            <tr>
                    <td colspan="3" class="letras-resaltadas">
                       Col. ' . (isset($DatosPadron->Colonia) ? strlen(utf8_decode($DatosPadron->Colonia) <= 70 ) ? utf8_decode($DatosPadron->Colonia) : substr(utf8_decode($DatosPadron->Colonia), 0, 75) : '<br>')  . '

                    </td>
                    <td rowspan="2" colspan="2" class="derecha" >
                        <div>
                        <img  width="220" height="30" src="' . $rutaBarcode . '" >
                        </div>
                    </td>
            </tr>
            <tr>
               <td colspan="3" class="letras-resaltadas"> '.(isset($DatosPadron->SuperManzana) ? 'S. Mza. ' . $DatosPadron->SuperManzana : '') . (isset($DatosPadron->Manzana) ? '&nbsp;&nbsp;&nbsp;S. Mza. ' . $DatosPadron->Manzana : '') . (isset($DatosPadron->Lote) ? '&nbsp;&nbsp;&nbsp;Lote ' . $DatosPadron->Lote : '').'</td>

            </tr>
            <tr>
                    <td colspan="3">
                         <table border=0 width="100%" >
                            <tr class="centrado color-gris">
                                <td>N&#176; de medidor</td>
                                <td>Di&aacute;metro</td>
                                <td>Tipo de serv.</td>
                            </tr>
                            <tr>
                                <td class="sin-espacio"> <span class="marco-borde-izq"> ' . (isset($DatosPadron->Medidor) ? intval($DatosPadron->Medidor) : '') . '</span></td>
                                <td class="sin-espacio"><span class="marco-sin-borde"> ' . (isset($DatosPadron->Diametro) ? $DatosPadron->Diametro : '') . '</span></td>
                                <td class="sin-espacio"><span class="marco-borde-der">' . $tipoToma . '</span></td>
                            </tr>
                         </table>
                    </td>
                    <td rowspan="2" colspan="2" align="right">
                       <span class="color-gris marco-derecha">N&#176; de folio </span><span class="marco-turquesa-derecha-sin-relleno"><span class="color-gris" style="font-size:15px;">E</span><font color="red" size=3>'.$idLectura.'</font></span>
                     </td>
            </tr>
            <tr>
               <td colspan="3">
                     <table border=0 width="100%">
                            <tr class="centrado color-gris">
                                <td>Lectura Anterior</td>
                                <td> Lectura Actual</td>
                            </tr>
                            <tr>
                                <td class="sin-espacio"> <span class="marco-borde-izq">' . intval($lecturaAnterior) . '</span></td>
                                <td class="sin-espacio"><span class="marco-borde-der"> ' . intval($lecturaActual) . '</span></td>
                            </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="3">

                    <table border=0 width="100%" >
                            <tr class="centrado color-gris">
                                <td> Consumo M3</td>
                                <td>Fecha de corte del mes</td>
                            </tr>
                            <tr>
                                <td class="sin-espacio"> <span class="marco-borde-izq">' .intval($lecturaConsumo). '</span></td>
                                <td class="sin-espacio"><span class="marco-borde-der"> ' . $fechaCorte . '</span></td>
                            </tr>
                    </table>
                </td>
                <td colspan="2" class="derecha">
                    ' . utf8_decode($anomalia)  . '</br>' . $adeudos  . '
                </td>
            </tr>

            <tr>
                <td colspan="5">

                        <tr >
                            <td colspan="2" class="color-gris"> Descripci&oacute;n del concepto</td>
                            <td colspan="3" class="color-gris ">C&aacute;lculo de su facturaci&oacute;n</td>
                        </tr>

                        <tr>
                            <td colspan="2"><br></td>
                            <td class="centrado">Mes</td>
                            <td class="centrado">Rezago</td>
                            <td class="centrado">Total</td>
                        </tr>' . $FilaConceptosTotales . '

                       <tr>
                            <td colspan="2"><br></td>
                            <td  class="centrado"><span class="marco-turquesa">' .  number_format($totalMes, 2, '.', ','). '</span></td>
                            <td  class="centrado" ><span class="marco-turquesa">' . number_format($totalRezago, 2, '.', ',') . '</span></td>
                            <td  class="centrado"><span class="marco-turquesa">' . number_format($totalFinal, 2, '.', ',') . '</span></td>

                        </tr>
                        <tr>
                            <td colspan="2"><br></td>
                            <td>Sector</td>
                            <td  class="centrado" >Ruta</td>
                            <td  class="centrado">Prog.</td>
                        </tr>
                        <tr>
                            <td colspan="2"><br></td>
                            <td>' . (isset($DatosPadron->Sector) ? $DatosPadron->Sector : '') . '</td>
                            <td  class="centrado" >' . (isset($DatosPadron->Ruta) ? $DatosPadron->Ruta : '') . '</td>
                            <td  class="centrado">' . intval($folio) . '</td>
                        </tr>
                        <tr>
                            <td colspan="2"><br></td>
                            <!--<td>Uso:</td>
                            <td colspan="2">' . $DatosPadron->Giro . '</td>--!>
                        </tr>
                        <tr>
                            <td colspan="2"><br></td>
                            <td >Periodo de consumo:</td>
                            <td colspan="2">' . $periodo . '</td>
                        </tr>
                        <tr>
                            <td colspan="5" class="color-gris sin-espacio">Datos fiscales:</td>
                        </tr>
                        <tr>
                            <td colspan="5">
                                <tr>
                                    <td colspan="2">

                                        <table border="0" >
                                            <tbody>
                                                <tr>
                                                    <td>RFC: ' . (isset($DatosPadron->RFC) ? $DatosPadron->RFC : '') . '</td>
                                                </tr>
                                                <tr >
                                                    <td>'. utf8_decode($nombreComercial) .'</td>
                                                </tr>
                                                <tr>
                                                    <td>
                                                        ( ' . $letras . ' )

                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>

                                    <td colspan="3" class="color-gris">
                                        <table border="0" width="100%">
                                            <tbody class="derecha">
                                                <tr>
                                                    <td >Datos Historicos</td>

                                                </tr>
                                                ' . $datosHistoricosTabla . '
                                            </tbody>
                                        </table>
                                    </td>

                                </tr>
                            </td>
                        </tr>
                </td>
            </tr>



            <tr>

                <td colspan="1">
                </td>

                <td class="centrado">
                    <span class="color-gris">Per&iacute;odo</span><span class="marco-sin-color">' . ($estaPagado ? $auxMes[$mesActual] : $auxMes[$DatosParaRecibo->Mes]) . '</span>
                </td>
                <td colspan="2" class="centrado">
                      <span class="color-gris">Importe del mes</span><span class="marco-turquesa">' . number_format($totalMes, 2, '.', ',') . '</span>
                </td>
                <td class="centrado">
                     <span class="color-gris">Total a pagar</span><span class="marco-turquesa">' . number_format($totalFinal, 2, '.', ',') . '</span>
                </td>

            </tr>

            <tr>


            <tr>
                <td colspan="5" class="color-gris">Importe con lectura:</td>
            </tr>
            <tr>
                    <td colspan="5">
                        <tr>
                            <td colspan="2">

                                <table border="0" width="100%" cellspacing="0" cellpadding="0">
                                    <tbody>
                                        <tr>
                                            <td colspan=3>
                                                ( ' . $letras . ' )

                                            </td>
                                        </tr>

                                        <tr>
                                            <td colspan=3>
                                            ' . ( ( strlen($contribuyente) <= 70 ) ? $contribuyente : substr($contribuyente, 0, 75) ) .'
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan=3>
                                                ' . utf8_decode(( ( strlen(utf8_decode($DatosPadron->Domicilio) <= 70 ) ? $DatosPadron->Domicilio : substr($DatosPadron->Domicilio, 0, 75) )))  . '
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan=3>
                                                Col. ' . (isset($DatosPadron->Colonia) ? strlen(utf8_decode($DatosPadron->Colonia) <= 70 ) ? utf8_decode($DatosPadron->Colonia) : substr(utf8_decode($DatosPadron->Colonia), 0, 75) : '<br>')  . '

                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan=3> '.(isset($DatosPadron->SuperManzana) ? 'S. Mza. ' . $DatosPadron->SuperManzana : '') . (isset($DatosPadron->Manzana) ? '&nbsp;&nbsp;&nbsp;S. Mza. ' . $DatosPadron->Manzana: '') . (isset($DatosPadron->Lote) ? '&nbsp;&nbsp;&nbsp;Lote ' . $DatosPadron->Lote : '').'</td>
                                        </tr>
                                        <tr>
                                            <td >Sector</td>
                                            <td class="centrado">Ruta</td>
                                            <td class="centrado">Prog.</td>
                                        </tr>
                                        <tr>
                                            <td >' . (isset($DatosPadron->Sector) ? $DatosPadron->Sector : '') . '</td>
                                            <td class="centrado">' . (isset($DatosPadron->Ruta) ? $DatosPadron->Ruta : '') . '</td>
                                            <td class="centrado">' . intval($folio) . '</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>

                            <td colspan="3" class="color-gris centrado">
                                <table border="0" width="100%" cellspacing="0" cellpadding="0">
                                    <tbody>
                                        <tr>
                                            <td align="right" >
                                                 <span  class="color-gris marco-derecha">N&#176; de contrato </span><span class="marco-turquesa-derecha">' . (isset($DatosPadron->ContratoVigente) ? intval($DatosPadron->ContratoVigente) : '') . '</span>
                                           </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                </br>
                                                <div align="right" padding-top="5px">
                                                <img  width="220" height="25" src="' . $rutaBarcode . '" >
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td rowspan="2" colspan="4" align="right">

                                                <span class="color-gris marco-derecha">N&#176; de folio </span><div class="marco-turquesa-derecha-sin-relleno"><span class="color-gris" style="font-size:15px;">E</span><font color="red" size=3>'.$idLectura.'</font></div>
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>
                            </td>

                        </tr>
                    </td>
            </tr>

        </table>


    </div>
    <div> <span class="marcaAgua">capaz.servicioenlinea.mx</span></div>
    </body>
        </html>
';


 // return $htmlGlobal;


        #include_once("libPDF.php");
        include( app_path() . '/Libs/Wkhtmltopdf.php' );
        try {
            $nombre = uniqid() . "_" . $idLectura;
            #$wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'page_width'=>120,'page_height'=>200,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
            $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'margins' => ['top' => 10, 'left' => 10,'right'=>10,'bottom'=>10]));
            $wkhtmltopdf->setHtml($htmlGlobal);
            //$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, $nombre.".pdf");
            $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $nombre . ".pdf");
            //return "repositorio/temporal/" . $nombre . ".pdf";
            return response()->json([
                'success' => '1',
                'ruta' => "repositorio/temporal/" . $nombre . ".pdf",
            ]);
           /* return response()->json([
                'success' => '1',
                'ruta' => Storage::url( $DIR_IMG.'reciboCapazFondo.png'),
            ]);*/

        } catch (Exception $e) {
            echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
        }

    }




    public static function obtieneDatosLectura($Lectura){

		$TipoToma=ObtenValor("SELECT pl.TipoToma FROM  Padr_onDeAguaLectura pl WHERE id=".$Lectura, "TipoToma");
		#$TipoToma=($TipoToma=="NULL"?"pa":"pl");
		#precode("SELECT pl.TipoToma FROM  Padr_onDeAguaLectura pl WHERE id=".$Lectura,1,1);
		$DatosLecturaActual=ObtenValor("SELECT * , pa.id as paid, pl.id as plid, pl.Status as EstatusPagado, CONCAT(pl.A_no,LPAD(pl.Mes, 2, 0 )) as MesEjercicio, t.Concepto as TipoTomaTexto
			FROM Padr_onAguaPotable pa
			INNER JOIN Padr_onDeAguaLectura pl ON (pa.id=pl.Padr_onAgua)
			INNER JOIN TipoTomaAguaPotable t ON (t.id=".(is_null($TipoToma)?"pa":"pl").".TipoToma)
			WHERE
			#pl.Status=0 AND
			pl.id=".$Lectura."
			ORDER BY pl.A_no DESC, pl.Mes DESC
			LIMIT 0, 1");
		#precode($DatosLecturaActual,1,1);

		$ConsultaLecturas="SELECT DISTINCT pal.id as palid,  ".(is_null($TipoToma)?"pap":"pal").".TipoToma, pal.Consumo, pal.Tarifa, ccc.Descripci_on, ccc.CRI, ccc.id as id, pal.Mes, pal.A_no, ca.Importe as ImporteUnitario, ca.BaseCalculo, pap.Cliente, CONCAT(pal.A_no,LPAD(pal.Mes,2,'0')) as MesEjercicio, pal.Status as EstatusPagado
        FROM Padr_onAguaPotable pap
        INNER JOIN Padr_onDeAguaLectura pal ON (pap.id=pal.Padr_onAgua)
        INNER JOIN ConceptoCobroCaja ccc ON (ccc.TipoToma=".(is_null($TipoToma)?"pap":"pal").".TipoToma )
        INNER JOIN ConceptoRetencionesAdicionales cra ON ( cra.Concepto= ccc.id )
        INNER JOIN ConceptoRetencionesAdicionalesCliente crac ON ( cra.id = crac.ConceptoRetencionesAdicionales)
        INNER JOIN ConceptoAdicionales ca ON (ca.ConceptoRetencionesAdicionalesCliente=crac.id)
        WHERE
        ccc.Desde IS NULL AND
        ccc.Hasta IS NULL AND
        ca.Status=1 AND
        pap.id =".$DatosLecturaActual['paid']." AND
        pap.Cliente=".$DatosLecturaActual["Cliente"]." AND
        crac.Cliente=".$DatosLecturaActual["Cliente"]." AND
        pal.Status IN (0,1,2) AND
        pal.EstadoToma=1 AND
        CONCAT(pal.A_no,LPAD(pal.Mes,2,'0'))<=".$DatosLecturaActual["MesEjercicio"]." AND
        1=1";
#precode($ConsultaLecturas,1,1);

		$resultados=array();
		$resultados['SumaAdeudosAnteriores']=0;
		$resultados['AdeudoCompleto']=0;
		$resultados['AdeudoAnterior']=0;
		$resultados['AdeudoActual']=0;
		$resultados['MesInicial']="";
		$resultados['MesFinal']="";
		$resultados['MesDeCorte']=mesSiguiente($DatosLecturaActual["MesEjercicio"]);
		$resultados['Consumo']=$DatosLecturaActual['Consumo'];
		$resultados['Cuenta']=$DatosLecturaActual['Cuenta'];
                //Contrato Vigente
                //Contrato Anterior
                //medidor
                $resultados['ContratoVigente']=$DatosLecturaActual['ContratoVigente'];
                $resultados['ContratoAnterior']=$DatosLecturaActual['ContratoAnterior'];
                $resultados['Medidor']=$DatosLecturaActual['Medidor'];

		$resultados['FechaLectura']=$DatosLecturaActual['FechaLectura'];
		$resultados['EstatusPagado']=$DatosLecturaActual['EstatusPagado'];
		$resultados['numRecibo']=$DatosLecturaActual['plid'];
		$resultados['MesActual']=$DatosLecturaActual['MesEjercicio'];
		$resultados['TipoTomaTexto']=$DatosLecturaActual['TipoTomaTexto'];

		//precode($DatosLecturaActual,1 );
		if($DatosLecturaActual['M_etodoCobro']==2){
			$resultados['LecturaActual']=$DatosLecturaActual['LecturaActual'];
			$resultados['LecturaAnterior']=$DatosLecturaActual['LecturaAnterior'];
		}else{
			$resultados['LecturaActual']="No aplica";
			$resultados['LecturaAnterior']="No aplica";
		}
		$ejecutaLecturas=$Conexion->query($ConsultaLecturas);
		while($registroLectura=$ejecutaLecturas->fetch_assoc()){
		#	precode($registroLectura,1,1);
			//Aqui veo todas las lecturas

			$datosAdicioaneles=ObtieneAdicionales($registroLectura['id'], $registroLectura['Consumo'], $registroLectura['Tarifa'], $registroLectura['Cliente'], $registroLectura['BaseCalculo'], $registroLectura['ImporteUnitario'], $registroLectura['BaseCalculo'],  $registroLectura['MesEjercicio']);
			#precode($datosAdicioaneles,1);

			if($registroLectura['MesEjercicio']==$DatosLecturaActual["MesEjercicio"]){
				$resultados['AdeudoActual']=$datosAdicioaneles['SumaCompleta'];
				$resultados['AdeudoCompleto']+=$datosAdicioaneles['SumaCompleta'];

			}
			else{
				if($registroLectura['EstatusPagado']!=2){
					if($resultados['MesInicial']==""){
						$resultados['MesInicial']=$datosAdicioaneles['MesEjercicio'];
					}
					$resultados['SumaAdeudosAnteriores']+=$datosAdicioaneles['SumaCompleta'];
					if($datosAdicioaneles['MesEjercicio']>$resultados['MesFinal']){
						$resultados['MesFinal']=$datosAdicioaneles['MesEjercicio'];
					}
					if($datosAdicioaneles['MesEjercicio']<$resultados['MesInicial']){
						$resultados['MesInicial']=$datosAdicioaneles['MesEjercicio'];
					}
					$resultados['AdeudoAnterior']+=$datosAdicioaneles['SumaCompleta'];
					$resultados['AdeudoCompleto']+=$datosAdicioaneles['SumaCompleta'];

				}
			}
		}
		$resultados['MesInicial']=convierteMesA_no($resultados['MesInicial']);
		$resultados['MesFinal']=convierteMesA_no($resultados['MesFinal']);
		$resultados['MesActual']=convierteMesA_no($resultados['MesActual']);
		#precode($resultados,1,1);
		if($resultados['AdeudoAnterior']==0)
			$resultados['Rango']="No hay adeudo";
		else
			$resultados['Rango']=$resultados['MesInicial']." al ".$resultados['MesFinal'];
		return $resultados;
    }//termina funcion obtieneDatosLectura





	public static function ObtenerRecargosYActualizacionesPorConceptoRecibo($idConcepto, $idCotizacion, $cliente,$FechaActualV5=null){

        if(is_null($FechaActualV5))
            $FechaActualV5=date("Y-m-d");
        if($cliente==20){ /// Esta condicion es para que solo aplique y retrase los recargos en Zihua y Capaz
            #$FechaActualV5=date("Y-m-28");
            $FechaActualV5=date("2020-10-17");
        }

        /*if(isset($idCotizacion) && $idCotizacion!="")
           $FechaLimiteCotizaci_on = Funciones::ObtenValor("SELECT FechaLimite FROM Cotizaci_on WHERE id IN($idCotizacion) order by FechaLimite DESC", "FechaLimite");
        else
            $FechaLimiteCotizaci_on = NULL;*/


        $ActualizacionesYRecargosConcepto=array('Actualizaciones'=>0,'Recargos'=>0 );

        $ConsultaConceptos = "SELECT c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad, c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario,
                (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional, co.A_no, COALESCE(co.Mes, '01') as Mes, ct.Tipo, c.TipoToma,ct.id as idCotizacion
            FROM ConceptoAdicionalesCotizaci_on co
                INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
                INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )
            WHERE  co.id=".$idConcepto." AND  co.Cotizaci_on IN (".$idCotizacion.") and Estatus=0 ORDER BY co.A_no DESC, COALESCE(co.Mes, '01') DESC ,  co.id ASC  ";
        #precode($ConsultaConceptos,1

        $ResultadoConcepto  = DB::select($ConsultaConceptos);
        $FilaConceptos      = '';
        $FilaActualizacion  = '';
        $ConceptosCotizados = '';
        $totalConcepto      = 0;
        $idsConceptos       = '';
        $Contador           = 0;
        $ConceptoActual     = 0;
        $Conceptos          = '';
        $indexConcepto      = 0;
        $inicio             = 0;
        setlocale(LC_TIME,"es_MX.UTF-8");

        //Leermos el primer concepto.
      //Leermos el primer concepto.
        $RegistroConcepto = $ResultadoConcepto[0];

        $totalConcepto                                  = $RegistroConcepto->total;
        $idsConceptos                                   = $RegistroConcepto->ConceptoCobro.',';
        $ConceptosCotizados                             = $RegistroConcepto->idConceptoCotizacion.',';
        $ConceptoPadre[$indexConcepto]['id']            = $RegistroConcepto->idConceptoCotizacion;
        $ConceptoPadre[$indexConcepto]['TipoPredio']    = $RegistroConcepto->Tipo;
        $ConceptoPadre[$indexConcepto]['Mes']           = $RegistroConcepto->Mes;
        $ConceptoPadre[$indexConcepto]['A_no']          = $RegistroConcepto->A_no;
        $ConceptoPadre[$indexConcepto]['idConcepto']    = $RegistroConcepto->ConceptoCobro;
        $ConceptoPadre[$indexConcepto]['Nombre']        = $RegistroConcepto->NombreConcepto;
        $ConceptoPadre[$indexConcepto]['Total']         = 0;
        $ConceptoPadre[$indexConcepto]['FechaConcepto'] = $RegistroConcepto->A_no."-".$RegistroConcepto->Mes."-01";

        $recargosActualizaciones = array();
        $i=0;
        foreach($ResultadoConcepto as $RegistroConcepto){
            if($i!=0){
            //precode($RegistroConcepto,1);
            if( empty($RegistroConcepto->Adicional) ){
                //Es concepto
                $FilaConceptos = str_replace('TotalConcepto'.$ConceptoActual, round($totalConcepto,2 ), $FilaConceptos);
                $FilaConceptos = str_replace('ConceptoRetenciones'.$ConceptoActual, substr_replace($idsConceptos, '', -1), $FilaConceptos);
                $ConceptoPadre[$indexConcepto]['Total']=$totalConcepto;

                $totalConcepto = $RegistroConcepto->total ;
                $idsConceptos  = $RegistroConcepto->ConceptoCobro.',';
                $Contador      = 0;
                $ConceptoActual++;
                $indexConcepto++;

                $ConceptoPadre[$indexConcepto]['id']            = $RegistroConcepto->idConceptoCotizacion;
                $ConceptoPadre[$indexConcepto]['TipoPredio']    = $RegistroConcepto->Tipo;
                $ConceptoPadre[$indexConcepto]['Mes']           = $RegistroConcepto->Mes;
                $ConceptoPadre[$indexConcepto]['A_no']          = $RegistroConcepto->A_no;
                $ConceptoPadre[$indexConcepto]['idConcepto']    = $RegistroConcepto->ConceptoCobro;
                $ConceptoPadre[$indexConcepto]['Nombre']        = $RegistroConcepto->NombreConcepto;
                $ConceptoPadre[$indexConcepto]['FechaConcepto'] = $RegistroConcepto->A_no."-".$RegistroConcepto->Mes."-01";

            }else{
                //Es adicional
                $totalConcepto += $RegistroConcepto->total ;
                $idsConceptos  .= $RegistroConcepto->ConceptoCobro.',';
            }
            $Contador++;
            $ConceptosCotizados.=$RegistroConcepto->idConceptoCotizacion.',';
        }
        $i++;
        }

        $ConceptoPadre[$indexConcepto]['Total']=$totalConcepto;

        //Buscamos actualizaciones y recargos para los conceptos a pagar
        $ActualizacionesYRecargos="";
        $PagoActualizaciones=0;
        $sumatotalActyRec=0;

        $fechaActual = date("Y-m-d");


        if($cliente==20){
            $fechaActual= "2020-10-17";
        }

        $auxMes = array("", "Enero", "Febrero","Marzo","Abril","Mayo","Junio","Julio", "Agosto","Septiembre","Octubre","Noviembre","Diciembre");

        for($iC = 0; $iC < count($ConceptoPadre); $iC++){
            $ConceptoExcluidosParaActualizacionesRecargos = array(5461, 5462,5467, 5469, 2489, 5084);

            if ( !in_array($ConceptoPadre[$iC]['id'], $ConceptoExcluidosParaActualizacionesRecargos)) {
                //Obtenemos las actualizaciones y recargos.
                if($ConceptoPadre[$iC]['FechaConcepto']!="--01"){
                    if(date("Y-m", strtotime( $fechaActual ) ) > date('Y-m', strtotime($ConceptoPadre[$iC]['FechaConcepto']))){
                        //Obtenemos las multas del concepto
                        $ConsultaMultas= " SELECT mi.Categor_ia as Categoria, m.id as idMulta, m.Descripci_on as DescripcionMulta, c.id as idConcepto, c.Descripci_on as DescripcionConcepto
                            FROM MultaCategor_ia mi
                                INNER JOIN Multa m ON ( mi.Multa = m.id  )
                                INNER JOIN ConceptoCobroCaja c ON ( mi.Concepto = c.id  )
                            WHERE mi.Categor_ia = (select Categor_ia from ConceptoCobroCaja where id = ".$ConceptoPadre[$iC]['id'].")";
                        #precode($ConsultaMultas,1);
                        $ResultadoMultas=DB::select($ConsultaMultas);

                        foreach($ResultadoMultas as $RegistroMultas){
                            $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);
                            $elmes        = $fechainicial[1];
                            $elanio       = $fechainicial[0];

                            if($RegistroMultas->idMulta==1){
                                //Es Actualizacion
                                if($ConceptoPadre[$iC]['TipoPredio']==3)//3 es para predial
                                {
                                    $tipoPredio=Funciones::ObtenValor("SELECT pc.TipoPredio FROM Cotizaci_on c INNER JOIN Padr_onCatastral pc ON (pc.id=c.Padr_on) WHERE c.id=".$RegistroConcepto->idCotizacion, "TipoPredio");

                                    if($tipoPredio==10)// 10 es zofemat
                                    {
                                        $descripcionActyRec='Actualizaciones y Recargos de: '.$ConceptoPadre[$iC]['Nombre']." Correspondiente al bimestre ".$elmes." del ".$elanio.'';
                                        $montopredial=  $ConceptoPadre[$iC]['Total'];
                                        $elmes=$mes=($ConceptoPadre[$iC]['Mes']*2)+1;

                                        $elanio=$anio=$ConceptoPadre[$iC]['A_no'];
                                        if(intval($mes)>12){
                                            $mes=1;
                                            $anio=$anio+1;
                                        }

                                        $mes=str_pad($mes, 2, "0", STR_PAD_LEFT);

                                        $dia=18;

                                        $fechaVencimiento= $anio."-".$mes."-".$dia;

                                        if(date('D',intval($fechaVencimiento))=="Sat"){
                                           $dia=$dia+2;
                                        }
                                        if(date('D',intval($fechaVencimiento))=="Sun"){
                                            $dia=$dia+1;
                                        }

                                        $fechaVencimiento= $anio."-".$mes."-".$dia;

                                        //exit;
                                        $fecha_actual = strtotime(date("Y-m-d H:i:00",time()));
                                        $fecha_entrada = strtotime(date($fechaVencimiento." H:i:00"));
                                        if($fecha_actual > $fecha_entrada){
                                            //$recargosOK		   = 	CalculoRecargos($fechaVencimiento, $montopredial);
                                            $actualizacionesOK = Funciones::CalculoActualizacionFecha($fechaVencimiento, $montopredial, $fechaActual );

                                        }else{
                                           //	 $recargosOK		   = 0;
                                            $actualizacionesOK = 0;
                                        }
                                    }else{
                                    //para predial
                                        $descripcionActyRec='Actualizaciones y Recargos de: '.$ConceptoPadre[$iC]['Nombre']." Correspondiente al bimestre ".$elmes." del ".$elanio.'';
                                        $montopredial=  $ConceptoPadre[$iC]['Total'];
                                        $elmes=	$mes=($ConceptoPadre[$iC]['Mes']*2)-1;
                                        $elanio=$anio=$ConceptoPadre[$iC]['A_no'];
                                        if(intval($mes)>12){
                                           $mes=1;
                                           $anio=$anio+1;
                                        }

                                        $mes=str_pad($mes, 2, "0", STR_PAD_LEFT);

                                        $dia=15;

                                        $fechaVencimiento= $anio."-".$mes."-".$dia;

                                        if(date('D',intval($fechaVencimiento))=="Sat"){
                                            $dia=$dia+2;
                                        }
                                        if(date('D',intval($fechaVencimiento))=="Sun"){
                                            $dia=$dia+1;
                                        }

                                        $fechaVencimiento= $anio."-".$mes."-".$dia;

                                        //exit;
                                        $fecha_actual = strtotime(date("Y-m-d H:i:00",time()));
                                        $fecha_entrada = strtotime(date($fechaVencimiento." H:i:00"));
                                        if($fecha_actual > $fecha_entrada){
                                           // $recargosOK		   = 	CalculoRecargos($fechaVencimiento, $montopredial);

                                            $actualizacionesOK = Funciones::CalculoActualizacionFecha($fechaVencimiento, $montopredial, $fechaActual );
                                        }else{
                                          //  $recargosOK		   = 0;
                                            $actualizacionesOK = 0;
                                        }
                                    }
                                }// termina tipo 3 predial
                                else{
                                    //Caso normal que no es predial
                                    $fechainicial       = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);
                                    $descripcionActyRec = 'Actualizaciones y Recargos de: '.$ConceptoPadre[$iC]['Nombre'];
                                    $montoconcepto      = $ConceptoPadre[$iC]['Total'];
                                    $mes                = ($fechainicial[1])+1;
                                    $anio               = $fechainicial[0];

                                    if(intval($mes) > 12){
                                        $mes  = 1;
                                        $anio = $anio+1;
                                    }

                                    $mes = str_pad($mes, 2, "0", STR_PAD_LEFT);
                                    $dia = 18;

                                    $fechaVencimiento = $anio."-".$mes."-".$dia;

                                    if(date('D',intval($fechaVencimiento))=="Sat"){
                                       $dia = $dia+2;
                                    }
                                    if(date('D',intval($fechaVencimiento))=="Sun"){
                                        $dia = $dia+1;
                                    }

                                    $fechaVencimiento = $anio."-".$mes."-".$dia;

                                    $fecha_actual  = strtotime(date("Y-m-d H:i:00",time()));
                                    $fecha_entrada = strtotime(date($fechaVencimiento." H:i:00"));

                                    if($fecha_actual > $fecha_entrada){
                                        //$recargosOK		   = 	CalculoRecargos($fechaVencimiento, $montopredial);
                                        $actualizacionesOK = AguaController::CalculoActualizacionFecha($fechaVencimiento, $montoconcepto, $fechaActual );
                                    }else{
                                        //$recargosOK		   = 0;
                                        $actualizacionesOK = 0;
                                    }
                                }

                                if($actualizacionesOK>0){
                                    $PagoActualizaciones += $actualizacionesOK;

                                }
                            }
                            if($RegistroMultas->idMulta==2){
                                //Es Multa
                            }
                            if($RegistroMultas->idMulta==3){
                                //Es Recargo

                                if($ConceptoPadre[$iC]['TipoPredio']==3)//3 es para predial
                                {
                                    $tipoPredio = Funciones::ObtenValor("SELECT pc.TipoPredio FROM Cotizaci_on c INNER JOIN Padr_onCatastral pc ON (pc.id=c.Padr_on) WHERE c.id=".$RegistroConcepto->idCotizacion, "TipoPredio");

                                    if($tipoPredio==10)// 10 es zofemat
                                    {
                                       $descripcionActyRec='Actualizaciones y Recargos de: '.$ConceptoPadre[$iC]['Nombre']." Correspondiente al bimestre ".$elmes." del ".$elanio.'';
                                       $montopredial=  $ConceptoPadre[$iC]['Total'];
                                       $mes=($ConceptoPadre[$iC]['Mes']*2)+1;
                                       $anio=$ConceptoPadre[$iC]['A_no'];
                                       if(intval($mes) > 12){
                                            $mes  = 1;
                                            $anio = $anio+1;
                                        }

                                        $mes = str_pad($mes, 2, "0", STR_PAD_LEFT);
                                        $dia = 18;

                                        $fechaVencimiento= $anio."-".$mes."-".$dia;

                                        if(date('D',intval($fechaVencimiento))=="Sat"){
                                            $dia = $dia+2;
                                        }
                                        if(date('D',intval($fechaVencimiento))=="Sun"){
                                             $dia = $dia+1;
                                        }

                                        $fechaVencimiento= $anio."-".$mes."-".$dia;

                                        //exit;
                                        $fecha_actual  = strtotime(date("Y-m-d H:i:00",time()));
                                        $fecha_entrada = strtotime(date($fechaVencimiento." H:i:00"));

                                        if($fecha_actual > $fecha_entrada){
                                            $recargosOK = AguaController::CalculoRecargosFecha($fechaVencimiento, $montopredial, $fechaActual, $cliente );
                                        }else{
                                            $recargosOK = 0;
                                        }
                                    }
                                    else{
                                        $descripcionActyRec = 'Actualizaciones y Recargos de: '.$ConceptoPadre[$iC]['Nombre']." Correspondiente al bimestre ".$elmes." del ".$elanio.'';
                                        //para predial
                                        $montopredial = $ConceptoPadre[$iC]['Total'];
                                        $mes  = ($ConceptoPadre[$iC]['Mes']*2)-1;
                                        $anio = $ConceptoPadre[$iC]['A_no'];

                                        if(intval($mes)>12){
                                           $mes  = 1;
                                           $anio = $anio+1;
                                        }

                                        $mes = str_pad($mes, 2, "0", STR_PAD_LEFT);
                                        $dia = 15;
                                        $fechaVencimiento= $anio."-".$mes."-".$dia;

                                        if(date('D',intval($fechaVencimiento))=="Sat"){
                                            $dia = $dia+2;
                                        }
                                        if(date('D',intval($fechaVencimiento))=="Sun"){
                                            $dia = $dia+1;
                                        }

                                        $fechaVencimiento = $anio."-".$mes."-".$dia;

                                        //exit;
                                        $fecha_actual  = strtotime(date("Y-m-d H:i:00",time()));
                                        $fecha_entrada = strtotime(date($fechaVencimiento." H:i:00"));

                                        if($fecha_actual > $fecha_entrada){
                                            $recargosOK = AguaController::CalculoRecargosFecha($fechaVencimiento, $montopredial, $fechaActual,$cliente );
                                            //$actualizacionesOK = CalculoActualizacion($fechaVencimiento, $montopredial);
                                        }else{
                                            $recargosOK = 0;
                                        }
                                    }
                                }// termina tipo 3 predial
                                else{
                                    //Caso normal que no es predial
                                    $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);

                                    $descripcionActyRec = 'Actualizaciones y Recargos de: '.$ConceptoPadre[$iC]['Nombre'];

                                    $montoconcepto = $ConceptoPadre[$iC]['Total'];
                                    //precode($RegistroConcepto,1);
                                    $mes  = ($fechainicial[1])+1;
                                    $anio = $fechainicial[0];

                                    if($ConceptoPadre[$iC]['TipoPredio'] == 9){
                                        $auxMes = array("", "Enero", "Febrero","Marzo","Abril","Mayo","Junio","Julio", "Agosto","Septiembre","Octubre","Noviembre","Diciembre");
                                        $descripcionActyRec='Actualizaciones y Recargos de: '.$ConceptoPadre[$iC]['Nombre']." Correspondiente al mes de ".$auxMes[$fechainicial[1]]." del ".$fechainicial[0].'';
                                    }
                                    if(intval($mes)>12){
                                        $mes  = 1;
                                        $anio = $anio+1;
                                    }

                                    $mes = str_pad($mes, 2, "0", STR_PAD_LEFT);
                                    $dia = 18;
                                    if(isset($FechaLimiteCotizaci_on) && $FechaLimiteCotizaci_on!=""){
                                        $arr = explode("-",$FechaLimiteCotizaci_on);
                                        $dia = $arr[2];
                                       /* if(isset($cliente) && $cliente==20)
                                            $fechaActual= nuevaFecha($fechaVencimiento);*/
                                    }
                                    $fechaVencimiento= $anio."-".$mes."-".$dia;

                                    if(date('D',intval($fechaVencimiento))=="Sat"){
                                       $dia = $dia+2;
                                    }
                                    if(date('D',intval($fechaVencimiento))=="Sun"){
                                        $dia = $dia+1;
                                    }

                                    $fechaVencimiento = $anio."-".$mes."-".$dia;
                                    $fecha_actual     = strtotime(date("Y-m-d H:i:00",time()));
                                    $fecha_entrada    = strtotime(date($fechaVencimiento." H:i:00"));

                                    if($fecha_actual > $fecha_entrada){
                                        $fechaVencimiento.$montoconcepto;
                                        $recargosOK	= AguaController::CalculoRecargosFecha($fechaVencimiento, $montoconcepto, $fechaActual, $cliente );
                                        //$actualizacionesOK = CalculoActualizacion($fechaVencimiento, $montoconcepto);
                                    }else{
                                        $recargosOK	= 0;
                                    }
                                }

                                $recargosActualizaciones[] = ( round( $actualizacionesOK, 2) + round( $recargosOK, 2 ) );

                                if($actualizacionesOK<=0)
                                    $actualizacionesOK=0;

                                $ActualizacionesYRecargosConcepto=array('Actualizaciones'=>round( $actualizacionesOK, 2),'Recargos'=>round( $recargosOK, 2 ) );
                            }
                        }
                    }
                }//if si es fecha valida
            }
        }//for

        return $ActualizacionesYRecargosConcepto;
    }


    public static function ObtenerDescuentoConceptoRecibo($Cotizaci_on){

       $TotalAPagar=0;
       $conceptos="";
       $TotalCotizaci_on= Funciones::ObtenValor("SELECT sum(Importe) as Total FROM ConceptoAdicionalesCotizaci_on  WHERE Cotizaci_on IN (".$Cotizaci_on. ") ",'Total');
       $TotalAPagar=$TotalCotizaci_on;
       $obtenerXMLIngreso = Funciones::ObtenValor("SELECT * FROM XMLIngreso WHERE idCotizaci_on IN (".$Cotizaci_on.")");

      $DatosExtraDescuento=json_decode($obtenerXMLIngreso->DatosExtra, true);
      $ConsultaConeptos = "SELECT id,Importe,Mes,A_no FROM ConceptoAdicionalesCotizaci_on  WHERE Cotizaci_on IN (".$Cotizaci_on.") AND Origen!='PAGO'";
      $ResultadoConceptos = DB::select($ConsultaConeptos);

      /*Consulta para ver el Privilegio descuento */
      $ConsultaPadr_on = "SELECT p.PrivilegioDescuento FROM Padr_onAguaPotable p INNER JOIN Cotizaci_on c ON(p.id=c.Padr_on AND c.Tipo=9) WHERE c.id IN (".$Cotizaci_on.") ";
       $ResultadoPadr_on= Funciones::ObtenValor($ConsultaPadr_on,'PrivilegioDescuento');
      $TotalAPagar=$TotalAPagar- floatval($DatosExtraDescuento['Descuento']);
      if(($DatosExtraDescuento['Descuento']!="" && $DatosExtraDescuento['Descuento']!=0) || ($ResultadoPadr_on!=0)){
      if($ResultadoPadr_on==0){
      $Descuentos = array();
      $DescuentoAcumulados = 0;
               $Descuento2=0;
               $descuento=0;
              $ultimoConcepto=0;
              $ultimoDescuento=0;
                  foreach ($ResultadoConceptos as $RegistroConceptos) {
                       $descuento = number_format(($RegistroConceptos->Importe/$TotalCotizaci_on)*$DatosExtraDescuento['Descuento'], 2);
                       if(isset($RegistroConceptos->A_no) && $RegistroConceptos->A_no!="" && isset($RegistroConceptos->A_no) && $RegistroConceptos->A_no!="")
                        $Descuento2= AguaController::DescuentoPorMesRecibo($RegistroConceptos->A_no,$RegistroConceptos->Mes,($RegistroConceptos->Importe-$descuento),$Cotizaci_on);

                       $DescuentoAcumulados+=$descuento;
                       $arr[$RegistroConceptos->id] =$descuento+$Descuento2;
                       $TotalAPagar=$TotalAPagar-$arr[$RegistroConceptos->id];
                       $conceptos.=$arr[$RegistroConceptos->id].",";
                       $ultimoConcepto=$RegistroConceptos->id;
                       $ultimoDescuento=$descuento+$Descuento2;

                  }
         if($DescuentoAcumulados!=$DatosExtraDescuento['Descuento']){

              $arr[$ultimoConcepto] = $ultimoDescuento-($DescuentoAcumulados-$DatosExtraDescuento['Descuento']);

         }
      }else if($ResultadoPadr_on!=0){

                  foreach ( $ResultadoConceptos as $RegistroConceptos) {
                        $Descuento2 = 0;
                       if(isset($RegistroConceptos->A_no) && $RegistroConceptos->A_no!="" && isset($RegistroConceptos->A_no) && $RegistroConceptos->A_no!="")
                        $Descuento2= AguaController::DescuentoPorMesRecibo($RegistroConceptos->A_no,$RegistroConceptos->Mes,($RegistroConceptos->Importe),$Cotizaci_on);
                       if($Descuento2==0)
                           $Descuento2 = $RegistroConceptos->Importe;
                       $arr[$RegistroConceptos->id] =$RegistroConceptos->Importe-$Descuento2;
                       $TotalAPagar =$TotalAPagar-$arr[$RegistroConceptos->id];
                        $conceptos.=$arr[$RegistroConceptos->id].",";


                  }

      }
      }else{
          foreach ($ResultadoConceptos as $RegistroConceptos ) {
              $arr[$RegistroConceptos->id] =0;
                $conceptos.=$arr[$RegistroConceptos->id].",";
          }
      }

       $arr['ImporteNetoADescontar'] = $TotalAPagar;
        $arr['Conceptos'] = $conceptos;
           return $arr;
    }

    public static function DescuentoPorMesRecibo($A_noConcepto,$MesConcepto,$MotoNeto,$Cotizaci_on){
        $diaLimite = 18;

         $SeAplicaraElDescuento = 0;
        /*Esto nos servira para hacerle descuento por mes de los que tengan INPAM etc etc etc*/
        $CotizacionDescuentoPorMes= Funciones::ObtenValor("SELECT p.PrivilegioDescuento FROM Cotizaci_on c INNER JOIN Padr_onAguaPotable p ON(c.Padr_on=p.id AND c.Tipo=9) WHERE c.id IN (".$Cotizaci_on. ") ");
       // precode($CotizacionDescuentoPorMes,1);
           $DescuentoPorMes=0;
        if(isset($CotizacionDescuentoPorMes->PrivilegioDescuento) && $CotizacionDescuentoPorMes->PrivilegioDescuento!=0  && $CotizacionDescuentoPorMes->PrivilegioDescuento!=""){
           $SeAplicaraElDescuento = 0;


           $FechaActualParaDescuento=date('Y-m-d');
            $FechaActualParaDescuento =explode('-', $FechaActualParaDescuento);
             $a_noActual  = $FechaActualParaDescuento[0];
              $mesActual  = $FechaActualParaDescuento[1];
              $diaActual  = $FechaActualParaDescuento[2];

                  if($CotizacionDescuentoPorMes->PrivilegioDescuento!=0){
                         $SeAplicaraElDescuento = 1;
                          }
                          $A_noADescontar = $a_noActual;
                          $MesADescontar =20;
                          if($diaActual>$diaLimite && $mesActual == $MesConcepto && $a_noActual == $A_noConcepto ){
                              $buscarMesVigente = 1;//Cero de que no va aplicar ya se caduco
                              #Los meses se quedan normales
                              $MesADescontar =$mesActual;
                           }else if($diaActual<$diaLimite && $mesActual-1 == $MesConcepto && $a_noActual == $A_noConcepto){
                              $buscarMesVigente =1;
                              $MesADescontar =$mesActual-1;
                           }
                              $DescuentoPorMes = 0;
                           if($MesConcepto==$MesADescontar && $A_noConcepto==$A_noADescontar){
                              $DescuentoPorMes = $MotoNeto>0?$MotoNeto/2:$MotoNeto;


                           }


        }
         return $DescuentoPorMes;
    }



    public static function ObtenerDescuentoPorSaldoAnticipadoRecibo($Cotizaci_on,$importe,$Conceptos, $cliente){

        $actualizacionesYRecargos = 0;
         $actualizacionesYRecargos = AguaController::ObtenerRecargosYActualizacionesCotizaci_onRecibo($Cotizaci_on, $cliente);
        $descuentos= explode(',',$Conceptos);
      $Cotizacion= Funciones::ObtenValor("SELECT * FROM  Cotizaci_on WHERE id IN( ".$Cotizaci_on. ") ");
     // precode($Cotizacion,1);
      $TotalSaldo=0;
   if(isset($Cotizacion->Padr_on) && $Cotizacion->Padr_on!=""){
       if($Cotizacion->Tipo==9){
   $TotalSaldo = Funciones::ObtenValor("SELECT SaldoNuevo as Total FROM Padr_onAguaHistoricoAbono  WHERE idPadron=".$Cotizacion->Padr_on." ORDER BY id DESC",'Total');
      if($TotalSaldo<=0){
          $TotalSaldo=0;
      }
   }else{
        $TotalSaldo=0;
       }

   }

   $TotalCotizaci_on= Funciones::ObtenValor("SELECT sum(Importe) as Total FROM ConceptoAdicionalesCotizaci_on  WHERE Cotizaci_on IN (".$Cotizaci_on. ")  ",'Total');
      $TotalCotizaci_on = str_replace(",", "",$importe)+str_replace(",", "",$actualizacionesYRecargos);
      $TotalCotizaci_on= str_replace(",", "",$TotalCotizaci_on);
   if($TotalSaldo>$TotalCotizaci_on)
        $TotalSaldo=$TotalCotizaci_on;

  $ConsultaConeptos = "SELECT id,Importe FROM ConceptoAdicionalesCotizaci_on  WHERE Cotizaci_on IN (".$Cotizaci_on.") AND Origen!='PAGO' AND Estatus=0 ";
  $ResultadoConceptos = DB::select($ConsultaConeptos);

  if($TotalSaldo>0){
  $Descuentos = array();
  $DescuentoAcumulados = 0;
           $contador=0;
          $ultimoConcepto=0;
          $ultimoDescuento=0;
              foreach ( $ResultadoConceptos as $RegistroConceptos ) {
       /*Actualizaci_ones y recargos */
                  $ayr = 0;

                      $actualizacionesYRecargos = AguaController::ObtenerRecargosYActualizacionesPorConceptoRecibo($RegistroConceptos->id, $Cotizaci_on, $cliente);

                      //precode($actualizacionesYRecargos,1);
                      $ayr=$actualizacionesYRecargos['Actualizaciones']+$actualizacionesYRecargos['Recargos'];
                   $descuento = number_format(((($RegistroConceptos->Importe+$ayr-$descuentos[$contador])/$TotalCotizaci_on)*$TotalSaldo), 2);
                   $DescuentoAcumulados=str_replace(",", "",$DescuentoAcumulados);
                   $DescuentoAcumulados+=str_replace(",", "",$descuento);
                   $arr[$RegistroConceptos->id] = str_replace(",", "",$descuento);
                   $ultimoConcepto=$RegistroConceptos->id;
                   $ultimoDescuento=$descuento;
                   $contador++;
              }


     if($DescuentoAcumulados!=$TotalSaldo){
          $arr[$ultimoConcepto] = number_format(str_replace(",", "",$ultimoDescuento)-(str_replace(",", "",$DescuentoAcumulados)-str_replace(",", "",$TotalSaldo)),2);

     }

  }else{
      foreach ( $ResultadoConceptos as $RegistroConceptos) {
          $arr[$RegistroConceptos->id] =number_format(0,2);
      }
  }
       return $arr;
}


public static function ObtenerRecargosYActualizacionesCotizaci_onRecibo($Cotizaci_on, $cliente){
    global $Conexion;

    $idCotizacion =$Cotizaci_on;
    $ConsultaConceptos = "SELECT c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad, c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario,
            (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional, co.A_no, COALESCE(co.Mes, '01') as Mes, ct.Tipo, c.TipoToma
        FROM ConceptoAdicionalesCotizaci_on co
            INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
            INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )
        WHERE  co.Cotizaci_on IN (" . $idCotizacion . ") and Estatus=0 ORDER BY co.A_no DESC, COALESCE(co.Mes, '01') DESC ,  co.id ASC ";
    #precode($ConsultaConceptos,1);
    $ResultadoConcepto = DB::select($ConsultaConceptos);
    $ConceptosCotizados = '';
    $totalConcepto = 0;
    $indexConcepto = 0;
    setlocale(LC_TIME, "es_MX.UTF-8");
    //Leermos el primer concepto.
    $RegistroConcepto = $ResultadoConcepto[0];

    $totalConcepto                                  = $RegistroConcepto->total;
    $ConceptosCotizados                             = $RegistroConcepto->idConceptoCotizacion . ',';
    $ConceptoPadre[$indexConcepto]['id']            = $RegistroConcepto->idConceptoCotizacion;
    $ConceptoPadre[$indexConcepto]['Mes']           = $RegistroConcepto->Mes;
    $ConceptoPadre[$indexConcepto]['A_no']          = $RegistroConcepto->A_no;
    $ConceptoPadre[$indexConcepto]['Total']         = 0;
    $ConceptoPadre[$indexConcepto]['Nombre']        = $RegistroConcepto->NombreConcepto;
    $ConceptoPadre[$indexConcepto]['TipoPredio']    = $RegistroConcepto->Tipo;
    $ConceptoPadre[$indexConcepto]['idConcepto']    = $RegistroConcepto->ConceptoCobro;
    $ConceptoPadre[$indexConcepto]['FechaConcepto'] = $RegistroConcepto->A_no . "-" . $RegistroConcepto->Mes . "-01";

    $recargosActualizaciones = array();
     $i=0;
    foreach ( $ResultadoConcepto as $RegistroConcepto) {
        //precode($RegistroConcepto,1);
        if($i != 0){
            if (empty($RegistroConcepto->Adicional)) {
                //Es concepto
                $ConceptoPadre[$indexConcepto]['Total'] = $totalConcepto;
                $totalConcepto = $RegistroConcepto->total;
                $indexConcepto++;

                $ConceptoPadre[$indexConcepto]['id']            = $RegistroConcepto->idConceptoCotizacion;
                $ConceptoPadre[$indexConcepto]['Mes']           = $RegistroConcepto->Mes;
                $ConceptoPadre[$indexConcepto]['A_no']          = $RegistroConcepto->A_no;
                $ConceptoPadre[$indexConcepto]['Nombre']        = $RegistroConcepto->NombreConcepto;
                $ConceptoPadre[$indexConcepto]['TipoPredio']    = $RegistroConcepto->Tipo;
                $ConceptoPadre[$indexConcepto]['idConcepto']    = $RegistroConcepto->ConceptoCobro;
                $ConceptoPadre[$indexConcepto]['FechaConcepto'] = $RegistroConcepto->A_no . "-" . $RegistroConcepto->Mes . "-01";
            } else {//Es adicional
                $totalConcepto += $RegistroConcepto->total;
            }
            $ConceptosCotizados .= $RegistroConcepto->idConceptoCotizacion . ',';
      }
      $i++;
    }
    $ConceptoPadre[$indexConcepto]['Total'] = $totalConcepto;

    //Buscamos actualizaciones y recargos para los conceptos a pagar
    $PagoActualizaciones = 0;
    $ActualizacionesYRecargos = "";
    $fechaActual = date("Y-m-d");
    $auxMes = array("", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");

    for ($iC = 0; $iC < count($ConceptoPadre); $iC++) {
        $ConceptoExcluidosParaActualizacionesRecargos = array(5461, 5462, 5467, 5469, 2489, 5084);
        if (!in_array($ConceptoPadre[$iC]['id'], $ConceptoExcluidosParaActualizacionesRecargos)) {

            if ($ConceptoPadre[$iC]['FechaConcepto'] != "--01") {
                if (date("Y-m", strtotime($fechaActual)) > date('Y-m', strtotime($ConceptoPadre[$iC]['FechaConcepto']))) {
                    //Obtenemos las multas del concepto
                    $ConsultaMultas = " SELECT mi.Categor_ia as Categoria, m.id as idMulta, m.Descripci_on as DescripcionMulta, c.id as idConcepto, c.Descripci_on as DescripcionConcepto
                        FROM MultaCategor_ia mi
                            INNER JOIN Multa m ON ( mi.Multa = m.id  )
                            INNER JOIN ConceptoCobroCaja c ON ( mi.Concepto = c.id  )
                        WHERE mi.Categor_ia = (select Categor_ia from ConceptoCobroCaja where id = " . $ConceptoPadre[$iC]['id'] . ")";

                    $ResultadoMultas = DB::select($ConsultaMultas);

                    foreach ( $ResultadoMultas as $RegistroMultas) {
                        $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);
                        $elmes        = $fechainicial[1];
                        $elanio       = $fechainicial[0];

                        if ($RegistroMultas->idMulta == 1) {

                            if ($ConceptoPadre[$iC]['TipoPredio'] == 3) {
                                //3 es para predial
                            } else { //Caso normal que no es predial
                                $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);

                                $montoconcepto =  $ConceptoPadre[$iC]['Total'];

                                $mes  = ($fechainicial[1]) + 1;
                                $anio = $fechainicial[0];

                                if (intval($mes) > 12) {
                                    $mes  = 1;
                                    $anio = $anio + 1;
                                }

                                $mes = str_pad($mes, 2, "0", STR_PAD_LEFT);
                                $dia = 18;
                                $fechaVencimiento = $anio . "-" . $mes . "-" . $dia;

                                if (date('D', intval($fechaVencimiento)) == "Sat") {
                                    $dia = $dia + 2;
                                }
                                if (date('D', intval($fechaVencimiento)) == "Sun") {
                                    $dia = $dia + 1;
                                }

                                $fechaVencimiento = $anio . "-" . $mes . "-" . $dia;
                                $fecha_actual     = strtotime(date("Y-m-d H:i:00", time()));
                                $fecha_entrada    = strtotime(date($fechaVencimiento . " H:i:00"));

                                if ($fecha_actual > $fecha_entrada) {
                                    $actualizacionesOK = AguaController::CalculoActualizacionFecha($fechaVencimiento, $montoconcepto, $fechaActual);
                                } else {
                                    $actualizacionesOK = 0;
                                }
                            }

                            if ($actualizacionesOK > 0) {
                                $PagoActualizaciones += $actualizacionesOK;
                            }
                        }

                        if ($RegistroMultas->idMulta == 2) { //Es Multa
                        }

                        if ($RegistroMultas->idMulta == 3) {
                            //Es Recargo
                            if ($ConceptoPadre[$iC]['TipoPredio'] == 3) {
                                //3 es para predial
                            } else {
                                //Caso normal que no es predial
                                $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);

                                $montoconcepto =  $ConceptoPadre[$iC]['Total'];

                                $mes  = ($fechainicial[1]) + 1;
                                $anio = $fechainicial[0];

                                if ($ConceptoPadre[$iC]['TipoPredio'] == 9) { }

                                if (intval($mes) > 12) {
                                    $mes  = 1;
                                    $anio = $anio + 1;
                                }

                                $mes = str_pad($mes, 2, "0", STR_PAD_LEFT);
                                $dia = 18;

                                $fechaVencimiento = $anio . "-" . $mes . "-" . $dia;

                                if (date('D', intval($fechaVencimiento)) == "Sat") {
                                    $dia = $dia + 2;
                                }
                                if (date('D', intval($fechaVencimiento)) == "Sun") {
                                    $dia = $dia + 1;
                                }

                                $fechaVencimiento = $anio . "-" . $mes . "-" . $dia;
                                $fecha_actual     = strtotime(date("Y-m-d H:i:00", time()));
                                $fecha_entrada    = strtotime(date($fechaVencimiento . " H:i:00"));

                                if ($fecha_actual > $fecha_entrada) {
                                    $fechaVencimiento . $montoconcepto;
                                    $recargosOK = AguaController::CalculoRecargosFechaAgua($fechaVencimiento, $montoconcepto, $fechaActual, $cliente);
                                } else {
                                    $recargosOK = 0;
                                }
                            }
                            $recargosActualizaciones[] = (round($actualizacionesOK, 2) + round($recargosOK, 2));
                        }
                    }
                }
            } //if si es fecha valida
        }
    }//for

    return array_sum($recargosActualizaciones);
}

public static function CalculoActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL){
    //Es Recargo
    if(is_null($fechaActualArg)){
        $fechaActualArg=date('Y-m-d');
    }


    //Es Actualizacion
    $fechaHoy=$fechaActualArg;
    #$fechaHoy= date("Y-m-d", strtotime ( '-1 month' , strtotime (  date('Y-m-d') )) );
     #$fechaConcepto= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaConcepto )) );

#	precode($fechaHoy,1);
#	precode($fechaConcepto,1);
    $Recargoschecked="";
    $mesConocido=1;
    while(true){
         $fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ( $fechaConcepto ) ) ;
        setlocale(LC_TIME,"es_MX.UTF-8");
        $mes = ucwords(strftime("%B", $fecha ));
        $a_no = strftime("%Y", $fecha );
        #precode($a_no."-".$mes,1);
        $INPCCotizacion=Funciones::ObtenValor("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,$mes);

        if(empty($INPCCotizacion) || $INPCCotizacion=='NULL')
            $mesConocido++;
        else
            break;
    }

    $mesConocido=1;
    while(true){
        $fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ($fechaHoy)) ;
        setlocale(LC_TIME,"es_MX.UTF-8");
        $mes = ucwords(strftime("%B", $fecha ));
        $a_no = strftime("%Y", $fecha );
        #precode($a_no."-".$mes,1);
        #precode("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,1,1);
        $INPCPago=Funciones::ObtenValor("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,$mes);

        if(empty($INPCPago) || $INPCPago=='NULL')
            $mesConocido++;
        else
            break;
    }

            $FactorActualizacion=$INPCPago/$INPCCotizacion;

    if($FactorActualizacion<1){
        $FactorActualizacion=1;
    }

    $Actualizacion=($ImporteConcepto*$FactorActualizacion)-$ImporteConcepto;


    return  $Actualizacion;
}


public static function CalculoRecargosFechaAgua($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL , $cliente){
	//Es Recargo
	if(is_null($fechaActualArg)){
		$fechaActualArg=date('Y-m-d');
	}
        $Actualizacion = AguaController::CalculoActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg);
        $FactorActualizacion=AguaController::CalculofactorActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg);


	$mesConocido=0;
	$SumaDeTasa=0;

        $fechaHoy= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaActualArg )) );
        $fechaConcepto= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaConcepto )) );

	$fechafinal = explode('-', $fechaHoy);
	$fechainicial = explode('-', $fechaConcepto);

	$fechainicialdif = new DateTime($fechaConcepto);
	$fechafinaldif = new DateTime($fechaHoy);
	$elmes=$fechainicial[1];
	$elanio=$fechainicial[0];
	$diferencia = $fechainicialdif->diff($fechafinaldif);
	$meses = ( $diferencia->y * 12 ) + $diferencia->m;

	while($mesConocido<=$meses){
		$fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ( $fechaHoy)) ;
		setlocale(LC_TIME,"es_MX.UTF-8");
		$mes = (date("m", $fecha ));
		$a_no = strftime("%Y", $fecha );

		$SumaDeTasa+=floatval(Funciones::ObtenValor("select Recargo from PorcentajeRecargo where A_no=".$a_no." and Cliente=".$cliente." and Mes=".$mes,"Recargo"));
		$mesConocido++;
	}

	if($Actualizacion>0)
            $Recargo=(($ImporteConcepto*$FactorActualizacion)*round($SumaDeTasa, 2))/100;
	else
            $Recargo=(($ImporteConcepto)*$SumaDeTasa)/100;

	return $Recargo;
}



public static function CalculofactorActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL){

	if(is_null($fechaActualArg)){
		$fechaActualArg=date('Y-m-d');
	}


		//Es Actualizacion
		$fechaHoy=$fechaActualArg;
		#$fechaHoy= date("Y-m-d", strtotime ( '-1 month' , strtotime (  date('Y-m-d') )) );
	 	#$fechaConcepto= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaConcepto )) );

	#	precode($fechaHoy,1);
	#	precode($fechaConcepto,1);
		$Recargoschecked="";
		$mesConocido=1;
		while(true){
			 $fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ( $fechaConcepto ) ) ;
			setlocale(LC_TIME,"es_MX.UTF-8");
			$mes = ucwords(strftime("%B", $fecha ));
			$a_no = strftime("%Y", $fecha );
			#precode($a_no."-".$mes,1);
			$INPCCotizacion=Funciones::ObtenValor("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,$mes);

			if(empty($INPCCotizacion) || $INPCCotizacion=='NULL')
				$mesConocido++;
			else
				break;
		}

		$mesConocido=1;
		while(true){
			$fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ($fechaHoy)) ;
			setlocale(LC_TIME,"es_MX.UTF-8");
			$mes = ucwords(strftime("%B", $fecha ));
			$a_no = strftime("%Y", $fecha );
			#precode($a_no."-".$mes,1);
			#precode("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,1,1);
			$INPCPago=Funciones::ObtenValor("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,$mes);

			if(empty($INPCPago) || $INPCPago=='NULL')
				$mesConocido++;
			else
				break;
		}
                $FactorActualizacion=$INPCPago/$INPCCotizacion;

                if($FactorActualizacion<1){
			$FactorActualizacion=1;
		}

		return $FactorActualizacion;

    }


    public static function CalculoRecargosFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL, $cliente=0){

        //Es Recargo
        if(is_null($fechaActualArg)){
            $fechaActualArg=date('Y-m-d');
        }

        $Actualizacion		=AguaController::CalculoActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg);
        $FactorActualizacion=AguaController::CalculofactorActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg);


        $mesConocido=0;
        $SumaDeTasa=0;

            $fechaHoy= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaActualArg )) );
            $fechaConcepto= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaConcepto )) );

        #precode($fechaConcepto,1);
        #precode($fechaHoy,1);
        //Calculamos el numero de meses que hay entre las 2 fechas
        //$fechainicial = explode('-', substr($RegistroCotizacion['FechaCotizacion'], 0, 10));
        //echo "<br />". $fechaHoy." - ".$fechaConcepto."<br />";
        $fechafinal = explode('-', $fechaHoy);
        $fechainicial = explode('-', $fechaConcepto);
        #precode($fechafinal,1);
        #precode($fechainicial,1);
        $fechainicialdif = new DateTime($fechaConcepto);
        $fechafinaldif = new DateTime($fechaHoy);
        $elmes=$fechainicial[1];
        $elanio=$fechainicial[0];
        $diferencia = $fechainicialdif->diff($fechafinaldif);
        $meses = ( $diferencia->y * 12 ) + $diferencia->m;

        #echo "Meses:".$meses;
        //$meses = $fechafinal[1]-$fechainicial[1];
        //$meses-=2;
        #$meses+=1;
        //$mesConocido=$fechainicial[1];
        //Recorremos cada uno de los meses.
        #precode($mesConocido,1);
        #precode($meses,1);
        while($mesConocido<=$meses){
            //echo "fecha:".$meses."<br />";
            $fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ( $fechaHoy)) ;
            setlocale(LC_TIME,"es_MX.UTF-8");
            $mes = (date("m", $fecha ));
            $a_no = strftime("%Y", $fecha );
            //echo "<br />".ObtenValor("select Recargo from PorcentajeRecargo where A_no=".$a_no." and Mes=".$mes,"Recargo")." ----- "."select Recargo from PorcentajeRecargo where A_no=".$a_no." and Mes=".$mes."<br />";
            #echo $a_no."-".$mes."<br />";
            $SumaDeTasa+= floatval(Funciones::ObtenValor("select Recargo from PorcentajeRecargo where A_no=".$a_no." and Cliente=".$cliente." and Mes=".$mes,"Recargo"));
            $mesConocido++;
        }
        #echo "<br />Suma Tasa:".$SumaDeTasa;
        //Calculamos los recargos
        //$ImporteConcepto*$FactorActualizacion;
        if($Actualizacion>0)
            $Recargo=(($ImporteConcepto*$FactorActualizacion)*round($SumaDeTasa, 2))/100;
        else
            $Recargo=(($ImporteConcepto)*$SumaDeTasa)/100;
        #echo "<br />".$Recargo;
        return $Recargo;


}


public function cotizarServiciosAguaPotable(Request $request)
{


    $Cliente = $request->Cliente;
    $IdPadron = $request->IdPadron;
    $anio=date('Y');
    $Concepto []=$request->Concepto;
    Funciones::selecionarBase($Cliente);
    $Padron=Funciones::ObtenValor("select *  from Padr_onAguaPotable where id=".$IdPadron);

    $clienteClave = Funciones::ObtenValor('select Clave from Cliente where id=' .$Cliente, 'Clave');
    $UltimaCotizacion = Funciones::ObtenValor("select FolioCotizaci_on from Cotizaci_on where FolioCotizaci_on like '".$anio.$clienteClave."%' order by FolioCotizaci_on desc limit 0,1", "FolioCotizaci_on");
    $precioUnitario=json_decode(AguaController::ObtieneImporteyConceptosOP($Cliente,$Concepto[0], $Padron->id,1));

    $BaseCalculo=Funciones::ObtenValor("SELECT  c3.BaseCalculo as TipobaseCalculo
    FROM ConceptoCobroCaja c
    INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto  )
    INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales  )
    INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente  )
    WHERE c3.Cliente=".$Cliente." AND c3.EjercicioFiscal=".$anio." AND  c2.Cliente=".$Cliente." AND c.id = ".$Concepto[0],"TipobaseCalculo");


    if($UltimaCotizacion=="NULL"|| $UltimaCotizacion==""){

        $N_umeroDeCotizacion=$anio.$clienteClave .str_pad(1, 8, '0', STR_PAD_LEFT);
    }else{

        $N_umeroDeCotizacion=$anio.$clienteClave .str_pad(intval(substr($UltimaCotizacion, -8, 8))+1, 8, '0', STR_PAD_LEFT);
    }

    $Fondo=Funciones::ObtenValor(" select
        PresupuestoAnualPrograma.Fondo as Fondo
        FROM PresupuestoAnualPrograma
        INNER JOIN Fondo ON (Fondo.id = PresupuestoAnualPrograma.Fondo)
            INNER JOIN Cat_alogoDeFondo ON (Cat_alogoDeFondo.id=Fondo.CatalogoFondo)
        INNER JOIN PresupuestoGeneralAnual ON (Fondo.Presupuesto= PresupuestoGeneralAnual.id)
        WHERE
        (select Descripci_on from Cat_alogoPrograma where
        PresupuestoAnualPrograma.Programa=Cat_alogoPrograma.id) LIKE '%ingresos propios%' AND
        Cliente=".$Cliente." AND EjercicioFiscal=".$anio,"Fondo");

    $FuenteFinanciamiento=Funciones::ObtenValor("SELECT f1.id as FuenteFinanciamiento
        FROM Fondo f
            INNER JOIN Cat_alogoDeFondo ca ON ( f.CatalogoFondo = ca.id  )
                INNER JOIN FuenteFinanciamiento f1 ON ( ca.FuenteFinanciamiento = f1.id  )
        WHERE f.id = ".$Fondo, "FuenteFinanciamiento");

    $CatalogoFondo= Funciones::ObtenValor('select CatalogoFondo FROM Fondo WHERE id='.$Fondo,'CatalogoFondo' );
    $fecha=date('Y-m-d');
    $fechaCFDI=date('Y-m-d H:i:s');
    $consulta_usuario = Funciones::ObtenValor("SELECT c.idUsuario, c.Usuario
    FROM CelaUsuario c  INNER JOIN CelaRol c1 ON ( c.Rol = c1.idRol  )   WHERE c.CorreoElectr_onico='" . $Cliente . "@gmail.com' ");

    $PresupuestoAnualPrograma=Funciones::ObtenValor("select id, (select Descripci_on from Cat_alogoPrograma where PresupuestoAnualPrograma.Programa=Cat_alogoPrograma.id) FROM PresupuestoAnualPrograma where Fondo=".$Fondo,"id");
    $areaRecaudadora=Funciones::getAreaRecaudadora($Cliente,$anio,$Concepto[0]);

    $ConsultaInserta = sprintf("INSERT INTO Cotizaci_on (  `id` , `FolioCotizaci_on` , `Contribuyente` , `AreaAdministrativa` , `Fecha` , `Cliente`, `Fondo`, `Programa`, `FuenteFinanciamiento`,`Tipo`,`FechaCFDI` ,`Usuario` ,`Padr_on`) VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
         Funciones::GetSQLValueString(NULL, "int unsigned"),
         Funciones::GetSQLValueString($N_umeroDeCotizacion, "varchar") ,
         Funciones::GetSQLValueString($Padron->Contribuyente, "int unsigned") ,
         Funciones::GetSQLValueString($areaRecaudadora, "int unsigned") ,
         Funciones::GetSQLValueString( $fecha, "date"),
         Funciones::GetSQLValueString($Cliente, "int") ,
         Funciones::GetSQLValueString($Fondo, "varchar") ,
         Funciones::GetSQLValueString($PresupuestoAnualPrograma, "varchar") ,
         Funciones::GetSQLValueString($FuenteFinanciamiento, "varchar") ,
         Funciones::GetSQLValueString(16, "int") ,  //tipo 2 Agua
         Funciones::GetSQLValueString($fechaCFDI, "date") ,
         Funciones::GetSQLValueString($consulta_usuario ->idUsuario, "int"),
         Funciones::GetSQLValueString($Padron->id, "int"));

        if( DB::insert($ConsultaInserta)){
            $IdRegistroCotizaci_on = DB::getPdo()->lastInsertId();

           // clve cliente + anio + area recaudadora + cpnsecutivo
            $CajadeCobro=Funciones::ObtenValor("select CajaDeCobro from CelaUsuario where idUsuario=".$consulta_usuario ->idUsuario, "CajaDeCobro");


            $areaRecaudadora = Funciones::ObtenValor("SELECT Clave FROM AreasAdministrativas WHERE id=".$areaRecaudadora, "Clave");

            $UltimoFolio = Funciones::ObtenValor("select Folio from XMLIngreso INNER JOIN Cotizaci_on c ON (c.id=XMLIngreso.idCotizaci_on)  where c.Cliente='".$Cliente."' AND Folio like '%" . $clienteClave . $anio. $areaRecaudadora . "%' order by Folio desc limit 0,1", "Folio");

            $Serie = $clienteClave . $anio . $areaRecaudadora;
            $medoPago=04;
            if ($UltimoFolio == 'NULL') {
                $N_umeroDeFolio = str_pad(1, 8, '0', STR_PAD_LEFT);
            } else {
                $N_umeroDeFolio = str_pad(intval(substr($UltimoFolio, -8, 8)) + 1, 8, '0', STR_PAD_LEFT);
            }
           if(isset($Padron->Municipio)){
            $Municipio=Funciones::ObtenValor("SELECT Nombre FROM Municipio WHERE id=".$Padron->Municipio."","Nombre");
           }else{
            $Municipio=null;
           }
            $TipoToma=Funciones::ObtenValor(" select  Concepto FROM TipoTomaAguaPotable WHERE id=".$Padron->TipoToma,"Concepto");
            $MetodoCobro= Funciones::ObtenValor("select Descripci_on FROM M_etodoCobroAguaPotable WHERE id=".$Padron->M_etodoCobro,"Descripci_on");
            //$arr['Usuario']=$_POST['UserElab'];
            $arr['ContratoVigente']=$Padron->ContratoVigente ;
            $arr['ContratoAnterior']=$Padron->ContratoAnterior;
            $arr['Medidor']=$Padron->Medidor;
            $arr['Domicilio']=$Padron->Domicilio;
            $arr['Municipio']=$Municipio ;
            $arr['Ruta']=$Padron->Ruta ;
            $arr['TipoToma']=$TipoToma;
            $arr['M_etodoCobro']=$MetodoCobro;
            $arr['CuotaFija']=$Padron->Consumo;

            $arr['Usuario'] = Funciones::ObtenValor("SELECT NombreCompleto FROM CelaUsuario WHERE idUsuario=".$consulta_usuario ->idUsuario, "NombreCompleto");
            $arr['Leyenda'] =  "Elaboró";
            $arr['NumCuenta']=null;
            $arr['MPago']="PUE";
            $arr['Observacion'] = "";
            $arr['Descuento']= "";

            $DatosExtra = json_encode($arr, JSON_UNESCAPED_UNICODE);
            //echo "INSERT INTO XMLIngreso (Contribuyente, idCotizaci_on, Folio, MetodoDePago, DatosExtra) VALUES ('".$Padron->Contribuyente."','".$IdRegistroCotizaci_on."', '".$Serie.$N_umeroDeFolio."', '".$_POST['MetodoDePago']."', '".$DatosExtra."') ";

            DB::table('XMLIngreso')->insert([
                [  'Contribuyente'=>$Padron->Contribuyente,
                   'idCotizaci_on'=>$IdRegistroCotizaci_on,
                   'Folio' => $Serie.$N_umeroDeFolio,
                   'MetodoDePago'=>$medoPago,
                   'DatosExtra'=>$DatosExtra,
                ]
           ]);
           $XmlEndozar = DB::getPdo()->lastInsertId();


            $ConsultaInserta = "INSERT INTO ConceptoAdicionalesCotizaci_on (  `id` , `ConceptoAdicionales` , `Cotizaci_on` , `TipoContribuci_on` , `Importe` , `Estatus` , `MomentoCotizaci_on` , `MomentoPago` , `FechaPago` , `CajaDeCobro` , `Xml` , `TipoCobroCaja` , `N_umeroDeOperaci_on` , `CuentaBancaria`, `Adicional`,  `Cantidad`,  `Padre`,  `Mes` ,  `A_no` ,  `Origen`,  `TipoBase`,  `MontoBase`, `Padr_on` ) VALUES ";

            $totalConceptos= count($Concepto);
            $todoslosconceptos=array();
            $m=0;
            $momento=4;
            $mesEnviar=null;
            $A_noEnviar=null;

            for($i=0; $i<$totalConceptos; $i++){
                $todoslosconceptos[$m]["id"]=$Concepto[$i];
                $m++;
                //Recorro cada concepto y hago la consulta
                $ConsultaInserta .= sprintf("(  %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s),",
                 Funciones::GetSQLValueString(NULL, "int unsigned"),
                 Funciones::GetSQLValueString($Concepto[$i], "int unsigned") ,
                 Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int unsigned") ,
                 Funciones::GetSQLValueString(1, "int") , // 1 para concepto o 2 para retencion adicional
                 Funciones::GetSQLValueString(floatval( str_replace(',', '',  $precioUnitario->punitario)), "decimal") , //importe
                 Funciones::GetSQLValueString(0, "int") , //0 para no pagado  1 para pagado
                 Funciones::GetSQLValueString($momento, "int unsigned") , //4 es ley de ingresos devengada
                 Funciones::GetSQLValueString(NULL, "int unsigned") ,//5 es para cuando se paga
                 Funciones::GetSQLValueString(NULL, "datetime") , //fecha de cobro
                 Funciones::GetSQLValueString($CajadeCobro, "int unsigned") ,//caja de cobro
                 Funciones::GetSQLValueString($XmlEndozar, "int unsigned") ,//el XML
                 Funciones::GetSQLValueString(NULL, "int unsigned") ,//TipoCobroCaja
                 Funciones::GetSQLValueString(NULL, "varchar") ,//numero de operacion
                 Funciones::GetSQLValueString(NULL, "int unsigned") , // cuenta bancaria
                 Funciones::GetSQLValueString(NULL, "int unsigned"), //adicional
                 Funciones::GetSQLValueString(1, "int unsigned"),//cantidad
                 Funciones::GetSQLValueString(NULL, "int"), //Padre
                 Funciones::GetSQLValueString($mesEnviar, "int"), //Mes
                 Funciones::GetSQLValueString($A_noEnviar, "int"), //A_no
                 Funciones::GetSQLValueString("Cotizacion OPD", "varchar"), //Origen
                 Funciones::GetSQLValueString(NULL, "int unsigned"), //tipoBase
                 Funciones::GetSQLValueString($BaseCalculo, "decimal"), //MontoBase
                 Funciones::GetSQLValueString($Padron->id, "int") ); //idPadron
                //Actualiza a pagado el dato de Status en PadronAguaLectura
                //$ActualizaLecturaAPagado = "UPDATE Padr_onDeAguaLectura SET Status=1 WHERE Padr_onAgua=".$_POST['idPadron']." AND Mes=".$mesEnviar." AND A_no=".$A_noEnviar;
                // $Conexion->query($ActualizaLecturaAPagado);
                //echo "<br />indice:".$i;
                //obtengo los adicionales del concepto actual
                $datosAdicionales = AguaController::ObtieneImporteyConceptos2OP($Cliente,$Concepto[$i],$BaseCalculo );


                //precode($datosAdicionales,1);
                //echo "<pre>".print_r($datosAdicionales, true)."</pre>";
                //exit;
                //agrego a la consulta los adicionales
                for($k=1; $k<=$datosAdicionales['NumAdicionales']; $k++ ){

                    $ConsultaInserta .= sprintf("(  %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s),",
                         Funciones::GetSQLValueString(NULL, "int unsigned"),
                         Funciones::GetSQLValueString($Concepto[$i], "int unsigned") ,
                         Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int unsigned") ,
                         Funciones::GetSQLValueString(2, "int") , // 1 para concepto o 2 para retencion adicional
                         Funciones::GetSQLValueString(floatval( str_replace(',', '', $datosAdicionales['adicionales'.$k]['Resultado'])), "decimal") , //importe
                         Funciones::GetSQLValueString(0, "int") , //0 para no pagado  1 para pagado
                         Funciones::GetSQLValueString($momento, "int unsigned") , //4 es ley de ingresos devengada
                         Funciones::GetSQLValueString(NULL, "int unsigned") ,//5 es para cuando se paga
                         Funciones::GetSQLValueString(NULL, "datetime") , //fecha de cobro
                         Funciones::GetSQLValueString($CajadeCobro, "int unsigned") ,//caja de cobro
                         Funciones::GetSQLValueString($XmlEndozar, "int unsigned") ,//el XML
                         Funciones::GetSQLValueString(NULL, "int unsigned") ,//TipoCobroCaja
                         Funciones::GetSQLValueString(NULL, "varchar") ,//numero de operacion
                         Funciones::GetSQLValueString(NULL, "int unsigned"), //cuenta bancaria
                         Funciones::GetSQLValueString($datosAdicionales['adicionales'.$k]['idAdicional'], "int unsigned"),//adicional
                         Funciones::GetSQLValueString(1, "int unsigned"), //cantidad
                         Funciones::GetSQLValueString(NULL, "int"), //Padre
                         Funciones::GetSQLValueString($mesEnviar, "int"), //Mes
                         Funciones::GetSQLValueString($A_noEnviar, "int"), //A_no
                         Funciones::GetSQLValueString("Cotizacion OPD", "varchar"), //Origen
                         Funciones::GetSQLValueString($datosAdicionales['adicionales'.$k]['TipoBase'], "int unsigned"),
                         Funciones::GetSQLValueString($datosAdicionales['adicionales'.$k]['MontoBase'], "int unsigned"),
                         Funciones::GetSQLValueString($Padron->id, "int") ); //idPadron

                }

            }
            //exit;
            $ConsultaInserta=substr_replace($ConsultaInserta,";",-1);
            //echo $ConsultaInserta;
            if(DB::insert($ConsultaInserta)){



                //AGREGO TODOS LOS DATOS A LA CONTABILIDAD
                $DatosEncabezado=Funciones::ObtenValor("select N_umeroP_oliza from EncabezadoContabilidad where Cotizaci_on=".$IdRegistroCotizaci_on,"N_umeroP_oliza");
                $Programa=Funciones::ObtenValor("SELECT Programa FROM PresupuestoAnualPrograma WHERE id=".$PresupuestoAnualPrograma, "Programa");
                if( $DatosEncabezado=="NULL" || $DatosEncabezado==""){
                    $UltimaPoliza=Funciones::ObtenValor("select N_umeroP_oliza as Ultimo from EncabezadoContabilidad where N_umeroP_oliza like '".$anio.$clienteClave.str_pad($Programa, 3, '0', STR_PAD_LEFT)."03%' order by N_umeroP_oliza desc limit 0,1","Ultimo");

                    if($UltimaPoliza=='NULL' || $UltimaPoliza=="")
                        $N_umeroDePoliza=$anio.$clienteClave.str_pad($Programa, 3, '0', STR_PAD_LEFT)."03".str_pad(1, 6, '0', STR_PAD_LEFT);
                    else
                        $N_umeroDePoliza=$anio.$clienteClave.str_pad($Programa, 3, '0', STR_PAD_LEFT)."03".str_pad(intval(substr($UltimaPoliza, -6, 6))+1, 6, '0', STR_PAD_LEFT);
                }else{
                    $N_umeroDePoliza=$DatosEncabezado;
                }


                 $ConsultaInsertaContabilidad = sprintf("INSERT INTO EncabezadoContabilidad ( id, Cliente, EjercicioFiscal, TipoP_oliza, N_umeroP_oliza, FechaP_oliza, Concepto, Cotizaci_on, AreaRecaudadora, FuenteFinanciamiento, Fondo, CatalogoFondo, Programa, idPrograma, Proyecto, Clasificaci_onProgram_atica, Clasificaci_onFuncional, Clasificaci_onAdministrativa, Contribuyente, Persona, CuentaBancaria, MovimientoBancario, N_umeroDeMovimientoBancario, Momento, EstatusTupla, FechaTupla, AreaAdministrativaProyecto, Proveedor, CuentaPorPagar) VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s )",
                             Funciones::GetSQLValueString(NULL, "int"),
                             Funciones::GetSQLValueString($Cliente, "int"),
                             Funciones::GetSQLValueString($anio, "int"),
                             Funciones::GetSQLValueString(1, "int"), // 1 ingresos   2 egresos  3 diario
                             Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //numero de poliza
                             Funciones::GetSQLValueString($fecha, "datetime"), //fecha de poliza
                             Funciones::GetSQLValueString("Cotizacion #".$N_umeroDeCotizacion, "varchar"), //descripcion o concepto
                             Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //id de cotizacion
                             Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area recaudadora
                             Funciones::GetSQLValueString($FuenteFinanciamiento, "int"),  //fuente de financiamiento
                             Funciones::GetSQLValueString($Fondo, "int"), //Fondo
                             Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                             Funciones::GetSQLValueString($Programa, "int"), //Programa
                             Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                             Funciones::GetSQLValueString(NULL, "int"), // proyecto
                             Funciones::GetSQLValueString(NULL, "int"),//Clasificaci_onProgram_atica
                             Funciones::GetSQLValueString(NULL, "int"),//Clasificaci_onFuncional
                             Funciones::GetSQLValueString(NULL, "int"),//Clasificaci_onAdministrativa
                             Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                             Funciones::GetSQLValueString(NULL, "int"), //Persona
                             Funciones::GetSQLValueString(NULL, "int"), //cuenta bancaria
                             Funciones::GetSQLValueString(NULL, "int"), //Movimiento bancario
                             Funciones::GetSQLValueString(NULL, "int"), //Num de mov bancario
                             Funciones::GetSQLValueString($momento, "int"), //Momento
                             Funciones::GetSQLValueString(1, "int"), //Status 1 activo   y 0 cancelado
                             Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                             Funciones::GetSQLValueString(NULL, "int"), //Area administrativa proyecto
                             Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                             Funciones::GetSQLValueString(NULL, "int")); //Cuenta por pagar
                if(DB::insert($ConsultaInsertaContabilidad)){
                    $Status=true;
                    $IdEncabezadoContabilidad = DB::getPdo()->lastInsertId();

                    $ConsultaInsertaDetalleContabilidadC="INSERT INTO DetalleContabilidad
                        ( id, EncabezadoContabilidad, TipoP_oliza, FehaP_oliza, N_umeroP_oliza, ConceptoMovimientoContable, Cotizaci_on, AreaRecaudaci_on, FuenteFinanciamiento, Programa, idPrograma, Proyecto, AreaAdministrativaProyecto
                    , Clasificaci_onProgram_atica, Clasificaci_onFuncionalGasto, Clasificaci_onAdministrativa, Clasificaci_onEcon_omicaIGF, Contribuyente, Proveedor, CuentaBancaria, MovimientoBancario
                    , N_umeroMovimientoBancario, uuid, EstatusConcepto, ConceptoDeXML, MomentoContable, CRI, COG, TipoDeMovimientoContable, Importe, EstatusInventario, idDeLaObra, Persona, TipoDeGasto
                    , TipoCobroCaja, PlanDeCuentas, NaturalezaCuenta, TipoBien, TipoObra, EstatusTupla, Fondo, CatalogoFondo, FechaTupla, Origen,ConceptoCobroCajaId) VALUES ";



                   //Obtiene conceptos y adicioanles
                   $ConsultaObtiene = "SELECT c3.id as ConceptoCobroCajaID, co.id, co.Importe, co.MomentoCotizaci_on, co.Xml, c3.CRI, co.Adicional, (SELECT Cri FROM RetencionesAdicionales WHERE co.Adicional= RetencionesAdicionales.id) as CriBueno, (SELECT PlanDeCuentas FROM RetencionesAdicionales WHERE co.Adicional= RetencionesAdicionales.id) as PlanCuentasBueno, (SELECT Abono FROM MomentoContable WHERE Momento=".$momento." AND MomentoContable.CRI=c3.CRI ) as AbonoSegunPlan,  (SELECT Naturaleza FROM PlanCuentas WHERE  PlanCuentas.id= AbonoSegunPlan ) as NaturalezaSegunPlan, (SELECT Naturaleza FROM PlanCuentas WHERE  PlanCuentas.id= PlanCuentasBueno ) as NaturalezaPlanCuentasBueno
                    FROM ConceptoAdicionalesCotizaci_on co
                                    INNER JOIN ConceptoCobroCaja c3 ON ( co.ConceptoAdicionales = c3.id  )
                    WHERE co.Cotizaci_on =" . $IdRegistroCotizaci_on;
                    $UUID=NULL;
                    if($ResultadoObtieneConceptos = DB::select($ConsultaObtiene)){
                    foreach($ResultadoObtieneConceptos as $registroConceptos){
                            if(!is_null($registroConceptos->Adicional)){
                                $registroConceptos->ConceptoCobroCajaID= Funciones::ObtenValor("SELECT ra.ConceptoCobro FROM RetencionesAdicionales ra WHERE ra.id=".$registroConceptos->Adicional,"ConceptoCobro");
                            }
                            //aqui agrego datos a Asignaci_onPresupuestal
                            $ConsultaInserta = sprintf("INSERT INTO Asignaci_onPresupuestal (  `id` , `Cri` , `ImporteAnual` , `PresupuestoAnualPrograma` , `FechaInicial` , `FechaFinal` , `EstatusTupla`, `AreaAdministrativa`,  `ImporteModificado`, `ImporteDevengado`, `Concepto` ) VALUES (  %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
                                Funciones::GetSQLValueString(NULL, "int"),
                                Funciones::GetSQLValueString($registroConceptos->CRI, "int") ,
                                Funciones::GetSQLValueString(0, "decimal") ,
                                Funciones::GetSQLValueString($PresupuestoAnualPrograma ,"int") ,
                                Funciones::GetSQLValueString(date('Y-m-d'), "date") ,
                                Funciones::GetSQLValueString(date('Y-m-d'), "date") ,
                                Funciones::GetSQLValueString(1, "tinyint") ,
                                Funciones::GetSQLValueString($areaRecaudadora, "int"),
                                Funciones::GetSQLValueString(NULL, "decimal"),
                                Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"),
                                Funciones::GetSQLValueString($registroConceptos->id, "int")
                                );
                            if(DB::insert($ConsultaInserta)){
                                $IdRegistroAsignaci_onPresupuestal = DB::getPdo()->lastInsertId();
                            }


                            $DatosMomentoContable = Funciones::ObtenValor("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Abono) as NaturalezaAbono FROM  MomentoContable WHERE Momento=" . $momento . " AND Cri=" . $registroConceptos->CRI);
                                                //AQUI AGREGO LOS DATOS DE RetencionesAdicionales

                                //REGLA: si el adicional es retencion se duplica el abono
                                //verifico que sea un adicional=
                            if(!is_null($registroConceptos->Adicional)){
                                //es un adicional

                                $tieneCri=Funciones::ObtenValor("SELECT * FROM RetencionesAdicionales WHERE id=".$registroConceptos->Adicional);
                                if(is_null($tieneCri->Cri)){
                                    //es adicional con plan de cuentas
                                    //quiere decir que no tiene CRI, trae plan de cuentas
                                $DatosMomentoContable = Funciones::ObtenValor("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Abono) as NaturalezaAbono FROM  MomentoContable WHERE Momento=" . $momento . " AND Cri=" . $registroConceptos->CRI);
                                    //$planCuentas=ObtenValor();
                                    /**/
                                        //Para Abono 1 negativo
                                $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                 Funciones::GetSQLValueString(NULL, "int"),
                                 Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                 Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                 Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                 Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                 Funciones::GetSQLValueString("AA1PC Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                 Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                 Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                 Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                 Funciones::GetSQLValueString($Programa, "int"), //programa
                                 Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                 Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                 Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                 Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                 Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                 Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                 Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                 Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                 Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                 Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                 Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                 Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                 Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                 Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                 Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                 Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                 Funciones::GetSQLValueString(NULL, "int"), //CRI
                                 Funciones::GetSQLValueString(NULL, "int"), //COG
                                 Funciones::GetSQLValueString(1, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                 Funciones::GetSQLValueString(($registroConceptos->Importe), "decimal"), //importe
                                 Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                 Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                 Funciones::GetSQLValueString(NULL, "int"), //persona
                                 Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                 Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                 Funciones::GetSQLValueString($DatosMomentoContable->Cargo, "int"), //Plan de cuentas
                                 Funciones::GetSQLValueString($DatosMomentoContable->NaturalezaCargo, "int"), //naturaleza
                                 Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                 Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                 Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                 Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                 Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                 Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                 Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                 Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );
                                /* */
                                //Para Abono 2 positivo
                                $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                 Funciones::GetSQLValueString(NULL, "int"),
                                 Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                 Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                 Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                 Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                 Funciones::GetSQLValueString("AA2PC Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                 Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                 Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                 Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                 Funciones::GetSQLValueString($Programa, "int"), //programa
                                 Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                 Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                 Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                 Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                 Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                 Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                 Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                 Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                 Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                 Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                 Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                 Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                 Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                 Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                 Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                 Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                 Funciones::GetSQLValueString(NULL, "int"), //CRI
                                 Funciones::GetSQLValueString(NULL, "int"), //COG
                                 Funciones::GetSQLValueString(2, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                 Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                 Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                 Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                 Funciones::GetSQLValueString(NULL, "int"), //persona
                                 Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                 Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                 Funciones::GetSQLValueString($registroConceptos->PlanCuentasBueno, "int"), //Plan de cuentas
                                 Funciones::GetSQLValueString($registroConceptos->NaturalezaPlanCuentasBueno, "int"), //naturaleza
                                 Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                 Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                 Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                 Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                 Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                 Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                 Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                 Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );



                                }else{
                                //es adicional con cri
                                        $DatosMomentoContable = Funciones::ObtenValor("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Abono) as NaturalezaAbono FROM  MomentoContable WHERE Momento=" . $momento . " AND Cri=" . $registroConceptos->CriBueno);

                                //Para cargo
                                $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                 Funciones::GetSQLValueString(NULL, "int"),
                                 Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                 Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                 Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                 Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                 Funciones::GetSQLValueString("ACC Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                 Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                 Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                 Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                 Funciones::GetSQLValueString($Programa, "int"), //programa
                             Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                 Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                 Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                 Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                 Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                 Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                 Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                 Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                 Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                 Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                 Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                 Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                 Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                 Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                 Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                 Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                 Funciones::GetSQLValueString($registroConceptos->CriBueno, "int"), //CRI
                                 Funciones::GetSQLValueString(NULL, "int"), //COG
                                 Funciones::GetSQLValueString(1, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                 Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                 Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                 Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                 Funciones::GetSQLValueString(NULL, "int"), //persona
                                 Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                 Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                 Funciones::GetSQLValueString($DatosMomentoContable->Cargo, "int"), //Plan de cuentas
                                 Funciones::GetSQLValueString($DatosMomentoContable->NaturalezaCargo, "int"), //naturaleza
                                 Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                 Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                 Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                 Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                 Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                 Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                 Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                 Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );

                                //Para Abono
                                $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                 Funciones::GetSQLValueString(NULL, "int"),
                                 Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                 Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                 Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                 Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                 Funciones::GetSQLValueString("ACA Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                 Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                 Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                 Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                 Funciones::GetSQLValueString($Programa, "int"), //programa
                                 Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                 Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                 Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                 Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                 Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                 Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                 Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                 Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                 Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                 Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                 Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                 Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                 Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                 Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                 Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                 Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                 Funciones::GetSQLValueString($registroConceptos->CriBueno, "int"), //CRI
                                 Funciones::GetSQLValueString(NULL, "int"), //COG
                                 Funciones::GetSQLValueString(2, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                 Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                 Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                 Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                 Funciones::GetSQLValueString(NULL, "int"), //persona
                                 Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                 Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                 Funciones::GetSQLValueString($DatosMomentoContable->Abono, "int"), //Plan de cuentas
                                 Funciones::GetSQLValueString($DatosMomentoContable->NaturalezaAbono, "int"), //naturaleza
                                 Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                 Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                 Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                 Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                 Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                 Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                 Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                 Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );

                                /************************************************************************************/
                                //Esto es presupuestal
                                $ConsultaObtieneMomentoPresupuestal = sprintf("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Abono) as NaturalezaAbono FROM MomentoPresupuestal WHERE Momento=%s AND Cargo IS NOT NULL AND Abono IS NOT NULL ",  Funciones::GetSQLValueString($momento, "int"));
                                $ResultadoObtieneMomentoPresupuestal = DB::select($ConsultaObtieneMomentoPresupuestal);

                                foreach ($ResultadoObtieneMomentoPresupuestal as $RegistroObtieneMomentoPresupuestal) {
                                    //Para cargo en presupuestal
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                     Funciones::GetSQLValueString(NULL, "int"),
                                     Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                     Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                     Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                     Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                     Funciones::GetSQLValueString("---Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                     Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                     Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                     Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                     Funciones::GetSQLValueString($Programa, "int"), //programa
                                    Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                     Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                     Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                     Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                     Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                     Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                     Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                     Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                     Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                     Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                     Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                     Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                     Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                     Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                     Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                     Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                     Funciones::GetSQLValueString($registroConceptos->CriBueno, "int"), //CRI
                                     Funciones::GetSQLValueString(NULL, "int"), //COG
                                     Funciones::GetSQLValueString(1, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                     Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                     Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                     Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                     Funciones::GetSQLValueString(NULL, "int"), //persona
                                     Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                     Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                     Funciones::GetSQLValueString($RegistroObtieneMomentoPresupuestal->Cargo, "int"), //Plan de cuentas
                                     Funciones::GetSQLValueString($RegistroObtieneMomentoPresupuestal->NaturalezaCargo, "int"), //naturaleza
                                     Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                     Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                     Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                     Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                     Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                     Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                     Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                     Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );

                                    //Para abono en presupuestal
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                     Funciones::GetSQLValueString(NULL, "int"),
                                     Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                     Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                     Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                     Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                     Funciones::GetSQLValueString("---Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                     Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                     Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                     Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                     Funciones::GetSQLValueString($Programa, "int"), //programa
                             Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                     Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                     Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                     Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                     Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                     Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                     Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                     Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                     Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                     Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                     Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                     Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                     Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                     Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                     Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                     Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                     Funciones::GetSQLValueString($registroConceptos->CriBueno, "int"), //CRI
                                     Funciones::GetSQLValueString(NULL, "int"), //COG
                                     Funciones::GetSQLValueString(2, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                     Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                     Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                     Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                     Funciones::GetSQLValueString(NULL, "int"), //persona
                                     Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                     Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                     Funciones::GetSQLValueString($RegistroObtieneMomentoPresupuestal->Abono, "int"), //Plan de cuentas
                                     Funciones::GetSQLValueString($RegistroObtieneMomentoPresupuestal->NaturalezaAbono, "int"), //naturaleza
                                     Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                     Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                     Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                     Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                     Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                     Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                     Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                     Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );

                                }//while($RegistroObtieneMomentoPresupuestal = $ResultadoObtieneMomentoPresupuestal->fetch_assoc()){
                                    //TERMINA PRESUPUESTAL
                                    /************************************************/
                            }//

                        }//termina if para saber si es adicional o concepto
                        else{
                            //es concepto
                            //
                                $DatosMomentoContable = Funciones::ObtenValor("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Abono) as NaturalezaAbono FROM  MomentoContable WHERE Momento=" . $momento . " AND Cri=" . $registroConceptos->CRI);

                                //Para cargo
                                $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                 Funciones::GetSQLValueString(NULL, "int"),
                                 Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                 Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                 Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                 Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                 Funciones::GetSQLValueString("ConceptoC - Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                 Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                 Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                 Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                 Funciones::GetSQLValueString($Programa, "int"), //programa
                                Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                 Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                 Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                 Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                 Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                 Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                 Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                 Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                 Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                 Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                 Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                 Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                 Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                 Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                 Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                 Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                 Funciones::GetSQLValueString($registroConceptos->CRI, "int"), //CRI
                                 Funciones::GetSQLValueString(NULL, "int"), //COG
                                 Funciones::GetSQLValueString(1, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                 Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                 Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                 Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                 Funciones::GetSQLValueString(NULL, "int"), //persona
                                 Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                 Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                 Funciones::GetSQLValueString($DatosMomentoContable->Cargo, "int"), //Plan de cuentas
                                 Funciones::GetSQLValueString($DatosMomentoContable->NaturalezaCargo, "int"), //naturaleza
                                 Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                 Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                 Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                 Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                 Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                 Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                 Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                 Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );

                                //Para Abono
                                $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                 Funciones::GetSQLValueString(NULL, "int"),
                                 Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                 Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                 Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                 Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                 Funciones::GetSQLValueString("ConceptoA - Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                 Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                 Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                 Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                 Funciones::GetSQLValueString($Programa, "int"), //programa
                             Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                 Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                 Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                 Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                 Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                 Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                 Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                 Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                 Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                 Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                 Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                 Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                 Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                 Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                 Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                 Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                 Funciones::GetSQLValueString($registroConceptos->CRI, "int"), //CRI
                                 Funciones::GetSQLValueString(NULL, "int"), //COG
                                 Funciones::GetSQLValueString(2, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                 Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                 Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                 Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                 Funciones::GetSQLValueString(NULL, "int"), //persona
                                 Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                 Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                 Funciones::GetSQLValueString($DatosMomentoContable->Abono, "int"), //Plan de cuentas
                                 Funciones::GetSQLValueString($DatosMomentoContable->NaturalezaAbono, "int"), //naturaleza
                                 Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                 Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                 Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                 Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                 Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                 Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                 Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                 Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );


                        /************************************************************************************/
                                //Esto es presupuestal
                                $ConsultaObtieneMomentoPresupuestal = sprintf("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Abono) as NaturalezaAbono FROM MomentoPresupuestal WHERE Momento=%s AND Cargo IS NOT NULL AND Abono IS NOT NULL ",  Funciones::GetSQLValueString($momento, "int"));
                                $ResultadoObtieneMomentoPresupuestal = DB::select($ConsultaObtieneMomentoPresupuestal);

                                foreach ($ResultadoObtieneMomentoPresupuestal  as $RegistroObtieneMomentoPresupuestal) {
                                    //Para cargo en presupuestal
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                     Funciones::GetSQLValueString(NULL, "int"),
                                     Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                     Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                     Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                     Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                     Funciones::GetSQLValueString("---Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                     Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                     Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                     Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                     Funciones::GetSQLValueString($Programa, "int"), //programa
                                     Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                     Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                     Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                     Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                     Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                     Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                     Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                     Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                     Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                     Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                     Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                     Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                     Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                     Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                     Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                     Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                     Funciones::GetSQLValueString($registroConceptos->CRI, "int"), //CRI
                                     Funciones::GetSQLValueString(NULL, "int"), //COG
                                     Funciones::GetSQLValueString(1, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                     Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                     Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                     Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                     Funciones::GetSQLValueString(NULL, "int"), //persona
                                     Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                     Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                     Funciones::GetSQLValueString($RegistroObtieneMomentoPresupuestal->Cargo, "int"), //Plan de cuentas
                                     Funciones::GetSQLValueString($RegistroObtieneMomentoPresupuestal->NaturalezaCargo, "int"), //naturaleza
                                     Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                     Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                     Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                     Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                     Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                     Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                    Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                    Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );

                                    //Para abono en presupuestal
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                     Funciones::GetSQLValueString(NULL, "int"),
                                     Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                     Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                     Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                     Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                     Funciones::GetSQLValueString("---Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                     Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                     Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                     Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                     Funciones::GetSQLValueString($Programa, "int"), //programa
                                     Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                     Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                     Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                     Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                     Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                     Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                     Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                     Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                     Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                     Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                     Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                     Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                     Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                     Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                     Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                     Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                     Funciones::GetSQLValueString($registroConceptos->CRI, "int"), //CRI
                                     Funciones::GetSQLValueString(NULL, "int"), //COG
                                     Funciones::GetSQLValueString(2, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                     Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                     Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                     Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                     Funciones::GetSQLValueString(NULL, "int"), //persona
                                     Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                     Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                     Funciones::GetSQLValueString($RegistroObtieneMomentoPresupuestal->Abono, "int"), //Plan de cuentas
                                     Funciones::GetSQLValueString($RegistroObtieneMomentoPresupuestal->NaturalezaAbono, "int"), //naturaleza
                                     Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                     Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                     Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                     Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                     Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                     Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                     Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                     Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );

                                }//while($RegistroObtieneMomentoPresupuestal = $ResultadoObtieneMomentoPresupuestal->fetch_assoc()){
                                    //TERMINA PRESUPUESTAL
                                    /************************************************/
                        }//termina else de si es adicional o concepto



                }//while




            }	//Obtiene conceptos

                $ConsultaInsertaDetalleContabilidadC=substr_replace($ConsultaInsertaDetalleContabilidadC,";",-1);

                //print $ConsultaInsertaDetalleContabilidadC;

                    //echo  "<pre>".$ConsultaInsertaDetalleContabilidadC."</pre>";
                    if( DB::insert($ConsultaInsertaDetalleContabilidadC)){
                        $ConsultaLog = sprintf("INSERT INTO CelaAccesos ( idAcceso, FechaDeAcceso, idUsuario, Tabla, IdTabla, Acci_on ) VALUES ( %s, %s, %s, %s, %s, %s)",
                         Funciones::GetSQLValueString( NULL, "int"),
                         Funciones::GetSQLValueString( date('Y-m-d H:i:s'), "varchar"),
                         Funciones::GetSQLValueString( $consulta_usuario ->idUsuario, "int"),
                         Funciones::GetSQLValueString( 'Cotizaci_on', "varchar"),
                         Funciones::GetSQLValueString( $IdRegistroCotizaci_on, "int"),
                         Funciones::GetSQLValueString( 2, "int"));
                    $ResultadoLog = DB::insert($ConsultaLog);

                    }
                    else{
                        $Error = $Conexion->error;
                    }

                }	//termina if($ResultadoInsertaContabilidad = $Conexion->query($ConsultaInsertaContabilidad)){
                else{
                    //verifica si no insterto el encabezado contabilidad
                    $Error = $Conexion->error;
                }
            }//state==0


            return response()->json([
                'idCotizacion' =>  $IdRegistroCotizaci_on,
                ], 200);


            }else{
                return response()->json([
                    'idCotizacion' =>  $IdRegistroCotizaci_on,
                    ], 200);
                $Status = "Error";
                $Error  = $Conexion->error;
            }
            return response()->json([
                'idCotizacion' =>  $IdRegistroCotizaci_on,
                ], 200);
        }






public function ObtieneImporteyConceptos2OP($cliente,$idConcepto, $montobase){

    $anio=date("Y");
    $datosConcepto=Funciones::ObtenValor("SELECT c3.`Importe` as import, c3.`BaseCalculo` as TipobaseCalculo
    FROM `ConceptoCobroCaja` c
    INNER JOIN `ConceptoRetencionesAdicionales` c1 ON ( c.id = c1.`Concepto`  )
    INNER JOIN `ConceptoRetencionesAdicionalesCliente` c2 ON ( c1.id = c2.`ConceptoRetencionesAdicionales`  )
    INNER JOIN `ConceptoAdicionales` c3 ON ( c2.id = c3.`ConceptoRetencionesAdicionalesCliente`  )
    WHERE c3.Cliente=".$cliente." AND c3.EjercicioFiscal=".$anio." AND c2.Cliente=".$cliente." AND c.id = ".$idConcepto." AND  c3.Status=1" );

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

 $ConsultaSelect =DB::select( "SELECT ra.id, ra.Descripci_on, ra.Cri, ra.PlanDeCuentas, ra.ConceptoCobro, ra.Porcentaje, ra.Proveedor, c2.AplicaIVA
FROM `ConceptoCobroCaja` c
INNER JOIN `ConceptoRetencionesAdicionales` c1 ON ( c.id = c1.`Concepto` )
INNER JOIN `ConceptoRetencionesAdicionalesCliente` c2 ON ( c1.id = c2.`ConceptoRetencionesAdicionales` )
INNER JOIN `ConceptoAdicionales` c3 ON ( c2.id = c3.`ConceptoRetencionesAdicionalesCliente` )
INNER JOIN RetencionesAdicionales ra ON (ra.id=c1.RetencionAdicional)
WHERE c2.AplicaEnSubtotal=0 AND c3.EjercicioFiscal=".$anio." AND  c3.Cliente=".$cliente." AND c2.Cliente=".$cliente." AND c.id = ".$idConcepto." AND  c3.Status=1");


$i=0;
$Datos['suma']=$Datos['importe'];
$Datos['sumaIVA']=$Datos['importe'];
$Datos['sumaSubtotal']=$Datos['importe'];


    foreach($ConsultaSelect as $filas){
                     if(isset($filas->id)){
                        $filasV2= AguaController::Configuraci_onAdicionales($filas,$cliente);

                        $filas->id = $filasV2->id;
                        $filas->Descripci_on =  $filasV2->Descripci_on;
                        $filas->PlanDeCuentas =  $filasV2->PlanDeCuentas;
                        $filas->Proveedor =  $filasV2->Proveedor;
                        $filas->Porcentaje=  $filasV2->Porcentaje;
                        $filas->ConceptoCobro =  $filasV2->ConceptoCobro;
                        }
        $i++;
        if($Datos['baseCalculo']==1){ //importe
            $idConceptoOperacion=number_format((floatval(str_replace(",","",$Datos['importe']))*floatval($filas->Porcentaje / 100 )  ),2);
            $idConceptoOperacion2=(floatval(str_replace(",","",$Datos['importe']))*floatval($filas->Porcentaje / 100 )  );
        }
        if($Datos['baseCalculo']==2){ //porcentaje

            $idConceptoOperacion=number_format((floatval(str_replace(",","",$Datos['importe']))*floatval($filas->Porcentaje) / 100 ),2);
            $idConceptoOperacion2=(floatval(str_replace(",","",$Datos['importe']))*floatval($filas->Porcentaje) / 100 );
        }
        if($Datos['baseCalculo']==3){ // SDG
            $zona=Funciones::ObtenValor("SELECT ZonaEconomica FROM Cliente WHERE id=".$cliente, "ZonaEconomica");
            $sdg=Funciones::ObtenValor("SELECT ".$zona." FROM SalariosM_inimos WHERE EjercicioFiscal=".$anio, $zona);

            $Datos['importe']=$datosConcepto->import*$sdg;
            $Datos['punitario']=$sdg;

            $calculoimporte= floatval(str_replace(",","",$sdg)) * floatval(str_replace(",","",$datosConcepto->import));
            $idConceptoOperacion=number_format((floatval($calculoimporte)*floatval($filas->Porcentaje) / 100 ),2);
            $idConceptoOperacion2=(floatval($calculoimporte)*floatval($filas->Porcentaje) / 100 );
        }
        $Datos['suma']+=floatval($idConceptoOperacion2);
        $Datos['sumaSubtotal']+=floatval($idConceptoOperacion2);
        if($filas->AplicaIVA==1){
            $Datos['sumaIVA']+=floatval($idConceptoOperacion2);
        }

        $Datos['adicionales'.$i]['TipoBase']=$Datos['baseCalculo'];
        $Datos['adicionales'.$i]['MontoBase']=$Datos['punitario'];
        $Datos['adicionales'.$i]['idAdicional']=$filas->id;
        $Datos['adicionales'.$i]['Descripcion']=$filas->Descripci_on;
        $Datos['adicionales'.$i]['Resultado']=$idConceptoOperacion2;



    }


         $ConsultaSelect = "SELECT ra.id, ra.Descripci_on, ra.Cri, ra.PlanDeCuentas, ra.ConceptoCobro, ra.Porcentaje, ra.Proveedor, c2.AplicaIVA
FROM `ConceptoCobroCaja` c
INNER JOIN `ConceptoRetencionesAdicionales` c1 ON ( c.id = c1.`Concepto` )
INNER JOIN `ConceptoRetencionesAdicionalesCliente` c2 ON ( c1.id = c2.`ConceptoRetencionesAdicionales` )
INNER JOIN `ConceptoAdicionales` c3 ON ( c2.id = c3.`ConceptoRetencionesAdicionalesCliente` )
INNER JOIN RetencionesAdicionales ra ON (ra.id=c1.RetencionAdicional)
WHERE c2.AplicaEnSubtotal=1 AND c3.EjercicioFiscal=".$anio." AND c3.Cliente=".$cliente." AND c2.Cliente=".$cliente." AND c.id = ".$idConcepto." AND  c3.Status=1";

$ResultadoInserta = DB::select($ConsultaSelect);

    foreach($ResultadoInserta as $filas){
        $i++;
        if($filas->AplicaIVA==1){
            if($Datos['baseCalculo']==1){ //importe
                $idConceptoOperacion=number_format((floatval(str_replace(",","",$Datos['sumaIVA']))*floatval($filas->Porcentaje/ 100 )  ),2);
                $idConceptoOperacion2=(floatval(str_replace(",","",$Datos['sumaIVA']))*floatval($filas->Porcentaje / 100 )  );

            }
            if($Datos['baseCalculo']==2){ //porcentaje

                $idConceptoOperacion=number_format((floatval(str_replace(",","",$Datos['sumaIVA']))*floatval($filas->Porcentaje) / 100 ),2);
                $idConceptoOperacion2=(floatval(str_replace(",","",$Datos['sumaIVA']))*floatval($filas->Porcentaje) / 100 );
            }
            if($Datos['baseCalculo']==3){ // SDG
                $zona=Funciones::ObtenValor("SELECT ZonaEconomica FROM Cliente WHERE id=".$cliente, "ZonaEconomica");
                $sdg=Funciones::ObtenValor("SELECT ".$zona." FROM SalariosM_inimos WHERE EjercicioFiscal=".$anio, $zona);

                $Datos['importe']=$datosConcepto->import*$sdg;
                $Datos['punitario']=$sdg;

                $calculoimporte= floatval(str_replace(",","",$sdg)) * floatval(str_replace(",","",$datosConcepto->import));
                $idConceptoOperacion=number_format((floatval($calculoimporte)*floatval($filas->Porcentaje) / 100 ),2);
                $idConceptoOperacion2=(floatval($calculoimporte)*floatval($filas->Porcentaje) / 100 );
            }
            $Datos['adicionales'.$i]['TipoBase']=$Datos['baseCalculo'];
            $Datos['adicionales'.$i]['MontoBase']=$Datos['punitario'];
            $Datos['adicionales'.$i]['idAdicional']=$filas->id;
            $Datos['adicionales'.$i]['Descripcion']=$filas->Descripci_on;
            $Datos['adicionales'.$i]['Resultado']=$idConceptoOperacion2;
        }
        else{
            if($Datos['baseCalculo']==1){ //importe
                $idConceptoOperacion=number_format((floatval(str_replace(",","",$Datos['sumaSubtotal']))*floatval($filas->Porcentaje / 100 )  ),2);
                $idConceptoOperacion2=(floatval(str_replace(",","",$Datos['sumaSubtotal']))*floatval($filas->Porcentaje / 100 )  );

            }
            if($Datos['baseCalculo']==2){ //porcentaje

                $idConceptoOperacion=number_format((floatval(str_replace(",","",$Datos['sumaSubtotal']))*floatval($filas->Porcentaje) / 100 ),2);
                $idConceptoOperacion2=(floatval(str_replace(",","",$Datos['sumaSubtotal']))*floatval($filas->Porcentaje) / 100 );
            }
            if($Datos['baseCalculo']==3){ // SDG
                $zona=Funciones::ObtenValor("SELECT ZonaEconomica FROM Cliente WHERE id=".$cliente, "ZonaEconomica");
                $sdg=Funciones::ObtenValor("SELECT ".$zona." FROM SalariosM_inimos WHERE EjercicioFiscal=".$anio, $zona);

                $Datos['importe']=$datosConcepto->import*$sdg;
                $Datos['punitario']=$sdg;

                $calculoimporte= floatval(str_replace(",","",$sdg)) * floatval(str_replace(",","",$datosConcepto->import));
                $idConceptoOperacion=number_format((floatval($calculoimporte)*floatval($filas->Porcentaje) / 100 ),2);
                $idConceptoOperacion2=(floatval($calculoimporte)*floatval($filas->Porcentaje) / 100 );
            }
            $Datos['adicionales'.$i]['TipoBase']=$Datos['baseCalculo'];
            $Datos['adicionales'.$i]['MontoBase']=$Datos['punitario'];
            $Datos['adicionales'.$i]['idAdicional']=$filas->id;
            $Datos['adicionales'.$i]['Descripcion']=$filas->Descripci_on;
            $Datos['adicionales'.$i]['Resultado']=$idConceptoOperacion2;

        }


    }
    $Datos['NumAdicionales']=$i;
    //echo "<pre>";
    //print_r($Datos);
    //echo "</pre>";
    //obtenemos los datos de los adicionales
    //select Descripci_on from RetencionesAdicionales where id in (select RetencionAdicional from ConceptoRetencionesAdicionales where Concepto=10 and id in (select ConceptoRetencionesAdicionales from ConceptoRetencionesAdicionalesCliente where id in (select ConceptoRetencionesAdicionalesCliente from ConceptoAdicionales where Cliente=1 and AreaRecaudadora=2)))

    return ($Datos);
}








public function Configuraci_onAdicionales($Adicional,$Cliente=0){

    $ClienteDatos = Funciones::ObtenValor("SELECT Turistico FROM Cliente c where c.id=$Cliente","Turistico");
     $AdicionalRespuesta=$Adicional;
     if(($Adicional->id==1 || $Adicional->id==3 || $Adicional->id==24 || $Adicional->id==26) && 1==1){
    if(isset($ClienteDatos) && $ClienteDatos==1 &&  $Adicional->id==24){
           $AdicionalRespuesta = Funciones::ObtenValor("SELECT * FROM RetencionesAdicionales r where r.id=26");
    }else  if(isset($ClienteDatos) && $ClienteDatos==0 &&  $Adicional->id==26){
           $AdicionalRespuesta = Funciones::ObtenValor("SELECT * FROM RetencionesAdicionales r where r.id=24");
    }
    else  if(isset($ClienteDatos) && $ClienteDatos==1 &&  $Adicional->id==1){
           $AdicionalRespuesta = Funciones::ObtenValor("SELECT * FROM RetencionesAdicionales r where r.id=3");
    }else  if(isset($ClienteDatos) && $ClienteDatos==0 &&  $Adicional->id==3){
           $AdicionalRespuesta = Funciones::ObtenValor("SELECT * FROM RetencionesAdicionales r where r.id=1");
    }
     }
     return $AdicionalRespuesta;
}


public function ObtieneImporteyConceptosOP($cliente,$valor, $indice, $montobase){

	$redondeo=3;
    //Obtenemos el importe del concepto seleccionado.
    $anio=date("Y");
	$importe=Funciones::ObtenValor("SELECT c3.`Importe` as import, c3.`BaseCalculo` as TipobaseCalculo
FROM `ConceptoCobroCaja` c
	INNER JOIN `ConceptoRetencionesAdicionales` c1 ON ( c.id = c1.`Concepto`  )
		INNER JOIN `ConceptoRetencionesAdicionalesCliente` c2 ON ( c1.id = c2.`ConceptoRetencionesAdicionales`  )
			INNER JOIN `ConceptoAdicionales` c3 ON ( c2.id = c3.`ConceptoRetencionesAdicionalesCliente`  )
WHERE c3.Cliente=".$cliente." AND c3.EjercicioFiscal=".$anio." AND c2.Cliente=".$cliente." and c3.Status=1 AND c.id = ".$valor );

	$Datos['baseCalculo']=$importe->TipobaseCalculo;
        #precode($Datos,1,1);
	/*if($importe['TipobaseCalculo']==2){
            $Datos['importe']=$importe->import*$montobase/100;
            $Datos['punitario']=$importe->import;
    }else{
        $Datos['importe']=$montobase*$importe->import;
        $Datos['punitario']=$importe->import;

    }*/
	 $ConsultaSelect = "SELECT ra.id, ra.Descripci_on, ra.Cri, ra.PlanDeCuentas, ra.ConceptoCobro, ra.Porcentaje, ra.Proveedor, c2.AplicaIVA
FROM `ConceptoCobroCaja` c
	INNER JOIN `ConceptoRetencionesAdicionales` c1 ON ( c.id = c1.`Concepto` )
	INNER JOIN `ConceptoRetencionesAdicionalesCliente` c2 ON ( c1.id = c2.`ConceptoRetencionesAdicionales` )
	INNER JOIN `ConceptoAdicionales` c3 ON ( c2.id = c3.`ConceptoRetencionesAdicionalesCliente` )
    INNER JOIN RetencionesAdicionales ra ON (ra.id=c1.RetencionAdicional)
    INNER JOIN ConceptoAdicionalesDetalle detalle ON(detalle.ConceptoAdicionales=c3.id)
WHERE detalle.AplicaAdicional=1 AND c2.AplicaEnSubtotal=0 AND  c3.EjercicioFiscal=".$anio." AND  c3.Cliente=".$cliente." AND c2.Cliente=".$cliente." and c3.Status=1 AND c.id = ".$valor."";
	$ResultadoInserta = DB::select($ConsultaSelect);
	$i=0;
	$Datos['suma']=0;
	$Datos['sumaIVA']=0;
	$Datos['adicionales']='<div class="losadicionales'.$indice.'" >';
	if($Datos['baseCalculo']==1){
                #$montobase=0;
		$Datos['importe']= floatval($montobase)*floatval($importe->import); // Esto estaba en suma
                #$Datos['importe']= floatval($montobase)+floatval($importe->import);
		$Datos['punitario']=$importe->import;
		$Datos['simbolo']="$";
		$Datos['montoBase']=$montobase;
	}
	if($Datos['baseCalculo']==2){
		$Datos['importe']=$importe->import*$montobase/100;
		$Datos['punitario']=$importe->import;
		$Datos['simbolo']="%";
		$Datos['montoBase']=$montobase;
	}
	if($Datos['baseCalculo']==3){ //SDG
		$zona=Funciones::ObtenValor("SELECT ZonaEconomica FROM Cliente WHERE id=".$cliente, "ZonaEconomica", "ZonaEconomica");
		//echo "SELECT ".$zona." FROM SalariosM_inimos WHERE EjercicioFiscal=".$anio;
		$sdg=Funciones::ObtenValor("SELECT ".$zona." FROM SalariosM_inimos WHERE EjercicioFiscal=".$anio,$zona);
		if($montobase==$importe->import || $montobase==1)
			$Datos['montoBase']=$importe->import;
		else
			$Datos['montoBase']=$montobase;
		$Datos['punitario']=$sdg;
		$Datos['importe']=$Datos['punitario']*$Datos['montoBase'];
		$Datos['simbolo']="SMG";


	}

	$Datos['suma']+=$Datos['importe'];
	$Datos['sumaIVA']+=$Datos['importe'];

	foreach($ResultadoInserta as $filas){
            #precode($filas,1);
                if(isset($filas->id)){
                       $filasV2= AguaController::Configuraci_onAdicionales($filas,$cliente);
                       $filas->id = $filasV2->id;
                       $filas->Descripci_on =  $filasV2->Descripci_on;
                       $filas->PlanDeCuentas =  $filasV2->PlanDeCuentas;
                       $filas->Proveedor =  $filasV2->Proveedor;
                       $filas->Porcentaje=  $filasV2->Porcentaje;
                       $filas->ConceptoCobro =  $filasV2->ConceptoCobro;

                }
		$i++;
		if($Datos['baseCalculo']==1){
			$valorOperacion=number_format((floatval(str_replace(",","",$Datos['importe']))*floatval($filas->Porcentaje / 100 )  ),$redondeo);
			$valorOperacion2=(floatval(str_replace(",","",$Datos['importe']))*floatval($filas->Porcentaje / 100 )  );
		}
		if($Datos['baseCalculo']==2){
			$valorOperacion=number_format((floatval($Datos['importe'])*floatval($filas->Porcentaje) / 100 ),$redondeo);
			$valorOperacion2=(floatval($Datos['importe'])*floatval($filas->Porcentaje) / 100 );
		}
		if($Datos['baseCalculo']==3){
			$valorOperacion=number_format((floatval($Datos['importe'])*floatval($filas->Porcentaje) / 100 ),$redondeo);
			$valorOperacion2=(floatval($Datos['importe'])*floatval($filas->Porcentaje) / 100 );
		}
		$Datos['suma']+=$valorOperacion2;
		if($filas->AplicaIVA==1){
			$Datos['sumaIVA']+=$valorOperacion2;
		}


                #precode($Datos,1,1);
		//echo $importe->import." +++ ".$filas->Porcentaje;

	}


	 $ConsultaSelectDespSubtotal = "SELECT ra.id, ra.Descripci_on, ra.Cri, ra.PlanDeCuentas, ra.ConceptoCobro, ra.Porcentaje, ra.Proveedor, c2.AplicaIVA
	FROM `ConceptoCobroCaja` c
	INNER JOIN `ConceptoRetencionesAdicionales` c1 ON ( c.id = c1.`Concepto` )
	INNER JOIN `ConceptoRetencionesAdicionalesCliente` c2 ON ( c1.id = c2.`ConceptoRetencionesAdicionales` )
	INNER JOIN `ConceptoAdicionales` c3 ON ( c2.id = c3.`ConceptoRetencionesAdicionalesCliente` )
INNER JOIN RetencionesAdicionales ra ON (ra.id=c1.RetencionAdicional)
	WHERE c2.AplicaEnSubtotal=1 AND c3.EjercicioFiscal=".$anio." AND c3.Cliente=".$cliente." AND c2.Cliente=".$cliente." AND c3.Status=1 AND c.id = ".$valor."";
	$ResultadoAdicionales = DB::select($ConsultaSelectDespSubtotal);
	$i=0;
	//$Datos['adicionales']='<div class="losadicionalesSubtotal'.$indice.'" >';

	//$Datos['suma']+=$Datos['importe'];
	//echo $Datos['suma'];
	foreach($ResultadoAdicionales as $filas){
		$i++;
		//if()
		if($filas->AplicaIVA==1){
			if($Datos['baseCalculo']==1){
				$valorOperacion=number_format((floatval(str_replace(",","",$Datos['sumaIVA']))*floatval($filas->Porcentaje / 100 )  ),$redondeo);
				$valorOperacion2=(floatval(str_replace(",","",$Datos['sumaIVA']))*floatval($filas->Porcentaje / 100 )  );
			}
			if($Datos['baseCalculo']==2){
				$valorOperacion=number_format((floatval($Datos['sumaIVA'])*floatval($filas->Porcentaje) / 100 ),$redondeo);
				$valorOperacion2=(floatval($Datos['sumaIVA'])*floatval($filas->Porcentaje) / 100 );
			}
			if($Datos['baseCalculo']==3){
				$valorOperacion=number_format((floatval($Datos['sumaIVA'])*floatval($filas->Porcentaje) / 100 ),$redondeo);
				$valorOperacion2=(floatval($Datos['sumaIVA'])*floatval($filas->Porcentaje) / 100 );
			}
			//$Datos['suma']+=$valorOperacion;

		}else{
			if($Datos['baseCalculo']==1){
				$valorOperacion=number_format((floatval(str_replace(",","",$Datos['suma']))*floatval($filas->Porcentaje / 100 )  ),$redondeo);
				$valorOperacion2=             (floatval(str_replace(",","",$Datos['suma']))*floatval($filas->Porcentaje / 100 )  );
			}
			if($Datos['baseCalculo']==2){
				$valorOperacion=number_format((floatval($Datos['suma'])*floatval($filas->Porcentaje) / 100 ),$redondeo);
				$valorOperacion2=             (floatval($Datos['suma'])*floatval($filas->Porcentaje) / 100 );
			}
			if($Datos['baseCalculo']==3){
				$valorOperacion=number_format((floatval($Datos['suma'])*floatval($filas->Porcentaje) / 100 ),$redondeo);
				$valorOperacion2=             (floatval($Datos['suma'])*floatval($filas->Porcentaje) / 100 );
			}
			//$Datos['suma']+=$valorOperacion;
		}



	}




	$Datos['cantidadAdicional']=$i;
	//obtenemos los datos de los adicionales
	//select Descripci_on from RetencionesAdicionales where id in (select RetencionAdicional from ConceptoRetencionesAdicionales where Concepto=10 and id in (select ConceptoRetencionesAdicionales from ConceptoRetencionesAdicionalesCliente where id in (select ConceptoRetencionesAdicionalesCliente from ConceptoAdicionales where Cliente=1 and AreaRecaudadora=2)))

	return json_encode($Datos);
}

    public static function validarAdeudoAguaOPD(Request $request){


        $cliente = $request->Cliente;
        $idPadron = $request->IdPadron;
        #return $request;
        Funciones::selecionarBase($cliente);

        $SQL="SELECT COUNT(pl.id) as Adeudos FROM Padr_onDeAguaLectura pl WHERE pl.Padr_onAgua=$idPadron and pl.`Status`=1";
        $Adeudos= Funciones::ObtenValor($SQL,"Adeudos");
        if($Adeudos>0){
            return response()->json([
                'Status' => 0,
                'success'=>1
                ], 200);
        }
        else{
            return response()->json([
                'Status' => 1,
                'success'=>1
                ], 200);
        }


    }

    public static function pagoAnual(Request $request){

        $cliente = $request->Cliente;
        $idPadron = $request->IdPadron;

        Funciones::selecionarBase($cliente);

        $SQL="SELECT pa.id, pa.Total,pa.Descuento FROM Padr_onAguaPotablePagoAnual as pa WHERE pa.Padr_on=$idPadron and pa.Estatus=0";
        $DatosPagoAnual = DB::select($SQL);

        if(isset($DatosPagoAnual) && sizeof($DatosPagoAnual)>0) {

            return response()->json([
                'success' => 1,
                'DatosPagoAnual' => $DatosPagoAnual,
            ], 200);
        }else{
            return response()->json([
                'success' => 0,
                'padron' => $idPadron,

            ], 200);
        }
    }

    public static function obtenerPagosBD(Request $request){
        $cliente = $request->Cliente;
        $datos = $request->Datos;
        $datos = json_decode($datos);
        Funciones::selecionarBase($cliente);
        $SQL= sprintf("INSERT INTO TransaccionesEnLinea (`Id`, `idAnterior`, `id_servicio`, `IdTransaccion`, `id_cotizaciones`, `estatus`, `extras`, `id_cliente`, `Tipo_Pago`, `IdTiket`, `fechaTupla`, `nombre`, `correo`, `telefono`, `tipoServicio`, `fecha`, `idContribuyente`, `idPagoReferenciado`, `idConceptoServicio`, `banco`, `tipoPago`, `datosAdicionales`, `ImporteEjecucionFiscal`, `Adicionales`, `UsoCFDI`) VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
         Funciones::GetSQLValueString(NULL, "int unsigned"),
         Funciones::GetSQLValueString($datos->Id, "int") ,
         Funciones::GetSQLValueString($datos->id_servicio, "varchar") ,
         Funciones::GetSQLValueString($datos->IdTransaccion, "varchar") ,
         Funciones::GetSQLValueString($datos->id_cotizaciones, "varchar") ,
         Funciones::GetSQLValueString($datos->estatus, "int") ,
         Funciones::GetSQLValueString($datos->extras, "varchar") ,
         Funciones::GetSQLValueString($datos->id_cliente, "int") ,
         Funciones::GetSQLValueString($datos->Tipo_Pago, "varchar") ,
         Funciones::GetSQLValueString($datos->IdTiket, "varchar") ,
         Funciones::GetSQLValueString($datos->fechaTupla, "varchar") ,
         Funciones::GetSQLValueString($datos->nombre, "varchar") ,
         Funciones::GetSQLValueString($datos->correo, "varchar") ,
         Funciones::GetSQLValueString($datos->telefono, "varchar") ,
         Funciones::GetSQLValueString($datos->tipoServicio, "varchar") ,
         Funciones::GetSQLValueString($datos->fecha, "varchar") ,
         Funciones::GetSQLValueString($datos->idContribuyente, "int") ,
         Funciones::GetSQLValueString($datos->idPagoReferenciado, "int") ,
         Funciones::GetSQLValueString($datos->idConceptoServicio, "varchar") ,
         Funciones::GetSQLValueString($datos->banco, "varchar") ,
         Funciones::GetSQLValueString($datos->tipoPago, "int") ,
         Funciones::GetSQLValueString($datos->datosAdicionales, "varchar") ,
         Funciones::GetSQLValueString($datos->ImporteEjecucionFiscal, "decimal") ,
         Funciones::GetSQLValueString($datos->Adicionales, "varchar") ,
         Funciones::GetSQLValueString($datos->UsoCFDI, "varchar"));

        if( DB::insert($SQL)){
            return response()->json([
                'success' => 1,
                'Resultado' => 'Sin Error',
            ], 200);
        }else{
            return response()->json([
                'success' => 0,
                'Resultado' => 'Error',
            ], 200);
        }
    }

    public static function obtenerURLEstadoCuentaAnual(Request $request)
    {
        $cliente = $request->Cliente;
        $idPadron = $request->IdPadron;



        $url = 'https://suinpac.com/reciboAnualEnLinea.php';
        $dataForPost = array(
            'Cliente' => [
                "Cliente" => $cliente,
                "IdPadron" => $idPadron,

            ]

        );

        $options = array(
            'http' => array(
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($dataForPost),
            )
        );

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        return response()->json([
            'url' => $result,
            'padron' => $idPadron,
        ], 200);
    }

    public static function obtenerContribuyente(Request $request){
        $cliente = $request->Cliente;
        $idServicio=$request->IdServicio;

        Funciones::selecionarBase($cliente);
        $Contribuyente = Funciones::ObtenValor('SELECT Contribuyente from Padr_onAguaPotable  WHERE id=' .$idServicio, 'Contribuyente');

        return response()->json([
            'Contribuyente' => $Contribuyente,

        ], 200);
    }
}
