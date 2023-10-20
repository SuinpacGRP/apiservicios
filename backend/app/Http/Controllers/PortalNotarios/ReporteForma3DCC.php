<?php

namespace App\Http\Controllers\PortalNotarios;

use JWTAuth;
use DateTime;
use Exception;
use App\Cliente;
use App\Funciones;
use App\Libs\QRcode;
use App\Libs\Wkhtmltopdf;
use App\PadronAguaPotable;
use App\PadronAguaLectura;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use \Illuminate\Support\Facades\Config;

class ReporteForma3DCC {
    /**
     * !Retorna un json con el nuevoken y el resultado obtenido psado por parametro.
     * @param string $token
     * @return \Illuminate\Http\JsonResponse
    */

    public static function generar( $idCotizacionDocumentos, $cliente, $ejercicioFiscal ){
        $clave = $idCotizacionDocumentos;
        $idCliente = $cliente;
        $AnioActual = $ejercicioFiscal;

        $DIR_RESOURCE = 'recursos/';
        $DIR_IMG = 'imagenes/';

        Funciones::selecionarBase($cliente);

        $idCatalogoDocumento = 29;
        $urlCodigoQR = "http://v.servicioenlinea.mx/vf.php?id=".$clave.'&cliente='.$idCliente.'&idDoc='.$idCatalogoDocumento;  
        $QR = 'repositorio/QR/'.date('Y/m/d').'/vf'.$clave.'_'.$idCliente.'_'.$idCatalogoDocumento.'.png';
        
        if ( !file_exists('repositorio/QR/'.date('Y/m/d').'/') ) {
            mkdir('repositorio/QR/'.date('Y/m/d').'/', 0755, true);
        }

        if( !file_exists($QR) ){
            #include( app_path() . '/Libs/QRcode.php' );
            #QRcode::png($contenido, $QR, 'M' , 4, 2);
            //Contenido del QR
            QRcode::png($urlCodigoQR, $QR, 'M' , 4, 2);
            #usleep(500);
            #header("Refresh:0");
        }
        #return "Existe...." . $QR;

        #return $QR;
        $bimestres= Array( 1=>'primer', 2=>'segundo', 3=>'tercer', 4=>'cuarto', 5=>'quinto', 6=>'sexto' );
        $meses = array("","Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");

        $FechaAbre=str_replace(' ', '', ucwords(strftime("%d/ %B",strtotime('01-01-2019'))));
        $FechaAbre2=str_replace('/', ' de ', $FechaAbre);

        $FechaCorte=str_replace(' ', '', ucwords(strftime("%d/ %B /%Y",strtotime('01-01-2019'))));
        $FechaCorte2=str_replace('/', ' de ', $FechaCorte);
                
        $consultaCliente = "select *,Descripci_on,
        (SELECT l.Nombre FROM DatosFiscalesCliente d INNER JOIN Localidad l ON (l.id=d.Localidad) WHERE d.id=Cliente.DatosFiscales  ) Localidad,
        (SELECT l.Nombre FROM DatosFiscalesCliente d INNER JOIN EntidadFederativa l ON (l.id=d.EntidadFederativa) WHERE d.id=Cliente.DatosFiscales  ) Entidad, (select Ruta from CelaRepositorioC where CelaRepositorioC.idRepositorio=Cliente.Logotipo) as Logo from Cliente where id=" . $idCliente;
        
        $Cliente = DB::select($consultaCliente);

        $datosDirector = DB::select('SELECT p.id as id,CONCAT(p1.Nombre," ",p1.ApellidoPaterno," ",.p1.ApellidoMaterno) AS Nombre, pf.Descripci_on as Cargo FROM PuestoEmpleado p INNER JOIN Persona p1 ON (p.Empleado=p1.id) INNER JOIN PuestoFirmante pf ON (pf.id=p.PuestoFirmante) WHERE p.PuestoFirmante=19 AND p1.Cliente='.$idCliente.' AND p.Estatus=1');	
        
        $DatosPredio = DB::select("SELECT *, pc.Cuenta as ClaveCatastral, pc.CuentaAnterior as CuentaPredial, pc.SuperficieTerreno, 
                (SELECT SUM( REPLACE(pcd.SuperficieConstrucci_on, ',','')  * (pcd.Indiviso/100)) FROM Padr_onConstruccionDetalle pcd WHERE pcd.idPadron=pc.id  ) AS SuperficieConstrucci_on,
                pc.ValorCatastral AS BaseGravable,
                CONCAT_WS( ' ', pc.Ubicaci_on, pc.Colonia ) AS UbicacionPredio, pc.id AS IdPadr_on, 
                pc.Ubicaci_onDeNotificacion, 
                (SELECT l.Nombre from Localidad l WHERE l.id=pc.Localidad) AS Localidad,
                (SELECT CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial)) FROM `Contribuyente` c WHERE c.id=pc.Contribuyente ) as Vendedor, 
                (SELECT CONCAT(IF (c.NombreComercial IS NULL OR c.NombreComercial='',CONCAT_WS(' ',c.Nombres,c.ApellidoPaterno,c.ApellidoMaterno),c.NombreComercial)) FROM `Contribuyente` c WHERE c.id=pc.Comprador) AS Comprador 
            FROM Cotizaci_on c 
                INNER JOIN Padr_onCatastral pc ON (pc.id=c.Padr_on) 
            WHERE c.id=".$clave);

        #$consultaDatosTramiteISAI = "SELECT * FROM Padr_onCatastralTramitesISAINotarios WHERE IdCotizacionForma3=".$clave;
        #$DatosTramiteISAI = DB::table('Padr_onCatastralTramitesISAINotarios')->where('IdCotizacionForma3', $clave )->take(1)->get()[0];
        $DatosTramiteISAI = DB::table('Padr_onCatastralTramitesISAINotarios')->where('IdCotizacionForma3', $clave )->first();
        //return $DatosTramiteISAI;
        
        if( isset($DatosTramiteISAI) ){
            $DatosExtras = json_decode( $DatosTramiteISAI->DatosExtra, true);
            //return $DatosExtras;
            $domicilioNotificacion=isset($DatosExtras['domicilioNotificacion'])?$DatosExtras['domicilioNotificacion']:"";
            $LugaryFecha='';

            if( isset($DatosTramiteISAI) ) {
                if(isset($DatosExtras['selectEntidadFederativa'])&& !empty($DatosExtras['selectEntidadFederativa'])){
                    $ConsultaEntidadFederativa = DB::table('EntidadFederativa')->select("Nombre")->where('id', $DatosExtras['selectEntidadFederativa'])->value("Nombre");
                    #return $ConsultaEntidadFederativa;
                    $ConsultaMunicipio = DB::table('Municipio')->select("Nombre")->where('id', $DatosExtras['selectMunicipio'])->value("Nombre");
                    $ConsultaLocalidad = DB::table('Localidad')->select('Nombre')->where('id', $DatosExtras['selectLocalidad'])->value("Nombre");
                }
                $fecha = (isset($DatosExtras['fechaExpedicion'])&&$DatosExtras['fechaExpedicion']!=null)?$DatosExtras['fechaExpedicion']:'';
                $fechaEntera = strtotime($fecha);
                #return $fecha;
                $anio = date("Y", $fechaEntera);
                $mes = date("m", $fechaEntera);
                $dia = date("d", $fechaEntera);

                $LugaryFecha = isset($ConsultaLocalidad)?( $ConsultaLocalidad. ' Municipio de '.$ConsultaMunicipio. ', Estado de '.$ConsultaEntidadFederativa. '; '.$dia.' dias de '.$meses[intval($mes)]. ' del año '. $anio.'.'):''; 
            }
            
            $MarcaDocumentos = DB::select("SELECT DISTINCT IdTipoDocumento FROM Padr_onCatastralTramitesISAINotariosDocumentos WHERE EstatusCatastro=1 AND EstatusTercero=1 AND IdTramite='".$DatosTramiteISAI->Id . "'");
            #return "SELECT DISTINCT IdTipoDocumento FROM Padr_onCatastralTramitesISAINotariosDocumentos WHERE IdTramite=".$DatosTramiteISAI->Id;
            $Deslinde = 'circulopcion'; $noadedudopredial='circulopcion'; $noadeudoagua='circulopcion'; $avaluo='circulopcion'; $certificado='circulopcion'; $escritura='circulopcion';
            #return $MarcaDocumentos;
            foreach ($MarcaDocumentos as $doc) {
                switch ( $doc->IdTipoDocumento ) {
                    case 2:
                        $Deslinde="circulopcionMarcado";
                        break;
                    case 3:
                        $noadedudopredial ="circulopcionMarcado";
                        break;
                    case 4:
                        $noadeudoagua ="circulopcionMarcado";
                        break;
                    case 5:
                        $avaluo ="circulopcionMarcado";
                        break;
                    case 6:
                        $certificado ="circulopcionMarcado";
                        break;
                    case 7:
                        $escritura ="circulopcionMarcado";
                        break;
                }
            }
            
            $costoPorMetroTerreno = DB::select('SELECT tpve.Importe FROM TipoPredioValores tpv INNER JOIN TipoPredioValoresEjercicioFiscal tpve ON (tpv.id=tpve.idTipoPredioValores AND tpve.EjercicioFiscal='.$ejercicioFiscal.') WHERE tpv.id='.$DatosPredio[0]->TipoPredioValores);
            $valorParcialTerreno = floatval(str_replace(",", "", $DatosPredio[0]->SuperficieTerreno)) * floatval($costoPorMetroTerreno[0]->Importe) * ($DatosPredio[0]->Indiviso/100);
            
            $datosConstruccionDetalle = "SELECT * FROM Padr_onConstruccionDetalle tcd INNER JOIN TipoConstrucci_on tc ON (tc.id=tcd.TipoConstruci_on) WHERE tcd.idPadron=".$DatosPredio[0]->id; 
            //PARA LAS CONSTRUCCIONES QUE DEL APARTADO B      
            $construccionB='';

            $ejecuta = DB::select($datosConstruccionDetalle);

            $cont=0;
            $totalMetrosB=0;
            $subtotalValorContruccionB=0;

            if( count($ejecuta) == 0){
                $construccionB .= str_repeat('<tr><td class="informacion2" colspan="12">&nbsp;</td></tr>', 4);
            }else{
                foreach( $ejecuta as $RegistroDetalle ){
                    #"SELECT FORMAT(tce.Importe,2) as Importe, tc.Caracteristica as Codigo FROM TipoConstrucci_onValores tc INNER JOIN TipoConstrucci_onValoresEjercicioFiscal tce ON (tce.idTipoConstrucci_onValores=tc.id AND tce.EjercicioFiscal=".$_SESSION['CELA_EjercicioFiscal'.$_SESSION['CELA_Aleatorio']].") WHERE Cliente=".$_SESSION['CELA_Cliente'.$_SESSION['CELA_Aleatorio']]." AND idTipoConstrucci_on=".$RegistroDetalle['TipoConstruci_on']
                    $costoPorMetroConstruccion = DB::select("SELECT FORMAT(tce.Importe,2) as Importe, tc.Caracteristica as Codigo FROM TipoConstrucci_onValores tc INNER JOIN TipoConstrucci_onValoresEjercicioFiscal tce ON (tce.idTipoConstrucci_onValores=tc.id AND tce.EjercicioFiscal=".$ejercicioFiscal.") WHERE Cliente=".$idCliente." AND idTipoConstrucci_on=".$RegistroDetalle->TipoConstruci_on);
                    #return $costoPorMetroConstruccion;
                    $valorParcialConstruccion= floatval(str_replace(",", "", $RegistroDetalle->SuperficieConstrucci_on)) * floatval(str_replace(",", "",(count($costoPorMetroConstruccion)>0 ? $costoPorMetroConstruccion[0]->Importe : 0))) * ($RegistroDetalle->Indiviso/100);
                    #$valorParcialConstruccion = floatval(str_replace(",", "", $RegistroDetalle->SuperficieConstrucci_on)) * floatval(str_replace(",", "",$costoPorMetroConstruccion[0]->Importe)) * ($RegistroDetalle->Indiviso/100);
                    
                    $totalMetrosB += $RegistroDetalle->SuperficieConstrucci_on;
                    $subtotalValorContruccionB += $valorParcialConstruccion;
                    $construccionB.='<tr>
                                        <td class="informacion2 text-center" colspan="2">'.(isset($costoPorMetroConstruccion[0]->Codigo) ? $costoPorMetroConstruccion[0]->Codigo : "<b><font color='red'>Error: Verifique los datos de construcción del predio</b></font>" ).'</td>
                                        <td class="informacion2 text-center" colspan="2">'.number_format(str_replace(",","",($RegistroDetalle->SuperficieConstrucci_on??0)), 2).'</td>
                                        <td class="informacion2 text-center" colspan="2">'.(isset($costoPorMetroConstruccion[0]->Importe) ? "$ ".number_format(str_replace(",","",$costoPorMetroConstruccion[0]->Importe), 2) : "<b><font color='red'>Error: Verifique los datos de construcción del predio</font></b>" ).'</td>


                                    <!---<td class="informacion2 text-center" colspan="2">'.(count($costoPorMetroConstruccion)>0 ? $costoPorMetroConstruccion[0]->Codigo : 0).'</td>
                                    <td class="informacion2 text-center" colspan="2">'.$RegistroDetalle->SuperficieConstrucci_on.'</td>
                                    <td class="informacion2 text-center" colspan="2">$'.number_format(str_replace(",","",(count($costoPorMetroConstruccion)>0 ? $costoPorMetroConstruccion[0]->Importe : 0)), 2).'</td>--->
                                    <td class="informacion2 text-center" colspan="3">'.$RegistroDetalle->Indiviso.'</td>
                                    <td class="informacion2 text-right" colspan="3">$'.number_format($valorParcialConstruccion, 2).'</td>
                                </tr>';
                    $cont++; 
                }
            
            }
        }

