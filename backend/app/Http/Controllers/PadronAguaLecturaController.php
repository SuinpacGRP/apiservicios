<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Modelos\PadronAguaAnomalia;
use App\Modelos\PadronAguaLectura;
use App\Modelos\PadronAguaPotable;
use App\Modelos\ConsumosAgua;
use App\CelaAccesos;
use App\Funciones;

class PadronAguaLecturaController extends Controller
{
    /**
     * ! Se asigna el middleware  al costructor para validar los tokens
     */
    public function __construct()
    {
        #$this->middleware('jwt', ['except' => ['login']]);
    }

    public function padronLecturas($id)
    {
        $lecturas = PadronAguaLectura::where('Padr_onAgua', $id)
            ->orderBy('id',   'DESC')
            ->orderBy('A_no', 'DESC')
            ->orderBy('Mes',  'DESC')
            ->take(1)
            ->get();

        return response()->json([
            'result' => $lecturas,
        ]);

        $result = Funciones::respondWithToken($lecturas);

        return $result;
    }

    public function anomalias(){
        $anomalias = PadronAguaAnomalia::all();

        return response()->json([
            'result' => $anomalias,
        ]);
    }

    public function registrarLectura(Request $request)
    {
        $existe          = "NULL";
        $mes             = $request->mes;
        $anio            = $request->anio;
        $fecha           = $request->fecha;
        $consumo         = $request->consumo;
        $cliente         = $request->cliente;
        $usuario         = $request->usuario;
        $padronAgua      = $request->padronAgua;
        $observacion     = $request->observacion;
        $lecturaActual   = $request->lecturaActual;
        $lecturaAnterior = $request->lecturaAnterior;
        
        #return $request;
        $existe = PadronAguaLectura::where('Mes', $mes)
            ->where('A_no', $anio)
            ->where('Padr_onAgua', $padronAgua)
            ->value('id');
        
        if ($existe == "NULL" || $existe == "") {

            $TipoToma = PadronAguaPotable::where('id', $padronAgua)->value('TipoToma');
            $Estatus  = PadronAguaPotable::where('id', $padronAgua)->value('Estatus');
            $obtieneTarifa = $this->ObtenConsumo( $TipoToma, $consumo, $cliente, $anio );
            
            $padronLectura = new PadronAguaLectura;

            $padronLectura->Padr_onAgua     = $padronAgua;
            $padronLectura->LecturaAnterior = $lecturaAnterior;
            $padronLectura->LecturaActual   = $lecturaActual;
            $padronLectura->Consumo         = $consumo;
            $padronLectura->Mes             = $mes;
            $padronLectura->A_no            = $anio;
            $padronLectura->Tarifa          = $obtieneTarifa;
            $padronLectura->TipoToma        = $TipoToma;
            $padronLectura->EstadoToma      = $Estatus;
            $padronLectura->FechaLectura    = $fecha;

            if( isset($observacion) && $observacion != "")
                $padronLectura->Observaci_on = $observacion;
            
            #dd($padronLectura);
            $padronLectura->save();

            /*return response()->json([
                'result' => $padronLectura,
            ]);*/
            $IdRegistroPadr_onDeAguaLectura = $padronLectura->id;
            
            $celaAcceso = new CelaAccesos;
            
            $celaAcceso->FechaDeAcceso = date('Y-m-d H:i:s');
            $celaAcceso->idUsuario     = $usuario;
            $celaAcceso->Tabla         = 'Padr_onDeAguaLectura';
            $celaAcceso->idTabla       = $IdRegistroPadr_onDeAguaLectura;
            $celaAcceso->Acci_on       = 2;
            
            $celaAcceso->save();
            #$idDeAcceso = $celaAcceso->id;
            #dd($idDeAcceso);
            
            /*if ($idDeAcceso != '' ) {
                $IdRegistroPadr_onDeAguaLectura = $padronLectura->id;
            }*/
            return response()->json([
                'result' => 'Lectura Registrada Correctamente.',
            ]);

            $result = Funciones::respondWithToken( "Lectura Registrada Correctamente." );

            return $result;
        } else { #Existe
            return response()->json([
                'result' => 'La lectura ya esta registrada',
            ]);

            $result = Funciones::respondWithToken( "La Lectura Ya Esta Registrada." );
            return $result;
        }
    }

    public function ObtenConsumo($TipoToma, $Cantidad, $Cliente, $ejercicioFiscal)
    {
        $maximoValor = ConsumosAgua::select( DB::raw('MAX(mts3) as Maximo') )
            ->where('Cliente', $Cliente)
            ->where('EjercicioFiscal', $ejercicioFiscal)
            ->where('TipoTomaAguaPotable', $TipoToma)
            ->value('Maximo');

        if ($Cantidad > ($maximoValor - 1)) {
            $restante = $Cantidad - ($maximoValor - 1);
            $Cantidad = $maximoValor;

            $Importe100 = ConsumosAgua::where('Cliente', $Cliente)
                ->where('EjercicioFiscal', $ejercicioFiscal)
                ->where('TipoTomaAguaPotable', $TipoToma)
                ->where('mts3', intval($Cantidad))
                ->value('Importe');
                
            $ValorDeMasDe100 = $Importe100 * $restante;
                
            $cantidad100 = ConsumosAgua::select( DB::raw('sum(Importe) as Suma') )
                ->where('Cliente', $Cliente)
                ->where('EjercicioFiscal', $ejercicioFiscal)
                ->where('TipoTomaAguaPotable', $TipoToma)
                ->where('mts3', '>', 0)
                ->where('mts3', '<', intval($Cantidad) )
                ->value('Suma');

            $ImporteTotal = $cantidad100 + $ValorDeMasDe100;
        } else {
            #dd("Ejemplo...");
            if ($Cantidad != 0) {
                $ImporteTotal = ConsumosAgua::select( DB::raw('sum(Importe) as Suma') )
                    ->where('Cliente', $Cliente)
                    ->where('EjercicioFiscal', $ejercicioFiscal)
                    ->where('TipoTomaAguaPotable', $TipoToma) 
                    ->where('mts3', '>', 0)
                    ->where('mts3', '<=', intval($Cantidad) )
                    ->value('Suma');
                } else {
                    $ImporteTotal = ConsumosAgua::select( DB::raw('sum(Importe) as Suma') )
                    ->where('Cliente', $Cliente)
                    ->where('EjercicioFiscal', $ejercicioFiscal) 
                    ->where('TipoTomaAguaPotable', $TipoToma)
                    ->where('mts3', 0)
                    ->value('Suma');
            }
        }
        return $this->truncateFloat($ImporteTotal, 2);
    }

    public function truncateFloat($number, $digitos)
    {
        $raiz          = 10;
        $multiplicador = pow($raiz, $digitos);
        $resultado     = ( (int)($number * $multiplicador) ) / $multiplicador;

        return number_format($resultado, $digitos);
    }
}