<?php

namespace App\Http\Controllers\PortalPago;


use App\Http\Controllers\PortalNotarios\PortalNotariosController;
use DateTime;
use App\Cliente;
use App\Funciones;
use App\FuncionesServidor;
use App\FuncionesCaja;
use App\Modelos\PadronAguaLectura;
use App\Modelos\PadronAguaPotable;
use App\ModelosNotarios\Observaciones;
use App\Libs\Wkhtmltopdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use \Illuminate\Support\Facades\Config;

use Illuminate\Support\Facades\Storage;


class PortalController extends Controller
{
    /**
     * ! Se asigna el middleware  al costructor
     */
    public function __construct()
    {
        $this->middleware( 'jwt', ['except' => ['getToken', 'getClienteByNombre' , 'pruebaWebHook']] );
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

    public function pruebas2()
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
        include_once( app_path() . '/Libs/Wkhtmltopdf.php' );
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
        #include_once( app_path() . '/Libs/num_letras.php' );

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


    public function pruebaWebHook(Request $request)
    {
        error_log("recibo".$request."", 3, "/var/log/suinpac/PruebaHook.log");

        return response()->json([
            'success' => '1',
            'mensaje' => 'hola',

        ], 200);

        http_response_code(200);

    }

    public function getImagenCopia(Request $request)
    {
        $cliente = $request->Cliente;
        Funciones::selecionarBase($cliente);
        $cliente = $request->Cliente;
        Funciones::selecionarBase($cliente);

        $ClienteImagenOficial = Cliente::select(
            'cr.Ruta as Logotipo','Cliente.Descripci_on as nombre')
            ->join('CelaRepositorioC AS cr',  'cr.idRepositorio', '=', 'Cliente.LogotipoOficial')
            ->where('Cliente.id', $cliente)
            ->first();
        $ClienteImagen = Cliente::select(
            'cr.Ruta as Logotipo','Cliente.Descripci_on as nombre')
            ->join('CelaRepositorioC AS cr',  'cr.idRepositorio', '=', 'Cliente.Logotipo')
            ->where('Cliente.id', $cliente)
            ->first();

        $response=[
            'success' => '1',
            'url'=>$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".$ClienteImagenOficial->Logotipo,
            'url2'=>$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".$ClienteImagen->Logotipo,
            'cliente'=>$ClienteImagen->Logotipo,
            'clienteNombre'=>$ClienteImagen->nombre

        ];

        $result = Funciones::respondWithToken($response);
        return $result;

    }



    public function getImagenes(Request $request)
    {
        $cliente = $request->Cliente;
        Funciones::selecionarBase($cliente);

        $ClienteImagen = Cliente::select(
            'cr.Ruta as Logotipo','Cliente.Descripci_on as nombre')
            ->join('CelaRepositorioC AS cr',  'cr.idRepositorio', '=', 'Cliente.LogotipoOficial')
            ->where('Cliente.id', $cliente)
            ->first();

        if($ClienteImagen){
            return response()->json([
                'success' => '1',
                'url'=>$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".$ClienteImagen->Logotipo,
                'cliente'=>$ClienteImagen->Logotipo,
                'clienteNombre'=>$ClienteImagen->nombre

            ], 200);
        }else{
            return response()->json([
                'success' => '2',
                //'url'=>$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".$ClienteImagen->Logotipo,
                //'cliente'=>$ClienteImagen->Logotipo,
                //'clienteNombre'=>$ClienteImagen->nombre

            ], 200);
        }



    }

    public function getClientesServicio(Request $request){
        $validar=Funciones::DecodeThis2($request->Validar);
        $clave="cliente!@$";
        
        $consultaCotizaciones= "SELECT sl.idCliente as cliente, c.Nombre as nombre, MAX(sl.Estatus) as activo, LOWER(c.subdominio) as subdominio, c.Estatus
        FROM ClientesServiciosEnLinea sl INNER JOIN Cliente c  ON (c.id=sl.idCliente) GROUP BY sl.idCliente ORDER BY activo DESC, cliente";
        $resultadoCotizaciones=DB::select($consultaCotizaciones);
        return response()->json([
            'success' => '1',
            'lista'=> $resultadoCotizaciones
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

    public function getNombreCliente(Request $request){

        $cliente=$request->Cliente;

        $ClienteRelacion="";
        $EsMunicipio="";
        $Cliente = Cliente::select('id','Descripci_on')
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
        $metodoPago=$request->MetodoPago;
        $correo=$request->Correo;
        $telefono=$request->Telefono;
        $idContribuyente=$request->idContribuyente;
        $UsoCFDI=$request->UsoCFDI;

        /*$cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $idCotizaciones=$request->IdCotizaciones;*/

        Funciones::selecionarBase($cliente);
        $DatosConceptos;

        $auxiliarCondicion ="";

        if($tipoServicio==9){// es agua
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";

       $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }else if($tipoServicio==3){// predial


            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and c.Tipo=3 and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2020).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";

            $resultadoCotizaciones=DB::select($consultaCotizaciones);

        }else if($tipoServicio==4){// licencia de funcionamiento
            $IdLicencia = Funciones::ObtenValor("SELECT id FROM Padr_onLicencia WHERE CAST(Folio AS UNSIGNED) = ".intval($idPadron)." AND Cliente = ".$cliente,'id');

            if($IdLicencia != '')
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$IdLicencia;
            else
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$idPadron;
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
               FROM Cotizaci_on c
               WHERE c.Cliente=".$cliente.$condicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
               $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }
         else{//servicios predial
            $cotizacioServicio=$request->CotizacioServicio;
            $miArray = array("id"=>$cotizacioServicio);
            $miArray2 =array($miArray);
            $resultadoCotizaciones=$miArray2;
       }

        $url = 'https://suinpac.com/PagoCajaVirtualPortal.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "Cotizaciones"=>$resultadoCotizaciones,
                "Importe"=>$importe,
                "Autorizacion"=>$autorizacion,
                "Referencia"=>$referencia,
                "Folio"=>$folio,
                "MetodoPago"=>$metodoPago,
                "Correo"=>$correo,
                "Telefono"=>$telefono,
                "idContribuyente"=>$idContribuyente,
                "idPadron"=>$idPadron,
                "UsoCFDI"=>$UsoCFDI,
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
            'cotizaciones'=> $resultadoCotizaciones,
            'idContribuyente' => $idContribuyente,
            'idPadron' =>$idPadron,
        ], 200);
    }


    public static function postSuinpacCajaReferenciado(Request $request){
        $cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $importe=$request->Importe;
        $autorizacion=$request->Autorizacion;
        $referencia=$request->Referencia;
        $folio=$request->Folio;
        $metodoPago=$request->MetodoPago;
        $correo=$request->Correo;
        $telefono=$request->Telefono;
        $idContribuyente=$request->idContribuyente;
        $UsoCFDI=$request->UsoCFDI;

        /*$cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $idCotizaciones=$request->IdCotizaciones;*/

        Funciones::selecionarBase($cliente);
        $DatosConceptos;

        $auxiliarCondicion ="";

        if($tipoServicio==9){// es agua
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";

            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }else if($tipoServicio==3){// predial


            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and c.Tipo=3 and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2020).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";

            $resultadoCotizaciones=DB::select($consultaCotizaciones);

        }else if($tipoServicio==4){// licencia de funcionamiento
            $IdLicencia = Funciones::ObtenValor("SELECT id FROM Padr_onLicencia WHERE CAST(Folio AS UNSIGNED) = ".intval($idPadron)." AND Cliente = ".$cliente,'id');

            if($IdLicencia != '')
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$IdLicencia;
            else
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$idPadron;
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
               FROM Cotizaci_on c
               WHERE c.Cliente=".$cliente.$condicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }
        else{//servicios predial
            $cotizacioServicio=$request->CotizacioServicio;
            $miArray = array("id"=>$cotizacioServicio);
            $miArray2 =array($miArray);
            $resultadoCotizaciones=$miArray2;

        }


        $url = 'https://suinpac.com/PagoCajaVirtualPortal.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "Cotizaciones"=>$resultadoCotizaciones,
                "Importe"=>$importe,
                "Autorizacion"=>$autorizacion,
                "Referencia"=>$referencia,
                "Folio"=>$folio,
                "MetodoPago"=>$metodoPago,
                "Correo"=>$correo,
                "Telefono"=>$telefono,
                "idContribuyente"=>$idContribuyente,
                "idPadron"=>$idPadron,
                "UsoCFDI"=>$UsoCFDI,
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

        $response=[
            'success' => $result,
            'cotizaciones'=> $resultadoCotizaciones,
            'idContribuyente' => $idContribuyente,
            'idPadron' =>$idPadron,
        ];

        $resul = Funciones::respondWithToken($response);

        return $resul;
    }

    public static function postCajaVirtualCajero(Request $request){
        $cliente = intval($request->Cliente);
        $idPadron = intval($request->IdPadron);
        $importe=$request->Importe;
        $referencia=$request->Referencia;
        $MetodoPago=$request->MetodoPago;
        $UsoCFDI=$request->UsoCFDI;
        $Usuario=$request->Usuario;
        error_log("Fecha: ". date("Y-m-d H:i:s") . " Inicia postCajaVirtualCajero 'cliente' => $cliente,'idPadron' => $idPadron, 'importe' => $importe, 'referencia' => $referencia,'metodoPago' => $MetodoPago,'UsoCFDI' => $UsoCFDI,'Usuario' => $Usuario \n" , 3, "/var/log/suinpac/LogCajero.log");
        #validacion de que se ingresan valores enteros
        if (!is_int($idPadron) || $idPadron=='' || !is_int($cliente) || $cliente=='') {
            return response()->json([
                'success' => '0',
                'error' => 'Datos Invalidos'
            ], 200);
        }
        Funciones::selecionarBase($cliente);
            if($cliente==32){// es CAPAZ
                //se le agrega a la consulta and c.Tipo=".$tipoServicio." para que solamente filtre por tipo de agua potable y no agregue las cotizaciones de predial
                $consultaCotizaciones= "SELECT x.id
                FROM (
                    SELECT 
                        c.id,
                        COALESCE((
                            SELECT COUNT(id) 
                            FROM ConceptoAdicionalesCotizaci_on 
                            WHERE Cotizaci_on = c.id AND Estatus = 0
                        ), 0) AS PorPagar
                    FROM Cotizaci_on c
                    WHERE c.Cliente = ".$cliente
                        ." AND c.Tipo = 9 
                        AND SUBSTR(c.FolioCotizaci_on, 1, 4) <= '".date("Y")."' 
                        AND c.Padr_on =".$idPadron."
                ) x 
                WHERE x.PorPagar != 0 
                ORDER BY x.id DESC";
                $resultadoCotizaciones=DB::select($consultaCotizaciones);
            }else{
                return response()->json([
                    'success' => '0',
                    'res'=> 'Solo puedes consultar el cliente CAPAZ'
                ], 200);
            }
        
        $url = 'https://suinpac.com/PagoCajaVirtualPortalCajero.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "Cotizaciones"=>$resultadoCotizaciones,
                "Importe"=>$importe,
                "MetodoPago"=>$MetodoPago,
                "idPadron"=>$idPadron,
                "UsoCFDI"=>$UsoCFDI,
                "Usuario"=>$Usuario,
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
        error_log("Fecha: ". date("Y-m-d H:i:s") . " Termina postCajaVirtualCajero $result \n" , 3, "/var/log/suinpac/LogCajero.log");
        $result = Funciones::respondWithToken($result);
        return $result;
    }

    public static function postCajeroDelete(Request $request){
        $usuario = $request->Usuario;
        if ($usuario == 'usuarioCajeroAPI') {
            $cliente = 32;#39
            Funciones::selecionarBase($cliente);
            if ($cliente == 32) { // $cliente==32 es CAPAZ
                $consultaCotizaciones = "SELECT ec.id AS Encabezado, pt.id AS Ticket FROM PagoTicket pt INNER JOIN Pago p ON(pt.Pago=p.id) 
                INNER JOIN EncabezadoContabilidad ec ON(ec.Pago=p.id) WHERE p.Contribuyente=519684 AND pt.Tipo=11 AND
                (JSON_VALUE(`Variables`, '$.ContratoVigente') LIKE '%31622%' OR JSON_VALUE(`Variables`, '$.ContratoVigente') LIKE '%32888%')
                AND pt.Estatus=1 AND DATE(pt.Fecha)>'2023-08-15' ORDER BY pt.id DESC LIMIT 1";
                $resultado = DB::select($consultaCotizaciones);
                if(isset($resultado[0]->Encabezado)){
                $url = 'https://suinpac.com/Cotizaci_onPolizasEliminarPagosCajero.php';
                $dataForPost = array(
                    'Datos' => [
                        "Cliente" => $cliente,
                        "Observaci_onEliminar" => 'Eliminación de Pago de Prueba - Cajero Automático',#'Eliminación de Pago de Prueba - Cajero Automático',
                        "claveEliminar" => $resultado[0]->Encabezado,
                        "TicketEliminar" => $resultado[0]->Ticket,
                        "Usuario" => $usuario,
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
                    'success' => 1,
                    'res' => $result
                ], 200);

            } else {
                return response()->json([
                    'success' => '2',
                    'res' => 'Sin Adeudo por restaurar'
                ], 200);
            }
            } else {
                return response()->json([
                    'success' => '0',
                    'res' => 'Solo puedes consultar el cliente CAPAZ'
                ], 200);
            }
        } else {
            return response()->json([
                'success' => '0',
                'res' => 'No Tienes permiso para acceder a este metodo'
            ], 200);
        }
    }
    public static function postDeleteTicket(Request $request){
        $usuario = $request->Usuario;
        $cliente = intval($request->Cliente);
        $padron = intval($request->Padron);
        Funciones::precode(" ",1,1);
        #if ($usuario == 'usuarioCajeroAPI') {
            Funciones::selecionarBase($cliente);
            if ($padron!='') { // $cliente==32 es CAPAZ
                $consultaCotizaciones = "SELECT a.id, a.ContratoVigente, GROUP_CONCAT(a.Ticket) AS Ticket, GROUP_CONCAT(a.Encabezado) AS Encabezado FROM (
                    SELECT pa.id, pa.ContratoVigente, pal.Mes, pt.id AS Ticket, ec.id AS Encabezado, pt.Fecha, pt.NumOperacion, pt.Corte, pt.Conceptos, pt.Adicionales, pt.IVA,
                    pt.Actualizaciones, pt.Recargos, pt.Descuentos, pt.Anticipo, pt.Total
                    FROM Padr_onAguaPotablePagoAnual an
                    INNER JOIN Padr_onAguaPotable pa ON(an.Padr_on=pa.id)
                    INNER JOIN Padr_onDeAguaLectura pal ON(pal.Padr_onAgua=pa.id)
                    INNER JOIN Cotizaci_on c ON(c.Padr_on=pa.id)
                    INNER JOIN Pago p ON(c.id=p.Cotizaci_on AND p.id=pal.Pago)
                    INNER JOIN PagoTicket pt ON(pt.Pago=p.id)
                    INNER JOIN EncabezadoContabilidad ec ON(ec.Pago=p.id)
                    WHERE an.EjercicioFiscal=2024 AND an.Estatus=3 AND pal.A_no=2024 AND pal.Mes IN(1,2,3) AND pt.Total=0 AND pt.Tipo=5
                    GROUP BY pt.id ORDER BY pa.ContratoVigente, pal.Mes limit 100
                    ) a";
                $resultado = DB::select($consultaCotizaciones);
                #Funciones::precode($resultado,1,1);
                if(isset($resultado[0]->Encabezado)){
                $url = 'https://pedrodev.suinpac.dev/Cotizaci_onPolizasEliminarPagosDev.php';
                $dataForPost = array(
                    'Datos' => [
                        "Cliente" => $cliente,
                        "Observaci_onEliminar" => 'Error al Recaudar de Forma Masiva',
                        "claveEliminar" => $resultado[0]->Encabezado,
                        "TicketEliminar" => $resultado[0]->Ticket,
                        "Usuario" => $usuario,
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
                    'success' => 1,
                    'res' => $result
                ], 200);

            } else {
                return response()->json([
                    'success' => '2',
                    'res' => 'Sin Adeudo por restaurar'
                ], 200);
            }
            } else {
                return response()->json([
                    'success' => '0',
                    'res' => 'Solo puedes consultar el cliente CAPAZ'
                ], 200);
            }
        /*} else {
            return response()->json([
                'success' => '0',
                'res' => 'No Tienes permiso para acceder a este metodo'
            ], 200);
        }*/
    }
    public static function postSuinpacCajaCopiaDEV(Request $request){
        $cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $importe=$request->Importe;
        $autorizacion=$request->Autorizacion;
        $referencia=$request->Referencia;
        $folio=$request->Folio;
        $metodoPago=$request->MetodoPago;
        $correo=$request->Correo;
        $telefono=$request->Telefono;
        $idContribuyente=$request->idContribuyente;
        $UsoCFDI=$request->UsoCFDI;

        /*$cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $idCotizaciones=$request->IdCotizaciones;*/



        Funciones::selecionarBase($cliente);
        $DatosConceptos;

        $auxiliarCondicion ="";
        if($tipoServicio==9){// es agua
            //la consulta se le agrega el  and c.Tipo=".$tipoServicio." debido a que sino filtra cotizaciones de predial tambien
            $consultaCotizaciones = "SELECT UNIQUE x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c INNER JOIN ConceptoAdicionalesCotizaci_on cac1 ON (cac1.Cotizaci_on = c.id)
            WHERE c.Cliente=".$cliente." AND (c.Tipo=".$tipoServicio." OR (c.Tipo = 16 AND cac1.ConceptoAdicionales IN(2843,5784,5783,5782,5561,2843))) AND SUBSTR(c.FolioCotizaci_on, 1, 4)<=".date('Y')." AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";
            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }else if($tipoServicio==3){// predial


            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where  ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and c.Tipo=".$tipoServicio." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2020).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";

            $resultadoCotizaciones=DB::select($consultaCotizaciones);

        }else if($tipoServicio==4){// licencia de funcionamiento
            $IdLicencia = Funciones::ObtenValor("SELECT id FROM Padr_onLicencia WHERE CAST(Folio AS UNSIGNED) = ".intval($idPadron)." AND Cliente = ".$cliente,'id');

            if($IdLicencia != '')
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$IdLicencia;
            else
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$idPadron;
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
               FROM Cotizaci_on c
               WHERE c.Cliente=".$cliente.$condicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
            $resultadoCotizaciones=DB::select($consultaCotizaciones);

        } else if ( $tipoServicio == 25){//Permiso de Carga y Descarga
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
                    FROM Cotizaci_on c
                WHERE c.Cliente=".$cliente." AND c.Tipo = 25 AND c.Padr_on = $idPadron ) x WHERE x.PorPagar!=0 order by x.id desc";
            $resultadoCotizaciones=DB::select($consultaCotizaciones);

        } else {//servicios predial
             $cotizacioServicio=$request->CotizacioServicio;
             $miArray = array("id"=>$cotizacioServicio);
             $miArray2 =array($miArray);
             $resultadoCotizaciones=$miArray2;
            /*return response()->json([
                'success' => $cotizacioServicio,
                //'cotizaciones'=> $resultadoCotizaciones,
                //'idContribuyente' => $idContribuyente,
                //'idPadron' =>$idPadron,
            ], 200);
            exit;*/
        }


        $url = 'https://suinpac.com/PagoCajaVirtualPortal.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "Cotizaciones"=>$resultadoCotizaciones,
                "Importe"=>$importe,
                "Autorizacion"=>$autorizacion,
                "Referencia"=>$referencia,
                "Folio"=>$folio,
                "MetodoPago"=>$metodoPago,
                "Correo"=>$correo,
                "Telefono"=>$telefono,
                "idContribuyente"=>$idContribuyente,
                "idPadron"=>$idPadron,
                "UsoCFDI"=>$UsoCFDI,
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




        $result = Funciones::respondWithToken($result);
        return $result;
    }

    public static function postSuinpacCajaCopia(Request $request){
        $cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $importe=$request->Importe;
        $autorizacion=$request->Autorizacion;
        $referencia=$request->Referencia;
        $folio=$request->Folio;
        $metodoPago=$request->MetodoPago;
        $correo=$request->Correo;
        $telefono=$request->Telefono;
        $idContribuyente=$request->idContribuyente;
        $UsoCFDI=$request->UsoCFDI;

        /*$cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $idCotizaciones=$request->IdCotizaciones;*/

        Funciones::selecionarBase($cliente);
        $DatosConceptos;

        $auxiliarCondicion ="";
        if($tipoServicio==9){// es agua
            //la consulta se le agrega el  and c.Tipo=".$tipoServicio." debido a que sino filtra cotizaciones de predial tambien
            $consultaCotizaciones = "SELECT UNIQUE x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c INNER JOIN ConceptoAdicionalesCotizaci_on cac1 ON (cac1.Cotizaci_on = c.id)
            WHERE c.Cliente=".$cliente." AND (c.Tipo=".$tipoServicio." OR (c.Tipo = 16 AND cac1.ConceptoAdicionales IN(2843,5784,5783,5782,5561,2843))) AND SUBSTR(c.FolioCotizaci_on, 1, 4)<=".date('Y')." AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";
            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }else if($tipoServicio==3){// predial


            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where  ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and c.Tipo=".$tipoServicio." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2020).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";

            $resultadoCotizaciones=DB::select($consultaCotizaciones);

        }else if($tipoServicio==4){// licencia de funcionamiento
            $IdLicencia = Funciones::ObtenValor("SELECT id FROM Padr_onLicencia WHERE CAST(Folio AS UNSIGNED) = ".intval($idPadron)." AND Cliente = ".$cliente,'id');

            if($IdLicencia != '')
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$IdLicencia;
            else
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$idPadron;
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
               FROM Cotizaci_on c
               WHERE c.Cliente=".$cliente.$condicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
            $resultadoCotizaciones=DB::select($consultaCotizaciones);

        } else if ( $tipoServicio == 25){//Permiso de Carga y Descarga
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
                    FROM Cotizaci_on c
                WHERE c.Cliente=".$cliente." AND c.Tipo = 25 AND c.Padr_on = $idPadron ) x WHERE x.PorPagar!=0 order by x.id desc";
            $resultadoCotizaciones=DB::select($consultaCotizaciones);

        } else if($tipoServicio == 38) { // Permiso Provisional
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
                                        FROM Cotizaci_on c
                                    WHERE c.Cliente=".$cliente." AND c.Tipo = 38 AND c.Padr_on = $idPadron ) x WHERE x.PorPagar!=0 order by x.id desc";
                                $resultadoCotizaciones=DB::select($consultaCotizaciones);

        } else {//servicios predial
             $cotizacioServicio=$request->CotizacioServicio;
             $miArray = array("id"=>$cotizacioServicio);
             $miArray2 =array($miArray);
             $resultadoCotizaciones=$miArray2;
            /*return response()->json([
                'success' => $cotizacioServicio,
                //'cotizaciones'=> $resultadoCotizaciones,
                //'idContribuyente' => $idContribuyente,
                //'idPadron' =>$idPadron,
            ], 200);
            exit;*/
        }


        $url = 'https://suinpac.com/PagoCajaVirtualPortal.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "Cotizaciones"=>$resultadoCotizaciones,
                "Importe"=>$importe,
                "Autorizacion"=>$autorizacion,
                "Referencia"=>$referencia,
                "Folio"=>$folio,
                "MetodoPago"=>$metodoPago,
                "Correo"=>$correo,
                "Telefono"=>$telefono,
                "idContribuyente"=>$idContribuyente,
                "idPadron"=>$idPadron,
                "UsoCFDI"=>$UsoCFDI,
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




        $result = Funciones::respondWithToken($result);
        return $result;
    }
/*  */

