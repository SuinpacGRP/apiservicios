<?php

namespace App\Http\Controllers;

use File;
use Response;

use App\Libs\QRcode;
use App\Libs\LibNubeS3;
use App\Libs\Wkhtmltopdf;

use App\Funciones;
use App\FuncionesFirma;

use App\Modelos\XML;
use App\Modelos\Pais;
use App\Modelos\Cliente;
use App\Modelos\Municipio;
use App\Modelos\Cotizacion;
use App\Modelos\XMLIngreso;
use App\Modelos\Contribuyente;
use App\Modelos\TipoCobroCaja;
use App\Modelos\CatalogoUnidad;
use App\Modelos\PadronCatastral;
use App\Modelos\CelaRepositorio;
use App\Modelos\CelaRepositorioC;
use App\Modelos\EntidadFederativa;
use App\Modelos\EncabezadoContabilidad;
use App\Modelos\ConceptoAdicionalCotizacion;
use App\ModelosNotarios\TramitesISAINotarios;
use App\Modelos\PadronCatastralTramitesISAINotarios;
use App\Modelos\PadronCatastralTramitesISAINotariosDocumentos;

use App\Http\Controllers\PortalNotarios\ReporteForma3DCC;

use App\Http\Controllers\PortalPago\CotizacionServiciosPredialController;
use App\Http\Controllers\PortalNotarios\PortalNotariosController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;

class ExtrasController extends Controller{
    /**
     * ! Se asigna el middleware  al costructor para validar los tokens
    */
    public function __construct(){
        #$this->middleware( 'jwt', ['except' => ['getToken']] );
    }

