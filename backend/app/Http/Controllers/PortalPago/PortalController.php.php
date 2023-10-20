<?php

namespace App\Http\Controllers\PortalPago;


use DateTime;
use App\Cliente;
use App\Funciones;
use App\FuncionesCaja;
use App\Modelos\PadronAguaLectura;
use App\Modelos\PadronAguaPotable;
use App\ModelosNotarios\Observaciones;
use App\Libs\Wkhtmltopdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use \Illuminate\Support\Facades\Config;

class PortalController extends Controller
{
    /**
     * ! Se asigna el middleware  al costructor
     */
    public function __construct()
    {
        #$this->middleware( 'jwt', ['except' => ['getToken']] );
    }

    public function existe(Request $request)
    {
        return $request->input('Cuenta');
    }

    public function pruebaConexion(Request $request)
    {
        $cliente = $request->cliente;
        $database = $request->database;
        /*if ($database == 'piacza_suinpac')$database = 'mysql';*/

        if ($database != 'piacza_suinpac') {
            Config::set('database.connections.mysql.database', $database);
            DB::purge('mysql');
        }
        #dd( DB::connection()->getDatabaseName() );
        #dd (DB::connection()->getPdo());
    }

    public function existeCuenta(Request $request)
    {
        $cuenta = $request->Cuenta;
        $cliente = $request->Cliente;
        $mes = $request->Mes;
        $a_no = $request->A_no;
        $total = $request->Total;

        #return $request;
        Funciones::selecionarBase($cliente);
    
        $padron = PadronAguaPotable::select("id", "Sector", "ContratoVigente", "ContratoAnterior", "Cuenta", "Medidor", "Domicilio", "Ruta", "Giro", "Estatus",
            DB::raw("COALESCE ( Consumo, 0.00 ) AS Consumo"),
            DB::raw("(SELECT Nombre FROM Municipio WHERE Municipio.id=Padr_onAguaPotable.Municipio ) as Municipio"),
            DB::raw("(SELECT Nombre FROM Localidad WHERE Localidad.id = Padr_onAguaPotable.Localidad) AS Localidad"),
            DB::raw("(SELECT Rfc FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) AS Rfc"),
            DB::raw("(SELECT Concepto FROM TipoTomaAguaPotable WHERE TipoTomaAguaPotable.id = Padr_onAguaPotable.TipoToma ) AS TipoToma"),
            DB::raw("(SELECT Descripci_on FROM M_etodoCobroAguaPotable WHERE M_etodoCobroAguaPotable.id = Padr_onAguaPotable.M_etodoCobro ) AS M_etodoCobro2"),
            DB::raw("(SELECT COALESCE ( NombreComercial, NULL ) FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) AS NombreComercial"),
            DB::raw("(SELECT RFC FROM DatosFiscales WHERE DatosFiscales.id = ( SELECT DatosFiscales FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) ) AS RFCDF"),
            DB::raw("(SELECT COALESCE ( CONCAT_WS( ' ', ApellidoPaterno, ApellidoMaterno, Nombres ), NULL ) FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) AS Contribuyente"),
            DB::raw("(SELECT COALESCE ( NombreORaz_onSocial, NULL ) FROM DatosFiscales WHERE DatosFiscales.id = ( SELECT DatosFiscales FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) ) AS DatosFiscales")
            )
            ->where('Cliente', $cliente)
            ->where(DB::raw('CAST(ContratoVigente AS UNSIGNED)'), $cuenta)
            ->first();
        if ($padron) {

            $lectura = PadronAguaLectura::where('Padr_onAgua', $padron->id)->where('A_no', $a_no)->where('Mes', $mes)->first();
            #return $lectura;
            if ($lectura) {
                if ($lectura->Status == 2) {
                    #$importe = number_format(Funciones::VerificarExistenciaCuentaAgua($cliente, $padron->id, $a_no, $mes, 3), 2);
                    $importe = number_format(Funciones::obtieneDatosLectura($lectura->id, 2), 2);
                    #return $importe;
                    #return $total . ' = ' . $importe;
                } else {
                    $importe = number_format(Funciones::VerificarExistenciaCuentaAgua($cliente, $padron->id, $a_no, $mes, 2), 2);
                    #return $total . ' == ' . $importe;
                }
               
                if ($total == $importe) {
                    return response()->json([
                        'success' => '1',
                        'result' => $padron,
                    ]);
                } else {
                    return response()->json([
                        'success' => '0',
                        'importe'=>$importe
                    ], 200);
                }
            } else {
                return response()->json([
                    'success' => '0',
                ], 200);
            }

        } else {
            return response()->json([
                'success' => '0',
            ], 200);
        }
    }

    public function adeudo(Request $request)
    {
        $idPadron = $request->idPadron;
        $cliente = $request->Cliente;

        $totalAdeudo = 0;
        Funciones::selecionarBase($cliente);
        $contribuyente = PadronAguaPotable::select(
                DB::raw( "(SELECT COALESCE ( CONCAT_WS( ' ', ApellidoPaterno, ApellidoMaterno, Nombres ), NULL ) FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) AS Contribuyente")
            )
            ->where('Cliente', $cliente)
            ->where('id', $idPadron)
            ->first();
        #return $contribuyente;
        $lecturas = PadronAguaLectura::select('id',
            DB::raw("COALESCE((SELECT Concepto FROM TipoTomaAguaPotable WHERE TipoTomaAguaPotable.id = TipoToma), '') AS TipoToma"),
            'FechaLectura', 'A_no', 'Mes',
            DB::raw("(SELECT Nombre FROM Mes WHERE Mes.id = Mes) AS MesLectura"),
            'LecturaAnterior', 'LecturaActual', 'Consumo', 'Tarifa', 'Observaci_on', 'Status', 'EstadoToma'
            )
            ->where('Padr_onAgua', $idPadron)
            ->where('Status', '1')
            ->orderBy('A_no', 'DESC')
            ->orderBy('Mes', 'DESC')
            ->get();
            
        foreach($lecturas as $registro){
            $registro1 = Funciones::VerificarExistenciaCuentaAgua($cliente, $idPadron, $registro->A_no, $registro->Mes, 1);
            $totalAdeudo += $registro1;
        }
        //return "Respuesta ".json_encode($array)."i= ".$i;

        #return $totalAdeudo;

        return response()->json([
            'success' => '1',
            'contribuyente' => $contribuyente['Contribuyente'],
            'adeudo' => $totalAdeudo
        ], 200);
    }

    public function historial(Request $request)
    {
        $idPadron = $request->idPadron;
        $a_no = $request->A_no;
        $cliente= $request->Cliente;
        #return $request;
        Funciones::selecionarBase($cliente);

        if (!isset($a_no) || $a_no == '') {
            $a_no = '2019';
        } elseif ($a_no == 'Todo') {
            $a_no = 0;
        }

        if ($a_no == 0) {
            $historial = PadronAguaLectura::select('id',
                DB::raw("COALESCE((SELECT Concepto FROM TipoTomaAguaPotable WHERE TipoTomaAguaPotable.id = TipoToma), '') AS TipoToma"),
                'FechaLectura', 'A_no', 'Mes',
                DB::raw("(SELECT Nombre FROM Mes WHERE Mes.id = Mes) AS MesLectura"),
                'LecturaAnterior', 'LecturaActual', 'Consumo', 'Tarifa', 'Observaci_on', 'Status', 'EstadoToma'
            )
                ->where('Padr_onAgua', $idPadron)
            #->where('Status', '1')
                ->orderBy('A_no', 'DESC')
                ->orderBy('Mes', 'DESC')
                ->get();
        } else {
            $historial = PadronAguaLectura::select('id',
                    DB::raw("COALESCE((SELECT Concepto FROM TipoTomaAguaPotable WHERE TipoTomaAguaPotable.id = TipoToma), '') AS TipoToma"),
                    'FechaLectura', 'A_no', 'Mes',
                    DB::raw("(SELECT Nombre FROM Mes WHERE Mes.id = Mes) AS MesLectura"),
                    'LecturaAnterior', 'LecturaActual', 'Consumo', 'Tarifa', 'Observaci_on', 'Status', 'EstadoToma'
                )
                ->where('Padr_onAgua', $idPadron)
                ->where('A_no', '>=', $a_no)
                #->where('Status', '1')
                ->orderBy('A_no', 'DESC')
                ->orderBy('Mes', 'DESC')
                ->get();
            
        }
        
        $data = "";

        foreach ($historial as $registro) {
            if ($registro->Status == 2) {
                #$registro->Tarifa = $this->obtieneDatosLectura($registro->id);
                $registro->Tarifa = number_format(Funciones::obtieneDatosLectura($registro->id, 1), 2);
            } else {
                $registro->Tarifa = number_format(Funciones::VerificarExistenciaCuentaAgua('32', $idPadron, $registro->A_no, $registro->Mes, 1), 2);
            }

            if ($registro->Status == 2) {
                $registro->Status = 'Pagado';
            } elseif ($registro->Status == 1) {
                $registro->Status = 'Cotizado';
            } else {
                $registro->Status = 'No Cotizado';
            }

            $registro->EstadoToma = $this->estadoAgua($registro->EstadoToma);
        }

        #return $data;

        return response()->json([
            'success' => '1',
            'historial' => $historial,
        ]);
    }

    public function estadoAgua($estadoToma)
    {
        switch ($estadoToma) {
            case 1:
                $estado = 'Activo';
                break;
            case 2:
                $estado = 'Cortado';
                break;
            case 3:
                $estado = 'Baja Temporal';
                break;
            case 4:
                $estado = 'Baja Permanente';
                break;
            case 5:
                $estado = 'Inactivo';
                break;
            case 6:
                $estado = 'Nueva';
                break;
            case 9:
                $estado = 'Sin toma';
                break;
            default:
                $estado = '';
                break;
        }

        return $estado;
    }

    public function pruebas()
    {
        $res = Funciones::num2letras('593', 0, 0);
        return $res;
    }

    public function recibo(Request $request)
    {
        $cliente = $request->Cliente;
        $idPadron = $request->Padron;
        $a_no = $request->A_no;
        $mes = $request->Mes;
        Funciones::selecionarBase($cliente);

        $ruta = Funciones::generaReciboOficialCapaz($cliente, $idPadron, $a_no, $mes);
        return $ruta;

        /*$ruta = Funciones::generaReciboOficialIndividual($idPadron, $idLectura, $cliente);
        
        if (isset($ruta) && $ruta != "") {
            return response()->json([
                'success' => '1',
                'ruta' => $ruta,
            ], 200);
        } else {
            return response()->json([
                'success' => '0',
                'mensaje' => 'Error al generar el archivo.',
            ], 400);
        }*/
    }

