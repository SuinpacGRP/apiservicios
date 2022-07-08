<?php

if(!function_exists("AgregaSalto")) {
    function AgregaSalto($cadena, $cuenta){
        $salida="";
        $tam = strlen($cadena);

        if($cuenta>0){
            $partes = $tam/$cuenta;
            if(!is_int($partes))
                $partes = (int)($tam/$cuenta) + 1;
            
            $inicial = 0;
            for($con=1;$con<=$partes;$con++) {
                if($con==1)
                    $salida .= substr($cadena,$inicial, $cuenta);
                else
                    $salida .="<br>". substr($cadena,$inicial, $cuenta);
                $inicial+=$cuenta;
            }
        }else
            $salida=$cadena;

        return $salida;
    }
}

if(!function_exists("NumeroPartes")) {
    function NumeroPartes($Cadena,$MaximoTamano){
        $tam=strlen($Cadena);
        $partes = ($tam/$MaximoTamano);
        if(!is_int($partes))
            $partes = (int)($tam/$MaximoTamano) + 1;

        return $partes;
    }
}

//Ancho de la pagina
$ancho = 970;
$TextoDescuento = '';
if ($descuento == 0) {
    $TextoDescuento .= '
        <td width="80%">&nbsp;</td>
        <td></td>
        <td>&nbsp;Descuento</td>
        <td align="right">$0.00</td>';
}else {
    $TextoDescuento .= '<td></td><td></td>
        <td>&nbsp;Descuento&nbsp;</td>
        <td align="right">$' . number_format(floatval($descuento), $redondeo, '.', ',') . '</td>';
}

//Cadena de impresion de  Total
$Totales='<table height="20" width="'.$ancho.'" class="Parrafo1" >
        <tr style="border-bottom: solid 1px black;" > 
            <td colspan="4">
            </td>
        </tr>
        <tr>
            <td width="25%">&nbsp;</td>
            <td width="25%"></td>
            <td width="25%">&nbsp;Subtotal&nbsp;</td>
            <td width="25%" align="right">$' . number_format(floatval($subtotal), $redondeo, '.', ',') . '</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td></td>
            <td><div class="  encabezado2">&nbsp;I.V.A.&nbsp;</div></td>
            <td align="right">$' . number_format(floatval($iva), $redondeo, '.', ',') . '</td>
        </tr>
        <tr>
            ' . $TextoDescuento . '
        </tr>
        <tr>
            <td>&nbsp;</td><td></td>
            <td>&nbsp;Total</div></td>
            <td align="right">$' . number_format(floatval($total), $redondeo, '.', ',') . '</td>
        </tr>
        <tr >
            <td colspan=4 align="right" style="border-top: solid 1px black;"><strong>' . $letras . '</strong></td>
        </tr>
    </table>';

$observaciones='<table height="80" width="'.$ancho.'">
        <tr>
            <td valign="top">
                <img style="width: 967px; height: 1px;" src="' . asset(Storage::url(env('IMAGES') . 'barraColores.png')) .'" alt="barra" />
                <p> Observaciones: '.$Observaciones.' </p>
            </td>
        </tr>
    </table>';

//Secciones que forman la hoja, repetitivas
//$numerodeconceptos=19; // Solo para pruebas
$max_conceptos = 21; // tenia 21 le puse 20 por que se pierde una concepto no se por que 

//Calcula el numero de hojas.
$partes = $numerodeconceptos / $max_conceptos;
if(!is_int($partes))
    $partes = (int)($numerodeconceptos/$max_conceptos) +1;

$ListaConceptos = "";
$TextoDescuento = "";
$claveUnidad="";
$max = $max_conceptos;
$imprimetotal = "";

if($max > $numerodeconceptos)
    $max = $numerodeconceptos;

$conceptosvariable=$numerodeconceptos;
$inicio = 0;
#$Estilo = '';