public static function postSuinpacCajaCopiaV2(Request $request){
    $cliente=$request->Cliente;
    $tipoServicio=$request->TipoServicio;
    $idPadron=$request->IdPadron;
    $importe=$request->Importe;
    $autorizacion=$request->Autorizacion;
    $referencia=$request->Referencia;
    $folio=$request->Folio;
    $metodoPago=$request->MetodoPago;
    $correo=$request->Correo;
    $telefono=$request->Telefono;
    $idContribuyente=$request->idContribuyente;
    $UsoCFDI=$request->UsoCFDI;

    /*$cliente=$request->Cliente;
    $tipoServicio=$request->TipoServicio;
    $idPadron=$request->IdPadron;
    $idCotizaciones=$request->IdCotizaciones;*/



    Funciones::selecionarBase($cliente);
    $DatosConceptos;

    $auxiliarCondicion ="";
    if($tipoServicio==9){// es agua
        if($cliente==32){
            $tipoServicio=('9,16');
        }
        $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
        FROM Cotizaci_on c
       WHERE c.Cliente=".$cliente." and c.Tipo IN(".$tipoServicio.") and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";

        $resultadoCotizaciones=DB::select($consultaCotizaciones);
    }else if($tipoServicio==3){// predial


        $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where  ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
        FROM Cotizaci_on c
       WHERE c.Cliente=".$cliente." and c.Tipo=".$tipoServicio." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2020).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";

        $resultadoCotizaciones=DB::select($consultaCotizaciones);

    }else if($tipoServicio==4){// licencia de funcionamiento
        $IdLicencia = Funciones::ObtenValor("SELECT id FROM Padr_onLicencia WHERE CAST(Folio AS UNSIGNED) = ".intval($idPadron)." AND Cliente = ".$cliente,'id');

        if($IdLicencia != '')
            $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$IdLicencia;
        else
            $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$idPadron;
        $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
           FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente.$condicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
        $resultadoCotizaciones=DB::select($consultaCotizaciones);
    }
    else{//servicios predial
        $cotizacioServicio=$request->CotizacioServicio;
        $miArray = array("id"=>$cotizacioServicio);
        $miArray2 =array($miArray);
        $resultadoCotizaciones=$miArray2;
        /*return response()->json([
            'success' => $cotizacioServicio,
            //'cotizaciones'=> $resultadoCotizaciones,
            //'idContribuyente' => $idContribuyente,
            //'idPadron' =>$idPadron,
        ], 200);
        exit;*/
    }


    $url = 'https://suinpac.com/PagoCajaVirtualPortal.php';
    $dataForPost = array(
        'Cliente'=> [
            "Cliente"=>$cliente,
            "Cotizaciones"=>$resultadoCotizaciones,
            "Importe"=>$importe,
            "Autorizacion"=>$autorizacion,
            "Referencia"=>$referencia,
            "Folio"=>$folio,
            "MetodoPago"=>$metodoPago,
            "Correo"=>$correo,
            "Telefono"=>$telefono,
            "idContribuyente"=>$idContribuyente,
            "idPadron"=>$idPadron,
            "UsoCFDI"=>$UsoCFDI,
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




    $result = Funciones::respondWithToken($result);
    return $result;
}
/*  */
    public static function postSuinpacCajaCopiazofemat(Request $request){
        $url = 'https://suinpac.com/Cotizaci_onPOSTRecaudaCopia.php';
        $dataForPost = array(
            "Cliente"=>$request->Cliente,
            "IdPadron"=>$request->IdPadron,
            "cotizaciones"=>$request->cotizaciones,
            "ServicioZofe"=>$request->ServicioZofe,
            "Importe"=>$request->Importe,
            "Autorizacion"=>$request->Autorizacion,
            "Referencia"=>$request->Referencia,
            "Folio"=>$request->Folio,
            "TipoServicio"=>$request->TipoServicio,
            "MetodoPago"=>$request->MetodoPago,
            "Correo"=>$request->Correo,
            "Telefono"=>$request->Telefono,
            "banco"=>$request->banco,
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

        /*return response()->json([
            'success' => $result,
            'cotizaciones'=> $resultadoCotizaciones,
            'idContribuyente' => $idContribuyente,
            'idPadron' =>$idPadron,
        ], 200);*/

        $result = Funciones::respondWithToken($result);
        return $result;
        exit();
        $cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $importe=$request->Importe;
        $autorizacion=$request->Autorizacion;
        $referencia=$request->Referencia;
        $folio=$request->Folio;
        $metodoPago=$request->MetodoPago;
        $correo=$request->Correo;
        $telefono=$request->Telefono;
        $idContribuyente=$request->idContribuyente;

        /*$cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $idCotizaciones=$request->IdCotizaciones;*/

        Funciones::selecionarBase($cliente);
        $DatosConceptos;

        $auxiliarCondicion ="";

        if($tipoServicio==9){// es agua
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";

            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }else if($tipoServicio==3){// predial


            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and c.Tipo=3 and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2020).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";

            $resultadoCotizaciones=DB::select($consultaCotizaciones);

        }else if($tipoServicio==4){// licencia de funcionamiento
            $IdLicencia = Funciones::ObtenValor("SELECT id FROM Padr_onLicencia WHERE CAST(Folio AS UNSIGNED) = ".intval($idPadron)." AND Cliente = ".$cliente,'id');

            if($IdLicencia != '')
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$IdLicencia;
            else
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$idPadron;
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
               FROM Cotizaci_on c
               WHERE c.Cliente=".$cliente.$condicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }
        else{//servicios predial
            $cotizacioServicio=$request->CotizacioServicio;
            $miArray = array("id"=>$cotizacioServicio);
            $miArray2 =array($miArray);
            $resultadoCotizaciones=$miArray2;

        }


        $url = 'https://suinpac.com/PagoCajaVirtualPortal.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "Cotizaciones"=>$resultadoCotizaciones,
                "Importe"=>$importe,
                "Autorizacion"=>$autorizacion,
                "Referencia"=>$referencia,
                "Folio"=>$folio,
                "MetodoPago"=>$metodoPago,
                "Correo"=>$correo,
                "Telefono"=>$telefono,
                "idContribuyente"=>$idContribuyente,
                "idPadron"=>$idPadron,
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

        /*return response()->json([
            'success' => $result,
            'cotizaciones'=> $resultadoCotizaciones,
            'idContribuyente' => $idContribuyente,
            'idPadron' =>$idPadron,
        ], 200);*/

        $result = Funciones::respondWithToken($result);
        return $result;
    }

    public static function postSuinpacCajaCopiaDos(Request $request){
        $cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $importe=$request->Importe;
        $autorizacion=$request->Autorizacion;
        $referencia=$request->Referencia;
        $folio=$request->Folio;
        $metodoPago=$request->MetodoPago;
        $correo=$request->Correo;
        $telefono=$request->Telefono;
        $idContribuyente=$request->idContribuyente;
        $UsoCFDI=$request->UsoCFDI;
        /*$cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $idCotizaciones=$request->IdCotizaciones;*/

        Funciones::selecionarBase($cliente);
        $DatosConceptos;

        $auxiliarCondicion ="";

        if($tipoServicio==9){// es agua
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";

            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }else if($tipoServicio==3){// predial


            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and c.Tipo=3 and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2020).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";

            $resultadoCotizaciones=DB::select($consultaCotizaciones);

        }else if($tipoServicio==4){// licencia de funcionamiento
            $IdLicencia = Funciones::ObtenValor("SELECT id FROM Padr_onLicencia WHERE CAST(Folio AS UNSIGNED) = ".intval($idPadron)." AND Cliente = ".$cliente,'id');

            if($IdLicencia != '')
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$IdLicencia;
            else
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$idPadron;
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
               FROM Cotizaci_on c
               WHERE c.Cliente=".$cliente.$condicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }
        else{//servicios predial
            $cotizacioServicio=$request->CotizacioServicio;
            $miArray = array("id"=>$cotizacioServicio);
            $miArray2 =array($miArray);
            $resultadoCotizaciones=$miArray2;

        }


        $url = 'https://suinpac.com/PagoCajaVirtualPortalCopiaDos.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "Cotizaciones"=>$resultadoCotizaciones,
                "Importe"=>$importe,
                "Autorizacion"=>$autorizacion,
                "Referencia"=>$referencia,
                "Folio"=>$folio,
                "MetodoPago"=>$metodoPago,
                "Correo"=>$correo,
                "Telefono"=>$telefono,
                "idContribuyente"=>$idContribuyente,
                "idPadron"=>$idPadron,
                "UsoCFDI"=>$UsoCFDI,
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

        /*return response()->json([
            'success' => $result,
            'cotizaciones'=> $resultadoCotizaciones,
            'idContribuyente' => $idContribuyente,
            'idPadron' =>$idPadron,
        ], 200);*/


        $result = Funciones::respondWithToken($result);

        return $result;

    }


    public static function postSuinpacCajaTest(Request $request){
        $cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $importe=$request->Importe;
        $autorizacion=$request->Autorizacion;
        $referencia=$request->Referencia;
        $folio=$request->Folio;
        $metodoPago=$request->MetodoPago;
        $correo=$request->Correo;
        $telefono=$request->Telefono;
        $idContribuyente=$request->idContribuyente;
        $UsoCFDI=$request->UsoCFDI;
        /*$cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $idCotizaciones=$request->IdCotizaciones;*/

        Funciones::selecionarBase($cliente);
        $DatosConceptos;

        $auxiliarCondicion ="";

        if($tipoServicio==9){// es agua
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";

            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }else if($tipoServicio==3){// predial


            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and c.Tipo=3 and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2020).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";

            $resultadoCotizaciones=DB::select($consultaCotizaciones);

        }else if($tipoServicio==4){// licencia de funcionamiento
            $IdLicencia = Funciones::ObtenValor("SELECT id FROM Padr_onLicencia WHERE CAST(Folio AS UNSIGNED) = ".intval($idPadron)." AND Cliente = ".$cliente,'id');

            if($IdLicencia != '')
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$IdLicencia;
            else
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$idPadron;
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
               FROM Cotizaci_on c
               WHERE c.Cliente=".$cliente.$condicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }
        else{//servicios predial
            $cotizacioServicio=$request->CotizacioServicio;
            $miArray = array("id"=>$cotizacioServicio);
            $miArray2 =array($miArray);
            $resultadoCotizaciones=$miArray2;

        }

/*TODO:*/
        $url = 'https://suinpac.com/PagoCajaVirtualPortalTest.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "Cotizaciones"=>$resultadoCotizaciones,
                "Importe"=>$importe,
                "Autorizacion"=>$autorizacion,
                "Referencia"=>$referencia,
                "Folio"=>$folio,
                "MetodoPago"=>$metodoPago,
                "Correo"=>$correo,
                "Telefono"=>$telefono,
                "idContribuyente"=>$idContribuyente,
                "idPadron"=>$idPadron,
                "UsoCFDI"=>$UsoCFDI,
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
            'cotizaciones'=> $resultadoCotizaciones,
            'idContribuyente' => $idContribuyente,
            'idPadron' =>$idPadron,
        ], 200);
    }

    public static function postSuinpacCajaRamon(Request $request){
        $cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $importe=$request->Importe;
        $autorizacion=$request->Autorizacion;
        $referencia=$request->Referencia;
        $folio=$request->Folio;
        $metodoPago=$request->MetodoPago;
        $correo=$request->Correo;
        $telefono=$request->Telefono;
        $idContribuyente=$request->idContribuyente;
        $banco=$request->banco;
        $UsoCFDI=$request->UsoCFDI;
        /*$cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $idCotizaciones=$request->IdCotizaciones;*/

        Funciones::selecionarBase($cliente);
        $DatosConceptos;

        $auxiliarCondicion ="";

        if($tipoServicio==9){// es agua
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";

            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }else if($tipoServicio==3){// predial


            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and c.Tipo=3 and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2020).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";

            $resultadoCotizaciones=DB::select($consultaCotizaciones);

        }else if($tipoServicio==4){// licencia de funcionamiento
            $IdLicencia = Funciones::ObtenValor("SELECT id FROM Padr_onLicencia WHERE CAST(Folio AS UNSIGNED) = ".intval($idPadron)." AND Cliente = ".$cliente,'id');

            if($IdLicencia != '')
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$IdLicencia;
            else
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$idPadron;
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
               FROM Cotizaci_on c
               WHERE c.Cliente=".$cliente.$condicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }
        else {//servicios predial
            $cotizacioServicio = $request->CotizacioServicio;
            $miArray = array("id" => $cotizacioServicio);
            $miArray2 = array($miArray);
            $resultadoCotizaciones = $miArray2;

        }


        $url = 'https://suinpac.com/PagoCajaVirtualPortalPrueba.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "Cotizaciones"=>$resultadoCotizaciones,
                "Importe"=>$importe,
                "Autorizacion"=>$autorizacion,
                "Referencia"=>$referencia,
                "Folio"=>$folio,
                "MetodoPago"=>$metodoPago,
                "Correo"=>$correo,
                "Telefono"=>$telefono,
                "idContribuyente"=>$idContribuyente,
                "idPadron"=>$idPadron,
                "banco"=>$banco,
                "consulta" => $consultaCotizaciones,
                "UsoCFDI"=>$UsoCFDI,
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
            'cotizaciones'=> $resultadoCotizaciones,
            'idContribuyente' => $idContribuyente,
            'idPadron' =>$idPadron,
        ], 200);
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


    public static function postSuinpacCajaPagoAnual(Request $request)
    {
        $cliente = $request->Cliente;
        $idPadron = $request->IdPadron;
        $contribuyente= $request-> Contribuyente;
        $PlandeCuentasCargoCheque = $request->PlandeCuentasCargoCheque;
        $TipoM = $request->TipoM;
        $AbonoP = $request->AbonoP;
        $ProveedorR = $request->ProveedorR;
        $PlandeCuentasCargoChequeDNI = $request->PlandeCuentasCargoChequeDNI;
        $TipoR = $request->TipoR;
        $AbonoE = $request->AbonoE;
        $EmpleadoR = $request->EmpleadoR;
        $AbonoRs = $request->AbonoRs;
        $ProyectoR = $request->ProyectoR;
        $MovimientoBancarioCheque = $request->MovimientoBancarioCheque;
        $N_umeroMovimientoBancarioCheque = $request->N_umeroMovimientoBancarioCheque;
        $importe = $request->Importe;
        $ConceptoCheque = $request->ConceptoCheque;
        $Anual = $request->Anual;
        $Referencia= $request->Referencia;
        $Folio=$request->Folio;


        Funciones::selecionarBase($cliente);


        $auxiliarCondicion = "";


        $url = 'https://suinpac.com/Padr_onAguaPotableAbonarAnualPagoAnticipadoBancoLinea.php';
        $dataForPost = array(
            'Cliente' => [
                "Cliente" => $cliente,
                "clave" => $idPadron,
                "PlandeCuentasCargoCheque" => $PlandeCuentasCargoCheque,
                "TipoM" => $TipoM,
                "PlandeCuentasCargoChequeDNI" => $PlandeCuentasCargoChequeDNI,
                "TipoR" => $TipoR,
                "AbonoE" => $AbonoE,
                "EmpleadoR" => $EmpleadoR,
                "AbonoP" => $AbonoP,
                "ProveedorR" => $ProveedorR,
                "AbonoRs" => $AbonoRs,
                "ProyectoR" => $ProyectoR,
                "MovimientoBancarioCheque" => $MovimientoBancarioCheque,
                "N_umeroMovimientoBancarioCheque" => $N_umeroMovimientoBancarioCheque,
                "Importe" => $importe,
                "ConceptoCheque" => $ConceptoCheque,
                "Anual" => $Anual,
                "Contribuyente"=>$contribuyente,
                "Padr_onAgua"=>$idPadron,
                "Referencia"=>$Referencia,
                "Folio"=>$Folio,
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
            'succe' => $result,
            'prueba' => "Prueba",
        ], 200);
    }


    public static function postSuinpacCajaPagoAnualCopia(Request $request)
    {
        $cliente = $request->Cliente;
        $idPadron = $request->IdPadron;
        $contribuyente= $request-> Contribuyente;
        $PlandeCuentasCargoCheque = $request->PlandeCuentasCargoCheque;
        $TipoM = $request->TipoM;
        $AbonoP = $request->AbonoP;
        $ProveedorR = $request->ProveedorR;
        $PlandeCuentasCargoChequeDNI = $request->PlandeCuentasCargoChequeDNI;
        $TipoR = $request->TipoR;
        $AbonoE = $request->AbonoE;
        $EmpleadoR = $request->EmpleadoR;
        $AbonoRs = $request->AbonoRs;
        $ProyectoR = $request->ProyectoR;
        $MovimientoBancarioCheque = $request->MovimientoBancarioCheque;
        $N_umeroMovimientoBancarioCheque = $request->N_umeroMovimientoBancarioCheque;
        $importe = $request->Importe;
        $ConceptoCheque = $request->ConceptoCheque;
        $Anual = $request->Anual;
        $Referencia= $request->Referencia;
        $Folio=$request->Folio;


        Funciones::selecionarBase($cliente);


        $auxiliarCondicion = "";


        $url = 'https://suinpac.com/Padr_onAguaPotableAbonarAnualPagoAnticipadoBancoLinea.php';
        $dataForPost = array(
            'Cliente' => [
                "Cliente" => $cliente,
                "clave" => $idPadron,
                "PlandeCuentasCargoCheque" => $PlandeCuentasCargoCheque,
                "TipoM" => $TipoM,
                "PlandeCuentasCargoChequeDNI" => $PlandeCuentasCargoChequeDNI,
                "TipoR" => $TipoR,
                "AbonoE" => $AbonoE,
                "EmpleadoR" => $EmpleadoR,
                "AbonoP" => $AbonoP,
                "ProveedorR" => $ProveedorR,
                "AbonoRs" => $AbonoRs,
                "ProyectoR" => $ProyectoR,
                "MovimientoBancarioCheque" => $MovimientoBancarioCheque,
                "N_umeroMovimientoBancarioCheque" => $N_umeroMovimientoBancarioCheque,
                "Importe" => $importe,
                "ConceptoCheque" => $ConceptoCheque,
                "Anual" => $Anual,
                "Contribuyente"=>$contribuyente,
                "Padr_onAgua"=>$idPadron,
                "Referencia"=>$Referencia,
                "Folio"=>$Folio,
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

        $response=[
            'succe' => $result,
            'prueba' => "Prueba",
        ];

        $result = Funciones::respondWithToken($response);

        return $result;
    }

    public static function formarReciboAnual(Request $request)
    {
        $clave = $request->Clave;
        $cliente= $request->Cliente;
        $movimiento= $request->Movimiento;
        $claveTicket= $request->ClaveTicket;
        $pago= $request-> Pago;
        $idTicket= $request -> IdTicket;
        $referencia= $request -> Referencia;
        $autorizacion= $request -> Autorizacion;
        $folio= $request -> Folio;
        $bancoCuenta= $request -> BancoCuenta;

        $url = 'https://suinpac.com/Cotizaci_onPagarReciboN3AguaBancoLinea.php';
        $dataForPost = array(
                "clave" => $clave,
                "cliente" => $cliente,
                "pago" => $pago,
                "idTicket"=> $idTicket,
                "Referencia"=> $referencia,
                "Autorizacion" => $autorizacion,
                "Folio"=> $folio,
                "BancoCuenta" => $bancoCuenta,
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

        $url = 'https://suinpac.com/Cotizacion_PagarOPDVistaPreviaCopyBancoLinea.php';
        $dataForPost = array(
            "claveTicket" => $claveTicket,
            "Movimiento" => $movimiento,
            "cliente" => $cliente
        );

        $options = array(
            'http' => array(
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($dataForPost),
            )
        );

        $context = stream_context_create($options);
        $resultDos = file_get_contents($url, false, $context);

        return response()->json([
            'urlRecibo' => $result,
            'urlTicket' => $resultDos,
            'clave'=> $clave,
        ], 200);
    }

        public static function postSuinpacCajaPruebas(Request $request){
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

        if($tipoServicio==9){//1 es agua
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";

       $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }else if($tipoServicio==3){//2 predial


            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and c.Tipo=3 and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2020).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";

            $resultadoCotizaciones=DB::select($consultaCotizaciones);

        } else  if($tipoServicio==4){//servicios predial
            $cotizacioServicio=$request->CotizacioServicio;
            $miArray = array("id"=>$cotizacioServicio);
            $miArray2 =array($miArray);
            $resultadoCotizaciones=$miArray2;

       }





        $url = 'https://suinpac.piacza.com.mx/PruebasMario.php';
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
            'cotizaciones'=> $resultadoCotizaciones
        ], 200);
    }

    public static function postSuinpacCajaListaAdeudoAnterior(Request $request){
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
        /*return  "AguaController.phpHo";
        exit();*/
        $auxiliarCondicion ="";

        if($tipoServicio==9){//9 es agua


        $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<=".date('Y')." AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";

       $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }else if($tipoServicio==3){//3 predial
          //  if($cliente==29)
           // $auxiliarCondicion=" AND c.FechaLimite IS NULL";

         $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
         FROM Cotizaci_on c
        WHERE c.Cliente=".$cliente."  and c.Tipo=3 and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2020).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";

        $resultadoCotizaciones=DB::select($consultaCotizaciones);

        }else if($tipoServicio==4){// licencia de funcionamiento
            $IdLicencia = Funciones::ObtenValor("SELECT id FROM Padr_onLicencia WHERE CAST(Folio AS UNSIGNED) = ".intval($idPadron)." AND Cliente = ".$cliente,'id');

            if($IdLicencia != '')
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$IdLicencia;
            else
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$idPadron;
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
               FROM Cotizaci_on c
               WHERE c.Cliente=".$cliente.$condicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
               $resultadoCotizaciones=DB::select($consultaCotizaciones);


        } else {//servicios predial

            $cotizacioServicio=$request->CotizacioServicio;
            $miArray = array("id"=>$cotizacioServicio);
            $miArray2 =array($miArray);
            $resultadoCotizaciones=$miArray2;

       }


        $url = 'https://suinpac.com/PagoCajaVirtualVerificacionCopia.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente2"=>$cliente,
                "Cotizaciones"=>$resultadoCotizaciones,
                "idPadron"=>$idPadron,
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

        if($tipoServicio==11){
            $UrlOrdenPagoISAI=(new PortalNotariosController)->obtenerOrdenPagoISAI($cliente,$cotizacioServicio);
            return response()->json([
                'success' => '1',
                'total'=> $result,
                'ss'=>$resultadoCotizaciones,
                'rutaOrdenPagoISAI' => $UrlOrdenPagoISAI,
            ], 200);
        }
        return response()->json([
            'success' => '1',
            'total'=> $result,
            'ss'=>$resultadoCotizaciones,

        ], 200);
    }

    //
    //
    public static function postSuinpacCajaListaAdeudoDEV(Request $request){
        $cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $cotizacioServicio=$request->CotizacioServicio;
        /*$cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $idCotizaciones=$request->IdCotizaciones;*/
        //return response()->json([
        //    'success' => '1',
        //    'total'=> $idPadron
        //], 200);
        //exit();
        Funciones::selecionarBase($cliente);
        $DatosConceptos;
        $UrlOrdenPagoISAI;
        /*return  "AguaController.phpHo";
        exit();*/
        $auxiliarCondicion ="";
        if($tipoServicio==9){//9 es agua
            //la consulta se le agrega el  and c.Tipo=".$tipoServicio." debido a que sino filtra cotizaciones de predial tambien
            $consultaCotizaciones = "SELECT UNIQUE x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c INNER JOIN ConceptoAdicionalesCotizaci_on cac1 ON (cac1.Cotizaci_on = c.id)
            WHERE c.Cliente=".$cliente." AND (c.Tipo=".$tipoServicio." OR (c.Tipo = 16 AND cac1.ConceptoAdicionales IN(2843,5784,5783,5782,5561,2843))) AND SUBSTR(c.FolioCotizaci_on, 1, 4)<=".date('Y')." AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";
            /*return response()->json([
                'total2'=> $consultaCotizaciones
            ], 200);            */                        
            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }else if($tipoServicio==3){//3 predial
            //  if($cliente==29)
            // $auxiliarCondicion=" AND c.FechaLimite IS NULL";
            $consultaCotizaciones ="SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where   ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
                                    FROM Cotizaci_on c
                                    WHERE c.Cliente=".$cliente." and c.Tipo=".$tipoServicio." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2020).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }else if($tipoServicio==4){// licencia de funcionamiento
            $IdLicencia = Funciones::ObtenValor("SELECT id FROM Padr_onLicencia WHERE CAST(Folio AS UNSIGNED) = ".intval($idPadron)." AND Cliente = ".$cliente,'id');
            if($IdLicencia != '')
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$IdLicencia;
            else
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$idPadron;
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
               FROM Cotizaci_on c
               WHERE c.Cliente=".$cliente.$condicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        } else {//servicios predial
            $cotizacioServicio=$request->CotizacioServicio;
            $miArray = array("id"=>$cotizacioServicio);
            $miArray2 =array($miArray);
            $resultadoCotizaciones=$miArray2;
        }
        $url = 'https://suinpac.com/PagoCajaVirtualVerificacion.php';
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
        $result = file_get_contents($url, true, $context);
        if($tipoServicio==11){
            $UrlOrdenPagoISAI=(new PortalNotariosController)->obtenerOrdenPagoISAI($cliente,$cotizacioServicio);
            return response()->json([
                'success' => '1',
                'total'=> $result,
                'ss'=>$resultadoCotizaciones,
                //'total2'=> $consultaCotizaciones,
                'rutaOrdenPagoISAI' => $UrlOrdenPagoISAI,
            ], 200);
        }
        return response()->json([
            //'url' => $url, descomentar para hacer debug
            //'dataForPost' => $dataForPost, descomentar para hacer debug
            'success' => '1',
            'total'=> $result,
            //'total2'=> $consultaCotizaciones, descomentar para hacer debug
            'ss'=>$resultadoCotizaciones//,
            //'resultadoConsulta'=>$consulta
        ], 200);
    }
    //
    public static function postSuinpacCajaListaAdeudo(Request $request){
        $cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $cotizacioServicio=$request->CotizacioServicio;
        /*$cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $idCotizaciones=$request->IdCotizaciones;*/
        //return response()->json([
        //    'success' => '1',
        //    'total'=> $idPadron
        //], 200);
        //exit();
        Funciones::selecionarBase($cliente);
        $DatosConceptos;
        $UrlOrdenPagoISAI;
        /*return  "AguaController.phpHo";
        exit();*/
        $auxiliarCondicion ="";
        if($tipoServicio==9){//9 es agua
            //la consulta se le agrega el  and c.Tipo=".$tipoServicio." debido a que sino filtra cotizaciones de predial tambien
            $consultaCotizaciones = "SELECT UNIQUE x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c INNER JOIN ConceptoAdicionalesCotizaci_on cac1 ON (cac1.Cotizaci_on = c.id)
            WHERE c.Cliente=".$cliente." AND (c.Tipo=".$tipoServicio." OR (c.Tipo = 16 AND cac1.ConceptoAdicionales IN(2843,5784,5783,5782,5561,2843))) AND SUBSTR(c.FolioCotizaci_on, 1, 4)<=".date('Y')." AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";
            /*return response()->json([
                'total2'=> $consultaCotizaciones
            ], 200);            */                        
            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }else if($tipoServicio==3){//3 predial
            //  if($cliente==29)
            // $auxiliarCondicion=" AND c.FechaLimite IS NULL";
            $consultaCotizaciones ="SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where   ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
                                    FROM Cotizaci_on c
                                    WHERE c.Cliente=".$cliente." and c.Tipo=".$tipoServicio." and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2020).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
                                    #Funciones::precode($consultaCotizaciones,1,1);
            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }else if($tipoServicio==4){// licencia de funcionamiento
            $IdLicencia = Funciones::ObtenValor("SELECT id FROM Padr_onLicencia WHERE CAST(Folio AS UNSIGNED) = ".intval($idPadron)." AND Cliente = ".$cliente,'id');
            if($IdLicencia != '')
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$IdLicencia;
            else
                $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$idPadron;
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
               FROM Cotizaci_on c
               WHERE c.Cliente=".$cliente.$condicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
            $resultadoCotizaciones=DB::select($consultaCotizaciones);
        } else {//servicios predial
            $cotizacioServicio=$request->CotizacioServicio;
            $miArray = array("id"=>$cotizacioServicio);
            $miArray2 =array($miArray);
            $resultadoCotizaciones=$miArray2;
        }
        $url = 'https://suinpac.com/PagoCajaVirtualVerificacion.php';
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
        $result = file_get_contents($url, true, $context);
        if($tipoServicio==11){
            $UrlOrdenPagoISAI=(new PortalNotariosController)->obtenerOrdenPagoISAI($cliente,$cotizacioServicio);
            return response()->json([
                'success' => '1',
                'total'=> $result,
                'ss'=>$resultadoCotizaciones,
                //'total2'=> $consultaCotizaciones,
                'rutaOrdenPagoISAI' => $UrlOrdenPagoISAI,
            ], 200);
        }
        return response()->json([
            //'url' => $url, descomentar para hacer debug
            //'dataForPost' => $dataForPost, descomentar para hacer debug
            'success' => '1',
            'total'=> $result,
            //'total2'=> $consultaCotizaciones, descomentar para hacer debug
            'ss'=>$resultadoCotizaciones//,
            //'resultadoConsulta'=>$consulta
        ], 200);
    }
    //
    //
    public static function postCajeroListaAdeudo(Request $request){
        /*return response()->json([
            'success' => '0',
            'error' => 'SERVICIO EN MANTENIMIENTO'
        ], 200);*/
        $cliente = intval($request->Cliente);
        $idPadron = intval($request->IdPadron);
        error_log("Fecha: ". date("Y-m-d H:i:s") . " Inicia postCajeroListaAdeudo 'Cliente' => $cliente, 'Padron' => $idPadron \t" , 3, "/var/log/suinpac/LogCajero.log");
        #validacion de que se ingresan valores enteros
        if (!is_int($idPadron) || $idPadron=='' || !is_int($cliente) || $cliente=='') {
            return response()->json([
                'success' => '0',
                'error' => 'Datos Invalidos'
            ], 200);
        }
        Funciones::selecionarBase($cliente);
        #se verifica que el contrato no contenga una cotizacion de Reconexion Pendiente
        $Reconexion = "SELECT c.id, cac.ConceptoAdicionales, c.Padr_on
        FROM Cotizaci_on c 
            INNER JOIN ConceptoAdicionalesCotizaci_on cac ON c.id = cac.Cotizaci_on
            INNER JOIN Padr_onAguaPotable pa ON pa.id = c.Padr_on 
        WHERE pa.Cliente = '32' 
          AND cac.ConceptoAdicionales IN (2843, 5784, 5783, 5782, 5561)
          AND cac.Estatus = 0
          AND c.Padr_on =" . $idPadron . "  GROUP BY pa.id";
        #Funciones::precode($Reconexion,1,1);
        $ReconexionExiste = DB::select($Reconexion);
        if (count($ReconexionExiste) > 0) {
            error_log("Fecha: ". date("Y-m-d H:i:s") . " Termina postCajeroListaAdeudo 'success' => '2', 'result' => 'Existe una Reconexión pendiente, favor de pagar directamente en caja' \n" , 3, "/var/log/suinpac/LogCajero.log");
            return response()->json([
                'success' => '2',
                'result' => 'Existe una Reconexión pendiente, favor de pagar directamente en caja'
            ],
                200
            );
        } else {
            #No tiene Reconexiones pendientes por pagar
            $consultaCotizaciones = "SELECT c.id
            FROM Cotizaci_on c
            LEFT JOIN (
                SELECT Cotizaci_on, COUNT(id) AS NoPagados
                FROM ConceptoAdicionalesCotizaci_on
                WHERE Estatus = 0
                GROUP BY Cotizaci_on
            ) cac ON c.id = cac.Cotizaci_on
            WHERE c.Cliente =" . $cliente . " AND c.Tipo IN (9) 
              AND SUBSTR(c.FolioCotizaci_on, 1, 4) <= " . date('Y') 
              . " AND c.Padr_on = " . $idPadron . " AND cac.NoPagados != 0
            ORDER BY c.id DESC";
            $resultadoCotizaciones = DB::select($consultaCotizaciones);
        }
        $url = 'https://suinpac.com/PagoCajaVirtualVerificacionCajero.php';
        $dataForPost = array(
            'Cliente' => [
                "Cliente2" => $cliente,
                "Cotizaciones" => $resultadoCotizaciones
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
        error_log("Fecha: ". date("Y-m-d H:i:s") . " Termina postCajeroListaAdeudo 'success' => '1', 'total' => $result \n" , 3, "/var/log/suinpac/LogCajero.log");
        return response()->json([
            'success' => '1',
            'total' => $result,
            'ss' => $resultadoCotizaciones
        ], 200);
    }
    
    public static function postSuinpacCajaListaAdeudoISAI(Request $request){
        $cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $cotizacioServicio=$request->CotizacioServicio;

        Funciones::selecionarBase($cliente);
        $DatosConceptos;
        $UrlOrdenPagoISAI;
        $auxiliarCondicion ="";
    if($tipoServicio==11){//11  lo manejo por la cotizacion que en este caso ISAI es no. 11
            $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where   ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
            WHERE c.Cliente=".$cliente."  and c.Tipo=11 and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2020).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
        $resultadoCotizaciones=DB::select($consultaCotizaciones);

        } else {//servicios predial
            $cotizacioServicio=$request->CotizacioServicio;
            $miArray = array("id"=>$cotizacioServicio);
            $miArray2 =array($miArray);
            $resultadoCotizaciones=$miArray2;
        }


        $url = 'https://suinpac.com/PagoCajaVirtualVerificacion.php';
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
        $result = file_get_contents($url, true, $context);
        /*if($tipoServicio==11){
            $UrlOrdenPagoISAI=(new PortalNotariosController)->obtenerOrdenPagoISAI($cliente,$cotizacioServicio);
            return response()->json([
                'success' => '1',
                'total'=> $result,
                'ss'=>$resultadoCotizaciones,
                'rutaOrdenPagoISAI' => $UrlOrdenPagoISAI,
            ], 200);
        }*/
        return response()->json([
            'success' => '1',
            'total'=> $result,
            'ss'=>$resultadoCotizaciones//,
            //'resultadoConsulta'=>$consultaCotizaciones
        ], 200);
    }
    //
    //

    //


//lista adeudo V2 para  pagos en linea
public static function postSuinpacCajaListaAdeudoV2Ccdn(Request $request){
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
    $UrlOrdenPagoISAI;
    /*return  "AguaController.phpHo";
    exit();*/
    $auxiliarCondicion ="";

    if($tipoServicio==9){//9 es agua


        $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
        FROM Cotizaci_on c
       WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<=".date('Y')." AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";

        $resultadoCotizaciones=DB::select($consultaCotizaciones);
    }else if($tipoServicio==3){//3 predial
        //  if($cliente==29)
        // $auxiliarCondicion=" AND c.FechaLimite IS NULL";

        $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
     FROM Cotizaci_on c
    WHERE c.Cliente=".$cliente."  and c.Tipo=3 and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2020).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";

        $resultadoCotizaciones=DB::select($consultaCotizaciones);

    }else if($tipoServicio==4){// licencia de funcionamiento
        $IdLicencia = Funciones::ObtenValor("SELECT id FROM Padr_onLicencia WHERE CAST(Folio AS UNSIGNED) = ".intval($idPadron)." AND Cliente = ".$cliente,'id');

        if($IdLicencia != '')
            $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$IdLicencia;
        else
            $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$idPadron;
        $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
           FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente.$condicion." ) x WHERE x.PorPagar!=0 order by x.id desc";
        $resultadoCotizaciones=DB::select($consultaCotizaciones);


    } else {//servicios predial

        $cotizacioServicio=$request->CotizacioServicio;
        $miArray = array("id"=>$cotizacioServicio);
        $miArray2 =array($miArray);
        $resultadoCotizaciones=$miArray2;

    }


    $url = 'https://suinpac.com/PagoCajaVirtualVerificacionCarlos.php';
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


    $result = file_get_contents($url, true, $context);

    if($tipoServicio==11){
        $UrlOrdenPagoISAI=(new PortalNotariosController)->obtenerOrdenPagoISAI($cliente,$cotizacioServicio);
        return response()->json([
            'success' => '1',
            'total'=> $result,
            'ss'=>$resultadoCotizaciones,
            'rutaOrdenPagoISAI' => $UrlOrdenPagoISAI,
        ], 200);
    }
    return response()->json([
        'success' => '1',
        'total'=> $result,
        'ss'=>$resultadoCotizaciones,

    ], 200);
}
//Fin lista adeudo V2 para pagos en linea



   public static function listadoAdeudoPagarAnterior(Request $request){

        $cliente = $request->Cliente;
        $idPadron = $request->IdPadron;
        $tipo= $request->TipoServicio;



        #return $request;
        Funciones::selecionarBase($cliente);
      /*  $countConceptos=DB::select("SELECT COUNT(c.id) as total FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN Cotizaci_on c ON(c.id=cac.Cotizaci_on) WHERE c.Tipo IN (".$tipo.") and c.Padr_on=".$idPadron." and cac.Estatus=0 and c.FechaLimite IS NULL GROUP BY cac.A_no,cac.Mes ORDER BY cac.A_no DESC,cac.Mes DESC");
        $mostrar2020=true;


        foreach($countConceptos as $concepto){

            //si hay cotizaciones 2019 no se muestra 2020
            if($concepto->total>0){
               $mostrar2020=false;
            }
        }*/

        if($tipo==4){

            $padronHistorial=" Padr_onLicenciaHistorial  ";
            $tipoPadron= "Padr_onLicencia";
            $mes="";
        }else if($tipo==3){
          $padronHistorial=" Padr_onCatastralHistorial  ";
          $tipoPadron= "Padr_onCatastral";
          $mes="AND Mes = cac.Mes";
        }else if($tipo==9){
            $padronHistorial=" Padr_onDeAguaLectura  ";
            $tipoPadron= "Padr_onAgua";
            $mes="AND Mes = cac.Mes";
        }

                $conceptos="SELECT
                    GROUP_CONCAT( cac.id ) AS Conceptos,
                    cac.A_no,
                    cac.Mes,
                    SUM( cac.Importe ) AS Importe,
                    ( SELECT id FROM ".$padronHistorial." WHERE ".$tipoPadron." = c.Padr_on AND A_no = cac.A_no ".$mes." LIMIT 0, 1 ) AS IdLectura
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

       /*else{

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

       }*/


    return response()->json([
        'success' => '1',
        'cliente'=> $conceptos
    ], 200);
   }

    public static function listadoAdeudoPagarEjecucionFiscal(Request $request)
    {   $cliente = $request->Cliente;
        $idPadron = $request->IdPadron;
        $tipo = $request->TipoServicio;

        Funciones::selecionarBase($cliente);

        $DATOS = [];
        $DatosEjeucionDetalle = DB::select("SELECT SUM(GastoEjecucion) AS GastoEjecucionAnterior, SUM(GastoEmbargo) AS GastoEmbargoAnterior, SUM(Multas) AS MultasAnterior, SUM(OtrosGastos) AS OtrosGastosAnterior FROM PadronEjecucionFiscalDetalle efd WHERE efd.Estatus IN (1,2) AND efd.IdPadron='$idPadron';");
        $Resultado = DB::select("SELECT * FROM MultaCategor_ia WHERE Categor_ia=$tipo");

        $DatosEjeucionDetalle = (array)$DatosEjeucionDetalle[0];

        foreach ($Resultado as $Registro) {
            $registro = (array)$Registro;
            switch ($registro['Multa']) {
                case 2:
                    array_push($DATOS, ['Importe' => $DatosEjeucionDetalle['MultasAnterior'], 'Concepto' => $registro['Concepto'], 'Categoria' => $registro['Multa']]);
                    break;
                case 4:
                    array_push($DATOS, ['Importe' => $DatosEjeucionDetalle['GastoEjecucionAnterior'], 'Concepto' => $registro['Concepto'], 'Categoria' => $registro['Multa']]);
                    break;
                case 5:
                    array_push($DATOS, ['Importe' => $DatosEjeucionDetalle['GastoEmbargoAnterior'], 'Concepto' => $registro['Concepto'], 'Categoria' => $registro['Multa']]);
                    break;
            }
        }
        return response()->json([
            'success' => '1',
            'GastosEjecucion' => $DATOS
        ], 200);
    }

    public static function listadoAdeudoPagarCajero(Request $request){
        $cliente = intval($request->Cliente);
        $idPadron = intval($request->IdPadron);
        error_log("Fecha: ". date("Y-m-d H:i:s") . " Inicia listadoAdeudoPagarCajero 'Cliente' => $cliente, 'idPadron' => $idPadron \t" , 3, "/var/log/suinpac/LogCajero.log");
        #validacion de que se ingresan valores enteros
        if (!is_int($idPadron) || $idPadron=='' || !is_int($cliente) || $cliente=='') {
            return response()->json([
                'success' => '0',
                'error' => 'Datos Invalidos'
            ], 200);
        }
        Funciones::selecionarBase($cliente);
        $conceptos="SELECT 
            GROUP_CONCAT(cac.id) AS Conceptos, 
            cac.A_no, 
            cac.Mes, 
            ROUND(SUM(cac.Importe), 2) AS Importe,
            pdl.id AS IdLectura
        FROM Cotizaci_on c
        INNER JOIN ConceptoAdicionalesCotizaci_on cac ON c.id = cac.Cotizaci_on
        LEFT JOIN Padr_onDeAguaLectura pdl ON pdl.Padr_onAgua = c.Padr_on AND pdl.A_no = cac.A_no AND pdl.Mes = cac.Mes
        WHERE c.Tipo IN (9) AND c.Padr_on = ".$idPadron ." AND cac.Estatus = 0 AND cac.EstatusConvenioC = 0
        GROUP BY cac.A_no, cac.Mes
        ORDER BY cac.A_no DESC, cac.Mes DESC";
        #$conceptos = preg_replace("/[\r\n|\n|\r]+/", " ", $conceptos);
        $conceptos=DB::select($conceptos);
        $convenio = Funciones::ObtenValor("SELECT COUNT(id) AS total FROM Padr_onConvenio WHERE idPadron = $idPadron AND Estatus = 1", "total");
        error_log("Fecha: ". date("Y-m-d H:i:s") . " Termina listadoAdeudoPagarCajero 'Cliente' => $cliente, 'idPadron' => $idPadron, 'Convenio' => $convenio \n" , 3, "/var/log/suinpac/LogCajero.log");
        return response()->json([
            'success' => '1',
            'cliente'=> $conceptos,
            'convenio' => $convenio
        ], 200);
    }

    public static function listadoAdeudoPagar(Request $request){
        $cliente = $request->Cliente;
        $idPadron = $request->IdPadron;
        $tipo= $request->TipoServicio;
        #return $request;
        Funciones::selecionarBase($cliente);
        /*  $countConceptos=DB::select("SELECT COUNT(c.id) as total FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN Cotizaci_on c ON(c.id=cac.Cotizaci_on) WHERE c.Tipo IN (".$tipo.") and c.Padr_on=".$idPadron." and cac.Estatus=0 and c.FechaLimite IS NULL GROUP BY cac.A_no,cac.Mes ORDER BY cac.A_no DESC,cac.Mes DESC");
          $mostrar2020=true;
          foreach($countConceptos as $concepto){

              //si hay cotizaciones 2019 no se muestra 2020
              if($concepto->total>0){
                 $mostrar2020=false;
              }
          }*/
        if($tipo==4){
            $padronHistorial=" Padr_onLicenciaHistorial  ";
            $tipoPadron= "Padr_onLicencia";
            $mes="";
        }else if($tipo==3){
            $padronHistorial=" Padr_onCatastralHistorial  ";
            $tipoPadron= "Padr_onCatastral";
            $mes="AND Mes = cac.Mes";
        }else if($tipo==9){
            $padronHistorial=" Padr_onDeAguaLectura  ";
            $tipoPadron= "Padr_onAgua";
            $mes="AND Mes = cac.Mes";
        }
        if($cliente==32){
            $tipo=$tipo.',16';
        }
        #( SELECT id FROM ".$padronHistorial." WHERE ".$tipoPadron." = c.Padr_on AND A_no = cac.A_no ".$mes." LIMIT 0, 1 ) AS IdLectura      --El bueno  Anterior a 2021-Diciembre-07
        //( SELECT id FROM ".$padronHistorial." WHERE ".$tipoPadron." = c.Padr_on AND  cac.A_no<2022 ".$mes." LIMIT 0, 1 ) AS IdLectura

        $conceptos="SELECT GROUP_CONCAT( cac.id ) AS Conceptos, cac.A_no, cac.Mes, SUM( cac.Importe ) AS Importe,
                    ( SELECT id FROM ".$padronHistorial." WHERE ".$tipoPadron." = c.Padr_on AND A_no = cac.A_no ".$mes." LIMIT 0, 1 ) AS IdLectura
                    FROM Cotizaci_on c
                    INNER JOIN ConceptoAdicionalesCotizaci_on cac ON ( c.id = cac.Cotizaci_on )
                    WHERE c.Tipo IN (".$tipo.") AND c.Padr_on = ".$idPadron ." AND cac.Estatus = 0 AND cac.EstatusConvenioC=0
                    GROUP BY cac.A_no, cac.Mes
                    ORDER BY cac.A_no DESC, cac.Mes DESC";

                    //$consulta=$conceptos;
        $conceptos = preg_replace("/[\r\n|\n|\r]+/", " ", $conceptos);
        $conceptos=DB::select($conceptos);

        $convenio = Funciones::ObtenValor("SELECT COUNT(*) AS total FROM Padr_onConvenio WHERE idPadron = $idPadron AND Estatus = 1", "total");
        // $conceptos=DB::select("SELECT  GROUP_CONCAT(cac.id) as Conceptos,cac.A_no,cac.Mes,SUM(cac.Importe) as Importe FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN Cotizaci_on c ON(c.id=cac.Cotizaci_on) WHERE c.Tipo IN (".$tipo.") and c.Padr_on=".$idPadron." and cac.Estatus=0 GROUP BY cac.A_no,cac.Mes ORDER BY cac.A_no DESC,cac.Mes DESC");

        /*else{

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

        }*/


        return response()->json([
            'success' => '1',
            'cliente'=> $conceptos,
            'convenio' => $convenio//,
            //'consulta' => $consulta,
        ], 200);
    }
    public static function listadoAdeudoPagarISAI(Request $request){
        $cliente = $request->Cliente;
        $idPadron = $request->IdPadron;
        $tipo= $request->TipoServicio;
        Funciones::selecionarBase($cliente);
            $conceptos="SELECT
            GROUP_CONCAT( cac.id ) AS Conceptos,
            COALESCE(cac.A_no,YEAR(CURDATE())) AS A_no,
            COALESCE(cac.Mes,MONTH(CURDATE())) AS Mes,
            SUM( cac.Importe ) AS Importe,
            c.Id AS IdLectura
        FROM
            Cotizaci_on c
            INNER JOIN ConceptoAdicionalesCotizaci_on cac ON ( c.id = cac.Cotizaci_on )
        WHERE
            c.Tipo IN ($tipo)
            AND c.Padr_on = $idPadron
            AND cac.Estatus = 0
            AND cac.EstatusConvenioC=0


        GROUP BY
            cac.A_no,
            cac.Mes
        ORDER BY
            cac.A_no DESC,
            cac.Mes DESC
                                ";


        $conceptos = preg_replace("/[\r\n|\n|\r]+/", " ", $conceptos);
        $conceptos=DB::select($conceptos);


        $convenio = Funciones::ObtenValor("SELECT COUNT(*) AS total FROM Padr_onConvenio WHERE idPadron = $idPadron AND Estatus = 1", "total");
        // $conceptos=DB::select("SELECT  GROUP_CONCAT(cac.id) as Conceptos,cac.A_no,cac.Mes,SUM(cac.Importe) as Importe FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN Cotizaci_on c ON(c.id=cac.Cotizaci_on) WHERE c.Tipo IN (".$tipo.") and c.Padr_on=".$idPadron." and cac.Estatus=0 GROUP BY cac.A_no,cac.Mes ORDER BY cac.A_no DESC,cac.Mes DESC");

        /*else{

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

        }*/


        return response()->json([
            'success' => '1',
            'cliente'=> $conceptos,
            'convenio' => $convenio,
        ], 200);
    }

   public static function comprobanteDePago(Request $request){
    $cliente=$request->Cliente;
    $idTiket=$request->IdTiket;
   // $referencia=$request->Referencia;
  //  $autorizacion=$request->Autorizacion;

  if(!isset($idTiket)){
    return response()->json([
        'success' => '0',
        'ruta' => "sin Ticket",
    ]);
  }

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
                         $arr= FuncionesCaja::ReciboDeServicioGeneral($idTiket,$arregloServicio['Concepto'.$Contribuyente], $Contribuyente, $datosTicket->Pago,$datosTicket->Cliente,$arrayDescuentoIndividual[$Contribuyente]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);
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
                         $arraySubtotalPadron[$Padr_on]=array_sum(FuncionesCaja::LimpiarNumero($SubtotalParte['SubtotalPadr_on'.$Padr_on]));
                         $TotalPadrones = array_sum(FuncionesCaja::LimpiarNumero($arraySubtotalPadron));
                      foreach ($arrPadr_on as $key => $Padr_on){
                          if($Descuentos[2]>0)
                            $arrayDescuentoIndividual[$Padr_on][] =((floatval(FuncionesCaja::LimpiarNumero($Descuentos[2])) / floatval(FuncionesCaja::LimpiarNumero($TotalPadrones))) * floatval(FuncionesCaja::LimpiarNumero($arraySubtotalPadron[$Padr_on])));
                          else
                              $arrayDescuentoIndividual[$Padr_on][] =0;
                      }
                      #precode($arrayDescuentoIndividual,1,1);
                     $arrayDescuentoIndividual= FuncionesCaja::ProrratiarDescuentoIndividual(FuncionesCaja::LimpiarNumero($Descuentos[2]), $arrayDescuentoIndividual);
                     #precode($arrayDescuentoIndividual,1);
                      $Totales = 0;
                     $keys = array_keys($arrPadr_on);
                    foreach ($arrPadr_on as $key => $Padr_on) {
                        $arrContribuyente = array_unique($arreglo['Contribuyente'.$Padr_on]);
                        $arr = FuncionesCaja::ReciboPredialISAI($idTiket,$arreglo['PredialISAI'.$Padr_on], $Padr_on, $arrContribuyente, $datosTicket->Pago,$datosTicket->Cliente,$arrayDescuentoIndividual[$Padr_on]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);
                        $Recibos.= $arr['html'];
                        $NoRecibos=$arr['NoRecibos'];
                    }

               }

                if(isset($arreglo['Licencia']) && isset($arreglo['Tipo']))
               {
                    $arraySubtotalPadron=array();
                    $arrPadr_on = array_unique($arreglo['Licencia']);
                    foreach ($arrPadr_on as $key => $Padr_on)
                         $arraySubtotalPadron[$Padr_on]=array_sum(FuncionesCaja::LimpiarNumero($SubtotalParte['SubtotalPadr_on'.$Padr_on]));
                         $TotalPadrones = array_sum(FuncionesCaja::LimpiarNumero($arraySubtotalPadron));
                      foreach ($arrPadr_on as $key => $Padr_on){
                          if($Descuentos[2]>0)
                            $arrayDescuentoIndividual[$Padr_on][] = (FuncionesCaja::LimpiarNumero($Descuentos[2]) / FuncionesCaja::LimpiarNumero($TotalPadrones)) * FuncionesCaja::LimpiarNumero($arraySubtotalPadron[$Padr_on]);
                          else
                              $arrayDescuentoIndividual[$Padr_on][] =0;

                      }
                     $arrayDescuentoIndividual= FuncionesCaja::ProrratiarDescuentoIndividual(FuncionesCaja::LimpiarNumero($Descuentos[2]), $arrayDescuentoIndividual);
                     #precode($arrayDescuentoIndividual,1);
                      $Totales = 0;
                     $keys = array_keys($arrPadr_on);



                    foreach ($arrPadr_on as $key => $Padr_on) {
                        $arrContribuyente = array_unique($arreglo['Contribuyente'.$Padr_on]);
                        $arr = FuncionesCaja::ReciboLicenciaFuncionamiento($arreglo['Licencia'.$Padr_on], $Padr_on, $arrContribuyente, $datosTicket->Pago,$datosTicket->Cliente,$arrayDescuentoIndividual[$Padr_on]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);

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

    include_once( app_path() . '/Libs/Wkhtmltopdf.php' );
    try {
        $nombre = uniqid() . "_" . $idTiket;
       // $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'margins' => ['top' => 10, 'left' => 10,'right'=>10,'bottom'=>10]));
       		$wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'FooterStyleLeft'=>'Grupo Piacza - SUINPAC Contable', 'FooterStyleCenter'=>'Pag. [page] de [toPage]','FooterStyleRight'=>'Ticket No: '.$idTiket));

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

public static function comprobanteDePagoV2(Request $request){
    $cliente = intval($request->Cliente);
    $idTiket = intval($request->IdTiket);
    if(isset($request->IdTicket) && $request->IdTicket!="")
        $idTiket = intval($request->IdTicket);
    #validacion de que se ingresan valores enteros
    if (!is_int($idTiket) || $idTiket=='' || !is_int($cliente) || $cliente=='') {
        return response()->json([
            'success' => '0',
            'error' => 'Datos Invalidos'
        ], 200);
    }
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

        if($concepto[15] == 37 || $concepto[15] == 1 || $concepto[15] == 25 || $concepto[15] == 21  ||  $concepto[15] == 36||  $concepto[15] == 22|| $concepto[15] == 23 || $concepto[15] == 31|| $concepto[15] == 32){ #Servicio
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

        }else if($concepto[15] == 9 || $concepto[15] == 2 || $concepto[15] == 16){ #Agua Potable

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
            $arr= FuncionesCaja::ReciboDeServicioGeneral($idTiket,$arregloServicio['Concepto'.$Contribuyente], $Contribuyente, $datosTicket->Pago,$datosTicket->Cliente,$arrayDescuentoIndividual[$Contribuyente]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);
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
            $arraySubtotalPadron[$Padr_on]=array_sum(FuncionesCaja::LimpiarNumero($SubtotalParte['SubtotalPadr_on'.$Padr_on]));
        $TotalPadrones = array_sum(FuncionesCaja::LimpiarNumero($arraySubtotalPadron));
        foreach ($arrPadr_on as $key => $Padr_on){
            if($Descuentos[2]>0)
                $arrayDescuentoIndividual[$Padr_on][] =((floatval(FuncionesCaja::LimpiarNumero($Descuentos[2])) / floatval(FuncionesCaja::LimpiarNumero($TotalPadrones))) * floatval(FuncionesCaja::LimpiarNumero($arraySubtotalPadron[$Padr_on])));
            else
                $arrayDescuentoIndividual[$Padr_on][] =0;
        }
        #precode($arrayDescuentoIndividual,1,1);
        $arrayDescuentoIndividual= FuncionesCaja::ProrratiarDescuentoIndividual(FuncionesCaja::LimpiarNumero($Descuentos[2]), $arrayDescuentoIndividual);
        #precode($arrayDescuentoIndividual,1);
        $Totales = 0;
        $keys = array_keys($arrPadr_on);
        foreach ($arrPadr_on as $key => $Padr_on) {
            $arrContribuyente = array_unique($arreglo['Contribuyente'.$Padr_on]);
            $arr = FuncionesCaja::ReciboPredialISAI($idTiket,$arreglo['PredialISAI'.$Padr_on], $Padr_on, $arrContribuyente, $datosTicket->Pago,$datosTicket->Cliente,$arrayDescuentoIndividual[$Padr_on]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);
            $Recibos.= $arr['html'];
            $NoRecibos=$arr['NoRecibos'];
        }

    }

    if(isset($arreglo['Licencia']) && isset($arreglo['Tipo']))
    {
        $arraySubtotalPadron=array();
        $arrPadr_on = array_unique($arreglo['Licencia']);
        foreach ($arrPadr_on as $key => $Padr_on)
            $arraySubtotalPadron[$Padr_on]=array_sum(FuncionesCaja::LimpiarNumero($SubtotalParte['SubtotalPadr_on'.$Padr_on]));
        $TotalPadrones = array_sum(FuncionesCaja::LimpiarNumero($arraySubtotalPadron));
        foreach ($arrPadr_on as $key => $Padr_on){
            if($Descuentos[2]>0)
                $arrayDescuentoIndividual[$Padr_on][] = (FuncionesCaja::LimpiarNumero($Descuentos[2]) / FuncionesCaja::LimpiarNumero($TotalPadrones)) * FuncionesCaja::LimpiarNumero($arraySubtotalPadron[$Padr_on]);
            else
                $arrayDescuentoIndividual[$Padr_on][] =0;

        }
        $arrayDescuentoIndividual= FuncionesCaja::ProrratiarDescuentoIndividual(FuncionesCaja::LimpiarNumero($Descuentos[2]), $arrayDescuentoIndividual);
        #precode($arrayDescuentoIndividual,1);
        $Totales = 0;
        $keys = array_keys($arrPadr_on);



        foreach ($arrPadr_on as $key => $Padr_on) {
            $arrContribuyente = array_unique($arreglo['Contribuyente'.$Padr_on]);
            $arr = FuncionesCaja::ReciboLicenciaFuncionamiento($arreglo['Licencia'.$Padr_on], $Padr_on, $arrContribuyente, $datosTicket->Pago,$datosTicket->Cliente,$arrayDescuentoIndividual[$Padr_on]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);

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

    include_once( app_path() . '/Libs/Wkhtmltopdf.php' );
    try {
        $nombre = uniqid() . "_" . $idTiket;
        // $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'margins' => ['top' => 10, 'left' => 10,'right'=>10,'bottom'=>10]));
        $wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'FooterStyleLeft'=>'Grupo Piacza - SUINPAC Contable', 'FooterStyleCenter'=>'Pag. [page] de [toPage]','FooterStyleRight'=>'Ticket No: '.$idTiket));

        $wkhtmltopdf->setHtml($HTML);
        //$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, $nombre.".pdf");
        $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $nombre . ".pdf");
        //return "repositorio/temporal/" . $nombre . ".pdf";
        $response=[
            'success' => '1',
            'ruta' => "repositorio/temporal/" . $nombre . ".pdf",
            'rutaCompleta' => "https://suinpac.com/repositorio/temporal/" . $nombre . ".pdf",
            'nombre' => $nombre. ".pdf"
        ];
        $resCredencial = FuncionesServidor::serverCredenciales();
        $connection = ssh2_connect('servicioenlinea.mx', 22);
        ssh2_auth_password($connection, $resCredencial->original['Usuario'], $resCredencial->original['Contra']);//Actualizar si hay cambio en el usuario del servidor, ya que sino colapsa y no mostrara informacion...
        ssh2_scp_send($connection, "repositorio/temporal/" . $nombre . ".pdf", "tmp/" . $nombre . ".pdf", 0644);

        $result = Funciones::respondWithToken($response);
        return $result;

    } catch (Exception $e) {
        echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
    }

}

    public static function comprobanteDePagoV2TEST(Request $request){
        return "test desactivado, endpoint correcto comprobanteDePagoV2";
    }

    public static function comprobanteDePagoDos(Request $request){
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

            }else if($concepto[15] == 9 || $concepto[15] == 2 || $concepto[15] == 16){ #Agua Potable

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
                $arr= FuncionesCaja::ReciboDeServicioGeneral($idTiket,$arregloServicio['Concepto'.$Contribuyente], $Contribuyente, $datosTicket->Pago,$datosTicket->Cliente,$arrayDescuentoIndividual[$Contribuyente]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);
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
                $arraySubtotalPadron[$Padr_on]=array_sum(FuncionesCaja::LimpiarNumero($SubtotalParte['SubtotalPadr_on'.$Padr_on]));
            $TotalPadrones = array_sum(FuncionesCaja::LimpiarNumero($arraySubtotalPadron));
            foreach ($arrPadr_on as $key => $Padr_on){
                if($Descuentos[2]>0)
                    $arrayDescuentoIndividual[$Padr_on][] =((floatval(FuncionesCaja::LimpiarNumero($Descuentos[2])) / floatval(FuncionesCaja::LimpiarNumero($TotalPadrones))) * floatval(FuncionesCaja::LimpiarNumero($arraySubtotalPadron[$Padr_on])));
                else
                    $arrayDescuentoIndividual[$Padr_on][] =0;
            }
            #precode($arrayDescuentoIndividual,1,1);
            $arrayDescuentoIndividual= FuncionesCaja::ProrratiarDescuentoIndividual(FuncionesCaja::LimpiarNumero($Descuentos[2]), $arrayDescuentoIndividual);
            #precode($arrayDescuentoIndividual,1);
            $Totales = 0;
            $keys = array_keys($arrPadr_on);
            foreach ($arrPadr_on as $key => $Padr_on) {
                $arrContribuyente = array_unique($arreglo['Contribuyente'.$Padr_on]);
                $arr = FuncionesCaja::ReciboPredialISAI($idTiket,$arreglo['PredialISAI'.$Padr_on], $Padr_on, $arrContribuyente, $datosTicket->Pago,$datosTicket->Cliente,$arrayDescuentoIndividual[$Padr_on]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);
                $Recibos.= $arr['html'];
                $NoRecibos=$arr['NoRecibos'];
            }

        }

        if(isset($arreglo['Licencia']) && isset($arreglo['Tipo']))
        {
            $arraySubtotalPadron=array();
            $arrPadr_on = array_unique($arreglo['Licencia']);
            foreach ($arrPadr_on as $key => $Padr_on)
                $arraySubtotalPadron[$Padr_on]=array_sum(FuncionesCaja::LimpiarNumero($SubtotalParte['SubtotalPadr_on'.$Padr_on]));
            $TotalPadrones = array_sum(FuncionesCaja::LimpiarNumero($arraySubtotalPadron));
            foreach ($arrPadr_on as $key => $Padr_on){
                if($Descuentos[2]>0)
                    $arrayDescuentoIndividual[$Padr_on][] = (FuncionesCaja::LimpiarNumero($Descuentos[2]) / FuncionesCaja::LimpiarNumero($TotalPadrones)) * FuncionesCaja::LimpiarNumero($arraySubtotalPadron[$Padr_on]);
                else
                    $arrayDescuentoIndividual[$Padr_on][] =0;

            }
            $arrayDescuentoIndividual= FuncionesCaja::ProrratiarDescuentoIndividual(FuncionesCaja::LimpiarNumero($Descuentos[2]), $arrayDescuentoIndividual);
            #precode($arrayDescuentoIndividual,1);
            $Totales = 0;
            $keys = array_keys($arrPadr_on);



            foreach ($arrPadr_on as $key => $Padr_on) {
                $arrContribuyente = array_unique($arreglo['Contribuyente'.$Padr_on]);
                $arr = FuncionesCaja::ReciboLicenciaFuncionamiento($arreglo['Licencia'.$Padr_on], $Padr_on, $arrContribuyente, $datosTicket->Pago,$datosTicket->Cliente,$arrayDescuentoIndividual[$Padr_on]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);

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

        include_once( app_path() . '/Libs/Wkhtmltopdf.php' );
        try {
            $nombre = uniqid() . "_" . $idTiket;
            // $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'margins' => ['top' => 10, 'left' => 10,'right'=>10,'bottom'=>10]));
            $wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'FooterStyleLeft'=>'Grupo Piacza - SUINPAC Contable', 'FooterStyleCenter'=>'Pag. [page] de [toPage]','FooterStyleRight'=>'Ticket No: '.$idTiket));

            $wkhtmltopdf->setHtml($HTML);
            //$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, $nombre.".pdf");
            $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $nombre . ".pdf");
            //return "repositorio/temporal/" . $nombre . ".pdf";
            $response=[
                'success' => '1',
                'ruta' => "repositorio/temporal/" . $nombre . ".pdf",
            ];

            $result = Funciones::respondWithToken($response);
            return $result;

        } catch (Exception $e) {
            echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
        }

    }

    public static function comprobanteDePagoCopia(Request $request){
        $cliente=$request->Cliente;
        $idTiket=$request->IdTiket;
        $tipoPago=$request->TipoPago;
        $referencia=$request->Referencia;
        $extras=$request->Extras;

        Funciones::selecionarBase($cliente);

        if($tipoPago==0){


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
                $arr= FuncionesCaja::ReciboDeServicioGeneral($idTiket,$arregloServicio['Concepto'.$Contribuyente], $Contribuyente, $datosTicket->Pago,$datosTicket->Cliente,$arrayDescuentoIndividual[$Contribuyente]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);
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
                $arraySubtotalPadron[$Padr_on]=array_sum(FuncionesCaja::LimpiarNumero($SubtotalParte['SubtotalPadr_on'.$Padr_on]));
            $TotalPadrones = array_sum(FuncionesCaja::LimpiarNumero($arraySubtotalPadron));
            foreach ($arrPadr_on as $key => $Padr_on){
                if($Descuentos[2]>0)
                    $arrayDescuentoIndividual[$Padr_on][] =((floatval(FuncionesCaja::LimpiarNumero($Descuentos[2])) / floatval(FuncionesCaja::LimpiarNumero($TotalPadrones))) * floatval(FuncionesCaja::LimpiarNumero($arraySubtotalPadron[$Padr_on])));
                else
                    $arrayDescuentoIndividual[$Padr_on][] =0;
            }
            #precode($arrayDescuentoIndividual,1,1);
            $arrayDescuentoIndividual= FuncionesCaja::ProrratiarDescuentoIndividual(FuncionesCaja::LimpiarNumero($Descuentos[2]), $arrayDescuentoIndividual);
            #precode($arrayDescuentoIndividual,1);
            $Totales = 0;
            $keys = array_keys($arrPadr_on);
            foreach ($arrPadr_on as $key => $Padr_on) {
                $arrContribuyente = array_unique($arreglo['Contribuyente'.$Padr_on]);
                $arr = FuncionesCaja::ReciboPredialISAI($idTiket,$arreglo['PredialISAI'.$Padr_on], $Padr_on, $arrContribuyente, $datosTicket->Pago,$datosTicket->Cliente,$arrayDescuentoIndividual[$Padr_on]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);
                $Recibos.= $arr['html'];
                $NoRecibos=$arr['NoRecibos'];
            }

        }

        if(isset($arreglo['Licencia']) && isset($arreglo['Tipo']))
        {
            $arraySubtotalPadron=array();
            $arrPadr_on = array_unique($arreglo['Licencia']);
            foreach ($arrPadr_on as $key => $Padr_on)
                $arraySubtotalPadron[$Padr_on]=array_sum(FuncionesCaja::LimpiarNumero($SubtotalParte['SubtotalPadr_on'.$Padr_on]));
            $TotalPadrones = array_sum(FuncionesCaja::LimpiarNumero($arraySubtotalPadron));
            foreach ($arrPadr_on as $key => $Padr_on){
                if($Descuentos[2]>0)
                    $arrayDescuentoIndividual[$Padr_on][] = (FuncionesCaja::LimpiarNumero($Descuentos[2]) / FuncionesCaja::LimpiarNumero($TotalPadrones)) * FuncionesCaja::LimpiarNumero($arraySubtotalPadron[$Padr_on]);
                else
                    $arrayDescuentoIndividual[$Padr_on][] =0;

            }
            $arrayDescuentoIndividual= FuncionesCaja::ProrratiarDescuentoIndividual(FuncionesCaja::LimpiarNumero($Descuentos[2]), $arrayDescuentoIndividual);
            #precode($arrayDescuentoIndividual,1);
            $Totales = 0;
            $keys = array_keys($arrPadr_on);



            foreach ($arrPadr_on as $key => $Padr_on) {
                $arrContribuyente = array_unique($arreglo['Contribuyente'.$Padr_on]);
                $arr = FuncionesCaja::ReciboLicenciaFuncionamiento($arreglo['Licencia'.$Padr_on], $Padr_on, $arrContribuyente, $datosTicket->Pago,$datosTicket->Cliente,$arrayDescuentoIndividual[$Padr_on]/*$datosTicket['Descuentos']*/,  $_POST['PagoAnticipado'],$NoRecibos);

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

        include_once( app_path() . '/Libs/Wkhtmltopdf.php' );
        try {
            $nombre = uniqid() . "_" . $idTiket;
            // $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'margins' => ['top' => 10, 'left' => 10,'right'=>10,'bottom'=>10]));
            $wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'FooterStyleLeft'=>'Grupo Piacza - SUINPAC Contable', 'FooterStyleCenter'=>'Pag. [page] de [toPage]','FooterStyleRight'=>'Ticket No: '.$idTiket));

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

        }else if($tipoPago==1){

            $_POST=json_decode($extras,true);

            $_POST['autorizacion'] = $_POST['autorizacion']>0?str_replace(",", "",$_POST['autorizacion']):0;

            $_POST['s_transm']= $_POST['s_transm']>0?str_replace(",", "",$_POST['s_transm']):0;

            $url = 'https://suinpac.com/ConsultarReciboPagoAnticipadoBancoLinea.php';
            $dataForPost = array(
                "DetalleMovimiento" => $idTiket,
                "N_umeroMovimientoBancarioCheque" => $_POST['autorizacion'],
                "Cliente" => $cliente,
                "Referencia"=>$referencia,
                "Folio"=>$_POST['s_transm']
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
                'success' => '1',
                'ruta' => $result,
            ]);


        }

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
         $condicion="291, 144,818,884";
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