        $construccionB.='
                <tr>
                    <td class="informacion2 text-right subtotal" colspan="12"> Subtotal: $'.number_format($subtotalValorContruccionB,2).'</td>
                </tr>';

        $consultaColincancias = "SELECT pc.Colindancia,(SELECT pp.Nombre FROM PuntoCardinal pp WHERE pp.id=pc.idPuntoCardinal) AS Nombre FROM Padr_onCatastral po INNER JOIN Padr_onColindancia pc ON (pc.idPadr_onCatastral=po.id) WHERE po.id=".$DatosPredio[0]->Padr_on;
        $ejecuta = DB::select( $consultaColincancias );
        $Colindancias = '';
        foreach( $ejecuta as $registro ){
            $Colindancias.='<tr> <td colspan="4"> '.($registro->Nombre !=''? $registro->Nombre.": " : "" ).'</td> <td colspan="8"> '.$registro->Colindancia.'</td> </tr>';
        }
        
        $propietario = DB::select("SELECT c.id, CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial))  AS Nombre FROM `Contribuyente` c  WHERE c.id=".$DatosPredio[0]->Contribuyente);
        #return $propietario;
        $DatosDocumento = DB::select("SELECT  ccc.Descripci_on as Concepto,
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
            c.id=".$clave." AND 
            ccc.CatalogoDocumento IS NOT NULL AND
            c.Cliente=".$idCliente." AND
            cac.Adicional IS NULL AND
            Origen!='PAGO'" );
        
        $DatosDocumentosObligatoriosCatastro = DB::select("SELECT  ccc.Descripci_on as Concepto,
                c.id, c.FolioCotizaci_on, d.NombreORaz_onSocial as Nombre, ccc.Tiempo, 
                d.id as did, cont.id contid, 
                        (SELECT sum(importe) FROM ConceptoAdicionalesCotizaci_on WHERE ConceptoAdicionalesCotizaci_on.Cotizaci_on=c.id AND Padre is NULL) as importe,
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
            c.Padr_on = ".$DatosPredio[0]->id." AND
            ccc.CatalogoDocumento IN (7, 3) AND
            c.Cliente=".$idCliente." AND
            cac.Adicional IS NULL AND
            Origen='PAGO'");
        #return $DatosDocumentosObligatoriosCatastro;

        $DocumentosObligatorios='<tr> <td colspan="12">Se cubrio el Impuesto sobre Adquisiciones de bienes Inmuebles, Derecho de Certificado Catastral y en su caso multa por presentación extemporanea.</td> </tr>  <tr> <td colspan="3" class="informacion2" width="40%"><b>Documento:</b></td>  <td colspan="3" width="25%" class="informacion2 text-center" ><b>CFDI:</b>  </td> <td colspan="3" width="15%" class="informacion2 text-center" ><b>Fecha de Pago:</b></td>  <td colspan="3" width="20%"class="informacion2 text-center" ><b>Importe:</b></td>  </tr>';
        foreach ($DatosDocumentosObligatoriosCatastro as $valor){
            $DocumentosObligatorios .= '
                                    <tr> 
                                            <td colspan="3">'.$valor->NombreDocumento.'</td>
                                            <td colspan="3" class="text-center">'.$valor->uuid.'</td>
                                            <td colspan="3" class="text-center">'.$valor->Fechapago.'</td>
                                            <td colspan="3" class="text-right"> $'.number_format($valor->importe,2).'</td>
                                    </tr>';
        }
        
        $FechaCorte2 = $DatosDocumento[0]->Fechapago;
        $FechaCorte2 = str_replace(' ', '', ucwords(strftime("%d/ %B /%Y",strtotime($DatosDocumento[0]->Fechapago))));
        $FechaCorte2 = str_replace('/', ' de ', $FechaCorte2);
        
        $FirmanteDirectorCatastro = DB::select('select Reporte.Ruta,LeyendaFirmante.Descripci_on as Leyenda,concat_ws(" ",(SELECT Nombre FROM CatalogoTituloPersonal ctp WHERE ctp.id=Persona.TituloPersonal), Persona.Nombre,Persona.ApellidoPaterno,Persona.ApellidoMaterno) '
        . 'as Nombre,PuestoFirmante.Descripci_on from PuestoEmpleado inner join Persona on PuestoEmpleado.Empleado=Persona.id inner join PuestoFirmante on '
        . 'PuestoEmpleado.PuestoFirmante=PuestoFirmante.id inner join Reporte_Firmante on PuestoEmpleado.id=Reporte_Firmante.PuestoFirmante left '
        . 'join LeyendaFirmante on Reporte_Firmante.LeyendaFirmante=LeyendaFirmante.id inner join Reporte on Reporte_Firmante.Reporte=Reporte.id '
        . 'where Reporte_Firmante.Cliente='.$idCliente.' and Reporte.Ruta="Reporte_Forma3DCC.php" and Reporte_Firmante.Estatus=1 order by Reporte_Firmante.Orden asc');

        #return $FirmanteDirectorCatastro;

        $NumeroDocumentos = DB::select("Select count(idRepositorio) as numero FROM CelaRepositorio WHERE Tabla='Reporte_Forma3DCC' and idTabla=".$DatosDocumento[0]->idContabilidad.$DatosDocumento[0]->id);
        
        $Existe = TRUE;
        $Existe = FALSE;

        if(!isset($DatosPredio[0]->ValorFiscal)){
           $valorFiscal="";
        }else{
            $valorFiscal='$'.number_format(str_replace(",","",$DatosPredio[0]->ValorFiscal),2);

        }

        if(!isset($DatosPredio[0]->ValorPericial)){
            $valorPericial="";
        }else{
            $valorPericial='$'.number_format(str_replace(",","",$DatosPredio[0]->ValorPericial),2);

        }

        $miHTMLP= '<html><head>
                     <meta charset="utf-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1">
               
               
               
                </head></html>';

        $miHTML = '<html>
            <head>
                <meta charset="utf-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1">      
                <link href="'. (Storage::exists($DIR_RESOURCE.'bootstrap.min.css')?asset(Storage::url($DIR_RESOURCE.'bootstrap.min.css')):'') .'" rel="stylesheet">
                
                <style>    
                    body{
                        font-size: 12px;
                    }
                    th > div, th > span, th {
                        font-size: 12px;
                        vertical-align: middle;
                    }
                    td > div, td > span, td {
                        font-size: 12px;
                        vertical-align: middle;
                    }
                    .letraGeneral{
                        font-size: 12px;
                    }
                    .main_container{
                        padding-top:15px;
                        padding-left:5px;
                        z-index: 99;
                        background-size: cover;
                        width:735px;
                        height:975px;
                        position:relative;
                    }
                    .break{
                        display: block;
                        clear: both;
                        page-break-after: always;
                    }
                    .tabla-fit{
                        padding: 2px 5px 2px 2px;	
                    }
                    .tabla-fit > thead > tr > th, 
                    .tabla-fit >tbody>tr>td {
                        padding: 2px 5px 2px 2px;
                    }
                    .titulo{
                        background:#ccc;
                        color:black;
                        font-size:20px;
                        font-weight:bold;    
                    }
                    .table > thead > tr > th, 
				    .table>tbody>tr>td {
					    padding: 2px 5px 2px 2px;
                    }
                    .table-bordered>tbody>tr>td {
                        border: 1px solid #ddd;
                    }
                    .titulo2{
                        background: #F4F4F4;
                        text-align: center;
                        font-weight: bold;
                        font-size : 11px;
                    }
                    .titulo3{
                        background: #F4F4F4;
                        text-align: left;
                        font-weight: bold;
                        font-size : 11px;
                    }
                    .titulo4{
                        background: #F4F4F4;
                        text-align: right;
                        font-weight: bold;
                        font-size : 11px;
                    }
                    .informacion2{
                        vertical-align: middle;
                        v-align:middle;
                        font-size : 11px;
	    			}
                    .subtotal{
                        text-align: right;
                        font-weight: bold;
                        font-size : 10px;
                    }
		    		.total{
                        text-align: right;
                        font-weight: bold;
                        font-size : 10px;
                    }
                    .circulopcion {
                        border-radius: 5px;
                        width: 20px;
                        height: 20px;
                    }             
                    .circulopcionMarcado {
                        background: #151414;
                        border-radius: 5px;
                        border-color: #151414;
                        width: 20px;
                        height: 20px;
                    }
                    .fecha{
                        v-align:middle;
                        font-size : 11px;
                    }
                </style>
            </head>
            <body>  
                <div class="container"> 
                    <div class="row">  
                    
                        <table class="table" border="0" width="100%" colspan="12">
                        
                                <tr>
                                    <td colspan="12">
                                       
                                        <table border="0" width="100%">
                                           
                                            <tr>
                                                <td colspan="4" width="20%"><img src="'.asset($Cliente[0]->Logo).'" alt="Logo del cliente"  style="height: 80px;"></td>
                                                <td colspan="4" width="80%"><div class="text-center"><b><h2>'.$Cliente[0]->Descripci_on.'</h2></b></div></td>
                                               <td colspan="4" width="20%"> <img src="'.asset($QR).'" alt="QR Verificador"  style="height: 80px;"> </td>
                                            </tr>  
                                            <tr> 
                                            	<td> &nbsp; </td> </tr>
                                            <tr>
                                                <td colspan="12"><div class="titulo text-center">AVISO DE MOVIMIENTO DE PROPIEDAD INMUEBLE</div> </td>
                                            </tr>
                                            <tr>
                                            	<td colspan="12"> &nbsp; </td>
                                            </tr>
                                            <tr>
	                                            <td colspan="12" class="text-right" style="font-size: 14spx;">
	                                                 <strong>Folio: </strong> <font color="red">'.($idCliente. ' - '.$DatosPredio[0]->id.' - '. $DatosDocumento[0]->id ).'</font>
	                                            </td>
                                            </tr>
                                            <tr> 
                                            	<td colspan="12"> &nbsp; </td> 
                                            </tr>
                                            
                                        </table>
                                        
                                    </td>
                                </tr>
                            
                                  
                                <tr>
                                    <td class="titulo3" colspan="12">I.- Datos que proporcionara el Contribuyente</td>
                                </tr>
                                <tr>
                                    <td class="informacion2" width="20%" colspan="2"><strong> Notario o Documento: </strong> </td>
                                    <td class="informacion2" width="40%" colspan="4">'.((isset($DatosExtras['notariaodocumento'])&&$DatosExtras['notariaodocumento']!=''&&$DatosExtras['notariaodocumento']!=null)?$DatosExtras['notariaodocumento']:'').'</td>
                                    <td class="informacion2" width="20%" colspan="2"><strong> Fecha de Operación: </strong> </td>
                                    <td class="informacion2" width="40%" colspan="4"> '.((isset($DatosExtras['fechaOperacion'])&&$DatosExtras['fechaOperacion']!=''&&$DatosExtras['fechaOperacion']!=null)?$DatosExtras['fechaOperacion']:'') .' </td>
                                </tr>

                                <tr>
                                    <td class="informacion2" colspan="2"><strong> Importe a la Venta: </strong> </td>
                                    <td class="informacion2" colspan="4"> $ '.((isset($DatosExtras['importeVenta'])&&$DatosExtras['importeVenta']!=''&& $DatosExtras['importeVenta']!=null)?$DatosExtras['importeVenta']:'') .'</td>
                                        
                                    <td class="informacion2" colspan="2"><strong> Cuenta: </strong> </td>
                                    <td class="informacion2" colspan="4">'.((isset($DatosExtras['cuentaPredial'])&&$DatosExtras['cuentaPredial']!=''&&$DatosExtras['cuentaPredial']!=null)?$DatosExtras['cuentaPredial']:'')  .'</td>
                                            
                                </tr>
                                
                                <tr>
                                    <td class="informacion2" colspan="2"><strong> Número de Escritura: </strong> </td>
                                    <td class="informacion2" colspan="4">'.((isset($DatosExtras['escritura'])&&$DatosExtras['escritura']!=''&&$DatosExtras['escritura']!=null)?$DatosExtras['escritura']:'') .'</td>
                                        
                                    <td class="informacion2" colspan="2"><strong>Fecha de Escritura: </strong> </td>
                                    <td class="informacion2" colspan="4">'.((isset($DatosExtras['fechaEscritura'])&&$DatosExtras['fechaEscritura']!=''&&$DatosExtras['fechaEscritura']!=null)?$DatosExtras['fechaEscritura']:'')  .'</td>
                                </tr>
                                            
                                <tr>
                                    <td class="informacion2" colspan="2"><strong> Lugar y Fecha: </strong> </td>
                                    <td class="informacion2" colspan="8">'.$LugaryFecha.'</td>
                                </tr>
                                
                                <tr> <td colspan="12"> &nbsp; </td> </tr>
                                <tr>
                                    <td class="titulo3" colspan="12">Datos que proporcionara el Contribuyente</td>
                                </tr>
                                
                                <tr>
                                    <td class="informacion2" colspan="4"><strong>Dependencia: </strong>  </td>
                                    <td class="informacion2" colspan="8"> Dirección de Catastro </td>
                                    
                                </tr>

                                <tr>
                                    <td class="informacion2" colspan="4"><strong>Nombre del Vendedor: </strong> </td>
                                    <td class="informacion2" colspan="8"> '.$DatosPredio[0]->Vendedor.' </td>
                                </tr>
                                <tr>
                                    <td class="informacion2" colspan="4"><strong>Nombre del Comprador: </strong> </td>
                                    <td class="informacion2" colspan="8"> '.$DatosPredio[0]->Comprador.' </td>
                                </tr>
                                
                                    <tr>
                                    <td class="informacion2" colspan="4"><strong>Ubicación del Predio: </strong> </td>
                                    <td class="informacion2" colspan="8"> '.$DatosPredio[0]->UbicacionPredio.' </td>    
                                </tr>
                                
                                <tr>
                                    <td class="informacion2" colspan="4"><strong>Domicilio de Notificación: </strong> </td>
                                    <td class="informacion2" colspan="8"> '.$domicilioNotificacion.' </td>
                                </tr>
                                            
                                <tr>
                                    <td class="informacion2" colspan="4"><strong>Localidad: </strong> </td>
                                    <td class="informacion2" colspan="8"> '.$DatosPredio[0]->Localidad.' </td>
                                </tr>
                                
                                <tr> <td colspan="12"> <div class="col-md-12 col-xs-12 col-lg-12"> <div class="col-md-6 col-sm-6 col-xs-6 text-center letraGeneral"><br> <small><strong class="letraGeneral"></strong></small><br><br>
                                        <small><strong class="letraGeneral">'.($DatosPredio[0]->Vendedor).'</strong></small><br>
                                            <small><strong class="letraGeneral">Vendedor</strong></small><br>
                                        &nbsp; </div> <div class="col-md-6 col-sm-6 col-xs-6 text-center letraGeneral"><br> <small><strong class="letraGeneral"></strong></small><br><br>
                                        <small><strong class="letraGeneral">'.($DatosPredio[0]->Comprador).'</strong></small><br>
                                            <small><strong class="letraGeneral">Comprador</strong></small><br>
                                        &nbsp; </div> </div> </td> </tr>
                                    
                                <tr>
                                    <td class="informacion2" colspan="12"><strong>Documentos que acompañan el trámite: </strong> </td>
                                </tr>
                                
                                <tr> <td colspan="2"  class="text-center">  Deslinde Catastral </td> <td colspan="2"  class="text-center"> Certificado de No Adeudo Predial</td> <td colspan="2"  class="text-center">  Certificado de No Adeudo Agua Potable</td>  <td colspan="2"  class="text-center">  Avalúo Fiscal </td> <td colspan="2" class="text-center">  Certificado de Libertad de Gravamen </td> <td colspan="2" class="text-center">  Escritura Preventiva </td> </tr>
                                <tr> <td colspan="2"  class="text-center">  <input type="text" class="'.$Deslinde.'" value=""> </td> <td colspan="2" class="text-center">  <input type="text" class="'.$noadedudopredial.'" value=""> </td> <td colspan="2" class="text-center">  <input type="text" class="'.$noadeudoagua.'" value=""> </td>  <td colspan="2" class="text-center">  <input type="text" class="'.$avaluo.'" value=""> </td> <td colspan="2" class="text-center">  <input type="text" class="'.$certificado.'" value=""> </td> <td colspan="2" class="text-center">  <input type="text" class="'.$escritura.'" value=""> </td> </tr>

                                <tr>
                                    <td colspan="12">
                                        <br><br>
                                        <table class="table tabla-fit" width="100%">
                                            '.$DocumentosObligatorios.'
                                        </table>
                                    </td>
                                </tr>

                               <!-- <tr> <td colspan="6"> <div class="col-md-12 col-xs-12 col-lg-12"> <div class="col-md-12 col-sm-12 col-xs-12 text-center letraGeneral"><br> <small><strong class="letraGeneral"></strong></small><br><br>
                                        <small><strong class="letraGeneral">'.((isset($FirmanteDirectorCatastro)&& count($FirmanteDirectorCatastro)>0)? $FirmanteDirectorCatastro[0]->Nombre:'Sin Firmante').'</strong></small><br>
                                            <small><strong class="letraGeneral">'.((isset($FirmanteDirectorCatastro)&& count($FirmanteDirectorCatastro)>0)?$FirmanteDirectorCatastro[0]->Descripci_on:'Sin Firmante') .'</strong></small><br>
                                        &nbsp; </div> </div> </td>   <td colspan="6" class="fecha text-right"><br><br><br><br>'.$Cliente[0]->Localidad.", ".$Cliente[0]->Entidad.'; a <br>'.$FechaCorte2.'... </td>    
                                </tr>-->
                                               
                                <tr>
                                    <td class="titulo3" colspan="12">II.- Campos reservados para la Oficina Recepora</td>
                                </tr>

                                <tr>
                                    <td class="informacion2" colspan="5"><strong>Número de Clave Catastral: </strong> '."".$DatosPredio[0]->Cuenta.'</td>
                                    <td class="informacion2" colspan="6"><strong>Número de Cuenta Predial:&nbsp;</strong> '." ".$DatosPredio[0]->CuentaAnterior.'</td>
                                </tr>

                                <tr> <td colspan="12"> &nbsp; </td> </tr>

                                <tr>
                                    <td class="titulo3" colspan="12">III.- Caracteristicas del Predio (Deberan ser llenadas por el Contribuyente)</td>
                                </tr>

                                <tr>
                                    <td class="informacion2" colspan="12">
                                    <p><strong> Colindancias</strong></p>
                                    </td>
                                </tr>

                                '.$Colindancias.'

                                    <tr> <td colspan="12"> &nbsp; </td> </tr>

                                <tr>
                                    <td class="titulo3" colspan="12">IV.- Llenese por el Departamento de Catastro</td>
                                </tr>

                                <tr>
                                    <td class="informacion2" colspan="12">
                                    <p><strong>Terreno</strong></p>
                                        </td>
                                </tr>

                                <tr>
                                    <td class="titulo2" colspan="2"></td>
                                    <td class="titulo2" colspan="2">Superficie M2</td>
                                    <td class="titulo2" colspan="2">Valor Unitario M2</td>
                                    <td class="titulo2" colspan="3">Porcentaje Indiviso</td>
                                    <td class="titulo2" colspan="3">Valor Parcial</td>
                                </tr>
                                <tr>
                                    <td class="informacion2" colspan="2"></td>
                                    <td class="informacion2 text-center" colspan="2">'.number_format(str_replace(",","",$DatosPredio[0]->SuperficieTerreno), 2).'</td>
                                    <td class="informacion2 text-center" colspan="2">$'.$costoPorMetroTerreno[0]->Importe.'</td>
                                    <td class="informacion2 text-center" colspan="3">'.(str_replace(",","",$DatosPredio[0]->Indiviso)).'</td>
                                    <td class="informacion2 text-right" colspan="3">$'.number_format(str_replace(",","",$valorParcialTerreno), 2).'</td>

                                </tr>

                                <tr>
                                    <td class="informacion2" colspan="12">
                                    <p><strong>Construcción</strong></p>
                                    </td>
                                </tr>
                                    <tr>
                                    <td class="titulo2" colspan="2">Código</td>
                                    <td class="titulo2" colspan="2">Supercifie</td>
                                    <td class="titulo2" colspan="2">Valor Unitario M2</td>
                                    <td class="titulo2" colspan="3">Porcentaje Indiviso</td>
                                    <td class="titulo2" colspan="3">Valor Parcial</td>
                                </tr>
                                '.$construccionB.'

                                <tr>
                                    <td class="titulo3" colspan="9">

                                        Valor Catastral:
                                    </td>
                                    <td class="titulo4 text-right" colspan="9">
                                        $'.number_format(str_replace(",","",$subtotalValorContruccionB+$valorParcialTerreno), 2).'
                                    </td>
                                </tr>

                                <tr>
                                    <td class="titulo3" colspan="9">
                                        Valor Fiscal:
                                    </td>
                                    <td class="titulo4 text-right" colspan="9">
                                        '.$valorFiscal.'
                                    </td>
                                </tr>

                                <tr>
                                    <td class="titulo3" colspan="9">
                                            Valor de Operación:
                                    </td>
                                    <td class="titulo4 text-right" colspan="9">
                                        '.$valorPericial.'
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="12">'.ReporteForma3DCC::Leyenda_Firma_Reporte2C1($idCliente, "Reporte_Forma3DCC.php").'</td>
                                </tr>
                                <tr>
                                    <br><td colspan="12">Para el seguimiento del Trámite ingresar a la siguiente URL: <a href="https://servicioenlinea.mx/portalnotarios/">https://servicioenlinea.mx/portalnotarios/</a>  </td>
                                </tr>
                            
                            
                        </table>
                    </div>
                </div>             
            </body>
        </html>';
        //return $miHTML;

        //$miHTML2='<html><body><h1>Hola Misa</h1></body></html>';

        include( app_path() . '/Libs/Wkhtmltopdf.php' );
        try{
            $DirectorioTemporal = 'repositorio/temporal/';
            $NombreArchivo = "Reporte_Forma3DCC_".uniqid()."_".$DatosDocumento[0]->idContabilidad.$DatosDocumento[0]->id.".pdf";
            $wkhtmltopdf = new Wkhtmltopdf(array('path' =>$DirectorioTemporal, 'lowquality'=>true, 'FooterStyleLeft'=>'Grupo Piacza - SUINPAC Contable', 'FooterStyleCenter'=>'Pag. [page] de [toPage]'));
            $wkhtmltopdf->setHtml($miHTML);
            $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $NombreArchivo);
            
            return $DirectorioTemporal.$NombreArchivo;
        }catch (Exception $e) {
            //echo "Hubo un error al generar el PDF: " . $e->getMessage();
            return $e->getMessage();
        }
    }

