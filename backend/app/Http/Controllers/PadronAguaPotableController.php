<?php

namespace App\Http\Controllers;

use App\Funciones;
use App\Modelos\PadronAguaPotable;
use Illuminate\Support\Facades\DB;

class PadronAguaPotableController extends Controller
{
    /**
     * ! Se asigna el middleware  al costructor para validar los tokens
     */
    public function __construct()
    {
        #$this->middleware('jwt', ['except' => ['login']]);
    }

    /**
     * ! Obtiene los padrones de agua mediante la busqueda del Cliente y del numero de ContratoVigente.
     * @param  int     $cliente  
     * @param  string  $query 
     * @return \Illuminate\Http\JsonResponse
     */
    public function padrones($cliente, $query)
    {
        $padrones = PadronAguaPotable::select( "id", "Sector", "ContratoVigente", "ContratoAnterior", "Cuenta", "Medidor", "Domicilio", "Ruta", "Giro", "Estatus", 
                DB::raw( "COALESCE ( Consumo, 0.00 ) AS Consumo"),
                DB::raw( "(SELECT Nombre FROM Municipio WHERE Municipio.id=Padr_onAguaPotable.Municipio ) as Municipio"),
                DB::raw( "(SELECT Nombre FROM Localidad WHERE Localidad.id = Padr_onAguaPotable.Localidad) AS Localidad"),
                DB::raw( "(SELECT Rfc FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) AS Rfc"),
                DB::raw( "(SELECT Concepto FROM TipoTomaAguaPotable WHERE TipoTomaAguaPotable.id = Padr_onAguaPotable.TipoToma ) AS TipoToma"),
                DB::raw( "(SELECT Descripci_on FROM M_etodoCobroAguaPotable WHERE M_etodoCobroAguaPotable.id = Padr_onAguaPotable.M_etodoCobro ) AS M_etodoCobro2"),
                DB::raw( "(SELECT COALESCE ( NombreComercial, NULL ) FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) AS NombreComercial"),
                DB::raw( "(SELECT RFC FROM DatosFiscales WHERE DatosFiscales.id = ( SELECT DatosFiscales FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) ) AS RFCDF"),
                DB::raw( "(SELECT COALESCE ( CONCAT_WS( ' ', ApellidoPaterno, ApellidoMaterno, Nombres ), NULL ) FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) AS Contribuyente"),
                DB::raw( "(SELECT COALESCE ( NombreORaz_onSocial, NULL ) FROM DatosFiscales WHERE DatosFiscales.id = ( SELECT DatosFiscales FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) ) AS DatosFiscales")
            )
            ->where('Cliente', $cliente)
            ->where('M_etodoCobro', 2)
            ->where('ContratoVigente', 'LIKE', ('%'. $query .'%') )
            ->take(5)
            ->get();
            //->orderBy('name', 'desc')
            #AND ( ContratoVigente LIKE '%" . $query . "%' OR Cuenta LIKE '%" . $query . "%' OR Medidor LIKE '%" . $query . "%' )  ORDER BY id DESC LIMIT 200";

        foreach ($padrones as $padron) {
            $padron->Domicilio     = utf8_decode( $padron->Domicilio );
            $padron->Municipio     = utf8_decode( $padron->Municipio );
            $padron->Contribuyente = utf8_decode( $padron->Contribuyente );
            $padron->DatosFiscales = utf8_decode( $padron->DatosFiscales );
        }

        return response()->json([
            'result' => $padrones,
        ]);

        $result = Funciones::respondWithToken($padrones);

        return $result;
    }
}