    public  static function listadoServicios(Request $request){
        $cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $ano=date("Y");
        $condicion;
        if( $cliente==31 & $tipoServicio==3){
            //predial catastro
            $condicion=144;
        }else if($cliente==31  & $tipoServicio==9){
            //agua
            $condicion= 884;
        }else{
            $condicion="291, 144,818,884,409";
        }

        Funciones::selecionarBase($cliente);
        $servicios=DB::select("SELECT DISTINCT
	cd.id AS IdCatalogoDocumentos,
	cd.Nombre,
	cd.RequierePago,
	c3.id AS IdConceptoCobroCaja
    FROM
        ConceptoAdicionales c
        INNER JOIN ConceptoRetencionesAdicionalesCliente c1 ON ( c.ConceptoRetencionesAdicionalesCliente = c1.id )
        INNER JOIN ConceptoRetencionesAdicionales c2 ON ( c1.ConceptoRetencionesAdicionales = c2.id )
        INNER JOIN ConceptoCobroCaja c3 ON ( c2.Concepto = c3.id )
        INNER JOIN CatalogoDocumentos cd ON ( cd.id = c3.CatalogoDocumento )
    WHERE
        c.Cliente =".$cliente."
        AND c1.Cliente =".$cliente."
        AND c.EjercicioFiscal =". $ano ."
        AND c.STATUS = 1
        AND c1.PagoOnLine=1
        and c3.EstatusVisible=1
        AND c.AreaRecaudadora IN (".$condicion.")
        AND c.AreaRecaudadora != 49
    GROUP BY
        `IdCatalogoDocumentos`
    ORDER BY
        cd.Nombre ASC");
        // ( IF( c1.Cliente = 29, (c3.id IN (3862,4328) ), (c3.id IN(152,697) ) ))
        return response()->json([
            'success' => '1',
            'servicios' => $servicios,
        ]);
    }

    public function getNombreCliente(Request $request){

        $cliente=$request->Cliente;

        $ClienteRelacion="";
        $EsMunicipio="";
        $Cliente = \App\Cliente::select('id','Descripci_on')
            ->where('nombre',$cliente)
            ->first();

        $Cliente = Cliente::select('id','Descripci_on')
            ->where('id',$cliente)
            ->first();

        //return $cliente;
        return response()->json([
            'success' => '1',
            'cliente'=> $Cliente,
            'clienteRelacion'=>$ClienteRelacion,
            'esMunicipio'=>$EsMunicipio
        ], 200);
    }

    public function getEstatusCliente(Request $request){

        $cliente=$request->Cliente;

        $ClienteRelacion="";
        $EsMunicipio="";
        $Cliente = \App\Cliente::select('id','Descripci_on')
            ->where('nombre',$cliente)
            ->first();

        $Cliente = Cliente::select('id','Estatus')
            ->where('id',$cliente)
            ->first();

        //return $cliente;
        return response()->json([
            'success' => '1',
            'cliente'=> $Cliente,
        ], 200);
    }
    public function getServiciosCliente(Request $request){
        $cliente=$request->Cliente;
        
        $sqlConsulta="SELECT c.idCliente AS Cliente, c.id_servicios AS idServicio,d.tiposervicio, c.Estatus,c.EstatusMantenimiento,c.urlAlternativa FROM suinpac_general.ClientesServiciosEnLinea c
        INNER JOIN suinpac_general.CatalogoServiciosEnLineaC d ON (c.id_servicios=d.id) and c.idCliente=".$cliente;
            $respuesta=DB::select($sqlConsulta);
        return response()->json([
            'success' => '1',
            'servicios'=> $respuesta,
        ], 200);
    }
    public function getEstatusServiciosCliente(Request $request){
        $cliente=$request->Cliente;
        $idServicio=$request->IdServicio;
        $sqlConsulta="SELECT c.idCliente AS Cliente, c.id_servicios AS idServicio,d.tiposervicio, c.Estatus,c.EstatusMantenimiento,c.urlAlternativa FROM suinpac_general.ClientesServiciosEnLinea c INNER JOIN suinpac_general.CatalogoServiciosEnLineaC d ON (c.id_servicios=d.id) and c.idCliente=".$cliente." AND c.id_servicios=".$idServicio;
            $respuesta=DB::select($sqlConsulta);
        return response()->json([
            'success' => '1',
            'servicios'=> $respuesta,
        ], 200);
    }

    public function getImagen(Request $request)
    {
        $cliente = $request->Cliente;
        Funciones::selecionarBase($cliente);

        $ClienteImagen = \App\Cliente::select(
            'cr.Ruta as Logotipo','Cliente.Descripci_on as nombre')
            ->join('CelaRepositorioC AS cr',  'cr.idRepositorio', '=', 'Cliente.Logotipo')
            ->where('Cliente.id', $cliente)
            ->first();

        return response()->json([
            'success' => '1',
            'url'=>$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".$ClienteImagen->Logotipo,
            'cliente'=>$ClienteImagen->Logotipo,
            'clienteNombre'=>$ClienteImagen->nombre

        ], 200);

    }


    public function getImagenCopia(Request $request)
    {
        $cliente = $request->Cliente;
        Funciones::selecionarBase($cliente);

        $ClienteImagen = \App\Cliente::select(
            'cr.Ruta as Logotipo','Cliente.Descripci_on as nombre')
            ->join('CelaRepositorioC AS cr',  'cr.idRepositorio', '=', 'Cliente.Logotipo')
            ->where('Cliente.id', $cliente)
            ->first();

        $response=json([
            'success' => '1',
            'url'=>$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".$ClienteImagen->Logotipo,
            'cliente'=>$ClienteImagen->Logotipo,
            'clienteNombre'=>$ClienteImagen->nombre

        ], 200);

        $result = Funciones::respondWithToken($response);

        return $result;

    }

    public function getLogos(Request $request)
    {
        $cliente = intval($request->Cliente);
        Funciones::selecionarBase($cliente);
        $ClienteImagen=Funciones::ObtenValor("SELECT c.Descripci_on AS nombre,
        (SELECT CONCAT_WS('','https://api.servicioenlinea.mx/',cr.Ruta) FROM CelaRepositorioC cr WHERE cr.idRepositorio=c.LogotipoOficial) AS LogoOficial,
        (SELECT CONCAT_WS('','https://api.servicioenlinea.mx/',cr.Ruta) FROM CelaRepositorioC cr WHERE cr.idRepositorio=c.Logotipo) AS LogoAdministracion
        FROM Cliente c 
        WHERE c.id=".$cliente);

        if ($ClienteImagen->LogoOficial!='') {
            error_log("Fecha: ". date("Y-m-d H:i:s") . " Se accede a la funcion de getLogos 'success' => '1', 'cliente' => $cliente \n" , 3, "/var/log/suinpac/LogCajero.log");
            return response()->json([
                'success' => '1',
                'urlOficial' => $ClienteImagen->LogoOficial,
                'urlAdministracion' => $ClienteImagen->LogoAdministracion,
                'clienteNombre' => $ClienteImagen->nombre
            ], 200);
        } else {
            error_log("Fecha: ". date("Y-m-d H:i:s") . " Se accede a la funcion de getLogos 'success' => '2', 'cliente' => $cliente \n" , 3, "/var/log/suinpac/LogCajero.log");
            return response()->json([
                'success' => '2',
                'error' => $ClienteImagen->nombre
            ], 200);
        }
    }

    public function getImagenes(Request $request)
    {
        $cliente = $request->Cliente;
        Funciones::selecionarBase($cliente);

        $ClienteImagen = \App\Cliente::select(
            'cr.Ruta as Logotipo', 'Cliente.Descripci_on as nombre')
            ->join('CelaRepositorioC AS cr', 'cr.idRepositorio', '=', 'Cliente.LogotipoOficial')
            ->where('Cliente.id', $cliente)
            ->first();

        if ($ClienteImagen) {
            return response()->json([
                'success' => '1',
                'url' => $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . "/" . $ClienteImagen->Logotipo,
                'cliente' => $ClienteImagen->Logotipo,
                'clienteNombre' => $ClienteImagen->nombre

            ], 200);
        } else {
            return response()->json([
                'success' => '2',
                //'url'=>$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".$ClienteImagen->Logotipo,
                //'cliente'=>$ClienteImagen->Logotipo,
                //'clienteNombre'=>$ClienteImagen->nombre

            ], 200);
        }
    }

    public function postSuinpac(Request $request){
        #return $request;

        Funciones::selecionarBase($request->Cliente);
        $SQL = "SELECT id, Folio, FolioAnterior FROM Padr_onLicencia WHERE id = 12";

        if($Result = DB::select($SQL)){
            if( count($Result) == 0 )
                return array('result'=>'NULL');

            if(count($Result) > 0 ){
                $Result[0]->result = 'OK';
                $Result = json_decode(json_encode($Result[0]), true);
            }else
                $Result = ['result' => "ERROR"];
        }else{
            $Result = ['result' => "ERROR"];
        }

        return $Result['Folio'];
        #$datos = DB::select($SQL);
        #$array = json_decode(json_encode($datos[0]), true);
        #return $array['Folio'];

        $url = 'https://suinpac.piacza.com.mx/ApiRest/post.php';
        #$url = 'https://suinpac.piacza.com.mx/PruebasMario.php';

        $dataForPost = array(
            'Cliente' => [
                'Cliente2' => $request->Cliente,
                'Ejercicios' => $request->Ejercicio,
                'Claves' => $request->Claves,
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
        return $result;
    }

    public function ObtenerFactura(Request $request){
        #return $request;
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

                    #return $total;
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

                if( !array_key_exists( 'A_noPago', $DatosExtra ) || $DatosExtra['A_noPago'] == null ){
                    $DatosExtra['A_noPago'] = $ejercicio;
                }

                $A_noPago = $DatosExtra['A_noPago'];

                if($Ubicacion==" "){
                    $Ubicacion="No disponible";
                }

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

                $SuperficieConstruccion = ($DatosConstruccion!="NULL" ) ? number_format(str_replace(",","",$DatosConstruccion) ,2) : '0.00';
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
            return $concepto;
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
        return
        '<p><strong>Domicilio Fiscal:</strong> ' . $calle . ' ' . $numexterior . ' ' . $numinterior . ' ' . $colonia . ' ' . ucwords(strtolower($municipio)) . ' ' . ucwords(strtolower($estado)) . ' '
            .'<br><strong>C.P.:</strong> ' . $cp . '<br /><strong>Lugar de Expedici&oacute;n:</strong> ' . ucwords(strtolower($municipio)) . ', ' . ucwords(strtolower($estado)) . '
        </p >';
        return $htmlGlobal;
        try{
            $archivosalida = "Factura_Electronica".$claves.rand(10,99)."".uniqid().".pdf";
            $wkhtmltopdf   = new Wkhtmltopdf(array('path' =>$rutacompleta.'repositorio/temporal/', 'lowquality'=>true, 'FooterStyleLeft'=>'Grupo Piacza - SUINPAC Contable', 'FooterStyleRight'=>'Poliza No: '.$N_umeroP_oliza, 'FooterStyleCenter'=>'Pag. [page] de [toPage]'));
            $wkhtmltopdf->setHtml($htmlGlobal);
            $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE,$archivosalida);

            return $rutacompleta."repositorio/temporal/".$archivosalida;

        }catch (Exception $e) {
           return "Hubo un error al generar el PDF: ".$e->getMessage();
        }
    }

    public static function pruebas(Request $request){
        $cliente = $request->Cliente;
        $contribuyente = $request->Contribuyente;
        $concepto=$request->Concepto;
        $idPadron=$request->IdPadron;
        $consulta_usuario = Funciones::ObtenValor("SELECT c.idUsuario, c.Usuario
        FROM CelaUsuario c  INNER JOIN CelaRol c1 ON ( c.Rol = c1.idRol  )   WHERE c.CorreoElectr_onico='" . $cliente . "@gmail.com' ");
        $CajadeCobro = Funciones::ObtenValor("select CajaDeCobro from CelaUsuario where idUsuario=" .$consulta_usuario->idUsuario, "CajaDeCobro");
        $ObtieneImporteyConceptos= CotizacionServiciosPredialController::ObtieneImporteyConceptos($cliente,$ano,$concepto, 0, 1);
        return response()->json([
            'success' => '1',
            'datosFiscales'=>$DatosFiscales
        ], 200);
        return
        Funciones::selecionarBase($cliente);
        $Cliente = Funciones::ObtenValor('select Clave from Cliente where id=' .$cliente, 'Clave');
        $ano=date('Y');
        $momento=4;
        $totalGeneral=0;
        //////


        //////
        $UltimaCotizacion = Funciones::ObtenValor("select FolioCotizaci_on from Cotizaci_on where FolioCotizaci_on like '" . $ano. $Cliente . "%' order by FolioCotizaci_on desc limit 0,1", "FolioCotizaci_on");

        if ($UltimaCotizacion == 'NULL') {
            $N_umeroDeCotizacion = $ano. $Cliente . str_pad(1, 8, '0', STR_PAD_LEFT);
        } else {
            $N_umeroDeCotizacion = $ano. $Cliente . str_pad(intval(substr($UltimaCotizacion, -8, 8)) + 1, 8, '0', STR_PAD_LEFT);
        }

        $fondoDatos= CotizacionServiciosPredialController::getFondo($cliente,$ano);
        $presupuestoAnualPrograma=$fondoDatos->Progid;
        $fondo=$fondoDatos->Fondoid;
        $fechaCFDI=date('Y-m-d H:i:s');
        $fecha=date('Y-m-d');
        $medoPago=04;

        $areaRecaudadora=Funciones::getAreaRecaudadora($cliente,$ano,$concepto);

        $FuenteFinanciamiento = Funciones::ObtenValor("SELECT f1.id as FuenteFinanciamiento
            FROM Fondo f
                INNER JOIN Cat_alogoDeFondo ca ON ( f.CatalogoFondo = ca.id  )
                    INNER JOIN FuenteFinanciamiento f1 ON ( ca.FuenteFinanciamiento = f1.id  )
            WHERE f.id = " . $fondo, "FuenteFinanciamiento");



        DB::table('Cotizaci_on')->insert([
             [  'id' => null,
                'FolioCotizaci_on' => $N_umeroDeCotizacion,
                'Contribuyente'=>$contribuyente,
                'AreaAdministrativa'=>$areaRecaudadora,
                'Fecha' => $fecha,
                'Cliente'=>$cliente,
                'Fondo'=>$fondo,
                'Programa' => $presupuestoAnualPrograma,
                'FuenteFinanciamiento'=>$FuenteFinanciamiento,
                'FechaCFDI'=>$fechaCFDI,
                'MetodoDePago' => $medoPago,
                'Padr_on'=>$idPadron,
                'Usuario'=> $consulta_usuario ->idUsuario,
             ]
        ]);
        $idCotizacion = DB::getPdo()->lastInsertId();;

        $CatalogoFondo=Funciones::ObtenValor('select CatalogoFondo FROM Fondo WHERE id='.$fondo,'CatalogoFondo' );
        if(isset($idCotizacion)){
            // $tipoDoc=$ListadoDocumentos;
            // clve cliente + anio + area recaudadora + cpnsecutivo
            $CajadeCobro = Funciones::ObtenValor("select CajaDeCobro from CelaUsuario where idUsuario=" . $consulta_usuario ->idUsuario, "CajaDeCobro");

            $areaRecaudadora = Funciones::ObtenValor("SELECT Clave FROM AreasAdministrativas WHERE id=".$areaRecaudadora, "Clave");

            $UltimoFolio = Funciones::ObtenValor("select Folio from XMLIngreso where Folio like '%" . $Cliente .$ano . $areaRecaudadora . "%' order by Folio desc limit 0,1", "Folio");

            $Serie = $Cliente . $ano . $areaRecaudadora;

            if ($UltimoFolio == 'NULL') {
                $N_umeroDeFolio = str_pad(1, 8, '0', STR_PAD_LEFT);
            } else {
                $N_umeroDeFolio = str_pad(intval(substr($UltimoFolio, -8, 8)) + 1, 8, '0', STR_PAD_LEFT);

            }
            $ConsultaPadr_onCatastral = Funciones::ObtenValor("SELECT * FROM Padr_onCatastral WHERE id =  " . $idPadron);
            $datosPadron = Funciones::ObtenValor("SELECT  COALESCE(SuperficieConstrucci_on,0) SuperficieConstrucci_on, COALESCE(SuperficieTerreno,0) SuperficieTerreno, COALESCE(TipoPredioValores,0) as TipoPredioValores, COALESCE(TipoConstrucci_onValores,0) TipoConstrucci_onValores, TipoConstruci_on FROM Padr_onCatastral pc WHERE pc.id=" . $ConsultaPadr_onCatastral->id);

            $tipoPredioValor = Funciones::ObtenValor("SELECT  COALESCE(tpve.Importe, 0) as Importe FROM TipoPredioValores tpv
            INNER JOIN TipoPredioValoresEjercicioFiscal tpve ON (tpv.id=tpve.idTipoPredioValores AND tpve.EjercicioFiscal=" . $ano . ")
            WHERE tpv.id=" . $datosPadron->TipoPredioValores, "Importe");

            #
            $tipoPredioValor = (($tipoPredioValor == "NULL") ? 0 : floatval($tipoPredioValor));
            $consultaPredio=  Funciones::ObtenValor("SELECT *, FORMAT(ValorCatastral,2) as ValorCatastral, (SELECT sum((Padr_onConstruccionDetalle.SuperficieConstrucci_on)) FROM Padr_onConstruccionDetalle WHERE Padr_onCatastral.id=Padr_onConstruccionDetalle.idPadron) as SuperficieConstrucci_on,  Padr_onCatastral.Colonia as ColoniaPadron   FROM Padr_onCatastral WHERE id=".$idPadron);

            $valorCatastral=number_format(  ( floatval(str_replace(",", "", $ConsultaPadr_onCatastral->SuperficieTerreno) ) * floatval(str_replace(",", "", number_format($tipoPredioValor, 2) ) )  ) * number_format($ConsultaPadr_onCatastral->Indiviso, 6) / 100, 2);
            $SuperficieTerreno= number_format(str_replace(",", "", $ConsultaPadr_onCatastral->SuperficieTerreno), 2);
            $patron = '/[\'"]/';
            $arr['Usuario'] = Funciones::ObtenValor("SELECT NombreCompleto FROM CelaUsuario WHERE idUsuario=".$consulta_usuario ->idUsuario, "NombreCompleto");
            $arr['Leyenda'] = "Elaboró";
            $arr['NumCuenta']=null;
            $arr['idPadron']=$idPadron;
            $arr['Observacion'] = "";
            $arr['Descuento']= null;

            $arr['Cuenta']=$ConsultaPadr_onCatastral->Cuenta;
            $arr['CuentaAnterior']=$ConsultaPadr_onCatastral->CuentaAnterior ;
            $arr['Localidad']=$consultaPredio->Localidad;
            $arr['ValorCatastral']=$consultaPredio->ValorCatastral ;
            $arr['SuperficieTerreno']=$consultaPredio->SuperficieTerreno ;
            $arr['ValorPredio']="" ;
            $arr['SuperficieConstruccion']=$consultaPredio->SuperficieConstrucci_on;
            $arr['ValorConstruccion']="" ;
            $arr['Ubicacion']=preg_replace($patron, '',$consultaPredio->Ubicaci_on." ".$consultaPredio->ColoniaPadron);
            $arr['TipoCotizacionPredial']="Servicio";
            $DatosExtra = json_encode($arr, JSON_UNESCAPED_UNICODE);


            DB::table('XMLIngreso')->insert([
                [  'Contribuyente'=>$contribuyente,
                   'idCotizaci_on'=>$idCotizacion,
                   'Folio' => $Serie.$N_umeroDeFolio,
                   'MetodoDePago'=>$medoPago,
                   'DatosExtra'=>$DatosExtra,
                ]
           ]);
           $elXMLIngreso = DB::getPdo()->lastInsertId();

            $mes = explode("-",$fecha);

            $tipoBaseCalculo=Funciones::ObtenValor("SELECT  c3.BaseCalculo as TipobaseCalculo
            FROM ConceptoCobroCaja c
            INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto  )
            INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales  )
            INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente  )
            WHERE c3.Cliente=".$cliente." AND c3.EjercicioFiscal=".$ano." AND  c2.Cliente=".$cliente." AND c.id = ".$concepto,"TipobaseCalculo");

           $ObtieneImporteyConceptos= CotizacionServiciosPredialController::ObtieneImporteyConceptos($cliente,$ano,$concepto, 0, 1);
           $totalPagar=$ObtieneImporteyConceptos['importe'];
		   if($tipoBaseCalculo==1 || $tipoBaseCalculo==3){
				$cantidadConcepto=1;
			}else{
				$cantidadConcepto=$ObtieneImporteyConceptos['baseCalculo'];
            }
            $totalGeneral+=$totalPagar;
			DB::table('ConceptoAdicionalesCotizaci_on')->insert([
                [  'id' => null,
                   'ConceptoAdicionales'=>$concepto,
                   'Cotizaci_on'=>$idCotizacion,
                   'TipoContribuci_on' =>1,// 1 para concepto o 2 para retencion adicional
                   'Importe'=>number_format($ObtieneImporteyConceptos['importe'],2),//importe nota si no pongo el cero y es 50.6 no lo reconoce la base tiene que ser 50.60
                   'Estatus'=>0,//importe
                   'MomentoCotizaci_on'=>4,//4 es ley de ingresos devengada
                   'MomentoPago'=>null, //5 es para cuando se paga
                   'FechaPago' => null,//fecha de cobro
                   'CajaDeCobro'=>$CajadeCobro,//caja de cobro
                   'Xml'=>$elXMLIngreso,//el XML
                   'TipoCobroCaja'=>null,//TipoCobroCaja
                   'N_umeroDeOperaci_on'=>null,//numero de operacion
                   'CuentaBancaria' =>null ,// cuenta bancaria
                   'Adicional'=>null,//adicional
                   'Cantidad'=>$cantidadConcepto,//Cantidad de Conceptos ***** Aqui esta el detalle chato *****.
                   //Se modifico esta linea cuando se detecto que se generaban actualizaciones y recargos -> 12 mayo 2019
                   'Mes'=>null,
                   'A_no'=>$ano,
                   'TipoBase' => null,
                   'MontoBase'=>$ObtieneImporteyConceptos['baseCalculo'],
                   'Origen'=>"Cotizacion Servicios",
                ]
            ]);




			//obtengo los adicionales del concepto actual
			$datosAdicionales = CotizacionServiciosPredialController::ObtieneImporteyConceptos2($cliente,$ano,$concepto,$tipoBaseCalculo);

			//echo "<pre>".print_r($datosAdicionales, true)."</pre>";

			//agrego a la consulta los adicionales
			for ($k = 1; $k <= $datosAdicionales['NumAdicionales']; $k++) {

				$tt=  str_replace(',', '',$datosAdicionales['adicionales'.$k]['Resultado']);
                //cantidad
                $totalGeneral+=str_replace(',', '',$datosAdicionales['adicionales'.$k]['Resultado']);
                DB::table('ConceptoAdicionalesCotizaci_on')->insert([
                    [  'id' => null,
                       'ConceptoAdicionales'=>$concepto,
                       'Cotizaci_on'=>$idCotizacion,
                       'TipoContribuci_on' =>2,// 1 para concepto o 2 para retencion adicional
                       'Importe'=>str_replace(',', '',$datosAdicionales['adicionales'.$k]['Resultado']), //importe
                       'Estatus'=>0,//0 para no pagado  1 para pagado
                       'MomentoCotizaci_on'=>4,//4 es ley de ingresos devengada
                       'MomentoPago'=>null, //5 es para cuando se paga
                       'FechaPago' => null,//fecha de cobro
                       'CajaDeCobro'=>$CajadeCobro,//caja de cobro
                       'Xml'=>$elXMLIngreso,//el XML
                       'TipoCobroCaja'=>null,//TipoCobroCaja
                       'N_umeroDeOperaci_on'=>null,//numero de operacion
                       'CuentaBancaria' =>null ,// cuenta bancaria
                       'Adicional'=>$datosAdicionales['adicionales' . $k]['idAdicional'],//adicional
                       'Cantidad'=>$cantidadConcepto,//Cantidad de Conceptos ***** Aqui esta el detalle chato *****.
                       //Se modifico esta linea cuando se detecto que se generaban actualizaciones y recargos -> 12 mayo 2019
                       'Mes'=>null,
                       'A_no'=>$ano,
                       'TipoBase' => $datosAdicionales['adicionales' . $k]['TipoBase'],
                       'MontoBase'=>$datosAdicionales['adicionales' . $k]['MontoBase'],
                       'Origen'=>"Cotizacion Servicios",
                    ]
                ]);
            }

            $FuenteFinanciamiento = Funciones::ObtenValor("SELECT f1.id as FuenteFinanciamiento
            FROM Fondo f
                INNER JOIN Cat_alogoDeFondo ca ON ( f.CatalogoFondo = ca.id  )
                    INNER JOIN FuenteFinanciamiento f1 ON ( ca.FuenteFinanciamiento = f1.id  )
            WHERE f.id = " .$fondo, "FuenteFinanciamiento");

            $CatalogoFondo=Funciones::ObtenValor('select CatalogoFondo FROM Fondo WHERE id='.$fondo,'CatalogoFondo' );

            //AGREGO TODOS LOS DATOS A LA CONTABILIDAD
            $DatosEncabezado = Funciones::ObtenValor("select N_umeroP_oliza from EncabezadoContabilidad where Cotizaci_on=" . $idCotizacion, "N_umeroP_oliza");
            $Programa = Funciones::ObtenValor("SELECT Programa FROM PresupuestoAnualPrograma WHERE id=" . $presupuestoAnualPrograma, "Programa");

            if ($DatosEncabezado=="") {
                $UltimaPoliza = Funciones::ObtenValor("select N_umeroP_oliza as Ultimo from EncabezadoContabilidad where N_umeroP_oliza like '" . $ano . $Cliente . str_pad($Programa, 3, '0', STR_PAD_LEFT) . "03%' order by N_umeroP_oliza desc limit 0,1", "Ultimo");
                if ($UltimaPoliza == 'NULL')
                    $N_umeroDePoliza = $ano. $Cliente . str_pad($Programa, 3, '0', STR_PAD_LEFT) . "03" . str_pad(1, 6, '0', STR_PAD_LEFT);
                else
                    $N_umeroDePoliza = $ano . $Cliente . str_pad($Programa, 3, '0', STR_PAD_LEFT) . "01" . str_pad(intval(substr($UltimaPoliza, -6, 6)) + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $N_umeroDePoliza = $DatosEncabezado;
            }

            //cantidad
            DB::table('EncabezadoContabilidad')->insert([
                [  'id' => null,
                   'Cliente'=>$cliente,
                   'EjercicioFiscal'=>$ano,
                   'TipoP_oliza' =>1,// 1 ingresos   2 egresos  3 diario
                   'N_umeroP_oliza'=>$N_umeroDePoliza, //numero de poliza
                   'FechaP_oliza'=>$fecha,//fecha de poliza
                   'Concepto'=>"Cotizacion #" . $N_umeroDeCotizacion,//descripcion o concepto
                   'Cotizaci_on'=>$idCotizacion, //
                   'AreaRecaudadora' => $areaRecaudadora,//
                   'FuenteFinanciamiento'=> $FuenteFinanciamiento ,//
                   'Fondo'=>$fondo,//
                   'CatalogoFondo'=>$CatalogoFondo,//
                   'Programa'=>$Programa,//
                   'idPrograma' =>$presupuestoAnualPrograma ,//
                   'Proyecto'=>null,//adicional
                   'Clasificaci_onProgram_atica'=>null,
                   'Clasificaci_onFuncional'=>null,
                   'Clasificaci_onAdministrativa'=>null,
                   'Contribuyente' => $contribuyente,
                   'Persona'=>null,
                   'CuentaBancaria'=>null,
                   'MovimientoBancario'=>null,//
                   'N_umeroDeMovimientoBancario' =>null ,//
                   'Momento'=>$momento,//
                   'EstatusTupla'=>1,//Status 1 activo   y 0 cancelado
                   'FechaTupla'=>date('Y-m-d H:i:s'),
                   'AreaAdministrativaProyecto'=>null,
                   'Proveedor' => null,
                   'CuentaPorPagar'=>null,

                ]
            ]);

            $Status = true;
            $IdEncabezadoContabilidad = DB::getPdo()->lastInsertId();

            if(isset($IdEncabezadoContabilidad ) && $IdEncabezadoContabilidad>0){

                $ConsultaInsertaDetalleContabilidadC = "INSERT INTO DetalleContabilidad
                ( id, EncabezadoContabilidad, TipoP_oliza, FehaP_oliza, N_umeroP_oliza, ConceptoMovimientoContable, Cotizaci_on, AreaRecaudaci_on, FuenteFinanciamiento, Programa, idPrograma, Proyecto, AreaAdministrativaProyecto
                , Clasificaci_onProgram_atica, Clasificaci_onFuncionalGasto, Clasificaci_onAdministrativa, Clasificaci_onEcon_omicaIGF, Contribuyente, Proveedor, CuentaBancaria, MovimientoBancario
                , N_umeroMovimientoBancario, uuid, EstatusConcepto, ConceptoDeXML, MomentoContable, CRI, COG, TipoDeMovimientoContable, Importe, EstatusInventario, idDeLaObra, Persona, TipoDeGasto
                , TipoCobroCaja, PlanDeCuentas, NaturalezaCuenta, TipoBien, TipoObra, EstatusTupla, Fondo, CatalogoFondo, FechaTupla, Origen,ConceptoCobroCajaId) VALUES ";

                //Obtiene conceptos y adicioanles
                $ConsultaObtiene = "SELECT c3.id as ConceptoCobroCajaID,co.id, co.Importe, co.MomentoCotizaci_on, co.Xml, c3.CRI, co.Adicional, (SELECT Cri FROM RetencionesAdicionales WHERE co.Adicional= RetencionesAdicionales.id) as CriBueno, (SELECT PlanDeCuentas FROM RetencionesAdicionales WHERE co.Adicional= RetencionesAdicionales.id) as PlanCuentasBueno, (SELECT Abono FROM MomentoContable WHERE Momento=".$momento." AND MomentoContable.CRI=c3.CRI ) as AbonoSegunPlan,  (SELECT Naturaleza FROM PlanCuentas WHERE  PlanCuentas.id= AbonoSegunPlan ) as NaturalezaSegunPlan, (SELECT Naturaleza FROM PlanCuentas WHERE  PlanCuentas.id= PlanCuentasBueno ) as NaturalezaPlanCuentasBueno
                ,co.A_no as A_no
                FROM ConceptoAdicionalesCotizaci_on co
                                INNER JOIN ConceptoCobroCaja c3 ON ( co.ConceptoAdicionales = c3.id  )
                WHERE co.Cotizaci_on =" . $idCotizacion;

                if ($ResultadoObtieneConceptos = DB::select($ConsultaObtiene)) {
                         foreach( $ResultadoObtieneConceptos as $registroConceptos ) {

                            if(!is_null($registroConceptos->Adicional)){
                                $registroConceptos->ConceptoCobroCajaID= Funciones::ObtenValor("SELECT ra.ConceptoCobro FROM RetencionesAdicionales ra WHERE ra.id=".$registroConceptos->Adicional,"ConceptoCobro");
                            }

                            //aqui agrego datos a Asignaci_onPresupuestal
                            $ConsultaInserta = sprintf("INSERT INTO Asignaci_onPresupuestal (  id , Cri , ImporteAnual , PresupuestoAnualPrograma , FechaInicial , FechaFinal , EstatusTupla, AreaAdministrativa,  ImporteModificado, ImporteDevengado, Concepto )
                            VALUES (  %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
                            Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($registroConceptos->CRI, "int"), Funciones::GetSQLValueString(0, "decimal"), Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"), Funciones::GetSQLValueString(date('Y-m-d'), "date"), Funciones::GetSQLValueString(date('Y-m-d'), "date"), Funciones::GetSQLValueString(1, "tinyint"), Funciones::GetSQLValueString($areaRecaudadora, "int"), Funciones::GetSQLValueString(NULL, "decimal"), Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), Funciones::GetSQLValueString($registroConceptos->id, "int"));


                           if(DB::insert($ConsultaInserta)){
                                $IdRegistroAsignaci_onPresupuestal = DB::getPdo()->lastInsertId();
                           }
                           $momento = 4;

                           if($registroConceptos->A_no!=$ano) {
                               $momento = 13;
                           }


                            $DatosMomentoContable = Funciones::ObtenValor("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Abono) as NaturalezaAbono FROM  MomentoContable WHERE Momento=" . $registroConceptos->MomentoCotizaci_on . " AND Cri=" . $registroConceptos->CRI);
                            //AQUGO LOS DATOS DE RetencionesAdicionales

                            //REGLA: si el adicional es retencion se duplica el abono
                            //verifico que sea un adicional=I AGRE
                            if(!is_null($registroConceptos->Adicional)){
                                    //es un adicional

                                    $tieneCri=Funciones::ObtenValor("SELECT * FROM RetencionesAdicionales WHERE id=".$registroConceptos->Adicional);
                                    if(is_null($tieneCri->Cri)){
                                        //es adicional con plan de cuentas
                                        //quiere decir que no tiene CRI, trae plan de cuentas
                                    $DatosMomentoContable = Funciones::ObtenValor("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Abono) as NaturalezaAbono FROM  MomentoContable WHERE Momento=" . $registroConceptos->MomentoCotizaci_on. " AND Cri=" . $registroConceptos->CRI);
                                        //$planCuentas=ObtenValor();
                                        /**/
                                            //Para Abono 1 negativo
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                    Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                    Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                    Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                    Funciones::GetSQLValueString("AA1PC Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                    Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                    Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                    Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                    Funciones::GetSQLValueString($Programa, "int"), //programa
                                    Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                    Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                    Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                    Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                    Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                    Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                    Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                    Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                    Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                    Funciones::GetSQLValueString(null, "varchar"), //uuid
                                    Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                    Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                    Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                    Funciones::GetSQLValueString($fondo, "int"), //fondo
                                    Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                    Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                    Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                    Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));
                                    /* */
                                    //Para Abono 2 positivo
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                    Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                    Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                    Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                    Funciones::GetSQLValueString("AA2PC Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                    Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                    Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                    Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                    Funciones::GetSQLValueString($Programa, "int"), //programa
                                    Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                    Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                    Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                    Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                    Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                    Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                    Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                    Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                    Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                    Funciones::GetSQLValueString(null, "varchar"), //uuid
                                    Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                    Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                    Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                    Funciones::GetSQLValueString($fondo, "int"), //fondo
                                    Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                    Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                    Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                    Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));



                                    }else{
                                    //es adicional con cri
                                    $DatosMomentoContable = Funciones::ObtenValor("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Abono) as NaturalezaAbono FROM  MomentoContable WHERE Momento=" . $registroConceptos->MomentoCotizaci_on. " AND Cri=" . $registroConceptos->CriBueno);

                                    //Para cargo
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                    Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                    Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                    Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                    Funciones::GetSQLValueString("ACC Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                    Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                    Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                    Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                    Funciones::GetSQLValueString($Programa, "int"), //programa
                                    Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                    Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                    Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                    Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                    Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                    Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                    Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                    Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                    Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                    Funciones::GetSQLValueString(null, "varchar"), //uuid
                                    Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                    Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                    Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                    Funciones::GetSQLValueString($fondo, "int"), //fondo
                                    Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                    Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                    Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                    Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));

                                    //Para Abono
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                    Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                    Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                    Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                    Funciones::GetSQLValueString("ACA Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                    Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                    Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                    Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                    Funciones::GetSQLValueString($Programa, "int"), //programa
                                    Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                    Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                    Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                    Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                    Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                    Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                    Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                    Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                    Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                    Funciones::GetSQLValueString(null, "varchar"), //uuid
                                    Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                    Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                    Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                    Funciones::GetSQLValueString($fondo, "int"), //fondo
                                    Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                    Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                    Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                    Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));

                                    /************************************************************************************/
                                    //Esto es presupuestal
                                    $ConsultaObtieneMomentoPresupuestal = sprintf("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Abono) as NaturalezaAbono FROM MomentoPresupuestal WHERE Momento=%s AND Cargo IS NOT NULL AND Abono IS NOT NULL ", Funciones::GetSQLValueString($momento, "int"));
                                    $ResultadoObtieneMomentoPresupuestal = DB::select($ConsultaObtieneMomentoPresupuestal);

                                    foreach ($ResultadoObtieneMomentoPresupuestal as $RegistroObtieneMomentoPresupuestal) {
                                        //Para cargo en presupuestal
                                        $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                        Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                        Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                        Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                        Funciones::GetSQLValueString("---Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                        Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                        Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                        Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                        Funciones::GetSQLValueString($Programa, "int"), //programa
                                        Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                        Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                        Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                        Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                        Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                        Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                        Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                        Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                        Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                        Funciones::GetSQLValueString(null, "varchar"), //uuid
                                        Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                        Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                        Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                        Funciones::GetSQLValueString($fondo, "int"), //fondo
                                        Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                        Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                        Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                        Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));

                                        //Para abono en presupuestal
                                       $ConsultaInsertaDetalleContabilidadC .= sprintf("(%s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                        Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                        Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                        Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                        Funciones::GetSQLValueString("---Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                        Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                        Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                        Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                        Funciones::GetSQLValueString($Programa, "int"), //programa
                                        Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                        Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                        Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                        Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                        Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                        Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                        Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                        Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                        Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                        Funciones::GetSQLValueString(null, "varchar"), //uuid
                                        Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                        Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                        Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                        Funciones::GetSQLValueString($fondo, "int"), //fondo
                                        Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                        Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                        Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                        Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));

                                    }//while($RegistroObtieneMomentoPresupuestal = $ResultadoObtieneMomentoPresupuestal->fetch_assoc()){
                                        //TERMINA PRESUPUESTAL

                            }//

                         }//termina if para saber si es adicional o concepto
                            else{
                                //es concepto
                                //
                                    $DatosMomentoContable = Funciones::ObtenValor("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Abono) as NaturalezaAbono FROM  MomentoContable WHERE Momento=" . $registroConceptos->MomentoCotizaci_on . " AND Cri=" . $registroConceptos->CRI);

                                    //Para cargo
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                    Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                    Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                    Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                    Funciones::GetSQLValueString("ConceptoC - Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                    Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                    Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                    Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                    Funciones::GetSQLValueString($Programa, "int"), //programa
                                    Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                    Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                    Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                    Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                    Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                    Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                    Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                    Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                    Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                    Funciones::GetSQLValueString(null, "varchar"), //uuid
                                    Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                    Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                    Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                    Funciones::GetSQLValueString($fondo, "int"), //fondo
                                    Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                    Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                    Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                    Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));

                                    //Para Abono
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                    Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                    Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                    Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                    Funciones::GetSQLValueString("ConceptoA - Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                    Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                    Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                    Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                    Funciones::GetSQLValueString($Programa, "int"), //programa
                                    Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                    Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                    Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                    Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                    Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                    Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                    Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                    Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                    Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                    Funciones::GetSQLValueString(null, "varchar"), //uuid
                                    Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                    Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                    Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                    Funciones::GetSQLValueString($fondo, "int"), //fondo
                                    Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                    Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                    Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                    Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));


                                    /************************************************************************************/
                                    //Esto es presupuestal
                                    $ConsultaObtieneMomentoPresupuestal = sprintf("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Abono) as NaturalezaAbono FROM MomentoPresupuestal WHERE Momento=%s AND Cargo IS NOT NULL AND Abono IS NOT NULL ", Funciones::GetSQLValueString($momento, "int"));
                                    $ResultadoObtieneMomentoPresupuestal =DB::select($ConsultaObtieneMomentoPresupuestal);

                                    foreach ( $ResultadoObtieneMomentoPresupuestal as $RegistroObtieneMomentoPresupuestal) {
                                        //Para cargo en presupuestal
                                        $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                        Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                        Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                        Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                        Funciones::GetSQLValueString("---Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                        Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                        Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                        Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                        Funciones::GetSQLValueString($Programa, "int"), //programa
                                        Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                        Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                        Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                        Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                        Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                        Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                        Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                        Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                        Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                        Funciones::GetSQLValueString(null, "varchar"), //uuid
                                        Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                        Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                        Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                        Funciones::GetSQLValueString($fondo, "int"), //fondo
                                        Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                        Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                        Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                        Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));

                                        //Para abono en presupuestal
                                        $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                        Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                        Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                        Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                        Funciones::GetSQLValueString("---Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                        Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                        Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                        Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                        Funciones::GetSQLValueString($Programa, "int"), //programa
                                        Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                        Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                        Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                        Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                        Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                        Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                        Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                        Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                        Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                        Funciones::GetSQLValueString(null, "varchar"), //uuid
                                        Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                        Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                        Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                        Funciones::GetSQLValueString($fondo, "int"), //fondo
                                        Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                        Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                        Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                        Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));

                                    }//while($RegistroObtieneMomentoPresupuestal = $ResultadoObtieneMomentoPresupuestal->fetch_assoc()){
                                        //TERMINA PRESUPUESTAL
                                        /************************************************/
                                }//termina else de si es adicional o concepto


                        }//while

                }//Obtiene conceptos
                            $ConsultaInsertaDetalleContabilidadC = substr_replace($ConsultaInsertaDetalleContabilidadC, ";", -1);

                            //print $ConsultaInsertaDetalleContabilidadC;

                            //echo  "<pre>".$ConsultaInsertaDetalleContabilidadC."</pre>";
                            if ($ResultadoInsertaDetalleContabilidadC =DB::insert($ConsultaInsertaDetalleContabilidadC)) {
                                $ConsultaLog = sprintf("INSERT INTO CelaAccesos ( idAcceso, FechaDeAcceso, idUsuario, Tabla, IdTabla, Acci_on ) VALUES ( %s, %s, %s, %s, %s, %s)", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "varchar"), Funciones::GetSQLValueString($consulta_usuario ->idUsuario, "int"), Funciones::GetSQLValueString('Cotizaci_on', "varchar"), Funciones::GetSQLValueString($idCotizacion, "int"), Funciones::GetSQLValueString(2, "int"));
                                $ResultadoLog = DB::insert($ConsultaLog);

                                if (isset($_POST['ConceptoAdicionalesCotizaci_onInsertBack']) && $_POST['ConceptoAdicionalesCotizaci_onInsertBack'] == "ConceptoAdicionalesCotizaci_onInsertBack") {
                                    $Status = "Success";
                                } else {
                                    //$InsertGoTo = "ConceptoAdicionalesCotizaci_onLeer.php?".EncodeThis("Status=SuccessC");
                                    //header(sprintf("Location: %s", $InsertGoTo));

                                                                //Se guarda el registro en la tabla de tramites de isai en caso de que sea tramite por ISAI
                                                                if( $concepto==697 || $concepto==4328) {
                                                                    $Consulta="UPDATE Padr_onCatastral SET TrasladoDominio=1 WHERE id=".$idPadron;
                                                                    DB::update($Consulta);
                                                                        $ConsultaInserta = sprintf("INSERT INTO Padr_onCatastralTramitesISAINotarios ( `id` , `IdPadron`, `Estatus`, `IdUsuario`,  `IdCotizacionForma3` ) VALUES (%s, %s, %s, %s, %s)",
                                                                        Funciones::GetSQLValueString(NULL, "int unsigned"),
                                                                        Funciones::GetSQLValueString($idPadron, "varchar"),
                                                                        Funciones::GetSQLValueString(1, "varchar"),
                                                                        Funciones::GetSQLValueString($consulta_usuario ->idUsuario, "int"),
                                                                        Funciones::GetSQLValueString($idCotizacion, "int"));
                                                                        if(DB::insert($ConsultaInserta)){
                                                                            $idTramite =  DB::getPdo()->lastInsertId();
                                                                            $CatalogoDocumntosISAI = DB::select("SELECT * FROM TipoDocumentoTramiteISAI WHERE Requerido=1");
                                                                            foreach ($CatalogoDocumntosISAI as $DocCatalogo){

                                                                                $ConsultaInserta = sprintf("INSERT INTO Padr_onCatastralTramitesISAINotariosDocumentos (Id, IdTramite, IdTipoDocumento, Origen, ControlVersion) VALUES ( %s, %s, %s, %s, %s )",
                                                                                Funciones::GetSQLValueString( NULL, "int"),
                                                                                Funciones::GetSQLValueString($idTramite, "int"),
                                                                                Funciones::GetSQLValueString($DocCatalogo->Id, "int"),
                                                                                Funciones::GetSQLValueString($DocCatalogo->Origen, "int"),
                                                                                Funciones::GetSQLValueString(1, "int") );
                                                                                DB::insert($ConsultaInserta);



                                                                            }

                                                                        }

                                                                }

                                                                if( isset($_POST['ServicioDU']) && $_POST['ServicioDU']=='ServicioDU') {
                                                                    $ConsultaActualiza = sprintf("UPDATE Padr_onCatastral SET
                                                                    `UsoActual`=%s,
                                                                    `Frente`=%s,
                                                                    `NumeroOficial`=%s
                                                                     WHERE id = %s",
                                                                    Funciones::GetSQLValueString($_POST['UsoActual'], "varchar"),
                                                                    Funciones::GetSQLValueString($_POST['Frente'], "decimal"),
                                                                    Funciones::GetSQLValueString($_POST['NumeroOficial'], "varchar"),
                                                                    Funciones::GetSQLValueString($_POST['idPadron'], "int unsigned")  );
                                                                    if($ResultadoInserta = $Conexion->query($ConsultaActualiza)){

                                                                    }else{
                                                                        precode($Conexion->error,1,1);
                                                                    }
                                                                }

                                  //  header(sprintf("Location: %s", "Cotizaci_onVistaPrevia.php?" . EncodeThis("clave=" . $IdRegistroCotizaci_on."&TipoDocumento=Servicio")));
                                }

                                return response()->json([
                                    'idCotizacion' => $idCotizacion,
                                    'idXML'=>$elXMLIngreso,
                                    'IdEncabezadoContabilidad'=> $IdEncabezadoContabilidad,
                                    'Asignaci_onPresupuestal'=>$IdRegistroAsignaci_onPresupuestal,
                                    'Total'=>$totalGeneral

                                    ], 200);
                      } else {
                                $Error = $Conexion -> error;
                       }


            }
            return response()->json([
                'idCotizacion' => $idCotizacion,
                'CajadeCobro'=>$elXMLIngreso

                ], 200);

        }

    }

    public function demo2(Request $request){
        $Cliente = 20;

        Funciones::selecionarBase($Cliente);

        DB::enableQueryLog(); // Enable query log

        $entidad = DB::table('Padr_onAguaPotable')->where('id', '39523')->get();

        dd(DB::getQueryLog()); // Show results of log

        return $entidad;

        $datosExtra = TramitesISAINotarios::all();
        return response()->json($datosExtra, 200, [], JSON_UNESCAPED_UNICODE);
        #return $datosExtra->toArray();;
        return $datosExtra->toJson();

        return response()->json([
            'success' => '1',
            'datosExtra'=>  $datosExtra
        ], 200);
    }

    public function demo( Request $request){

        $resultado = (new PortalNotariosController )->num2letras(25);

        return $resultado;



        #$idCliente = $request->Cliente;
        $idCliente = 40;

        $QR = '';

        $consultaCliente = "select *,Descripci_on,
        (SELECT l.Nombre FROM DatosFiscalesCliente d INNER JOIN Localidad l ON (l.id=d.Localidad) WHERE d.id=Cliente.DatosFiscales ) Localidad,
        (SELECT l.Nombre FROM DatosFiscalesCliente d INNER JOIN EntidadFederativa l ON (l.id=d.EntidadFederativa) WHERE d.id=Cliente.DatosFiscales ) Entidad,
        (select Ruta from CelaRepositorioC where CelaRepositorioC.idRepositorio=Cliente.Logotipo) as Logo
        from Cliente where id=" . $idCliente;

        $Cliente = DB::select($consultaCliente);
#return $Cliente;
        #$imagen = asset( 'backend/public/storage/imagenes/capazlogo.png' );
        #return public_path('images') .'<br>'. storage_path('images') .'<br>'. app_path('images') . '<br>'. Storage::url('imagenes/capazlogo.png');

        #$imagen = asset( 'backend/public' . Storage::url('imagenes/capazlogo.png') );

        //Para las imagenes
        #$STORAGE = 'backend/public';

        $DIR_RESOURCE = 'recursos/';
        $DIR_IMG = 'imagenes/';
        /*$nombreArchivo = 'capazlogo.png';
        $imagen = '';
        if( Storage::exists( $DIR_IMG.$nombreArchivo) ){
            $imagen = asset( Storage::url( $DIR_IMG.$nombreArchivo) );
        }*/

        #return public_path();
        #return app_path();

        $contenido = "https://api.servicioenlinea.mx/repositorio/QR/2019/10/24/vf35_111.png";
        $QR = 'repositorio/QR/'.date('Y/m/d').'/vf'.$idCliente.'_111.png';

        if ( !file_exists('repositorio/QR/'.date('Y/m/d').'/') ) {
            mkdir('repositorio/QR/'.date('Y/m/d').'/', 0755, true);
            #return "No Existe...";
        }else{
            #return "Existe...";
        }

        if( !file_exists($QR) ){
            #return "No Existe...." . $QR;
            #include( app_path() . '/Libs/qrcode.php' );
            //Contenido del QR
            QRcode::png($contenido, $QR, 'M' , 4, 2);
            #QRcode::png($contenido, $QR, 'M' , 4, 2);
            #usleep(500);
            #header("Refresh:0");
        }else{
            #return "Existe...";
        }

        /*$path = public_path( $urlImagen );
        $pathApp = app_path( $urlImagen );
        $pathImg = asset( $urlImagen );*/

        #https://api.servicioenlinea.mx/backend/public/barraColores.png
        #https://api.servicioenlinea.mx/backend/storage/app/public/capazlogo.png
        #https://api.servicioenlinea.mx/backend/public/storage/capazlogo.png

        #$urlImagen = Storage::response('capazlogo.png');
        #$imagen = asset('storage/app/public/capazlogo.png');
        #$imagen = asset( Storage::url('barraColores.png') );

        #$imagen = asset( 'backend/public/barraColores.png' );
        #return asset('storage/barraColores.png');

        /*$path = storage_path('app/capazlogo.png');
        if (!File::exists($path)) {
            return "No existe...";
        }
        $file = File::get($path);
        $type = File::mimeType($path);
        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);
        return $file;*/

        /*return response()->json([
            'result' =>  [
                'public path' => $path,
                'app path' => $pathApp,
                'app pathImg' => $pathImg,
            ],
        ]);*/

        $miHTML = '
        <html>
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
                        <table border="0" width="100%">
                            <tr>
                                <td colspan="4" width="20%"><img src="'.asset($Cliente[0]->Logo).'" alt="Logo del cliente"  style="height: 80px;"></td>
                                <td colspan="4" width="80%"><div class="text-center"><b><h2>'.$Cliente[0]->Descripci_on.'</h2></b></div></td>
                                <td colspan="4" width="20%"> <img src="'.asset($QR).'" alt="QR Verificador"  style="height: 80px;"> </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </body>
        </html>';

        return $miHTML;
        #include( app_path() . '/Libs/Wkhtmltopdf.php' );

        try{
            $DirectorioTemporal = 'repositorio/temporal/';
            $NombreArchivo = "Forma3DCC_".uniqid().".pdf";
            $wkhtmltopdf = new Wkhtmltopdf(array('path' =>$DirectorioTemporal, 'lowquality'=>true, 'FooterStyleLeft'=>'Grupo Piacza - SUINPAC Contable', 'FooterStyleCenter'=>'Pag. [page] de [toPage]'));
            $wkhtmltopdf->setHtml($miHTML);
            $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $NombreArchivo);

            return $DirectorioTemporal.$NombreArchivo;
        }catch (Exception $e) {
            echo "Hubo un error al generar el PDF: " . $e->getMessage();
            return "";
        }
    }

    public function generar3DCC( Request $request ){
        #return $request;
        #$resultado = ReporteForma3DCC::generar(71123, 35, 2019);
        #$resultado = ExtrasController::G3DCC(71123, 35, 2019);

        #                           NombreArchivo, Contenido
        #Storage::disk('local')->put('file.txt', 'Contents');
        #Storage::put('avatars/1', $fileContents);
        $url = Storage::url('file.txt');
        return $url;
    }

    public function entidadFederativa(){
        $entidad = DB::table('EntidadFederativa')->select('id', 'Nombre')->get();
        return response()->json([
            'success' => '1',
            'entidadFederativa'=>$entidad
        ], 200);
    }

    public function municipios(Request $request){
        $entidad=$request->IdEntidad;

        $municipios = DB::table('Municipio')
        ->select('id', 'Nombre')
        ->orderBy('Nombre',"asc")
        ->where('EntidadFederativa',$entidad)->get();

        //$municipios = DB::table('Municipio')->select('id', 'Nombre')->where('EntidadFederativa', '12')->get();
        return response()->json([
            'success' => '1',
            'entidadFederativa'=>$municipios
        ], 200);
    }

    public function localidades(Request $request){
        $municipio=$request->IdMunicipio;

        $localidades = DB::table('Localidad')
        ->select('id', 'Nombre')
        ->orderBy('Nombre',"asc")
        ->where('Municipio', $municipio)->get();
        return response()->json([
            'success' => '1',
            'localidades'=>$localidades
        ], 200);
    }

    public function getImagenCliente( $cliente ){

        $imagen = Cliente::select('CelaRepositorio.Ruta')
            ->join('CelaRepositorio', 'zCliente.Logotipo', '=', 'CelaRepositorio.idRepositorio')
            ->where('zCliente.id', $cliente)
            ->value('Ruta');
            #->get();

        #return $response->withHeader('Content-Type', 'image/png')->withStatus(200);
        return response()->json([
            'result' =>  [
                'Ruta' => $imagen,
                ],
        ]);
        /*$result = Funciones::respondWithToken([
            'Ruta' => $imagen,
        ]);
        return $result;*/
    }

    public static function G3DCC( $idCotizacionDocumentos, $cliente, $ejercicioFiscal ){
        $clave = $idCotizacionDocumentos;
        $idCliente = $cliente;
        $AnioActual = $ejercicioFiscal;

        Funciones::selecionarBase($cliente);

        $idCatalogoDocumento = 29;
        $urlCodigoQR = "http://v.servicioenlinea.mx/vf.php?id=".$clave.'&cliente='.$idCliente.'&idDoc='.$idCatalogoDocumento;
        $QR = 'repositorio/QR/'.date('Y/m/d').'/vf'.$clave.'_'.$idCliente.'_'.$idCatalogoDocumento.'.png';

        if ( !file_exists('repositorio/QR/'.date('Y/m/d').'/') ) {
            mkdir('repositorio/QR/'.date('Y/m/d').'/', 0755, true);
        }

        if( !file_exists($QR) ){
            #return "No Existe...." . $QR;
            include( app_path() . '/Libs/QRcode.php' );
            //Contenido del QR
            new QRcode($urlCodigoQR, $QR, 'M' , 4, 2);
            usleep(500);
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
                $anio = date("Y", $fechaEntera);
                $mes = date("m", $fechaEntera);
                $dia = date("d", $fechaEntera);

                $LugaryFecha = $ConsultaLocalidad. ' Municipio de '.$ConsultaMunicipio. ', Estado de '.$ConsultaEntidadFederativa. '; '.$dia.' dias de '.$meses[intval($mes)]. ' del año '. $anio.'.';
            }

            $MarcaDocumentos = DB::select("SELECT DISTINCT IdTipoDocumento FROM Padr_onCatastralTramitesISAINotariosDocumentos WHERE EstatusCatastro=1 AND EstatusTercero=1 AND IdTramite='".$DatosTramiteISAI->Id . "'");
            #return "SELECT DISTINCT IdTipoDocumento FROM Padr_onCatastralTramitesISAINotariosDocumentos WHERE IdTramite=".$DatosTramiteISAI->Id;
            $Deslinde = 'circulopcion'; $noadedudopredial='circulopcion'; $noadeudoagua='circulopcion'; $avaluo='circulopcion'; $certificado='circulopcion'; $escritura='circulopcion';
            return $MarcaDocumentos;
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

                <link href="' . $_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/". 'bootstrap.min.css" rel="stylesheet">
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

                            <thead>
                                <tr>
                                    <th colspan="12">

                                        <table border="0" width="100%">

                                            <tr>
                                                <td colspan="4" width="20%"><img src="'.$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".$Cliente[0]->Logo.'" alt="Logo del cliente"  style="height: 80px;"></td>
                                                <td colspan="4" width="80%"><div class="text-center"><b><h2>'.$Cliente[0]->Descripci_on.'</h2></b></div></td>
                                               <td colspan="4" width="20%"> <img src="'.$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".$QR.'" alt="QR Verificador"  style="height: 80px;"> </td>
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

                                    </th>
                                </tr>
                            </thead>

                            <tbody>
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
                            </tbody>

                        </table>
                    </div>
                </div>
            </body>
        </html>';
        return $miHTML;
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

    public function obtenerDocumentosAyuntamiento2(Request $request){
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
        #return $Forma;
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

            $VerificarTramite = PadronCatastralTramitesISAINotarios::selectRaw('COUNT(Id) AS ParaFirma')
                    ->where('IdCotizacionForma3', $Tramite->IdCotizacionForma3)
                    ->whereNotNull('IdCotizacionISAI')
                    ->value('ParaFirma');
            #return $VerificarTramite;
            if($VerificarTramite > 0){
                $s3 = new LibNubeS3($Cliente);

                $idTabla = $DatosDocumento->idContabilidad . $DatosDocumento->id;
                $Tabla   = 'Reporte_Forma3DCC';

                $url = FuncionesFirma::ObtenerDocumentoFirmado($idTabla, $Tabla, $s3, 0, $Cliente);
                #return $Documento;

                /*$Tabla = "Reporte_Forma3DCC";
                $idTabla = $DatosDocumento->idContabilidad.$DatosDocumento->id;

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
                            if($firmado)
                                $url = $urls[0]->Ruta;
                            else
                                $url = CelaRepositorio::select('Ruta')->where([["Tabla", $name], ["idTabla", $valor->idContabilidad.$valor->id],['NombreOriginal', '!=', 'noexiste.pdf']])->value('Ruta');
                        break;
                    }
                }else
                    $url = ReporteForma3DCC::generar($Tramite->IdCotizacionForma3, $Cliente, date("Y"));*/
            }else
                $url = ReporteForma3DCC::generar($Tramite->IdCotizacionForma3, $Cliente, date("Y"));

            $NombreDocumento = DB::table('TipoDocumentoTramiteISAI')->where('Id','1')->value('Nombre');

            $Rutas[3] = Array(
                "Ruta" => $url,
                "IdDocumentoControl" => $Forma->Id,
                "EstatusCatastro" => $Forma->EstatusCatastro,
                "EstatusTercero" => $Forma->EstatusTercero,
                "Nombre"=>$NombreDocumento,
                "IdCotizacion"=>$Tramite->IdCotizacionForma3
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

        $Documentos = DB::select( $consultaDB );

        foreach ($Documentos as $valor){
            if($valor->DocumentoRuta == 'DeslindeCatastralFirma.php'){
                $s3 = new LibNubeS3($Cliente);

                $idTabla = $valor->idContabilidad . $valor->id;
                $Tabla   = 'DeslindeCatastral';
                $IdDocControl = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
                    ->select('Id', 'EstatusCatastro', 'EstatusTercero')
                    ->where('IdTipoDocumento', '2')->where('IdTramite', $IdTramite)
                    ->orderByDesc('Id')->first();#->take(1)->get();

                $url = FuncionesFirma::ObtenerDocumentoFirmado($idTabla, $Tabla, $s3, 0, $Cliente);
                #return $idTabla;

                /*$name = 'DeslindeCatastral';
                $IdDocControl = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
                    ->select('Id', 'EstatusCatastro', 'EstatusTercero')
                    ->where('IdTipoDocumento', '2')->where('IdTramite', $IdTramite)
                    ->orderByDesc('Id')->first();#->take(1)->get();
                $NombreDocumento = DB::table('TipoDocumentoTramiteISAI')->where('Id','2')->value('Nombre');
                #$url = CelaRepositorio::where("Tabla", $name)->where("idTabla", $valor->idContabilidad . $valor->id )->value("Ruta");
                $NumeroDocumentos = CelaRepositorio::selectRaw('count(idRepositorio) as numero')
                    ->where([
                        ['Tabla', $name],
                        ['idTabla', $valor->idContabilidad.$valor->id],
                    ])->value('numero');

                $urls = CelaRepositorio::where([
                        ["Tabla", $name],
                        ["idTabla", $valor->idContabilidad.$valor->id]
                    ])->orderByDesc('idRepositorio')->get();
                return $urls;
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
                            #return $urls;
                            if($firmado)
                                $url = $urls[0]->Ruta;
                            else
                                $url = CelaRepositorio::select('Ruta')->where([["Tabla", $name], ["idTabla", $valor->idContabilidad.$valor->id],['NombreOriginal', '!=', 'noexiste.pdf']])->value('Ruta');
                        break;
                    }
                }else
                    $url = "";
                */

                if($IdDocControl){
                    $Rutas[2] = Array(
                        "Ruta"               => $url,
                        "IdDocumentoControl" => $IdDocControl->Id,
                        "EstatusCatastro"    => $IdDocControl->EstatusCatastro,
                        "EstatusTercero"     => $IdDocControl->EstatusTercero,
                        "DiasRestantes"      => $valor->DiasRestantes,
                        "FechaVencimiento"   => $valor->FechaVencimiento,
                        "Nombre"             => $NombreDocumento,
                        "IdCotizacion"       => $valor->id
                    );
                }
            }

            if($valor->DocumentoRuta=='Padr_onCatastralConstanciaNoAdeudoOK.php'){
                $s3 = new LibNubeS3($Cliente);

                $idTabla = $valor->idContabilidad . $valor->id;
                $Tabla   = 'ConstanciaNoAdeudoPredial';
                $IdDocControl = PadronCatastralTramitesISAINotariosDocumentos::select('Id', 'EstatusCatastro', 'EstatusTercero')
                    ->where([ ['IdTipoDocumento', '3'], ['ControlVersion', '1'], ['IdTramite', $IdTramite] ])
                    ->orderByDesc('Id')->first();

                $url = FuncionesFirma::ObtenerDocumentoFirmado($idTabla, $Tabla, $s3, 0, $Cliente);
                #return $idTabla;
                /*$name = 'ConstanciaNoAdeudoPredial';

                $IdDocControl = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
                    ->select('Id', 'EstatusCatastro', 'EstatusTercero')
                    ->where('IdTipoDocumento', '3')->where('ControlVersion', '1')->where('IdTramite', $IdTramite)
                    ->orderByDesc('Id')->first();

                $NombreDocumento = DB::table('TipoDocumentoTramiteISAI')->where('Id','3')->value('Nombre');

                #$url = CelaRepositorio::where("Tabla", $name)->where("idTabla", $valor->idContabilidad . $valor->id )->value("Ruta");

                $NumeroDocumentos = CelaRepositorio::selectRaw('count(idRepositorio) as numero')
                    ->where([
                        ['Tabla', $name],
                        ['idTabla', $valor->idContabilidad.$valor->id],
                    ])->value('numero');

                $urls = CelaRepositorio::where([
                        ["Tabla", $name],
                        ["idTabla", $valor->idContabilidad.$valor->id]
                    ])->orderByDesc('idRepositorio')->get();

                return $urls;

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
                                $url = $urls[0]->Ruta;
                            else
                                $url = CelaRepositorio::select('Ruta')->where([["Tabla", $name], ["idTabla", $valor->idContabilidad.$valor->id],['NombreOriginal', '!=', 'noexiste.pdf']])->value('Ruta');
                                #$url = CelaRepositorio::where([["Tabla", $name],["idTabla", $valor->idContabilidad.$valor->id]])->first()->value('Ruta');
                        break;
                    }
                }else
                    $url = "";*/

                if($IdDocControl){
                    $Rutas[1] = Array(
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

    public function obtenerDocumentosAyuntamiento(Request $request){
        $Cliente   = $request->Cliente;
        $IdPadron  = $request->idPadron;
        $IdTramite = $request->idTramite;

        return PruebaHelper();
        #$raiz = $_SERVER['DOCUMENT_ROOT'];
        #$cliente = Cliente::where('id', $Cliente)->value('Nombre');
        #return $cliente;

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

            /*$Forma3DCC = DB::table('Cotizaci_on as c')->select('c.id')
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
            */

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

            $VerificarTramite = PadronCatastralTramitesISAINotarios::selectRaw('COUNT(Id) AS ParaFirma')
                    ->where('IdCotizacionForma3', $Tramite->IdCotizacionForma3)
                    ->whereNotNull('IdCotizacionISAI')
                    ->value('ParaFirma');

            if($VerificarTramite > 0){
                $Tabla = "Reporte_Forma3DCC";
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
                            if($firmado)
                                $url = $urls[0]->Ruta;
                            else
                                $url = CelaRepositorio::select('Ruta')->where([["Tabla", $name], ["idTabla", $valor->idContabilidad.$valor->id],['NombreOriginal', '!=', 'noexiste.pdf']])->value('Ruta');
                        break;
                    }
                }else
                    $url = ReporteForma3DCC::generar($Tramite->IdCotizacionForma3, $Cliente, date("Y"));
            }else
                $url = ReporteForma3DCC::generar($Tramite->IdCotizacionForma3, $Cliente, date("Y"));

            $NombreDocumento = DB::table('TipoDocumentoTramiteISAI')->where('Id','1')->value('Nombre');

            $Rutas[3] = Array(
                "Ruta" => $url,
                "IdDocumentoControl" => $Forma->Id,
                "EstatusCatastro" => $Forma->EstatusCatastro,
                "EstatusTercero" => $Forma->EstatusTercero,
                "Nombre"=>$NombreDocumento,
                "IdCotizacion"=>$Tramite->IdCotizacionForma3
            );

            #return $Rutas;

            /*foreach ($Forma3DCC as $valor1){
                $name = "Reporte_Forma3DCC";
                $url = ReporteForma3DCC::generar($Tramite->IdCotizacionForma3, $Cliente, date("Y"));
                #$url= ReporteForma3DCC::generar($Forma->IdCotizacion, $Cliente, date("Y"));
                //$url = CelaRepositorio::where("Tabla", $name)->where("idTabla", $valor1->idContabilidad . $valor1->id )->value("Ruta");
                $NombreDocumento = DB::table('TipoDocumentoTramiteISAI')
                    ->where('Id','1')
                    ->value('Nombre');

                $Rutas[3] = Array(
                    "Ruta" => $url,
                    "IdDocumentoControl" => $Forma->Id,
                    "EstatusCatastro" => $Forma->EstatusCatastro,
                    "EstatusTercero" => $Forma->EstatusTercero,
                    "Nombre"=>$NombreDocumento,
                    "IdCotizacion"=>$Tramite->IdCotizacionForma3
                );
            }*/
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

                $IdDocControl = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
                    ->select('Id', 'EstatusCatastro', 'EstatusTercero')
                    ->where('IdTipoDocumento', '2')->where('IdTramite', $IdTramite)
                    ->orderByDesc('Id')->first();#->take(1)->get();

                $NombreDocumento = DB::table('TipoDocumentoTramiteISAI')->where('Id','2')->value('Nombre');

                #$url = CelaRepositorio::where("Tabla", $name)->where("idTabla", $valor->idContabilidad . $valor->id )->value("Ruta");
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
                            #return $urls;
                            if($firmado)
                                $url = $urls[0]->Ruta;
                            else
                                $url = CelaRepositorio::select('Ruta')->where([["Tabla", $name], ["idTabla", $valor->idContabilidad.$valor->id],['NombreOriginal', '!=', 'noexiste.pdf']])->value('Ruta');
                        break;
                    }
                }else
                    $url = "";


                if($IdDocControl){
                    $Rutas[2] = Array(
                        "Ruta"               => $url,
                        "IdDocumentoControl" => $IdDocControl->Id,
                        "EstatusCatastro"    => $IdDocControl->EstatusCatastro,
                        "EstatusTercero"     => $IdDocControl->EstatusTercero,
                        "DiasRestantes"      => $valor->DiasRestantes,
                        "FechaVencimiento"   => $valor->FechaVencimiento,
                        "Nombre"             => $NombreDocumento,
                        "IdCotizacion"       => $valor->id
                    );
                }
            }

            if($valor->DocumentoRuta=='Padr_onCatastralConstanciaNoAdeudoOK.php'){
                $name = 'ConstanciaNoAdeudoPredial';

                $IdDocControl = DB::table('Padr_onCatastralTramitesISAINotariosDocumentos')
                    ->select('Id', 'EstatusCatastro', 'EstatusTercero')
                    ->where('IdTipoDocumento', '3')->where('ControlVersion', '1')->where('IdTramite', $IdTramite)
                    ->orderByDesc('Id')->first();

                $NombreDocumento = DB::table('TipoDocumentoTramiteISAI')->where('Id','3')->value('Nombre');

                #$url = CelaRepositorio::where("Tabla", $name)->where("idTabla", $valor->idContabilidad . $valor->id )->value("Ruta");

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
                                $url = $urls[0]->Ruta;
                            else
                                $url = CelaRepositorio::select('Ruta')->where([["Tabla", $name], ["idTabla", $valor->idContabilidad.$valor->id],['NombreOriginal', '!=', 'noexiste.pdf']])->value('Ruta');
                                #$url = CelaRepositorio::where([["Tabla", $name],["idTabla", $valor->idContabilidad.$valor->id]])->first()->value('Ruta');
                        break;
                    }
                }else
                    $url = "";

                if($IdDocControl){
                    $Rutas[1] = Array(
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

    public static function obtenerCampoRequerido(Request $request){
        $cliente=$request->Cliente;
        $campo=$request->Campo;
        $tabla=$request->Tabla;
        $condiciones=$request->Condiciones;
        Funciones::selecionarBase($cliente);

        $Campo=Funciones::ObtenValor("select ".$campo." from ".$tabla." ".$condiciones,$campo);
        return response()->json([
        'success' => 1,
        'Campo' => $Campo
        ], 200);
    }

    public static function obtenerCamposRequerido(Request $request){
        $cliente=$request->Cliente;
        $campos=$request->Campos;
        $tabla=$request->Tabla;
        $condiciones=$request->Condiciones;
        Funciones::selecionarBase($cliente);

        $Campo=DB::SELECT("select ".$campos." from ".$tabla ." ".$condiciones);
        return response()->json([
        'success' => 1,
        'Campos' => $Campo
        ], 200);
    }

    public static function  obtenerRegimen(Request $request){

        $idRegimen=$request->IdRegimen;
        $cliente=$request->Cliente;


        Funciones::selecionarBase( $cliente);
        $DatosFiscales = DB::table('RegimenFiscal')
        ->orderBy('Descripci_on',"asc")
       ->select("id","Descripci_on","Clave")

       ->get();
       return response()->json([
        'success' => '1',
        'datosFiscales'=>$DatosFiscales
    ], 200);
   }

   public static function obtenerUsoCFDI(Request $request){
    $cliente=$request->Cliente;
    Funciones::selecionarBase( $cliente);
    $UsoCFDI = DB::table('Catalogo_UsoCFDI')->
        orderBy('Nombre',"asc")->
        select("id","id","Nombre","Clave")
        ->get();

    return response()->json([
        'success' => '1',
        'UsoCFDI'=>$UsoCFDI
        ], 200);
    }

   public static function  obtenerDatosTransaccion(Request $request){

    $idRegimen=$request->IdRegimen;
    $cliente=$request->Cliente;


    Funciones::selecionarBaseRemoto();

    return DB::connection()->getDataBaseName();

    $DatosFiscales = DB::select('select * from transacciones');

    return response()->json([
    'success' => '1',
    'datosFiscales'=>$DatosFiscales
], 200);
}

   public static function  registrarNuevoContribuyente(Request $request){

    $cliente=$request->Cliente;
    $datos=$request->Datos;
    Funciones::selecionarBase( $cliente);
    parse_str($datos, $searcharray);

    DB::table('DatosFiscales')->insert([
        ['id' => null,
        'RFC' => $searcharray['rfcContribuyenteNuevo'],
        'NombreORaz_onSocial'=>$searcharray['razonSocialContribuyenteNuevoDatosFiscales'],
        'PersonalidadJur_idica'=>$searcharray['selectTipoPersonaContribuyenteNuevo'],
        'Pa_is' => $searcharray['selectPaisContribuyenteNuevo'],
        'EntidadFederativa'=>$searcharray['selectEntidadFederativaContribuyenteNuevo'],
        'Municipio'=>$searcharray['selectMunicipioContribuyenteNuevo'],
        'Localidad' => $searcharray['selectLocalidadContribuyenteNuevo'],
        'Colonia'=>$searcharray['coloniaContribuyenteNuevo'],
        'Calle'=>$searcharray['calleContribuyenteNuevo'],
        'N_umeroInterior' => $searcharray['numeroInteriorContribuyenteNuevo'],
        'N_umeroExterior'=>$searcharray['numeroExteriorContribuyenteNuevo'],
        'C_odigoPostal'=>$searcharray['codigoPostalContribuyenteNuevo'],
        'Referencia' => $searcharray['referenciaContribuyenteNuevoDatosFiscales'],
        'R_egimenFiscal'=>$searcharray['regimenFiscalContribuyenteNuevoDatosFiscales'],
        'CorreoElectr_onico'=>$searcharray['correoContribuyenteNuevo']
        ]
    ]);
    $idDatosFiscales= DB::getPdo()->lastInsertId();


    DB::table('Contribuyente')->insert([
        ['id' => null,
        'Rfc' => $searcharray['rfcContribuyenteNuevo'],
        'Curp'=>$searcharray['curpContribuyenteNuevo'],
        'Nombres'=>$searcharray['nombreContribuyenteNuevo'],
        'ApellidoPaterno'=>$searcharray['apContribuyenteNuevo'],
        'ApellidoMaterno'=>$searcharray['amContribuyenteNuevo'],
        'PersonalidadJur_idica'=>$searcharray['selectTipoPersonaContribuyenteNuevo'],
        'NombreComercial'=>$searcharray['nombreComercialContribuyenteNuevo'],
        'RepresentanteLegal'=>$searcharray['representanteLegalContribuyenteNuevo'],
        'Tel_efonoParticular'=>$searcharray['telefonoParticularContribuyenteNuevo'],
        'Tel_efonoCelular'=>$searcharray['telefonoCelularContribuyenteNuevo'],
        'CorreoElectr_onico'=>$searcharray['correoContribuyenteNuevo'],
        'Pa_is_c' => $searcharray['selectPaisContribuyenteNuevo'],
        'EntidadFederativa_c'=>$searcharray['selectEntidadFederativaContribuyenteNuevo'],
        'Municipio_c'=>$searcharray['selectMunicipioContribuyenteNuevo'],
        'Localidad_c' => $searcharray['selectLocalidadContribuyenteNuevo'],
        'Colonia_c'=>$searcharray['coloniaContribuyenteNuevo'],
        'Calle_c'=>$searcharray['calleContribuyenteNuevo'],
        'N_umeroInterior_c' => $searcharray['numeroInteriorContribuyenteNuevo'],
        'N_umeroExterior_c'=>$searcharray['numeroExteriorContribuyenteNuevo'],
        'C_odigoPostal_c'=>$searcharray['codigoPostalContribuyenteNuevo'],
        'DatosFiscales' =>$idDatosFiscales,
        'Cliente'=>$cliente,

        ]
    ]);
    $idContribuyente= DB::getPdo()->lastInsertId();


   return response()->json([
    'success' => '1',
    'idContribuyente'=>$idContribuyente,

], 200);
}



public function ValidarPredioNoAdeudo(Request $request) {

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
        ( SELECT COUNT(id) FROM Padr_onCatastralHistorial pch  WHERE pch.Padr_onCatastral = pc.id AND pch.`Status` IN (0,1) ".$DeFecha." AND CONCAT(pch.A_no,pch.Mes) <=".$AnioActual.$Meses[$MesActual] ." and  DAY(NOW())>15 ) as TotalNoPagadas,
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


public static function  existePagoSUINPAC(Request $request){

    $cliente=$request->Cliente;
    $idPadron=$request->IdPadron;
    $tipo=$request->Tipo;

    Funciones::selecionarBase( $cliente);

    $existePago= DB::select("SELECT IF (( SELECT COUNT( cac.id ) FROM ConceptoAdicionalesCotizaci_on cac WHERE cac.Cotizaci_on = c.id AND cac.Estatus =-1 )> 0
                            AND ( SELECT COUNT( cac.id ) FROM ConceptoAdicionalesCotizaci_on cac WHERE cac.Cotizaci_on = c.id AND cac.Estatus = 1 )> 0,1,0) AS existePago
                            FROM Cotizaci_on c INNER JOIN ConceptoAdicionalesCotizaci_on cac ON ( c.id = cac.Cotizaci_on ) INNER JOIN PagoTicket pt ON ( cac.Pago = pt.Pago ) INNER JOIN Pago p ON(p.id=pt.Pago) INNER JOIN Padr_onCatastralHistorial pch ON(c.id=pch.idCotizacion)
                            WHERE c.Tipo IN (".$tipo." ) AND (c.Padr_on = ".$idPadron."   OR c.Padr_on=(SELECT CuentaPadre FROM Padr_onCatastral WHERE id=".$idPadron." ) OR c.Padr_on=(SELECT idPadreSubdivision FROM Padr_onCatastral WHERE id=".$idPadron." )) AND pch.A_no='".DATE('Y')."' AND pch.`Status` IN(2,3) #AND YEAR(c.Fecha)=
                            GROUP BY c.Padr_on");

    if ($idPadron=='499086') {
        $existePago=array(array("existePago"=>1),"length"=>1);
    }
        
    
   return response()->json([
    'success' => '1',
    'existePagoSUINPAC'=>$existePago
    ], 200);
}

public static function ajaxsFuntionsAPI(Request $request){
    $cliente=$request->Cliente;
    $opcion=$request->Opcion;
    $idPadron=$request->IdPadron;

    Funciones::selecionarBase($cliente);

    $url = 'https://suinpac.piacza.com.mx/PruebasLogsMario.php';
    $dataForPost = array(
            'Cliente'=> [
            "Cliente"=>$cliente,
            "Padron"=>$idPadron,
            "Opcion"=>$opcion,

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
    $result=json_decode($result,true);
    return response()->json([
        'success' => 1,
        'respuesta'=> $result['res']
    ], 200);
}




public static function  testAPI(Request $request){
    return response()->json([
        'success' => '1'
        ], 200);
}

//nuevo método para pago referenciado

    public function ObtenerDatosPagoReferenciado(Request $request)
    {
        #return $request;
        $cliente = $request->Cliente;
        #$idCotizacion = $request->IdCotizacion;
        $idPadron = $request->Padron;
        $Referencia = $request->Referencia;
        $IdInicial = $request->IdInicial;

        $correo = $request->correo;
        $telefono = $request->telefono;
        $Tipo_Pago = $request->Tipo_Pago;
        $cotizacion = $request->cotizacion;
        
        $nombre = $request->nombre;

        /*
        ---adicional---
        nombre:nombre,
                    correo:correo,
                    telefono:telefono,
                    Tipo_Pago:Tipo_Pago,
                    cotizacion:cotizacion

        */

        Funciones::selecionarBase($cliente);
        $url = '';
        if($cliente == "40"){//DEFAULT:==40
            #$url = 'https://suinpac.com//PagoReferenciadoAguaTestV2.php';
            $url = 'https://suinpac.com//PagoReferenciadoAguaTestV2copiCarlos200210709.php';
        }else{
            if($cliente == 20){
                $url = 'https://suinpac.com//PagoReferenciadoTestManolo.php';
            }else{
                $url = 'https://suinpac.com//PagoReferenciadoTestRamon.php';
            }
        }

        #$url = 'https://suinpac.piacza.com.mx/PlantillasHTML/Plantillas/PagoReferenciadoTestL.php';


        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                #"IdCotizacion"=>$idCotizacion,
                "idPadron"=>$idPadron,
                "Referencia"=>$Referencia,
                "IdInicial"=>$IdInicial,
                "correo"=>$correo,
                "telefono"=>$telefono,
                "Tipo_Pago"=>$Tipo_Pago,
                "cotizacion"=>$cotizacion,
                
                "nombre"=>$nombre
        
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
        $result = file_get_contents($url, true, $context);

        return response()->json([
            'result' => $result,
            'cliente' => $cliente,
            'DatosRecibidos' => $dataForPost,
            #'idCotizaciones' => $idCotizacion
        ], 200);

    }
//termina
    //agregue 23 de Octubre 2020 

    public  static function obtenerVariablesDeServicio(Request $request){
        $cliente=$request->Cliente;
        $campoBusqueda=$request->CampoBusquedaServicio;
        Funciones::selecionarBase($cliente);
        $datosServicio=DB::select("SELECT idBanco, VPOS, Referenciado, idExpress, icono
                                    FROM suinpac_general.ServiciosEnLinea
                                WHERE (idServicio =".$campoBusqueda." OR tipoServicio=".$campoBusqueda.") AND idCliente =".$cliente ." ORDER BY idBanco");
        return response()->json([
            'success' => '1',
            'datosServicio' => $datosServicio,
        ]);
    }

    public  static function obtenerNombreCortoCliente(Request $request){
        $cliente=$request->Cliente;

        Funciones::selecionarBase($cliente);
        $nombreCorto=DB::select("SELECT nombreCorto
    FROM
        suinpac_general.Cliente
    WHERE
        id =".$cliente);

        return response()->json([
            'success' => '1',
            'nombreCorto' => $nombreCorto,
        ]);
    }

    public function getLogoBanco(Request $request)
    {
        $cliente = $request->Cliente;
        $icono = $request -> idRepositorio;
        Funciones::selecionarBase($cliente);
        $urlLogo = CelaRepositorioC::where([
            ['CelaRepositorioC.idRepositorio', $icono],
        ])
            ->value('Ruta');

        return response()->json([
            'success' => '1',
            'url'=>$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".$urlLogo
        ], 200);

    }

    public  static function obtenerBancosAPagar(Request $request){
        $cliente=$request->Cliente;

        Funciones::selecionarBase($cliente);
        $bancos=$cliente;
/*
        $bancos=DB::select("SELECT idBanco
    FROM
        suinpac_40.ServiciosEnLinea

    WHERE

        idCliente =".$cliente."
    GROUP BY
        idBanco");*/

        return response()->json([
            'success' => '1',
            'bancos' => $bancos,
        ],200);
    }

    //obtener pagos referenciados

    public  static function obtenerPagosReferenciados(Request $request){
        $cliente=$request->Cliente;

        Funciones::selecionarBase($cliente);

        $consultaPagosReferenciados= "Select ClaveReferencia, Importe from DatosPagosReferenciados";

        $resultadoPagosReferenciados=DB::select($consultaPagosReferenciados);

        return response()->json([
            'success' => '1',
            'pagosReferenciados' => $resultadoPagosReferenciados,
        ]);
    }

}