    public static function Leyenda_Firma_Reporte2C1($cliente, $ruta){
        $Respuesta='';
        $distribucion='';
        $_SERVER['PHP_SELF'] = substr($_SERVER['PHP_SELF'],1);
        $Consulta = 'select Reporte.Ruta,LeyendaFirmante.Descripci_on as Leyenda,concat_ws(" ",(SELECT Nombre FROM CatalogoTituloPersonal ctp WHERE ctp.id=Persona.TituloPersonal), Persona.Nombre,Persona.ApellidoPaterno,Persona.ApellidoMaterno) as Nombre,PuestoFirmante.Descripci_on from PuestoEmpleado inner join Persona on PuestoEmpleado.Empleado=Persona.id inner join PuestoFirmante on PuestoEmpleado.PuestoFirmante=PuestoFirmante.id inner join Reporte_Firmante on PuestoEmpleado.id=Reporte_Firmante.PuestoFirmante left join LeyendaFirmante on Reporte_Firmante.LeyendaFirmante=LeyendaFirmante.id inner join Reporte on Reporte_Firmante.Reporte=Reporte.id where Reporte_Firmante.Cliente='.$cliente.' and Reporte.Ruta="'.$ruta.'" and Reporte_Firmante.Estatus=1 order by Reporte_Firmante.Orden asc';
        
        $Resultado = DB::select( $Consulta );
    
        if( count($Resultado) != 0 ){
            $num_firmas = count($Resultado);

            if($num_firmas==4){
                $distribucion="col-md-3 col-sm-3 col-xs-3";
            }
            else if($num_firmas==3){
                $distribucion="col-md-4 col-sm-4 col-xs-4";
            }
            else if($num_firmas==2){
                $distribucion="col-md-6 col-sm-6 col-xs-6";
            }
            else if($num_firmas==1){
                $distribucion="col-md-12 col-sm-12 col-xs-12";
            }
    
            foreach($Resultado as $Renglon ){
                $Respuesta.='<div class="'.$distribucion.' text-center letraGeneral"><br> <small><strong class="letraGeneral">'.($Renglon->Leyenda).'</strong></small><br><br><br />
                        <small><strong class="letraGeneral">'.($Renglon->Nombre).'</strong></small><br />
                            <small><strong class="letraGeneral">'.($Renglon->Descripci_on).'</strong></small><br />
                        &nbsp; </div>';
            }
    
            $Respuesta='<div class="col-md-12 col-xs-12 col-lg-12">'.$Respuesta.'</div>';
        }
    
        return $Respuesta;
    }
    public static function validarForma( $idCotizacionDocumentos, $cliente, $ejercicioFiscal ){
        $clave = $idCotizacionDocumentos;
        $idCliente = $cliente;
        Funciones::selecionarBase($cliente);

        $ConsultaDatosDocumento="SELECT  ccc.Descripci_on as Concepto,
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
	c.id=".$clave." AND 
	ccc.CatalogoDocumento IS NOT NULL AND
	c.Cliente=".$idCliente." AND
	cac.Adicional IS NULL AND
    Origen!='PAGO'";
    
    $DatosDocumento = DB::select($ConsultaDatosDocumento)->first();

  return $DatosDocumento;
    }