    public function reciboIndividual(Request $request){
        $cliente = $request->Cliente;
        $idPadron = $request->Padron;
        $a_no = $request->A_no;
        $mes = $request->Mes;
        Funciones::selecionarBase($cliente);

        $contribuyente = PadronAguaPotable::select(
                DB::raw( "(SELECT COALESCE ( CONCAT_WS( ' ', ApellidoPaterno, ApellidoMaterno, Nombres ), NULL ) FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) AS Contribuyente")
            )->where('Cliente', $cliente)->where('id', $idPadron)->first();
        
        $mesCobro = PadronAguaLectura::where('Padr_onAgua', $idPadron)->orderByDesc('A_no')->orderByDesc('Mes')->value('Mes');
        
        $lectura = PadronAguaLectura::where('Padr_onAgua', $idPadron)->where('A_no', $a_no)->where('Mes', $mes)->first();
        

        $idLectura = $lectura->id;
        
        $estaPagado = FALSE;

        if (  $lectura->Status == 1 )
            $estaPagado = FALSE;

        if (  $lectura->Status == 2 )
            $estaPagado = TRUE;

        $adeudos = "";
        $adeudo = PadronAguaLectura::select( DB::raw("COUNT(id) AS adeudo") )
                ->where("Padr_onAgua", $idPadron)
                ->where('Status',  '1')
                ->value('adeudo');

        if ($adeudo && $adeudo > 0) {
            if ($adeudo >= 2) {
                $adeudo = $adeudo - 1;
                $adeudos = "Meses adeudo: " . $adeudo;
            }
        }

        $cuentasPapas = DB::table('Padr_onAguaPotable AS p')
            ->select( DB::raw("GROUP_CONCAT(p.id) AS CuentasPapas") ) 
            ->where("p.id", DB::raw("(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1)") )
            ->where("p.Cliente", $cliente)->value('CuentasPapas');

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

        $anomalia = PadronAguaLectura::select("pac.descripci_on as descripci_on") 
            ->join("Padr_onAguaCatalogoAnomalia as pac", 'Padr_onDeAguaLectura.Observaci_on', '=', 'pac.id') 
            ->where("Padr_onDeAguaLectura.id", $idLectura)->value('descripci_on');

        $tieneAnomalia = TRUE;

        if ($anomalia == '') {
            $anomalia = '';
            $tieneAnomalia = FALSE;
        }

        $DatosPadron = PadronAguaPotable::select(
            'd.RFC', 'Padr_onAguaPotable.Ruta', 'Padr_onAguaPotable.Lote',
            'Padr_onAguaPotable.Cuenta', 'Padr_onAguaPotable.Sector', 'Padr_onAguaPotable.Manzana',
            'Padr_onAguaPotable.Colonia', 'Padr_onAguaPotable.Medidor', 'Padr_onAguaPotable.Diametro',
            'Padr_onAguaPotable.TipoToma', 'Padr_onAguaPotable.Domicilio', 'Padr_onAguaPotable.SuperManzana',
            'Padr_onAguaPotable.ContratoVigente', 'd.NombreORaz_onSocial',
            DB::raw("COALESCE ( c.NombreComercial, NULL ) AS NombreComercialPadron"),
            DB::raw("( SELECT Descripci_on FROM Giro g WHERE g.id = Padr_onAguaPotable.Giro ) AS Giro"),
            DB::raw("( SELECT COALESCE ( Nombre, '' ) FROM Municipio m WHERE m.id = d.Municipio ) AS Municipio"),
            DB::raw("( SELECT COALESCE ( Nombre, '' ) FROM EntidadFederativa e WHERE e.id = d.EntidadFederativa ) AS Estado"),
            DB::raw("COALESCE ( CONCAT( c.Nombres, ' ', c.ApellidoPaterno, ' ', c.ApellidoMaterno ), NULL ) AS ContribuyentePadron")
        )
        ->join('Contribuyente as c','c.id','=','Padr_onAguaPotable.Contribuyente')
        ->join('DatosFiscales as d','d.id','=','c.DatosFiscales')
        ->where('Padr_onAguaPotable.id', $idPadron)
        ->first();

        if (!$DatosPadron->ContribuyentePadron || empty($DatosPadron->ContribuyentePadron) || strlen($DatosPadron->ContribuyentePadron) <= 2)
            $contribuyente = utf8_decode($DatosPadron->NombreComercialPadron);
        else
            $contribuyente = utf8_decode($DatosPadron->ContribuyentePadron);

        if (isset($DatosPadron->TipoToma) && $DatosPadron->TipoToma != '')
            $consultaToma = DB::table('TipoTomaAguaPotable')->where('id', $DatosPadron->TipoToma)->value('Concepto');
        else
            $consultaToma = 'NULL';

        if ( !$consultaToma || $consultaToma == '')
            $tipoToma = '0';
        else
            $tipoToma = utf8_decode($consultaToma);

        $folio = PadronAguaPotable::where('id', $idPadron)->value('Cuenta');

        if ($estaPagado) {
            $DatosParaRecibo = Funciones::obtieneDatosLectura($idLectura, 2);
            $mesActual = PadronAguaLectura::select('Mes')->where('id', $idLectura)->value('Mes');

            if ($tieneAnomalia || $esPapa) {
                $lecturaAnterior = $DatosParaRecibo['LecturaAnterior'];
                $lecturaActual   = $DatosParaRecibo['LecturaActual'];
                $lecturaConsumo  = $DatosParaRecibo['Consumo'];
            } else {
                $lecturaAnterior = $DatosParaRecibo['LecturaAnterior'];
                $lecturaActual   = $DatosParaRecibo['LecturaActual'];
                $lecturaConsumo  = $lecturaActual - $lecturaAnterior;
            }
        }else{
            $DatosParaRecibo = $lectura;
            
            if ($tieneAnomalia || $esPapa) {
                $lecturaAnterior = $DatosParaRecibo->LecturaAnterior;
                $lecturaActual   = $DatosParaRecibo->LecturaActual;
                $lecturaConsumo  = $DatosParaRecibo->Consumo;
            } else {
                $lecturaAnterior = $DatosParaRecibo->LecturaAnterior;
                $lecturaActual   = $DatosParaRecibo->LecturaActual;
                $lecturaConsumo  = $lecturaActual - $lecturaAnterior;
            }
        }

        $mesCobro = PadronAguaPotable::select( DB::raw('LPAD(pl.Mes, 2, 0 ) as MesEjercicio') )
            ->join('Padr_onDeAguaLectura as pl','Padr_onAguaPotable.id','=','pl.Padr_onAgua')
            ->join('TipoTomaAguaPotable as t','t.id','=', ( ($DatosPadron->TipoToma == '' || is_null($DatosPadron->TipoToma) ) ? 'Padr_onAguaPotable' : 'pl') . '.TipoToma')
            ->where('pl.id', $idLectura)
            ->orderBy('pl.A_no', 'DESC')
            ->orderBy('pl.Mes', 'DESC')
            ->value('MesEjercicio');
        
        $a_noCobro = PadronAguaPotable::select('pl.A_no as a_noEjercicio')
            ->join('Padr_onDeAguaLectura as pl','Padr_onAguaPotable.id','=','pl.Padr_onAgua')
            ->join('TipoTomaAguaPotable as t','t.id','=', ( ($DatosPadron->TipoToma == '' || is_null($DatosPadron->TipoToma) ) ? 'Padr_onAguaPotable' : 'pl') . '.TipoToma')
            ->where('pl.id', $idLectura)
            ->orderBy('pl.A_no', 'DESC')
            ->orderBy('pl.Mes', 'DESC')
            ->value('a_noEjercicio');
            
        $diaLimite = 15;
        if( isset($DatosPadron->TipoToma) && $DatosPadron->TipoToma != '' && $DatosPadron->TipoToma == 4 ){
            $diaLimite = 5;
            $fechaLimite = $diaLimite . '/' . intval($mesCobro + 1) . '/' . date('Y');
            $fechaCorte = $diaLimite + 1 . '/' . intval($mesCobro + 1) . '/' . date('Y');
        }else{
            $fechaLimite = $diaLimite . '/' . intval($mesCobro + 1) . '/' . date('Y');
            $fechaCorte = $diaLimite + 1 . '/' . intval($mesCobro + 1) . '/' . date('Y');
        }

        $fechasPeriodo = PadronAguaLectura::select('FechaLectura')
            ->where('Padr_onAgua', $idPadron)
            ->where('Mes', '<=', $mesCobro) 
            ->where('A_no', $a_noCobro) 
            ->orderBy('id', 'DESC') 
            ->take(2)
            ->get();
    
        if (count($fechasPeriodo) == 2) {
            $periodo = date_format(new DateTime($fechasPeriodo[1]->FechaLectura), 'd/m/Y') . " a " . date_format(new DateTime($fechasPeriodo[0]->FechaLectura), 'd/m/Y');
        }

        $meses = ["ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE"];

        $DatosHistoricos = PadronAguaLectura::select('Consumo', 'Mes', 'A_no') 
            ->where('Mes', $mesCobro)
            ->where('Padr_onAgua', $idPadron)
            ->where('A_no', '<', DB::raw("DATE_FORMAT( CURDATE(), '%Y')" ) ) 
            ->orderBy('FechaLectura', 'DESC')
            ->take(3)
            ->get();

        $datosHistoricosTabla = '';
        foreach ($DatosHistoricos as $valor) {
            //$lista[] = $fila[$valor->name];
            $datosHistoricosTabla .=
                '<tr>
                    <td>' . $meses[$valor->Mes - 1] . '-' . $valor->A_no . '</td>
                    <td class="derecha">' . intval($valor->Consumo) . ' M3</td>
                </tr>';
        }
        
        /*$Cotizaciones = DB::table('Cotizaci_on')->select( DB::raw('GROUP_CONCAT(id) as Cotizaci_ones') )
            ->where('Padr_on', $idPadron)
            ->where(DB::raw('( SELECT sum( importe ) FROM ConceptoAdicionalesCotizaci_on WHERE ConceptoAdicionalesCotizaci_on.Cotizaci_on = Cotizaci_on.id AND Padre IS NULL )'), '>', '0')
            ->groupBy('Padr_on')
            ->orderBy('id', 'DESC')
            ->value('Cotizaci_ones');*/

        $Cotizaciones = DB::table('Cotizaci_on')->select( DB::raw('GROUP_CONCAT(id) as Cotizaci_ones') )
            ->where('Padr_on', $idPadron)
            ->where('Padr_on', $idPadron)
            ->where('Padr_on', $idPadron)
            ->where(DB::raw('( SELECT sum( importe ) FROM ConceptoAdicionalesCotizaci_on ca WHERE ca.Cotizaci_on = Cotizaci_on.id AND ca.Padre IS NULL AND ca.A_no ='.$a_no.' AND ca.Mes = '.$mes.')'), '>', '0')
            ->groupBy('Padr_on')
            ->orderBy('id', 'DESC')
            ->value('Cotizaci_ones');
        
        #return $Cotizaciones;

        if ($Cotizaciones == '') {
            return "Sin Cotizaciones...";
        }
        
        if( $estaPagado ) goto sinCalcular;

        $DescuentoGeneralCotizaciones = 0;
        $SaldoDescontadoGeneralTodo   = 0;
                
        $Descuentos = Funciones::ObtenerDescuentoConceptoRecibo($Cotizaciones);
        $SaldosActuales = Funciones::ObtenerDescuentoPorSaldoAnticipadoRecibo($Cotizaciones, $Descuentos['ImporteNetoADescontar'], $Descuentos['Conceptos'], $cliente);
        
        $ConsultaConceptos="SELECT c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad, 
                c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario, 
                (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional, co.A_no, COALESCE(co.Mes, '01') as Mes, ct.Tipo, c.TipoToma
            FROM ConceptoAdicionalesCotizaci_on co 
                INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
                INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )
            WHERE  co.Cotizaci_on IN( " .$Cotizaciones. ") and Estatus=0 ORDER BY co.A_no DESC, COALESCE(co.Mes, '01') DESC ,  co.id ASC ";

        $ResultadoConcepto=DB::select($ConsultaConceptos);

        setlocale(LC_TIME, "es_MX.UTF-8");
        
        $ConceptosCotizados  = '';
        $totalConcepto       = 0;
        $indexConcepto       = 0;
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

        $ActualizacionesYRecargosFunciones = Funciones::ObtenerRecargosYActualizacionesPorConceptoRecibo($RegistroConcepto->ConceptoCobro, $Cotizaciones, $cliente);
            
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

        $i = 0;
        foreach($ResultadoConcepto as $RegistroConcepto) {
            if($i != 0){
                $ActualizacionesYRecargosFunciones = Funciones::ObtenerRecargosYActualizacionesPorConceptoRecibo($RegistroConcepto->ConceptoCobro, $Cotizaciones, $cliente);
        
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
                    }else {
                        if ($RegistroConcepto->A_no == $a_noCobro && $RegistroConcepto->Mes == $mesCobro) {
                            $conceptosNombresMes[$RegistroConcepto->ConceptoCobro] = $subtotal;
                        } else {
                            $sumaConceptosA = str_replace(",", "", $sumaConceptosA);
                            $sumaConceptosA += $subtotal; //Para el consumo de meses anteriores
                        }
                    }
                }else {
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

        if($SaldoDescontadoGeneralTodo > 0 && $SaldoDescontadoGeneralTodo <= $sumaTotalFinal)
            $sumaTotalFinal=str_replace(",", "",$sumaTotalFinal)-str_replace(",", "",$SaldoDescontadoGeneralTodo)-str_replace(",", "",$sumaDescuentos);
        else if($SaldoDescontadoGeneralTodo>=$sumaTotalFinal)
            $sumaTotalFinal=0;
        else if($SaldoDescontadoGeneralTodo==0)
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

        //uno los arrays
        $array_mes    = array_merge($consumoMesActual, $adicionalesNombresMes);
        $array_otros  = array_merge($conceptosOtros, $adicionalesOtros);
        $array_rezago = array_merge($conceptosNombres, $contar);

        $totalMes       = 0;
        $totalOtros     = 0;
        $totalRezago    = 0;
        $totalFinal     = $sumaTotalFinal;
        $totalesFinales = $sumaActualizaciones + $sumaRecargos;

        $FilaConceptosTotales = "<br>";
        if (empty($array_rezago)) {
            foreach ($array_mes as $key => $value) {
                $FilaConceptosTotales .= '<tr>
                <td colspan="2">' . utf8_decode($key) . '</td>
                <td class="centrado">' . (number_format($value, 2)) . '</td>
                <td class="centrado">-</td>
                <td class="centrado">' . (number_format($value, 2)) . '</td>
            </tr>';
                $totalMes    += str_replace(",", "", $value);
            }
        } else {
            foreach ($array_mes as $key => $value) {
                $FilaConceptosTotales .= '<tr>
                <td colspan="2">' . utf8_decode($key) . '</td>
                <td class="centrado">' . (number_format(str_replace(",", "", $value), 2)) . '</td>
                <td class="centrado">' . (number_format(str_replace(",", "", $array_rezago[$key]), 2)) . '</td>
                <td class="centrado">' . (number_format(str_replace(",", "", $value) + str_replace(",", "", $array_rezago[$key]), 2)) . '</td>
            </tr>';
                $totalMes     = str_replace(",", "", $totalMes);
                $totalMes    += str_replace(",", "", $value);
                $totalRezago  = str_replace(",", "", $totalRezago);
                $totalRezago += str_replace(",", "", $array_rezago[$key]);
            }
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
                <td colspan="2">' . (substr($concepto, 0, 44)) . '</td>
                <td class="centrado"></td>
                <td class="centrado"></td>
                <td class="centrado">' . (number_format($value, 2)) . '</td>
            </tr>';
                $totalOtros = str_replace(",", "", $totalOtros);
                $totalOtros +=  str_replace(",", "", $value);
            }
        }

        $descuentos  = PadronAguaPotable::select('PrivilegioDescuento')
            ->where('id', $idPadron)
            ->where('PrivilegioDescuento', '!=', '0')
            ->value('PrivilegioDescuento');
        
        $esDescuento = FALSE;
        $descuento   = 0;
        if ($descuentos != "") {
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
                <td colspan="2">Adeudo Completo</td>
                <td class="centrado"></td>
                <td class="centrado"></td>
                <td class="centrado">' . (number_format(str_replace(",", "", $totalFinal), 2)) . '</td>
            </tr>';
            
            $totalRezago = 0;
            $totalMes = 0;
            
            goto finCalculos;
        }

        $saldo = DB::table('Padr_onAguaHistoricoAbono')->select('SaldoNuevo')->where('idPadron', $idPadron)->orderByDesc('id')->value('SaldoNuevo');

        $saldoNuevo = 0;
        if($saldo != ""){
            //Si el saldo es menor a lo que se debe
            if($saldo < $sumaSaldos){
                $saldoNuevo = 0;
            }
            #Si el saldo es mayor a lo se se debe
            if($saldo > $totalFinal){
                $saldoNuevo = $saldo - $sumaSaldos;
            }
        }

        $estaAjustado = FALSE;
        /*$decimales = 0;
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
        }*/

        #$totalFinal = intval( $totalFinal );

        if ($totalesFinales > 0) {
            $totalRezago += str_replace(",", "", $totalesFinales);
            $FilaConceptosTotales .= '<tr>
                        <td colspan="2">Actualizaciones y Recargos</td>
                        <td></td>
                        <td class="centrado">
                            ' . number_format($totalesFinales, 2) . '
                        </td>
                        <td class="centrado">
                            ' . number_format($totalesFinales, 2) . '
                        </td>
                    </tr>';
        }

        if ($estaAjustado) {
            $FilaConceptosTotales .= '<tr>
                        <td colspan="2">Redondeo
                        </td>
                        <td></td>
                        <td></td>
                        <td class="centrado">
                            ' . $ajuste . '
                        </td>
                    </tr>';
        }
    
        if ($saldo != "NULL" && $saldoNuevo > 0) {
            $FilaConceptosTotales .= '<tr>
                        <td colspan="2">Saldo disponible</td>
                        <td></td>
                        <td></td>
                        <td class="centrado">
                            ' . number_format($saldoNuevo, 2) . '
                        </td>
                    </tr>';
        }
    
        if ($sumaSaldos > 0) {
            $FilaConceptosTotales .= '<tr>
                        <td colspan="2">Aplicacion Ingresos Cobrados por Anticipado</td>
                        <td></td>
                        <td></td>
                        <td class="centrado">-
                            ' . $sumaSaldos . '
                        </td>
                    </tr>';
        }

        $descNombre = "";
        if ($esDescuento && $sumaDescuentos > 0) {
            if ($descuentos == 1)
                $descNombre = "INAPAM";

            if ($descuentos == 2)
                $descNombre = "Pensionados y Jubilados";

            $FilaConceptosTotales .= '<tr>
                        <td colspan="2">Descuento: ' . $descNombre . '</td>
                        <td></td>
                        <td></td>
                        <td  class="centrado">-
                            ' . $sumaDescuentos . '
                        </td>
                    </tr>';
        }

        #$totalFinal = str_replace(",", "", $ajusteFinal);
        $totalFinal = str_replace(",", "", $totalFinal);
        
        finCalculos:
        
        if( $estaPagado )
            $totalFinal = 0;

        if($totalFinal == 0){
            $letras = "Cero pesos M. N.";
        }else{
            $letras = utf8_decode(Funciones::num2letras($totalFinal, 0, 0) . " pesos");
            $ultimoArr = explode(".", number_format($totalFinal, 2)); //recupero lo que este despues del decimal
            $ultimo = $ultimoArr[1];
            if ($ultimo == "")
                $ultimo = "00";
            $letras = $letras . " " . $ultimo . "/100 M. N.";
        } 
    
        if( ($estaPagado && $Cotizaciones == "NULL") )
            return "";
        
        $nombreComercial = $DatosPadron["NombreORaz_onSocial"];
        if( strlen($nombreComercial) > 0 && strlen($nombreComercial) > 55 ){
            $nombreComercial = substr($nombreComercial, 0, strlen($nombreComercial) / 2) . '<br>' . substr($nombreComercial, strlen($nombreComercial) / 2, strlen($nombreComercial) );
        }

        $rutaBarcode = 'https://suinpac.piacza.com.mx/lib/barcode2.php?f=png&text=' . (isset($DatosPadron['ContratoVigente']) ? $DatosPadron['ContratoVigente'] : '') . '&codetype=Code128&orientation=Horizontal&size=40&print=false "';

        $htmlGlobal = '<style>
        .centrado {
            text-align: center;
            
        }
        .derecha {
            text-align: right;
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
        
        
        
    </style>
   
		<img  class="portada" src="'.$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".'img/capaz/reciboCapazFondo.png">
        <div class="azul"></div>
       
    <div>
    ' . ($estaPagado ? '<div> <span class="es inline-block">PAGADO</span></div>' : '') . '
        <table border="0" width="100%">
            <tr>
                <td rowspan="2" width="45%">
                    <img alt="Smiley face" height="100" width="300" src="'.$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".'img/capaz/capazlogo.png">
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
                      <span class="color-gris">Total a pagar</span><span class="marco-turquesa-derecha">' . number_format($totalFinal, 2, '.', ',') . '</span>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="centrado" >
                     <span class="color-gris">Fecha l&iacute;mite de pago</span><span style="display: block;">' . $fechaLimite . '</span>
            
                </td>
                <td colspan="2" class="centrado" >
                     <span class="color-gris">Periodo</span><span style="display: block;">' . ($estaPagado ? $auxMes[$mesActual] : $auxMes[$DatosParaRecibo["Mes"]]) . '</span>
                </td>
            </tr>
            
            <tr>
                <td  class="letras-resaltadas" colspan="3" >
                  ' . ( ( strlen($contribuyente) <= 70 ) ? $contribuyente : substr($contribuyente, 0, 75) ) .'
                </td>
                <td rowspan="2" colspan="2" align="right">
                    <span  class="color-gris marco-derecha" >N&#176; de contrato </span><span class="marco-turquesa-derecha">' . (isset($DatosPadron["ContratoVigente"]) ? intval($DatosPadron["ContratoVigente"]) : '') . '</span>
                </td>
            </tr>
            <tr> 
                    <td colspan="3" class="letras-resaltadas">
                    ' . utf8_decode(( ( strlen(utf8_decode($DatosPadron["Domicilio"]) <= 70 ) ? $DatosPadron["Domicilio"] : substr($DatosPadron["Domicilio"], 0, 75) ))) . '
                    </td>
            </tr>
            <tr>
                    <td colspan="3" class="letras-resaltadas">
                       Col. ' . (isset($DatosPadron["Colonia"]) ? strlen(utf8_decode($DatosPadron["Colonia"]) <= 70 ) ? utf8_decode($DatosPadron["Colonia"]) : substr(utf8_decode($DatosPadron["Colonia"]), 0, 75) : '<br>')  . '

                    </td>
                    <td rowspan="2" colspan="2" class="derecha" >
                        <div>
                        <img  width="220" height="30" src="' . $rutaBarcode . '" >
                        </div>
                    </td>
            </tr>
            <tr>
               <td colspan="3" class="letras-resaltadas"> '.(isset($DatosPadron["SuperManzana"]) ? 'S. Mza. ' . $DatosPadron["SuperManzana"] : '') . (isset($DatosPadron["Manzana"]) ? '&nbsp;&nbsp;&nbsp;S. Mza. ' . $DatosPadron["Manzana"] : '') . (isset($DatosPadron["Lote"]) ? '&nbsp;&nbsp;&nbsp;Lote ' . $DatosPadron["Lote"] : '').'</td>
               
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
                                <td class="sin-espacio"> <span class="marco-borde-izq"> ' . (isset($DatosPadron["Medidor"]) ? intval($DatosPadron["Medidor"]) : '') . '</span></td>
                                <td class="sin-espacio"><span class="marco-sin-borde"> ' . (isset($DatosPadron["Diametro"]) ? $DatosPadron["Diametro"] : '') . '</span></td>
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
                            <td colspan="3" class="color-gris centrado">C&aacute;lculo de su facturaci&oacute;n</td>
                        </tr>
                        
                        <tr>
                            <td colspan="2"><br></td>
                            <td  class="centrado">Mes</td>
                            <td  class="centrado" >Rezago</td>
                            <td  class="centrado">Total</td>
                        </tr>' . $FilaConceptosTotales . ' 

                        <tr>
                        <td colspan="2">Adeudo Completo</td>
                        <td class="centrado"></td>
                        <td class="centrado"></td>
                        <td class="centrado">' . (number_format(str_replace(",", "", $totalFinal), 2)) . '</td>
                        </tr>
                        <tr>
                        <td colspan="2">Adeudo Completo</td>
                        <td class="centrado"></td>
                        <td class="centrado"></td>
                        <td class="centrado">' . (number_format(str_replace(",", "", $totalFinal), 2)) . '</td>
                        </tr><tr>
                        <td colspan="2">Adeudo Completo</td>
                        <td class="centrado"></td>
                        <td class="centrado"></td>
                        <td class="centrado">' . (number_format(str_replace(",", "", $totalFinal), 2)) . '</td>
                        </tr><tr>
                        <td colspan="2">Adeudo Completo</td>
                        <td class="centrado"></td>
                        <td class="centrado"></td>
                        <td class="centrado">' . (number_format(str_replace(",", "", $totalFinal), 2)) . '</td>
                        </tr>
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
                            <td>' . (isset($DatosPadron["Sector"]) ? $DatosPadron["Sector"] : '') . '</td>
                            <td  class="centrado" >' . (isset($DatosPadron["Ruta"]) ? $DatosPadron["Ruta"] : '') . '</td>
                            <td  class="centrado">' . intval($folio) . '</td>
                        </tr>
                        <tr>
                            <td colspan="2"><br></td>
                            <td>Uso:</td>
                            <td colspan="2">' . $DatosPadron["Giro"] . '</td>
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
                                                    <td>RFC: ' . (isset($DatosPadron["RFC"]) ? $DatosPadron["RFC"] : '') . '</td>
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
                    <span class="color-gris">Per&iacute;odo</span><span class="marco-sin-color">' . ($estaPagado ? $auxMes[$mesActual] : $auxMes[$DatosParaRecibo["Mes"]]) . '</span>
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
                                                ' . utf8_decode(( ( strlen(utf8_decode($DatosPadron["Domicilio"]) <= 70 ) ? $DatosPadron["Domicilio"] : substr($DatosPadron["Domicilio"], 0, 75) )))  . '
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan=3>
                                                Col. ' . (isset($DatosPadron["Colonia"]) ? strlen(utf8_decode($DatosPadron["Colonia"]) <= 70 ) ? utf8_decode($DatosPadron["Colonia"]) : substr(utf8_decode($DatosPadron["Colonia"]), 0, 75) : '<br>')  . '

                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan=3> '.(isset($DatosPadron["SuperManzana"]) ? 'S. Mza. ' . $DatosPadron["SuperManzana"] : '') . (isset($DatosPadron["Manzana"]) ? '&nbsp;&nbsp;&nbsp;S. Mza. ' . $DatosPadron["Manzana"] : '') . (isset($DatosPadron["Lote"]) ? '&nbsp;&nbsp;&nbsp;Lote ' . $DatosPadron["Lote"] : '').'</td>
                                        </tr>
                                        <tr>
                                            <td >Sector</td>
                                            <td class="centrado">Ruta</td>
                                            <td class="centrado">Prog.</td>
                                        </tr>
                                        <tr>
                                            <td >' . (isset($DatosPadron["Sector"]) ? $DatosPadron["Sector"] : '') . '</td>
                                            <td class="centrado">' . (isset($DatosPadron["Ruta"]) ? $DatosPadron["Ruta"] : '') . '</td>
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
                                                 <span  class="color-gris marco-derecha">N&#176; de contrato </span><span class="marco-turquesa-derecha">' . (isset($DatosPadron["ContratoVigente"]) ? intval($DatosPadron["ContratoVigente"]) : '') . '</span>
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
   <!--de aqui hacia arriba -->

    </div>
';
    //return $htmlGlobal;
     $C=`<h1>Hola</h1>`;
        include( app_path() . '/Libs/Wkhtmltopdf.php' );
        try {
            $nombre = uniqid() . "_" . $idLectura;
            #$wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'page_width'=>120,'page_height'=>200,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
            $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'margins' => ['top' => 0, 'left' => 0]));
            $wkhtmltopdf->setHtml($C);
            //$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, $nombre.".pdf");		
            $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $nombre . ".pdf");
           // return "repositorio/temporal/" . $nombre . ".pdf";
            return response()->json([
                'success' => '1',
                'ruta' => "repositorio/temporal/" . $nombre . ".pdf",
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => '0',
                'error' => 'Hubo un error al generar el PDF' . $e->getMessage() 
            ], 200);
        }



        













        $ResultadoConcepto = DB::select("SELECT
                ct.Importe as Pago, ct.id ,
                ( SELECT Descripci_on FROM RetencionesAdicionales WHERE RetencionesAdicionales.id = ct.Adicional ) AS Adicional
            FROM
                Cotizaci_on c 
                INNER JOIN ConceptoAdicionalesCotizaci_on ct ON(ct.Cotizaci_on = c.id AND ct.Padr_on=" . $idPadron . "  AND ct.Estatus=0)
                INNER JOIN ConceptoCobroCaja co ON ( ct.ConceptoAdicionales = co.id  )
            WHERE c.Padr_on=" . $idPadron . " AND  ct.A_no = $a_no AND ct.Mes = $mes AND
                c.Tipo=9 AND
                c.Cliente= $cliente");
        #return $ResultadoConcepto;

        $adeudo = 0;
        $adeudoString = '';
        $recargos = 0;
        $totalFinal = 0;

        $conceptosMes = array(); #Conceptos del mes
        
        foreach($ResultadoConcepto as $RegistroConcepto){
            $adeudo += floatval($RegistroConcepto->Pago);
            if($RegistroConcepto->Adicional == "")
                $conceptosMes['Consumo'] = number_format($RegistroConcepto->Pago, 2);
            else 
                $conceptosMes[$RegistroConcepto->Adicional] = number_format($RegistroConcepto->Pago, 2);

            $adeudoString .= $RegistroConcepto->Pago . ' - ';
            $recargos += floatval($this->ObtenerRecargosYActualizacionesAguaPortal($cliente, $idPadron, $RegistroConcepto->id) );
        }

        if($recargos > 0){
            $conceptosMes['Actualizaciones Y Recargos'] = number_format($recargos, 2);
            $totalFinal = number_format($adeudo + $recargos, 2);
        }else
            $totalFinal = number_format($adeudo, 2);

        $decimales = 0;
        $estaAjustado = FALSE;
        $exp = '';
        if ($totalFinal > 0) {
            #En caso de que el total sea decimal - Se toma el numero despues del punto
            $exp = explode(".", $totalFinal);
            #Se asigna el numero tomado
            if(isset($exp[1])){
                $decimales = "0." . $exp[1];
                $estaAjustado = TRUE;
            }else
                $decimales = "0";
        }
        
        if ( $estaAjustado ){
            $totalFinal = intval($totalFinal);
            $conceptosMes['Redondeo'] = $decimales;
        }
        
        return $conceptosMes;


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




        
        return response()->json([
            'success' => '1',
            'adeudo' => $adeudo,
            'adeudoString' => $adeudoString,
            'recargos' => $recargos
        ], 200);

        #$ruta = $this->generaReciboOficialIndividual($cliente, $idPadron, $a_no, $mes);

        #return $ruta;
    }

    public function consumo(Request $request)
    {
        $cuenta = $request->Cuenta;
        $cliente=$request->Cliente;
        Funciones::selecionarBase($cliente);
        
        #return $cuenta;
        #$DatosParaRecibo = Funciones::VerificarExistenciaCuentaAgua('32', $cuenta, '2019', '07');
        #return $DatosParaRecibo;
        #return "Salio...";

        $adeudo = 0;

        #$padronAgua = PadronAguaPotable::select('id')->where('ContratoVigente', $cuenta)->first();
        $padronAgua = PadronAguaPotable::where(DB::raw('CAST(ContratoVigente AS UNSIGNED)'), $cuenta)->first();
        #return $padronAgua;

        if (!$padronAgua) {
            return 'No se encuentra el contrato';
        } else {
            #$estaPagado = FALSE;
            #$idLectura = '';

            $estaPagado = true;
            #$idLectura = '34438120';
            #$idLectura = '34499247';
            #$idLectura = '29453346';
            $idLectura = '34215837';

            /*$lectura = PadronAguaLectura::select('id')#->where('Padr_onAgua', $padronAgua->id)#->where('Status', '1')
            ->where([
            ['Padr_onAgua', $padronAgua->id],
            ['Status', '1'],
            ])
            ->orderBy('A_no', 'DESC')
            ->orderBy('Mes', 'DESC')
            ->first();

            if ( ! $lectura ) {
            $estaPagado = TRUE;
            $lectura = PadronAguaLectura::select('id')
            ->where([
            ['Padr_onAgua', $padronAgua->id],
            ['Status', '2'],
            ])
            ->max('id');

            $idLectura = $lectura;
            }else{
            $idLectura = $lectura->id;
            }*/

            #return $idLectura;

            if ($estaPagado) {

                #$DatosParaRecibo = $this->obtieneDatosLectura($idLectura);
                $DatosParaRecibo = Funciones::obtieneDatosLectura($idLectura);
                #$funciones = new Funciones();
                #$DatosParaRecibo = Funciones::obtieneDatosLectura($idLectura);
                #$DatosParaRecibo = Funciones::VerificarExistenciaCuentaAgua('32', '1389', '2019', '6');

                return $DatosParaRecibo;

                #goto sinCalcular;
                $adeudo = $DatosParaRecibo['AdeudoCompleto'];
                return $adeudo;

            } else {
            }

        }
        return 'Fin Proceso.';
    }

    #Funcion Para Cuando Esten Pagado

    public function obtieneDatosLectura($Lectura)
    {

        $TipoToma = PadronAguaLectura::select('TipoToma')->where('id', $Lectura)->first();
        #return $TipoToma;
        
        
        $DatosLecturaActual = PadronAguaPotable::select('Padr_onAguaPotable.*', 'Padr_onAguaPotable.id as paid',
            'pl.id as plid', 'pl.Status as EstatusPagado',
            DB::raw('CONCAT(pl.A_no, LPAD(pl.Mes, 2, 0 )) as MesEjercicio'),
            DB::raw("(SELECT t.Concepto FROM TipoTomaAguaPotable t WHERE t.id=" . ((is_null($TipoToma) || $TipoToma == '') ? "pa" : "pl") . ".TipoToma ) as TipoTomaTexto")
        )
            ->join('Padr_onDeAguaLectura as pl', 'Padr_onAguaPotable.id', '=', 'pl.Padr_onAgua')
            ->where('pl.id', $Lectura)
            ->orderBy('pl.A_no', 'DESC')
            ->orderBy('pl.Mes', 'DESC')
            ->first();

        $registroLecturas = PadronAguaPotable::select('pal.id as palid',
            (is_null($TipoToma || $TipoToma == '') ? 'Padr_onAguaPotable' : 'pal') . '.TipoToma',
            'pal.Consumo', 'pal.Tarifa', 'ccc.Descripci_on', 'ccc.CRI', 'ccc.id as id', 'pal.Mes', 'pal.A_no',
            'ca.Importe as ImporteUnitario', 'ca.BaseCalculo', 'Padr_onAguaPotable.Cliente',
            DB::raw("CONCAT(pal.A_no,LPAD(pal.Mes,2,'0')) as MesEjercicio"), 'pal.Status as EstatusPagado'
        )
            ->join('Padr_onDeAguaLectura as pal', 'Padr_onAguaPotable.id', '=', 'pal.Padr_onAgua')
            ->join('ConceptoCobroCaja as ccc', 'ccc.TipoToma', '=', ((is_null($TipoToma) || $TipoToma == '') ? "Padr_onAguaPotable" : "pal") . '.TipoToma')
            ->join('ConceptoRetencionesAdicionales as cra', 'cra.Concepto', '=', 'ccc.id')
            ->join('ConceptoRetencionesAdicionalesCliente as crac', 'cra.id', '=', 'crac.ConceptoRetencionesAdicionales')
            ->join('ConceptoAdicionales as ca', 'ca.ConceptoRetencionesAdicionalesCliente', '=', 'crac.id')
            ->whereNull('ccc.Desde')
            ->whereNull('ccc.Hasta')
            ->where('ca.Status', '1')
            ->where('Padr_onAguaPotable.id', $DatosLecturaActual->paid)
            ->where('Padr_onAguaPotable.Cliente', $DatosLecturaActual->Cliente)
            ->where('crac.Cliente', $DatosLecturaActual->Cliente)
            ->whereIn('pal.Status', [0, 1, 2])
            ->where('pal.EstadoToma', '1')
            ->where(DB::raw("CONCAT(pal.A_no,LPAD(pal.Mes,2,'0'))"), '<=', $DatosLecturaActual->MesEjercicio)
            ->distinct()
        #->first();
            ->get();

        $resultados = array();
        $resultados['SumaAdeudosAnteriores'] = 0;
        $resultados['AdeudoCompleto'] = 0;
        $resultados['AdeudoAnterior'] = 0;
        $resultados['AdeudoActual'] = 0;
        $resultados['MesInicial'] = "";
        $resultados['MesFinal'] = "";
        #$resultados['MesDeCorte']=mesSiguiente($DatosLecturaActual[0]->MesEjercicio);
        $resultados['Consumo'] = $DatosLecturaActual->Consumo;
        $resultados['Cuenta'] = $DatosLecturaActual->Cuenta;
        //Contrato Vigente
        //Contrato Anterior
        //medidor
        $resultados['ContratoVigente'] = $DatosLecturaActual->ContratoVigente;
        $resultados['ContratoAnterior'] = $DatosLecturaActual->ContratoAnterior;
        $resultados['Medidor'] = $DatosLecturaActual->Medidor;

        $resultados['FechaLectura'] = $DatosLecturaActual->FechaLectura;
        $resultados['EstatusPagado'] = $DatosLecturaActual->EstatusPagado;
        $resultados['numRecibo'] = $DatosLecturaActual->plid;
        $resultados['MesActual'] = $DatosLecturaActual->MesEjercicio;
        $resultados['TipoTomaTexto'] = $DatosLecturaActual->TipoTomaTexto;

        //precode($DatosLecturaActual,1 );
        if ($DatosLecturaActual->M_etodoCobro == 2) {
            $resultados['LecturaActual'] = $DatosLecturaActual->LecturaActual;
            $resultados['LecturaAnterior'] = $DatosLecturaActual->LecturaAnterior;
        } else {
            $resultados['LecturaActual'] = "No aplica";
            $resultados['LecturaAnterior'] = "No aplica";
        }

        #return $resultados;

        foreach ($registroLecturas as $registroLectura) {
            #return $registroLectura->id;

            $datosAdicioaneles = $this->ObtieneAdicionales($registroLectura->id, $registroLectura->Consumo, $registroLectura->Tarifa, $registroLectura->Cliente, $registroLectura->BaseCalculo, $registroLectura->ImporteUnitario, $registroLectura->BaseCalculo, $registroLectura->MesEjercicio);
            #return $datosAdicioaneles;

            if ($registroLectura->MesEjercicio == $DatosLecturaActual->MesEjercicio) {
                $resultados['AdeudoActual'] = $datosAdicioaneles['SumaCompleta'];
                $resultados['AdeudoCompleto'] += $datosAdicioaneles['SumaCompleta'];

            } else {
                if ($registroLectura->EstatusPagado != 2) {
                    if ($resultados['MesInicial'] == "") {
                        $resultados['MesInicial'] = $datosAdicioaneles['MesEjercicio'];
                    }
                    $resultados['SumaAdeudosAnteriores'] += $datosAdicioaneles['SumaCompleta'];
                    if ($datosAdicioaneles['MesEjercicio'] > $resultados['MesFinal']) {
                        $resultados['MesFinal'] = $datosAdicioaneles['MesEjercicio'];
                    }
                    if ($datosAdicioaneles['MesEjercicio'] < $resultados['MesInicial']) {
                        $resultados['MesInicial'] = $datosAdicioaneles['MesEjercicio'];
                    }
                    $resultados['AdeudoAnterior'] += $datosAdicioaneles['SumaCompleta'];
                    $resultados['AdeudoCompleto'] += $datosAdicioaneles['SumaCompleta'];

                }
            }
        }

        return $resultados;
        #return $resultados['AdeudoCompleto'];
    }

    public function ObtieneAdicionales($Concepto, $MetrosConsumidos, $Tarifa, $cliente, $BaseCalculo, $ImporteConcepto, $ImporteConsumo, $MesEjercicio)
    {

        $Datos = array();

        $resultadoConsulta = DB::table('ConceptoCobroCaja as c')->select('ra.id', 'ra.Descripci_on',
            'ra.Cri', 'ra.PlanDeCuentas', 'ra.ConceptoCobro', 'ra.Porcentaje', 'ra.Proveedor', 'c2.AplicaIVA'
        )
            ->join('ConceptoRetencionesAdicionales as c1', 'c.id', '=', 'c1.Concepto')
            ->join('ConceptoRetencionesAdicionalesCliente as c2', 'c1.id', '=', 'c2.ConceptoRetencionesAdicionales')
            ->join('ConceptoAdicionales as c3', 'c2.id', '=', 'c3.ConceptoRetencionesAdicionalesCliente')
            ->join('RetencionesAdicionales as ra', 'ra.id', '=', 'c1.RetencionAdicional')
            ->where('c2.AplicaEnSubtotal', '0')
            ->where('c3.Cliente', $cliente)
            ->where('c2.Cliente', $cliente)
            ->where('c.id', $Concepto)
            ->distinct()
            ->get();

        $i = 0;
        $Datos['MesEjercicio'] = $MesEjercicio;
        $Datos['Importe'] = $Tarifa;
        $Datos['Subtotal'] = $Tarifa;
        $sumaIVA = $Tarifa;
        $Datos['sumaAdicionales'] = 0;

        foreach ($resultadoConsulta as $filas) {
            #return $filas->Porcentaje;
            $i++;
            $ConceptoOperacion = number_format((floatval(str_replace(",", "", $Datos['Importe'])) * floatval($filas->Porcentaje / 100)), 2);
            $ConceptoOperacion2 = (floatval(str_replace(",", "", $Datos['Importe'])) * floatval($filas->Porcentaje / 100));

            $Datos['Adicional_' . $i] = $ConceptoOperacion;
            $Datos['sumaAdicionales'] += $ConceptoOperacion2;
            $Datos['Subtotal'] += $ConceptoOperacion2;
            if ($filas->AplicaIVA == 1) {
                $sumaIVA += $ConceptoOperacion2;
            }
        }

        $ResultadoAdicionales = DB::table('ConceptoCobroCaja as c')->select('ra.id', 'ra.Descripci_on',
            'ra.Cri', 'ra.PlanDeCuentas', 'ra.ConceptoCobro', 'ra.Porcentaje', 'ra.Proveedor', 'c2.AplicaIVA'
        )
            ->join('ConceptoRetencionesAdicionales as c1', 'c.id', '=', 'c1.Concepto')
            ->join('ConceptoRetencionesAdicionalesCliente as c2', 'c1.id', '=', 'c2.ConceptoRetencionesAdicionales')
            ->join('ConceptoAdicionales as c3', 'c2.id', '=', 'c3.ConceptoRetencionesAdicionalesCliente')
            ->join('RetencionesAdicionales as ra', 'ra.id', '=', 'c1.RetencionAdicional')
            ->where('c2.AplicaEnSubtotal', '1')
            ->where('c3.Cliente', $cliente)
            ->where('c2.Cliente', $cliente)
            ->where('c.id', $Concepto)
            ->distinct()
        #->first();
            ->get();

        foreach ($ResultadoAdicionales as $filas) {
            #return $filas->Porcentaje;
            $i++;
            if ($filas->AplicaIVA == 1) {
                $ConceptoOperacion = number_format((floatval(str_replace(",", "", $sumaIVA)) * floatval($filas->Porcentaje / 100)), 2);
                $ConceptoOperacion2 = (floatval(str_replace(",", "", $sumaIVA)) * floatval($filas->Porcentaje / 100));
            } else {
                $ConceptoOperacion = number_format((floatval(str_replace(",", "", $Datos['Subtotal'])) * floatval($filas->Porcentaje / 100)), 2);
                $ConceptoOperacion2 = (floatval(str_replace(",", "", $Datos['Subtotal'])) * floatval($filas->Porcentaje / 100));
            }

            $Datos['Adicional' . $i] = $ConceptoOperacion;
            $Datos['sumaAdicionales'] += $ConceptoOperacion2;
        }

        $Datos['sumaAdicionales'] = str_replace(",", "", number_format(floatval(str_replace(",", "", $Datos['sumaAdicionales'])), 2));
        $Datos['Subtotal'] = str_replace(",", "", number_format(floatval(str_replace(",", "", $Datos['Subtotal'])), 2));
        $Datos['SumaCompleta'] = str_replace(",", "", number_format(floatval(str_replace(",", "", $Datos['sumaAdicionales'])) + floatval(str_replace(",", "", $Datos['Importe'])), 2));

        $Datos['cantidadAdicional'] = $i;

        return ($Datos);
    }

    public function obtieneDatosLectura2($Lectura)
    {
        #$TipoToma=DB::select("SELECT pl.TipoToma FROM  Padr_onDeAguaLectura pl WHERE id=" . $Lectura);

        /*$DatosLecturaActual = DB::select("SELECT * , pa.id as paid, pl.id as plid, pl.Status as EstatusPagado,
        CONCAT(pl.A_no,LPAD(pl.Mes, 2, 0 )) as MesEjercicio,
        ( select t.Concepto FROM TipoTomaAguaPotable  t WHERE t.id=".(is_null($TipoToma)?"pa":"pl").".TipoToma ) as TipoTomaTexto
        FROM Padr_onAguaPotable pa
        INNER JOIN Padr_onDeAguaLectura pl ON (pa.id=pl.Padr_onAgua)
        #INNER JOIN TipoTomaAguaPotable t ON (t.id=".(is_null($TipoToma)?"pa":"pl").".TipoToma)
        WHERE
        #pl.Status=0 AND
        pl.id=".$Lectura."
        ORDER BY pl.A_no DESC, pl.Mes DESC
        LIMIT 0, 1");*/

        #return $TipoToma;
        #return $DatosLecturaActual[0]->id;

        /*$ConsultaLecturas="SELECT DISTINCT pal.id as palid,  ".(is_null($TipoToma)?"pap":"pal").".TipoToma, pal.Consumo, pal.Tarifa, ccc.Descripci_on, ccc.CRI, ccc.id as id, pal.Mes,
        pal.A_no, ca.Importe as ImporteUnitario, ca.BaseCalculo, pap.Cliente,
        CONCAT(pal.A_no,LPAD(pal.Mes,2,'0')) as MesEjercicio, pal.Status as EstatusPagado
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
        pap.id =".$DatosLecturaActual->paid." AND
        pap.Cliente=".$DatosLecturaActual->Cliente." AND
        crac.Cliente=".$DatosLecturaActual->Cliente." AND
        pal.Status IN (0,1,2) AND
        pal.EstadoToma=1 AND
        CONCAT(pal.A_no,LPAD(pal.Mes,2,'0'))<=".$DatosLecturaActual->MesEjercicio." AND 1=1";

        $registroLecturas = DB::select($ConsultaLecturas);*/

        #return $registroLecturas;
    }

    public function ObtieneAdicionales2($Concepto, $MetrosConsumidos, $Tarifa, $cliente, $BaseCalculo, $ImporteConcepto, $ImporteConsumo, $MesEjercicio)
    {
        /*$ConsultaSelect = "SELECT DISTINCT ra.id, ra.Descripci_on, ra.Cri, ra.PlanDeCuentas, ra.ConceptoCobro, ra.Porcentaje, ra.Proveedor, c2.AplicaIVA
        FROM ConceptoCobroCaja c
        INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto )
        INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales )
        INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente )
        INNER JOIN RetencionesAdicionales ra ON (ra.id=c1.RetencionAdicional)
        WHERE c2.AplicaEnSubtotal=0 AND c3.Cliente=".$cliente.
        " AND c2.Cliente=".$cliente." AND c.id = ".$Concepto."";

        $resultadoConsulta = DB::select($ConsultaSelect);*/
        #return $resultadoConsulta;

        /*$ConsultaSelectDespSubtotal = "SELECT DISTINCT ra.id, ra.Descripci_on, ra.Cri, ra.PlanDeCuentas,
        ra.ConceptoCobro, ra.Porcentaje, ra.Proveedor, c2.AplicaIVA
        FROM ConceptoCobroCaja c
        INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto )
        INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales )
        INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente )
        INNER JOIN RetencionesAdicionales ra ON (ra.id=c1.RetencionAdicional)
        WHERE c2.AplicaEnSubtotal=1 AND c3.Cliente=".$cliente." AND c2.Cliente=".$cliente.
        " AND c.id = ".$Concepto."";
        $ResultadoAdicionales = DB::select($ConsultaSelectDespSubtotal);*/
        #return $ResultadoAdicionales;

        $Datos = array(
            'cadena1' => array(
                'tabla' => 'PresupuestoAnualPrograma p
                        INNER JOIN Cat_alogoPrograma ca ON ( p.Programa = ca.id  )
                        INNER JOIN Fondo f ON ( p.Fondo = f.id  )
                        INNER JOIN PresupuestoGeneralAnual p1 ON ( f.Presupuesto = p1.id  )  ',
                'campo' => 'concat(ca.Descripci_on, " (",p1.EjercicioFiscal,")") /as/ Programa',
                'filtro' => 'AreaAdministrativa',
                'hijo' => 'Programa',
                'indice' => 'p.id /as/ id',
                'vacio' => 1,
                'mensajevacio' => "Seleccione una opci&oacute;n",
                'Order' => ' ORDER BY concat(ca.Descripci_on, " (",p1.EjercicioFiscal,")") ASC',
            ),
        );
        $OpcFondo['nombre'] = "AreaAdministrativa";
        $OpcFondo['clase'] = "form-control e_requerido combo_padre AreaAdministrativa";

        $OpcFondo['extra'] = "data-combo_cadena='" . json_encode($Datos) . "'";
        $Consulta = "SELECT f.id, concat(ca.Descripci_on, ' (',p.EjercicioFiscal,')')
                    FROM Fondo f
                        INNER JOIN PresupuestoGeneralAnual p ON ( f.Presupuesto = p.id  )
                        INNER JOIN Cat_alogoDeFondo ca ON ( f.CatalogoFondo = ca.id  )
                    WHERE f.Status = 1 AND 1=0 AND
                        p.Status = 1 AND
                        p.Cliente = " . $_SESSION['CELA_Cliente' . $_SESSION['CELA_Aleatorio']] . " ORDER BY ca.Descripci_on ASC";
        print RellenaCombo($Consulta, $OpcFondo, 1);

        $OpcAreaAdministrativa['nombre'] = "AreaAdministrativa";
        $OpcAreaAdministrativa['clase'] = "form-control  e_requerido";
        $Consulta = " select id, Descripci_on from AreasAdministrativas where id in (select AreaAdministrativa from ClienteAreaAdministrativa where Cliente = " . $_SESSION['CELA_Cliente' . $_SESSION['CELA_Aleatorio']] . ") ORDER BY Descripci_on ASC";
        print RellenaCombo($Consulta, $OpcAreaAdministrativa, 1);

    }

    public function pagosHistorial(Request $request)
    {
        $idPadron = $request->idPadron;
        $a_no = $request->A_no;
        $cliente=$request->Cliente;
        #return $a_no;
        Funciones::selecionarBase($cliente);

        if (!isset($a_no) || $a_no == '') {
            $a_no = '2019';
        } elseif ($a_no == 'Todo') {
            $a_no = 0;
        }

        #return $a_no;

        if ($a_no == 0) {
            $historial = PadronAguaLectura::select('id',
                DB::raw("COALESCE((SELECT Concepto FROM TipoTomaAguaPotable WHERE TipoTomaAguaPotable.id = TipoToma), '') AS TipoToma"),
                'FechaLectura', 'A_no', 'Mes',
                DB::raw("(SELECT Nombre FROM Mes WHERE Mes.id = Mes) AS MesLectura"),
                'LecturaAnterior', 'LecturaActual', 'Consumo', 'Tarifa', 'Observaci_on', 'Status', 'EstadoToma'
            )
                ->where('Padr_onAgua', $idPadron)
                ->where('Status', '1')
                ->orderBy('A_no', 'DESC')
                ->orderBy('Mes', 'DESC')
                ->get();
        } else {
            $historial = PadronAguaLectura::select('id',
                DB::raw("COALESCE((SELECT Concepto FROM TipoTomaAguaPotable WHERE TipoTomaAguaPotable.id = TipoToma), '') AS TipoToma"),
                'FechaLectura', 'A_no', 'Mes',
                DB::raw("(SELECT Nombre FROM Mes WHERE Mes.id = Mes) AS MesLectura"),
                'LecturaAnterior', 'LecturaActual', 'Consumo', 'Tarifa', 'Observaci_on', 'Status', 'EstadoToma'
            )
                ->where('Padr_onAgua', $idPadron)
                ->where('A_no', '>=', $a_no)
                ->where('Status', '1')
                ->orderBy('A_no', 'DESC')
                ->orderBy('Mes', 'DESC')
                ->get();
        }

        #return $historial;
        $data = "";

        /*$Cotizaciones = DB::table('Cotizaci_on')->select( DB::raw('GROUP_CONCAT(id) as Cotizaci_ones') )
            ->where('Padr_on', $idPadron)
            ->where(DB::raw('( SELECT sum( importe ) FROM ConceptoAdicionalesCotizaci_on ca WHERE ca.Cotizaci_on = Cotizaci_on.id AND ca.Padre IS NULL AND ca.A_no ='.$a_no.' AND ca.Mes = '.$mes.')'), '>', '0')
            ->groupBy('Padr_on')
            ->orderBy('id', 'DESC')
            ->value('Cotizaci_ones'); */
        foreach ($historial as $registro) {
            if ($registro->Status == 2) {
                #$registro->Tarifa = $this->obtieneDatosLectura($registro->id);
                $registro->Tarifa = number_format(Funciones::VerificarExistenciaCuentaAgua($registro->id, 1), 2);
            } else {
                //recibos no pagados 
                $Cotizaciones = DB::table('Cotizaci_on')->select( DB::raw('GROUP_CONCAT(id) as Cotizaci_ones') )
            ->where('Padr_on', $idPadron)
            ->where(DB::raw('( SELECT sum( importe ) FROM ConceptoAdicionalesCotizaci_on ca WHERE ca.Cotizaci_on = Cotizaci_on.id AND ca.Padre IS NULL AND ca.A_no ='.$registro->A_no.' AND ca.Mes = '.$registro->Mes.')'), '>', '0')
            ->groupBy('Padr_on')
            ->orderBy('id', 'DESC')
            ->value('Cotizaci_ones'); 
                $registro->Tarifa = number_format(Funciones::VerificarExistenciaCuentaAgua($cliente, $idPadron, $registro->A_no, $registro->Mes, 1), 2);
                $registro->Cotizacion=$Cotizaciones;
            }

            if ($registro->Status == 2) {
                $registro->Status = 'Pagado';
            } elseif ($registro->Status == 1) {
                $registro->Status = 'Cotizado';
            } else {
                $registro->Status = 'No Cotizado';
            }

            $registro->EstadoToma = $this->estadoAgua($registro->EstadoToma);
        }

        #return $data;

        return response()->json([
            'success' => '1',
            'historial' => $historial,
        ]);
    }

    public function pago(Request $request)
    {

        $cuenta = $request->IdCuenta;
        $total = $request->Total;
        $lecturas = $request->Lecturas;
        $cliente=$request->Cliente;
        $re;
        Funciones::selecionarBase($cliente);

        foreach ($lecturas as $lectura) {
            $re = $lectura;

        }
        return response()->json([
            'success' => '1',
            'result' => $re,
        ]);

    }

    public static function generaReciboOficialIndividual($cliente, $idPadron, $a_no, $mes){

        #require_once('num_letras.php');
        #include( app_path() . '/Libs/num_letras.php' );

        $diaLimite = 15;
        $idLectura = PadronAguaLectura::where('Padr_onAgua', $idPadron)->where('A_no', $a_no)->where('Mes', $mes)->where('Status',  1)->value('id');

        $estaPagado = FALSE;
        if ( ! $idLectura ) {
            $estaPagado = TRUE;
            #$idLectura = ObtenValor("SELECT MAX(id) as id FROM Padr_onDeAguaLectura WHERE Padr_onAgua =" . $idPadron . " AND `Status` = 2");
            $idLectura = PadronAguaLectura::select( DB::raw(" ( MAX(id) ) AS id") )
                ->where("Padr_onAgua", $idPadron) 
                ->where("Status",  2)->value('id');
        }

        $adeudos = "";
        $adeudo = PadronAguaLectura::select( DB::raw("COUNT(id) AS adeudo") )
                ->where("Padr_onAgua", $idPadron)
                ->where('Status',  '1')
                ->value('adeudo');

        if ($adeudo && $adeudo > 0) {
            #$adeudo = intval($adeudo);
            if ($adeudo >= 2) {
                $adeudo = $adeudo - 1;
                $adeudos = "Meses adeudo: " . $adeudo;
            }
        }

        #$cuentasPapas = ObtenValor("SELECT GROUP_CONCAT(p.id) as CuentasPapas FROM Padr_onAguaPotable p WHERE p.id=(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1) and p.Cliente=" . $cliente . "", 'CuentasPapas');
        $cuentasPapas = DB::table('Padr_onAguaPotable AS p')
            ->select( DB::raw("GROUP_CONCAT(p.id) AS CuentasPapas") ) 
            ->where("p.id", DB::raw("(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1)") )
            ->where("p.Cliente", $cliente)->value('CuentasPapas');

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

        #$anomalia = ObtenValor("SELECT pac.descripci_on FROM Padr_onDeAguaLectura pal INNER JOIN Padr_onAguaCatalogoAnomalia pac ON pal.Observaci_on = pac.id WHERE pal.id=" . $idLectura, "descripci_on");
        $anomalia = PadronAguaLectura::select("pac.descripci_on as descripci_on") 
            ->join("Padr_onAguaCatalogoAnomalia as pac", 'Padr_onDeAguaLectura.Observaci_on', '=', 'pac.id') 
            ->where("Padr_onDeAguaLectura.id", $idLectura)->value('descripci_on');

        $tieneAnomalia = TRUE;

        if ($anomalia == 'NULL') {
            $anomalia = '';
            $tieneAnomalia = FALSE;
        }

        #$textoletras = new EnLetras();

        $DatosPadron = PadronAguaPotable::select(
                'd.RFC', 'Padr_onAguaPotable.Ruta', 'Padr_onAguaPotable.Lote',
                'Padr_onAguaPotable.Cuenta', 'Padr_onAguaPotable.Sector', 'Padr_onAguaPotable.Manzana',
                'Padr_onAguaPotable.Colonia', 'Padr_onAguaPotable.Medidor', 'Padr_onAguaPotable.Diametro',
                'Padr_onAguaPotable.TipoToma', 'Padr_onAguaPotable.Domicilio', 'Padr_onAguaPotable.SuperManzana',
                'Padr_onAguaPotable.ContratoVigente', 'd.NombreORaz_onSocial',
                DB::raw("COALESCE ( c.NombreComercial, NULL ) AS NombreComercialPadron"),
                DB::raw("( SELECT Descripci_on FROM Giro g WHERE g.id = Padr_onAguaPotable.Giro ) AS Giro"),
                DB::raw("( SELECT COALESCE ( Nombre, '' ) FROM Municipio m WHERE m.id = d.Municipio ) AS Municipio"),
                DB::raw("( SELECT COALESCE ( Nombre, '' ) FROM EntidadFederativa e WHERE e.id = d.EntidadFederativa ) AS Estado"),
                DB::raw("COALESCE ( CONCAT( c.Nombres, ' ', c.ApellidoPaterno, ' ', c.ApellidoMaterno ), NULL ) AS ContribuyentePadron")
            )
            ->join('Contribuyente as c','c.id','=','Padr_onAguaPotable.Contribuyente')
            ->join('DatosFiscales as d','d.id','=','c.DatosFiscales')
            ->where('Padr_onAguaPotable.id', $idPadron)
            ->first();
        
        if (!$DatosPadron->ContribuyentePadron || empty($DatosPadron->ContribuyentePadron) || strlen($DatosPadron->ContribuyentePadron) <= 2)
            $contribuyente = utf8_decode($DatosPadron->NombreComercialPadron);
        else
            $contribuyente = utf8_decode($DatosPadron->ContribuyentePadron);

        if (isset($DatosPadron->TipoToma) && $DatosPadron->TipoToma != '')
            $consultaToma = DB::table('TipoTomaAguaPotable')->where('id', $DatosPadron->TipoToma)->value('Concepto');
        else
            $consultaToma = 'NULL';

        if ( !$consultaToma || $consultaToma == '')
            $tipoToma = '0';
        else
            $tipoToma = utf8_decode($consultaToma);

        $folio = PadronAguaPotable::where('id', $idPadron)->value('Cuenta');
        
        if ($estaPagado) {
            $DatosParaRecibo = Funciones::obtieneDatosLectura($idLectura);
            #$mesActual = ObtenValor("SELECT Mes FROM Padr_onDeAguaLectura WHERE id = " . $idLectura, 'Mes');
            $mesActual = PadronAguaLectura::select('Mes')->where('id', $idLectura)->value('Mes');
        }else
            $DatosParaRecibo = PadronAguaLectura::where('id', $idLectura)->first();
            #$DatosParaRecibo = ObtenValor("SELECT * FROM Padr_onDeAguaLectura WHERE id = " . $idLectura);
    
        if ($tieneAnomalia || $esPapa) {
            $lecturaAnterior = $DatosParaRecibo->LecturaAnterior;
            $lecturaActual   = $DatosParaRecibo->LecturaActual;
            $lecturaConsumo  = $DatosParaRecibo->Consumo;
        } else {
            $lecturaAnterior = intval($DatosParaRecibo->LecturaAnterior);
            $lecturaActual   = intval($DatosParaRecibo->LecturaActual);
            $lecturaConsumo  = $lecturaActual - $lecturaAnterior;
        }
    
        /*$mesCobro = ObtenValor("SELECT LPAD(pl.Mes, 2, 0 ) as MesEjercicio FROM Padr_onAguaPotable pa
            INNER JOIN Padr_onDeAguaLectura pl ON (pa.id=pl.Padr_onAgua)
            INNER JOIN TipoTomaAguaPotable t ON (t.id=" . (is_null($DatosPadron["TipoToma"]) ? "pa" : "pl") . ".TipoToma)
            WHERE pl.id=" . $idLectura . " ORDER BY pl.A_no DESC, pl.Mes DESC LIMIT 0, 1", "MesEjercicio");*/

        $mesCobro = PadronAguaPotable::select( DB::raw('LPAD(pl.Mes, 2, 0 ) as MesEjercicio') )
            ->join('Padr_onDeAguaLectura as pl','Padr_onAguaPotable.id','=','pl.Padr_onAgua')
            ->join('TipoTomaAguaPotable as t','t.id','=', ( ($DatosPadron->TipoToma == '' || is_null($DatosPadron->TipoToma) ) ? 'Padr_onAguaPotable' : 'pl') . '.TipoToma')
            ->where('pl.id', $idLectura)
            ->orderBy('pl.A_no', 'DESC')
            ->orderBy('pl.Mes', 'DESC')
            ->value('MesEjercicio');
        
        $a_noCobro = PadronAguaPotable::select('pl.A_no as a_noEjercicio')
            ->join('Padr_onDeAguaLectura as pl','Padr_onAguaPotable.id','=','pl.Padr_onAgua')
            ->join('TipoTomaAguaPotable as t','t.id','=', ( ($DatosPadron->TipoToma == '' || is_null($DatosPadron->TipoToma) ) ? 'Padr_onAguaPotable' : 'pl') . '.TipoToma')
            ->where('pl.id', $idLectura)
            ->orderBy('pl.A_no', 'DESC')
            ->orderBy('pl.Mes', 'DESC')
            ->value('a_noEjercicio');
            
        if( isset($DatosPadron->TipoToma) && $DatosPadron->TipoToma != '' && $DatosPadron->TipoToma == 4 ){
            $diaLimite = 5;
            $fechaLimite = $diaLimite . '/' . intval($mesCobro + 1) . '/' . date('Y');
            $fechaCorte = $diaLimite + 1 . '/' . intval($mesCobro + 1) . '/' . date('Y');
        }else{
            $fechaLimite = $diaLimite . '/' . intval($mesCobro + 1) . '/' . date('Y');
            $fechaCorte = $diaLimite + 1 . '/' . intval($mesCobro + 1) . '/' . date('Y');
        }
    
        $fechasPeriodo = PadronAguaLectura::select('FechaLectura')
            ->where('Padr_onAgua', $idPadron)
            ->where('Mes', '<=', $mesCobro) 
            ->where('A_no', $a_noCobro) 
            ->orderBy('id', 'DESC') 
            ->take(2)
            ->get();
    
        if (count($fechasPeriodo) == 2) {
            $periodo = date_format(new DateTime($fechasPeriodo[1]->FechaLectura), 'd/m/Y') . " a " . date_format(new DateTime($fechasPeriodo[0]->FechaLectura), 'd/m/Y');
        }
    
        #$DatosHistoricos = ObtenValores("SELECT Consumo, Mes, A_no FROM Padr_onDeAguaLectura WHERE Mes = " . $mesCobro . " AND Padr_onAgua =" . $idPadron . " AND A_no < DATE_FORMAT( CURDATE(), '%Y') ORDER BY FechaLectura DESC LIMIT 3");
        $DatosHistoricos = PadronAguaLectura::select('Consumo', 'Mes', 'A_no') 
            ->where('Mes', $mesCobro)
            ->where('Padr_onAgua', $idPadron)
            ->where('A_no', '<', DB::raw("DATE_FORMAT( CURDATE(), '%Y')" ) ) 
            ->orderBy('FechaLectura', 'DESC')
            ->take(3)
            ->get();

        $meses = ["ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE"];

        $datosHistoricosTabla = '';
        foreach ($DatosHistoricos as $valor) {
            //$lista[] = $fila[$valor->name];
            $datosHistoricosTabla .=
                '<tr>
                    <td>' . $meses[$valor->Mes - 1] . '-' . $valor->A_no .'</td>
                    <td class="derecha">' . intval($valor->Consumo) . ' M3</td>
                </tr>';
        }

        /*$Cotizaciones = ObtenValor("SELECT GROUP_CONCAT(id) as Cotizaci_ones
            FROM
                Cotizaci_on 
            WHERE
                Padr_on = " . $idPadron . "
                and ( SELECT sum( importe ) FROM ConceptoAdicionalesCotizaci_on WHERE ConceptoAdicionalesCotizaci_on.Cotizaci_on = Cotizaci_on.id AND Padre IS NULL ) > 0 
                GROUP by Padr_on ORDER BY id DESC
                ", 'Cotizaci_ones');
        #return $Cotizaciones;*/
        $Cotizaciones = DB::table('Cotizaci_on')->select( DB::raw('GROUP_CONCAT(id) as Cotizaci_ones') )
            ->where('Padr_on', $idPadron)
            ->where(DB::raw('( SELECT sum( importe ) FROM ConceptoAdicionalesCotizaci_on WHERE ConceptoAdicionalesCotizaci_on.Cotizaci_on = Cotizaci_on.id AND Padre IS NULL )'), '>', '0')
            ->groupBy('Padr_on')
            ->orderBy('id', 'DESC')
            ->value('Cotizaci_ones');

        if ($Cotizaciones == '') {
            precode("Sin Cotizaciones.", 1, 1);
        }
        
        if( $estaPagado ) goto sinCalcular;

        $DescuentoGeneralCotizaciones = 0;
        $SaldoDescontadoGeneralTodo   = 0;
                
        $Descuentos = Funciones::ObtenerDescuentoConceptoRecibo($Cotizaciones);
        $SaldosActuales = Funciones::ObtenerDescuentoPorSaldoAnticipadoRecibo($Cotizaciones, $Descuentos['ImporteNetoADescontar'], $Descuentos['Conceptos'], $cliente);
        
        $ConsultaConceptos="SELECT c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad, 
                c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario, 
                (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional, co.A_no, COALESCE(co.Mes, '01') as Mes, ct.Tipo, c.TipoToma
            FROM ConceptoAdicionalesCotizaci_on co 
                INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
                INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )
            WHERE  co.Cotizaci_on IN( " .$Cotizaciones. ") and Estatus=0 ORDER BY co.A_no DESC, COALESCE(co.Mes, '01') DESC ,  co.id ASC ";
        
        /*$ConsultaConceptos = DB::table('ConceptoAdicionalesCotizaci_on')->select('c.id as idConceptoCotizacion', 
                'ConceptoAdicionalesCotizaci_on.id as ConceptoCobro', 'ConceptoAdicionalesCotizaci_on.Cantidad as Cantidad', 
                'c.Descripci_on as NombreConcepto', 'ConceptoAdicionalesCotizaci_on.Importe as total', 
                'ConceptoAdicionalesCotizaci_on.MontoBase as punitario',
                DB::raw("(select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=ConceptoAdicionalesCotizaci_on.Adicional) as Adicional"),
                'ConceptoAdicionalesCotizaci_on.A_no', DB::raw("COALESCE(ConceptoAdicionalesCotizaci_on.Mes, '01') as Mes"), 'ct.Tipo', 'c.TipoToma'
            )
            ->join('Cotizaci_on as ct', 'ConceptoAdicionalesCotizaci_on.Cotizacion', '=', 'ct.id')
            ->join('ConceptoCobroCaja as c', 'ConceptoAdicionalesCotizaci_on.ConceptoAdicionales', '=', 'c.id')
            ->whereIn('ConceptoAdicionalesCotizaci_on.Cotizaci_on', explode(',', $Cotizaciones) )
            ->where('Estatus', '0')
            ->orderBy('ConceptoAdicionalesCotizaci_on.A_no', 'DESC')
            ->orderBy(DB::raw("COALESCE(ConceptoAdicionalesCotizaci_on.Mes, '01')"), 'DESC')
            ->orderBy('ConceptoAdicionalesCotizaci_on.id', 'ASC')
            ->get()*/;

        $ResultadoConcepto=DB::select($ConsultaConceptos);
        #$ResultadoConcepto  = $Conexion->query($ConsultaConceptos);
        
        setlocale(LC_TIME, "es_MX.UTF-8");
        
        $ConceptosCotizados  = '';
        $totalConcepto       = 0;
        $indexConcepto       = 0;
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
        
        $ActualizacionesYRecargosFunciones = Funciones::ObtenerRecargosYActualizacionesPorConceptoRecibo($RegistroConcepto->ConceptoCobro, $Cotizaciones, $cliente);
            
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
    
        $sumaConceptosA = 0;
        $sumaAdicionalesA = 0;

        #while ($RegistroConcepto = $ResultadoConcepto->fetch_assoc()) {
            $i = 0;
        foreach($ResultadoConcepto as $RegistroConcepto) {
            if($i != 0){
                $ActualizacionesYRecargosFunciones = Funciones::ObtenerRecargosYActualizacionesPorConceptoRecibo($RegistroConcepto->ConceptoCobro, $Cotizaciones, $cliente);
        
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
                        if ($RegistroConcepto->A_no == $a_noCobro && $RegistroConcepto->Mes == $mesCobro) {
                            $conceptosNombresMes[$RegistroConcepto->ConceptoCobro] = $subtotal;
                        } else {
                            $sumaConceptosA = str_replace(",", "", $sumaConceptosA);
                            $sumaConceptosA += $subtotal; //Para el consumo de meses anteriores
                        }
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

        if($SaldoDescontadoGeneralTodo>0 && $SaldoDescontadoGeneralTodo <= $sumaTotalFinal)
            $sumaTotalFinal=str_replace(",", "",$sumaTotalFinal)-str_replace(",", "",$SaldoDescontadoGeneralTodo)-str_replace(",", "",$sumaDescuentos);
        else if($SaldoDescontadoGeneralTodo>=$sumaTotalFinal)
            $sumaTotalFinal=0;
        else if($SaldoDescontadoGeneralTodo==0)
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

        //uno los arrays
        $array_mes    = array_merge($consumoMesActual, $adicionalesNombresMes);
        $array_otros  = array_merge($conceptosOtros, $adicionalesOtros);
        $array_rezago = array_merge($conceptosNombres, $contar);

        $totalMes       = 0;
        $totalOtros     = 0;
        $totalRezago    = 0;
        $totalFinal     = $sumaTotalFinal;
        $totalesFinales = $sumaActualizaciones + $sumaRecargos;

        $FilaConceptosTotales = "<br>";
        if (empty($array_rezago)) {
            foreach ($array_mes as $key => $value) {
                $FilaConceptosTotales .= '<tr>
                <td colspan="2">' . utf8_decode($key) . '</td>
                <td class="centrado">' . (number_format($value, 2)) . '</td>
                <td class="centrado">-</td>
                <td class="centrado">' . (number_format($value, 2)) . '</td>
            </tr>';
                $totalMes    += str_replace(",", "", $value);
            }
        } else {
            foreach ($array_mes as $key => $value) {
                $FilaConceptosTotales .= '<tr>
                <td colspan="2">' . utf8_decode($key) . '</td>
                <td class="centrado">' . (number_format(str_replace(",", "", $value), 2)) . '</td>
                <td class="centrado">' . (number_format(str_replace(",", "", $array_rezago[$key]), 2)) . '</td>
                <td class="centrado">' . (number_format(str_replace(",", "", $value) + str_replace(",", "", $array_rezago[$key]), 2)) . '</td>
            </tr>';
                $totalMes     = str_replace(",", "", $totalMes);
                $totalMes    += str_replace(",", "", $value);
                $totalRezago  = str_replace(",", "", $totalRezago);
                $totalRezago += str_replace(",", "", $array_rezago[$key]);
            }
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
                <td colspan="2">' . (substr($concepto, 0, 44)) . '</td>
                <td class="centrado"></td>
                <td class="centrado"></td>
                <td class="centrado">' . (number_format($value, 2)) . '</td>
            </tr>';
                $totalOtros = str_replace(",", "", $totalOtros);
                $totalOtros +=  str_replace(",", "", $value);
            }
        }

        #$descuentos  = ObtenValor("SELECT PrivilegioDescuento FROM Padr_onAguaPotable WHERE id = " . $idPadron . " AND PrivilegioDescuento != 0", "PrivilegioDescuento");
        $descuentos  = PadronAguaPotable::select('PrivilegioDescuento')
            ->where('id', $idPadron)
            ->where('PrivilegioDescuento', '!=', '0')
            ->value('PrivilegioDescuento');
        
        $esDescuento = FALSE;
        $descuento   = 0;
        if ($descuentos != "") {
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
                <td colspan="2">Adeudo Completo</td>
                <td class="centrado"></td>
                <td class="centrado"></td>
                <td class="centrado">' . (number_format(str_replace(",", "", $totalFinal), 2)) . '</td>
            </tr>';
            
            $totalRezago = 0;
            $totalMes = 0;
            
            goto finCalculos;
        }

        #$saldo = ObtenValor("SELECT SaldoNuevo FROM Padr_onAguaHistoricoAbono WHERE idPadron = " . $idPadron . " ORDER BY id DESC ", "SaldoNuevo");
        $saldo = DB::table('Padr_onAguaHistoricoAbono')->select('SaldoNuevo')
            ->where('idPadron', $idPadron)->orderByDesc('id')->value('SaldoNuevo');

        $saldoNuevo = 0;
        if($saldo != ""){
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
                        <td colspan="2">Actualizaciones y Recargos</td>
                        <td><br></td>
                        <td class="centrado">
                            ' . number_format($totalesFinales, 2) . '
                        </td>
                        <td class="centrado">
                            ' . number_format($totalesFinales, 2) . '
                        </td>
                    </tr>';
        }

        if ($estaAjustado) {
            $FilaConceptosTotales .= '<tr>
                        <td colspan="2">Redondeo
                        </td>
                        <td><br></td>
                        <td><br></td>
                        <td class="centrado">
                            ' . $ajuste . '
                        </td>
                    </tr>';
        }
    
        if ($saldo != "NULL" && $saldoNuevo > 0) {
            $FilaConceptosTotales .= '<tr>
                        <td colspan="2">Saldo disponible</td>
                        <td><br></td>
                        <td><br></td>
                        <td class="centrado">
                            ' . number_format($saldoNuevo, 2) . '
                        </td>
                    </tr>';
        }
    
        if ($sumaSaldos > 0) {
            $FilaConceptosTotales .= '<tr>
                        <td colspan="2">Aplicacion Ingresos Cobrados por Anticipado</td>
                        <td><br></td>
                        <td><br></td>
                        <td class="centrado">-
                            ' . $sumaSaldos . '
                        </td>
                    </tr>';
        }

        $descNombre = "";
        if ($esDescuento && $sumaDescuentos > 0) {
            if ($descuentos == 1)
                $descNombre = "INAPAM";

            if ($descuentos == 2)
                $descNombre = "Pensionados y Jubilados";

            $FilaConceptosTotales .= '<tr>
                        <td colspan="2" >Descuento: ' . $descNombre . '</td>
                        <td><br></td>
                        <td><br></td>
                        <td class="centrado">-
                            ' . $sumaDescuentos . '
                        </td>
                    </tr>';
        }


        $totalFinal = str_replace(",", "", $ajusteFinal);
        
        finCalculos:
        
        if( $estaPagado )
            $totalFinal = 0;

        $letras = utf8_decode(Funciones::num2letras($totalFinal, 0, 0) . " pesos");
        $ultimoArr = explode(".", number_format($totalFinal, 2)); //recupero lo que este despues del decimal
        $ultimo = $ultimoArr[1];
        if ($ultimo == "")
            $ultimo = "00";
        $letras = $letras . " " . $ultimo . "/100 M. N.";
    
        if( ($estaPagado && $Cotizaciones == "NULL") || $totalFinal == 0 )
            return "";
        
        $nombreComercial = $DatosPadron["NombreORaz_onSocial"];
        if( strlen($nombreComercial) > 0 && strlen($nombreComercial) > 55 ){
            $nombreComercial = substr($nombreComercial, 0, strlen($nombreComercial) / 2) . '<br>' . substr($nombreComercial, strlen($nombreComercial) / 2, strlen($nombreComercial) );
        }

        $rutaBarcode = 'https://suinpac.piacza.com.mx/lib/barcode2.php?f=png&text=' . (isset($DatosPadron['ContratoVigente']) ? $DatosPadron['ContratoVigente'] : '') . '&codetype=Code128&orientation=Horizontal&size=40&print=false "';

        return $letras;
    }

    public static function ObtenerRecargosYActualizacionesAguaPortal($cliente, $Padr_on, $Concepto ){
        
        $ConsultaConceptos = "SELECT c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad, 
                c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario, 
                (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional, 
                co.A_no, COALESCE(co.Mes, '01') as Mes, ct.Tipo, c.TipoToma
            FROM ConceptoAdicionalesCotizaci_on co 
                INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
                INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )
            WHERE ct.Padr_on =" . $Padr_on . " and Estatus=0 AND co.id  IN($Concepto) ORDER BY co.A_no DESC, 
            COALESCE(co.Mes, '01') DESC ,  co.id ASC ";
        
        $ResultadoConcepto = DB::select($ConsultaConceptos);
        #return $ResultadoConcepto;
        $RegistroConcepto = $ResultadoConcepto[0];
        #return $ResultadoConcepto[0]->idConceptoCotizacion;

        #$ResultadoConcepto=$Conexion->query($ConsultaConceptos);
        $totalConcepto=0;
        $idsConceptos='';
        $Contador=0;
        $ConceptoActual=0;
        $indexConcepto=0;
        $i = 0;
        #setlocale(LC_TIME,"es_MX.UTF-8");
        #$RegistroConcepto=$ResultadoConcepto->fetch_assoc();
        
        $totalConcepto = $RegistroConcepto->total;
        $ConceptoPadre[$indexConcepto]['id']=$RegistroConcepto->idConceptoCotizacion;
        $ConceptoPadre[$indexConcepto]['idConcepto']=$RegistroConcepto->ConceptoCobro;
        $ConceptoPadre[$indexConcepto]['TipoPredio']=$RegistroConcepto->Tipo;
        $ConceptoPadre[$indexConcepto]['Total']=0;
        $ConceptoPadre[$indexConcepto]['FechaConcepto']=$RegistroConcepto->A_no."-".$RegistroConcepto->Mes."-01";

        $recargosActualizaciones = array();

        #while($RegistroConcepto=$ResultadoConcepto->fetch_assoc()){
        foreach($ResultadoConcepto as $RegistroConcepto){
        //precode($RegistroConcepto,1);
            $i++;
            if($i != 1){
                if( empty($RegistroConcepto->Adicional) ){
                    //Es concepto
                    $ConceptoPadre[$indexConcepto]['Total']=$totalConcepto;
        
                    $totalConcepto=$RegistroConcepto->total ;
                    $Contador=0;
                    $ConceptoActual++;
                    $indexConcepto++;
        
                    $ConceptoPadre[$indexConcepto]['id']=$RegistroConcepto->idConceptoCotizacion;
                    $ConceptoPadre[$indexConcepto]['TipoPredio']=$RegistroConcepto->Tipo;
                    $ConceptoPadre[$indexConcepto]['Mes']=$RegistroConcepto->Mes;
                    $ConceptoPadre[$indexConcepto]['A_no']=$RegistroConcepto->A_no;
        
                    $ConceptoPadre[$indexConcepto]['idConcepto']=$RegistroConcepto->ConceptoCobro;
                    $ConceptoPadre[$indexConcepto]['Nombre']=$RegistroConcepto->NombreConcepto;
                    $ConceptoPadre[$indexConcepto]['FechaConcepto']=$RegistroConcepto->A_no."-".$RegistroConcepto->Mes."-01";
        
                }else{ //Es adicional
                    $totalConcepto +=$RegistroConcepto->total ;
                }
                $Contador++;
            }
        }
        
        $ConceptoPadre[$indexConcepto]['Total']=$totalConcepto;

        #return $ConceptoPadre;
        
        //Buscamos actualizaciones y recargos para los conceptos a pagar
        $ActualizacionesYRecargos="";
        $PagoActualizaciones=0;
        $sumatotalActyRec=0;
        
        $fechaActual = date("Y-m-d");
        $auxMes = array("", "Enero", "Febrero","Marzo","Abril","Mayo","Junio","Julio", "Agosto","Septiembre","Octubre","Noviembre","Diciembre"); 
    
        for($iC=0; $iC<count($ConceptoPadre); $iC++){
            $ConceptoExcluidosParaActualizacionesRecargos = array(5461, 5462,5467, 5469, 2489, 5084);
            if (!in_array($ConceptoPadre[$iC]['id'], $ConceptoExcluidosParaActualizacionesRecargos)) {
            
                //Obtenemos las actualizaciones y recargos.
                if($ConceptoPadre[$iC]['FechaConcepto']!="--01"){
                    if(date("Y-m", strtotime( $fechaActual ) ) > date('Y-m', strtotime($ConceptoPadre[$iC]['FechaConcepto']))){
                        //Obtenemos las multas del concepto
                        $ConsultaMultas = " SELECT mi.Categor_ia as Categoria, m.id as idMulta, m.Descripci_on as DescripcionMulta, c.id as idConcepto, c.Descripci_on as DescripcionConcepto
                        FROM MultaCategor_ia mi 
                        INNER JOIN Multa m ON ( mi.Multa = m.id  )  
                        INNER JOIN ConceptoCobroCaja c ON ( mi.Concepto = c.id  )  
                        WHERE mi.Categor_ia = (select Categor_ia from ConceptoCobroCaja where id = ".$ConceptoPadre[$iC]['id'].")";
                        #precode($ConsultaMultas,1);
                        
                        $ResultadoMultas = DB::select($ConsultaMultas);
                        #$ResultadoMultas=$Conexion->query($ConsultaMultas);
    
                        foreach($ResultadoMultas as $RegistroMultas){
                        #while($RegistroMultas=$ResultadoMultas->fetch_assoc()){
                            $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);
                            $elmes=$fechainicial[1];
                            $elanio=$fechainicial[0];	
    
                            if($RegistroMultas->idMulta == 1){
                                //Es Actualizacion
    
                                if($ConceptoPadre[$iC]['TipoPredio']==3)//3 es para predial
                                {
                                    
                                }// termina tipo 3 predial
                                else{
                                    //Caso normal que no es predial
                                    $fechainicial  = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);
                                    $montoconcepto =  $ConceptoPadre[$iC]['Total'];   
                                    $mes  = ($fechainicial[1])+1; 
                                    $anio = $fechainicial[0];

                                    if(intval($mes)>12){
                                        $mes  = 1;
                                        $anio = $anio+1;
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
    
                                    $fechaVencimiento = $anio."-".$mes."-".$dia;
                                    $fecha_actual     = strtotime(date("Y-m-d H:i:00",time()));
                                    $fecha_entrada    = strtotime(date($fechaVencimiento." H:i:00"));
                                    if($fecha_actual > $fecha_entrada){
                                        $actualizacionesOK = Funciones::CalculoActualizacionFecha1($fechaVencimiento, $montoconcepto, $fechaActual );
                                    }else{
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
                                    
                                }// termina tipo 3 predial
                                else{
                                    //Caso normal que no es predial
                                    $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);
                                    $montoconcepto=  $ConceptoPadre[$iC]['Total'];   
                                    //precode($RegistroConcepto,1);     
                                    $mes=($fechainicial[1])+1; 
                                    $anio=$fechainicial[0];
    
                                    
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
                                        $fechaVencimiento.$montoconcepto;
                                        $recargosOK	= Funciones::CalculoRecargosFechaAgua($fechaVencimiento, $montoconcepto, $fechaActual, $cliente );
                                        //$actualizacionesOK = CalculoActualizacion($fechaVencimiento, $montoconcepto);
                                    }else{
                                        $recargosOK		   = 0;
                                    }
                                }
                            
                                $recargosActualizaciones[] = ( round( $actualizacionesOK, 2) + round( $recargosOK, 2 ) );
                            }
                        }
                    }
                }//if si es fecha valida
            }
        }//for
        
        return array_sum($recargosActualizaciones);
    }

    public function getImagen(Request $request)
    {
        $cliente = $request->Cliente;
        Funciones::selecionarBase($cliente);
  
        $ClienteImagen = Cliente::select( 
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

    public function getClientes(Request $request){
         
        $cliente=Funciones::DecodeThis2($request->Cliente);
        //return $cliente;
        if($cliente==="cliente!@$"){
            
            $Cliente = Cliente::select('id', 'Descripci_on','nombre')
                ->where('Estatus', '1')
                ->orderBy('Descripci_on','asc')
                ->get();
                //return $cliente;
                return response()->json([
                    'success' => '1',
                    'cliente'=> $Cliente
                ], 200);
        }else{
            return response()->json([
                'success' => '0',
            ], 300);
        }
    }
    public function getClienteNombre(Request $request){
         
               $cliente=$request->Cliente;
               /* $Cliente = Cliente::select('Descripci_on')
                ->where('id',$cliente)
                ->first();*/
                //return $cliente;
                $ClienteRelacion= DB::table('ClienteDatos')
                ->where('Cliente', $cliente)
                ->where('Indice','ConectarBD')
                ->value('valor');

                Funciones::conexionGeneral('suinpac_general');

                $EsMunicipio = DB::table('Cliente')
                ->where('id',$cliente)
                ->value('EsMunicipio');

                return response()->json([
                    'success' => '1',
                    'clienteRelacion'=>$ClienteRelacion,
                    'esMunicipio'=>$EsMunicipio
                ], 200);
    }

    public function getClienteByNombre(Request $request){
         
        $cliente=$request->Cliente;
        
            $ClienteRelacion="";
            $EsMunicipio="";
            $Cliente = Cliente::select('id','Descripci_on')
                ->where('nombre',$cliente)
                ->first();
               
            if($Cliente!=null){
              $ClienteRelacion= DB::table('ClienteDatos')
                ->where('Cliente', $Cliente->id)
                ->where('Indice','ConectarBD')
                ->value('valor');

                Funciones::conexionGeneral('suinpac_general');

                $EsMunicipio = DB::table('Cliente')
                ->where('id',$Cliente->id)
                ->value('EsMunicipio');
               
            }
                 //return $cliente;
                return response()->json([
                    'success' => '1',
                    'cliente'=> $Cliente,
                    'clienteRelacion'=>$ClienteRelacion,
                    'esMunicipio'=>$EsMunicipio
                ], 200);
    }
   
    public function pagoCaja(Request $request){
      
      
        $cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
       
        Funciones::selecionarBase($cliente);

        $Cotizaciones;
        $resultadoCotizaciones;
        $SaldosActuales;
        $ResultadoConcepto;
        $T_otalImporte = 0;
        $T_otalActualizaciones = 0;
        $T_otalRecargos = 0;
        $T_otalDescuentos = 0;
        $T_otalSaldoDescontado = 0;
        $T_otal =0;
        $DescuentoGeneralCotizaciones=0;
        $SaldoDescontadoGeneralTodo =0;
        //$DatosConceptos=array();
        $FilaConceptos="";
        $claveAgrupar="";
        $aux="";
        $subtotal;
        $c;
        $DatosConceptos;
       
        $auxiliarCondicion ="";
      
        if($tipoServicio==1){//1 es agua
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='2019' AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";
          
        }else if($tipoServicio==2){//2 agua
            if($cliente==29)
            $auxiliarCondicion=" AND c.FechaLimite IS NULL";
 
         $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
         FROM Cotizaci_on c
        WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='2019' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2019).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
       
        }
        
        //aqui falta revisar a detalle
      /*
       $Cotizaciones = DB::table('Cotizaci_on')->select( DB::raw('id as Cotizaci_ones') )
       ->where('Padr_on', $idPadron)
       ->where(DB::raw('( SELECT sum( importe ) FROM ConceptoAdicionalesCotizaci_on ca WHERE ca.Cotizaci_on = Cotizaci_on.id AND ca.Padre IS NULL AND ca.A_no ='.$fecha[0].' AND ca.Mes = '.$fecha[1].')'), '>', '0')
       ->groupBy('Padr_on')
       ->orderBy('id', 'A')
       ->value('Cotizaci_ones');*/
       

       $resultadoCotizaciones=DB::select($consultaCotizaciones);
       
       if($cliente==35||$cliente==29){
        return FuncionesCaja::descuentoPredial($resultadoCotizaciones,$cliente,2019);

      }else{
        return FuncionesCaja::cajaSinDescuento($resultadoCotizaciones,$cliente);
      }
     
     

        //$DatosConceptos[]

    
     
    //return FuncionesCaja::Cotizaci_onAguaOPDPagarActualizarGeneraTicket2($DatosConceptos,$T_otal,$cliente);
    
        
            /*$Cliente = Cliente::select('id','Descripci_on')
                ->where('nombre',$cliente)
                ->first();
                //return $cliente;
                return response()->json([
                    'success' => '1',
                    'cliente'=> $Cliente
                ], 200);*/
    }
 

    public static function postSuinpacCaja(Request $request){
        $cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $importe=$request->Importe;
        $autorizacion=$request->Autorizacion;
        $referencia=$request->Referencia;
        $folio=$request->Folio;
        /*$cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $idCotizaciones=$request->IdCotizaciones;*/
        
        Funciones::selecionarBase($cliente);
        $DatosConceptos;
       
        $auxiliarCondicion ="";
        
        if($tipoServicio==1){//1 es agua
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='2019' AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";
          
        }else if($tipoServicio==3){//2 predial
            if($cliente==29)
            $auxiliarCondicion=" AND c.FechaLimite IS NULL";
 
         $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
         FROM Cotizaci_on c
        WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2019).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
       
        }
        
       $resultadoCotizaciones=DB::select($consultaCotizaciones);
      
       
        $url = 'https://suinpac.piacza.com.mx/PagoCajaVirtualPortal.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "Cotizaciones"=>$resultadoCotizaciones,
                "Importe"=>$importe,
                "Autorizacion"=>$autorizacion,
                "Referencia"=>$referencia,
                "Folio"=>$folio
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
            'success' => $result
        ], 200);
    }

    public static function postSuinpacCajaNuevo(Request $request){
        $cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $importe=$request->Importe;
        $autorizacion=$request->Autorizacion;
        $referencia=$request->Referencia;
        $folio=$request->Folio;
        /*$cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $idCotizaciones=$request->IdCotizaciones;*/

        Funciones::selecionarBase($cliente);
        $DatosConceptos;

        $auxiliarCondicion ="";

        if($tipoServicio==1){//1 es agua
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='2019' AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";

        }else if($tipoServicio==3){//2 predial
            if($cliente==29)
                $auxiliarCondicion=" AND c.FechaLimite IS NULL";

            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
         FROM Cotizaci_on c
        WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2019).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";

        }

        $resultadoCotizaciones=DB::select($consultaCotizaciones);


        $url = 'https://suinpac.piacza.com.mx/PagoCajaVirtualPortal.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "Cotizaciones"=>$resultadoCotizaciones,
                "Importe"=>$importe,
                "Autorizacion"=>$autorizacion,
                "Referencia"=>$referencia,
                "Folio"=>$folio
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
            'success' => $result,
            'Cotizaciones'=> $resultadoCotizaciones,
        ], 200);
    }


    public static function postSuinpacCajaListaAdeudo(Request $request){
        $cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $cotizacioServicio=$request->CotizacioServicio;
        /*$cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $idCotizaciones=$request->IdCotizaciones;*/
        Funciones::selecionarBase($cliente);
        $DatosConceptos;
       
        $auxiliarCondicion ="";
        
        if($tipoServicio==1){//1 es agua
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
                                    FROM Cotizaci_on c
                                    WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='2019' AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";
          
            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }else if($tipoServicio==3){//3 predial 
            if($cliente==29)
                $auxiliarCondicion=" AND c.FechaLimite IS NULL";
 
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
                                    FROM Cotizaci_on c
                                    WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2019).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
       
            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        } else  if($tipoServicio==4){//servicios predial
            $miArray = array("id"=>$cotizacioServicio);
            $miArray2 =array($miArray);
            $resultadoCotizaciones=$miArray2;
          
       }
       
        $url = 'https://suinpac.piacza.com.mx/PagoCajaVirtualVerificacion.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente2"=>$cliente,
                "Cotizaciones"=>$resultadoCotizaciones
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
            'total'=> $result,
            'ss'=>$resultadoCotizaciones
        ], 200);
    }
 
   public static function listadoAdeudoPagar(Request $request){
    
        $cliente = $request->Cliente;
        $idPadron = $request->IdPadron;
        $tipo= $request->TipoServicio;
        


        #return $request;
        Funciones::selecionarBase($cliente);
        $countConceptos=DB::select("SELECT COUNT(c.id) as total FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN Cotizaci_on c ON(c.id=cac.Cotizaci_on) WHERE c.Tipo IN (".$tipo.") and c.Padr_on=".$idPadron." and cac.Estatus=0 and c.FechaLimite IS NULL GROUP BY cac.A_no,cac.Mes ORDER BY cac.A_no DESC,cac.Mes DESC");
        $mostrar2020=true;

       
        foreach($countConceptos as $concepto){
            
            //si hay cotizaciones 2019 no se muestra 2020
            if($concepto->total>0){
               $mostrar2020=false;
            }
        }
       if($mostrar2020){

                $conceptos="SELECT
                    GROUP_CONCAT( cac.id ) AS Conceptos,
                    cac.A_no,
                    cac.Mes,
                    SUM( cac.Importe ) AS Importe,
                    ( SELECT id FROM Padr_onCatastralHistorial WHERE Padr_onCatastral = c.Padr_on AND A_no = cac.A_no AND Mes = cac.Mes LIMIT 0, 1 ) AS IdLectura 
                FROM
                    Cotizaci_on c
                    INNER JOIN ConceptoAdicionalesCotizaci_on cac ON ( c.id = cac.Cotizaci_on ) 
                WHERE
                    c.Tipo IN (".$tipo.")
                    AND c.Padr_on = ".$idPadron ." 
                    AND cac.Estatus = 0
                   
                GROUP BY
                    cac.A_no,
                    cac.Mes 
                ORDER BY
                    cac.A_no DESC,
                    cac.Mes DESC";

                $conceptos = preg_replace("/[\r\n|\n|\r]+/", " ", $conceptos);
                $conceptos=DB::select($conceptos);
       // $conceptos=DB::select("SELECT  GROUP_CONCAT(cac.id) as Conceptos,cac.A_no,cac.Mes,SUM(cac.Importe) as Importe FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN Cotizaci_on c ON(c.id=cac.Cotizaci_on) WHERE c.Tipo IN (".$tipo.") and c.Padr_on=".$idPadron." and cac.Estatus=0 GROUP BY cac.A_no,cac.Mes ORDER BY cac.A_no DESC,cac.Mes DESC");

       }else{

                    $conceptos="SELECT
                    GROUP_CONCAT( cac.id ) AS Conceptos,
                    cac.A_no,
                    cac.Mes,
                    SUM( cac.Importe ) AS Importe,
                    ( SELECT id FROM Padr_onCatastralHistorial WHERE Padr_onCatastral = c.Padr_on AND A_no = cac.A_no AND Mes = cac.Mes LIMIT 0, 1 ) AS IdLectura 
                FROM
                    Cotizaci_on c
                    INNER JOIN ConceptoAdicionalesCotizaci_on cac ON ( c.id = cac.Cotizaci_on ) 
                WHERE
                    c.Tipo IN (".$tipo.")
                    AND c.Padr_on = ".$idPadron ." 
                    AND cac.Estatus = 0
                    and cac.A_no<=".date("Y")."
                GROUP BY
                    cac.A_no,
                    cac.Mes 
                ORDER BY
                    cac.A_no DESC,
                    cac.Mes DESC";

                $conceptos = preg_replace("/[\r\n|\n|\r]+/", " ", $conceptos);
                $conceptos=DB::select($conceptos);
           // $conceptos=DB::select("SELECT  GROUP_CONCAT(cac.id) as Conceptos,cac.A_no,cac.Mes,SUM(cac.Importe) as Importe FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN Cotizaci_on c ON(c.id=cac.Cotizaci_on) WHERE c.Tipo IN (".$tipo.") and c.Padr_on=".$idPadron." and cac.Estatus=0 and cac.A_no=".date("Y")." GROUP BY cac.A_no,cac.Mes ORDER BY cac.A_no DESC,cac.Mes DESC");

       }
        
    
    return response()->json([
        'success' => '1',
        'cliente'=> $conceptos
    ], 200);
   }

   public static function comprobanteDePago(Request $request){
    $cliente=$request->Cliente;
    $idTiket=$request->IdTiket;
   // $referencia=$request->Referencia;
  //  $autorizacion=$request->Autorizacion;
    
     Funciones::selecionarBase($cliente);

     $datosTicket=Funciones::ObtenValor("SELECT * FROM PagoTicket WHERE id=$idTiket");

     $_POST=json_decode($datosTicket->Variables,true);
    
     $_POST['PagoAnticipado'] = $_POST['PagoAnticipado']>0?str_replace(",", "",$_POST['PagoAnticipado']):0;
     $datosTicket->Descuentos= $datosTicket->Descuentos>0?str_replace(",", "",$datosTicket->Descuentos):0;
     if(!isset($_POST['DescuentoCupon']) && $_POST['DescuentoCupon']=="")
         $_POST['DescuentoCupon']=0;
     $DescuentoGeneralizado = FuncionesCaja::LimpiarNumeroV2($datosTicket->Descuentos);#- LimpiarNumero( $_POST['Redondeo']);
             $arrCotizacion			= array();
            
             $DescuentoTotal = 0;
            
            $datosTicket->Descuentos = $datosTicket->Descuentos;#-$_POST['Redondeo'];
            $DesglosarDescuento = array(1=>0,2=>0,3=>0,4=>0,5=>0);
                $Contribuyentes= array();
               
        foreach($_POST['cpnp']  as $conceptos){
            $concepto=explode(",", $conceptos);
                    
                    if($concepto[15] == 1){ #Servicio
                        $arregloServicio['Concepto'.$concepto[1]][]=$concepto;
                        $arregloServicio['ContribuyenteServicio'][] = $concepto[1];
                        $DescuentoTotal = FuncionesCaja::LimpiarNumeroV2($DescuentoTotal);
                        $DescuentoTotal += FuncionesCaja::LimpiarNumeroV2($concepto[7]);
                        $DesglosarDescuento[1]+= $concepto[9];
                        $arregloServicio['SubtotalContribuyente'.$concepto[1]][]=$concepto[9];
                        $Contribuyentes[]=$concepto[1];// Contribuyente cuando es servicio
                        
                        
                     }else if($concepto[15] == 3){ #Predial
                        $arreglo['Predial'.$concepto[12]][] = $concepto;
                        $arreglo['Contribuyente'.$concepto[12]][] = $concepto[1];
                        $arreglo['PadronPredial'][] = $concepto[12];
                        $arreglo['Tipo'][] = "Padr_onPredial";
                        $datosTicket->Descuentos = FuncionesCaja::LimpiarNumeroV2($datosTicket->Descuentos);
                        $datosTicket->Descuentos=$datosTicket->Descuentos-$concepto[7]-$concepto[8];
                        $DescuentoTotal = FuncionesCaja::LimpiarNumeroV2($DescuentoTotal+$concepto[8]);
                        $DescuentoTotal += FuncionesCaja::LimpiarNumeroV2($concepto[7]);
                        $DesglosarDescuento[3]+= $concepto[9];
                        $SubtotalParte['SubtotalPadr_on'.$concepto[12]][]=$concepto[9];
                        $Contribuyentes[]="Predial".$concepto[12];// Padr_on Cuando  cuando es Predial
                        
                    }else if($concepto[15] == 9 || $concepto[15] == 2){ #Agua Potable
                        
                        $arreglo['AguaPotable'.$concepto[12]][] = $concepto;
                        $arreglo['Contribuyente'.$concepto[12]][] = $concepto[1];
                        $arreglo['Padron'][] = $concepto[12];
                        $arreglo['Tipo'][] = "Padr_onAguaPotable";
                        $datosTicket->Descuentos = FuncionesCaja::LimpiarNumeroV2($datosTicket->Descuentos);
                        $datosTicket->Descuentos=$datosTicket->Descuentos-$concepto[7]-$concepto[8];
                        $DescuentoTotal = FuncionesCaja::LimpiarNumeroV2($DescuentoTotal+$concepto[8]);
                        $DescuentoTotal += FuncionesCaja::LimpiarNumeroV2($concepto[7]);
                        $DesglosarDescuento[3]+= $concepto[9];
                        $SubtotalParte['SubtotalPadr_on'.$concepto[12]][]=$concepto[9];
                        $Contribuyentes[]="Agua".$concepto[12];// Padr_on Cuando  cuando es Agua OPD
                        
                    }else if($concepto[15] == 10){ #Convenios
                        
                        $arreglo['Convenios'][]=$conceptos; 
                        $DesglosarDescuento[4]+= $concepto[9];
                        
                    }else if($concepto[15] == 11){ #ISAI
                        $arreglo['PredialISAI'.$concepto[12]][] = $concepto;
                        $arreglo['Contribuyente'.$concepto[12]][] = $concepto[1];
                        $arreglo['PadronPredialISAI'][] = $concepto[12];
                        $arreglo['Tipo'][] = "Padr_onPredialISA";
                        $datosTicket->Descuentos = FuncionesCaja::LimpiarNumeroV2($datosTicket->Descuentos);
                        $datosTicket->Descuentos=$datosTicket->Descuentos-$concepto[7]-$concepto[8];
                        $DescuentoTotal = FuncionesCaja::LimpiarNumeroV2($DescuentoTotal+$concepto[8]);
                        $DescuentoTotal += FuncionesCaja::LimpiarNumeroV2($concepto[7]);
                        $DesglosarDescuento[3]+= $concepto[9];
                        $SubtotalParte['SubtotalPadr_on'.$concepto[12]][]=$concepto[9];
                        $Contribuyentes[]="ISAI".$concepto[12];// Padr_on Cuando  cuando es ISAI
                          
                    }else if($concepto[15] == 4){ #Licencia
                        $arreglo['Licencia'.$concepto[12]][] = $concepto;
                        $arreglo['Contribuyente'.$concepto[12]][] = $concepto[1];
                        $arreglo['Licencia'][] = $concepto[12];
                        $arreglo['Tipo'][] = "Licencia";
                        $datosTicket->Descuentos = FuncionesCaja::LimpiarNumeroV2($datosTicket->Descuentos);
                        $datosTicket->Descuentos=$datosTicket->Descuentos-$concepto[7]-$concepto[8];
                        $DescuentoTotal = FuncionesCaja::LimpiarNumeroV2($DescuentoTotal+$concepto[8]);
                        $DescuentoTotal += FuncionesCaja::LimpiarNumeroV2($concepto[7]);
                        $DesglosarDescuento[3]+= $concepto[9];
                        $SubtotalParte['SubtotalPadr_on'.$concepto[12]][]=$concepto[9];
                        $Contribuyentes[]="Licencia".$concepto[12];// Padr_on Cuando  cuando es Predial
                          
                    }
                 else if($concepto[15] == 12 && 1==2){ #Licencia de Construcci_on
                        $arregloServicio['Concepto'.$concepto[1]][]=$concepto;
                        $arregloServicio['ContribuyenteServicioLicencia'][] = $concepto[1];
                        $DescuentoTotal = FuncionesCaja::LimpiarNumeroV2($DescuentoTotal);
                        $DescuentoTotal += FuncionesCaja::LimpiarNumeroV2($concepto[7]);
                        $DesglosarDescuento[1]+= $concepto[9];
                        $arregloServicio['SubtotalContribuyenteLicencia'.$concepto[1]][]=$concepto[9];
                        $Contribuyentes[]=$concepto[1];// Contribuyente cuando es servicio
                        
                        
                     }else if($concepto[15] == 12){ #Licencia Construcci_on
                        $arreglo['LicenciaContrucci_on'.$concepto[12]][] = $concepto;
                        $arreglo['Contribuyente'.$concepto[12]][] = $concepto[1];
                        $arreglo['LicenciaContrucci_on'][] = $concepto[12];
                        $arreglo['Tipo'][] = "LicenciaContrucci_on";
                        $datosTicket->Descuentos = FuncionesCaja::LimpiarNumeroV2($datosTicket->Descuentos);
                        $datosTicket->Descuentos=$datosTicket->Descuentos-$concepto[7]-$concepto[8];
                        $DescuentoTotal = FuncionesCaja::LimpiarNumeroV2($DescuentoTotal+$concepto[8]);
                        $DescuentoTotal += FuncionesCaja::LimpiarNumeroV2($concepto[7]);
                        $DesglosarDescuento[3]+= $concepto[9];
                        $SubtotalParte['SubtotalPadr_on'.$concepto[12]][]=$concepto[9];
                        $Contribuyentes[]="LicenciaContrucci_on".$concepto[12];// Padr_on Cuando  cuando es Predial
                          
                    }
             
                    
        }
            $Contribuyentes = array_unique($Contribuyentes);
            $NoRecibos =  count($Contribuyentes); 
             #precode($NoRecibos,1);
            $DescuentoGeneralizado = $DescuentoGeneralizado - $DescuentoTotal;
            $Descuentos = FuncionesCaja::ProrratiarDescuentoGeneral($DescuentoGeneralizado, $DesglosarDescuento);
           
              $Recibos = "";
              ;
           if(isset($arreglo['Padron']) && isset($arreglo['Tipo']))
               {    
                    $arraySubtotalPadron=array();
                    $arrPadr_on = array_unique($arreglo['Padron']);
                    
                    foreach ($arrPadr_on as $key => $Padr_on)
                   
                         $arraySubtotalPadron[$Padr_on]=array_sum(FuncionesCaja::LimpiarNumero($SubtotalParte['SubtotalPadr_on'.$Padr_on]));
                         $TotalPadrones = array_sum(FuncionesCaja::LimpiarNumero($arraySubtotalPadron));
                      foreach ($arrPadr_on as $key => $Padr_on)
                      {     if($Descuentos[2]>0)
                            $arrayDescuentoIndividual[$Padr_on][] = (FuncionesCaja::LimpiarNumeroV2($Descuentos[2]) / FuncionesCaja::LimpiarNumeroV2($TotalPadrones)) * FuncionesCaja::LimpiarNumeroV2($arraySubtotalPadron[$Padr_on]); 
                      else
                       $arrayDescuentoIndividual[$Padr_on][] = 0;
                      }
                     $arrayDescuentoIndividual= FuncionesCaja::ProrratiarDescuentoIndividual(FuncionesCaja::LimpiarNumeroV2($Descuentos[2]), $arrayDescuentoIndividual);
                     #precode($arrayDescuentoIndividual,1);
                      $Totales = 0;
                     $keys = array_keys($arrPadr_on);
                    foreach ($arrPadr_on as $key => $Padr_on) {
                        $arrContribuyente = array_unique($arreglo['Contribuyente'.$Padr_on]);
                        $arr = FuncionesCaja::ReciboAguaPotable($idTiket,$arreglo['AguaPotable'.$Padr_on], $Padr_on, $arrContribuyente, $datosTicket->Pago,$datosTicket->Cliente,$arrayDescuentoIndividual[$Padr_on]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);  
                        $Recibos.= $arr['html'];
                        $NoRecibos=$arr['NoRecibos'];
                    }
             
               } 
            if(isset($arregloServicio['ContribuyenteServicio'])) {
                    $arrContribuyente = array_unique($arregloServicio['ContribuyenteServicio']);
                    $arraySubtotalCotizaci_on=array();
                      foreach ($arrContribuyente as $key => $Contribuyente)
                         $arrayDescuentoIndividual[$Contribuyente]=array_sum($arregloServicio['SubtotalContribuyente'.$Contribuyente]);
                         $TotalPadrones = array_sum($arrayDescuentoIndividual);
                     foreach ($arrContribuyente as $key => $Contribuyente){
                            if($Descuentos[0]>0)
                                $arraySubtotalCotizaci_on[$Contribuyente][] = ($Descuentos[0] / $TotalPadrones) * $arrayDescuentoIndividual[$Contribuyente];
                            else 
                                $arraySubtotalCotizaci_on[$Contribuyente][] = 0;
                     }
                      $arrayDescuentoIndividual= FuncionesCaja::ProrratiarDescuentoIndividual($Descuentos[0], $arraySubtotalCotizaci_on);
                      foreach ($arrContribuyente as $key => $Contribuyente) {
                         $arr= ReciboDeServicioGeneral($arregloServicio['Concepto'.$Contribuyente], $Contribuyente, $datosTicket->Pago,$datosTicket->Cliente,$arrayDescuentoIndividual[$Contribuyente]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);  
                         $Recibos.= $arr['html'];
                        $NoRecibos=$arr['NoRecibos'];
                  }
                }
               if(isset($arreglo['PadronPredial']) && isset($arreglo['Tipo']))
               {    
                    $arraySubtotalPadron=array();
                    $arrPadr_on = array_unique($arreglo['PadronPredial']);
                    foreach ($arrPadr_on as $key => $Padr_on)
                         $arraySubtotalPadron[$Padr_on]=array_sum(FuncionesCaja::LimpiarNumero($SubtotalParte['SubtotalPadr_on'.$Padr_on]));
                         $TotalPadrones = array_sum(FuncionesCaja::LimpiarNumero($arraySubtotalPadron));
                      foreach ($arrPadr_on as $key => $Padr_on)
                            $arrayDescuentoIndividual[$Padr_on][] = (FuncionesCaja::LimpiarNumero($Descuentos[2]) / FuncionesCaja::LimpiarNumero($TotalPadrones)) * FuncionesCaja::LimpiarNumero($arraySubtotalPadron[$Padr_on]); 
                
                     $arrayDescuentoIndividual= FuncionesCaja::ProrratiarDescuentoIndividual(FuncionesCaja::LimpiarNumero($Descuentos[2]), $arrayDescuentoIndividual);
                     #precode($arrayDescuentoIndividual,1);
                      $Totales = 0;
                     $keys = array_keys($arrPadr_on);
                    foreach ($arrPadr_on as $key => $Padr_on) {
                        $arrContribuyente = array_unique($arreglo['Contribuyente'.$Padr_on]);
                        $arr = FuncionesCaja::ReciboPredial($idTiket,$arreglo['Predial'.$Padr_on], $Padr_on, $arrContribuyente, $datosTicket->Pago,$datosTicket->Cliente,$arrayDescuentoIndividual[$Padr_on]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);  
                        $Recibos.= $arr['html'];
                        $NoRecibos=$arr['NoRecibos'];
                        #precode($NoRecibos,1);
                    }
             
               }
               
                 if(isset($arreglo['PadronPredialISAI']) && isset($arreglo['Tipo']))
               {    
                    $arraySubtotalPadron=array();
                    $arrPadr_on = array_unique($arreglo['PadronPredialISAI']);
                    $arrayDescuentoIndividual=array();
                    foreach ($arrPadr_on as $key => $Padr_on)
                         $arraySubtotalPadron[$Padr_on]=array_sum(LimpiarNumero($SubtotalParte['SubtotalPadr_on'.$Padr_on]));
                         $TotalPadrones = array_sum(LimpiarNumero($arraySubtotalPadron));
                      foreach ($arrPadr_on as $key => $Padr_on){
                          if($Descuentos[2]>0)
                            $arrayDescuentoIndividual[$Padr_on][] =((floatval(LimpiarNumero($Descuentos[2])) / floatval(LimpiarNumero($TotalPadrones))) * floatval(LimpiarNumero($arraySubtotalPadron[$Padr_on]))); 
                          else
                              $arrayDescuentoIndividual[$Padr_on][] =0;
                      }
                      #precode($arrayDescuentoIndividual,1,1);
                     $arrayDescuentoIndividual= ProrratiarDescuentoIndividual(LimpiarNumero($Descuentos[2]), $arrayDescuentoIndividual);
                     #precode($arrayDescuentoIndividual,1);
                      $Totales = 0;
                     $keys = array_keys($arrPadr_on);
                    foreach ($arrPadr_on as $key => $Padr_on) {
                        $arrContribuyente = array_unique($arreglo['Contribuyente'.$Padr_on]);
                        $arr = ReciboPredialISAI($arreglo['PredialISAI'.$Padr_on], $Padr_on, $arrContribuyente, $datosTicket['Pago'],$datosTicket['Cliente'],$arrayDescuentoIndividual[$Padr_on]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);  
                        $Recibos.= $arr['html'];
                        $NoRecibos=$arr['NoRecibos'];
                    }
             
               }
               
                if(isset($arreglo['Licencia']) && isset($arreglo['Tipo']))
               {    
                    $arraySubtotalPadron=array();
                    $arrPadr_on = array_unique($arreglo['Licencia']);
                    foreach ($arrPadr_on as $key => $Padr_on)
                         $arraySubtotalPadron[$Padr_on]=array_sum(LimpiarNumero($SubtotalParte['SubtotalPadr_on'.$Padr_on]));
                         $TotalPadrones = array_sum(LimpiarNumero($arraySubtotalPadron));
                      foreach ($arrPadr_on as $key => $Padr_on){
                          if($Descuentos[2]>0)
                            $arrayDescuentoIndividual[$Padr_on][] = (LimpiarNumero($Descuentos[2]) / LimpiarNumero($TotalPadrones)) * LimpiarNumero($arraySubtotalPadron[$Padr_on]); 
                          else
                              $arrayDescuentoIndividual[$Padr_on][] =0;
                          
                      }
                     $arrayDescuentoIndividual= ProrratiarDescuentoIndividual(LimpiarNumero($Descuentos[2]), $arrayDescuentoIndividual);
                     #precode($arrayDescuentoIndividual,1);
                      $Totales = 0;
                     $keys = array_keys($arrPadr_on);
                    foreach ($arrPadr_on as $key => $Padr_on) {
                        $arrContribuyente = array_unique($arreglo['Contribuyente'.$Padr_on]);
                        $arr = ReciboLicenciaFuncionamiento($arreglo['Licencia'.$Padr_on], $Padr_on, $arrContribuyente, $datosTicket['Pago'],$datosTicket['Cliente'],$arrayDescuentoIndividual[$Padr_on]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);  
                        $Recibos.= $arr['html'];
                        $NoRecibos=$arr['NoRecibos'];
                    }
             
               }
                if(isset($arregloServicio['ContribuyenteServicioLicencia'])) {
                    $arrContribuyente = array_unique($arregloServicio['ContribuyenteServicioLicencia']);
                    $arraySubtotalCotizaci_on=array();
                      foreach ($arrContribuyente as $key => $Contribuyente)
                         $arrayDescuentoIndividual[$Contribuyente]=array_sum($arregloServicio['SubtotalContribuyenteLicencia'.$Contribuyente]);
                         $TotalPadrones = array_sum($arrayDescuentoIndividual);
                     foreach ($arrContribuyente as $key => $Contribuyente){
                            if($Descuentos[0]>0)
                                $arraySubtotalCotizaci_on[$Contribuyente][] = ($Descuentos[0] / $TotalPadrones) * $arrayDescuentoIndividual[$Contribuyente];
                            else 
                                $arraySubtotalCotizaci_on[$Contribuyente][] = 0;
                     }
                      $arrayDescuentoIndividual= ProrratiarDescuentoIndividual($Descuentos[0], $arraySubtotalCotizaci_on);
                      foreach ($arrContribuyente as $key => $Contribuyente) {
                         $arr= ReciboDeServicioGeneral($arregloServicio['Concepto'.$Contribuyente], $Contribuyente, $datosTicket['Pago'],$datosTicket['Cliente'],$arrayDescuentoIndividual[$Contribuyente]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);  
                         $Recibos.= $arr['html'];
                        $NoRecibos=$arr['NoRecibos'];
                  }
                }
                
                             if(isset($arreglo['LicenciaContrucci_on']) && isset($arreglo['Tipo']))
               {    
                    $arraySubtotalPadron=array();
                    $arrPadr_on = array_unique($arreglo['LicenciaContrucci_on']);
                    $arrayDescuentoIndividual=array();
                    foreach ($arrPadr_on as $key => $Padr_on)
                         $arraySubtotalPadron[$Padr_on]=array_sum(FuncionesCaja::LimpiarNumeroV2($SubtotalParte['SubtotalPadr_on'.$Padr_on]));
                         $TotalPadrones = array_sum(FuncionesCaja::LimpiarNumeroV2($arraySubtotalPadron));
                      foreach ($arrPadr_on as $key => $Padr_on){
                          if($Descuentos[2]>0)
                            $arrayDescuentoIndividual[$Padr_on][] =((floatval(FuncionesCaja::LimpiarNumeroV2($Descuentos[2])) / floatval(FuncionesCaja::LimpiarNumeroV2($TotalPadrones))) * floatval(FuncionesCaja::LimpiarNumeroV2($arraySubtotalPadron[$Padr_on]))); 
                          else
                              $arrayDescuentoIndividual[$Padr_on][] =0;
                      }
                      #precode($arrayDescuentoIndividual,1,1);
                     $arrayDescuentoIndividual= FuncionesCaja::ProrratiarDescuentoIndividual(FuncionesCaja::LimpiarNumeroV2($Descuentos[2]), $arrayDescuentoIndividual);
                     return $arrayDescuentoIndividual;
                      $Totales = 0;
                     $keys = array_keys($arrPadr_on);
                    foreach ($arrPadr_on as $key => $Padr_on) {
                        $arrContribuyente = array_unique($arreglo['Contribuyente'.$Padr_on]);
                        $arr = ReciboLicenciaContrucci_on($arreglo['LicenciaContrucci_on'.$Padr_on], $Padr_on, $arrContribuyente, $datosTicket['Pago'],$datosTicket['Cliente'],$arrayDescuentoIndividual[$Padr_on]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);  
                        $Recibos.= $arr['html'];
                        $NoRecibos=$arr['NoRecibos'];
                    }
             
               }
                
      
        
     #print $Recibos; exit();
    
     $HTML=$Recibos;
     
   
    
    
    $rutacompleta="";
         
    include( app_path() . '/Libs/Wkhtmltopdf.php' );
    try {
        $nombre = uniqid() . "_" . $idTiket;
        #$wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'page_width'=>120,'page_height'=>200,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
        $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'margins' => ['top' => 10, 'left' => 10,'right'=>10,'bottom'=>10]));
        $wkhtmltopdf->setHtml($HTML);
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

public  static function listadoServicios(Request $request){
    $cliente=$request->Cliente;
   return "hola";
    Funciones::selecionarBase($cliente);
   
}

public static function descargarXML(Request $request){
    $cliente=$request->Cliente;
    $cotizacion=$request->Cotizacion;
    $xml = Funciones::ObtenValor("SELECT XML.xml as elxml
    FROM XMLIngreso x
        INNER JOIN XML ON (XML.id = x.xml )
    WHERE
        x.idCotizaci_on = ". $cotizacion."
        ORDER BY x.id ASC ", "elxml" );
    return response()->json([
        'success' => '1',
        'xml' => $xml,
    ]);
}
}
