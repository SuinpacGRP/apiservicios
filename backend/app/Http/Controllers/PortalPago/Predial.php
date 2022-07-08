<?php

namespace App\Http\Controllers\PortalPago;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class Predial extends Controller
{
   

    function GeneraEstadoDeCuentaOficial($idPadron, $idLectura, $cliente){
	
        global $Conexion;
    
        $ServerNameURL=ObtenValor("SELECT Valor FROM CelaConfiguraci_on WHERE Nombre='URLSitio'","Valor");
        require_once ('lib/num_letras.php');
        require_once ("lib/phpqrcode.php");
        $textoletras = new EnLetras();	
    
        $DatosPadron=ObtenValor("SELECT *,  pa.Cuenta as CuentaOK, 
            (SELECT Nombre FROM Municipio WHERE id=d.Municipio) as Municipio, 
            (SELECT Nombre FROM EntidadFederativa WHERE id=d.EntidadFederativa) as Estado,
            (SELECT Nombre FROM Municipio WHERE id=pa.Municipio) as MunicipioPredio, 
            (SELECT Nombre FROM Localidad WHERE id=pa.Localidad) as LocalidadPredio,
            CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno, c.NombreComercial ) as Propietario,
            pa.Colonia as paColonia, d.Colonia as Colonia
                    FROM Padr_onCatastral pa
                    INNER JOIN Contribuyente c ON (c.id=pa.Contribuyente)
                    INNER JOIN DatosFiscales d ON (d.id=c.DatosFiscales)
                    WHERE
                    pa.id=".$idPadron);
                    #precode($DatosPadron,1,1);
            
        $DatosCliente=ObtenValor("SELECT *, m.Nombre as Municipio, e.Nombre as Estado, cr.Ruta  as Logotipo
        FROM Cliente c 
        INNER JOIN DatosFiscalesCliente d ON (d.id=c.DatosFiscales) 
        INNER JOIN EntidadFederativa e ON (e.id=d.EntidadFederativa)
        INNER JOIN Municipio m ON (m.id=d.Municipio)
        INNER JOIN CelaRepositorioC cr ON (cr.idRepositorio=c.Logotipo)
        WHERE c.id=". $cliente);
            
    //	precode("SELECT *, m.Nombre as Municipio, e.Nombre as Estado, cr.Ruta  as Logotipo
    //	FROM Cliente c 
    //	INNER JOIN DatosFiscalesCliente d ON (d.id=c.DatosFiscales) 
    //	INNER JOIN EntidadFederativa e ON (e.id=d.EntidadFederativa)
    //	INNER JOIN Municipio m ON (m.id=d.Municipio)
    //	INNER JOIN CelaRepositorio cr ON (cr.idRepositorio=c.Logotipo)
    //	WHERE c.id=". $cliente,1,1);
        
            $Copropietarios="";
            $ConsultaCopropietarios = "SELECT ( SELECT CONCAT(IF (c.NombreComercial IS NULL OR c.NombreComercial='',CONCAT_WS(' ',c.Nombres,c.ApellidoPaterno,c.ApellidoMaterno),c.NombreComercial)) 
            FROM `Contribuyente` c WHERE c.id=pcc.idContribuyente) AS CoPropietario FROM Padr_onCatastralCopropietarios pcc WHERE pcc.idPadron=".$idPadron;
            $ejecutaCopropietarios=$Conexion->query($ConsultaCopropietarios);
            $row_cnt = $ejecutaCopropietarios->num_rows;
            $aux=1;
            while($registroCopropietarios=$ejecutaCopropietarios->fetch_assoc()){
                if($aux==$row_cnt){
                    $Copropietarios.=$registroCopropietarios['CoPropietario'].'<br /> ';
                }else{
                    $Copropietarios.=$registroCopropietarios['CoPropietario'].', <br /> ';
                }
                $aux++;
            }
            if($Copropietarios!=""){
                $Copropietarios = '<b>Copropietarios:</b> '.$Copropietarios ;
            }
                    
            //Code para Personas vulnerables 
            $ExisteDescuento=ObtenValor("SELECT (SELECT Nombre FROM TipoDescuentoPersona WHERE id=cd.idTipoDescuentoPersona) TipoDescuento FROM Padr_onCatastral p INNER JOIN ClienteDescuentos cd ON (cd.idTipoDescuentoPersona=p.TipoDescuento) WHERE CURDATE() BETWEEN FechaInicial AND FechaFinal AND cd.Tipo='Predial' AND cd.Cliente=p.Cliente AND p.id=$idPadron","TipoDescuento");
        
        $tablaDatos=obtieneDatosLecturaCatastralNuevo($idLectura,$ExisteDescuento);
    
        //cuenta de deposito
        $ConsultaCuentas="SELECT c.N_umeroCuenta, c.Clabe, b.Nombre as Banco FROM CuentaBancaria c INNER JOIN Banco b ON (b.id=c.Banco) WHERE c.Cliente=".$cliente." AND c.CuentaDeRecaudacion=1";
        $ejecutaCuentas=$Conexion->query($ConsultaCuentas);
        $lascuentas='';
        while($registroCuentas=$ejecutaCuentas->fetch_assoc()){
            $lascuentas.='<tr>
                <td colspan="2" align="center">
                    '.$registroCuentas['Banco'].'
                </td>
                <td colspan="2" align="center">
                    '.$registroCuentas['N_umeroCuenta'].'
                </td>
                <td colspan="2" align="center">
                    '.$registroCuentas['Clabe'].'
                </td>
            </tr>';
        
        }
            
          
            $htmlGlobal='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
            <html xmlns="http://www.w3.org/1999/xhtml">
            <link href="'.AnfitrionURL().'bootstrap/css/bootstrap.min.css" rel="stylesheet">
    
            <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <title>ServicioEnLinea.mx</title>
            </head>
            <body>
                    <table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="787px">
                            <tr>
                <td colspan="2" width="33.5%">
                    <img height="200px" src="'.AnfitrionURL().$DatosCliente['Logotipo'].'">
                    
                </td>
                <td  colspan="4"  width="66.5%" align="right">
                    '.$DatosCliente['NombreORaz_onSocial'].'<br />
                    Domicilio Fiscal: '.$DatosCliente['Calle'].' '.$DatosCliente['N_umeroExterior'].'<br />
                    '.$DatosCliente['Colonia'].', C.P. '.$DatosCliente['C_odigoPostal'].'<br />
                    '.$DatosCliente['Municipio'].', '.$DatosCliente['Estado'].'<br />
                    RFC: '.$DatosCliente['RFC'].'	
                    <br /><br />
                    <span style="font-size: 20px;>Estado de Cuenta</span> <br />
                    <span  style="font-size: 12px;"><b>Estado de Cuenta</b>: <span  style="color:#ff0000; font-size: 20px;">'.$_GET['clave'].'</span></span>			
                </td>
            </tr>
            <tr>
                <td colspan="6" align="right"><img width="787px"  height="1px" src="'.AnfitrionURL().'bootstrap/img/barraColores.png" </td></tr>
        </table>
        <table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="787px">
    
            <tr>
            
                <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
                <br /><b>Datos del Predio</b><br />
                <br />	<b>Propietario:</b> '.$DatosPadron['Propietario'].'<br />'.$Copropietarios.
                    '<b>Ubicaci&oacute;n:</b> '.$DatosPadron['Ubicaci_on'].' '.$DatosPadron['paColonia'].'<br />
                    <b>Localidad:</b> '.$DatosPadron['LocalidadPredio'].'<br />
                    <b>Municipio:</b> '.$DatosPadron['MunicipioPredio'].'<br />
                    <b>Clave SUINPAC:</b> '.$DatosPadron['ClaveCatastral'] .'<br />
                    <b>Clave Catastral:</b> '.$DatosPadron['CuentaOK'] .' <br />
                    <b>Cuenta Predial:</b> '.$DatosPadron['CuentaAnterior'] .'<br />
                </td>
                
                <td colspan="3"  width="50%" style="vertical-align:top;" v-align="top">
                <br /><b>Datos de Facturaci&oacute;n</b><br />
                <br /><b>Razon Social:</b> '.$DatosPadron['NombreORaz_onSocial'].'<br />
                    <b>RFC:</b> '.$DatosPadron['RFC'].'<br />
                    <b>Direcci&oacute;n:</b> '.$DatosPadron['Calle'].' '.$DatosPadron['N_umeroExterior'].'
                     '.$DatosPadron['Colonia'].', C.P. '.$DatosPadron['C_odigoPostal'].'<br />
                    '.$DatosPadron['Municipio'].', '.$DatosPadron['Estado'].'.<br />
                </td>
            </tr>
                    <tr>
                <td colspan="6">
                    <br /><img width="787px" height="1px" src="'.AnfitrionURL().'bootstrap/img/barraColores.png"><br /> &nbsp;
                </td>
            </tr>
            
            
        </table>
        
        <style>
        
        </style>
        <table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="787px">
    
    
            
            <tr>
                <td colspan="6"><br />
                    <table class="table table-sm" style="padding:-35px 0 0 0;margin:-10px 0 0 0; font-size:12px;" border="0" width="787px">
                        <tr>
                            <td width="4%" align="center"><b>A&ntilde;o</b></td>
                            <td width="12%" align="center"><b>Base</b></td>
                            <td width="9%" align="center"><b>'.(($DatosPadron['TipoPredio']==10)?'Derecho':'Predial').'</b></td>
                            <td width="9%" align="center"><b>Act</b></td>
                            <td width="9%" align="center"><b>Rec</b></td>
                            
                            <td width="9%" align="center"><b>Gastos Ejecucion</b></td>
                            <td width="9%" align="center"><b>Gastos Embargo</b></td>
                                                    <td width="9%" align="center"><b>Multas</b></td>
                            <td width="9%" align="center"><b>Otros Gastos</b></td>
                            <td width="9%" align="center"><b>Descuento</b></td>
                            <td width="12%" align="center"><b>Total</b></td>
                        </tr>
                        '.$tablaDatos.'
                    </table>
                </td>
            </tr>
            
            
    
        </table>
    </body>
    </html>';
            
       
        include_once("lib/libPDF.php");
            try 
            {
                        $nombre=uniqid()."_".$idPadron;
                $wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'FooterStyleLeft'=>'Grupo Piacza - SUINPAC Contable', 'FooterStyleCenter'=>'Pag. [page] de [toPage]'));
                $wkhtmltopdf->setHtml($htmlGlobal);
                //$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, $nombre.".pdf");		
                $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $nombre.".pdf");		
                return "repositorio/temporal/".$nombre.".pdf";
            } 
            catch (Exception $e) 
            {
                echo "<script>alert('Hubo un error al generar el PDF: ".$e->getMessage()."');</script>";	
            }	
        /**/
    }
}