    public static function generar2( $idCotizacionDocumentos, $cliente, $ejercicioFiscal ){
        $clave = $idCotizacionDocumentos;
        $idCliente = $cliente;
        $AnioActual = $ejercicioFiscal;

        $DIR_RESOURCE = 'recursos/';
        $DIR_IMG = 'imagenes/';

        Funciones::selecionarBase($cliente);

        $idCatalogoDocumento = 29;
        $urlCodigoQR = "http://v.servicioenlinea.mx/vf.php?id=".$clave.'&cliente='.$idCliente.'&idDoc='.$idCatalogoDocumento;  
        $QR = 'repositorio/QR/'.date('Y/m/d').'/vf'.$clave.'_'.$idCliente.'_'.$idCatalogoDocumento.'.png';
        
        if ( !file_exists('repositorio/QR/'.date('Y/m/d').'/') ) {
            mkdir('repositorio/QR/'.date('Y/m/d').'/', 0755, true);
        }

        if( !file_exists($QR) ){
            #include( app_path() . '/Libs/QRcode.php' );
            #QRcode::png($contenido, $QR, 'M' , 4, 2);
            //Contenido del QR
            QRcode::png($urlCodigoQR, $QR, 'M' , 4, 2);
            #usleep(500);
            #header("Refresh:0");
        }
        #return "Existe...." . $QR;

        #return $QR;
        $bimestres= Array( 1=>'primer', 2=>'segundo', 3=>'tercer', 4=>'cuarto', 5=>'quinto', 6=>'sexto' );
        $meses = array("","Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");

        $FechaAbre=str_replace(' ', '', ucwords(strftime("%d/ %B",strtotime('01-01-2019'))));
        $FechaAbre2=str_replace('/', ' de ', $FechaAbre);

        $FechaCorte=str_replace(' ', '', ucwords(strftime("%d/ %B /%Y",strtotime('01-01-2019'))));
        $FechaCorte2=str_replace('/', ' de ', $FechaCorte);
                
        $consultaCliente = "select *,Descripci_on,
        (SELECT l.Nombre FROM DatosFiscalesCliente d INNER JOIN Localidad l ON (l.id=d.Localidad) WHERE d.id=Cliente.DatosFiscales  ) Localidad,
        (SELECT l.Nombre FROM DatosFiscalesCliente d INNER JOIN EntidadFederativa l ON (l.id=d.EntidadFederativa) WHERE d.id=Cliente.DatosFiscales  ) Entidad, (select Ruta from CelaRepositorioC where CelaRepositorioC.idRepositorio=Cliente.Logotipo) as Logo from Cliente where id=" . $idCliente;
        
        $Cliente = DB::select($consultaCliente);

        $datosDirector = DB::select('SELECT p.id as id,CONCAT(p1.Nombre," ",p1.ApellidoPaterno," ",.p1.ApellidoMaterno) AS Nombre, pf.Descripci_on as Cargo FROM PuestoEmpleado p INNER JOIN Persona p1 ON (p.Empleado=p1.id) INNER JOIN PuestoFirmante pf ON (pf.id=p.PuestoFirmante) WHERE p.PuestoFirmante=19 AND p1.Cliente='.$idCliente.' AND p.Estatus=1');	
        
        $DatosPredio = DB::select("SELECT *, pc.Cuenta as ClaveCatastral, pc.CuentaAnterior as CuentaPredial, pc.SuperficieTerreno, 
                (SELECT SUM( REPLACE(pcd.SuperficieConstrucci_on, ',','')  * (pcd.Indiviso/100)) FROM Padr_onConstruccionDetalle pcd WHERE pcd.idPadron=pc.id  ) AS SuperficieConstrucci_on,
                pc.ValorCatastral AS BaseGravable,
                CONCAT_WS( ' ', pc.Ubicaci_on, pc.Colonia ) AS UbicacionPredio, pc.id AS IdPadr_on, 
                pc.Ubicaci_onDeNotificacion, 
                (SELECT l.Nombre from Localidad l WHERE l.id=pc.Localidad) AS Localidad,
                (SELECT CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial)) FROM `Contribuyente` c WHERE c.id=pc.Contribuyente ) as Vendedor, 
                (SELECT CONCAT(IF (c.NombreComercial IS NULL OR c.NombreComercial='',CONCAT_WS(' ',c.Nombres,c.ApellidoPaterno,c.ApellidoMaterno),c.NombreComercial)) FROM `Contribuyente` c WHERE c.id=pc.Comprador) AS Comprador 
            FROM Cotizaci_on c 
                INNER JOIN Padr_onCatastral pc ON (pc.id=c.Padr_on) 
            WHERE c.id=".$clave);

