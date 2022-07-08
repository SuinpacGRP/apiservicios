<?php

namespace App\Http\Controllers;

use App\Cliente;
use App\Funciones;
use App\Modelos\XML;
use App\Libs\QRcode;
use App\Modelos\Pais;
use App\Libs\Wkhtmltopdf;
use App\Modelos\Municipio;
use App\Modelos\Cotizacion;
use App\Modelos\XMLIngreso;
use Illuminate\Http\Request;
use App\Modelos\Contribuyente;
use App\Modelos\TipoCobroCaja;
use App\Modelos\CatalogoUnidad;
use App\Modelos\PadronCatastral;
use App\Modelos\CelaRepositorioC;
use App\Modelos\EntidadFederativa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Modelos\EncabezadoContabilidad;
use App\Modelos\ConceptoAdicionalCotizacion;

class FacturaController extends Controller
{
    public function __construct(){
        #$this->middleware('jwt', ['except' => ['login']]);
    }

    public function obtenerFactura(Request $request){
        $cliente   = $request->Cliente;
        $ejercicio = $request->Ejercicio;
        $claves    = $request->Claves;

        Funciones::selecionarBase($cliente);

        $rutacompleta  = "";
        $dias          = array("Domingo","Lunes","Martes","Miercoles","Jueves","Viernes","S&aacute;bado");
        $meses         = array("Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");
        $hay_resultado = false;
        $busqueda      = false;
        $mihtml        = "";

        $mixml          = XMLIngreso::select('xml', 'Folio')->where('idCotizaci_on', $claves)->first();
        $elxml          = $mixml->xml;
        $mifolio        = $mixml->Folio;
        $N_umeroP_oliza = '';
        $idTicket=Funciones::ObtenValor("select PT.id as IdTicket from Pago P join PagoTicket PT on P.id=PT.Pago where P.Cotizaci_on=".$claves,"IdTicket");
        if( $elxml != "" ){
            $datsCotizacion = Cotizacion::find($claves);

            //aqui mando al otro archivo de IVA
            $tipo_factura      = "Factura Electr&oacute;nica";
            $ejecutaObtieneXML = XMLIngreso::select('xml')->where('idCotizaci_on', $claves)->get();
            $htmlGlobal        = '';

            $DatosXML   = XMLIngreso::where('idCotizaci_on', $claves)->first();
            $string     = preg_replace("/[\r\n|\n|\r]+/", " ", $DatosXML->DatosExtra);
            $DatosExtra = json_decode($string, true);
            
            $Leyenda          = isset($DatosExtra['Leyenda']) ? $DatosExtra['Leyenda'] : '';
            $UserElab         = isset($DatosExtra['Usuario']) ? $DatosExtra['Usuario'] : '';
            $Observaciones    = isset($DatosExtra['Observacion']) ?  addslashes( $DatosExtra['Observacion'] ) : '';
            $ContratoVigente  = isset($DatosExtra['ContratoVigente'] ) ? $DatosExtra['ContratoVigente'] : '';
            $ContratoAnterior = isset($DatosExtra['ContratoAnterior'] ) ? $DatosExtra['ContratoAnterior'] : '';
            $Medidor          = isset($DatosExtra['Medidor']) ? $DatosExtra['Medidor'] : '' ;
            $Domicilio        = isset($DatosExtra['Domicilio'] ) ? $DatosExtra['Domicilio'] : '';
            $Municipio        = isset($DatosExtra['Municipio'] ) ? $DatosExtra['Municipio'] : '';
            $Ruta             = isset($DatosExtra['Ruta'] ) ? $DatosExtra['Ruta'] : '';
            $TipoToma         = isset($DatosExtra['TipoToma'] ) ? $DatosExtra['TipoToma'] : '';
            $M_etodoCobro     = isset($DatosExtra['M_etodoCobro'] ) ? $DatosExtra['M_etodoCobro'] : '';
            $numcuentapago    = isset($DatosExtra['NumCuenta'] ) ? $DatosExtra['NumCuenta'] : '';
            $CuotaFija        = isset($DatosExtra['CuotaFija'] ) ? $DatosExtra['CuotaFija'] : '';

            if($datsCotizacion->Tipo==10  || $datsCotizacion->Tipo==3 || $datsCotizacion->Tipo==11 || (isset($DatosExtra['TipoCotizacionPredial']) && $DatosExtra['TipoCotizacionPredial']=="Servicio")) {
          
                #if(is_null($DatosExtra['A_noPago'])){
                if( !array_key_exists( 'A_noPago', $DatosExtra ) || $DatosExtra['A_noPago'] == null ){
                    $DatosExtra['A_noPago'] = $ejercicio;
                }

                $A_noPago = $DatosExtra['A_noPago'];

                $ValorcatastralDB = Cotizacion::from('Cotizaci_on as co1')
                    ->select('te.Importe as ImportePredioValor')
                    ->join('Padr_onCatastral as po', 'co1.Padr_on', 'po.id')
                    ->join('TipoPredioValores as t', 'po.TipoPredioValores', 't.id')
                    ->join('TipoPredioValoresEjercicioFiscal as te', function ($join) use ($A_noPago){
                            $join->on('te.idTipoPredioValores', 't.id');
                            $join->on('te.EjercicioFiscal', DB::raw( $A_noPago ) );
                        })
                    ->where('co1.id', $claves)
                    ->value('ImportePredioValor');

                $ValorConstruccionDB =  ConceptoAdicionalCotizacion::from('ConceptoAdicionalesCotizaci_on as co')
                    ->select('to1.Importe as ImporteConstruccionValor')
                    ->join('Cotizaci_on as co1', 'co.Cotizaci_on', 'co1.id')
                    ->join('Padr_onCatastral as po', 'co.Padr_on', 'po.id')
                    ->join('TipoConstrucci_onValores as to1', 'po.TipoConstrucci_onValores', 'to1.id')
                    ->where([
                        ['co1.id', $claves],
                        ['co.TipoContribuci_on', 1]
                    ])
                    ->value('ImporteConstruccionValor');

                $datosHistorial = Cotizacion::from('Cotizaci_on as co1')
                    ->join('Padr_onCatastral as po', 'co1.Padr_on', 'po.id')
                    ->join('Padr_onCatastralHistorial as pch', function ($join) use ($A_noPago){
                        $join->on('pch.Padr_onCatastral', 'po.id');
                        $join->on('pch.A_no', DB::raw( $A_noPago ) );
                    })
                    ->where('co1.id', $claves)
                    ->first();

                $ValorConstruccionDB = $datosHistorial->ConstruccionCosto;

                $Ubicacion = PadronCatastral::selectRaw("CONCAT (COALESCE (Ubicaci_on,''),' ',COALESCE (Colonia,'')) AS Ubicaci_on")
                    ->where('id', $datsCotizacion->Padr_on)
                    ->value('Ubicaci_on');

                if( $Ubicacion == " "){
                    $Ubicacion = "No disponible";
                }

                $CuentaVigente  		= isset($DatosExtra['Cuenta'] ) ? $DatosExtra['Cuenta'] : '';
                $CuentaAnterior 		= isset($DatosExtra['CuentaAnterior'] ) ? $DatosExtra['CuentaAnterior'] : '';
                $ValorCatastral         = isset($DatosExtra['ValorCatastral'] ) ? $DatosExtra['ValorCatastral'] : ''; 
                $a_noPago 				= isset($DatosExtra['A_noPago'] ) ? $DatosExtra['A_noPago'] : '';
                $SuperficieConstruccion = isset($DatosExtra['SuperficieConstruccion']) && $DatosExtra['SuperficieConstruccion'] != '' ? number_format(str_replace(",", "", $DatosExtra['SuperficieConstruccion'] ),2) : '';
                $ValorConstruccion		= isset($ValorConstruccionDB) && $ValorConstruccionDB != '' ? number_format($ValorConstruccionDB, 2) : '0.00';
                $SuperficieTerreno		= isset($DatosExtra['SuperficieTerreno'] ) ? str_replace(",","",$DatosExtra['SuperficieTerreno']) : '';
                $ValorTerreno			= isset($ValorcatastralDB) && $ValorcatastralDB != '' ? number_format($ValorcatastralDB,2) : '0.00';
                
                if($DatosXML->Contribuyente != "")
                    $Propietario = Contribuyente::from('Contribuyente as c')
                    ->selectRaw("COALESCE ((CONCAT(c.id,' - ',c.NombreComercial,' - ') ), (CONCAT(c.id,' - ',c.Nombres,' ',c.ApellidoPaterno,' ', c.ApellidoMaterno) ) ) AS NombreORaz_onSocial")
                    ->join('DatosFiscales as d', 'c.DatosFiscales', 'd.id')
                    ->where('c.id', $DatosXML->Contribuyente)
                    ->value('NombreORaz_onSocial');
                else
                    $Propietario="No disponible";
            }

            $N_umeroP_oliza = EncabezadoContabilidad::where('Cotizaci_on', $claves)->value('N_umeroP_oliza');

            foreach($ejecutaObtieneXML as $filaObtieneXML){
                $clave   = XML::where('id', $filaObtieneXML->xml)->value('xml');
                $Addenda = utf8_decode(XML::where('id', $filaObtieneXML->xml)->value('addenda'));

                $xml = @simplexml_load_string($clave);
                $ns  = $xml->getNamespaces(true);
                $xml->registerXPathNamespace('c', $ns['cfdi']);
                $xml->registerXPathNamespace('t', $ns['tfd']);

                $estraslado         = false;
                $esretencion        = false;
                $tieneImplocal      = false;
                $ImpoLocalTraslado  = array("Nombre"=>"","Importe"=>"","Tasa"=>"");
                $ImpoLocalRetencion = array("Nombre"=>"","Importe"=>"","Tasa"=>"");
                
                foreach ($xml->xpath('//cfdi:Comprobante') as $cfdiComprobante){}

                if($cfdiComprobante['Version']=='3.2' || $cfdiComprobante['version']=='3.2' )
                    $versionxml="3.2";
                else
                    $versionxml="3.3";
                #return $ns;
                if($versionxml=="3.2"){
                    if(array_key_exists("implocal", $ns)){
                        $tieneImplocal = true;
                        $xml->registerXPathNamespace('l', $ns['implocal']);

                        foreach ($xml->xpath('//l:ImpuestosLocales') as $ImpuestosLocales) {}
                        $l = 0;

                        foreach ($xml->xpath('//l:ImpuestosLocales//l:RetencionesLocales') as $ImpRetencionesLocales) {
                            $ImpoLocalRetencion['Nombre']=$ImpRetencionesLocales['ImpLocRetenido'];
                            $ImpoLocalRetencion['Importe']=$ImpRetencionesLocales['Importe'];
                            $ImpoLocalRetencion['Tasa']=$ImpRetencionesLocales['TasadeRetencion'];
                            $l++;
                            $esretencion=true;
                        }
            
                        foreach ($xml->xpath('//l:ImpuestosLocales//l:TrasladosLocales') as $ImpTrasladosLocales) {
                            $ImpoLocalTraslado['Nombre']=$ImpTrasladosLocales['ImpLocTrasladado'];
                            $ImpoLocalTraslado['Importe']=$ImpTrasladosLocales['Importe'];
                            $ImpoLocalTraslado['Tasa']=$ImpTrasladosLocales['TasadeRetencion'];
                            $l++;
                            $estraslado=true;
                        }
                    }
        
                    if(array_key_exists("nomina", $ns)){
                        $esnomina=true;
                        $tipo_factura="Recibo de N&oacute;mina";
            
                        $xml->registerXPathNamespace('n', $ns['nomina']);

                        foreach ($xml->xpath('//n:Nomina') as $Nomina) {}
                        foreach ($xml->xpath('//n:Nomina//n:Percepciones') as $Percepciones) {}

                        $j=0;
            
                        foreach ($xml->xpath('//n:Nomina//n:Percepciones//n:Percepcion') as $Percepcion){
                            if(floatval($Percepcion['ImporteExento'])==0.0 AND floatval($Percepcion['ImporteGravado'])==0.0){}
                            else{
                                $perpecpiones[$j]['Percepcion']=$Percepcion['TipoPercepcion'];
                                $perpecpiones[$j]['Clave']=$Percepcion['Clave'];
                                $perpecpiones[$j]['Concepto']=$Percepcion['Concepto'];
                                $perpecpiones[$j]['Gravado']=floatval($Percepcion['ImporteGravado']);
                                $perpecpiones[$j]['Excento']=floatval($Percepcion['ImporteExento']);
                                $j++;
                            }
                        }
            
                        $totalPercepciones=$j;
                        foreach ($xml->xpath('//n:Nomina//n:Deducciones') as $Deducciones) {}
                        $k=0;

                        foreach ($xml->xpath('//n:Nomina//n:Deducciones//n:Deduccion') as $Deduccion){
                            if(floatval($Deduccion['ImporteExento'])==0.0 AND floatval($Deduccion['ImporteGravado'])==0.0){}
                            else{
                                $deducciones[$k]['Deduccion']=$Deduccion['TipoDeduccion'];
                                $deducciones[$k]['Clave']=$Deduccion['Clave'];
                                $deducciones[$k]['Concepto']=$Deduccion['Concepto'];
                                $deducciones[$k]['Gravado']=floatval($Deduccion['ImporteGravado']);
                                $deducciones[$k]['Excento']=floatval($Deduccion['ImporteExento']);
                                $k++;
                            }
                        }
            
                        $totalDeducciones=$k;
            
                        //DATOS DE NOMINA
                        $CURP                   = isset($Nomina['CURP']) ? $Nomina['CURP'] : '' ;
                        $FechaInicioLaboral     = isset($Nomina['FechaInicioRelLaboral']) ? $Nomina['FechaInicioRelLaboral'] : '' ;
                        $TipoJornada            = isset($Nomina['TipoJornada']) ? $Nomina['TipoJornada'] : '' ;
                        $TipoContrato           = isset($Nomina['TipoContrato']) ? $Nomina['TipoContrato'] : '' ;
                        $NoEmpleado             = isset($Nomina['NumEmpleado']) ? $Nomina['NumEmpleado'] : '' ;
                        $NoSegSocial            = isset($Nomina['NoSegSocial']) ? $Nomina['NoSegSocial'] : '' ;
                        $Regimen                = isset($Nomina['TipoRegimen']) ? $Nomina['TipoRegimen'] : '' ;
                        $RiesgoPuesto           = isset($Nomina['RiesgoPuesto']) ? $Nomina['RiesgoPuesto'] : '' ;
                        $Banco                  = isset($Nomina['Banco']) ? $Nomina['Banco'] : '' ;
                        $Clabe                  = isset($Nomina['Clabe']) ? $Nomina['Clabe'] : '' ;
                        $PeriodicidadPago       = isset($Nomina['PeriodicidadPago']) ? $Nomina['PeriodicidadPago'] : '' ;
                        $diasPagados            = isset($Nomina['NumDiasPagados']) ? $Nomina['NumDiasPagados'] : '' ;
                        $fechaPago              = isset($Nomina['FechaPago']) ? $Nomina['FechaPago'] : '' ;
                        $Puesto                 = isset($Nomina['Puesto']) ? $Nomina['Puesto'] : '' ;
                        $Departamento           = isset($Nomina['Departamento']) ? $Nomina['Departamento'] : '' ;
                        $SalarioDiarioIntegrado = isset($Nomina['SalarioDiarioIntegrado']) ? $Nomina['SalarioDiarioIntegrado'] : '' ;
                        $SalarioBaseCotizacion  = isset($Nomina['SalarioBaseCotizacion']) ? $Nomina['SalarioBaseCotizacion'] : '' ;
                        $fechaInicialPago       = isset($Nomina['FechaInicialPago']) ? $Nomina['FechaInicialPago'] : '' ;
                        $fechaFinalPago         = isset($Nomina['FechaFinalPago']) ? $Nomina['FechaFinalPago'] : '' ;
                        $tipocomp               = "nomina";
                    }else{
                        $esnomina = false;
                        $tipocomp = "factura";
                    }
        
                    //EMPIEZO A LEER LA INFORMACION DEL CFDI E IMPRIMIRLA
                    foreach ($xml->xpath('//cfdi:Comprobante') as $cfdiComprobante){}
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Emisor') as $Emisor){}
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Emisor//cfdi:DomicilioFiscal') as $DomicilioFiscal){}
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Emisor//cfdi:RegimenFiscal') as $RegimenFiscal){}
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Emisor//cfdi:ExpedidoEn') as $ExpedidoEn){}
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Receptor') as $Receptor){}
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Receptor//cfdi:Domicilio') as $ReceptorDomicilio){}
        
                    $i=0;
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Conceptos//cfdi:Concepto') as $Concepto){
                        $concepto[$i]['cantidad']=$Concepto['cantidad'];
                        $concepto[$i]['unidad']=$Concepto['ClaveUnidad'];
                        $concepto[$i]['descripcion']=$Concepto['descripcion'];
                        $concepto[$i]['precio']=$Concepto['valorUnitario']."";
                        $concepto[$i]['importe']=$Concepto['importe']."";
                        $concepto[$i]['ClaveUnidad']=$Concepto['ClaveUnidad'];
                        $i++;
                    }

                    $numerodeconceptos = $i;
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Impuestos') as $misImpuestos){}
            
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Impuestos//cfdi:Traslados//cfdi:Traslado') as $Traslado){}
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Impuestos//cfdi:Traslados//cfdi:Retencion') as $Retencion){}
                    foreach ($xml->xpath('//t:TimbreFiscalDigital') as $TimbreFiscalDigital) {}
        
                    /*********************************************************************************************/
                    /*********************************************************************************************/
                    /*********************************************************************************************/
                    //-----------------------------------------------------------------
                    //Para los datos de la factura EMISOR, RECEPTOR, CONCEPTOS e IMPUESTOS
                    //-----------------------------------------------------------------
            
                    //Para estado de la factura  1=vista previa  2= Emitido   3=cancelado
                    $est_factura='';
            
                    $fecha       = isset($cfdiComprobante['fecha']) ? substr($cfdiComprobante['fecha'], 0, 10) : '' ;
                    $hora        = isset($cfdiComprobante['fecha']) ? substr($cfdiComprobante['fecha'], 11, 8) : '' ;
                    $folio       = isset($cfdiComprobante['folio']) ? $cfdiComprobante['folio'] : '' ;
                    $serie       = isset($cfdiComprobante['serie']) ? $cfdiComprobante['serie'] : '' ;
                    $formadepago = isset($cfdiComprobante['formaDePago']) ? $cfdiComprobante['formaDePago'] : '' ;
                    $metodopago  = isset($cfdiComprobante['metodoDePago']) ? $cfdiComprobante['metodoDePago'] : '' ;
                    
                    if(strlen($M_etodoCobro) < 3){
                        if($metodopago == 99)
                            $metodopago = $metodopago." Otros";
                        else
                            $metodopago = $metodopago." ". TipoCobroCaja::where('Clave', $metodopago)->value('Descripci_on');
                    }

                    $tipocomprobante = isset($cfdiComprobante['tipoDeComprobante']) ? $cfdiComprobante['tipoDeComprobante'] : '' ;
                    $numcuentapago   = isset($cfdiComprobante['NumCtaPago']) ? $cfdiComprobante['NumCtaPago'] : '' ;
                    $lugarexpedicion = isset($cfdiComprobante['LugarExpedicion']) ? $cfdiComprobante['LugarExpedicion'] : '' ;
                    $moneda          = isset($cfdiComprobante['Moneda']) ? $cfdiComprobante['Moneda'] : '' ;
                    $cadenaoriginal  = isset($cfdiComprobante['CadenaOriginal']) ? $cfdiComprobante['CadenaOriginal'] : '' ;
                    $descuento       = isset($cfdiComprobante['descuento']) ? $cfdiComprobante['descuento'] : '' ;
                    
                    //datos fiscales EMISOR
                    $url_logo = CelaRepositorioC::where([
                            ['Cotizaci_on.Cliente', 'Cliente.id'],
                            ['CelaRepositorioC.idRepositorio', 'Cliente.Logotipo'],
                            ['Cotizaci_on.id', $claves]
                        ])
                        ->value('Ruta');
                    return  $url_logo;

                    #$url_logo  = $ServerNameURL."/".ObtenValor("SELECT Ruta FROM CelaRepositorioC, Cotizaci_on, Cliente WHERE Cotizaci_on.Cliente = Cliente.id AND CelaRepositorioC.idRepositorio = Cliente.Logotipo AND Cotizaci_on.id =".$claves, "Ruta");
                    $url_logo2 = $url_logo;// ""; //$ServerNameURL."/bootstrap/img/logo.png";
                    $mivar     = "SELECT Ruta FROM CelaRepositorioC, Cotizaci_on, Cliente WHERE Cotizaci_on.Cliente = Cliente.id AND CelaRepositorioC.idRepositorio = Cliente.Logotipo AND Cotizaci_on.id =".$claves;

                    $fondo_color     = "#C0C0C0";
                    $texto_color     = "#000";
                    $plantilla       = "1";
                    $redondeo        = "2";
                    $pais            = isset($DomicilioFiscal['pais']) ? $DomicilioFiscal['pais'] : '' ;
                    $estado          = isset($DomicilioFiscal['estado']) ? $DomicilioFiscal['estado'] : '' ;
                    $municipio       = isset($DomicilioFiscal['municipio']) ? $DomicilioFiscal['municipio'] : '' ;
                    $colonia         = isset($DomicilioFiscal['colonia']) ? $DomicilioFiscal['colonia'] : '' ;
                    $calle           = isset($DomicilioFiscal['calle']) ? $DomicilioFiscal['calle'] : '' ;
                    $numexterior     = isset($DomicilioFiscal['noExterior']) ? $DomicilioFiscal['noExterior'] : '' ;
                    $numinterior     = isset($DomicilioFiscal['noInterior']) ? $DomicilioFiscal['noInterior'] : '' ;
                    $cp              = isset($DomicilioFiscal['codigoPostal']) ? $DomicilioFiscal['codigoPostal'] : '' ;
                    $razonsocial     = isset($Emisor['nombre']) ? $Emisor['nombre'] : '' ;
                    $rfc             = isset($Emisor['rfc']) ? $Emisor['rfc'] : '' ;
                    $miregimenFiscal = isset($RegimenFiscal['Regimen']) ? $RegimenFiscal['Regimen'] : '' ;
            
                    //datos de RECEPTOR
                    $Rpais        = isset($ReceptorDomicilio['pais']) ? $ReceptorDomicilio['pais'] : '' ;
                    $Restado      = isset($ReceptorDomicilio['estado']) ? $ReceptorDomicilio['estado'] : '' ;
                    $Rmunicipio   = isset($ReceptorDomicilio['municipio']) ? $ReceptorDomicilio['municipio'] : '' ;
                    $Rlocalidad   = isset($ReceptorDomicilio['localidad']) ? $ReceptorDomicilio['localidad'] : '' ;
                    $Rcolonia     = isset($ReceptorDomicilio['colonia']) ? $ReceptorDomicilio['colonia'] : '' ;
                    $Rcalle       = isset($ReceptorDomicilio['calle']) ? $ReceptorDomicilio['calle'] : '' ;
                    $Rnumexterior = isset($ReceptorDomicilio['noExterior']) ? $ReceptorDomicilio['noExterior'] : '' ;
                    $Rnuminterior = isset($ReceptorDomicilio['noInterior']) ? $ReceptorDomicilio['noInterior'] : '' ;
                    $Rcp          = isset($ReceptorDomicilio['codigoPostal']) ? $ReceptorDomicilio['codigoPostal'] : '' ;
                    $Rrazonsocial = isset($Receptor['nombre']) ? $Receptor['nombre'] : '' ;
                    $Rrfc         = isset($Receptor['rfc']) ? $Receptor['rfc'] : '' ;
                    $referencia   = isset($Receptor['referencia']) ? $Receptor['referencia'] : '' ;
        
                    $subtotal = isset($cfdiComprobante['subTotal']) ? $cfdiComprobante['subTotal']    : '0' ;
                    $total    = isset($cfdiComprobante['total']) ? floatval($cfdiComprobante['total']): '0.0' ;
            
                    $tasaIVA = isset($Traslado['tasa']) ? floatval($Traslado['tasa']) : '0' ;
                    $iva     = isset($Traslado['importe']) ? floatval($Traslado['importe']) : '0' ;
            
                    $subtotal2   = $subtotal."";
                    $ISRretenido = isset($cfdiComprobante['ISRretenido']) ? $cfdiComprobante['ISRretenido'] : '0' ;
                    $IVAretenido = isset($cfdiComprobante['IVAretenido']) ? $cfdiComprobante['IVAretenido'] : '0' ;
            
                    $totalRetenciones = isset($misImpuestos['totalImpuestosRetenidos']) ? $misImpuestos['totalImpuestosRetenidos'] : '0' ;
        
                    //cantidad con letra y total
                    $letras       = utf8_decode( Funciones::num2letras($total, 0, 0) . " pesos  ");
                    $total_cadena = $total;
                    $ultimo       = substr (strrchr ($cfdiComprobante['total'], "."), 1, 2); //recupero lo que este despues del decimal

                    if($ultimo == "") 
                        $ultimo = "00";
            
                    $letras = $letras." ".str_pad($ultimo,  2, "0")."/100 M. N.";
                    $numeroconceros = number_format($total, 6, '.','');
            
                    $selloCFDI      = $cfdiComprobante['sello'];
                    $certificado    = $cfdiComprobante['certificado'];
                    $certificadoCSD = $cfdiComprobante['noCertificado'];
            
                    $selloCFD         = $TimbreFiscalDigital['selloCFD'];
                    $FechaTimbrado    = $TimbreFiscalDigital['FechaTimbrado'];
                    $UUID             = $TimbreFiscalDigital['UUID'];
                    $noCertificadoSAT = $TimbreFiscalDigital['noCertificadoSAT'];
                    $selloSAT         = $TimbreFiscalDigital['selloSAT'];
                    $version          = $TimbreFiscalDigital['version'];
                    
                    $cadenaoriginal = "||".$version."|".$UUID."|".$FechaTimbrado."|".$selloCFD."|".$noCertificadoSAT."||";
        
                    //-----------------------------------------------------------------
                    //Para generar QR
                    //-----------------------------------------------------------------
                    //Nueva Funcion de codificazion de imagenes
                    $rutaQR_code='repositorio/temporal/QR/'.$UUID.'.png';
                    $Direccion="?re=".$rfc."&rr=".$Rrfc."&tt=".$numeroconceros."&id=".$UUID;
                    GeneraQR($Direccion, $rutacompleta.$rutaQR_code, $ServerNameURL."/");
                    $rutatimbradosimg = $ServerNameURL."/".$rutaQR_code;
                    //-----------------------------------------------------------------
                    //Termina para generar QR
                    //-----------------------------------------------------------------
                    $pacquecertifico = "CFDI Timbrado"; //.ObtenValor("SELECT RFC FROM PACS WHERE col1='".$noCertificadoSAT."'", "RFC");
                    //Incluyo el archivo de plantilla
                    // print "tipo cotiza: "."SELECT Tipo FROM Cotizaci_on WHERE id=".$claves;
                    $tipoCotizacion = Cotizacion::where('id', $claves)->value('Tipo');
                    #$tipoCotizacion = ObtenValor("SELECT Tipo FROM Cotizaci_on WHERE id=".$claves, "Tipo");
                    //La siguiente condicion se puso para cuando son servicios de predial
                    if( (isset($DatosExtra['TipoCotizacionPredial']) && $DatosExtra['TipoCotizacionPredial'] == "Servicio") )
                        $tipoCotizacion = 3;
                
                    return $tipoCotizacion . " Caso 1";

                    switch($tipoCotizacion) {
                        case 1:
                            #include_once "formatos/facturas/FormatoNormal.php";
                            break;
                        case 2:
                            #include_once "formatos/facturas/FormatoAgua.php";
                            break;
                        case 3:
                            include_once "formatos/facturas/FormatoPredial.php"; //para predial
                            break;
                        case 4:
                            #include_once "formatos/facturas/FormatoNormal.php"; //para licencia
                            break;
                        case 5:
                            #include_once "formatos/facturas/FormatoNormal.php";// para fertilizante
                            break;
                        case 9:
                            #include_once "formatos/facturas/FormatoAgua.php";// para agua OPD
                            break;
                        case 8:
                            #include_once "formatos/facturas/FormatoAgua.php";// para agua OPD masivo
                            break;
                        case 6:
                            #include_once "formatos/facturas/FormatoNormal.php"; //para actualizaciones y recargos
                            break;
                        case 10:
                            include_once "formatos/facturas/FormatoPredial.php"; //para predial convenios
                            break;
                        case 11:
                            include_once "formatos/facturas/FormatoPredial.php"; //para isai
                            break;
                        default:
                            #include_once "formatos/facturas/FormatoNormal.php";
                        
                    }
                
                }

                if($versionxml=="3.3"){
                    if(array_key_exists("implocal", $ns)){
                        $tieneImplocal = true;
                        $xml->registerXPathNamespace('l', $ns['implocal']);
                        foreach ($xml->xpath('//l:ImpuestosLocales') as $ImpuestosLocales) {}

                        $l=0;
                        foreach ($xml->xpath('//l:ImpuestosLocales//l:RetencionesLocales') as $ImpRetencionesLocales) {
                            $ImpoLocalRetencion['Nombre']  = $ImpRetencionesLocales['ImpLocRetenido'];
                            $ImpoLocalRetencion['Importe'] = $ImpRetencionesLocales['Importe'];
                            $ImpoLocalRetencion['Tasa']    = $ImpRetencionesLocales['TasadeRetencion'];
                            $l++;
                            $esretencion = true;
                        }
            
                        foreach ($xml->xpath('//l:ImpuestosLocales//l:TrasladosLocales') as $ImpTrasladosLocales) {
                            $ImpoLocalTraslado['Nombre']=$ImpTrasladosLocales['ImpLocTrasladado'];
                            $ImpoLocalTraslado['Importe']=$ImpTrasladosLocales['Importe'];
                            $ImpoLocalTraslado['Tasa']=$ImpTrasladosLocales['TasadeRetencion'];
                            $l++;
                            $estraslado=true;
                        }
                    }
                    $Regimen = '';
                    if(array_key_exists("nomina", $ns)){
                        $esnomina = true;
                        $tipo_factura="Recibo de N&oacute;mina";
            
                        $xml->registerXPathNamespace('n', $ns['nomina']);
                        foreach ($xml->xpath('//n:Nomina') as $Nomina) {}
                        foreach ($xml->xpath('//n:Nomina//n:Percepciones') as $Percepciones) {}
                        $j=0;
            
                        foreach ($xml->xpath('//n:Nomina//n:Percepciones//n:Percepcion') as $Percepcion){
                            if(floatval($Percepcion['ImporteExento'])==0.0 AND floatval($Percepcion['ImporteGravado'])==0.0){}
                            else{
                                $perpecpiones[$j]['Percepcion']=$Percepcion['TipoPercepcion'];
                                $perpecpiones[$j]['Clave']=$Percepcion['Clave'];
                                $perpecpiones[$j]['Concepto']=$Percepcion['Concepto'];
                                $perpecpiones[$j]['Gravado']=floatval($Percepcion['ImporteGravado']);
                                $perpecpiones[$j]['Excento']=floatval($Percepcion['ImporteExento']);
                                $j++;
                            }
                        }
            
                        $totalPercepciones=$j;
                        foreach ($xml->xpath('//n:Nomina//n:Deducciones') as $Deducciones) {}
                        $k=0;
                        foreach ($xml->xpath('//n:Nomina//n:Deducciones//n:Deduccion') as $Deduccion){
                            if(floatval($Deduccion['ImporteExento'])==0.0 AND floatval($Deduccion['ImporteGravado'])==0.0){}
                            else{
                                $deducciones[$k]['Deduccion']=$Deduccion['TipoDeduccion'];
                                $deducciones[$k]['Clave']=$Deduccion['Clave'];
                                $deducciones[$k]['Concepto']=$Deduccion['Concepto'];
                                $deducciones[$k]['Gravado']=floatval($Deduccion['ImporteGravado']);
                                $deducciones[$k]['Excento']=floatval($Deduccion['ImporteExento']);
                                $k++;
                            }
                        }
            
                        $totalDeducciones=$k;
            
                        //DATOS DE NOMINA
                        $CURP                   = isset($Nomina['CURP']) ? $Nomina['CURP'] : '' ;
                        $FechaInicioLaboral     = isset($Nomina['FechaInicioRelLaboral']) ? $Nomina['FechaInicioRelLaboral'] : '' ;
                        $TipoJornada            = isset($Nomina['TipoJornada']) ? $Nomina['TipoJornada'] : '' ;
                        $TipoContrato           = isset($Nomina['TipoContrato']) ? $Nomina['TipoContrato'] : '' ;
                        $NoEmpleado             = isset($Nomina['NumEmpleado']) ? $Nomina['NumEmpleado'] : '' ;
                        $NoSegSocial            = isset($Nomina['NoSegSocial']) ? $Nomina['NoSegSocial'] : '' ;
                        $Regimen                = isset($Nomina['TipoRegimen']) ? $Nomina['TipoRegimen'] : '' ;
                        $RiesgoPuesto           = isset($Nomina['RiesgoPuesto']) ? $Nomina['RiesgoPuesto'] : '' ;
                        $Banco                  = isset($Nomina['Banco']) ? $Nomina['Banco'] : '' ;
                        $Clabe                  = isset($Nomina['Clabe']) ? $Nomina['Clabe'] : '' ;
                        $PeriodicidadPago       = isset($Nomina['PeriodicidadPago']) ? $Nomina['PeriodicidadPago'] : '' ;
                        $diasPagados            = isset($Nomina['NumDiasPagados']) ? $Nomina['NumDiasPagados'] : '' ;
                        $fechaPago              = isset($Nomina['FechaPago']) ? $Nomina['FechaPago'] : '' ;
                        $Puesto                 = isset($Nomina['Puesto']) ? $Nomina['Puesto'] : '' ;
                        $Departamento           = isset($Nomina['Departamento']) ? $Nomina['Departamento'] : '' ;
                        $SalarioDiarioIntegrado = isset($Nomina['SalarioDiarioIntegrado']) ? $Nomina['SalarioDiarioIntegrado'] : '' ;
                        $SalarioBaseCotizacion  = isset($Nomina['SalarioBaseCotizacion']) ? $Nomina['SalarioBaseCotizacion'] : '' ;
                        $fechaInicialPago       = isset($Nomina['FechaInicialPago']) ? $Nomina['FechaInicialPago'] : '' ;
                        $fechaFinalPago         = isset($Nomina['FechaFinalPago']) ? $Nomina['FechaFinalPago'] : '' ;
                        $tipocomp               = "nomina";
                    }else{
                        $esnomina=false;
                        $tipocomp="factura";
                    }
        
                    //EMPIEZO A LEER LA INFORMACION DEL CFDI E IMPRIMIRLA
                    foreach ($xml->xpath('//cfdi:Comprobante') as $cfdiComprobante){}
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Emisor') as $Emisor){}
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Emisor//cfdi:ExpedidoEn') as $ExpedidoEn){}
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Receptor') as $Receptor){}
            
                    $i=0;
                    $sumaivaOK=0;
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Conceptos//cfdi:Concepto') as $Concepto){
                        $concepto[$i]['cantidad']      = $Concepto['Cantidad'];
                        $concepto[$i]['unidad']        = CatalogoUnidad::where('Clave', $Concepto['ClaveUnidad'])->value("Nombre");//$Concepto['Unidad'];
                        $concepto[$i]['descripcion']   = str_replace("/*/", "<br />", $Concepto['Descripcion']); 
                        $concepto[$i]['precio']        = $Concepto['ValorUnitario']."";
                        $concepto[$i]['importe']       = $Concepto['Importe']."";
                        $concepto[$i]['ClaveProdServ'] = $Concepto['ClaveProdServ']."";
                        $concepto[$i]['ClaveUnidad']   = $Concepto['ClaveUnidad'];

                        foreach ($Concepto->xpath('cfdi:Impuestos//cfdi:Traslados//cfdi:Traslado') as $ImpConcepto){
                            // print_r($ImpConcepto);
                            $concepto[$i]['IVA']  = $ImpConcepto['Importe']."";
                            $sumaivaOK           += floatval($ImpConcepto['Importe']);
                            $concepto[$i]['Tasa'] = $ImpConcepto['TasaOCuota']."";            
                        }
                        $i++;
                    }

                    $numerodeconceptos = $i;
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Impuestos') as $misImpuestos){}
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Impuestos//cfdi:Traslados//cfdi:Traslado') as $Traslado){}
                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Impuestos//cfdi:Traslados//cfdi:Retencion') as $Retencion){}
                    foreach ($xml->xpath('//t:TimbreFiscalDigital') as $TimbreFiscalDigital) {}
        
                    /*********************************************************************************************/
                    /*********************************************************************************************/
                    /*********************************************************************************************/
                    //-----------------------------------------------------------------
                    //Para los datos de la factura EMISOR, RECEPTOR, CONCEPTOS e IMPUESTOS
                    //-----------------------------------------------------------------
                    //Para estado de la factura  1=vista previa  2= Emitido   3=cancelado
                    $est_factura = '';
        
                    $fecha       = isset($cfdiComprobante['Fecha']) ? substr($cfdiComprobante['Fecha'], 0, 10) : '' ;
                    $hora        = isset($cfdiComprobante['Fecha']) ? substr($cfdiComprobante['Fecha'], 11, 8) : '' ;
                    $folio       = isset($cfdiComprobante['Folio']) ? $cfdiComprobante['Folio'] : '' ;
                    $serie       = isset($cfdiComprobante['Serie']) ? $cfdiComprobante['Serie'] : '' ;
                    $formadepago = isset($cfdiComprobante['FormaPago']) ? $cfdiComprobante['FormaPago'] : '' ;
                    $metodopago  = isset($cfdiComprobante['MetodoPago']) ? $cfdiComprobante['MetodoPago'] : '' ;
                    
                    
                    $tipocomprobante = isset($cfdiComprobante['TipoDeComprobante']) ? $cfdiComprobante['TipoDeComprobante'] : '' ;
                    $tipocomprobante = ($tipocomprobante=="I"?"Ingreso":$tipocomprobante);
                    $numcuentapago   = isset($cfdiComprobante['NumCtaPago']) ? $cfdiComprobante['NumCtaPago'] : '' ;
                    $lugarexpedicion = isset($cfdiComprobante['LugarExpedicion']) ? $cfdiComprobante['LugarExpedicion'] : '' ;
                    $moneda          = isset($cfdiComprobante['Moneda']) ? $cfdiComprobante['Moneda'] : '' ;
                    $cadenaoriginal  = isset($cfdiComprobante['CadenaOriginal']) ? $cfdiComprobante['CadenaOriginal'] : '' ;
                    $descuento       = isset($cfdiComprobante['Descuento']) ? $cfdiComprobante['Descuento'] : '' ;
                
                    $url_logo = CelaRepositorioC::join('Cliente', 'CelaRepositorioC.idRepositorio', 'Cliente.Logotipo')
                        ->join('Cotizaci_on', 'Cotizaci_on.Cliente', 'Cliente.id')
                        ->where('Cotizaci_on.id', $claves)
                        ->value('Ruta');

                    #$url_logo    = $ServerNameURL."/".ObtenValor("SELECT Ruta FROM CelaRepositorioC, Cotizaci_on, Cliente  WHERE Cotizaci_on.Cliente = Cliente.id AND CelaRepositorioC.idRepositorio = Cliente.Logotipo AND Cotizaci_on.id =".$claves, "Ruta");
                    $url_logo2   = $url_logo;// ""; //$ServerNameURL."/bootstrap/img/logo.png";
                    $mivar       = "SELECT Ruta FROM CelaRepositorioC, Cotizaci_on, Cliente WHERE Cotizaci_on.Cliente = Cliente.id AND CelaRepositorioC.idRepositorio = Cliente.Logotipo AND Cotizaci_on.id =".$claves;
                    $fondo_color = "#C0C0C0";
                    $texto_color = "#000";
                    $plantilla   = "1";
                    $redondeo    = "2";
                
                    //datos de EMISOR
                    $razonsocial     = isset($Emisor['Nombre']) ? $Emisor['Nombre'] : '' ;
                    $rfc             = isset($Emisor['Rfc']) ? $Emisor['Rfc'] : '' ;
                    $miregimenFiscal = isset($Emisor['RegimenFiscal']) ? $Emisor['RegimenFiscal'] : '' ;
                
                    $Emisor = Cotizacion::select('d.RFC as rfc', 'd.NombreORaz_onSocial as nombre','d.Pa_is as pais', 
                        'd.EntidadFederativa as estado', 'd.Municipio as municipio', 'd.Colonia as colonia', 'd.Calle as calle', 
                        'd.N_umeroInterior as noInterior','d.N_umeroExterior as noExterior', 'd.C_odigoPostal as codigoPostal')
                        ->join('Cliente as c', 'Cotizaci_on.Cliente', 'c.id')
                        ->join('DatosFiscales as d', 'c.DatosFiscales', 'd.id')
                        ->where('Cotizaci_on.id', $claves)
                        ->first();
            
                    $pais        = isset($Emisor['pais']) ? (is_numeric($Emisor['pais'])? Pais::where('id', $Emisor['pais'])->value('Nombre') :$Emisor['pais']) : '' ;
                    $estado      = isset($Emisor['estado']) ? (is_numeric($Emisor['estado'])? EntidadFederativa::where('id', $Emisor['estado'])->value('Nombre') :$Emisor['estado']) : '' ;
                    $municipio   = isset($Emisor['municipio']) ? (is_numeric($Emisor['municipio'])? Municipio::where('id', $Emisor['municipio'])->value('Nombre') :$Emisor['municipio']) : '' ;
                
                    $colonia     = isset($Emisor['colonia']) ? $Emisor['colonia'] : '' ;
                    $calle       = isset($Emisor['calle']) ? $Emisor['calle'] : '' ;
                    $numexterior = isset($Emisor['noExterior']) ? $Emisor['noExterior'] : '' ;
                    $numinterior = isset($Emisor['noInterior']) ? $Emisor['noInterior'] : '' ;
                    $cp          = isset($Emisor['codigoPostal']) ? $Emisor['codigoPostal'] : '' ;
            
                    //datos de RECEPTOR
                    $Rrazonsocial = isset($Receptor['Nombre']) ? $Receptor['Nombre'] : '' ;
                    $Rrfc         = isset($Receptor['Rfc']) ? $Receptor['Rfc'] : '' ;
                    $UsoCFDI      = isset($Receptor['UsoCFDI']) ? $Receptor['UsoCFDI'] : '' ;
                
                    $Receptor = Cotizacion::select('d.RFC as rfc', 'd.NombreORaz_onSocial as nombre',
                            'd.Pa_is as pais', 'd.EntidadFederativa as estado', 
                            'd.Municipio as municipio', 'd.Referencia as referencia',
                            'd.Colonia as colonia', 'd.Calle as calle', 'd.N_umeroInterior as noInterior',
                            'd.N_umeroExterior as noExterior', 'd.C_odigoPostal as codigoPostal'
                        )
                        ->join('Contribuyente as c', 'Cotizaci_on.Contribuyente', 'c.id')
                        ->join('DatosFiscales as d', 'c.DatosFiscales', 'd.id')
                        ->where('Cotizaci_on.id', $claves)
                        ->first();

                    $Rpais        = isset($Receptor['pais']) ? (is_numeric($Receptor['pais'])? Pais::where('id', $Receptor['pais'])->value('Nombre') :$Receptor['pais']) : '' ;
                    $Restado      = isset($Receptor['estado']) ? (is_numeric($Receptor['estado'])? EntidadFederativa::where('id', $Receptor['estado'])->value('Nombre') :$Receptor['estado']) : '' ;
                    $Rmunicipio   = isset($Receptor['municipio']) ? (is_numeric($Receptor['municipio'])? Municipio::where('id', $Receptor['municipio'])->value('Nombre') :$Receptor['municipio']) : '' ;
                    $Rlocalidad   = isset($Receptor['localidad']) ? $Receptor['localidad'] : '' ;
                    $Rcolonia     = isset($Receptor['colonia']) ? $Receptor['colonia'] : '' ;
                    $Rcalle       = isset($Receptor['calle']) ? $Receptor['calle'] : '' ;
                    $Rnumexterior = isset($Receptor['noExterior']) ? $Receptor['noExterior'] : '' ;
                    $Rnuminterior = isset($Receptor['noInterior']) ? $Receptor['noInterior'] : '' ;
                    $Rcp          = isset($Receptor['codigoPostal']) ? $Receptor['codigoPostal'] : '' ;
                    $referencia   = isset($Receptor['referencia']) ? $Receptor['referencia'] : '' ;
        
                    $subtotal         = isset($cfdiComprobante['SubTotal']) ? $cfdiComprobante['SubTotal'] : '0' ;
                    $total            = isset($cfdiComprobante['Total']) ? floatval($cfdiComprobante['Total']): '0.0' ;
                    $tasaIVA          = isset($Traslado['tasa']) ? floatval($Traslado['tasa']) : '0' ;
                    $iva              = isset($sumaivaOK) ? floatval($sumaivaOK) : '0' ;
                    $subtotal2        = $subtotal."";
                    $ISRretenido      = isset($cfdiComprobante['ISRretenido']) ? $cfdiComprobante['ISRretenido'] : '0' ;
                    $IVAretenido      = isset($cfdiComprobante['IVAretenido']) ? $cfdiComprobante['IVAretenido'] : '0' ;
                    $totalRetenciones = isset($misImpuestos['totalImpuestosRetenidos']) ? $misImpuestos['totalImpuestosRetenidos'] : '0' ;
        
                    //cantidad con letra y total
                    $letras         = utf8_decode(Funciones::num2letras($total, 0, 0) . " pesos ");
                    $total_cadena   = $total;
                    $ultimo         = substr (strrchr ($total_cadena, "."), 1, 2); //recupero lo que este despues del decimal
                    
                    if($ultimo == "")
                        $ultimo="00";

                    $letras         = $letras." ".str_pad($ultimo,  2, "0")."/100 M.N.";
                    $numeroconceros = number_format($total, 6, '.','');
        
                    $selloCFDI      = $cfdiComprobante['Sello'];
                    $certificado    = $cfdiComprobante['Certificado'];
                    $certificadoCSD = $cfdiComprobante['NoCertificado'];
            
                    $selloCFD         = $TimbreFiscalDigital['SelloCFD'];
                    $FechaTimbrado    = $TimbreFiscalDigital['FechaTimbrado'];
                    $UUID             = $TimbreFiscalDigital['UUID'];
                    $noCertificadoSAT = $TimbreFiscalDigital['NoCertificadoSAT'];
                    $selloSAT         = $TimbreFiscalDigital['SelloSAT'];
                    $version          = $TimbreFiscalDigital['Version'];
        
                    $cadenaoriginal = "||".$version."|".$UUID."|".$FechaTimbrado."|".$selloCFD."|".$noCertificadoSAT."||";
                    //-----------------------------------------------------------------
                    //Para generar QR
                    //-----------------------------------------------------------------
                    //Nueva Funcion de codificazion de imagenes
                    $Direccion   = "?re=".$rfc."&rr=".$Rrfc."&tt=".$numeroconceros."&id=".$UUID;
                    $rutaQR_code = 'repositorio/temporal/QR/'.$UUID.'.png';

                    if ( !file_exists('repositorio/QR/') ) {
                        mkdir('repositorio/QR/', 0755, true);
                    }
            
                    if( !file_exists($rutaQR_code) ){
                        QRcode::png($Direccion, $rutaQR_code, 'M' , 4, 2);
                    }
                    #return Funciones::GenerarQR($Direccion, $rutacompleta.$rutaQR_code, $request->root()."/");
                    $rutatimbradosimg = $request->root()."/".$rutaQR_code;
                    #return $rutatimbradosimg;

                    //-----------------------------------------------------------------
                    //Termina para generar QR
                    //-----------------------------------------------------------------
                    $pacquecertifico = "CFDI Timbrado"; //.ObtenValor("SELECT RFC FROM PACS WHERE col1='".$noCertificadoSAT."'", "RFC");
                    //Incluyo el archivo de plantilla
                    // print "tipo cotiza: "."SELECT Tipo FROM Cotizaci_on WHERE id=".$claves;
                    $tipoCotizacion = Cotizacion::where('id', $claves)->value('Tipo');
                    $cliente = Cotizacion::where('id', $claves)->value('Cliente');
                
                    if((isset($DatosExtra['TipoCotizacionPredial']) && $DatosExtra['TipoCotizacionPredial']=="Servicio"))
                        $tipoCotizacion = 3;
                    
                    #return public_path('images') .'<br>'. storage_path('images') .'<br>'. app_path('images') . '<br>'. Storage::url('imagenes/capazlogo.png');
                    #return storage_path();
                    #return Storage::url(env('RESOURCES').'bootstrap.min.css');
                    #return $tipoCotizacion . " Caso 2";
                    #return $Regimen;
                    $CuentaPredial = '';
                    switch($tipoCotizacion) {
                        case 1:
                            #$formato = $cliente == 22?"FormatoNormal2.php":"FormatoNormal.php";
                            #include_once "formatos/facturas33/".$formato;
                            break;
                        case 2:
                            #include_once "formatos/facturas33/FormatoAgua.php";
                            break;
                        case 3:
                            include_once app_path().'/Formatos/Facturas/FormatoPredial.php'; //para predial
                            break;
                        case 4:
                            #include_once "formatos/facturas33/FormatoNormal.php"; //para licencia
                            break;
                        case 5:
                            #include_once "formatos/facturas33/FormatoNormal.php";// para fertilizante
                            break;
                        case 9:
                            #include_once "formatos/facturas33/FormatoAgua.php";// para agua OPD
                            break;
                        case 8:
                            #include_once "formatos/facturas33/FormatoAgua.php";// para agua OPD masivo
                            break;
                        case 6:
                            #include_once "formatos/facturas33/FormatoNormal.php"; //para actualizaciones y recargos
                            break;
                        case 10:
                            include_once app_path().'/Formatos/Facturas/FormatoPredial.php'; //para predial convenios
                            break;
                        case 11:
                            include_once app_path().'/Formatos/Facturas/FormatoPredial.php'; //para isai
                            break;
                        default:
                            #include_once app_path().'/formatos/facturas33/FormatoNormal.php';   
                    }
                }
        
                //echo $Direccion;
                $htmlGlobal .= $mihtml.'<!DOCTYPE html>
                <html>
                    <head>
                        <meta charset="utf-8">	
                        <style>
                            body{font-size: 12px;}				
                                .main_container{ padding-top:15px; padding-left:5px; z-index: 99; background-size: cover; width:970px; height:950px; position:relative;}
                                .break{	display: block; clear: both; page-break-after: always; }
                                .titulo{ font-size:28px; }
                                .emisor{ font-size:24px; display:block; margin: 0 0 5px 0;	}
                                .centrado {	text-align: center; clear: both; 
                            }
                        </style>
                    </head>
                    <body>			
                        <div style="display: inline-block; word-break: break-word;" class="main_container break ">
                            <div class="emisor centrado titulo">'.$razonsocial.'</div>'
                            .htmlentities($clave).htmlentities($Addenda).'
                        </div>
                    </body>
                </html>';
                
            }//termina for de XML

        } //termina el if(elxml != "")
        else{
            $mifolio            = "";
            $datsCotizacion     = Cotizacion::find($claves);
            $tipo_factura       = "Factura Electr&oacute;nica";
            $ejecutaObtieneXML  = XMLIngreso::where('idCotizaci_on', $claves)->value('xml');
            $htmlGlobal         = '';
            $DatosXML           = XMLIngreso::where('idCotizaci_on', $claves)->first();
            
            $string             = preg_replace("/[\r\n|\n|\r]+/", " ", $DatosXML['DatosExtra']);
            $DatosExtra         = json_decode($string, true);
            
            $Leyenda            = isset($DatosExtra['Leyenda']) ? $DatosExtra['Leyenda'] : '';
            $UserElab           = isset($DatosExtra['Usuario']) ? $DatosExtra['Usuario'] : '';
            $Observaciones      = isset($DatosExtra['Observacion']) ? $DatosExtra['Observacion'] : '';
            $ContratoVigente    = isset($DatosExtra['ContratoVigente'] ) ? $DatosExtra['ContratoVigente'] : '';
            $ContratoAnterior   = isset($DatosExtra['ContratoAnterior'] ) ? $DatosExtra['ContratoAnterior'] : '';
            $Medidor            = isset($DatosExtra['Medidor']) ? $DatosExtra['Medidor'] : '' ;
            $Domicilio          = isset($DatosExtra['Domicilio'] ) ? $DatosExtra['Domicilio'] : '';
            $Municipio          = isset($DatosExtra['Municipio'] ) ? $DatosExtra['Municipio'] : '';
            $Ruta               = isset($DatosExtra['Ruta'] ) ? $DatosExtra['Ruta'] : '';
            $TipoToma           = isset($DatosExtra['TipoToma'] ) ? $DatosExtra['TipoToma'] : '';
            $M_etodoCobro       = isset($DatosExtra['M_etodoCobro'] ) ? $DatosExtra['M_etodoCobro'] : '';
            $CuotaFija          = isset($DatosExtra['CuotaFija'] ) ? $DatosExtra['CuotaFija'] : '';
            $numcuentapago      = isset($DatosExtra['NumCuenta'] ) ? $DatosExtra['NumCuenta'] : '';

            if($datsCotizacion->Tipo==10 || $datsCotizacion->Tipo==3 ||(isset($DatosExtra['TipoCotizacionPredial']) && $DatosExtra['TipoCotizacionPredial']=="Servicio" )){
                                             
                $Ubicacion = PadronCatastral::selectRaw("CONCAT (COALESCE (Ubicaci_on,''),' ',COALESCE (Colonia,'')) AS Ubicaci_on")
                    ->where('id', $datsCotizacion->Padr_on)
                    ->value('Ubicaci_on');
                    
                if($Ubicacion==" "){
                    $Ubicacion="No disponible";
                }
                
                if( !array_key_exists( 'A_noPago', $DatosExtra ) || $DatosExtra['A_noPago'] == null ){
                    $DatosExtra['A_noPago'] = $ejercicio;
                }

                $A_noPago = $DatosExtra['A_noPago'];

                $ValorcatastralDB = Cotizacion::from('Cotizaci_on as co1')
                    ->select('te.Importe as ImportePredioValor')
                    ->join('Padr_onCatastral as po', 'co1.Padr_on', 'po.id')
                    ->join('TipoPredioValores as t', 'po.TipoPredioValores', 't.id')
                    ->join('TipoPredioValoresEjercicioFiscal as te', function ($join) use ($A_noPago){
                            $join->on('te.idTipoPredioValores', 't.id');
                            $join->on('te.EjercicioFiscal', DB::raw( $A_noPago ) );
                        })
                    ->where('co1.id', $claves)
                    ->value('ImportePredioValor');

                $ValorConstruccionDB =  ConceptoAdicionalCotizacion::from('ConceptoAdicionalesCotizaci_on as co')
                    ->select('to1.Importe as ImporteConstruccionValor')
                    ->join('Cotizaci_on as co1', 'co.Cotizaci_on', 'co1.id')
                    ->join('Padr_onCatastral as po', 'co.Padr_on', 'po.id')
                    ->join('TipoConstrucci_onValores as to1', 'po.TipoConstrucci_onValores', 'to1.id')
                    ->where([
                        ['co1.id', $claves],
                        ['co.TipoContribuci_on', 1]
                    ])
                    ->value('ImporteConstruccionValor');

                $datosHistorial = Cotizacion::from('Cotizaci_on as co1')
                    ->join('Padr_onCatastral as po', 'co1.Padr_on', 'po.id')
                    ->join('Padr_onCatastralHistorial as pch', function ($join) use ($A_noPago){
                        $join->on('pch.Padr_onCatastral', 'po.id');
                        $join->on('pch.A_no', DB::raw( $A_noPago ) );
                    })
                    ->where('co1.id', $claves)
                    ->first();

                $ValorConstruccionDB = $datosHistorial->ConstruccionCosto;

                $DatosConstruccion = PadronCatastral::selectRaw("sum(pd.SuperficieConstrucci_on) as Cons")
                    ->join('Padr_onConstruccionDetalle as pd', 'pd.idPadron', 'Padr_onCatastral.id')
                    ->where('Padr_onCatastral.id', $datosHistorial->Padr_onCatastral)
                    ->value('Cons');
                
                $CuentaVigente  		= isset($DatosExtra['Cuenta'] ) ? $DatosExtra['Cuenta'] : '';
                $CuentaAnterior 		= isset($DatosExtra['CuentaAnterior'] ) ? $DatosExtra['CuentaAnterior'] : '';
                $ValorCatastral         = isset($DatosExtra['ValorCatastral'] ) ? $DatosExtra['ValorCatastral'] : ''; 
                $a_noPago 				= isset($DatosExtra['A_noPago'] ) ? $DatosExtra['A_noPago'] : '';
                
                $SuperficieConstruccion = ($DatosConstruccion!="NULL" ) ? number_format(floatval( str_replace(",","",$DatosConstruccion) ),2) : '0.00';
                $ValorConstruccion		= isset($ValorConstruccionDB ) ? number_format($ValorConstruccionDB,2) : '0.00';
                $CuentaPredial          = $CuentaAnterior;
                $SuperficieTerreno		= isset($DatosExtra['SuperficieTerreno'] ) ? number_format(str_replace(",","",$DatosExtra['SuperficieTerreno']),2) : '';
                $ValorTerreno			= isset($ValorcatastralDB ) ? number_format($ValorcatastralDB,2) : '0.00';
                 
                if($DatosXML['Contribuyente']!="")
                    $Propietario = Contribuyente::from('Contribuyente as c')
                        ->selectRaw("COALESCE ((CONCAT(c.id,' - ',c.NombreComercial,' - ') ), (CONCAT(c.id,' - ',c.Nombres,' ',c.ApellidoPaterno,' ', c.ApellidoMaterno) ) ) AS NombreORaz_onSocial")
                        ->join('DatosFiscales as d', 'c.DatosFiscales', 'd.id')
                        ->where('c.id', $DatosXML->Contribuyente)
                        ->value('NombreORaz_onSocial');
                else
                    $Propietario = "No disponible";
            }
            
            $datsCotizacion   = Cotizacion::find($claves);
            $tipo_factura     = "Factura Electr&oacute;nica";
            $DatosXML         = XMLIngreso::where('idCotizaci_on', $claves)->first();
            $string           = preg_replace("/[\r\n|\n|\r]+/", " ", $DatosXML['DatosExtra']);
            $DatosExtra       = json_decode($string, true);
            $Leyenda          = isset($DatosExtra['Leyenda']) ? $DatosExtra['Leyenda'] : '';
            $UserElab         = isset($DatosExtra['Usuario']) ? $DatosExtra['Usuario'] : '';
            $Observaciones    = isset($DatosExtra['Observacion']) ? $DatosExtra['Observacion'] : '';
            $ContratoVigente  = isset($DatosExtra['ContratoVigente'] ) ? $DatosExtra['ContratoVigente'] : '';
            $ContratoAnterior = isset($DatosExtra['ContratoAnterior'] ) ? $DatosExtra['ContratoAnterior'] : '';
            $Medidor          = isset($DatosExtra['Medidor']) ? $DatosExtra['Medidor'] : '' ;
            $Domicilio        = isset($DatosExtra['Domicilio'] ) ? $DatosExtra['Domicilio'] : '';
            $Municipio        = isset($DatosExtra['Municipio'] ) ? $DatosExtra['Municipio'] : '';
            $Ruta             = isset($DatosExtra['Ruta'] ) ? $DatosExtra['Ruta'] : '';
            $TipoToma         = isset($DatosExtra['TipoToma'] ) ? $DatosExtra['TipoToma'] : '';
            $M_etodoCobro     = isset($DatosExtra['M_etodoCobro'] ) ? $DatosExtra['M_etodoCobro'] : '';
            $CuotaFija        = isset($DatosExtra['CuotaFija'] ) ? $DatosExtra['CuotaFija'] : '';
            $descuento        = isset($DatosExtra['Descuento'] ) ? $DatosExtra['Descuento'] : '0';
        
            /****************************************************************************************************/
            
            $ejecutaConceptos = ConceptoAdicionalCotizacion::from('ConceptoAdicionalesCotizaci_on as co')
                ->select('co.id', 'co.TipoContribuci_on as TipoContribuci_on', 'co.Importe as Importe',
                    'co.Estatus', 'co.MomentoCotizaci_on', 'co.MomentoPago', 'co.FechaPago', 'co.CajaDeCobro', 'co.Xml',
                    'co.TipoCobroCaja', 'co.N_umeroDeOperaci_on', 'co.CuentaBancaria', 'co.Adicional', 'co.Observacion',
                    'co.Cantidad as Cantidad', 'co.Padre', 'co.Mes', 'co.A_no', 'co.Mes', 'co.Origen', 'co.TipoBase',
                    'co.MontoBase as MontoBase', 'co.Padr_on', 'c.id', 'c.Descripci_on as ConceptoDescripcion',
                    DB::raw("(SELECT Descripci_on FROM RetencionesAdicionales WHERE RetencionesAdicionales.id=co.Adicional ) as DescripcionAdicional"),
                    DB::raw("(SELECT Tipo FROM Cotizaci_on WHERE Cotizaci_on.id=co.Cotizaci_on) tipoCotizacion")
                )
                ->join('ConceptoCobroCaja as c', 'co.ConceptoAdicionales', 'c.id')
                ->where('co.Cotizaci_on', $claves)
                ->whereNull('co.Padre')
                ->orderByDesc( DB::raw('CONCAT(co.A_no, co.Mes)') )
                ->get();

            $sumaconceptos = $i = 0;

            foreach($ejecutaConceptos as $filaConcepto){
                if($filaConcepto->TipoContribuci_on==1){
                    $concepto[$i]['cantidad']    = $filaConcepto->Cantidad;
                    $concepto[$i]['unidad']      = "No aplica";
                    $concepto[$i]['descripcion'] = $filaConcepto->ConceptoDescripcion.(!is_null($filaConcepto->Observacion)?" <br /> ".$filaConcepto->Observacion: '');

                    if($filaConcepto->tipoCotizacion==3 || $filaConcepto->tipoCotizacion==10){
                        //Solo Predial
                        $concepto[$i]['descripcion']=($filaConcepto->ConceptoDescripcion." correspondiente al Bimestre ".$filaConcepto->Mes." del ".$filaConcepto->A_no);
                    }
                    if( $filaConcepto->tipoCotizacion==9 )
                        //Solo Agua
                        $concepto[$i]['descripcion']=($filaConcepto->ConceptoDescripcion." correspondiente al Mes ".$meses[intval($filaConcepto->Mes)-1]." del ".$filaConcepto->A_no);
                    
                    if( $filaConcepto->tipoCotizacion==11 )
                        $concepto[$i]['descripcion']=($filaConcepto->ConceptoDescripcion);
                    
                    $concepto[$i]['precio']  = $filaConcepto->Importe/$filaConcepto->Cantidad;
                    $concepto[$i]['importe'] = $filaConcepto->Importe;
                    $sumaconceptos          += $concepto[$i]['importe'];

                }else if($filaConcepto->TipoContribuci_on==2){
                    $concepto[$i]['cantidad']    = $filaConcepto->Cantidad;
                    $concepto[$i]['unidad']      = "No aplica";
                    $concepto[$i]['descripcion'] = $filaConcepto->DescripcionAdicional;
                    $concepto[$i]['precio']      = $filaConcepto->Importe/$filaConcepto->Cantidad;
                    $concepto[$i]['importe']     = $filaConcepto->Importe;
                    $sumaconceptos              += $concepto[$i]['importe'];
                }
                $i++;
            }
            $esnomina = false;
            $tipocomp = "factura";

            $numerodeconceptos=$i;
            //Para estado de la factura  1=vista previa  2= Emitido   3=cancelado
            $est_factura = '';
            $cfdiComprobante = XMLIngreso::select('FechaTimbrado as fecha',
                    DB::raw("SUBSTR(Folio,1,16) as serie"), DB::raw("SUBSTR(Folio, -8) as folio"),
                    'MetodoDePago', 'DatosExtra'
                )
                ->where('idCotizaci_on', $claves)
                ->first();
                
            $fecha           = isset($cfdiComprobante['fecha'])  ? substr($cfdiComprobante['fecha'], 0, 10) : '' ;
            $hora            = isset($cfdiComprobante['fecha']) ? substr($cfdiComprobante['fecha'], 11, 8) : '' ;
            $folio           = isset($cfdiComprobante['folio']) ? $cfdiComprobante['folio'] : '' ;
            $serie           = isset($cfdiComprobante['serie']) ? $cfdiComprobante['serie'] : '' ;
            $formadepago     = isset($cfdiComprobante['formaDePago']) ? $cfdiComprobante['formaDePago'] : 'No disponible' ;
            $metodopago      = isset($cfdiComprobante['metodoDePago']) ? $cfdiComprobante['metodoDePago'] : '' ;
            $tipocomprobante = isset($cfdiComprobante['tipoDeComprobante']) ? $cfdiComprobante['tipoDeComprobante'] : '' ;
            $numcuentapago   = isset($cfdiComprobante['NumCtaPago']) ? $cfdiComprobante['NumCtaPago'] : '' ;
            $lugarexpedicion = isset($cfdiComprobante['LugarExpedicion']) ? $cfdiComprobante['LugarExpedicion'] : '' ;
            $moneda          = isset($cfdiComprobante['Moneda']) ? $cfdiComprobante['Moneda'] : '' ;
            $cadenaoriginal  = isset($cfdiComprobante['CadenaOriginal']) ? $cfdiComprobante['CadenaOriginal'] : '' ;

            $Emisor = Cotizacion::select('d.RFC as rfc', 'd.NombreORaz_onSocial as nombre','d.Pa_is as pais', 
                    'd.EntidadFederativa as estado', 'd.Municipio as municipio', 'd.Colonia as colonia', 'd.Calle as calle', 
                    'd.N_umeroInterior as noInterior','d.N_umeroExterior as noExterior', 'd.C_odigoPostal as codigoPostal'
                )
                ->join('Cliente as c', 'Cotizaci_on.Cliente', 'c.id')
                ->join('DatosFiscales as d', 'c.DatosFiscales', 'd.id')
                ->where('Cotizaci_on.id', $claves)
                ->first();

            $url_logo = CelaRepositorioC::join('Cliente', 'CelaRepositorioC.idRepositorio', 'Cliente.Logotipo')
                ->join('Cotizaci_on', 'Cotizaci_on.Cliente', 'Cliente.id')
                ->where('Cotizaci_on.id', $claves)
                ->value('Ruta');
            #$url_logo = $ServerNameURL."/".ObtenValor("SELECT Ruta FROM CelaRepositorioC, Cotizaci_on, Cliente WHERE Cotizaci_on.Cliente = Cliente.id AND CelaRepositorioC.idRepositorio = Cliente.Logotipo AND Cotizaci_on.id =".$claves, "Ruta");

            $url_logo2   = $url_logo;
            $fondo_color = "#C0C0C0";
            $texto_color = "#000";
            $plantilla   = "1";
            $redondeo    = "2";
            $pais        = isset($Emisor['pais']) ? (is_numeric($Emisor['pais'])? Pais::where('id', $Emisor['pais'])->value('Nombre') :$Emisor['pais']) : '' ;
            $estado      = isset($Emisor['estado']) ? (is_numeric($Emisor['estado'])? EntidadFederativa::where('id', $Emisor['estado'])->value('Nombre') :$Emisor['estado']) : '' ;
            $municipio   = isset($Emisor['municipio']) ? (is_numeric($Emisor['municipio'])? Municipio::where('id', $Emisor['municipio'])->value('Nombre') :$Emisor['municipio']) : '' ;

            $colonia         = isset($Emisor['colonia']) ? $Emisor['colonia'] : '' ;
            $calle           = isset($Emisor['calle']) ? $Emisor['calle'] : '' ;
            $numexterior     = isset($Emisor['noExterior']) ? $Emisor['noExterior'] : '' ;
            $numinterior     = isset($Emisor['noInterior']) ? $Emisor['noInterior'] : '' ;
            $cp              = isset($Emisor['codigoPostal']) ? $Emisor['codigoPostal'] : '' ;
            $razonsocial     = isset($Emisor['nombre']) ? $Emisor['nombre'] : '' ;
            $rfc             = isset($Emisor['rfc']) ? $Emisor['rfc'] : '' ;
            $miregimenFiscal = isset($Emisor['RegimenFiscal']) ? $Emisor['RegimenFiscal'] : '' ;

            //datos de RECEPTOR
            $Receptor = Cotizacion::select('d.RFC as rfc', 'd.NombreORaz_onSocial as nombre',
                    'd.Pa_is as pais', 'd.EntidadFederativa as estado', 
                    'd.Municipio as municipio', 'd.Referencia as referencia',
                    'd.Colonia as colonia', 'd.Calle as calle', 'd.N_umeroInterior as noInterior',
                    'd.N_umeroExterior as noExterior', 'd.C_odigoPostal as codigoPostal'
                )
                ->join('Contribuyente as c', 'Cotizaci_on.Contribuyente', 'c.id')
                ->join('DatosFiscales as d', 'c.DatosFiscales', 'd.id')
                ->where('Cotizaci_on.id', $claves)
                ->first();

            $Rpais        = isset($Receptor['pais']) ? (is_numeric($Receptor['pais'])? Pais::where('id', $Receptor['pais'])->value('Nombre') :$Receptor['pais']) : '' ;
            $Restado      = isset($Receptor['estado']) ? (is_numeric($Receptor['estado'])? EntidadFederativa::where('id', $Receptor['estado'])->value('Nombre') :$Receptor['estado']) : '' ;
            $Rmunicipio   = isset($Receptor['municipio']) ? (is_numeric($Receptor['municipio'])? Municipio::where('id', $Receptor['municipio'])->value('Nombre') :$Receptor['municipio']) : '' ;
            $Rlocalidad   = isset($Receptor['localidad']) ? $Receptor['localidad'] : '' ;
            $Rcolonia     = isset($Receptor['colonia']) ? $Receptor['colonia'] : '' ;
            $Rcalle       = isset($Receptor['calle']) ? $Receptor['calle'] : '' ;
            $Rnumexterior = isset($Receptor['noExterior']) ? $Receptor['noExterior'] : '' ;
            $Rnuminterior = isset($Receptor['noInterior']) ? $Receptor['noInterior'] : '' ;
            $Rcp          = isset($Receptor['codigoPostal']) ? $Receptor['codigoPostal'] : '' ;
            $Rrazonsocial = isset($Receptor['nombre']) ? $Receptor['nombre'] : '' ;
            $Rrfc         = isset($Receptor['rfc']) ? $Receptor['rfc'] : '' ;
            $referencia   = isset($Receptor['referencia']) ? $Receptor['referencia'] : '' ;

            $subtotal         = $sumaconceptos ;
            $total            = $sumaconceptos-$descuento ;
            $tasaIVA          = 0; ;
            $iva              = 0 ;
            $subtotal2        = $subtotal."";
            $ISRretenido      = isset($cfdiComprobante['ISRretenido']) ? $cfdiComprobante['ISRretenido'] : '0' ;
            $IVAretenido      = isset($cfdiComprobante['IVAretenido']) ? $cfdiComprobante['IVAretenido'] : '0' ;
            $totalRetenciones = isset($misImpuestos['totalImpuestosRetenidos']) ? $misImpuestos['totalImpuestosRetenidos'] : '0' ;

            //cantidad con letra y total
            $letras       = utf8_decode(Funciones::num2letras($total,0,0)." pesos  ");
            $total_cadena = $total;
            $ultimo       = substr (strrchr ($total, "."), 1, 2); //recupero lo que este despues del decimal
            
            if($ultimo=="")
                $ultimo="00";

            $letras         = $letras." ".str_pad($ultimo,  2, "0")."/100 M.N.";
            $numeroconceros = number_format($total, 6, '.','');

            $selloCFDI        = "No disponible";
            $certificado      = "No disponible";
            $certificadoCSD   = "No disponible";
            $selloCFD         = "No disponible";
            $FechaTimbrado    = "No disponible";
            $UUID             = "No disponible";
            $noCertificadoSAT = "No disponible";
            $selloSAT         = "No disponible";
            $version          = "No disponible";
            $cadenaoriginal   = "No disponible";

            //-----------------------------------------------------------------
            //Para generar QR
            //-----------------------------------------------------------------
            $rutaQR_code='repositorio/QR/'.$UUID.'.png';
            //Contenido del QR
            $Direccion="?re=".$rfc."&rr=".$Rrfc."&tt=".$numeroconceros."&id=".$UUID;
            if ( !file_exists('repositorio/QR/') ) {
                mkdir('repositorio/QR/', 0755, true);
            }
    
            if( !file_exists($rutaQR_code) ){
                QRcode::png($Direccion, $rutaQR_code, 'M' , 4, 2);
            }

            $rutatimbradosimg = $request->root()."/".$rutaQR_code;
            //-----------------------------------------------------------------
            //Termina para generar QR
            //-----------------------------------------------------------------
            $pacquecertifico = "CFDI Timbrado"; //.ObtenValor("SELECT RFC FROM PACS WHERE col1='".$noCertificadoSAT."'", "RFC");
            //Incluyo el archivo de plantilla
            $tipoCotizacion = Cotizacion::where('id', $claves)->value('Tipo');

            if((isset($DatosExtra['TipoCotizacionPredial']) && $DatosExtra['TipoCotizacionPredial']=="Servicio"))
                $tipoCotizacion = 3;

            #return $tipoCotizacion. " Caso 3";

            switch($tipoCotizacion) {
                case 1:
                    #include_once "formatos/facturas/FormatoNormal.php";
                    break;
                case 2:
                    #include_once "formatos/facturas/FormatoAgua.php";
                    break;
                case 3:
                    include_once app_path().'/Formatos/Facturas/FormatoPredial.php'; //para predial
                    break;
                case 5:
                    #include_once "formatos/facturas/FormatoNormal.php";
                    break;
                case 9:
                    #include_once "formatos/facturas/FormatoAgua.php";// para agua OPD
                    break;
                case 8:
                    #include_once "formatos/facturas/FormatoAgua.php";// para agua OPD masivo
                    break;
                case 6:
                    #include_once "formatos/facturas/FormatoNormal.php"; //para actualizaciones y recargos
                    break;
                case 10:
                    include_once app_path().'/Formatos/Facturas/FormatoPredial.php'; //para predial convenios
                    break;
                case 11:
                    include_once app_path().'/Formatos/Facturas/FormatoPredial.php'; //para isai
                    break;
                default:
                    #include_once "formatos/facturas/FormatoNormal.php";
                    //break;
            }
            /****************************************************************************************************/
            $htmlGlobal = $mihtml;
        }

        try{
            $archivosalida = "Factura_Electronica".$claves.rand(10,99)."".uniqid().".pdf";
            $wkhtmltopdf   = new Wkhtmltopdf(array('path' =>$rutacompleta.'repositorio/temporal/', 'lowquality'=>true, 'FooterStyleLeft'=>'Grupo Piacza - SUINPAC Contable', 'FooterStyleRight'=>'Poliza No: '.$N_umeroP_oliza, 'FooterStyleCenter'=>'Pag. [page] de [toPage]'));
            $wkhtmltopdf->setHtml($htmlGlobal);
            $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE,$archivosalida);
            return response()->json([
                'success' => '1',
                'ruta'=> $rutacompleta."repositorio/temporal/".$archivosalida,
                'idTicket'=> $idTicket
            ], 200);
            
    
        }catch (Exception $e) {
            return response()->json([
                'success' => '0'
            ], 200);
           return "Hubo un error al generar el PDF: ".$e->getMessage();
        }
    }
}