public static function firmarDocumento(Request $request){
    $cliente=$request->Cliente;
   // $cotizacion=$request->Cotizacion;
    $idTiket=$request->Ticket;
    $idPadron=$request->IdPadron;


    Funciones::selecionarBase($cliente);



    $cotizacion=Funciones::ObtenValor("select Pago.Cotizaci_on from Pago join PagoTicket on Pago.id=PagoTicket.Pago where PagoTicket.id=".$idTiket,"Cotizaci_on");

    $documento=DB::select("SELECT
    cd.id,
    cd.Nombre
    FROM Cotizaci_on c
    INNER JOIN ConceptoAdicionalesCotizaci_on cac ON (cac.Cotizaci_on=c.id)
    INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=cac.ConceptoAdicionales)
    INNER JOIN CatalogoDocumentos cd on cd.id=ccc.CatalogoDocumento

    WHERE
    c.id=$cotizacion GROUP BY Nombre");

    if($documento[0]->id==3){
       $ruta= PortalController::deslindeNoDisponible($cliente,$idPadron);
       $ruta= $ruta->original['ruta'];
    }else{

        $url = 'https://suinpac.com/FirmaElectronicaPagoLinea.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "Cotizacion"=>$cotizacion

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

        $porciones = explode("$*",  $result);


        $documento[0]->Nombre;
        if(empty($porciones[1])){
            //si no se mando a firmar entra a esta condicion
            $ruta=$porciones[0];
        }else{
            // se firmo automaticamente
            $ruta=$porciones[1];
        }
    }
    return response()->json([
        'success' => 1,
        'ruta' => $ruta,
        'nombreDocumento'=>$documento[0]->Nombre,
        'idDocumento'=>$documento[0]->id

    ], 200);
}
public static function firmarDocumentoV2DEV(Request $request){
    $cliente=$request->Cliente;
    // $cotizacion=$request->Cotizacion;
    $idTiket=$request->Ticket;
    $idPadron=$request->IdPadron;

    Funciones::selecionarBase($cliente);

    $cotizacion=Funciones::ObtenValor("select Pago.Cotizaci_on from Pago join PagoTicket on Pago.id=PagoTicket.Pago where PagoTicket.id=".$idTiket,"Cotizaci_on");

    $documento=DB::select("SELECT
cd.id,
cd.Nombre
FROM Cotizaci_on c
INNER JOIN ConceptoAdicionalesCotizaci_on cac ON (cac.Cotizaci_on=c.id)
INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=cac.ConceptoAdicionales)
INNER JOIN CatalogoDocumentos cd on cd.id=ccc.CatalogoDocumento
WHERE
c.id=$cotizacion GROUP BY Nombre");

    if($documento[0]->id==3){
        $ruta= PortalController::deslindeNoDisponible($cliente,$idPadron);
        $ruta= $ruta->original['ruta'];
    }else{
        $url = 'https://suinpac.com/FirmaElectronicaPagoLinea.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "Cotizacion"=>$cotizacion
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

        $porciones = explode("$*",  $result);


        $documento[0]->Nombre;
        if(empty($porciones[1])){
            //si no se mando a firmar entra a esta condicion
            $ruta=$porciones[0];
        }else{
            // se firmo automaticamente
            $ruta=$porciones[1];
        }
    }


    $iparr = explode("/",$ruta);
    $resCredencial = FuncionesServidor::serverCredenciales();
    $connection = ssh2_connect('servicioenlinea.mx', 22);
    ssh2_auth_password($connection, $resCredencial->original['Usuario'], $resCredencial->original['Contra']);//Actualizar si hay cambio en el usuario del servidor, ya que sino colapsa y no mostrara informacion...
    ssh2_scp_send($connection, $ruta, "tmp/" . $iparr[2], 0644);

    $response=[
        'success' => 1,
        'ruta' => $ruta,
        'nombreDocumento'=>$documento[0]->Nombre,
        'idDocumento'=>$documento[0]->id,
        'nombre'=>$iparr[2],

    ];

    $result = Funciones::respondWithToken($response);

    return $result;
}

    public static function firmarDocumentoV2(Request $request){
        $cliente=$request->Cliente;
        // $cotizacion=$request->Cotizacion;
        $idTiket=$request->Ticket;
        $idPadron=$request->IdPadron;

        Funciones::selecionarBase($cliente);

        $cotizacion=Funciones::ObtenValor("select Pago.Cotizaci_on from Pago join PagoTicket on Pago.id=PagoTicket.Pago where PagoTicket.id=".$idTiket,"Cotizaci_on");

        $documento=DB::select("SELECT
    cd.id,
    cd.Nombre
    FROM Cotizaci_on c
    INNER JOIN ConceptoAdicionalesCotizaci_on cac ON (cac.Cotizaci_on=c.id)
    INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=cac.ConceptoAdicionales)
    INNER JOIN CatalogoDocumentos cd on cd.id=ccc.CatalogoDocumento
    WHERE
    c.id=$cotizacion GROUP BY Nombre");

        if($documento[0]->id==3){
            $ruta= PortalController::deslindeNoDisponible($cliente,$idPadron);
            $ruta= $ruta->original['ruta'];
        }else{
            $url = 'https://suinpac.com/FirmaElectronicaPagoLinea.php';
            $dataForPost = array(
                'Cliente'=> [
                    "Cliente"=>$cliente,
                    "Cotizacion"=>$cotizacion
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

            $porciones = explode("$*",  $result);


            $documento[0]->Nombre;
            if(empty($porciones[1])){
                //si no se mando a firmar entra a esta condicion
                $ruta=$porciones[0];
            }else{
                // se firmo automaticamente
                $ruta=$porciones[1];
            }
        }


        $iparr = explode("/",$ruta);
        $resCredencial = FuncionesServidor::serverCredenciales();
        $connection = ssh2_connect('servicioenlinea.mx', 22);
        ssh2_auth_password($connection, $resCredencial->original['Usuario'], $resCredencial->original['Contra']);//Actualizar si hay cambio en el usuario del servidor, ya que sino colapsa y no mostrara informacion...
        ssh2_scp_send($connection, $ruta, "tmp/" . $iparr[2], 0644);

        $response=[
            'success' => 1,
            'ruta' => $ruta,
            'nombreDocumento'=>$documento[0]->Nombre,
            'idDocumento'=>$documento[0]->id,
            'nombre'=>$iparr[2],

        ];

        $result = Funciones::respondWithToken($response);

        return $result;
    }


    public static function firmarDocumentoCopia(Request $request){
        $cliente=$request->Cliente;
        // $cotizacion=$request->Cotizacion;
        $idTiket=$request->Ticket;
        $idPadron=$request->IdPadron;


        Funciones::selecionarBase($cliente);



        $cotizacion=Funciones::ObtenValor("select Pago.Cotizaci_on from Pago join PagoTicket on Pago.id=PagoTicket.Pago where PagoTicket.id=".$idTiket,"Cotizaci_on");

        $documento=DB::select("SELECT
    cd.id,
    cd.Nombre
    FROM Cotizaci_on c
    INNER JOIN ConceptoAdicionalesCotizaci_on cac ON (cac.Cotizaci_on=c.id)
    INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=cac.ConceptoAdicionales)
    INNER JOIN CatalogoDocumentos cd on cd.id=ccc.CatalogoDocumento

    WHERE
    c.id=$cotizacion GROUP BY Nombre");


        if( $documento[0]->id==3){
            $ruta= PortalController::deslindeNoDisponible($cliente,$idPadron);
            $ruta= $ruta->original['ruta'];
        }else{

            $url = 'https://suinpac.com/FirmaElectronicaPagoLinea.php';
            $dataForPost = array(
                'Cliente'=> [
                    "Cliente"=>$cliente,
                    "Cotizacion"=>$cotizacion

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

            $porciones = explode("$*",  $result);


            $documento[0]->Nombre;
            if(empty($porciones[1])){
                //si no se mando a firmar entra a esta condicion
                $ruta=$porciones[0];
            }else{
                // se firmo automaticamente
                $ruta=$porciones[1];
            }
        }



        $response=[
            'success' => 1,
            'ruta' => $ruta,
            'nombreDocumento'=>  $documento[0]->Nombre,
            'idDocumento'=>  $documento[0]->id

        ];

        $result = Funciones::respondWithToken($response);

        return $result;
    }


public static function obtnerFacturaPagoLinea(Request $request){
    $cliente=$request->Cliente;
    $idTicket=$request->IdTicket;
    $idPadron=$request->IdPadron;
    Funciones::selecionarBase($cliente);

    $url = 'https://suinpac.com/FacturaPagoLinea.php';
    $dataForPost = array(
        'Cliente'=> [
            "Cliente"=>$cliente,
            "IdTicket"=>$idTicket

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
    $ruta;


    if($result=="Hubo un error al generar el PDF: HTML content or source URL not set"){

        $ruta=  PortalController::errorFactura($cliente,$idPadron);
        return $ruta;
        $ruta= $ruta->original['ruta'];
    }else{
        $ruta=$result;
    }

    return response()->json([
        'success' => 1,
        'ruta' => $ruta
    ], 200);
}

    public static function obtnerFacturaPagoLineaCopia(Request $request){
        $cliente=$request->Cliente;
        $idTicket=$request->IdTicket;
        $idPadron=$request->IdPadron;
        Funciones::selecionarBase($cliente);

        $url = 'https://suinpac.piacza.com.mx/FacturaPagoLinea.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "IdTicket"=>$idTicket

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


        if($result=="Hubo un error al generar el PDF: HTML content or source URL not set"){

            $ruta=  PortalController::errorFactura($cliente,$idPadron);
            //return $ruta;
            $ruta= $ruta->original['ruta'];
        }else{
            $ruta=$result;
        }

       $response=[
            'success' => '1',
            'ruta' => $ruta,
        ];

        $result = Funciones::respondWithToken($response);

        return $result;

    }


    public static function obtnerFacturaPagoLineaCopiaDos(Request $request){
        $cliente=$request->Cliente;
        $idTicket=$request->IdTicket;
        $idPadron=$request->IdPadron;
        Funciones::selecionarBase($cliente);

        $url = 'https://suinpac.piacza.com.mx/FacturaPagoLinea.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "IdTicket"=>$idTicket

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



        if($result=="Hubo un error al generar el PDF: HTML content or source URL not set"){

            $ruta=  PortalController::errorFactura($cliente,$idPadron);
            //return $ruta;
            //return "Error";
            $ruta= $ruta->original['ruta'];
            $estatus=0;
        }else{
            $ruta=$result;
            $estatus=1;
        }

        $response=[
            'success' => 1,
            'ruta' => $ruta,
            'estatus' => $estatus,
        ];

        $result = Funciones::respondWithToken($response);

        return $result;

    }

    public static function obtnerFacturaPagoLineaV2(Request $request){
        $cliente=$request->Cliente;
        $idTicket=$request->IdTicket;
        $idPadron=$request->IdPadron;
        Funciones::selecionarBase($cliente);

        $url = 'https://suinpac.com/FacturaPagoLinea.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "IdTicket"=>$idTicket

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


        if($result=="Hubo un error al generar el PDF: HTML content or source URL not set"){

            $ruta=  PortalController::errorFactura($cliente,$idPadron);
            //return $ruta;
            //return "Error";
            $ruta= $ruta->original['ruta'];
            $estatus=0;
            $iparr = explode("/",$ruta);


        }else{
            $ruta=$result;
            $estatus=1;

            $iparr = explode("/",$ruta);
            $resCredencial = FuncionesServidor::serverCredenciales();
            $connection = ssh2_connect('servicioenlinea.mx', 22);
            ssh2_auth_password($connection, $resCredencial->original['Usuario'], $resCredencial->original['Contra']);//Actualizar si hay cambio en el usuario del servidor, ya que sino colapsa y no mostrara informacion...
            ssh2_scp_send($connection, $ruta, "tmp/" . $iparr[2], 0644);
        }

        $response=[
            'success' => 1,
            'ruta' => $ruta,
            'estatus' => $estatus,
            'nombre' => $iparr[2]
        ];

        $result = Funciones::respondWithToken($response);

        return $result;

    }

    public static function modificarEstatusReferenciado(Request $request){

        $idReferenciado=$request-> IdReferenciado;
        $cliente=$request->Cliente;

        Funciones::selecionarBase($cliente);

        $dato= DB::table('DatospagoReferenciados')
            ->where('id', $idReferenciado)
            ->update(["estatus"=>1]);

        $response=[
            'success' => '1',
            'idRegresado' => $idReferenciado,
        ];


        $result = Funciones::respondWithToken($response);
        return $result;

    }


    public static function obtnerFacturaPagoLineaV2Copia(Request $request){
        $cliente=$request->Cliente;
        $idTicket=$request->IdTicket;
        $idPadron=$request->IdPadron;
        Funciones::selecionarBase($cliente);

        $url = 'https://suinpac.com/FacturaPagoLinea.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "IdTicket"=>$idTicket
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

        if($result=="Hubo un error al generar el PDF: HTML content or source URL not set"){

            //$ruta=  PortalController::errorFactura($cliente,$idPadron);
            //return $ruta;
            //return "Error";
            $ruta=$idPadron;
            //$ruta= $ruta->original['ruta'];
            $estatus=0;
            //$iparr = explode("/",$ruta);


        }else{
            $ruta=$result;
            $estatus=1;
            $iparr = explode("/",$ruta);
            $resCredencial = FuncionesServidor::serverCredenciales();
            $connection = ssh2_connect('servicioenlinea.mx', 22);
            ssh2_auth_password($connection, $resCredencial->original['Usuario'], $resCredencial->original['Contra']);//Actualizar si hay cambio en el usuario del servidor, ya que sino colapsa y no mostrara informacion...
            ssh2_scp_send($connection, $ruta, "tmp/" . $iparr[2], 0644);
        }
        $response=[
            'success' => 1,
            'ruta' => $ruta,
            'estatus' => $estatus,
            //'nombre' => $iparr[2]
        ];

        $response=[
            'success' => 1,
            'ruta' => $result,
            'estatus' => $estatus,
        ];

        $result = Funciones::respondWithToken($response);

        return $result;

    }



    public static function moverArchivo(Request $request){

        $ruta= $request -> Ruta;

        $iparr = explode("/",$ruta);

        $resCredencial = FuncionesServidor::serverCredenciales();
        $connection = ssh2_connect('servicioenlinea.mx', 22);
        ssh2_auth_password($connection, $resCredencial->original['Usuario'], $resCredencial->original['Contra']);//Actualizar si hay cambio en el usuario del servidor, ya que sino colapsa y no mostrara informacion...
        ssh2_scp_send($connection, $ruta, "tmp/" . $iparr['2'], 0644);

        $nombre=$iparr['2'];
        $result = Funciones::respondWithToken($nombre);
        return $result;

    }

public static function descargarXML(Request $request){
    $cliente=$request->Cliente;
    $idTiket=$request->Ticket;


    Funciones::selecionarBase($cliente);

    $cotizacion=Funciones::ObtenValor("select Pago.Cotizaci_on from Pago join PagoTicket on Pago.id=PagoTicket.Pago where PagoTicket.id=".$idTiket,"Cotizaci_on");
    $xml = Funciones::ObtenValor("SELECT XML.xml as elxml
    FROM XMLIngreso x
        INNER JOIN XML ON (XML.id = x.xml )
    WHERE
        x.idCotizaci_on = ".$cotizacion."
        ORDER BY x.id ASC ", "elxml" );

    return response()->json([
        'success' => '1',
        'xml' => $xml,
    ]);
}


public static function errorFactura($cliente,$idPadron){



    $ServerNameURL=Funciones::ObtenValor("SELECT Valor FROM CelaConfiguraci_on WHERE Nombre='URLSitio'","Valor");

    $Cliente=Funciones::ObtenValor("Select EsMunicipio from Cliente where id=".$cliente,"EsMunicipio");
    if($Cliente==1){
        $tabla="Padr_onCatastral";
    }else{
        $tabla="Padr_onAguaPotable";
    }
    $DatosPadron=Funciones::ObtenValor("SELECT *,  pa.Cuenta as CuentaOK,
        (SELECT Nombre FROM Municipio WHERE id=d.Municipio) as Municipio,
        (SELECT Nombre FROM EntidadFederativa WHERE id=d.EntidadFederativa) as Estado,
        (SELECT Nombre FROM Municipio WHERE id=pa.Municipio) as MunicipioPredio,
        (SELECT Nombre FROM Localidad WHERE id=pa.Localidad) as LocalidadPredio,
        CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno, c.NombreComercial ) as Propietario,
        pa.Colonia as paColonia, d.Colonia as Colonia
                FROM ".$tabla." pa
                INNER JOIN Contribuyente c ON (c.id=pa.Contribuyente)
                INNER JOIN DatosFiscales d ON (d.id=c.DatosFiscales)
                WHERE
                pa.id=".$idPadron);

    if(isset($DatosPadron->result) && $DatosPadron->result=="ERROR"){
        $DatosPadron=Funciones::ObtenValor("SELECT *,
        (SELECT Nombre FROM Municipio WHERE id=d.Municipio) as Municipio,
        (SELECT Nombre FROM EntidadFederativa WHERE id=d.EntidadFederativa) as Estado,
        (SELECT Nombre FROM Municipio WHERE id=pa.Municipio) as MunicipioPredio,
        (SELECT Nombre FROM Localidad WHERE id=pa.Localidad) as LocalidadPredio,
        CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno, c.NombreComercial ) as Propietario,
        d.Colonia as paColonia, d.Colonia as Colonia
                FROM Padr_onLicencia pa
                INNER JOIN Contribuyente c ON (c.id=pa.Contribuyente)
                INNER JOIN DatosFiscales d ON (d.id=c.DatosFiscales)
                WHERE
                pa.id=".$idPadron);
    }
    $DatosCliente=Funciones::ObtenValor("SELECT *, m.Nombre as Municipio, e.Nombre as Estado, cr.Ruta  as Logotipo
    FROM Cliente c
    INNER JOIN DatosFiscalesCliente d ON (d.id=c.DatosFiscales)
    INNER JOIN EntidadFederativa e ON (e.id=d.EntidadFederativa)
    INNER JOIN Municipio m ON (m.id=d.Municipio)
    INNER JOIN CelaRepositorioC cr ON (cr.idRepositorio=c.Logotipo)
    WHERE c.id=". $cliente);



        $Copropietarios="";
        $ConsultaCopropietarios = "SELECT ( SELECT CONCAT(IF (c.NombreComercial IS NULL OR c.NombreComercial='',CONCAT_WS(' ',c.Nombres,c.ApellidoPaterno,c.ApellidoMaterno),c.NombreComercial))
        FROM `Contribuyente` c WHERE c.id=pcc.idContribuyente) AS CoPropietario FROM Padr_onCatastralCopropietarios pcc WHERE pcc.idPadron=".$idPadron;
        $ejecutaCopropietarios=DB::select($ConsultaCopropietarios);
        $row_cnt = count($ejecutaCopropietarios);
        $aux=1;
        foreach($ejecutaCopropietarios as $registroCopropietarios){
            if($aux==$row_cnt){
                $Copropietarios.=$registroCopropietarios->CoPropietario.'<br /> ';
            }else{
                $Copropietarios.=$registroCopropietarios->CoPropietario.', <br /> ';
            }
            $aux++;
        }
        if($Copropietarios!=""){
            $Copropietarios = '<b>Copropietarios:</b> '.$Copropietarios ;
        }


    //cuenta de deposito
    $ConsultaCuentas="SELECT c.N_umeroCuenta, c.Clabe, b.Nombre as Banco FROM CuentaBancaria c INNER JOIN Banco b ON (b.id=c.Banco) WHERE c.Cliente=".$cliente." AND c.CuentaDeRecaudacion=1";
    $ejecutaCuentas=DB::select($ConsultaCuentas);
    $lascuentas='';
    foreach($ejecutaCuentas as $registroCuentas){
        $lascuentas.='<tr>
            <td colspan="2" align="center">
                '.$registroCuentas->Banco.'
            </td>
            <td colspan="2" align="center">
                '.$registroCuentas->N_umeroCuenta.'
            </td>
            <td colspan="2" align="center">
                '.$registroCuentas->Clabe.'
            </td>
        </tr>';

    }

        $htmlGlobal='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml">
        <link href="'. asset(Storage::url(env('RESOURCES').'bootstrap.min.css')).'">

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
            <td  colspan="4"  width="66.5%" align="right">
                '.$DatosCliente->NombreORaz_onSocial.'<br />
                Domicilio Fiscal: '.$DatosCliente->Calle.' '.$DatosCliente->N_umeroExterior.'<br />
                '.$DatosCliente->Colonia.', C.P. '.$DatosCliente->C_odigoPostal.'<br />
                '.$DatosCliente->Municipio.', '.$DatosCliente->Estado.'<br />
                RFC: '.$DatosCliente->RFC.'
                <br /><br />
               </td>
        </tr>
        <tr>
            <td colspan="6" align="right"><img width="787px"  height="1px" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" </td></tr>
    </table>
    <table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="787px">

        <tr>

            <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
            <br /><b>Datos del Predio</b><br />
            <br />	<b>Propietario:</b> '.(isset($DatosPadron->Propietario))?$DatosPadron->Propietario:"".'<br />'.$Copropietarios.
                '<b>Ubicaci&oacute;n:</b> '.(isset($DatosPadron->Ubicaci_on)?$DatosPadron->Ubicaci_on:'').' '.$DatosPadron->paColonia.'<br />
                <b>Localidad:</b> '.$DatosPadron->LocalidadPredio.'<br />
                <b>Municipio:</b> '.$DatosPadron->MunicipioPredio.'<br />

            </td>

            <td colspan="3"  width="50%" style="vertical-align:top;" v-align="top">
            <br /><b>Datos de Facturaci&oacute;n</b><br />
            <br /><b>Razon Social:</b> '.$DatosPadron->NombreORaz_onSocial.'<br />
                <b>RFC:</b> '.$DatosPadron->RFC.'<br />
                <b>Direcci&oacute;n:</b> '.$DatosPadron->Calle.' '.$DatosPadron->N_umeroExterior.'
                 '.$DatosPadron->Colonia.', C.P. '.$DatosPadron->C_odigoPostal.'<br />
                '.$DatosPadron->Municipio.', '.$DatosPadron->Estado.'.<br />
            </td>
        </tr>
        <tr>
            <td colspan="6">
                <br /><img width="787px" height="1px" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'"><br /> &nbsp;
            </td>
        </tr>
        <tr style="width: 85%">
            <td colspan="5" width="85%" style="text-align: justify-all">
           <p style="text-align: justify;color:#ff0000; font-size: 50px;">Su factura a&uacute;n no esta disponible acude a nuestras oficinas o comunícate al teléfono: (747) - 121 - 2478</p>

            </td>
        </tr>
         <tr>
            <td colspan="6">
                <br /><img width="787px" height="1px" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'"><br /> &nbsp;
            </td>
        </tr>

    </table>

    <style>

    </style>

</body>
</html>';

include_once( app_path() . '/Libs/Wkhtmltopdf.php' );
try {
    $nombre = uniqid() . "_Factura" . $cliente;
    #$wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'page_width'=>120,'page_height'=>200,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
    $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'margins' => ['top' => 10, 'left' => 10,'right'=>10,'bottom'=>10]));
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
    /**/
}


public static function deslindeNoDisponible($cliente,$idPadron){



    $ServerNameURL=Funciones::ObtenValor("SELECT Valor FROM CelaConfiguraci_on WHERE Nombre='URLSitio'","Valor");

    $Cliente=Funciones::ObtenValor("Select EsMunicipio from Cliente where id=".$cliente,"EsMunicipio");
    if($Cliente==1){
        $tabla="Padr_onCatastral";
    }else{
        $tabla="Padr_onAguaPotable";
    }
    $DatosPadron=Funciones::ObtenValor("SELECT *,  pa.Cuenta as CuentaOK,
        (SELECT Nombre FROM Municipio WHERE id=d.Municipio) as Municipio,
        (SELECT Nombre FROM EntidadFederativa WHERE id=d.EntidadFederativa) as Estado,
        (SELECT Nombre FROM Municipio WHERE id=pa.Municipio) as MunicipioPredio,
        (SELECT Nombre FROM Localidad WHERE id=pa.Localidad) as LocalidadPredio,
        CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno, c.NombreComercial ) as Propietario,
        pa.Colonia as paColonia, d.Colonia as Colonia
                FROM ".$tabla." pa
                INNER JOIN Contribuyente c ON (c.id=pa.Contribuyente)
                INNER JOIN DatosFiscales d ON (d.id=c.DatosFiscales)
                WHERE
                pa.id=".$idPadron);
                #precode($DatosPadron,1,1);

    $DatosCliente=Funciones::ObtenValor("SELECT *, m.Nombre as Municipio, e.Nombre as Estado, cr.Ruta  as Logotipo
    FROM Cliente c
    INNER JOIN DatosFiscalesCliente d ON (d.id=c.DatosFiscales)
    INNER JOIN EntidadFederativa e ON (e.id=d.EntidadFederativa)
    INNER JOIN Municipio m ON (m.id=d.Municipio)
    INNER JOIN CelaRepositorioC cr ON (cr.idRepositorio=c.Logotipo)
    WHERE c.id=". $cliente);



        $Copropietarios="";
        $ConsultaCopropietarios = "SELECT ( SELECT CONCAT(IF (c.NombreComercial IS NULL OR c.NombreComercial='',CONCAT_WS(' ',c.Nombres,c.ApellidoPaterno,c.ApellidoMaterno),c.NombreComercial))
        FROM `Contribuyente` c WHERE c.id=pcc.idContribuyente) AS CoPropietario FROM Padr_onCatastralCopropietarios pcc WHERE pcc.idPadron=".$idPadron;
        $ejecutaCopropietarios=DB::select($ConsultaCopropietarios);
        $row_cnt = count($ejecutaCopropietarios);
        $aux=1;
        foreach($ejecutaCopropietarios as $registroCopropietarios){
            if($aux==$row_cnt){
                $Copropietarios.=$registroCopropietarios->CoPropietario.'<br /> ';
            }else{
                $Copropietarios.=$registroCopropietarios->CoPropietario.', <br /> ';
            }
            $aux++;
        }
        if($Copropietarios!=""){
            $Copropietarios = '<b>Copropietarios:</b> '.$Copropietarios ;
        }


    //cuenta de deposito
    $ConsultaCuentas="SELECT c.N_umeroCuenta, c.Clabe, b.Nombre as Banco FROM CuentaBancaria c INNER JOIN Banco b ON (b.id=c.Banco) WHERE c.Cliente=".$cliente." AND c.CuentaDeRecaudacion=1";
    $ejecutaCuentas=DB::select($ConsultaCuentas);
    $lascuentas='';
    foreach($ejecutaCuentas as $registroCuentas){
        $lascuentas.='<tr>
            <td colspan="2" align="center">
                '.$registroCuentas->Banco.'
            </td>
            <td colspan="2" align="center">
                '.$registroCuentas->N_umeroCuenta.'
            </td>
            <td colspan="2" align="center">
                '.$registroCuentas->Clabe.'
            </td>
        </tr>';

    }

        $htmlGlobal='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml">
        <link href="'. asset(Storage::url(env('RESOURCES').'bootstrap.min.css')).'">

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
            <td  colspan="4"  width="66.5%" align="right">
                '.$DatosCliente->NombreORaz_onSocial.'<br />
                Domicilio Fiscal: '.$DatosCliente->Calle.' '.$DatosCliente->N_umeroExterior.'<br />
                '.$DatosCliente->Colonia.', C.P. '.$DatosCliente->C_odigoPostal.'<br />
                '.$DatosCliente->Municipio.', '.$DatosCliente->Estado.'<br />
                RFC: '.$DatosCliente->RFC.'
                <br /><br />
               </td>
        </tr>
        <tr>
            <td colspan="6" align="right"><img width="787px"  height="1px" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" </td></tr>
    </table>
    <table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="787px">

        <tr>

            <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
            <br /><b>Datos del Predio</b><br />
            <br />	<b>Propietario:</b> '.$DatosPadron->Propietario.'<br />'.$Copropietarios.
                '<b>Ubicaci&oacute;n:</b> '.(isset($DatosPadron->Ubicaci_on)?$DatosPadron->Ubicaci_on:'').' '.$DatosPadron->paColonia.'<br />
                <b>Localidad:</b> '.$DatosPadron->LocalidadPredio.'<br />
                <b>Municipio:</b> '.$DatosPadron->MunicipioPredio.'<br />

            </td>

            <td colspan="3"  width="50%" style="vertical-align:top;" v-align="top">
            <br /><b>Datos de Facturaci&oacute;n</b><br />
            <br /><b>Razon Social:</b> '.$DatosPadron->NombreORaz_onSocial.'<br />
                <b>RFC:</b> '.$DatosPadron->RFC.'<br />
                <b>Direcci&oacute;n:</b> '.$DatosPadron->Calle.' '.$DatosPadron->N_umeroExterior.'
                 '.$DatosPadron->Colonia.', C.P. '.$DatosPadron->C_odigoPostal.'<br />
                '.$DatosPadron->Municipio.', '.$DatosPadron->Estado.'.<br />
            </td>
        </tr>
        <tr>
            <td colspan="6">
                <br /><img width="787px" height="1px" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'"><br /> &nbsp;
            </td>
        </tr>

        <tr><td align="justify">Su Deslinde Catastral estará disponible en cuanto se realicé el trabajo de campo, una vez que esté listo se le hará llegar al correo y teléfono que proporciono.<br>

        Tiempo de espera ajeno a la plataforma, espere una respuesta aproximadamente en un lapso de 07 a 14 días hábiles.<br>

        El tiempo depende de la demanda del servicio. </span>

        </td>
        </tr>
         <tr>
            <td colspan="6">
                <br /><img width="787px" height="1px" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'"><br /> &nbsp;
            </td>
        </tr>

    </table>

    <style>

    </style>

</body>
</html>';

include_once( app_path() . '/Libs/Wkhtmltopdf.php' );
try {
    $nombre = uniqid() . "_Factura" . $cliente;
    #$wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'page_width'=>120,'page_height'=>200,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
    $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'margins' => ['top' => 10, 'left' => 10,'right'=>10,'bottom'=>10]));
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
    /**/
}


//Nuevo metodo obtener folio cotizacion para pago de ISAI en línea
    public static function obtenerFolioCotizacion(Request $request){
        $padron=$request->Padron;
        $tipo=$request->Tipo;
        $cliente=$request->Cliente;
        Funciones::selecionarBase($cliente);
        $FolioCotizacion=Funciones::ObtenValor("SELECT FolioCotizaci_on from Cotizaci_on WHERE Tipo=11 AND Padr_on=".$padron);
        return response()->json([
            'success' => 1,
            'folioCotizacion' =>$FolioCotizacion
        ],200);
    }
//Termina obtener Folio Cotizacion

//Nuevo metodo para obtener estatus padron catastral pago de ISAI en línea
    public static function obtenerEstatusPadronCatastral(Request $request){
        $padron=$request->Padron;
        $cliente=$request->Cliente;
        Funciones::selecionarBase($cliente);
        $estatus=Funciones::ObtenValor("SELECT Estatus, CuentaHijo, Id from Padr_onCatastral WHERE id=".$padron);
        return response()->json([
            'success' => 1,
            'estatus' =>$estatus
        ],200);
    }
//Termina

//Nuevo metodo para obtener datos propietario pago de ISAI en línea
    public static function obtenerDatosPropietario(Request $request){
        $padron=$request->Padron;
        $cliente=$request->Cliente;

        Funciones::selecionarBase($cliente);

        $Campo=DB::SELECT("SELECT c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno, c.Tel_efonoCelular, c.CorreoElectr_onico, c.NombreComercial FROM Contribuyente c INNER JOIN Padr_onCatastral pc on c.id=pc.Contribuyente WHERE pc.id=".$padron);
        return response()->json([
            'success' => 1,
            'Campos' => $Campo
        ], 200);

    }
//Termina


public static function buscarCotizacionPorFolio(Request $request){
  $cliente=$request->Cliente;
  $valor=$request->Valor;
  Funciones::selecionarBase($cliente);
  $cotizacion= Funciones::obtenerCotizacionCopia($cliente,$valor);
  return response()->json([
    'success' => 1,
    'cotizacion' => $cotizacion
   ], 200);
}


public static function buscarContribuyente(Request $request){
    $cliente=$request->Cliente;
    $idPadronCatastral=$request->IdPadron;
    $opcion=$request->Opcion;
    $variableBusqueda=$request->VariableBusqueda;
    Funciones::selecionarBase($cliente);

  if($opcion==1){
    //busca por id de padron catastrat
    $contribuyente= Funciones::ObtenValor("select C.* from Contribuyente  C join Padr_onCatastral P ON C.id=P.Contribuyente where P.id=".$idPadronCatastral);
    return response()->json([
      'success' => 1,
      'contribuyente' => $contribuyente
     ], 200);

  }else if($opcion==2){
      //busca por rfc o curp
    $contribuyente= DB::select("select C.*,(
        SELECT
            CONCAT(
            IF
                (
                    C.NombreComercial IS NULL
                    OR C.NombreComercial = '',
                    CONCAT_WS( ' ', C.Nombres, C.ApellidoPaterno, C.ApellidoMaterno ),
                    C.NombreComercial
                )) ) AS Nombre from Contribuyente C join DatosFiscales DF on  C.DatosFiscales=DF.id where C.Rfc='".$variableBusqueda."' or C.Curp='".$variableBusqueda."' or DF.RFC='".$variableBusqueda."' limit 1");
    return response()->json([
      'success' => 1,
      'contribuyente' => $contribuyente
     ], 200);
  }
}

public static function buscarContribuyenteDF(Request $request){
    $cliente=$request->Cliente;
    $idPadronCatastral=$request->IdPadron;
    $opcion=$request->Opcion;
    $variableBusqueda=$request->VariableBusqueda;
    Funciones::selecionarBase($cliente);

    if($opcion==1){
    } else if($opcion==2){
        $contribuyente= DB::select("SELECT C.id, df.RFC, df.NombreORaz_onSocial,
   df.EntidadFederativa,
   df.Municipio,
   df.Localidad,
   df.Colonia,
   df.Calle,
   df.Pa_is,
   df.N_umeroInterior,
   df.N_umeroExterior,
   df.C_odigoPostal,
   df.Referencia,
   df.R_egimenFiscal, C.Estatus, C.Nombres, C.ApellidoPaterno, C.ApellidoMaterno, (
        SELECT
            CONCAT(
            IF
                (
                    C.NombreComercial IS NULL
                    OR C.NombreComercial = '',
                    CONCAT_WS( ' ', C.Nombres, C.ApellidoPaterno, C.ApellidoMaterno ),
                    C.NombreComercial
                )) ) AS Nombre FROM Contribuyente C 
        JOIN DatosFiscales df on  C.DatosFiscales=df.id where C.Rfc='".$variableBusqueda."' or C.Curp='".$variableBusqueda."' or df.RFC='".$variableBusqueda."' limit 1");
    return response()->json([
      'success' => 1,
      'contribuyente' => $contribuyente
     ], 200);
  }
}

    public static function buscarContribuyenteCopia(Request $request){
        $cliente=$request->Cliente;
        $idPadronCatastral=$request->IdPadron;
        $opcion=$request->Opcion;
        $variableBusqueda=$request->VariableBusqueda;
        Funciones::selecionarBase($cliente);

        if($opcion==1){
            //busca por id de padron catastrat
            $contribuyente= Funciones::ObtenValor("select C.* from Contribuyente  C join Padr_onCatastral P ON C.id=P.Contribuyente where P.id=".$idPadronCatastral);
            $response=[
                'success' => 1,
                'contribuyente' => $contribuyente
            ];

            $result = Funciones::respondWithToken($response);

            return $result;

        }else if($opcion==2){
            //busca por rfc o curp
            $contribuyente= DB::select("select C.*,(
        SELECT CONCAT(
            IF(
                C.NombreComercial IS NULL
                OR C.NombreComercial = '',
                CONCAT_WS( ' ', C.Nombres, C.ApellidoPaterno, C.ApellidoMaterno ),
                C.NombreComercial
                )) ) AS Nombre from Contribuyente C join DatosFiscales DF on  C.DatosFiscales=DF.id where C.Rfc='".$variableBusqueda."' or C.Curp='".$variableBusqueda."' or DF.RFC='".$variableBusqueda."' limit 1");
            $response=[
                'success' => 1,
                'contribuyente' => $contribuyente
            ];

            $result = Funciones::respondWithToken($response);

            return $result;
        }
    }

    public static function  modificarDatoPagoEnLinea(Request  $request){
        $cliente=$request->Cliente;
        $referencia=$request->Referencia;
        $ticket=$request->Ticket;

        Funciones::selecionarBase($cliente);
        $dato= DB::table('DatosPagosEnLinea')
            ->where('referencia', $referencia)
            ->update(["idTicket"=>$ticket]);

        return response()->json([
            'success' => 1,
            'cotizacion' => $dato
        ], 200);
    }

    public static function modificarCorreoContribuyente(Request $request){
        $cliente=$request->Cliente;
        $idContribuyente=$request->IdContribuyente;
        $correo=$request->Correo;
        Funciones::selecionarBase($cliente);
        $contribuyente= DB::table('Contribuyente')
        ->where('id', $idContribuyente)
        ->update(["CorreoElectr_onico"=>$correo]);

        return response()->json([
        'success' => 1,
        'cotizacion' => $contribuyente
        ], 200);
    }

    public static function obtenerPersonalidadJuridica(Request $request){
        $cliente=$request->Cliente;
        Funciones::selecionarBase($cliente);
        $personalidadJuridica=DB::select("select * from PersonalidadJur_idica");
        return response()->json([
        'success' => 1,
        'PersonalidadJuridica' => $personalidadJuridica
        ], 200);
    }


    public static function pruebaCaja(Request $request){
        $cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $cotizacioServicio=$request->CotizacioServicio;
        /*$cliente=$request->Cliente;
        $tipoServicio=$request->TipoServicio;
        $idPadron=$request->IdPadron;
        $idCotizaciones=$request->IdCotizaciones;*/
        Funciones::selecionarBase($cliente);
        
        /*return  "AguaController.phpHo";
        exit();*/
        $auxiliarCondicion ="";

        if($tipoServicio==9){//9 es agua


        $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
            FROM Cotizaci_on c
           WHERE c.Cliente=".$cliente." and SUBSTR(c.FolioCotizaci_on, 1, 4)<=".date('Y')." AND c.Padr_on=".$idPadron." ) x WHERE x.PorPagar!=0 order by x.id desc";

       $resultadoCotizaciones=DB::select($consultaCotizaciones);
        }else if($tipoServicio==3){//3 predial
          //  if($cliente==29)
           // $auxiliarCondicion=" AND c.FechaLimite IS NULL";

         $consultaCotizaciones= "SELECT x.id FROM (SELECT c.id,(select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar
         FROM Cotizaci_on c
        WHERE c.Cliente=".$cliente."  and c.Tipo=3 and SUBSTR(c.FolioCotizaci_on, 1, 4)<='".date("Y")."' AND c.Padr_on=".$idPadron.FuncionesCaja::verificarAdeudoPredial($idPadron,0,null,2020).$auxiliarCondicion." ) x WHERE x.PorPagar!=0 order by x.id desc";

        $resultadoCotizaciones=DB::select($consultaCotizaciones);
        } else  if($tipoServicio==4){//servicios predial

            $cotizacioServicio=$request->CotizacioServicio;
            $miArray = array("id"=>$cotizacioServicio);
            $miArray2 =array($miArray);
            $resultadoCotizaciones=$miArray2;

       }


        $url = 'https://suinpac.piacza.com.mx/PruebasMario.php';
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


        $result = file_get_contents($url, true, $context);


        return response()->json([
            'success' => '1',
            'total'=> $result,
            'ss'=>$resultadoCotizaciones
        ], 200);
    }


    public static function obtenerPagosReferenciadosRegistrados(Request $request){
        $cliente=$request->Cliente;

        Funciones::selecionarBase($cliente);

        $SQL="SELECT * FROM DatospagoReferenciados WHERE Estatus=0";#nota Generalizar la tabla DatospagoReferenciados
        $DatosPagoReferenciado = DB::select($SQL);

        if(isset($DatosPagoReferenciado) && sizeof($DatosPagoReferenciado)>0) {

            return response()->json([
                'success' => 1,
                'RegistrosReferenciado' => $DatosPagoReferenciado,
            ], 200);
        }else{
            return response()->json([
                'success' => 0,

            ], 200);
        }

    }
    public static function crearPagoReferenciadoSuinpac(Request $request){
        $idContribuyente=$request->idContribuyente;

    }


    public static function postSuinpacCajaListaAdeudoPredialZofemat(Request $request)
    {
        $cliente = $request->Cliente;
        $idPadron = $request->IdPadron;
        $tipoServicio = $request->TipoServicio;
        Funciones::selecionarBase($cliente);
        $SQL = "SELECT DISTINCT c.id  FROM Cotizaci_on c INNER JOIN ConceptoAdicionalesCotizaci_on cac ON ( c.id = cac.Cotizaci_on )  WHERE c.Tipo IN ( $tipoServicio)  AND c.Padr_on =$idPadron   AND cac.Estatus = 0  GROUP BY c.id  ORDER BY c.id DESC";
        $listadoCotizaciones = DB::select($SQL);
        if (Count($listadoCotizaciones) > 0) {
            $idCotizaciones = "";
            foreach ($listadoCotizaciones as $cotizacion) {
                $idCotizaciones .= "," . $cotizacion->id;
            } #obtiene todos los id de cotizaciones con adeudo
            $adeudoConceptos = PortalController::getAdeudoPredialZofemat($idCotizaciones, $cliente);
            return response()->json([ #no hay cotizaciones
                'success' => 1,
                'listaadeudoConceptos' => $adeudoConceptos
            ], 200);
        } else {
            return response()->json([ #no hay cotizaciones
                'success' => 0
            ], 200);
        }
        return response()->json([
            'success' => 0,
            'ListadoCotizaciones' => $listadoCotizaciones,
        ], 200);
    }
    static function  getAdeudoPredialZofemat($idCotizaciones, $cliente)
    {
        Funciones::selecionarBase($cliente);
        $r=substr($idCotizaciones, 1, strlen($idCotizaciones));
        $SQL = "SELECT (SELECT a.Descripci_on FROM AreasAdministrativas a  where a.id=ct.AreaAdministrativa) as AreasAdministrativa,
                                                                ct.id as Cotizaci_on,ct.Cliente, ct.Contribuyente as Contribu, c.id as idConceptoCotizacion, co.id as ConceptoCobro,
                                                                co.Cantidad as Cantidad, c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario,
                                                                (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional,co.Adicional AS idAdicional,
                                                                co.A_no, COALESCE(co.Mes, '01') as Mes, ct.Tipo, co.Padre, co.MomentoCotizaci_on,ct.Fecha AS FechaMomento,ct.Padr_on,ct.Tipo,
                                                                (SELECT  if(cont.Nombres is NOT NULL,CONCAT(cont.Nombres,' ',cont.ApellidoPaterno,' ',cont.ApellidoMaterno),cont.NombreComercial)  AS Contribuyente   FROM Contribuyente cont WHERE cont.id=ct.Contribuyente ) as ContribuyenteNombre,
                                                                '0' as DescuentoID,'0' as DescuentoCotizaci_on,'0' as SaldoTotalRestante,
                                                                ( SELECT id FROM Padr_onCatastralHistorial WHERE Padr_onCatastral = ct.Padr_on AND A_no = co.A_no AND Mes = co.Mes LIMIT 1 ) AS IdLectura
                                                                    FROM ConceptoAdicionalesCotizaci_on co
                                                                    INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
                                                                    INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )
                                                                    WHERE  co.Cotizaci_on in($r) and co.Estatus=0 and co.EstatusConvenioC=0 ORDER BY  ct.Tipo ASC, co.A_no DESC, COALESCE(co.Mes, 1) DESC , co.id ASC 	";
        $consulta =preg_replace("[\n|\t|\r|\n\r]", "", $SQL);
        $listadoAdeudos = DB::select($SQL);
        return $listadoAdeudos;
    }

    public static function postCajaVirtualCajeroDev(Request $request){
        $cliente = intval($request->Cliente);
        $idPadron = intval($request->IdPadron);
        $importe=$request->Importe;
        $referencia=$request->Referencia;
        $MetodoPago=$request->MetodoPago;
        $UsoCFDI=$request->UsoCFDI;
        $Usuario=$request->Usuario;
        $TipoTicket = 1;
        if(isset($request->Tipo))
            $TipoTicket = intval($request->Tipo);#1 - Normal, 2 - Anticipo
        error_log("Fecha: ". date("Y-m-d H:i:s") . " Inicia postCajaVirtualCajeroDev 'cliente' => $cliente,'idPadron' => $idPadron, 'importe' => $importe, 'referencia' => $referencia,'metodoPago' => $MetodoPago,'UsoCFDI' => $UsoCFDI,'Usuario' => $Usuario,'TipoTicket' => $TipoTicket \n" , 3, "/var/log/suinpac/TestLogPedro.log");
        #validacion de que se ingresan valores enteros
        if (!is_int($idPadron) || $idPadron=='' || !is_int($cliente) || $cliente=='') {
            return response()->json([
                'success' => '0',
                'error' => 'Datos Invalidos'
            ], 200);
        }
        Funciones::selecionarBase($cliente);

            if($TipoTicket == 1){
            /*
            ╔═╗╔═╗╔═╗╔═╗  ╔╗╔╔═╗╦═╗╔╦╗╔═╗╦  
            ╠═╝╠═╣║ ╦║ ║  ║║║║ ║╠╦╝║║║╠═╣║  
            ╩  ╩ ╩╚═╝╚═╝  ╝╚╝╚═╝╩╚═╩ ╩╩ ╩╩═╝
            */
            #Funciones::precode("Entra al metodo pago normal",1,1);
            $ruta = PortalController::CajaCajeroPagoNormal($cliente,$idPadron,$importe,$referencia,$MetodoPago,$UsoCFDI,$Usuario);
            return $ruta;
        }else{
            /*
            ╔═╗╔═╗╔═╗╔═╗  ╔═╗╔╗╔╔╦╗╦╔═╗╦╔═╗╔═╗╔╦╗╔═╗
            ╠═╝╠═╣║ ╦║ ║  ╠═╣║║║ ║ ║║  ║╠═╝╠═╣ ║║║ ║
            ╩  ╩ ╩╚═╝╚═╝  ╩ ╩╝╚╝ ╩ ╩╚═╝╩╩  ╩ ╩═╩╝╚═╝
            */
            #Funciones::precode("Entra al metodo pago anticipado",1,1);
            $ruta = PortalController::CajaCajeroPagoAnticipado($cliente,$idPadron,$importe,$referencia,$MetodoPago,$UsoCFDI,$Usuario);
            return $ruta;
        }
    }

    public static function CajaCajeroPagoAnticipado($cliente,$idPadron,$importe,$referencia,$MetodoPago,$UsoCFDI,$Usuario) {
        if($cliente==32){// es CAPAZ
                //se le agrega a la consulta and c.Tipo=".$tipoServicio." para que solamente filtre por tipo de agua potable y no agregue las cotizaciones de predial
                $consultaCotizaciones= "SELECT x.id
                FROM (
                    SELECT 
                        c.id,
                        COALESCE((
                            SELECT COUNT(id) 
                            FROM ConceptoAdicionalesCotizaci_on 
                            WHERE Cotizaci_on = c.id AND Estatus = 0
                        ), 0) AS PorPagar
                    FROM Cotizaci_on c
                    WHERE c.Cliente = ".$cliente
                        ." AND c.Tipo = 9 
                        AND SUBSTR(c.FolioCotizaci_on, 1, 4) <= '".date("Y")."' 
                        AND c.Padr_on =".$idPadron."
                ) x 
                WHERE x.PorPagar != 0 
                ORDER BY x.id DESC";
                $resultadoCotizaciones=DB::select($consultaCotizaciones);
            }else{
                return response()->json([
                    'success' => '0',
                    'res'=> 'Solo puedes consultar el cliente CAPAZ'
                ], 200);
            }
            
            $url = 'https://suinpac.com/Padr_onAguaPotable2PagoAnticipadoCajaCajero.php';
            $dataForPost = array(
                'Cliente'=> [
                    "Cliente"=>$cliente,
                    "Cotizaciones"=>$resultadoCotizaciones,
                    "Importe"=>$importe,
                    "MetodoPago"=>$MetodoPago,
                    "idPadron"=>$idPadron,
                    "UsoCFDI"=>$UsoCFDI,
                    "Usuario"=>$Usuario,
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
            #Funciones::precode($result,1,1);

            error_log("Fecha: ". date("Y-m-d H:i:s") . " Termina postCajaVirtualCajeroDev $result \n" , 3, "/var/log/suinpac/TestLogPedro.log");
            $result = Funciones::respondWithToken($result);
            return $result;
    }

    public static function CajaCajeroPagoNormal($cliente,$idPadron,$importe,$referencia,$MetodoPago,$UsoCFDI,$Usuario){
        
        if($cliente==32){// es CAPAZ
                //se le agrega a la consulta and c.Tipo=".$tipoServicio." para que solamente filtre por tipo de agua potable y no agregue las cotizaciones de predial
                $consultaCotizaciones= "SELECT x.id
                FROM (
                    SELECT 
                        c.id,
                        COALESCE((
                            SELECT COUNT(id) 
                            FROM ConceptoAdicionalesCotizaci_on 
                            WHERE Cotizaci_on = c.id AND Estatus = 0
                        ), 0) AS PorPagar
                    FROM Cotizaci_on c
                    WHERE c.Cliente = ".$cliente
                        ." AND c.Tipo = 9 
                        AND SUBSTR(c.FolioCotizaci_on, 1, 4) <= '".date("Y")."' 
                        AND c.Padr_on =".$idPadron."
                ) x 
                WHERE x.PorPagar != 0 
                ORDER BY x.id DESC";
                $resultadoCotizaciones=DB::select($consultaCotizaciones);
            }else{
                return response()->json([
                    'success' => '0',
                    'res'=> 'Solo puedes consultar el cliente CAPAZ'
                ], 200);
            }
            
            $url = 'https://suinpac.com/PagoCajaVirtualPortalCajero.php';
            $dataForPost = array(
                'Cliente'=> [
                    "Cliente"=>$cliente,
                    "Cotizaciones"=>$resultadoCotizaciones,
                    "Importe"=>$importe,
                    "MetodoPago"=>$MetodoPago,
                    "idPadron"=>$idPadron,
                    "UsoCFDI"=>$UsoCFDI,
                    "Usuario"=>$Usuario,
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
            error_log("Fecha: ". date("Y-m-d H:i:s") . " Termina postCajaVirtualCajeroDev $result \n" , 3, "/var/log/suinpac/TestLogPedro.log");
            $result = Funciones::respondWithToken($result);
            return $result;
    }
    
    public static function getPagoTicketDev(Request $request){
        $idCliente = intval($request->Cliente);
        $idTicket = intval($request->IdTiket);
        if(isset($request->IdTicket) && $request->IdTicket!="")
            $idTicket = intval($request->IdTicket);
        $TipoTicket = 1;
        if(isset($request->Tipo))
            $TipoTicket = intval($request->Tipo);#1 - Normal, 2 - Anticipo

        error_log("Fecha: ". date("Y-m-d H:i:s") . " Inicia getPagoTicketDev 'idCliente' => $idCliente, 'idTicket' => $idTicket, 'TipoTicket' => $TipoTicket \n" , 3, "/var/log/suinpac/TestLogPedro.log");
        #validacion de que se ingresan valores enteros
        if (!is_int($idTicket) || $idTicket=='' || !is_int($idCliente) || $idCliente=='') {
            return response()->json([
                'success' => '0',
                'error' => 'Datos Invalidos'
            ], 200);
        }
        Funciones::selecionarBase($idCliente);
        $Cliente = Funciones::ObtenValor("select id, Descripci_on, (select Ruta from CelaRepositorioC where CelaRepositorioC.idRepositorio=Cliente.Logotipo) as Logo from Cliente where id=".$idCliente);
        #Funciones::precode($Cliente,1,1);
        ////////////////////////////////////////////////////

        if($TipoTicket == 1){
            /*
            ╔═╗╔═╗╔═╗╔═╗  ╔╗╔╔═╗╦═╗╔╦╗╔═╗╦  
            ╠═╝╠═╣║ ╦║ ║  ║║║║ ║╠╦╝║║║╠═╣║  
            ╩  ╩ ╩╚═╝╚═╝  ╝╚╝╚═╝╩╚═╩ ╩╩ ╩╩═╝
            */
            
            $ruta = PortalController::GenerarPDFRecibo($Cliente,$idTicket);
            return $ruta;
        }else{
            /*
            ╔═╗╔═╗╔═╗╔═╗  ╔═╗╔╗╔╔╦╗╦╔═╗╦╔═╗╔═╗╔╦╗╔═╗
            ╠═╝╠═╣║ ╦║ ║  ╠═╣║║║ ║ ║║  ║╠═╝╠═╣ ║║║ ║
            ╩  ╩ ╩╚═╝╚═╝  ╩ ╩╝╚╝ ╩ ╩╚═╝╩╩  ╩ ╩═╩╝╚═╝
            */
            $ruta = PortalController::GenerarPDFReciboAnticipos($Cliente,$idTicket);
            return $ruta;
        }
    }

    
    public static function GenerarPDFReciboAnticipos($Cliente,$IdHAbono){
        $id = Funciones::ObtenValor("SELECT EncabezadoContabilidad FROM Padr_onAguaHistoricoAbono WHERE id ='".$IdHAbono."'","EncabezadoContabilidad");
        $CTotalPagar= "SELECT sum(d.Importe) as importe FROM  DetalleContabilidad d 
                INNER JOIN  EncabezadoContabilidad e ON ( d.EncabezadoContabilidad = e.id  )
                INNER JOIN PlanCuentas p ON (d.PlanDeCuentas = p.id)
                WHERE  d.EncabezadoContabilidad = ".$id." and d.TipoDeMovimientoContable=1 AND
                (p.Clave2  LIKE '1%' OR p.Clave2  LIKE '2%' OR p.Clave2  LIKE '3%' OR p.Clave2  LIKE '4%' )";
            $TotalPagar=Funciones::ObtenValor($CTotalPagar,"importe");
            $DatosEncabezado=Funciones::ObtenValor("select *, date(FechaP_oliza) as FechaP_oliza2  from EncabezadoContabilidad where id = ".$id);
            $DatosPago=Funciones::ObtenValor("SELECT pa.id as idPadr_onAguaPotable,p.SaldoNuevo,p.SaldoAnterior, p.FechaCorte as Fecha, c.NombreCompleto,c.idUsuario, 
                d.RFC, d.NombreORaz_onSocial, d.EntidadFederativa, d.Localidad, d.Municipio, d.Colonia, d.Calle, 
                d.N_umeroExterior, d.C_odigoPostal, c1.id as Contribuyente
            FROM Padr_onAguaHistoricoAbono p 
                INNER JOIN CelaUsuario c ON ( p.Usuario = c.idUsuario  )  
                INNER JOIN Padr_onAguaPotable pa ON(p.idPadron=pa.id)
                INNER JOIN Contribuyente c1 ON ( pa.Contribuyente = c1.id  )  
                INNER JOIN DatosFiscales d ON ( c1.DatosFiscales = d.id  )  
            WHERE p.EncabezadoContabilidad= ".$id);
            $CorteDeCaja=Funciones::ObtenValor("SELECT dm.idCorteCaja FROM EncabezadoContabilidad ec 
            INNER JOIN DetalleContabilidad dc ON (ec.id = dc.EncabezadoContabilidad) 
            INNER JOIN DetalleMovimientoCaja dm ON (dc.idMovimientoBancario = dm.id)
            WHERE ec.id=".$id,"idCorteCaja");
            $FechaHora = explode(" ", $DatosEncabezado->FechaTupla);
            $PadronAgua = Funciones::ObtenValor("SELECT *,(SELECT Concepto FROM TipoTomaAguaPotable  WHERE id=TipoToma) as TipoDeToma FROM Padr_onAguaPotable WHERE id=".$DatosPago->idPadr_onAguaPotable);
            $Observacionestxto= 'Datos de la Cuenta :'
                    . '<div class="col-xs-12">'
                    . '<div class="col-xs-2  text-left"><p class="texto text-left">Id</p></div>'
                    . '<div class="col-xs-2  text-left"><p class="texto text-left">'.$PadronAgua->id.'</p></div>'
                    . '<div class="col-xs-2  text-left"><p class="texto text-left">Contrato:</p></div>'
                    . '<div class="col-xs-2  text-left"><p class="texto text-left">'.$PadronAgua->ContratoVigente.'</p></div>'
                    . '<div class="col-xs-2 text-left"><p class="texto">Tipo de Toma:</p></div>'
                    . '<div class="col-xs-2 text-left"><p class="texto">'.$PadronAgua->TipoDeToma.'</p></div>'
                    . '<div class="col-xs-2 text-left"><p class="texto">Metodo de Cobro: </p></div>'
                    . '<div class="col-xs-2 text-left"><p class="texto">'.($PadronAgua->M_etodoCobro==1?'Fija':'Consumo')."</p></div>'"
                    . '<div class="col-xs-2 text-left"><p class="texto">Medidor:</p></div>'
                    . '<div class="col-xs-2 text-left"><p class="texto">'.$PadronAgua->Medidor.'</p></div>'
                    . '<div class="col-xs-2 text-left"><p class="texto">Domicilio:</p></div>'
                    . '<div class="col-xs-2 text-left"><p class="texto">'.$PadronAgua->Domicilio.'</p></div>'
                    . "</div>";
            $saldoNuevo = $PadronAgua->Saldo + $TotalPagar;
            
            $letras = utf8_decode(Funciones::num2letras($DatosPago->SaldoNuevo, 0, 0) . " pesos  ");
            $ultimo = substr(strrchr($saldoNuevo, "."), 1, 2); //recupero lo que este despues del decimal
            $ultimo = ($ultimo == 0) ? "00" : $ultimo;
            $letras = $letras . " " . $ultimo . "/100 M.N.";

            $miHTML = '
            <!DOCTYPE html>
            <html lang="es">
            <style>
                .centrado {
                    text-align: center;
                }
                .derecha {
                    text-align: right;
                }
                .letras {
                    font-family: "Arial", serif;
                    font-size: 6pt;
                }
                .numeros {
                    font-family: "Arial", serif;
                    font-size: 7pt;
                }
                td {
                    font-family: "Arial", serif;
                    font-size: 7pt;
                }
                th {
                    font-family: "Arial", serif;
                    font-size: 8pt;
                }
            </style>
            <body>
                <div id="ticket">
                    <table border="0">
                        <tr>
                            <td colspan="2" align="center"><img width="50px"  src='. asset($Cliente->Logo).'></td>
                        </tr>
                        <tr>
                        <th colspan="2">'.utf8_decode( ($Cliente->Descripci_on) ).'<br />
                            Comprobante de Anticipo Cajero<br />
                        </th></tr>
                        <tr>
                            <td colspan="2"><br /></td>
                        </tr>
                        <tr>
                            <td>Fecha: ' . $DatosEncabezado->FechaP_oliza2 . '&nbsp;&nbsp;Hora: ' . $FechaHora[1] . '</td>
                        </tr>


                        <tr>
                            <td>Corte: ' . $CorteDeCaja . '&nbsp;&nbsp;Cajero: ' . $DatosPago->idUsuario . '</td>
                        </tr>

                        <tr>
                            <td colspan="2"> Numero: ' . $DatosEncabezado->N_umeroP_oliza . ' </td>
                        </tr>

                        <tr>
                            <td colspan="2"> Contrato: ' . $PadronAgua->ContratoVigente . ' </td>
                        </tr>
                        <tr>
                            <td colspan="2">Contribuyente: ' . $DatosPago->NombreORaz_onSocial . '</td>
                        </tr>
                        <tr>
                            <td colspan="2">RFC: ' . $DatosPago->RFC . '</td>
                        </tr>
                        <tr>
                            <td colspan="2"><br /></td>
                        </tr>
                        <tr>
                            <td> Saldo disponible anterior</td>
                            <td style="text-align: right;">$'.number_format($DatosPago->SaldoAnterior, 2).'</td>
                        </tr>
                        <tr>
                            <td><strong>Pago anticipado</strong></td>
                            <td style="text-align: right;"> <strong>$'.number_format(($TotalPagar), 2).'</strong></td>
                        </tr>
                        
                        
                        
                        <tr>
                            <td >Saldo disponible actual</td>
                            <td style="text-align: right;"> $'.number_format(($DatosPago->SaldoNuevo), 2).'</td>
                                
                        </tr>
                                    
                        <tr>
                                    
                            <td class="centrado" colspan="2"><br><br>(' .$letras . ')</td>
                                            
                        </tr>
                        <tr>
                            <td class="centrado" colspan="2">Gracias por su pago</td>
                        </tr>
                    </table>
                </div>
            </body>
            </html>';
            include_once( app_path() . '/Libs/Wkhtmltopdf.php' );
            try{
                $archivo="MovimientoCajaPagarAnticipado". uniqid();
                $wkhtmltopdf = new Wkhtmltopdf(array('orientation'=>'portrait','path' =>'repositorio/temporal/', 'lowquality'=>true,'page_width'=>68,'page_height'=>100,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
                $wkhtmltopdf->setHtml($miHTML);
                $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $archivo . ".pdf");
                $response=[
                    'success' => '1',
                    'ruta' => "repositorio/temporal/" . $archivo . ".pdf",
                    'rutaCompleta' => "https://suinpac.com/repositorio/temporal/" . $archivo . ".pdf",
                ];
                error_log("Fecha: ". date("Y-m-d H:i:s") . " Termina getPagoTicketDev 'success' => '1', 'idCliente' => $Cliente->id, 'idTicket' => $id, 'rutaCompleta' => 'https://suinpac.com/repositorio/temporal/$archivo.pdf' \n" , 3, "/var/log/suinpac/TestLogPedro.log");

                $result = Funciones::respondWithToken($response);
                return $result;

            } catch (Exception $e) {
                echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
            }
    }

    public static function GenerarPDFRecibo($Cliente, $idTicket){
        $datosTicket = Funciones::ObtenValor("SELECT * FROM PagoTicket WHERE id=$idTicket");
        $Post=json_decode($datosTicket->Variables,true);

        if(isset($Post['DescuentoCupon']) && $Post['DescuentoCupon']!="")
            $Post['DescuentoCupon']= $Post['DescuentoCupon']; 
        else
        $Post['DescuentoCupon']= 0;
        $textoContrato_Cuenta="Contrato";
        $datosExtra=json_decode($datosTicket->Variables, true);
            $letras = utf8_decode( Funciones::num2letras($datosTicket->Total,0,0) . " pesos  ");
            $ultimo = substr(strrchr ($datosTicket->Total, "."), 1, 2); 
            $ultimo = ( $ultimo==0 )?"00":$ultimo;
            $letras = $letras . " " . $ultimo . "/100 M. N.";
                    $arrPeriodo=array();
                    $Agua=false;
                    $Post['cpnp']=$Post['cpnp']??[];
                    
                    foreach($Post['cpnp']  as $concepto){
                        $conce= explode(',', $concepto);
                        if($conce[13]>0 && $conce[14]>0)
                        $arrPeriodo[]=$conce[13].".".str_pad( $conce[14], 2, "0", STR_PAD_LEFT);
                        if($conce[15]==9){
                                
                                $Agua=true;
                        }
                        if($conce[15]==3){
                                
                        $textoContrato_Cuenta="Cuenta";
                    }
                            
                    }
                    $arrPeriodo= array_unique($arrPeriodo);   
                    sort($arrPeriodo);
                    $arrPeriodo = array_values($arrPeriodo);
                    if(count($arrPeriodo)>0){
                    $fechaFinal = explode('.', $arrPeriodo[(count($arrPeriodo)-1)]."");
                    $fechainicial = explode('.',$arrPeriodo[0] ."");
                    } 
                    $Mes = Array("1" => "Enero", "2" => "Febrero", "3" => "Marzo", "4" => "Abril", "5" => "Mayo",
                    "6" => "Junio", "7" => "Julio", "8" => "Agosto", "9" => "Septiembre", "10" => "Octubre", "11" => "Noviembre", "12" => "Diciembre");
                    if($Agua && count($arrPeriodo)>0)
                        $auxiliarPeriodo="<br><b>De ".$Mes[intval($fechainicial[1])]." ".$fechainicial[0]." A ".$Mes[intval($fechaFinal[1])]." ".$fechaFinal[0]."</b>" ;
                    else
                        $auxiliarPeriodo="";
                    $ImporteGatos=(floatval(str_replace(",", "",$datosTicket->Multas))+floatval(str_replace(",", "",$datosTicket->GastosEjecuci_on))+floatval(str_replace(",", "",$datosTicket->GastosEmbargo)));
                    $GatosDeEjecion="";
                    if($ImporteGatos>0){
                        $GatosDeEjecion='<tr>    
                            <td>Gastos de Ejecuci&oacute;n:</td>
                            <td class="derecha numeros">$ '.number_format($ImporteGatos,2).'</td>
                        </tr>';
                    }
            $ticketHtml= '
        <!DOCTYPE html>
        <html lang="es">
        <style>
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
            font-size: 7pt;
        }

        td {
            font-family: "Arial", serif;
            font-size: 7pt;
        }

        th {
            font-family: "Arial", serif;
            font-size: 8pt;
        }

        .negritas{
            font-family: "Arial", serif;
            font-size: 8pt;
            font-weight: bold;
        }

        .total{
            font-family: "Arial", serif;
            font-size: 10pt;
            font-weight: bold;
        }
        </style>
        <body >
            
        <div id="ticket">   
        <table border="0">
            <tr>
                <td width="70%">&nbsp;</td>
                <td width="30%">&nbsp;</td>
            </tr> 
            <tr>
                        <td colspan="2" align="center"><img width="50px"  src="'.asset($Cliente->Logo).'" alt=""></td>
                </tr>
            <tr>
                <th colspan="2">'.utf8_decode( ($Cliente->Descripci_on) ).'<br />
                    Comprobante de Anticipo Caja<br />
                </th>
            </tr>
            <tr>
                <td colspan="2"><br/></td>
            </tr>
            <tr>
                <td>Fecha: '. (date( 'Y-m-d', strtotime($datosTicket->Fecha))).'&nbsp;&nbsp;Hora: '. (date('H:i:s', strtotime($datosTicket->Fecha))).'</td>
            </tr>
            <tr>
                <td colspan="2">No. Operaci&oacute;n: '.  str_pad($datosTicket->NumOperacion, 5, "0", STR_PAD_LEFT).'</td>
            </tr> 

            <tr>
                <td>Caja: '. str_pad( $datosTicket->Caja, 5, "0", STR_PAD_LEFT)  .'&nbsp;&nbsp;Cajero: '. str_pad( $datosTicket->Cajero, 5, "0", STR_PAD_LEFT) .'</td>
            </tr>

                <tr>
                <td colspan="2">Recibo: '. $datosTicket->id .'</td>                
            </tr>

            <tr>
                <td colspan="2"> '.$textoContrato_Cuenta.': '. ( isset($datosExtra['ContratoVigente']) && $datosExtra['ContratoVigente']!=""?str_pad($datosExtra['ContratoVigente'], 9, "0", STR_PAD_LEFT):"Varios") .'</td>

            </tr>
            <tr>
                <td colspan="2">Nombre : '. utf8_decode(isset($datosExtra['Contribuyente']) && $datosExtra['Contribuyente']!=""?$datosExtra['Contribuyente']:'').'</td>
            </tr>
            <tr>
                <td colspan="2">RFC: '. (isset($datosExtra['RFC'] ) && $datosExtra['RFC'] !=""?$datosExtra['RFC'] :'').'</td>
            </tr>
            <tr>
                <td colspan="2">'.$auxiliarPeriodo.'</td>
            </tr>
            <tr>
                <td colspan="2"><br/></td>
            </tr>
            
            <tr>
                <td>Consumos:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->Conceptos,2).'</td>
            </tr>
            <tr>
                <td>Adicionales:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->Adicionales,2).'</td>
            </tr>
            <tr>
                <td>IVA:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->IVA,2).'</td>
            </tr>
            <tr>
                <td>Actualizaciones:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->Actualizaciones,2).'</td>
            </tr>
            <tr>
                <td>Recargos:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->Recargos,2).'</td>
            </tr>
            '.$GatosDeEjecion.'
            <tr>
                <td>Anticipo:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->Anticipo ,2).'</td>
            </tr>
            <tr>
                <td>Descuentos:</td>
                <td class="derecha numeros">$ -'.number_format((str_replace(",", "",$datosTicket->Descuentos)) ,2).'</td>
            </tr>
            <tr>
                <td>Total:</td>
                <td class="derecha numeros">$ '.number_format( $datosTicket->Total,2).'</td>
            </tr>
            

            <tr>
                <td colspan="2"><br/></td>
            </tr>

            <tr>
                    <td colspan="2" class="negritas centrado">IMPORTE PAGADO</td>
                </tr>
                <tr>
                    <td colspan="2" class="centrado total">$'. number_format($datosTicket->Total, 2).'</td>
                </tr>
                <tr>
                    <td colspan="2" class="centrado letras">('. strtoupper( $letras ).')</td>
                </tr>
                <tr>
                    <td colspan="2" class="centrado"><br />GRACIAS POR SU PAGO</td>
                </tr>

        </table>

        </div>
        </body>
        </html>';
                    
                //      precode($ticketHtml,1,1);
        #                print $ticketHtml; exit();
        
        include_once( app_path() . '/Libs/Wkhtmltopdf.php' );
        try{
            $archivo="PagoTicket".$datosTicket->Cliente. uniqid();
            $wkhtmltopdf = new Wkhtmltopdf(array('orientation'=>'portrait','path' =>'repositorio/temporal/', 'lowquality'=>true,'page_width'=>68,'page_height'=>140,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
            $wkhtmltopdf->setHtml($ticketHtml);
            $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $archivo . ".pdf");
            $response=[
                'success' => '1',
                'ruta' => "repositorio/temporal/" . $archivo . ".pdf",
                'rutaCompleta' => "https://suinpac.com/repositorio/temporal/" . $archivo . ".pdf",
            ];
            error_log("Fecha: ". date("Y-m-d H:i:s") . " Termina getPagoTicketDev 'success' => '1', 'idCliente' => $Cliente->id, 'idTicket' => $idTicket, 'rutaCompleta' => 'https://suinpac.com/repositorio/temporal/$archivo.pdf' \n" , 3, "/var/log/suinpac/TestLogPedro.log");

            $result = Funciones::respondWithToken($response);
            return $result;

        } catch (Exception $e) {
            echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
        }
    }

    public static function getPagoTicket(Request $request){
        $idCliente = intval($request->Cliente);
        $idTicket = intval($request->IdTiket);
        if(isset($request->IdTicket) && $request->IdTicket!="")
        $idTicket = intval($request->IdTicket);
        error_log("Fecha: ". date("Y-m-d H:i:s") . " Inicia getPagoTicket 'idCliente' => $idCliente, 'idTicket' => $idTicket \n" , 3, "/var/log/suinpac/LogCajero.log");
        #validacion de que se ingresan valores enteros
        if (!is_int($idTicket) || $idTicket=='' || !is_int($idCliente) || $idCliente=='') {
            return response()->json([
                'success' => '0',
                'error' => 'Datos Invalidos'
            ], 200);
        }
        Funciones::selecionarBase($idCliente);
        ////////////////////////////////////////////////////

        $datosTicket = Funciones::ObtenValor("SELECT * FROM PagoTicket WHERE id=$idTicket");
        $Cliente = Funciones::ObtenValor("select Descripci_on, (select Ruta from CelaRepositorioC where CelaRepositorioC.idRepositorio=Cliente.Logotipo) as Logo from Cliente where id=".$datosTicket->Cliente);
        #Funciones::precode($Cliente,1,1);
        $_POST=json_decode($datosTicket->Variables,true);
        if(isset($_POST['DescuentoCupon']) && $_POST['DescuentoCupon']!="")
            $_POST['DescuentoCupon']= $_POST['DescuentoCupon']; 
        else
        $_POST['DescuentoCupon']= 0;
        $textoContrato_Cuenta="Contrato";
        $datosExtra=json_decode($datosTicket->Variables, true);
            $letras = utf8_decode( Funciones::num2letras($datosTicket->Total,0,0) . " pesos  ");
            $ultimo = substr(strrchr ($datosTicket->Total, "."), 1, 2); 
            $ultimo = ( $ultimo==0 )?"00":$ultimo;
            $letras = $letras . " " . $ultimo . "/100 M. N.";
                    $arrPeriodo=array();
                    $Agua=false;
                    $_POST['cpnp']=$_POST['cpnp']??[];
                    
                    foreach($_POST['cpnp']  as $concepto){
                        $conce= explode(',', $concepto);
                        if($conce[13]>0 && $conce[14]>0)
                        $arrPeriodo[]=$conce[13].".".str_pad( $conce[14], 2, "0", STR_PAD_LEFT);
                        if($conce[15]==9){
                                
                                $Agua=true;
                        }
                        if($conce[15]==3){
                                
                        $textoContrato_Cuenta="Cuenta";
                    }
                            
                    }
                    $arrPeriodo= array_unique($arrPeriodo);   
                    sort($arrPeriodo);
                    $arrPeriodo = array_values($arrPeriodo);
                    if(count($arrPeriodo)>0){
                    $fechaFinal = explode('.', $arrPeriodo[(count($arrPeriodo)-1)]."");
                    $fechainicial = explode('.',$arrPeriodo[0] ."");
                    } 
                    $Mes = Array("1" => "Enero", "2" => "Febrero", "3" => "Marzo", "4" => "Abril", "5" => "Mayo",
                    "6" => "Junio", "7" => "Julio", "8" => "Agosto", "9" => "Septiembre", "10" => "Octubre", "11" => "Noviembre", "12" => "Diciembre");
                    if($Agua && count($arrPeriodo)>0)
                        $auxiliarPeriodo="<br><b>De ".$Mes[intval($fechainicial[1])]." ".$fechainicial[0]." A ".$Mes[intval($fechaFinal[1])]." ".$fechaFinal[0]."</b>" ;
                    else
                        $auxiliarPeriodo="";
                    $ImporteGatos=(floatval(str_replace(",", "",$datosTicket->Multas))+floatval(str_replace(",", "",$datosTicket->GastosEjecuci_on))+floatval(str_replace(",", "",$datosTicket->GastosEmbargo)));
                    $GatosDeEjecion="";
                    if($ImporteGatos>0){
                        $GatosDeEjecion='<tr>    
                            <td>Gastos de Ejecuci&oacute;n:</td>
                            <td class="derecha numeros">$ '.number_format($ImporteGatos,2).'</td>
                        </tr>';
                    }
            $ticketHtml= '
        <!DOCTYPE html>
        <html lang="es">
        <style>
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
            font-size: 7pt;
        }

        td {
            font-family: "Arial", serif;
            font-size: 7pt;
        }

        th {
            font-family: "Arial", serif;
            font-size: 8pt;
        }

        .negritas{
            font-family: "Arial", serif;
            font-size: 8pt;
            font-weight: bold;
        }

        .total{
            font-family: "Arial", serif;
            font-size: 10pt;
            font-weight: bold;
        }
        </style>
        <body >
            
        <div id="ticket">   
        <table border="0">
            <tr>
                <td width="70%">&nbsp;</td>
                <td width="30%">&nbsp;</td>
            </tr> 
            <tr>
                        <td colspan="2" align="center"><img width="50px"  src="'.asset($Cliente->Logo).'" alt=""></td>
                </tr>
            <tr>
                <th colspan="2">'.utf8_decode( ($Cliente->Descripci_on) ).'<br />
                    Comprobante de Pago Electr&oacute;nico<br />
                </th>
            </tr>
            <tr>
                <td colspan="2"><br/></td>
            </tr>
            <tr>
                <td>Fecha: '. (date( 'Y-m-d', strtotime($datosTicket->Fecha))).'&nbsp;&nbsp;Hora: '. (date('H:i:s', strtotime($datosTicket->Fecha))).'</td>
            </tr>
            <tr>
                <td colspan="2">No. Operaci&oacute;n: '.  str_pad($datosTicket->NumOperacion, 5, "0", STR_PAD_LEFT).'</td>
            </tr> 

            <tr>
                <td>Caja: '. str_pad( $datosTicket->Caja, 5, "0", STR_PAD_LEFT)  .'&nbsp;&nbsp;Cajero: '. str_pad( $datosTicket->Cajero, 5, "0", STR_PAD_LEFT) .'</td>
            </tr>

                <tr>
                <td colspan="2">Recibo: '. $datosTicket->id .'</td>                
            </tr>

            <tr>
                <td colspan="2"> '.$textoContrato_Cuenta.': '. ( isset($datosExtra['ContratoVigente']) && $datosExtra['ContratoVigente']!=""?str_pad($datosExtra['ContratoVigente'], 9, "0", STR_PAD_LEFT):"Varios") .'</td>

            </tr>
            <tr>
                <td colspan="2">Nombre : '. utf8_decode(isset($datosExtra['Contribuyente']) && $datosExtra['Contribuyente']!=""?$datosExtra['Contribuyente']:'').'</td>
            </tr>
            <tr>
                <td colspan="2">RFC: '. (isset($datosExtra['RFC'] ) && $datosExtra['RFC'] !=""?$datosExtra['RFC'] :'').'</td>
            </tr>
            <tr>
                <td colspan="2">'.$auxiliarPeriodo.'</td>
            </tr>
            <tr>
                <td colspan="2"><br/></td>
            </tr>
            
            <tr>
                <td>Consumos:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->Conceptos,2).'</td>
            </tr>
            <tr>
                <td>Adicionales:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->Adicionales,2).'</td>
            </tr>
            <tr>
                <td>IVA:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->IVA,2).'</td>
            </tr>
            <tr>
                <td>Actualizaciones:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->Actualizaciones,2).'</td>
            </tr>
            <tr>
                <td>Recargos:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->Recargos,2).'</td>
            </tr>
            '.$GatosDeEjecion.'
            <tr>
                <td>Anticipo:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->Anticipo ,2).'</td>
            </tr>
            <tr>
                <td>Descuentos:</td>
                <td class="derecha numeros">$ -'.number_format((str_replace(",", "",$datosTicket->Descuentos)) ,2).'</td>
            </tr>
            <tr>
                <td>Total:</td>
                <td class="derecha numeros">$ '.number_format( $datosTicket->Total,2).'</td>
            </tr>
            

            <tr>
                <td colspan="2"><br/></td>
            </tr>

            <tr>
                    <td colspan="2" class="negritas centrado">IMPORTE PAGADO</td>
                </tr>
                <tr>
                    <td colspan="2" class="centrado total">$'. number_format($datosTicket->Total, 2).'</td>
                </tr>
                <tr>
                    <td colspan="2" class="centrado letras">('. strtoupper( $letras ).')</td>
                </tr>
                <tr>
                    <td colspan="2" class="centrado"><br />GRACIAS POR SU PAGO</td>
                </tr>

        </table>

        </div>
        </body>
        </html>';
                    
                //      precode($ticketHtml,1,1);
        #                print $ticketHtml; exit();
        
        include_once( app_path() . '/Libs/Wkhtmltopdf.php' );
        try{
            $archivo="PagoTicket".$datosTicket->Cliente. uniqid();
            $wkhtmltopdf = new Wkhtmltopdf(array('orientation'=>'portrait','path' =>'repositorio/temporal/', 'lowquality'=>true,'page_width'=>68,'page_height'=>140,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
            $wkhtmltopdf->setHtml($ticketHtml);
            $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $archivo . ".pdf");
            $response=[
                'success' => '1',
                'ruta' => "repositorio/temporal/" . $archivo . ".pdf",
                'rutaCompleta' => "https://suinpac.com/repositorio/temporal/" . $archivo . ".pdf",
            ];
            error_log("Fecha: ". date("Y-m-d H:i:s") . " Termina getPagoTicket 'success' => '1', 'idCliente' => $idCliente, 'idTicket' => $idTicket, 'rutaCompleta' => 'https://suinpac.com/repositorio/temporal/$archivo.pdf' \n" , 3, "/var/log/suinpac/LogCajero.log");

            $result = Funciones::respondWithToken($response);
            return $result;

        } catch (Exception $e) {
            echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
        }
    }

    public static function getPagoTicketCopia(Request $request){
        $idCliente = intval($request->Cliente);
        $idTicket = intval($request->IdTiket);
        if(isset($request->IdTicket) && $request->IdTicket!="")
        $idTicket = intval($request->IdTicket);
        Funciones::selecionarBase($idCliente);
        ////////////////////////////////////////////////////

        $datosTicket = Funciones::ObtenValor("SELECT * FROM PagoTicket WHERE id=$idTicket");
        $Cliente = Funciones::ObtenValor("select Descripci_on, (select Ruta from CelaRepositorioC where CelaRepositorioC.idRepositorio=Cliente.Logotipo) as Logo from Cliente where id=".$datosTicket->Cliente);
        #Funciones::precode($Cliente,1,1);
        $_POST=json_decode($datosTicket->Variables,true);
        if(isset($_POST['DescuentoCupon']) && $_POST['DescuentoCupon']!="")
            $_POST['DescuentoCupon']= $_POST['DescuentoCupon']; 
        else
        $_POST['DescuentoCupon']= 0;
        $textoContrato_Cuenta="Contrato";
        $datosExtra=json_decode($datosTicket->Variables, true);
            $letras = utf8_decode( Funciones::num2letras($datosTicket->Total,0,0) . " pesos  ");
            $ultimo = substr(strrchr ($datosTicket->Total, "."), 1, 2); 
            $ultimo = ( $ultimo==0 )?"00":$ultimo;
            $letras = $letras . " " . $ultimo . "/100 M. N.";
                    $arrPeriodo=array();
                    $Agua=false;
                    $_POST['cpnp']=$_POST['cpnp']??[];
                    
                    foreach($_POST['cpnp']  as $concepto){
                        $conce= explode(',', $concepto);
                        if($conce[13]>0 && $conce[14]>0)
                        $arrPeriodo[]=$conce[13].".".str_pad( $conce[14], 2, "0", STR_PAD_LEFT);
                        if($conce[15]==9){
                                
                                $Agua=true;
                        }
                        if($conce[15]==3){
                                
                        $textoContrato_Cuenta="Cuenta";
                    }
                            
                    }
                    $arrPeriodo= array_unique($arrPeriodo);   
                    sort($arrPeriodo);
                    $arrPeriodo = array_values($arrPeriodo);
                    if(count($arrPeriodo)>0){
                    $fechaFinal = explode('.', $arrPeriodo[(count($arrPeriodo)-1)]."");
                    $fechainicial = explode('.',$arrPeriodo[0] ."");
                    } 
                    $Mes = Array("1" => "Enero", "2" => "Febrero", "3" => "Marzo", "4" => "Abril", "5" => "Mayo",
                    "6" => "Junio", "7" => "Julio", "8" => "Agosto", "9" => "Septiembre", "10" => "Octubre", "11" => "Noviembre", "12" => "Diciembre");
                    if($Agua && count($arrPeriodo)>0)
                        $auxiliarPeriodo="<br><b>De ".$Mes[intval($fechainicial[1])]." ".$fechainicial[0]." A ".$Mes[intval($fechaFinal[1])]." ".$fechaFinal[0]."</b>" ;
                    else
                        $auxiliarPeriodo="";
                    $ImporteGatos=(floatval(str_replace(",", "",$datosTicket->Multas))+floatval(str_replace(",", "",$datosTicket->GastosEjecuci_on))+floatval(str_replace(",", "",$datosTicket->GastosEmbargo)));
                    $GatosDeEjecion="";
                    if($ImporteGatos>0){
                        $GatosDeEjecion='<tr>    
                            <td>Gastos de Ejecuci&oacute;n:</td>
                            <td class="derecha numeros">$ '.number_format($ImporteGatos,2).'</td>
                        </tr>';
                    }
            $ticketHtml= '
        <!DOCTYPE html>
        <html lang="es">
        <style>
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
            font-size: 7pt;
        }

        td {
            font-family: "Arial", serif;
            font-size: 7pt;
        }

        th {
            font-family: "Arial", serif;
            font-size: 8pt;
        }

        .negritas{
            font-family: "Arial", serif;
            font-size: 8pt;
            font-weight: bold;
        }

        .total{
            font-family: "Arial", serif;
            font-size: 10pt;
            font-weight: bold;
        }
        </style>
        <body >
            
        <div id="ticket">   
        <table border="0">
            <tr>
                <td width="70%">&nbsp;</td>
                <td width="30%">&nbsp;</td>
            </tr> 
            <tr>
                        <td colspan="2" align="center"><img width="50px"  src="'.asset($Cliente->Logo).'" alt=""></td>
                </tr>
            <tr>
                <th colspan="2">'.utf8_decode( ($Cliente->Descripci_on) ).'<br />
                    Comprobante de Pago Electr&oacute;nico<br />
                </th>
            </tr>
            <tr>
                <td colspan="2"><br/></td>
            </tr>
            <tr>
                <td>Fecha: '. (date( 'Y-m-d', strtotime($datosTicket->Fecha))).'&nbsp;&nbsp;Hora: '. (date('H:i:s', strtotime($datosTicket->Fecha))).'</td>
            </tr>
            <tr>
                <td colspan="2">No. Operaci&oacute;n: '.  str_pad($datosTicket->NumOperacion, 5, "0", STR_PAD_LEFT).'</td>
            </tr> 

            <tr>
                <td>Caja: '. str_pad( $datosTicket->Caja, 5, "0", STR_PAD_LEFT)  .'&nbsp;&nbsp;Cajero: '. str_pad( $datosTicket->Cajero, 5, "0", STR_PAD_LEFT) .'</td>
            </tr>

                <tr>
                <td colspan="2">Recibo: '. $datosTicket->id .'</td>                
            </tr>

            <tr>
                <td colspan="2"> '.$textoContrato_Cuenta.': '. ( isset($datosExtra['ContratoVigente']) && $datosExtra['ContratoVigente']!=""?str_pad($datosExtra['ContratoVigente'], 9, "0", STR_PAD_LEFT):"Varios") .'</td>

            </tr>
            <tr>
                <td colspan="2">Nombre : '. utf8_decode(isset($datosExtra['Contribuyente']) && $datosExtra['Contribuyente']!=""?$datosExtra['Contribuyente']:'').'</td>
            </tr>
            <tr>
                <td colspan="2">RFC: '. (isset($datosExtra['RFC'] ) && $datosExtra['RFC'] !=""?$datosExtra['RFC'] :'').'</td>
            </tr>
            <tr>
                <td colspan="2">'.$auxiliarPeriodo.'</td>
            </tr>
            <tr>
                <td colspan="2"><br/></td>
            </tr>
            
            <tr>
                <td>Consumos:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->Conceptos,2).'</td>
            </tr>
            <tr>
                <td>Adicionales:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->Adicionales,2).'</td>
            </tr>
            <tr>
                <td>IVA:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->IVA,2).'</td>
            </tr>
            <tr>
                <td>Actualizaciones:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->Actualizaciones,2).'</td>
            </tr>
            <tr>
                <td>Recargos:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->Recargos,2).'</td>
            </tr>
            '.$GatosDeEjecion.'
            <tr>
                <td>Anticipo:</td>
                <td class="derecha numeros">$ '.number_format($datosTicket->Anticipo ,2).'</td>
            </tr>
            <tr>
                <td>Descuentos:</td>
                <td class="derecha numeros">$ -'.number_format((str_replace(",", "",$datosTicket->Descuentos)) ,2).'</td>
            </tr>
            <tr>
                <td>Total:</td>
                <td class="derecha numeros">$ '.number_format( $datosTicket->Total,2).'</td>
            </tr>
            

            <tr>
                <td colspan="2"><br/></td>
            </tr>

            <tr>
                    <td colspan="2" class="negritas centrado">IMPORTE PAGADO</td>
                </tr>
                <tr>
                    <td colspan="2" class="centrado total">$'. number_format($datosTicket->Total, 2).'</td>
                </tr>
                <tr>
                    <td colspan="2" class="centrado letras">('. strtoupper( $letras ).')</td>
                </tr>
                <tr>
                    <td colspan="2" class="centrado"><br />GRACIAS POR SU PAGO</td>
                </tr>

        </table>

        </div>
        </body>
        </html>';
                    
                //      precode($ticketHtml,1,1);
        #                print $ticketHtml; exit();
        
        include_once( app_path() . '/Libs/Wkhtmltopdf.php' );
        try{
            $archivo="PagoTicket".$datosTicket->Cliente. uniqid();
            $wkhtmltopdf = new Wkhtmltopdf(array('orientation'=>'portrait','path' =>'repositorio/temporal/', 'lowquality'=>true,'page_width'=>68,'page_height'=>140,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
            $wkhtmltopdf->setHtml($ticketHtml);
            $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $archivo . ".pdf");
            $response=[
                'success' => '1',
                'ruta' => "repositorio/temporal/" . $archivo . ".pdf",
                'rutaCompleta' => "https://suinpac.com/repositorio/temporal/" . $archivo . ".pdf",
            ];
            $result = Funciones::respondWithToken($response);
            return $result;

        } catch (Exception $e) {
            echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
        }
    }











    
    public static function postCajeroListaAdeudoV2(Request $request){
        error_log("Fecha: ". date("Y-m-d H:i:s") . " Inicia postCajeroListaAdeudoV2 \t" , 3, "/var/log/suinpac/LogCajero.log");
        /*return response()->json([
            'success' => '0',
            'error' => 'SERVICIO EN MANTENIMIENTO'
        ], 200);*/
        # Declaración de variables
        $contrato = intval($request->Contrato);
        $cliente = intval($request->Cliente);
        $tipoServicio = 9;
        $response = [
            'success' => '1',
            'contrato'=> null,
            'total' => null,
            'ss' => null,
        ];
        #validacion de que se ingresan valores enteros
        if (!is_int($contrato) || $contrato=='' || !is_int($cliente) || $cliente=='') {
            return response()->json([
                'success' => '0',
                'error' => 'Datos Invalidos'
            ], 200);
        }
        
        #Conexion a la base de datos
        Funciones::selecionarBase($cliente);
        $contribuyente=Funciones::ObtenValor("SELECT c.id AS Contribuyente FROM Padr_onAguaPotable pa INNER JOIN Contribuyente c ON (pa.Contribuyente=c.id) WHERE pa.ContratoVigente=".$contrato." AND pa.Cliente=".$cliente);
        if (!isset($contribuyente->Contribuyente)){ //sino se encuentra la cuenta retorna estatus 0 #2021-08-05
            return response()->json([
                'success' => '0',
                'error' => 'No se encontro el contrato ingresado'
            ], 200);
        }
        $contratoDatos=Funciones::ObtenValor("SELECT pa.id, pa.Estatus, ea.Descripci_on AS EstatusTXT, tt.Concepto AS TipoToma, c.id as idContribuyente,
        CONCAT_WS(' ',pa.Domicilio,pa.Colonia,pa.SuperManzana,pa.Manzana,pa.Lote) AS Domicilio,
        IF(c.PersonalidadJur_idica=1,CONCAT_WS(' ',c.Nombres,c.ApellidoPaterno,c.ApellidoMaterno), c.NombreComercial) AS Nombre 
        FROM Padr_onAguaPotable pa 
        INNER JOIN Contribuyente c ON(pa.Contribuyente=c.id) 
        INNER JOIN EstatusAgua ea ON (ea.id=pa.Estatus)
        INNER JOIN TipoTomaAguaPotable tt ON (tt.id=pa.TipoToma)
        WHERE pa.ContratoVigente=".$contrato." and pa.Cliente=".$cliente);
        #se asigna el id de servicio
        $idPadron = $contratoDatos->id;

     if(intval($contratoDatos->Estatus)!=2 && $contratoDatos->Estatus!=1){
        return response()->json([
            'success' => '2',
            'contrato'=>$contratoDatos,
            'error'=>"El Contrato no esta activo, estatus ".$contratoDatos->EstatusTXT,
        ], 200);
     }else{
        error_log("Fecha: ". date("Y-m-d H:i:s") . " Termina Parte 1 postCajeroListaAdeudoV2 ".json_encode($contratoDatos, JSON_UNESCAPED_SLASHES)." \t" , 3, "/var/log/suinpac/LogCajero.log");
        $response['contrato']=$contratoDatos;

######################################################################################################################
  
        #se verifica que el contrato no contenga una cotizacion de Reconexion Pendiente
        $Reconexion = "SELECT c.id, cac.ConceptoAdicionales, c.Padr_on
        FROM Cotizaci_on c 
            INNER JOIN ConceptoAdicionalesCotizaci_on cac ON c.id = cac.Cotizaci_on
            INNER JOIN Padr_onAguaPotable pa ON pa.id = c.Padr_on 
        WHERE pa.Cliente = '32' 
          AND cac.ConceptoAdicionales IN (2843, 5784, 5783, 5782, 5561)
          AND cac.Estatus = 0
          AND c.Padr_on =" . $idPadron . "  GROUP BY pa.id";
        #Funciones::precode($Reconexion,1,1);
        $ReconexionExiste = DB::select($Reconexion);
        if (count($ReconexionExiste) > 0) {
            error_log("Fecha: ". date("Y-m-d H:i:s") . " Termina postCajeroListaAdeudo 'success' => '2', 'result' => 'Existe una Reconexión pendiente, favor de pagar directamente en caja' \n" , 3, "/var/log/suinpac/LogCajero.log");
            return response()->json([
                'success' => '2',
                'result' => 'Existe una Reconexión pendiente, favor de pagar directamente en caja'
            ],
                200
            );
        } else {
            #No tiene Reconexiones pendientes por pagar
            $consultaCotizaciones = "SELECT c.id
            FROM Cotizaci_on c
            LEFT JOIN (
                SELECT Cotizaci_on, COUNT(id) AS NoPagados
                FROM ConceptoAdicionalesCotizaci_on
                WHERE Estatus = 0
                GROUP BY Cotizaci_on
            ) cac ON c.id = cac.Cotizaci_on
            WHERE c.Cliente =" . $cliente . " AND c.Tipo IN (9) 
              AND SUBSTR(c.FolioCotizaci_on, 1, 4) <= " . date('Y') 
              . " AND c.Padr_on = " . $idPadron . " AND cac.NoPagados != 0
            ORDER BY c.id DESC";
            $resultadoCotizaciones = DB::select($consultaCotizaciones);
        }
        $url = 'https://suinpac.com/PagoCajaVirtualVerificacionCajero.php';
        $dataForPost = array(
            'Cliente' => [
                "Cliente2" => $cliente,
                "Cotizaciones" => $resultadoCotizaciones
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
        error_log("Fecha: ". date("Y-m-d H:i:s") . " Termina la segunda parte de postCajeroListaAdeudoV2 'success' => '1', 'total' => $result \t" , 3, "/var/log/suinpac/LogCajero.log");
        $response['total']=$result;
        $response['ss']=$resultadoCotizaciones;
     }
######################################################################################################################
        $conceptos="SELECT 
            GROUP_CONCAT(cac.id) AS Conceptos, 
            cac.A_no, 
            cac.Mes, 
            ROUND(SUM(cac.Importe), 2) AS Importe,
            pdl.id AS IdLectura
        FROM Cotizaci_on c
        INNER JOIN ConceptoAdicionalesCotizaci_on cac ON c.id = cac.Cotizaci_on
        LEFT JOIN Padr_onDeAguaLectura pdl ON pdl.Padr_onAgua = c.Padr_on AND pdl.A_no = cac.A_no AND pdl.Mes = cac.Mes
        WHERE c.Tipo IN (9) AND c.Padr_on = ".$idPadron ." AND cac.Estatus = 0 AND cac.EstatusConvenioC = 0
        GROUP BY cac.A_no, cac.Mes
        ORDER BY cac.A_no DESC, cac.Mes DESC";
        #$conceptos = preg_replace("/[\r\n|\n|\r]+/", " ", $conceptos);
        $conceptos=DB::select($conceptos);
        $convenio = Funciones::ObtenValor("SELECT COUNT(id) AS total FROM Padr_onConvenio WHERE idPadron = $idPadron AND Estatus = 1", "total");
        #$convenio = Funciones::precode($conceptos,1,1);
        error_log("Fecha: ". date("Y-m-d H:i:s") . " Termina postCajeroListaAdeudoV2 'conceptos' => ".json_encode($conceptos, JSON_UNESCAPED_SLASHES).", 'Convenio' => $convenio \n" , 3, "/var/log/suinpac/LogCajero.log");
        $response['conceptos']=$conceptos;
        $response['convenio']=$convenio;
######################################################################################################################
        return response()->json($response, 200);
    }
}