        #$consultaDatosTramiteISAI = "SELECT * FROM Padr_onCatastralTramitesISAINotarios WHERE IdCotizacionForma3=".$clave;
        #$DatosTramiteISAI = DB::table('Padr_onCatastralTramitesISAINotarios')->where('IdCotizacionForma3', $clave )->take(1)->get()[0];
        $DatosTramiteISAI = DB::table('Padr_onCatastralTramitesISAINotarios')->where('IdCotizacionForma3', $clave )->first();
        #return $DatosTramiteISAI->Id;
        
        if( $DatosTramiteISAI ){
            $DatosExtras = json_decode( $DatosTramiteISAI->DatosExtra, true);
            #return $DatosExtras;
            
            $LugaryFecha='';

            if( $DatosTramiteISAI ) {

                $ConsultaEntidadFederativa = DB::table('EntidadFederativa')->select("Nombre")->where('id', $DatosExtras['selectEntidadFederativa'])->value("Nombre");
                #return $ConsultaEntidadFederativa;
                $ConsultaMunicipio = DB::table('Municipio')->select("Nombre")->where('id', $DatosExtras['selectMunicipio'])->value("Nombre");
                $ConsultaLocalidad = DB::table('Localidad')->select('Nombre')->where('id', $DatosExtras['selectLocalidad'])->value("Nombre");
                
                $fecha = $DatosExtras['fechaExpedicion'];
                $fechaEntera = strtotime($fecha);
                #return $fecha;
                $anio = date("Y", $fechaEntera);
                $mes = date("m", $fechaEntera);
                $dia = date("d", $fechaEntera);

                $LugaryFecha = $ConsultaLocalidad. ' Municipio de '.$ConsultaMunicipio. ', Estado de '.$ConsultaEntidadFederativa. '; '.$dia.' dias de '.$meses[intval($mes)]. ' del año '. $anio.'.'; 
            }
            
            $MarcaDocumentos = DB::select("SELECT DISTINCT IdTipoDocumento FROM Padr_onCatastralTramitesISAINotariosDocumentos WHERE EstatusCatastro=1 AND EstatusTercero=1 AND IdTramite='".$DatosTramiteISAI->Id . "'");
            #return "SELECT DISTINCT IdTipoDocumento FROM Padr_onCatastralTramitesISAINotariosDocumentos WHERE IdTramite=".$DatosTramiteISAI->Id;
            $Deslinde = 'circulopcion'; $noadedudopredial='circulopcion'; $noadeudoagua='circulopcion'; $avaluo='circulopcion'; $certificado='circulopcion'; $escritura='circulopcion';
            #return $MarcaDocumentos;
            foreach ($MarcaDocumentos as $doc) {
                switch ( $doc->IdTipoDocumento ) {
                    case 2:
                        $Deslinde="circulopcionMarcado";
                        break;
                    case 3:
                        $noadedudopredial ="circulopcionMarcado";
                        break;
                    case 4:
                        $noadeudoagua ="circulopcionMarcado";
                        break;
                    case 5:
                        $avaluo ="circulopcionMarcado";
                        break;
                    case 6:
                        $certificado ="circulopcionMarcado";
                        break;
                    case 7:
                        $escritura ="circulopcionMarcado";
                        break;
                }
            }
            
            $costoPorMetroTerreno = DB::select('SELECT tpve.Importe FROM TipoPredioValores tpv INNER JOIN TipoPredioValoresEjercicioFiscal tpve ON (tpv.id=tpve.idTipoPredioValores AND tpve.EjercicioFiscal='.$ejercicioFiscal.') WHERE tpv.id='.$DatosPredio[0]->TipoPredioValores);
            $valorParcialTerreno = floatval(str_replace(",", "", $DatosPredio[0]->SuperficieTerreno)) * floatval($costoPorMetroTerreno[0]->Importe) * ($DatosPredio[0]->Indiviso/100);
            
            $datosConstruccionDetalle = "SELECT * FROM Padr_onConstruccionDetalle tcd INNER JOIN TipoConstrucci_on tc ON (tc.id=tcd.TipoConstruci_on) WHERE tcd.idPadron=".$DatosPredio[0]->id; 
            //PARA LAS CONSTRUCCIONES QUE DEL APARTADO B      
            $construccionB='';

            $ejecuta = DB::select($datosConstruccionDetalle);

            $cont=0;
            $totalMetrosB=0;
            $subtotalValorContruccionB=0;

            if( count($ejecuta) == 0){
                $construccionB .= str_repeat('<tr><td class="informacion2" colspan="12">&nbsp;</td></tr>', 4);
            }else{
                foreach( $ejecuta as $RegistroDetalle ){
                    $costoPorMetroConstruccion = DB::select("SELECT FORMAT(tce.Importe,2) as Importe, tc.Caracteristica as Codigo FROM TipoConstrucci_onValores tc INNER JOIN TipoConstrucci_onValoresEjercicioFiscal tce ON (tce.idTipoConstrucci_onValores=tc.id AND tce.EjercicioFiscal=".$ejercicioFiscal.") WHERE Cliente=".$idCliente." AND idTipoConstrucci_on=".$RegistroDetalle->TipoConstruci_on);
                    #return $costoPorMetroConstruccion;
                    $valorParcialConstruccion = floatval(str_replace(",", "", $RegistroDetalle->SuperficieConstrucci_on)) * floatval(str_replace(",", "",$costoPorMetroConstruccion[0]->Importe)) * ($RegistroDetalle->Indiviso/100);
                    
                    $totalMetrosB += $RegistroDetalle->SuperficieConstrucci_on;
                    $subtotalValorContruccionB += $valorParcialConstruccion;
                    $construccionB.='<tr>
                                    <td class="informacion2 text-center" colspan="2">'.$costoPorMetroConstruccion[0]->Codigo.'</td>
                                    <td class="informacion2 text-center" colspan="2">'.$RegistroDetalle->SuperficieConstrucci_on.'</td>
                                    <td class="informacion2 text-center" colspan="2">$'.number_format(str_replace(",","",$costoPorMetroConstruccion[0]->Importe), 2).'</td>
                                    <td class="informacion2 text-center" colspan="3">'.$RegistroDetalle->Indiviso.'</td>
                                    <td class="informacion2 text-right" colspan="3">$'.number_format($valorParcialConstruccion, 2).'</td>
                                </tr>';
                    $cont++; 
                }
            
            }
        }