for ($con = 1; $con <= $partes; $con++) {
    $ListaConceptos = "";
    $con3 = 0;//Cuenta las lineas impresas incluyendo la que se separan en partes.

    for($con2 = $inicio; $con2 < $max; $con2++){
        #echo 'Ciclo: ' . $con2;

        if(isset($concepto[$con2]['ClaveUnidad'])){
            $claveUnidad=$concepto[$con2]['ClaveUnidad'];
        }else{
            $claveUnidad="";
        }

        $ListaConceptos .='<tr>
                <td width="10%" align="center">' . number_format(floatval($concepto[$con2]['cantidad']), $redondeo, '.', ',') . '</td>
                <td width="10%" align="center">' . $claveUnidad . '</td>
                <td width="50%" align="left">' . $concepto[$con2]['descripcion'] . '</td>
                <td width="15%" align="right">$' . number_format(floatval($concepto[$con2]['precio']), $redondeo, '.', ',') . '&nbsp;&nbsp;</td>
                <td width="15%" align="right">$' . number_format(floatval($concepto[$con2]['importe']), $redondeo, '.', ',') . '</td>
            </tr>';
        
        if(strlen($concepto[$con2]['descripcion']) > 75){
            // Si la cade es mas larga que el tamaño de la columna calcular en cuantas partes se va a dividir y restarlo al maximo por hoja
            $num = NumeroPartes($concepto[$con2]['descripcion'], 76) -1; //Se resta 1 para calcular el numero de lineas extra, la primera linea no cuenta por eso la resta.
            $conceptosvariable += $num; // Para calcular las nuevas hojas.
            $con3 += $num;
        }
        $con3++;

        if($con3 >= $max_conceptos)
            break; //Si llego al numero maximo de conceptoos por hoja
    }
    
    $inicio = $con2 + 1; //iniciamos en el concepto donde nos quedamos despues del break
    $max = $con2 + $max_conceptos; //Establecemos el intervalo con2 +  max_comceptos.

    //Calcula el numero de hojas necesarias por conceptos
    $partes = ($conceptosvariable)/$max_conceptos;
    if(!is_int($partes))
        $partes = (int)(($conceptosvariable)/$max_conceptos) +1;

    if($max>$numerodeconceptos)
        $max=$numerodeconceptos;

    //Si se calculo una hoja pero hay conceptos que saltaron a la segunda hoja por falta de espacio.
    //if($inicio<$max) //Todavia faltan conceptops por imprimir
    //  if($con==$partes) //Es la ultima hoja y faltan conceptos por imprimir
    //    $partes++; //Inserta una segunta hoja para imprimir los conceptos faltantes.
    if($con2 >= $numerodeconceptos || $con2 >= $numerodeconceptos -1)
        $imprimetotal=$Totales; //Si ya se imprimieron todos los conceptos poner el total.

    /* Estilo */
    $Estilo = '<!DOCTYPE html>
        <html lang="es">
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <link href="'. asset(Storage::url(env('RESOURCES').'bootstrap.min.css')) .'"rel="stylesheet">
                <link href="https://fonts.googleapis.com/css?family=Open+Sans" rel="stylesheet" type="text/css">
                <style> 
                    .Marco_Principal{
                        padding-top: 20px;
                    }
                    .Parrafo1{
                        font-size: small;  
                    }
                    .Parrafo2{
                        font-size: x-small;
                    }
                    .Parrafo3{
                        font-size: xx-small;
                    }
                    .Parrafo4{
                        font-size: 90%;
                    }
                    .break{
                        display: block;
                        clear: both;
                        page-break-after: always;
                    } 
                    .linea{
                        border 1px solid black;
                    }
                    .rotate {
                        /* Safari */
                        -webkit-transform: rotate(-90deg);

                        /* Firefox */
                        -moz-transform: rotate(-90deg);

                        /* IE */
                        -ms-transform: rotate(-90deg);

                        /* Opera */
                        -o-transform: rotate(-90deg);

                        /* Internet Explorer */
                        filter: progid:DXImageTransform.Microsoft.BasicImage(rotation=3);
                    }
                </style>
            </head>
            <body>';
    /* Estido */
    /* Parte Superior */
    $Superior = '<div class="Marco_Principal">';
    /* Fin Parte Superior */

    /* Datos Emisor Receptor */
    if(!isset($Regimen)){
        $Regimen="";
    }
    $DatosER = '<table height="250" class="Parrafo4" width="'.$ancho.'">
            <tr>
                <td  valign="top" width="%50%" >
                <img src="' . asset($url_logo) . '" alt="Logo del cliente" style="height: 120px;"><br><br><br>
                    <p><b>Datos Fiscales: <br /><span style="14px">' . $Rrazonsocial . '</span></b><br />' . $Rrfc . '<br />' . $Rcalle . ' ' . $Rnumexterior . ' ' . $Rnuminterior . ' ' . $Rcolonia . ' ' . ucwords(strtolower($Rmunicipio)) . ', ' . ucwords(strtolower($Restado)) . ' ' . '<br>' . $Rcp .'<br>'. $Regimen.'
                    </p>
                </td>
                <td align="right" valign="top" width="50%" >
                    <p><strong>Emisor: </strong>' . $razonsocial . '<br /> <strong>RFC:</strong> ' . $rfc . '<br /><strong>Domicilio Fiscal:</strong> ' . $calle . ' ' . $numexterior . ' ' . $numinterior . ' ' . $colonia . ' ' . ucwords(strtolower($municipio)) . ' ' . ucwords(strtolower($estado)) . ' ' . '<br><strong>C.P.:</strong> ' . $cp . '<br /><strong>Lugar de Expedici&oacute;n:</strong> ' . ucwords(strtolower($municipio)) . ', ' . ucwords(strtolower($estado)) . '<br/> <strong>Regimen Fiscal:</strong> ' . $miregimenFiscal . '
                        <br/><br><strong>Folio:<strong> '.$folio. '<br/><strong>Serie del Certificado del emisor:</strong> ' . $certificadoCSD.'<br><strong>Folio Fiscal:</strong> ' . $UUID . '<br><strong>No. de Serie del Certificado del SAT:</strong> ' . $noCertificadoSAT . '<br><strong>Fecha y hora de certificaci&oacute;n:</strong> ' . $FechaTimbrado . '<br><br><strong>Fecha de Expedici&oacute;n: <strong>'.$fecha.' '.$hora.'
                    </p >
                </td>
            </tr>
        </table>
        ';
    /* Fin Datos Emisor Receptor */

    /* Datos Factura */
    $DatosFactura = '<table  height="520" width="'.$ancho.'" class="  Parrafo1"> <tr><td valign="top">
            <table  height="20" width="'.$ancho.'">
                <tr>
                    <td>
                        <img style="width: 967px; height: 1px;" src="' . asset(Storage::url(env('IMAGES') . 'barraColores.png')) .'" alt="Mountain View" />
                        <p><strong>Conceptos Facturados:</strong></p>
                    </td>
                </tr>
            </table>
            <table width="'.($ancho - 2).'" class="  Parrafo1" >
                <tr style="border-bottom: solid 1px black;">
                    <td height="20" width="10%" align="center"><strong>Cantidad</strong></th>
                    <td width="10%" align="center"><strong>Unidad</strong></td>
                    <td width="50%" align="center"><strong>Descripci&oacute;n</strong></td>
                    <td width="15%" align="right"><strong>Valor Unitario</strong></td>
                    <td width="15%" align="right"><strong>Importe</strong></td>
                </tr>
                ' . $ListaConceptos . '
            </table>
            '.$imprimetotal.'
            <td></tr>
        </table>';
    /* Fin de Datos Factura */

    /* Parte Inferior */
    if(!isset($Propietario)){
        $Propietario="";
    }
    if(!isset($Ubicacion)){
        $Ubicacion="";
    }
    if(!isset($CuentaVigente)){
        $CuentaVigente="";
    }
    
    $Inferior = '<table height="80" width="'.$ancho.'" class=" ">
            <tr>
                <td  valign="top" colspan="4">
                    <img style="width: 967px; height: 1px;" src="' . asset(Storage::url(env('IMAGES') . 'barraColores.png')) . '" alt="Mountain View" />
                    <p><b> Datos del Predio:</b> </p>
                </td>
            </tr>
            <tr>
                <td colspan="2" ><b>Propietario:</b>  '.$Propietario.'</td>
                <td colspan="2"><b>Ubicaci&oacute;n: </b> '.$Ubicacion.'</td>
            </tr>
            <tr>
                <td width="25%"><b>Clave Catastral:</b> '.$CuentaVigente.'</td>
                <td width="25%"><b>Cuenta:</b>  '.$CuentaAnterior.' </td>
                <td width="25%"><b>Base Gravable:</b>  '.$ValorCatastral.' </td>
                <td width="25%"><b>Año de Pago:</b> '.$a_noPago.' </td>
            </tr>
            <tr>
                <td><b>Superficie del Terreno:</b>  '.$SuperficieTerreno.'</td>
                <td><b>Costo por m2:</b>  '.$ValorTerreno.'</td>
                <td><b>Superficie de la Construcci&oacute;n:</b>  '.$SuperficieConstruccion.'</td>
                <!--td><b>Costo por m2</b>  '.$ValorConstruccion.' </td-->
                <!--td><b>Cuenta Predial:</b> '.$CuentaPredial.' </td-->
                
            </tr>
  		
        </table>'.$observaciones.'
        <table height="110" width="'.$ancho.'" class="  Parrafo2">
            <tr>
                <td  valign="top" colspan="3">
                <img style="width: 967px; height: 1px;" src="' . asset(Storage::url(env('IMAGES') . 'barraColores.png')) . '" alt="Mountain View" />
            </td>

            </tr>
            <tr>
                <td width="420">
                    <p> Este documento es una representación impresa de un CFDI<br> Forma de Pago: ' . $formadepago . '<br>Metodo de Pago: ' . $metodopago . '<br>Regimen Fiscal: '.$Regimen.' <br>Tasa IVA ' . $tasaIVA . '</p>
                </td>
                <td class="rotate" align="center">
                    Firma   y   Sello
                </td>
                <td align="left" valign="botton">
                '.$UserElab.'<br />'.$Leyenda.'
                </td>
            </tr>
        </table>
        <table width="'.$ancho.'" class="  Parrafo3">
            <tr>
                <td>
                    <img src="' . $rutatimbradosimg . '"  class="qr_imagen"/>
                </td>
                <td>
                    <div>Sello digital del CFDI</div>
                    <div >' .  AgregaSalto($selloCFD, 120) . '</div>
                    <div>&nbsp;</div>
                    <div>Sello del SAT</div>
                    <div>' . AgregaSalto($selloSAT, 120) . '</div>
                    <div>&nbsp;</div>
                    <div>Cadena original del complemento de certificaci&oacute;n digital del SAT</div>
                    <div>' . AgregaSalto($cadenaoriginal, 120) . '</div>
                </td>
            </tr>
        </table>
    </body>
    </html>
    <div id="Salto" class="break"></div></div>';
    /* Fin Parte Inferior */
    $mihtml = $mihtml.$Superior.$DatosER.$DatosFactura.$Inferior;
}

$mihtml = $Estilo.$mihtml;

?>