        $construccionB.='
                <tr>
                    <td class="informacion2 text-right subtotal" colspan="12"> Subtotal: $'.number_format($subtotalValorContruccionB,2).'</td>
                </tr>';

        $consultaColincancias = "SELECT pc.Colindancia,(SELECT pp.Nombre FROM PuntoCardinal pp WHERE pp.id=pc.idPuntoCardinal) AS Nombre FROM Padr_onCatastral po INNER JOIN Padr_onColindancia pc ON (pc.idPadr_onCatastral=po.id) WHERE po.id=".$DatosPredio[0]->Padr_on;
        $ejecuta = DB::select( $consultaColincancias );
        $Colindancias = '';
        foreach( $ejecuta as $registro ){
            $Colindancias.='<tr> <td colspan="4"> '.($registro->Nombre !=''? $registro->Nombre.": " : "" ).'</td> <td colspan="8"> '.$registro->Colindancia.'</td> </tr>';
        }
        
        $propietario = DB::select("SELECT c.id, CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial))  AS Nombre FROM `Contribuyente` c  WHERE c.id=".$DatosPredio[0]->Contribuyente);
        #return $propietario;
        $DatosDocumento = DB::select("SELECT  ccc.Descripci_on as Concepto,
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
            c.id=".$clave." AND 
            ccc.CatalogoDocumento IS NOT NULL AND
            c.Cliente=".$idCliente." AND
            cac.Adicional IS NULL AND
            Origen!='PAGO'" );
        
        $DatosDocumentosObligatoriosCatastro = DB::select("SELECT  ccc.Descripci_on as Concepto,
                c.id, c.FolioCotizaci_on, d.NombreORaz_onSocial as Nombre, ccc.Tiempo, 
                d.id as did, cont.id contid, 
                        (SELECT sum(importe) FROM ConceptoAdicionalesCotizaci_on WHERE ConceptoAdicionalesCotizaci_on.Cotizaci_on=c.id AND Padre is NULL) as importe,
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
            c.Padr_on = ".$DatosPredio[0]->id." AND
            ccc.CatalogoDocumento IN (7, 3) AND
            c.Cliente=".$idCliente." AND
            cac.Adicional IS NULL AND
            Origen='PAGO'");
        #return $DatosDocumentosObligatoriosCatastro;

        $DocumentosObligatorios='<tr> <td colspan="12">Se cubrio el Impuesto sobre Adquisiciones de bienes Inmuebles, Derecho de Certificado Catastral y en su caso multa por presentación extemporanea.</td> </tr>  <tr> <td colspan="3" class="informacion2" width="40%"><b>Documento:</b></td>  <td colspan="3" width="25%" class="informacion2 text-center" ><b>CFDI:</b>  </td> <td colspan="3" width="15%" class="informacion2 text-center" ><b>Fecha de Pago:</b></td>  <td colspan="3" width="20%"class="informacion2 text-center" ><b>Importe:</b></td>  </tr>';
        foreach ($DatosDocumentosObligatoriosCatastro as $valor){
            $DocumentosObligatorios .= '
                                    <tr> 
                                            <td colspan="3">'.$valor->NombreDocumento.'</td>
                                            <td colspan="3" class="text-center">'.$valor->uuid.'</td>
                                            <td colspan="3" class="text-center">'.$valor->Fechapago.'</td>
                                            <td colspan="3" class="text-right"> $'.number_format($valor->importe,2).'</td>
                                    </tr>';
        }
        
        $FechaCorte2 = $DatosDocumento[0]->Fechapago;
        $FechaCorte2 = str_replace(' ', '', ucwords(strftime("%d/ %B /%Y",strtotime($DatosDocumento[0]->Fechapago))));
        $FechaCorte2 = str_replace('/', ' de ', $FechaCorte2);
        
        $FirmanteDirectorCatastro = DB::select('select Reporte.Ruta,LeyendaFirmante.Descripci_on as Leyenda,concat_ws(" ",(SELECT Nombre FROM CatalogoTituloPersonal ctp WHERE ctp.id=Persona.TituloPersonal), Persona.Nombre,Persona.ApellidoPaterno,Persona.ApellidoMaterno) '
        . 'as Nombre,PuestoFirmante.Descripci_on from PuestoEmpleado inner join Persona on PuestoEmpleado.Empleado=Persona.id inner join PuestoFirmante on '
        . 'PuestoEmpleado.PuestoFirmante=PuestoFirmante.id inner join Reporte_Firmante on PuestoEmpleado.id=Reporte_Firmante.PuestoFirmante left '
        . 'join LeyendaFirmante on Reporte_Firmante.LeyendaFirmante=LeyendaFirmante.id inner join Reporte on Reporte_Firmante.Reporte=Reporte.id '
        . 'where Reporte_Firmante.Cliente='.$idCliente.' and Reporte.Ruta="Reporte_Forma3DCC.php" and Reporte_Firmante.Estatus=1 order by Reporte_Firmante.Orden asc');

        #return $FirmanteDirectorCatastro;

        $NumeroDocumentos = DB::select("Select count(idRepositorio) as numero FROM CelaRepositorio WHERE Tabla='Reporte_Forma3DCC' and idTabla=".$DatosDocumento[0]->idContabilidad.$DatosDocumento[0]->id);
        
        $Existe = TRUE;
        $Existe = FALSE;

        $miHTML = '<html>
            <head>
                <meta charset="utf-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1">
        
                <link href="'. (Storage::exists($DIR_RESOURCE.'bootstrap.min.css')?asset(Storage::url($DIR_RESOURCE.'bootstrap.min.css')):'') .'" rel="stylesheet">
                <style>    
                    body{
                        font-size: 12px;
                    }
                    th > div, th > span, th {
                        font-size: 12px;
                        vertical-align: middle;
                    }
                    td > div, td > span, td {
                        font-size: 12px;
                        vertical-align: middle;
                    }
                    .letraGeneral{
                        font-size: 12px;
                    }
                    .main_container{
                        padding-top:15px;
                        padding-left:5px;
                        z-index: 99;
                        background-size: cover;
                        width:735px;
                        height:975px;
                        position:relative;
                    }
                    .break{
                        display: block;
                        clear: both;
                        page-break-after: always;
                    }
                    .tabla-fit{
                        padding: 2px 5px 2px 2px;	
                    }
                    .tabla-fit > thead > tr > th, 
                    .tabla-fit >tbody>tr>td {
                        padding: 2px 5px 2px 2px;
                    }
                    .titulo{
                        background:#ccc;
                        color:black;
                        font-size:20px;
                        font-weight:bold;    
                    }
                    .table > thead > tr > th, 
				    .table>tbody>tr>td {
					    padding: 2px 5px 2px 2px;
                    }
                    .table-bordered>tbody>tr>td {
                        border: 1px solid #ddd;
                    }
                    .titulo2{
                        background: #F4F4F4;
                        text-align: center;
                        font-weight: bold;
                        font-size : 11px;
                    }
                    .titulo3{
                        background: #F4F4F4;
                        text-align: left;
                        font-weight: bold;
                        font-size : 11px;
                    }
                    .titulo4{
                        background: #F4F4F4;
                        text-align: right;
                        font-weight: bold;
                        font-size : 11px;
                    }
                    .informacion2{
                        vertical-align: middle;
                        v-align:middle;
                        font-size : 11px;
	    			}
                    .subtotal{
                        text-align: right;
                        font-weight: bold;
                        font-size : 10px;
                    }
		    		.total{
                        text-align: right;
                        font-weight: bold;
                        font-size : 10px;
                    }
                    .circulopcion {
                        border-radius: 5px;
                        width: 20px;
                        height: 20px;
                    }             
                    .circulopcionMarcado {
                        background: #151414;
                        border-radius: 5px;
                        border-color: #151414;
                        width: 20px;
                        height: 20px;
                    }
                    .fecha{
                        v-align:middle;
                        font-size : 11px;
                    }
                </style>
            </head>
            <body>  
                <div class="container"> 
                    <div class="row">  
                    
                        <table class="table" border="0" width="100%" colspan="12">
                        
                                <tr>
                                    <td colspan="12">
                                       
                                        <table border="0" width="100%">
                                           
                                            <tr>
                                                <td colspan="4" width="20%"><img src="'.asset($Cliente[0]->Logo).'" alt="Logo del cliente"  style="height: 80px;"></td>
                                                <td colspan="4" width="80%"><div class="text-center"><b><h2>'.$Cliente[0]->Descripci_on.'</h2></b></div></td>
                                               <td colspan="4" width="20%"> <img src="'.asset($QR).'" alt="QR Verificador"  style="height: 80px;"> </td>
                                            </tr>  
                                            <tr> 
                                            	<td> &nbsp; </td> </tr>
                                            <tr>
                                                <td colspan="12"><div class="titulo text-center">AVISO DE MOVIMIENTO DE PROPIEDAD INMUEBLE</div> </td>
                                            </tr>
                                            <tr>
                                            	<td colspan="12"> &nbsp; </td>
                                            </tr>
                                            <tr>
	                                            <td colspan="12" class="text-right" style="font-size: 14spx;">
	                                                 <strong>Folio: </strong> <font color="red">'.($idCliente. ' - '.$DatosPredio[0]->id.' - '. $DatosDocumento[0]->id ).'</font>
	                                            </td>
                                            </tr>
                                            <tr> 
                                            	<td colspan="12"> &nbsp; </td> 
                                            </tr>
                                            
                                        </table>
                                        
                                    </td>
                                </tr>
                                
                                <tr>
                                    <td class="titulo3" colspan="12">I.- Datos que proporcionara el Contribuyente</td>
                                </tr>
                                <tr>
                                    <td class="informacion2" width="20%" colspan="2"><strong> Notario o Documento: </strong> </td>
                                    <td class="informacion2" width="40%" colspan="4">'.$DatosExtras['notariaodocumento'].'</td>
                                    <td class="informacion2" width="20%" colspan="2"><strong> Fecha de Operación: </strong> </td>
                                    <td class="informacion2" width="40%" colspan="4"> '.$DatosExtras['fechaOperacion'].' </td>
                                </tr>

                                <tr>
                                    <td class="informacion2" colspan="2"><strong> Importe a la Venta: </strong> </td>
                                    <td class="informacion2" colspan="4"> $ '.$DatosExtras['importeVenta'].'</td>
                                        
                                    <td class="informacion2" colspan="2"><strong> Cuenta: </strong> </td>
                                    <td class="informacion2" colspan="4">'.$DatosExtras['cuentaPredial'].'</td>
                                            
                                </tr>
                                
                                <tr>
                                    <td class="informacion2" colspan="2"><strong> Número de Escritura: </strong> </td>
                                    <td class="informacion2" colspan="4">'.$DatosExtras['escritura'].'</td>
                                        
                                    <td class="informacion2" colspan="2"><strong>Fecha de Escritura: </strong> </td>
                                    <td class="informacion2" colspan="4">'.$DatosExtras['fechaEscritura'].'</td>
                                </tr>
                                            
                                <tr>
                                    <td class="informacion2" colspan="2"><strong> Lugar y Fecha: </strong> </td>
                                    <td class="informacion2" colspan="8">'.$LugaryFecha.'</td>
                                </tr>
                                
                                <tr> <td colspan="12"> &nbsp; </td> </tr>
                                <tr>
                                    <td class="titulo3" colspan="12">Datos que proporcionara el Contribuyente</td>
                                </tr>
                                
                                <tr>
                                    <td class="informacion2" colspan="4"><strong>Dependencia: </strong>  </td>
                                    <td class="informacion2" colspan="8"> Dirección de Catastro </td>
                                    
                                </tr>

                                <tr>
                                    <td class="informacion2" colspan="4"><strong>Nombre del Vendedor: </strong> </td>
                                    <td class="informacion2" colspan="8"> '.$DatosPredio[0]->Vendedor.' </td>
                                </tr>
                                <tr>
                                    <td class="informacion2" colspan="4"><strong>Nombre del Comprador: </strong> </td>
                                    <td class="informacion2" colspan="8"> '.$DatosPredio[0]->Comprador.' </td>
                                </tr>
                                
                                    <tr>
                                    <td class="informacion2" colspan="4"><strong>Ubicación del Predio: </strong> </td>
                                    <td class="informacion2" colspan="8"> '.$DatosPredio[0]->UbicacionPredio.' </td>    
                                </tr>
                                
                                <tr>
                                    <td class="informacion2" colspan="4"><strong>Domicilio de Notificación: </strong> </td>
                                    <td class="informacion2" colspan="8"> '.$DatosPredio[0]->Ubicaci_onDeNotificacion.' </td>
                                </tr>
                                            
                                <tr>
                                    <td class="informacion2" colspan="4"><strong>Localidad: </strong> </td>
                                    <td class="informacion2" colspan="8"> '.$DatosPredio[0]->Localidad.' </td>
                                </tr>
                                
                                <tr> <td colspan="12"> <div class="col-md-12 col-xs-12 col-lg-12"> <div class="col-md-6 col-sm-6 col-xs-6 text-center letraGeneral"><br> <small><strong class="letraGeneral"></strong></small><br><br>
                                        <small><strong class="letraGeneral">'.($DatosPredio[0]->Vendedor).'</strong></small><br>
                                            <small><strong class="letraGeneral">Vendedor</strong></small><br>
                                        &nbsp; </div> <div class="col-md-6 col-sm-6 col-xs-6 text-center letraGeneral"><br> <small><strong class="letraGeneral"></strong></small><br><br>
                                        <small><strong class="letraGeneral">'.($DatosPredio[0]->Comprador).'</strong></small><br>
                                            <small><strong class="letraGeneral">Comprador</strong></small><br>
                                        &nbsp; </div> </div> </td> </tr>
                                    
                                <tr>
                                    <td class="informacion2" colspan="12"><strong>Documentos que acompañan el trámite: </strong> </td>
                                </tr>
                                
                                <tr> <td colspan="2"  class="text-center">  Deslinde Catastral </td> <td colspan="2"  class="text-center"> Certificado de No Adeudo Predial</td> <td colspan="2"  class="text-center">  Certificado de No Adeudo Agua Potable</td>  <td colspan="2"  class="text-center">  Avalúo Fiscal </td> <td colspan="2" class="text-center">  Certificado de Libertad de Gravamen </td> <td colspan="2" class="text-center">  Escritura Preventiva </td> </tr>
                                <tr> <td colspan="2"  class="text-center">  <input type="text" class="'.$Deslinde.'" value=""> </td> <td colspan="2" class="text-center">  <input type="text" class="'.$noadedudopredial.'" value=""> </td> <td colspan="2" class="text-center">  <input type="text" class="'.$noadeudoagua.'" value=""> </td>  <td colspan="2" class="text-center">  <input type="text" class="'.$avaluo.'" value=""> </td> <td colspan="2" class="text-center">  <input type="text" class="'.$certificado.'" value=""> </td> <td colspan="2" class="text-center">  <input type="text" class="'.$escritura.'" value=""> </td> </tr>

                                <tr>
                                    <td colspan="12">
                                        <br><br>
                                        <table class="table tabla-fit" width="100%">
                                            '.$DocumentosObligatorios.'
                                        </table>
                                    </td>
                                </tr>

                                <tr> <td colspan="6"> <div class="col-md-12 col-xs-12 col-lg-12"> <div class="col-md-12 col-sm-12 col-xs-12 text-center letraGeneral"><br> <small><strong class="letraGeneral"></strong></small><br><br>
                                        <small><strong class="letraGeneral">'.($FirmanteDirectorCatastro[0]->Nombre).'</strong></small><br>
                                            <small><strong class="letraGeneral">'.$FirmanteDirectorCatastro[0]->Descripci_on.'</strong></small><br>
                                        &nbsp; </div> </div> </td>   <td colspan="6" class="fecha text-right"><br><br><br><br>'.$Cliente[0]->Localidad.", ".$Cliente[0]->Entidad.'; a <br>'.$FechaCorte2.'. </td>    
                                </tr> 

                                               
                                <tr>
                                    <td class="titulo3" colspan="12">II.- Campos reservados para la Oficina Recepora </td>
                                </tr>

                                <tr>
                                    <td class="informacion2" colspan="5"><strong>Número de Clave Catastral: </strong> '."".$DatosPredio[0]->Cuenta.'</td>
                                    <td class="informacion2" colspan="6"><strong>Número de Cuenta Predial:&nbsp;</strong> '." ".$DatosPredio[0]->CuentaAnterior.'</td>
                                </tr>

                                <tr> <td colspan="12"> &nbsp; </td> </tr>

                                <tr>
                                    <td class="titulo3" colspan="12">III.- Caracteristicas del Predio (Deberan ser llenadas por el Contribuyente)</td>
                                </tr>

                                <tr>
                                    <td class="informacion2" colspan="12">
                                    <p><strong> Colindancias</strong></p>
                                    </td>
                                </tr>

                                '.$Colindancias.'

                                    <tr> <td colspan="12"> &nbsp; </td> </tr>

                                <tr>
                                    <td class="titulo3" colspan="12">IV.- Llenese por el Departamento de Catastro</td>
                                </tr>

                                <tr>
                                    <td class="informacion2" colspan="12">
                                    <p><strong>Terreno</strong></p>
                                        </td>
                                </tr>

                                <tr>
                                    <td class="titulo2" colspan="2"></td>
                                    <td class="titulo2" colspan="2">Superficie M2</td>
                                    <td class="titulo2" colspan="2">Valor Unitario M2</td>
                                    <td class="titulo2" colspan="3">Porcentaje Indiviso</td>
                                    <td class="titulo2" colspan="3">Valor Parcial</td>
                                </tr>
                                <tr>
                                    <td class="informacion2" colspan="2"></td>
                                    <td class="informacion2 text-center" colspan="2">'.number_format(str_replace(",","",$DatosPredio[0]->SuperficieTerreno), 2).'</td>
                                    <td class="informacion2 text-center" colspan="2">$'.$costoPorMetroTerreno[0]->Importe.'</td>
                                    <td class="informacion2 text-center" colspan="3">'.(str_replace(",","",$DatosPredio[0]->Indiviso)).'</td>
                                    <td class="informacion2 text-right" colspan="3">$'.number_format(str_replace(",","",$valorParcialTerreno), 2).'</td>

                                </tr>

                                <tr>
                                    <td class="informacion2" colspan="12">
                                    <p><strong>Construcción</strong></p>
                                    </td>
                                </tr>
                                    <tr>
                                    <td class="titulo2" colspan="2">Código</td>
                                    <td class="titulo2" colspan="2">Supercifie</td>
                                    <td class="titulo2" colspan="2">Valor Unitario M2</td>
                                    <td class="titulo2" colspan="3">Porcentaje Indiviso</td>
                                    <td class="titulo2" colspan="3">Valor Parcial</td>
                                </tr>
                                '.$construccionB.'

                                <tr>
                                    <td class="titulo3" colspan="9">

                                        Valor Catastral:
                                    </td>
                                    <td class="titulo4 text-right" colspan="9">
                                        $'.number_format(str_replace(",","",$subtotalValorContruccionB+$valorParcialTerreno), 2).'
                                    </td>
                                </tr>

                                <tr>
                                    <td class="titulo3" colspan="9">
                                        Valor Fiscal:
                                    </td>
                                    <td class="titulo4 text-right" colspan="9">
                                        $'.number_format(str_replace(",","",$DatosPredio[0]->ValorFiscal), 2).'
                                    </td>
                                </tr>

                                <tr>
                                    <td class="titulo3" colspan="9">
                                            Valor de Operación:
                                    </td>
                                    <td class="titulo4 text-right" colspan="9">
                                        $'.number_format(str_replace(",","",$DatosPredio[0]->ValorPericial), 2).'
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="12">'.ReporteForma3DCC::Leyenda_Firma_Reporte2C1($idCliente, "Reporte_Forma3DCC.php").'</td>
                                </tr>
                                <tr>
                                    <br><td colspan="12">Para el seguimiento del Trámite ingresar a la siguiente URL: <a href="https://servicioenlinea.mx/portalnotarios/">https://servicioenlinea.mx/portalnotarios/</a>  </td>
                                </tr>
                           
                        </table>
                    </div>
                </div>             
            </body>
        </html>';
        #return $miHTML;
        include( app_path() . '/Libs/Wkhtmltopdf.php' );
        try{
            $DirectorioTemporal = 'repositorio/temporal/';
            $NombreArchivo = "Reporte_Forma3DCC_".uniqid()."_".$DatosDocumento[0]->idContabilidad.$DatosDocumento[0]->id.".pdf";
            $wkhtmltopdf = new Wkhtmltopdf(array('path' =>$DirectorioTemporal, 'lowquality'=>true, 'FooterStyleLeft'=>'Grupo Piacza - SUINPAC Contable', 'FooterStyleCenter'=>'Pag. [page] de [toPage]'));
            $wkhtmltopdf->setHtml($miHTML);
            $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $NombreArchivo);
            
            return $DirectorioTemporal.$NombreArchivo;
        }catch (Exception $e) {
            echo "Hubo un error al generar el PDF: " . $e->getMessage();
            return "";
        }
    }

    public static function Leyenda_Firma_Reporte2C12($cliente, $ruta){
        $Respuesta='';
        $distribucion='';
        $_SERVER['PHP_SELF'] = substr($_SERVER['PHP_SELF'],1);
        $Consulta = 'select Reporte.Ruta,LeyendaFirmante.Descripci_on as Leyenda,concat_ws(" ",(SELECT Nombre FROM CatalogoTituloPersonal ctp WHERE ctp.id=Persona.TituloPersonal), Persona.Nombre,Persona.ApellidoPaterno,Persona.ApellidoMaterno) as Nombre,PuestoFirmante.Descripci_on from PuestoEmpleado inner join Persona on PuestoEmpleado.Empleado=Persona.id inner join PuestoFirmante on PuestoEmpleado.PuestoFirmante=PuestoFirmante.id inner join Reporte_Firmante on PuestoEmpleado.id=Reporte_Firmante.PuestoFirmante left join LeyendaFirmante on Reporte_Firmante.LeyendaFirmante=LeyendaFirmante.id inner join Reporte on Reporte_Firmante.Reporte=Reporte.id where Reporte_Firmante.Cliente='.$cliente.' and Reporte.Ruta="'.$ruta.'" and Reporte_Firmante.Estatus=1 order by Reporte_Firmante.Orden asc';
        
        $Resultado = DB::select( $Consulta );
    
        if( count($Resultado) != 0 ){
            $num_firmas = count($Resultado);

            if($num_firmas==4){
                $distribucion="col-md-3 col-sm-3 col-xs-3";
            }
            else if($num_firmas==3){
                $distribucion="col-md-4 col-sm-4 col-xs-4";
            }
            else if($num_firmas==2){
                $distribucion="col-md-6 col-sm-6 col-xs-6";
            }
            else if($num_firmas==1){
                $distribucion="col-md-12 col-sm-12 col-xs-12";
            }
    
            foreach($Resultado as $Renglon ){
                $Respuesta.='<div class="'.$distribucion.' text-center letraGeneral"><br> <small><strong class="letraGeneral">'.($Renglon->Leyenda).'</strong></small><br><br><br />
                        <small><strong class="letraGeneral">'.($Renglon->Nombre).'</strong></small><br />
                            <small><strong class="letraGeneral">'.($Renglon->Descripci_on).'</strong></small><br />
                        &nbsp; </div>';
            }
    
            $Respuesta='<div class="col-md-12 col-xs-12 col-lg-12">'.$Respuesta.'</div>';
        }
    
        return $Respuesta;
    }


    public static function ObtenerDocumentoFirmado($idTabla, $Tabla, $RepoS3, $Reporte = 0){
        global $idDocFirma;
        $RutaTemporal = ""; 
        
        // Si existe algun Documento pendiente de firmar.
       // InsertaValor("Bitacora", array("origen"=>"FuncionesFirma","Bitacora"=>"Estado: ". addslashes(" SELECT Documento, DocumentoOriginal, Estado from DocumentoParaFirma WHERE idOrigen = '".$idTabla."' and Origen='".$Tabla."' and Cliente = '".$_SESSION['CELA_Cliente'.$_SESSION['CELA_Aleatorio']]."' ORDER BY id DESC LIMIT 1;")));
        $Documentos = ObtenValor(" SELECT Documento, DocumentoOriginal, Estado from DocumentoParaFirma WHERE idOrigen = '".$idTabla."' and Origen='".$Tabla."' and Cliente = '".$_SESSION['CELA_Cliente'.$_SESSION['CELA_Aleatorio']]."' ORDER BY id DESC LIMIT 1;");
        //print_r($Documentos);exit;
        if($Documentos['result'] == 'NULL') {// Es documento nuevo para firmar 
            
            $DocOriginal = ObtenValor("select idRepositorio, NombreOriginal, Ruta, Size from CelaRepositorio WHERE idTabla=" . $idTabla . " and Tabla='". $Tabla ."' and Estado=1 and Reciente=1;");
           
            if($DocOriginal!='NULL'){ //Existe el Documento original
                $DocumentoFirma = ParaFirma2($DocOriginal['idRepositorio'], $Tabla, $idTabla, $Reporte, $_SESSION['CELA_Cliente'.$_SESSION['CELA_Aleatorio']], $RepoS3);
                //print_r($DocumentoFirma);exit;
               
                $idFirmadoAnterior = ObtenValor("SELECT idRepositorio FROM CelaRepositorio  WHERE idTabla=" . $idTabla . " and Tabla='". $Tabla ."' and Estado = 1 and Reciente = 0 ORDER BY idRepositorio DESC","idRepositorio");
                $idOriginalAnterior = ObtenValor("SELECT idRepositorio FROM CelaRepositorio  WHERE idTabla=" . $idTabla . " and Tabla='". $Tabla ."' and Estado = 1 and Reciente = 0 ORDER BY idRepositorio ASC","idRepositorio");
                 
                if($DocumentoFirma['Status'] == 'OK'){ ///and $Documento['TotalFirmadas'] == 1){
                   // echo "P".$idFirmadoAnterior." S".  $idOriginalAnterior;  
                    //echo "aqui1";exit;
                    if($idFirmadoAnterior)
                        HistorialArchivos($idFirmadoAnterior, $DocumentoFirma['idRepositorio']);
                    
                    if($idOriginalAnterior)
                        HistorialArchivos($idOriginalAnterior, $DocOriginal['idRepositorio']);
                    
                    /*SE obtiene una ruta temporal de archivo*/
                    $RutaTemporal = DirectorioTemporal()."Cotizacion_Pago_Firmado_".date("His").".pdf";
                    if(!$RepoS3->ObtenerArchivoRepo($DocumentoFirma['Archivo'],$RutaTemporal)) //Obtenemos el archivo firmado del repo
                       $RutaTemporal = "";
                }
                else{
                   // echo "P".$idFirmadoAnterior." S".  $idOriginalAnterior;exit;  
                 //echo "aqui2";exit;
                    $Documentos = ObtenValor(" SELECT Documento, DocumentoOriginal, Estado from DocumentoParaFirma WHERE idOrigen = '".$idTabla."' and Origen='".$Tabla."' and Cliente = '".$_SESSION['CELA_Cliente'.$_SESSION['CELA_Aleatorio']]."' ORDER BY id DESC LIMIT 1;");
                    if($Documentos){
                        if($idFirmadoAnterior)
                            HistorialArchivos($idFirmadoAnterior, $Documentos['Documento']);

                        if($idOriginalAnterior)
                            HistorialArchivos($idOriginalAnterior, $Documentos['DocumentoOriginal']);

                        $RutaTemporal = DirectorioTemporal().$DocOriginal['NombreOriginal'];
                        if(!($RepoS3->ObtenerArchivoRepo($DocOriginal['Ruta'], $RutaTemporal, number_format($DocOriginal['Size'], 0))))
                        $RutaTemporal = ""; 
                    }
                }

            }
        }   
        else{ //Ya existe en la lista para firmar.
            
            $DocOriginal = ObtenValor("select idRepositorio, NombreOriginal, Ruta, Size from CelaRepositorio WHERE idRepositorio='".$Documentos['DocumentoOriginal']."';");
           // InsertaValor("Bitacora", array("origen"=>"FuncionesFirma","Bitacora"=>"Estado: ".$Tabla.$idTabla));  
            if($Documentos['Estado']==0){ // Si todavia no esta firmado.
                $DocumentoFirma = ParaFirma2($DocOriginal['idRepositorio'], $Tabla, $idTabla, $Reporte, $_SESSION['CELA_Cliente'.$_SESSION['CELA_Aleatorio']], $RepoS3);
                //InsertaValor("Bitacora", array("origen"=>"FuncionesFirma","Bitacora"=>$Tabla." ".$idTabla));
                if($DocumentoFirma['Status'] == 'OK'){ //Si se Firmo Correctamente
                    /*SE obtiene una ruta temporal de archivo*/
                    $RutaTemporal = DirectorioTemporal()."Cotizacion_Pago_Firmado_".date("His").".pdf";
                    if(!$RepoS3->ObtenerArchivoRepo($DocumentoFirma['Archivo'],$RutaTemporal)) //Obtenemos el archivo firmado del repo
                       $RutaTemporal = "";
                }
                else{
                   $RutaTemporal = DirectorioTemporal().$DocOriginal['NombreOriginal'];

                   if(!($RepoS3->ObtenerArchivoRepo($DocOriginal['Ruta'], $RutaTemporal, number_format($DocOriginal['Size'], 0))))
                        $RutaTemporal = ""; 
                }
            }
            else{ //Ya esta Firmado el Documento Recuperamos el archivo para mostrarlo.
                $DocFirmado = ObtenValor("select NombreOriginal, Ruta, Size from CelaRepositorio WHERE idRepositorio='".$Documentos['Documento']."';");
                $RutaTemporal = DirectorioTemporal().$DocFirmado['NombreOriginal'];
                if(!($RepoS3->ObtenerArchivoRepo($DocFirmado['Ruta'], $RutaTemporal, number_format($DocFirmado['Size'], 0))))
                    $RutaTemporal = ""; 
            }
        }
       
        return $RutaTemporal;//Regresa la ruta del documento.
    }
}