<?php

namespace App\Http\Controllers\PortalPago;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Funciones;
use App\FuncionesCaja;
use App\Libs\Wkhtmltopdf;
use Illuminate\Support\Facades\Storage;
use DateTime;

use Illuminate\Support\Facades\DB;

class PredialController extends Controller
{
    /**
     * ! Se asigna el middleware  al costructor
    */
    public function __construct()
    {
        $this->middleware( 'jwt', ['except' => ['getToken']] );

    }

    public function  validarCuentaPredial(Request $request){
        $cliente=$request->Cliente;
        $cuentaPredial=$request->CuentaPredial;
        $cuentaCatastral=$request->CuentaCatastral;
        if( $cliente==31){
            $condition="";
        }else if($cliente==50 || $cliente==68){
            //existen cuentas catastrales con guion este es una prueba para ver como funciona
            $condition=" and  TRIM(REPLACE(P.Cuenta,'-',''))=TRIM(REPLACE('".$cuentaCatastral."','-',''))";
        } else{
            $condition=" and  TRIM(REPLACE(P.Cuenta,'-',''))=".$cuentaCatastral;
        }
        Funciones::selecionarBase( $cliente);

        $Cuenta=DB::select("SELECT P.id,C.id AS IdContribuyente, C.Nombres, C.ApellidoMaterno, C.ApellidoPaterno, P.Bloquear,
                (SELECT Nombre FROM Situaci_onPredio WHERE Id=P.Bloquear) AS Situacion, P.Ubicaci_on,P.Colonia,
                CONCAT_WS(',',P.id, P.CuentaPadre) AS CuentaCorrectaValida
            FROM Padr_onCatastral P
                INNER JOIN Contribuyente C ON (C.id=P.Contribuyente)
            WHERE P.Estatus=1 ".$condition."  AND TRIM(P.CuentaAnterior)='".$cuentaPredial ."' AND P.Cliente=".$cliente) ;
        $consulta="SELECT P.id,C.id AS IdContribuyente, C.Nombres, C.ApellidoMaterno, C.ApellidoPaterno, P.Bloquear,
                (SELECT Nombre FROM Situaci_onPredio WHERE Id=P.Bloquear) AS Situacion, P.Ubicaci_on,P.Colonia,
                CONCAT_WS(',',P.id, P.CuentaPadre) AS CuentaCorrectaValida
            FROM Padr_onCatastral P
                INNER JOIN Contribuyente C ON (C.id=P.Contribuyente)
            WHERE P.Estatus=1 ".$condition."  AND TRIM(P.CuentaAnterior)='".$cuentaPredial ."' AND P.Cliente=".$cliente;

        if (!isset($Cuenta[0]->id)){ //sino se encuentra la cuenta retorna estatus 0 #2021-08-05
            return response()->json([
                'success' => '0',
                're' => $Cuenta
            ], 200);
        }
        $nombrePropietario=Funciones::ObtenValor("SELECT pc.id,
            COALESCE( (SELECT CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  
            CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial))  FROM `Contribuyente` c WHERE c.id=pc.Contribuyente),
            (SELECT CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial))  
            FROM `Contribuyente` c WHERE c.id=pc.Comprador) ) AS Propietario        
            FROM Padr_onCatastral pc WHERE pc.id=".$Cuenta[0]->id);

        /* return $Cuenta[0]->Nombres;
        $Cuenta = DB::table('Padr_onCatastral as P')
        ->select("P.Id","C.Nombres","C.ApellidoPaterno","C.ApellidoMaterno")
        ->join('Contribuyente as C', 'C.Id','=','P.Contribuyente')
        ->where('P.Cuenta', $cuentaCatastral)
        ->where('P.CuentaAnterior', $cuentaPredial)
        ->get();*/

       if(count($Cuenta)==1 && ($Cuenta[0]->Bloquear==0 || $Cuenta[0]->Bloquear==5 || $Cuenta[0]->Bloquear=="")){
           //se encontro la cuenta
            return response()->json([
                'success' => '1',
                'cuenta'=>$Cuenta,
                'nombrePropietario'=>$nombrePropietario
            ], 200);
       }if(count($Cuenta)==1 && ($Cuenta[0]->Bloquear!=0 || $Cuenta[0]->Bloquear!=5 || $Cuenta[0]->Bloquear!="")){
        //cuenta bloqueda
         return response()->json([
             'success' => '3',
             'cuenta'=>$Cuenta
         ], 200);
       }else if(count($Cuenta)==0){
           //no se encontro la cuenta
            return response()->json([
                'success' => '0',
                're'=>$Cuenta
            ],200);
       }else if(count($Cuenta)>1){
            //se encontraron cuentas repetidas
            return response()->json([
                'success' => '2',
                'cuenta' => $Cuenta
            ], 200);
       }
    }
    //Zofemat
    public function  validarCuentaPredialConISAI(Request $request){
        $cliente=$request->Cliente;
        $cuentaPredial=$request->CuentaPredial;
        $cuentaCatastral=$request->CuentaCatastral;
        if( $cliente==31)
        {
                $condition="";
        } else{
            $condition=" and  TRIM(P.Cuenta)=".$cuentaCatastral;

        }
        Funciones::selecionarBase( $cliente);

        $Cuenta=DB::select("SELECT P.id,C.id AS IdContribuyente, C.Nombres, C.ApellidoMaterno, C.ApellidoPaterno, P.Bloquear,
                (SELECT Nombre FROM Situaci_onPredio WHERE Id=P.Bloquear) AS Situacion, P.Ubicaci_on,P.Colonia,
                CONCAT_WS(',',P.id, P.CuentaPadre) AS CuentaCorrectaValida
            FROM Padr_onCatastral P
                INNER JOIN Contribuyente C ON (C.id=P.Contribuyente)
            WHERE P.Estatus=1 ".$condition."  AND TRIM(P.CuentaAnterior)='".$cuentaPredial ."' AND P.Cliente=".$cliente) ;

if (!isset($Cuenta[0]->id)){ //sino se encuentra la cuenta retorna estatus 0 #2021-08-05
    return response()->json([
        'success' => '0',
        're' => $Cuenta
    ], 200);
}
        $nombrePropietario=Funciones::ObtenValor("SELECT pc.id,
COALESCE( (SELECT CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  
CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial))  FROM `Contribuyente` c WHERE c.id=pc.Contribuyente),
(SELECT CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial))  
FROM `Contribuyente` c WHERE c.id=pc.Comprador) ) AS Propietario        
FROM Padr_onCatastral pc WHERE pc.id=".$Cuenta[0]->id);

 /* return $Cuenta[0]->Nombres;
       $Cuenta = DB::table('Padr_onCatastral as P')
       ->select("P.Id","C.Nombres","C.ApellidoPaterno","C.ApellidoMaterno")
       ->join('Contribuyente as C', 'C.Id','=','P.Contribuyente')
       ->where('P.Cuenta', $cuentaCatastral)
       ->where('P.CuentaAnterior', $cuentaPredial)
       ->get();*/

       if(count($Cuenta)==1 && ($Cuenta[0]->Bloquear==0 || $Cuenta[0]->Bloquear==5 || $Cuenta[0]->Bloquear=="")){
           //se encontro la cuenta
            return response()->json([
                'success' => '1',
                'cuenta'=>$Cuenta,
                'nombrePropietario'=>$nombrePropietario
            ], 200);
       }if(count($Cuenta)==1 && ($Cuenta[0]->Bloquear!=0 || $Cuenta[0]->Bloquear!=5 || $Cuenta[0]->Bloquear!="")){
        //cuenta bloqueda por ISAI es el dato que que esperamos
         return response()->json([
             'success' => '1',
             'cuenta'=>$Cuenta
         ], 200);
       }else if(count($Cuenta)==0){
           //no se encontro la cuenta
            return response()->json([
                'success' => '0',
                're'=>$Cuenta
            ],200);
       }else if(count($Cuenta)>1){
            //se encontraron cuentas repetidas
            return response()->json([
                'success' => '2',
                'cuenta' => $Cuenta
            ], 200);
       }
    }
    //Zofemat

    public function  validarCuentaZofemat(Request $request)
    {
        $cliente = $request->Cliente;
        $cuentaPredial = $request->CuentaPredial;
        $cuentaCatastral = $request->CuentaCatastral;
        if ($cliente == 31) {
            $condition = "";
        } else {
            $condition = " AND  REPLACE(P.Cuenta,' ','')=REPLACE('" . $cuentaCatastral . "',' ','')";
        }
        Funciones::selecionarBase($cliente);
        $Cuenta = DB::select("SELECT P.id,C.id AS IdContribuyente, C.Nombres, C.ApellidoMaterno, C.ApellidoPaterno, P.Bloquear,
        (SELECT Nombre FROM Situaci_onPredio WHERE Id=P.Bloquear) AS Situacion, P.Ubicaci_on,P.Colonia,
        CONCAT_WS(',',P.id, P.CuentaPadre) AS CuentaCorrectaValida
        FROM Padr_onCatastral P
        INNER JOIN Contribuyente C ON (C.id=P.Contribuyente)
        WHERE P.Estatus=1 AND P.TipoPredio=10 " . $condition . "  OR REPLACE(P.CuentaAnterior,' ','')= REPLACE('" . $cuentaCatastral . "',' ','') AND P.Cliente=" . $cliente);
        
        if (!isset($Cuenta[0]->id)){
            return response()->json([
                'success' => '0',
                're' => $Cuenta
            ], 200);
        }
        $cuenta_id=$Cuenta[0]->id;

        $nombrePropietario = Funciones::ObtenValor("SELECT pc.id,
        COALESCE( (SELECT CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  
        CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial))  FROM `Contribuyente` c WHERE c.id=pc.Contribuyente),
        (SELECT CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial))  
        FROM `Contribuyente` c WHERE c.id=pc.Comprador) ) AS Propietario        
        FROM Padr_onCatastral pc WHERE pc.id=$cuenta_id");



/*
if (count($Cuenta) == 0) {
    //no se encontro la cuenta
    return response()->json([
        'success' => '0',
        're' => $Cuenta
    ], 200);
}else{
//se encontro la cuenta
return response()->json([
    'success' => '1',
    'cuenta' => $Cuenta,
    'nombrePropietario' => $nombrePropietario
], 200);

}*/
if (count($Cuenta) == 1 && ($Cuenta[0]->Bloquear == 0 || $Cuenta[0]->Bloquear == 5 || $Cuenta[0]->Bloquear == "")) {
    //se encontro la cuenta
    return response()->json([
        'success' => '1',
        'cuenta' => $Cuenta,
        'nombrePropietario' => $nombrePropietario
    ], 200);
}
if (count($Cuenta) == 1 && ($Cuenta[0]->Bloquear != 0 || $Cuenta[0]->Bloquear != 5 || $Cuenta[0]->Bloquear != "")) {
    //cuenta bloqueda
    return response()->json([
            'success' => '3',
            'cuenta' => $Cuenta
        ], 200);
} else if (count($Cuenta) == 0) {
    //no se encontro la cuenta
    return response()->json([
        'success' => '0',
        're' => $Cuenta
    ], 200);
} else if (count($Cuenta) > 1) {
    //se encontraron cuentas repetidas
    return response()->json([
        'success' => '2',
        'cuenta' => $Cuenta
    ], 200);
}

        /* return $Cuenta[0]->Nombres;
   $Cuenta = DB::table('Padr_onCatastral as P')
   ->select("P.Id","C.Nombres","C.ApellidoPaterno","C.ApellidoMaterno")
   ->join('Contribuyente as C', 'C.Id','=','P.Contribuyente')
   ->where('P.Cuenta', $cuentaCatastral)
   ->where('P.CuentaAnterior', $cuentaPredial)
   ->get();*/

        /*if (count($Cuenta) == 1 && ($Cuenta[0]->Bloquear == 0 || $Cuenta[0]->Bloquear == 5 || $Cuenta[0]->Bloquear == "")) {
            //se encontro la cuenta
            return response()->json([
                'success' => '1',
                'cuenta' => $Cuenta,
                'nombrePropietario' => $nombrePropietario
            ], 200);
        }
        if (count($Cuenta) == 1 && ($Cuenta[0]->Bloquear != 0 || $Cuenta[0]->Bloquear != 5 || $Cuenta[0]->Bloquear != "")) {
            //cuenta bloqueda
            return response()->json([
                    'success' => '3',
                    'cuenta' => $Cuenta
                ], 200);
        } else if (count($Cuenta) == 0) {
            //no se encontro la cuenta
            return response()->json([
                'success' => '0',
                're' => $Cuenta
            ], 200);
        } else if (count($Cuenta) > 1) {
            //se encontraron cuentas repetidas
            return response()->json([
                'success' => '2',
                'cuenta' => $Cuenta
            ], 200);
        }*/
    }
//Fin Zofemat

    public function  validarCuentaPredialPruebas(Request $request){
        $cliente=$request->Cliente;
        $cuentaPredial=$request->CuentaPredial;
        $cuentaCatastral=$request->CuentaCatastral;
        if( $cliente==31)
        {
            $condition="";
        } else{
            $condition=" and  TRIM(P.Cuenta)=".$cuentaCatastral;

        }
        Funciones::selecionarBase( $cliente);

        $Cuenta=DB::select("SELECT P.id,C.id AS IdContribuyente, C.Nombres, C.ApellidoMaterno, C.ApellidoPaterno, P.Bloquear,
                (SELECT Nombre FROM Situaci_onPredio WHERE Id=P.Bloquear) AS Situacion, P.Ubicaci_on,P.Colonia,
                CONCAT_WS(',',P.id, P.CuentaPadre) AS CuentaCorrectaValida
            FROM Padr_onCatastral P
                INNER JOIN Contribuyente C ON (C.id=P.Contribuyente)
            WHERE P.Estatus=1 ".$condition."  AND TRIM(P.CuentaAnterior)='".$cuentaPredial ."' AND P.Cliente=".$cliente) ;

        $nombrePropietario=Funciones::ObtenValor("SELECT pc.id,
COALESCE( (SELECT CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  
CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial))  FROM `Contribuyente` c WHERE c.id=pc.Comprador),
(SELECT CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial))  
FROM `Contribuyente` c WHERE c.id=pc.Contribuyente) ) AS Propietario        
FROM Padr_onCatastral pc WHERE pc.id=".$Cuenta[0]->id);

        $consulta="SELECT pc.id,
COALESCE( (SELECT CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  
CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial))  FROM `Contribuyente` c WHERE c.id=pc.Comprador),
(SELECT CONCAT( IF(c.NombreComercial IS NULL OR c.NombreComercial='',  CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno), c.NombreComercial))  
FROM `Contribuyente` c WHERE c.id=pc.Contribuyente) ) AS Propietario        
FROM Padr_onCatastral pc WHERE pc.id=".$Cuenta[0]->id;

        /* return $Cuenta[0]->Nombres;
              $Cuenta = DB::table('Padr_onCatastral as P')
              ->select("P.Id","C.Nombres","C.ApellidoPaterno","C.ApellidoMaterno")
              ->join('Contribuyente as C', 'C.Id','=','P.Contribuyente')
              ->where('P.Cuenta', $cuentaCatastral)
              ->where('P.CuentaAnterior', $cuentaPredial)
              ->get();*/

        if(count($Cuenta)==1 && ($Cuenta[0]->Bloquear==0 || $Cuenta[0]->Bloquear==5 || $Cuenta[0]->Bloquear=="")){
            //se encontro la cuenta
            return response()->json([
                'success' => '1',
                'cuenta'=>$Cuenta,
                'nombrePropietario'=>$nombrePropietario,
                'consulta'=>$consulta,
            ], 200);
        }if(count($Cuenta)==1 && ($Cuenta[0]->Bloquear!=0 || $Cuenta[0]->Bloquear!=5 || $Cuenta[0]->Bloquear!="")){
            //cuenta bloqueda
            return response()->json([
                'success' => '3',
                'cuenta'=>$Cuenta
            ], 200);
        }else if(count($Cuenta)==0){
            //no se encontro la cuenta
            return response()->json([
                'success' => '0',
                're'=>$Cuenta
            ],200);
        }else if(count($Cuenta)>1){
            //se encontraron cuentas repetidas
            return response()->json([
                'success' => '2',
                'cuenta' => $Cuenta
            ], 200);
        }
    }


    public function  validarCuentaPredialAnterior(Request $request){
        $cliente=$request->Cliente;
        $cuentaPredial=$request->CuentaPredial;
        $cuentaCatastral=$request->CuentaCatastral;
        if( $cliente==31)
        {
            $condition="";
        } else{
            $condition=" and  TRIM(P.Cuenta)=".$cuentaCatastral;

        }
        Funciones::selecionarBase( $cliente);

        $Cuenta=DB::select("SELECT P.id,C.id AS IdContribuyente, C.Nombres, C.ApellidoMaterno, C.ApellidoPaterno, P.Bloquear,
                (SELECT Nombre FROM Situaci_onPredio WHERE Id=P.Bloquear) AS Situacion, P.Ubicaci_on,P.Colonia,
                CONCAT_WS(',',P.id, P.CuentaPadre) AS CuentaCorrectaValida
            FROM Padr_onCatastral P
                INNER JOIN Contribuyente C ON (C.id=P.Contribuyente)
            WHERE P.Estatus=1 ".$condition."  AND TRIM(P.CuentaAnterior)='".$cuentaPredial ."' AND P.Cliente=".$cliente) ;


        if( $cliente==31)
        {
            $condition="";
        } else{
            $condition=" and  TRIM(Cuenta)=".$cuentaCatastral;

        }

        $nombrePropietario=Funciones::ObtenValor("select if(c.PersonalidadJur_idica=1,IF( CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno)
IS NOT NULL AND CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno)!='',CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno),
CONCAT(d.NombreORaz_onSocial)),CONCAT(d.NombreORaz_onSocial))  as nombre
from  Contribuyente c  INNER JOIN DatosFiscales d ON(d.id=c.DatosFiscales) where c.id=(SELECT Contribuyente from Padr_onCatastral WHERE Estatus=1"  .$condition."  AND TRIM(CuentaAnterior)='".$cuentaPredial ."' AND Cliente=".$cliente.")");
        /* return $Cuenta[0]->Nombres;
              $Cuenta = DB::table('Padr_onCatastral as P')
              ->select("P.Id","C.Nombres","C.ApellidoPaterno","C.ApellidoMaterno")
              ->join('Contribuyente as C', 'C.Id','=','P.Contribuyente')
              ->where('P.Cuenta', $cuentaCatastral)
              ->where('P.CuentaAnterior', $cuentaPredial)
              ->get();*/

        if(count($Cuenta)==1 && ($Cuenta[0]->Bloquear==0 || $Cuenta[0]->Bloquear==5 || $Cuenta[0]->Bloquear=="")){
            //se encontro la cuenta
            return response()->json([
                'success' => '1',
                'cuenta'=>$Cuenta,
                'nombrePropietario'=>$nombrePropietario
            ], 200);
        }if(count($Cuenta)==1 && ($Cuenta[0]->Bloquear!=0 || $Cuenta[0]->Bloquear!=5 || $Cuenta[0]->Bloquear!="")){
            //cuenta bloqueda
            return response()->json([
                'success' => '3',
                'cuenta'=>$Cuenta
            ], 200);
        }else if(count($Cuenta)==0){
            //no se encontro la cuenta
            return response()->json([
                'success' => '0',
                're'=>$Cuenta
            ],200);
        }else if(count($Cuenta)>1){
            //se encontraron cuentas repetidas
            return response()->json([
                'success' => '2',
                'cuenta' => $Cuenta
            ], 200);
        }
    }

    public function buscarCoopropietario(Request  $request){
        $cliente=$request->Cliente;
        $padron=$request->Padron;

        Funciones::selecionarBase( $cliente);
        $coopropietario=Funciones::ObtenValor("SELECT GROUP_CONCAT( ( SELECT CONCAT(IF (c.NombreComercial IS NULL OR c.NombreComercial='',CONCAT_WS(' ',c.Nombres,c.ApellidoPaterno,c.ApellidoMaterno),
    c.NombreComercial)) FROM `Contribuyente` c WHERE c.id=pcc.idContribuyente) SEPARATOR ',') AS CoPropietario FROM Padr_onCatastralCopropietarios pcc WHERE pcc.idPadron=".$padron);

        return response()->json([
            'success' => '1',
            'cooperopietarios' => $coopropietario
        ], 200);
    }

    public function  historialPredialAdeudo(Request $request){
        $cliente=$request->Cliente;
        $idPadron=$request->IdPadron;

     //"SELECT TipoPredio FROM Padr_onCatastral WHERE id="$cuentaPredial
        Funciones::selecionarBase( $cliente);
        $TipoPredio= DB::table('Padr_onCatastral')
        ->where('id',$idPadron)
        ->value('TipoPredio');
        $auxiliarCondicion="";
        $consultaHistorial = "SELECT id,A_no, Mes, TerrenoCosto, ConstruccionCosto, Consumo, `Status`,
        ( COALESCE(TerrenoCosto,0) + COALESCE(ConstruccionCosto,0) ) as ValorCatastral,
        IF( (CONVERT(GastosEjecucion, DECIMAL) ) > 0 OR (CONVERT(GastosEmbargo, DECIMAL) ) > 0 OR (CONVERT(Multas, DECIMAL) ) > 0 OR (CONVERT(OtrosGastos, DECIMAL) ) > 0 , 1, 0) as Condicion
    FROM Padr_onCatastralHistorial
    WHERE ";

    if($TipoPredio == 10){

        $consultaHistorial .= " Padr_onCatastral=" . $idPadron ." AND Status!=2 AND  Status!=3";
    }else{
        //$consultaHistorial .= " A_no =" . (date('Y')) . " AND Padr_onCatastral=" . $idPadron;
        $consultaHistorial .=  "  Padr_onCatastral=" . $idPadron. " AND Status!=2 AND Status!=3 ";

    }
    $consultaHistorial .= " ORDER BY A_no DESC, Mes DESC";

    $ejecutaHistorial = DB::select($consultaHistorial);
return $ejecutaHistorial;
    $htmlHistorial = '';

    foreach ( $ejecutaHistorial AS $RegistroHistorial) {

       $RegistroHistorial->Consumo= PredialController::obtieneDatosLecturaCatastralNuevoAdeudo($RegistroHistorial->id,$cliente,$RegistroHistorial->A_no,$RegistroHistorial->Mes);


        $Estado = 0;
        $Color = '';
        switch ( intval($RegistroHistorial->Status) ) {
            case 0:
                $Color = 'background-color: #f2dede;';
                $Estado = "No Cotizado";
                break;
            case 1:
                $Color = 'background-color: #fcf8e3;';
                $Estado = "Cotizado";
                break;
            case 2:
                $Color = 'background-color: #f0fff0;';
                $Estado = "Pagado";
                break;
            case 3:
                $Color = 'background-color: #acccbe;';
                $Estado = "Pagado";
                break;
            default:
                $Estado = "No Cotizado";
                break;
        }
        $RegistroHistorial->Status=$Estado;

    }
    return $ejecutaHistorial;
    }


    public function  historialPredial(Request $request){
        $cliente=$request->Cliente;
        $idPadron=$request->idPadron;

     //"SELECT TipoPredio FROM Padr_onCatastral WHERE id="$cuentaPredial
        Funciones::selecionarBase( $cliente);
        $TipoPredio= DB::table('Padr_onCatastral')
        ->where('id',$idPadron)
        ->value('TipoPredio');


        $consultaHistorial = "SELECT id,A_no, Mes, TerrenoCosto, ConstruccionCosto, Consumo, `Status`,
            ( COALESCE(TerrenoCosto,0) + COALESCE(ConstruccionCosto,0) ) as ValorCatastral,
            IF( (CONVERT(GastosEjecucion, DECIMAL) ) > 0 OR (CONVERT(GastosEmbargo, DECIMAL) ) > 0 OR (CONVERT(Multas, DECIMAL) ) > 0 OR (CONVERT(OtrosGastos, DECIMAL) ) > 0 , 1, 0) as Condicion
        FROM Padr_onCatastralHistorial
        WHERE ";


    if($TipoPredio == 10){

        $consultaHistorial .= " Padr_onCatastral=" . $idPadron;
    }else{
        //$consultaHistorial .= " A_no =" . (date('Y')) . " AND Padr_onCatastral=" . $idPadron;
        $consultaHistorial .= " A_no =" . (date('Y')) . " AND Padr_onCatastral=" . $idPadron;

    }
    $consultaHistorial .= " ORDER BY A_no DESC, Mes DESC";

    $ejecutaHistorial = DB::select( $consultaHistorial);
//return $ejecutaHistorial;
    $htmlHistorial = '';
    foreach ( $ejecutaHistorial AS $RegistroHistorial) {
        $Estado = 0;
        $Color = '';
        switch ( intval($RegistroHistorial->Status) ) {
            case 0:
                $Color = 'background-color: #f2dede;';
                $Estado = "No Cotizado";
                break;
            case 1:
                $Color = 'background-color: #fcf8e3;';
                $Estado = "Cotizado";
                break;
            case 2:
                $Color = 'background-color: #f0fff0;';
                $Estado = "Pagado";
                break;
            case 3:
                $Color = 'background-color: #acccbe;';
                $Estado = "Pagado";
                break;
            default:
                $Estado = "No Cotizado";
                break;
        }
        $RegistroHistorial->Status=$Estado;

    }
    return $ejecutaHistorial;
    }





public function  obtieneDatosLecturaCatastralNuevoAdeudo($Lectura,$cliente,$anio,$mes){

        //$configuracionGenerarRecargos = ObtenValorPorClave("GenerarRecargos", $_SESSION['CELA_Cliente' . $_SESSION['CELA_Aleatorio']]);
        //"SELECT Valor FROM ClienteDatos WHERE Cliente=".$Cliente." AND Indice='".$Clave."'"
        $configuracionGenerarRecargos = DB::table('ClienteDatos')
        ->where('Cliente', $cliente)
        ->where('Indice', "GenerarRecargos")
        ->value('Valor');

        if(!$configuracionGenerarRecargos==1)
            $configuracionGenerarRecargos = 0;
        //GenerarActualizaciones
        $configuracionGenerarActualizaciones= DB::table("ClienteDatos")
        ->where('Cliente', $cliente)
        ->where('Indice', "GenerarActualizaciones")
        ->value('Valor');

        if(!$configuracionGenerarActualizaciones==1)
            $configuracionGenerarActualizaciones = 0;

        $montoBase = 0;
       $ConsultaDatosLecturaActual="SELECT *, ccc.id as cccid, ca.Importe as ImporteUnitario,  pa.id as paid, pl.Consumo as ValorHistorial, pl.Status as EstatusPagado, pl.id as plid,  ca.BaseCalculo, pl.A_no, pl.Mes
            FROM Padr_onCatastral pa
            INNER JOIN Padr_onCatastralHistorial pl ON (pa.id=pl.Padr_onCatastral)
            INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=pa.ConceptoCobroCaja)
            INNER JOIN ConceptoRetencionesAdicionales cra ON ( cra.Concepto= ccc.id )
            INNER JOIN ConceptoRetencionesAdicionalesCliente crac ON ( cra.id = crac.ConceptoRetencionesAdicionales  AND crac.Cliente=pa.Cliente)
            INNER JOIN ConceptoAdicionales ca ON (ca.ConceptoRetencionesAdicionalesCliente=crac.id)
            WHERE
            pl.id=".$Lectura."
            ORDER BY pl.A_no DESC
            LIMIT 0, 1";

       $DatosLecturaActual =DB::select($ConsultaDatosLecturaActual);

       $decuentoInapam=DB::table("Padr_onCatastral")
       ->where("id",$DatosLecturaActual[0]->paid)
       ->value("INAPAM");

        $conditionDes=$decuentoInapam=='NULL' || $decuentoInapam==''? ' AND Descripci_on NOT LIKE "%INAPAM%" ':' AND Descripci_on LIKE "%INAPAM%" ';

        $lecturasCons="SELECT pal.id as palid,
        sum(pal.Consumo),
        pal.A_no,pal.Mes, pap.Cliente,
        pal.Status as EstatusPagado,
        sum(pap.ValorCatastral) as Tarifa,
        pap.SuperficieConstrucci_on,
        pap.SuperficieTerreno,
        pap.CuentaAnterior,
        pap.id as papid,
        pal.TerrenoCosto,
        pal.ConstruccionCosto,
        sum(pal.Consumo) as ValorHistorial,
        sum(pal.Multas) Multas,
        sum(pal.GastosEjecucion) GastosEjecucion,
        sum(pal.GastosEmbargo) GastosEmbargo,
        sum(pal.OtrosGastos) OtrosGastos,
        sum(pal.Consumo)*(
                SELECT COALESCE(Porcentaje,0) FROM ClienteDescuentos
                WHERE
                1=2 AND Ejercicio=pal.A_no AND
                Cliente=pap.Cliente AND
                CURDATE() BETWEEN FechaInicial AND FechaFinal ".$conditionDes." )/100 as Descuento
                FROM Padr_onCatastral pap
                INNER JOIN Padr_onCatastralHistorial pal ON (pap.id=pal.Padr_onCatastral)
                WHERE
                pap.id =".$DatosLecturaActual[0]->paid." AND
                pap.Cliente=".$cliente." AND
                pal.Status IN (0,1) AND pal.A_no>2013 AND CONCAT(pal.A_no,pal.Mes)=".$anio.$mes." GROUP BY pal.A_no,pal.Mes ORDER BY pal.A_no DESC, pal.Mes DESC";


                $resultados="";
        $sumapredial=0;
        $sumaAct=0;
        $sumaRec=0;
        $sumaMulta=0;
        $sumaGastosEjecucion=0;
        $sumaGastosEmbargo=0;
        $sumaOtrosGastos=0;
        $sumaDescuentos=0;
        $sumaTotalAnio=0;



        $Messumapredial=0;
        $MessumaAct=0;
        $MessumaRec=0;
        $MessumaMulta=0;
        $MessumaGastosEjecucion=0;
        $MessumaGastosEmbargo=0;
        $MessumaOtrosGastos=0;
        $MessumaDescuentos=0;
        $MessumaTotalAnio=0;
        $Descuentos=0;
        $anioActual=0;

          $FechasVencimiento = ['01-15','01-31',
                                '02-15','02-28',
                                '03-15','03-31',
                                '04-15','04-30',
                                '05-15','05-31',
                                '06-15','06-30',
                                '07-15','07-31',
                                '08-15','08-31',
                                '09-15','09-30',
                                '10-15','10-31',
                                '11-15','11-30',
                                '12-15','12-31'];



        $VencimientoEdoCuenta="";
        foreach ($FechasVencimiento as $valor){
            $Fecha="";
            $fechaVencimiento = date('Y-m-d', strtotime(date('Y-').$valor));

            if(date('D',strtotime($fechaVencimiento))=="Sat"){
                $fechaVencimiento = strtotime ( '-1 day' , strtotime ( $fechaVencimiento ) ) ;
                $fechaVencimiento = date ( 'Y-m-d' , $fechaVencimiento );
            }
            if(date('D',strtotime($fechaVencimiento))=="Sun"){
                $fechaVencimiento = strtotime ( '-2 day' , strtotime ( $fechaVencimiento ) ) ;
                $fechaVencimiento = date ( 'Y-m-d' , $fechaVencimiento );
            }

            if( strtotime($fechaVencimiento) >= strtotime(date("Y-m-d")) ){
               $VencimientoEdoCuenta = $fechaVencimiento;
               break;
            }
        }

        $ejecutaLect=DB::select($lecturasCons);
      // return $lecturasCons;
        foreach($ejecutaLect AS $lectr){

            if($anioActual!=$lectr->A_no && $anioActual!=0){
                $resultados.= "	<tr>
                            <td align='center'>".$anioActual."</td>
                            <td align='right'>".number_format($montoBase,2)."</td>
                            <td align='right'>".number_format($Messumapredial,2)."</td>
                            <td align='right'>".number_format($MessumaAct,2)."</td>
                            <td align='right'>".number_format($MessumaRec,2)."</td>

                            <td align='right'>".number_format($MessumaGastosEjecucion,2)."</td>
                            <td align='right'>".number_format($MessumaGastosEmbargo,2)."</td>

                            <td align='right'>".number_format($MessumaMulta,2)."</td>
                            <td align='right'>".number_format($MessumaOtrosGastos,2)."</td>
                            <td align='right'>".number_format($MessumaDescuentos,2)."</td>
                            <td align='right'>".number_format($MessumaTotalAnio,2)."</td>
                        </tr>";

                $Messumapredial=0;
                $MessumaAct=0;
                $MessumaRec=0;
                $MessumaMulta=0;
                $MessumaGastosEjecucion=0;
                $MessumaGastosEmbargo=0;
                $MessumaOtrosGastos=0;
                $MessumaDescuentos=0;
                $MessumaTotalAnio=0;
          }

    $montopredial = $lectr->ValorHistorial;

    $mes=($lectr->Mes*2)-1;
    $anio=$lectr->A_no;
    if(intval($mes)>12){
        $mes=1;
        $anio=$anio+1;
    }

   $mes=str_pad($mes, 2, "0", STR_PAD_LEFT);

    $dia=16;

    $fechaVencimiento= $anio."-".$mes."-".$dia;

    if(date('D',intval($fechaVencimiento))=="Sat"){
       $dia=$dia+2;
    }
    if(date('D',intval($fechaVencimiento))=="Sun"){
        $dia=$dia+1;
    }
    //aqui me quede mario
    $fechaVencimiento= $anio."-".$mes."-".$dia;
    //  echo "<br />FV:".$fechaVencimiento." H:i:00";

        $fecha_actual = strtotime(date("Y-m-d H:i:00",time()));
        $fecha_entrada = strtotime(date($fechaVencimiento." H:i:00"));

        if($fecha_actual > $fecha_entrada){

         $recargosOK = floatval( str_replace(",","", number_format ( PredialController::CalculoRecargos($fechaVencimiento, $montopredial,$cliente) , 2 ) ) );

            $actualizacionesOK = floatval( str_replace(",","", number_format ( PredialController::CalculoActualizacion($fechaVencimiento, $montopredial), 2 ) ) );
            //return  PredialController::CalculoRecargos($fechaVencimiento, $montopredial,$cliente)." ac". $actualizacionesOK;
        }else{
            $recargosOK  = 0;
            $actualizacionesOK = 0;
        }
      // return  $configuracionGenerarRecargos;
                //Configuracion del cliente para verificar si tiene habilidada la opcion de generar Act. y Rec.
                /*if($configuracionGenerarRecargos==0){
                     $recargosOK = 0;
                }
                if($configuracionGenerarActualizaciones==0){
                     $actualizacionesOK = 0;
                }*/
                #echo precode($configuracionGenerarActualizaciones .'<=aqui',1,1); exit;

    $montoBase=PredialController::CalcularValorCatastralConsMasTerreno($lectr->papid, $lectr->A_no,$cliente);
  // return  "(".$montopredial ." +". $actualizacionesOK ."+ ". $recargosOK ."+". $lectr->Multas ."+". $lectr->GastosEjecucion."+". $lectr->GastosEmbargo ."+". $lectr->OtrosGastos .")". "-". $lectr->Descuento ;

    $totalAnio=( $montopredial + $actualizacionesOK + $recargosOK + $lectr->Multas + $lectr->GastosEjecucion+ $lectr->GastosEmbargo + $lectr->OtrosGastos ) - $lectr->Descuento ;

    $Messumapredial+=$montopredial;
    $MessumaAct+=$actualizacionesOK;
    $MessumaRec+=$recargosOK;
    $MessumaMulta+=$lectr->Multas;
    $MessumaGastosEjecucion+=$lectr->GastosEjecucion;
    $MessumaGastosEmbargo+=$lectr->GastosEmbargo;
    $MessumaOtrosGastos+=$lectr->OtrosGastos;
    $MessumaDescuentos+=$lectr->Descuento;
    $MessumaTotalAnio+=$totalAnio;


    $sumapredial+=$montopredial;
    $sumaAct+=$actualizacionesOK;
    $sumaRec+=$recargosOK;
    $sumaMulta+=$lectr->Multas;
    $sumaGastosEjecucion+=$lectr->GastosEjecucion;
    $sumaGastosEmbargo+=$lectr->GastosEmbargo;
    $sumaOtrosGastos+=$lectr->OtrosGastos;
    $sumaDescuentos+=$lectr->Descuento;
    $sumaTotalAnio+=$totalAnio;
    $anioActual=$lectr->A_no;
    /**********************************************************************************************************/

}


if(count($ejecutaLect)>0){
    $MessumaDescuentos+=$ejecutaLect[0]->Descuento;
    $sumaDescuentos+=$ejecutaLect[0]->Descuento;
}



return number_format($sumaTotalAnio-$Descuentos,2);
}

    public function  GeneraEstadoDeCuenta(Request $request){
       $cliente=$request->Cliente;
       $idPadron=$request->IdPadron;
       $idLectura=$request->IdLectura;
       $anio=$request->Anio;
       $mes=$request->Mes;

       Funciones::selecionarBase( $cliente);

       $TipoPredio= DB::table('Padr_onCatastral')
       ->where('id',$idPadron)
       ->value('TipoPredio');

      // return PredialController::GenerarReciboOficialZofemat($cliente,$idPadron, $idLectura);

       if($TipoPredio==10){
        return PredialController::GenerarReciboOficialZofemat($cliente,$idPadron, $idLectura,$anio,$mes);
       }else{

           return PredialController::GeneraEstadoDeCuentaOficial($cliente,$idPadron, $idLectura,$anio,$mes);
       }

    }


    function GeneraEstadoDeCuentaOficial($cliente,$idPadron, $idLectura,$anio,$mes){



        $ServerNameURL=Funciones::ObtenValor("SELECT Valor FROM CelaConfiguraci_on WHERE Nombre='URLSitio'","Valor");


        $DatosPadron=Funciones::ObtenValor("SELECT *,  pa.Cuenta as CuentaOK,
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

            //Code para Personas vulnerables
         $ExisteDescuento=Funciones::ObtenValor("SELECT (SELECT Nombre FROM TipoDescuentoPersona WHERE id=cd.idTipoDescuentoPersona) TipoDescuento FROM Padr_onCatastral p INNER JOIN ClienteDescuentos cd ON (cd.idTipoDescuentoPersona=p.TipoDescuento) WHERE CURDATE() BETWEEN FechaInicial AND FechaFinal AND cd.Tipo='Predial' AND cd.Cliente=p.Cliente AND p.id=$idPadron","TipoDescuento");

        $tablaDatos=PredialController::obtieneDatosLecturaCatastralNuevo($idLectura,$ExisteDescuento,$cliente,$anio,$mes);

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
                    <span style="font-size: 20px;>Estado de Cuenta</span> <br />
                    <span  style="font-size: 12px;"><b>Estado de Cuenta</b>: <span  style="color:#ff0000; font-size: 20px;">'.$idLectura.'</span></span>
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
                    '<b>Ubicaci&oacute;n:</b> '.$DatosPadron->Ubicaci_on.' '.$DatosPadron->paColonia.'<br />
                    <b>Localidad:</b> '.$DatosPadron->LocalidadPredio.'<br />
                    <b>Municipio:</b> '.$DatosPadron->MunicipioPredio.'<br />
                    <b>Clave SUINPAC:</b> '.$DatosPadron->ClaveCatastral.'<br />
                    <b>Clave Catastral:</b> '.$DatosPadron->CuentaOK .' <br />
                    <b>Cuenta Predial:</b> '.$DatosPadron->CuentaAnterior.'<br />
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
                            <td width="9%" align="center"><b>'.(($DatosPadron->TipoPredio==10)?'Derecho':'Predial').'</b></td>
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
    } catch (Exception $e) {
        echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
    }
        /**/
    }

    public function  GeneraEstadoDeCuentaOficialNo($cliente,$idPadron, $idLectura,$anio,$mes){
      /* $cliente=$request->Cliente;
       $idPadron=$request->IdPadron;
       $idLectura=$request->IdLectura;*/


        //Funciones::selecionarBase( $cliente);
        $ConsultaDatosPadron="SELECT *,  pa.Cuenta as CuentaOK,
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
                    pa.id=".$idPadron;

        $DatosPadron=DB::select($ConsultaDatosPadron);

                    #precode($DatosPadron,1,1);

        $ConsultaDatosCliente="SELECT *, m.Nombre as Municipio, e.Nombre as Estado, cr.Ruta  as Logotipo
        FROM Cliente c
        INNER JOIN DatosFiscalesCliente d ON (d.id=c.DatosFiscales)
        INNER JOIN EntidadFederativa e ON (e.id=d.EntidadFederativa)
        INNER JOIN Municipio m ON (m.id=d.Municipio)
        INNER JOIN CelaRepositorioC cr ON (cr.idRepositorio=c.Logotipo)
        WHERE c.id=". $cliente;

        $DatosCliente=DB::select($ConsultaDatosCliente);


            $Copropietarios="";
            $ConsultaCopropietarios = "SELECT ( SELECT CONCAT(IF (c.NombreComercial IS NULL OR c.NombreComercial='',CONCAT_WS(' ',c.Nombres,c.ApellidoPaterno,c.ApellidoMaterno),c.NombreComercial))
            FROM `Contribuyente` c WHERE c.id=pcc.idContribuyente) AS CoPropietario FROM Padr_onCatastralCopropietarios pcc WHERE pcc.idPadron=".$idPadron;

           $ejecutaCopropietarios=DB::select($ConsultaCopropietarios);

            $row_cnt = count($ejecutaCopropietarios);
            $aux=1;
            foreach($ejecutaCopropietarios AS $registroCopropietarios ){
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

            //Code para Personas vulnerables
        $ConsultaExisteDescuento="SELECT (SELECT Nombre FROM TipoDescuentoPersona WHERE id=cd.idTipoDescuentoPersona) TipoDescuento FROM Padr_onCatastral p INNER JOIN ClienteDescuentos cd ON (cd.idTipoDescuentoPersona=p.TipoDescuento) WHERE CURDATE() BETWEEN FechaInicial AND FechaFinal AND cd.Tipo='Predial' AND cd.Cliente=p.Cliente AND p.id=$idPadron";
        $ExisteDescuento=DB::select($ConsultaExisteDescuento);

        $V= Funciones::ObtenValor("SELECT *, ccc.id as cccid, ca.Importe as ImporteUnitario,  pa.id as paid, pl.Consumo as ValorHistorial, pl.Status as EstatusPagado, pl.id as plid,  ca.BaseCalculo, pl.A_no, pl.Mes
        FROM Padr_onCatastral pa
        INNER JOIN Padr_onCatastralHistorial pl ON (pa.id=pl.Padr_onCatastral)
        INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=pa.ConceptoCobroCaja)
        INNER JOIN ConceptoRetencionesAdicionales cra ON ( cra.Concepto= ccc.id )
        INNER JOIN ConceptoRetencionesAdicionalesCliente crac ON ( cra.id = crac.ConceptoRetencionesAdicionales  AND crac.Cliente=pa.Cliente)
        INNER JOIN ConceptoAdicionales ca ON (ca.ConceptoRetencionesAdicionalesCliente=crac.id)
        WHERE
        pl.id=".$idLectura."
        ORDER BY pl.A_no DESC
        LIMIT 0, 1","cccid");
        return $V;
        $tablaDatos=PredialController::obtieneDatosLecturaCatastralNuevo($idLectura,$ExisteDescuento[0]->TipoDescuento,$cliente,$anio,$mes);

        //cuenta de deposito
        $ConsultaCuentas="SELECT c.N_umeroCuenta, c.Clabe, b.Nombre as Banco FROM CuentaBancaria c INNER JOIN Banco b ON (b.id=c.Banco) WHERE c.Cliente=".$cliente." AND c.CuentaDeRecaudacion=1";
        $ejecutaCuentas=DB::select($ConsultaCuentas);

        $lascuentas='';
        foreach($ejecutaCuentas AS $registroCuentas){
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
            <link href="'. asset(Storage::url(env('RESOURCES').'bootstrap.min.css')) .'"rel="stylesheet">

            <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <title>ServicioEnLinea.mx</title>
            </head>
            <body>
                    <table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="787px">
                            <tr>
                <td colspan="2" width="33.5%">
                    <img height="200px" src="'.asset($DatosCliente[0]->Logotipo).'">

                </td>
                <td  colspan="4"  width="66.5%" align="right">
                    '.$DatosCliente[0]->NombreORaz_onSocial.'<br />
                    Domicilio Fiscal: '.$DatosCliente[0]->Calle.' '.$DatosCliente[0]->N_umeroExterior.'<br />
                    '.$DatosCliente[0]->Colonia.', C.P. '.$DatosCliente[0]->C_odigoPostal.'<br />
                    '.$DatosCliente[0]->Municipio.', '.$DatosCliente[0]->Estado.'<br />
                    RFC: '.$DatosCliente[0]->RFC.'
                    <br /><br />
                    <span style="font-size: 20px;>Estado de Cuenta</span> <br />
                    <span  style="font-size: 12px;"><b>Estado de Cuenta</b>: <span  style="color:#ff0000; font-size: 20px;">'.$idLectura.'</span></span>
                </td>
            </tr>
            <tr>
                <td colspan="6" align="right"><img width="787px"  height="1px" src="' . idLectura.'" </td></tr>
        </table>
        <table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="787px">

            <tr>

                <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
                <br /><b>Datos del Predio</b><br />
                <br />	<b>Propietario:</b> '.$DatosPadron[0]->Propietario.'<br />'.$Copropietarios.
                    '<b>Ubicaci&oacute;n:</b> '.$DatosPadron[0]->Ubicaci_on.' '.$DatosPadron[0]->paColonia.'<br />
                    <b>Localidad:</b> '.$DatosPadron[0]->LocalidadPredio.'<br />
                    <b>Municipio:</b> '.$DatosPadron[0]->MunicipioPredio.'<br />
                    <b>Clave SUINPAC:</b> '.$DatosPadron[0]->ClaveCatastral .'<br />
                    <b>Clave Catastral:</b> '.$DatosPadron[0]->CuentaOK .' <br />
                    <b>Cuenta Predial:</b> '.$DatosPadron[0]->CuentaAnterior .'<br />
                </td>

                <td colspan="3"  width="50%" style="vertical-align:top;" v-align="top">
                <br /><b>Datos de Facturaci&oacute;n</b><br />
                <br /><b>Razon Social:</b> '.$DatosPadron[0]->NombreORaz_onSocial.'<br />
                    <b>RFC:</b> '.$DatosPadron[0]->RFC.'<br />
                    <b>Direcci&oacute;n:</b> '.$DatosPadron[0]->Calle.' '.$DatosPadron[0]->N_umeroExterior.'
                     '.$DatosPadron[0]->Colonia.', C.P. '.$DatosPadron[0]->C_odigoPostal.'<br />
                    '.$DatosPadron[0]->Municipio.', '.$DatosPadron[0]->Estado.'.<br />
                </td>
            </tr>
            <tr>
                <td colspan="6">
                    <br /><img width="787px" height="1px" src="' . asset(Storage::url(env('IMAGES') . 'barraColores.png')) .'"><br /> &nbsp;
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
                            <td width="9%" align="center"><b>'.(($DatosPadron[0]->TipoPredio==10)?'Derecho':'Predial').'</b></td>
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
            } catch (Exception $e) {
                echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
            }
        /**/
    }

    public function  obtieneDatosLecturaCatastralNuevoNo($Lectura,$ExisteDescuento,$cliente,$anio,$mes){

                //$configuracionGenerarRecargos = ObtenValorPorClave("GenerarRecargos", $_SESSION['CELA_Cliente' . $_SESSION['CELA_Aleatorio']]);
                //"SELECT Valor FROM ClienteDatos WHERE Cliente=".$Cliente." AND Indice='".$Clave."'"
                $configuracionGenerarRecargos = DB::table('ClienteDatos')
                ->where('Cliente', $cliente)
                ->where('Indice', "GenerarRecargos")
                ->value('Valor');

                if(!$configuracionGenerarRecargos==1)
                    $configuracionGenerarRecargos = 0;
//GenerarActualizaciones
                $configuracionGenerarActualizaciones= DB::table("ClienteDatos")
                ->where('Cliente', $cliente)
                ->where('Indice', "GenerarActualizaciones")
                ->value('Valor');

                if(!$configuracionGenerarActualizaciones==1)
                    $configuracionGenerarActualizaciones = 0;

                $montoBase = 0;
		       $ConsultaDatosLecturaActual="SELECT *, ccc.id as cccid, ca.Importe as ImporteUnitario,  pa.id as paid, pl.Consumo as ValorHistorial, pl.Status as EstatusPagado, pl.id as plid,  ca.BaseCalculo, pl.A_no, pl.Mes
                    FROM Padr_onCatastral pa
                    INNER JOIN Padr_onCatastralHistorial pl ON (pa.id=pl.Padr_onCatastral)
                    INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=pa.ConceptoCobroCaja)
                    INNER JOIN ConceptoRetencionesAdicionales cra ON ( cra.Concepto= ccc.id )
                    INNER JOIN ConceptoRetencionesAdicionalesCliente crac ON ( cra.id = crac.ConceptoRetencionesAdicionales  AND crac.Cliente=pa.Cliente)
                    INNER JOIN ConceptoAdicionales ca ON (ca.ConceptoRetencionesAdicionalesCliente=crac.id)
                    WHERE
                    pl.id=".$Lectura."
                    ORDER BY pl.A_no DESC
                    LIMIT 0, 1";

               $DatosLecturaActual =DB::select($ConsultaDatosLecturaActual);

               $decuentoInapam=DB::table("Padr_onCatastral")
               ->where("id",$DatosLecturaActual[0]->paid)
               ->value("INAPAM");

                $conditionDes=$decuentoInapam=='NULL' || $decuentoInapam==''? ' AND Descripci_on NOT LIKE "%INAPAM%" ':' AND Descripci_on LIKE "%INAPAM%" ';

                $lecturasCons="SELECT pal.id as palid,
                sum(pal.Consumo),
                pal.A_no,pal.Mes, pap.Cliente,
                pal.Status as EstatusPagado,
                sum(pap.ValorCatastral) as Tarifa,
                pap.SuperficieConstrucci_on,
                pap.SuperficieTerreno,
                pap.CuentaAnterior,
                pap.id as papid,
                pal.TerrenoCosto,
                pal.ConstruccionCosto,
                sum(pal.Consumo) as ValorHistorial,
                sum(pal.Multas) Multas,
                sum(pal.GastosEjecucion) GastosEjecucion,
                sum(pal.GastosEmbargo) GastosEmbargo,
                sum(pal.OtrosGastos) OtrosGastos,
                sum(pal.Consumo)*(
                        SELECT COALESCE(Porcentaje,0) FROM ClienteDescuentos
                        WHERE
                        1=2 AND Ejercicio=pal.A_no AND
                        Cliente=pap.Cliente AND
                        CURDATE() BETWEEN FechaInicial AND FechaFinal ".$conditionDes." )/100 as Descuento
                        FROM Padr_onCatastral pap
                        INNER JOIN Padr_onCatastralHistorial pal ON (pap.id=pal.Padr_onCatastral)
                        WHERE
                        pap.id =".$DatosLecturaActual[0]->paid." AND
                        pap.Cliente=".$cliente." AND
                        pal.Status IN (0,1) AND pal.A_no>2013 AND CONCAT(pal.A_no,pal.Mes)=".$anio.$mes." GROUP BY pal.A_no,pal.Mes ORDER BY pal.A_no DESC, pal.Mes DESC";


                        $resultados="";
                $sumapredial=0;
                $sumaAct=0;
                $sumaRec=0;
                $sumaMulta=0;
                $sumaGastosEjecucion=0;
                $sumaGastosEmbargo=0;
                $sumaOtrosGastos=0;
                $sumaDescuentos=0;
                $sumaTotalAnio=0;



                $Messumapredial=0;
                $MessumaAct=0;
                $MessumaRec=0;
                $MessumaMulta=0;
                $MessumaGastosEjecucion=0;
                $MessumaGastosEmbargo=0;
                $MessumaOtrosGastos=0;
                $MessumaDescuentos=0;
                $MessumaTotalAnio=0;
                $Descuentos=0;
                $anioActual=0;

                  $FechasVencimiento = ['01-15','01-31',
                                        '02-15','02-28',
                                        '03-15','03-31',
                                        '04-15','04-30',
                                        '05-15','05-31',
                                        '06-15','06-30',
                                        '07-15','07-31',
                                        '08-15','08-31',
                                        '09-15','09-30',
                                        '10-15','10-31',
                                        '11-15','11-30',
                                        '12-15','12-31'];



                $VencimientoEdoCuenta="";
                foreach ($FechasVencimiento as $valor){
                    $Fecha="";
                    $fechaVencimiento = date('Y-m-d', strtotime(date('Y-').$valor));

                    if(date('D',strtotime($fechaVencimiento))=="Sat"){
                        $fechaVencimiento = strtotime ( '-1 day' , strtotime ( $fechaVencimiento ) ) ;
                        $fechaVencimiento = date ( 'Y-m-d' , $fechaVencimiento );
                    }
                    if(date('D',strtotime($fechaVencimiento))=="Sun"){
                        $fechaVencimiento = strtotime ( '-2 day' , strtotime ( $fechaVencimiento ) ) ;
                        $fechaVencimiento = date ( 'Y-m-d' , $fechaVencimiento );
                    }

                    if( strtotime($fechaVencimiento) >= strtotime(date("Y-m-d")) ){
                       $VencimientoEdoCuenta = $fechaVencimiento;
                       break;
                    }
                }

                $ejecutaLect=DB::select($lecturasCons);

                foreach($ejecutaLect AS $lectr){

                    if($anioActual!=$lectr->A_no && $anioActual!=0){
                        $resultados.= "	<tr>
                                    <td align='center'>".$anioActual."</td>
                                    <td align='right'>".number_format($montoBase,2)."</td>
                                    <td align='right'>".number_format($Messumapredial,2)."</td>
                                    <td align='right'>".number_format($MessumaAct,2)."</td>
                                    <td align='right'>".number_format($MessumaRec,2)."</td>

                                    <td align='right'>".number_format($MessumaGastosEjecucion,2)."</td>
                                    <td align='right'>".number_format($MessumaGastosEmbargo,2)."</td>

                                    <td align='right'>".number_format($MessumaMulta,2)."</td>
                                    <td align='right'>".number_format($MessumaOtrosGastos,2)."</td>
                                    <td align='right'>".number_format($MessumaDescuentos,2)."</td>
                                    <td align='right'>".number_format($MessumaTotalAnio,2)."</td>
                                </tr>";

                        $Messumapredial=0;
                        $MessumaAct=0;
                        $MessumaRec=0;
                        $MessumaMulta=0;
                        $MessumaGastosEjecucion=0;
                        $MessumaGastosEmbargo=0;
                        $MessumaOtrosGastos=0;
                        $MessumaDescuentos=0;
                        $MessumaTotalAnio=0;
		      	}

            $montopredial = $lectr->ValorHistorial;

            $mes=($lectr->Mes*2)-1;
            $anio=$lectr->A_no;
            if(intval($mes)>12){
                $mes=1;
                $anio=$anio+1;
            }

           $mes=str_pad($mes, 2, "0", STR_PAD_LEFT);

            $dia=16;

            $fechaVencimiento= $anio."-".$mes."-".$dia;

            if(date('D',intval($fechaVencimiento))=="Sat"){
               $dia=$dia+2;
            }
            if(date('D',intval($fechaVencimiento))=="Sun"){
                $dia=$dia+1;
            }
            //aqui me quede mario
            $fechaVencimiento= $anio."-".$mes."-".$dia;
            //  echo "<br />FV:".$fechaVencimiento." H:i:00";

                $fecha_actual = strtotime(date("Y-m-d H:i:00",time()));
                $fecha_entrada = strtotime(date($fechaVencimiento." H:i:00"));
                if($fecha_actual > $fecha_entrada){
                   $recargosOK = floatval( str_replace(",","", number_format ( PredialController::CalculoRecargos($fechaVencimiento, $montopredial,$cliente) , 2 ) ) );

                    $actualizacionesOK = floatval( str_replace(",","", number_format ( PredialController::CalculoActualizacion($fechaVencimiento, $montopredial), 2 ) ) );

                }else{
                    $recargosOK  = 0;
                    $actualizacionesOK = 0;
                }

                        //Configuracion del cliente para verificar si tiene habilidada la opcion de generar Act. y Rec.
                        if($configuracionGenerarRecargos==0){
                             $recargosOK = 0;
                        }
                        if($configuracionGenerarActualizaciones==0){
                             $actualizacionesOK = 0;
                        }
                        #echo precode($configuracionGenerarActualizaciones .'<=aqui',1,1); exit;

			$montoBase=PredialController::CalcularValorCatastralConsMasTerreno($lectr->papid, $lectr->A_no,$cliente);

			$totalAnio=( $montopredial + $actualizacionesOK + $recargosOK + $lectr->Multas + $lectr->GastosEjecucion+ $lectr->GastosEmbargo + $lectr->OtrosGastos ) - $lectr->Descuento ;

			$Messumapredial+=$montopredial;
			$MessumaAct+=$actualizacionesOK;
			$MessumaRec+=$recargosOK;
			$MessumaMulta+=$lectr->Multas;
			$MessumaGastosEjecucion+=$lectr->GastosEjecucion;
			$MessumaGastosEmbargo+=$lectr->GastosEmbargo;
			$MessumaOtrosGastos+=$lectr->OtrosGastos;
			$MessumaDescuentos+=$lectr->Descuento;
			$MessumaTotalAnio+=$totalAnio;


			$sumapredial+=$montopredial;
			$sumaAct+=$actualizacionesOK;
			$sumaRec+=$recargosOK;
			$sumaMulta+=$lectr->Multas;
			$sumaGastosEjecucion+=$lectr->GastosEjecucion;
			$sumaGastosEmbargo+=$lectr->GastosEmbargo;
			$sumaOtrosGastos+=$lectr->OtrosGastos;
			$sumaDescuentos+=$lectr->Descuento;
			$sumaTotalAnio+=$totalAnio;
			$anioActual=$lectr->A_no;
			/**********************************************************************************************************/

		}


        if(count($ejecutaLect)>0){
            $MessumaDescuentos+=$ejecutaLect[0]->Descuento;
            $sumaDescuentos+=$ejecutaLect[0]->Descuento;
        }


					$resultados.= "	<tr>
								<td align='center'>".($anioActual==0? 2019: $anioActual )."</td>
								<td align='right'>".number_format($montoBase,2)."</td>
								<td align='right'>".number_format($Messumapredial,2)."</td>
								<td align='right'>".number_format($MessumaAct,2)."</td>
								<td align='right'>".number_format($MessumaRec,2)."</td>

								<td align='right'>".number_format($MessumaGastosEjecucion,2)."</td>
								<td align='right'>".number_format($MessumaGastosEmbargo,2)."</td>

                                                                <td align='right'>".number_format($MessumaMulta,2)."</td>

								<td align='right'>".number_format($MessumaOtrosGastos,2)."</td>
								<td align='right'>".number_format($MessumaDescuentos,2)."</td>
								<td align='right'>".number_format($MessumaTotalAnio,2)."</td>
							</tr>";

                #$sumaMulta=CalcularMultasPredial($DatosLecturaActual['paid'], $sumaTotalAnio-$Descuentos);

		$resultados.="<tr><td align='right' colspan='2'><b>Totales</b></td>
							<td align='right'><b>".number_format($sumapredial,2)."</b></td>
							<td align='right'><b>".number_format($sumaAct,2)."</b></td>
							<td align='right'><b>".number_format($sumaRec,2)."</b></td>

							<td align='right'><b>".number_format($sumaGastosEjecucion,2)."</b></td>
							<td align='right'><b>".number_format($sumaGastosEmbargo,2)."</b></td>

                                                        <td align='right'><b>".number_format($sumaMulta,2)."</b></td>
							<td align='right'><b>".number_format($sumaOtrosGastos,2)."</b></td>
							<td align='right'><b>".number_format($sumaDescuentos+$Descuentos,2)."</b></td>
							<td align='right'><b>".number_format($sumaTotalAnio-$Descuentos,2)."</b></td>
						</tr>
						<tr><td colspan='11'>&nbsp;</td></tr>";

		$resultados.="
						</table>
						<table style='padding:-35px 0 0 0;margin:-10px 0 0 0;' border='0' width='787px'>
					  <tr>
					  	<td colspan='11'><img width='787px' height='1px' src='" . asset(Storage::url(env('IMAGES') . 'barraColores.png')) ."'> <br /> </td>
					  </tr>
					  <tr>
						<td align='right' colspan='9'><br /><span style='font-size: 20px; font-weight: bold;'>Total a Pagar</span><br /><br /></td>
						<td align='right'  colspan='2'><br /><b><span style='font-size: 20px; font-weight: bold;'>$ ".number_format($sumaTotalAnio-$Descuentos,2)."</span></b><br /><br /></td>
					  </tr>

					  <tr>
					  	<td colspan='11'><br />
					  		<img width='787px' height='1px' src='" . asset(Storage::url(env("IMAGES") . "barraColores.png")) ."'>
					  	</td>
					  </tr>

						<tr>
							<td colspan='11' align='right'>
								<span style='font-size:12px;'>Expedici&oacute;n: ".date('d/m/Y H:i:s')."</span> - <span style='font-size:12px;'>Vencimiento: ".date('d/m/Y',strtotime($VencimientoEdoCuenta))."</span> <span style='font-size:12px;'>".' '.$cliente."</span>
							</td>
						</tr>

						<tr>
							<td colspan='11' align='right'>
								<span style='font-size:12px;'>Documento Informativo expedido a petici&oacute;n del contribuyente, no es un requerimiento de pago&nbsp;&nbsp;</span>
							</td>
						</tr>

					  ";
		return $resultados;
	}








public function GenerarReciboOficialZofemat($cliente,$idPadron, $idLectura,$anio,$mes){
	//ObtenValor("SELECT Valor FROM CelaConfiguraci_on WHERE Nombre='URLSitio'","Valor");
    $ServerNameURL=DB::table("CelaConfiguraci_on")
    ->where("Nombre","URLSitio")
    ->value("Valor");

	$ConsultaDatosPadron="SELECT *,  pa.Cuenta as CuentaOK,
		(SELECT Nombre FROM Municipio WHERE id=d.Municipio) as Municipio,
		(SELECT Nombre FROM EntidadFederativa WHERE id=d.EntidadFederativa) as Estado,
		(SELECT Nombre FROM Municipio WHERE id=pa.Municipio) as MunicipioPredio,
		(SELECT Nombre FROM Localidad WHERE id=pa.Localidad) as LocalidadPredio,
		CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno, c.NombreComercial ) as Propietario,
		pa.Colonia as paColonia, d.Colonia as Colonia,

	(
		SELECT t.Calle FROM TipoPredioValores t WHERE t.id=pa.TipoPredioValores

	) as TipoConcesion
    FROM Padr_onCatastral pa
    INNER JOIN Contribuyente c ON (c.id=pa.Contribuyente)
    INNER JOIN DatosFiscales d ON (d.id=c.DatosFiscales)
    WHERE
    pa.id=".$idPadron;
    $DatosPadron=DB::select($ConsultaDatosPadron);

#precode($DatosPadron,1,1);

	$ConsultaDatosCliente="SELECT *, m.Nombre as Municipio, e.Nombre as Estado, cr.Ruta  as Logotipo
	FROM Cliente c
	INNER JOIN DatosFiscalesCliente d ON (d.id=c.DatosFiscales)
	INNER JOIN EntidadFederativa e ON (e.id=d.EntidadFederativa)
	INNER JOIN Municipio m ON (m.id=d.Municipio)
	INNER JOIN CelaRepositorioC cr ON (cr.idRepositorio=c.Logotipo)
	WHERE c.id=". $cliente;
    $DatosCliente=DB::select($ConsultaDatosCliente);

	$tablaDatos=PredialController::obtieneDatosLecturaCatastralNuevo($idLectura,$cliente,$anio,$mes);

    //cuenta de deposito
    $ConsultaCuentas="SELECT c.N_umeroCuenta, c.Clabe, b.Nombre as Banco FROM CuentaBancaria c INNER JOIN Banco b ON (b.id=c.Banco) WHERE c.Cliente=".$cliente." AND c.CuentaDeRecaudacion=1";
//return $ConsultaCuentas;
   /* $ConsultaCuentas=DB::table("CuentaBancaria c")
    ->join("Banco b","b.id","=","c.Banco")
    ->where("c.Cliente",$cliente)
    ->where("c.CuentaDeRecaudacion",1)
    ->select("c.N_umeroCuenta", "c.Clabe", "b.Nombre as Banco");*/
    $ConsultaCuentas=DB::select($ConsultaCuentas);

	$lascuentas='';
	foreach($ConsultaCuentas as $registroCuentas){
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
<link href="'. asset(Storage::url(env('RESOURCES').'bootstrap.min.css')) .'"rel="stylesheet">

<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>ServicioEnLinea.mx</title>
</head>
<body>
	<table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0">
		<tr>
			<td colspan="2" width="33.5%">
				<img height="200px" src="'.asset($DatosCliente[0]->Logotipo).'">
			</td>
			<td  colspan="4"  width="66.5%" align="right">
				'.$DatosCliente[0]->NombreORaz_onSocial.'<br />
				Domicilio Fiscal: '.$DatosCliente[0]->Calle.' '.$DatosCliente[0]->N_umeroExterior.'<br />
				'.$DatosCliente[0]->Colonia.', C.P. '.$DatosCliente[0]->C_odigoPostal.'<br />
				'.$DatosCliente[0]->Municipio.', '.$DatosCliente[0]->Estado.'<br />
				RFC: '.$DatosCliente[0]->RFC.'
				<br /><br />
				<span style="font-size: 20px;>Estado de Cuenta</span> <br />
				<span  style="font-size: 12px;"><b>Estado de Cuenta Zofemat</b>: <span  style="color:#ff0000; font-size: 20px;">'.$idLectura.'</span></span>
			</td>
		</tr>
		<tr>
			<td colspan="6" align="right"><img width="100%"  height="1px" src="' . asset(Storage::url(env('IMAGES') . 'barraColores.png')) .'" </td></tr>
	</table>
	<table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="100%">

		<tr>

			<td width="50%" style="vertical-align:top;" v-align="top">
			<br /><b>Datos de la Concesi&oacute;n</b><br />
			<br />	<b>Propietario:</b> '.$DatosPadron[0]->Propietario.'<br />
				<b>Ubicaci&oacute;n:</b> '.$DatosPadron[0]->Ubicaci_on.' '.$DatosPadron[0]->paColonia.'<br />
				<b>Localidad:</b> '.$DatosPadron[0]->LocalidadPredio.'<br />
				<b>Municipio:</b> '.$DatosPadron[0]->MunicipioPredio.'<br />
				<b>Clave SUINPAC:</b> '.$DatosPadron[0]->ClaveCatastral .'<br />
				<b>Concesi&oacute;n:</b> '.$DatosPadron[0]->CuentaOK .' <br />
				<b>Cuenta Zofemat:</b> '.$DatosPadron[0]->CuentaAnterior.'<br />
				<b>Tipo:</b> '.$DatosPadron[0]->TipoConcesion .'<br />
				<b>Superficie:</b> '.number_format(str_replace(",", "",  $DatosPadron[0]->SuperficieTerreno),2) .'<br />
			</td>

			<td  width="50%" style="vertical-align:top;" v-align="top">
				<br /><b>Datos de Facturaci&oacute;n</b><br />
				<br /><b>Razon Social:</b> '.$DatosPadron[0]->NombreORaz_onSocial.'<br />
				<b>RFC:</b> '.$DatosPadron[0]->RFC.'<br />
				<b>Direcci&oacute;n:</b> '.$DatosPadron[0]->Calle.' '.$DatosPadron[0]->N_umeroExterior.'
				 '.$DatosPadron[0]->Colonia.', C.P. '.$DatosPadron[0]->C_odigoPostal.'<br />
				'.$DatosPadron[0]->Municipio.', '.$DatosPadron[0]->Estado.'.<br />
			</td>
		</tr>


	</table>
				<br /><img width="100%" height="1px" src="' . asset(Storage::url(env('IMAGES') . 'barraColores.png')) .'"><br /> &nbsp;
	<style>

	</style>
	<table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="787px">



		<tr>
			<td colspan="6"><br />
				<table class="table table-sm" style="padding:-35px 0 0 0;margin:-10px 0 0 0; font-size:12px;" border="0" width="787px">
					<tr>
						<td width="4%" align="center"><b>A&ntilde;o</b></td>
						<td width="4%" align="center"><b>Bimestre</b></td>
						<td width="10%" align="center"><b>Base</b></td>
						<td width="9%" align="center"><b>'.(($DatosPadron[0]->TipoPredio==10)?'Derecho':'Predial').'</b></td>
						<td width="9%" align="center"><b>Act</b></td>
						<td width="9%" align="center"><b>Rec</b></td>
						<td width="9%" align="center"><b>Multas</b></td>
						<td width="9%" align="center"><b>Gastos Ejecucion</b></td>
						<td width="9%" align="center"><b>Gastos Embargo</b></td>
						<td width="9%" align="center"><b>Otros Gastos</b></td>
						<td width="9%" align="center"><b>Descuento</b></td>
						<td width="10%" align="center"><b>Total</b></td>
					</tr>
					'.$tablaDatos.'
				</table>
			</td>
		</tr>



	</table>
</body>
</html>';

include( app_path() . '/Libs/Wkhtmltopdf.php' );
                try {
                    $nombre = uniqid() . "_" . $idPadron;
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
                } catch (Exception $e) {
                    echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
                }


	/**/
}


function obtieneDatosLecturaCatastralNuevo($Lectura, $ExisteDescuento,$cliente,$anio,$mes){

            $IdCliente=$cliente;
            $configuracionGenerarRecargos = PredialController::ObtenValorPorClave("GenerarRecargos",$cliente);

            if(!$configuracionGenerarRecargos==1)
                $configuracionGenerarRecargos = 0;

            $configuracionGenerarActualizaciones= PredialController::ObtenValorPorClave("GenerarActualizaciones", $cliente);
            if(!$configuracionGenerarActualizaciones==1)
                $configuracionGenerarActualizaciones = 0;
                $montoBase = 0;
                $DescuentoVulnerable=false;
                $TipoPredio=0;
                $Concepto=0;
    $DatosLecturaActual=Funciones::ObtenValor("SELECT *, ccc.id as cccid, ca.Importe as ImporteUnitario,  pa.id as paid, pl.Consumo as ValorHistorial, pl.Status as EstatusPagado, pl.id as plid,  ca.BaseCalculo, pl.A_no, pl.Mes
        FROM Padr_onCatastral pa
        INNER JOIN Padr_onCatastralHistorial pl ON (pa.id=pl.Padr_onCatastral)
        INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=pa.ConceptoCobroCaja)
        INNER JOIN ConceptoRetencionesAdicionales cra ON ( cra.Concepto= ccc.id )
        INNER JOIN ConceptoRetencionesAdicionalesCliente crac ON ( cra.id = crac.ConceptoRetencionesAdicionales  AND crac.Cliente=pa.Cliente)
        INNER JOIN ConceptoAdicionales ca ON (ca.ConceptoRetencionesAdicionalesCliente=crac.id)
        WHERE
        pl.id=".$Lectura."
        ORDER BY pl.A_no DESC
        LIMIT 0, 1");

            $anioCondicion=' AND pal.A_no<2020 ';
            $ConcionDescuentoClientes="IF(pap.TipoDescuento >0, (sum(pal.Consumo)*(SELECT COALESCE(Porcentaje,0) FROM ClienteDescuentos WHERE Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND Tipo=TRIM('Predial') AND idTipoDescuentoPersona=pap.TipoDescuento )/100 ), (sum(pal.Consumo)*(SELECT COALESCE(Porcentaje,0) FROM ClienteDescuentos WHERE Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND Tipo=TRIM('Predial') AND idTipoDescuentoPersona IS NULL AND Descripci_on NOT LIKE '%INAPAM%')/100 )) as Descuento,";

            if($IdCliente==35 && false) {
                $VerificaRezago= Funciones::ObtenValor("(SELECT COALESCE(COUNT(pch.id),0) Adeudo FROM Padr_onCatastralHistorial pch WHERE pch.Padr_onCatastral =".$DatosLecturaActual->paid." AND pch.`Status` IN (0,1) AND pch.A_no<=2019)", "Adeudo");
                if($VerificaRezago>0)
                    $anioCondicion=' AND pal.A_no<2020 ';
                else
                    $anioCondicion='';

                $ConcionDescuentoClientes=" sum(pal.Consumo)*IF(pal.A_no<=2018, (SELECT COALESCE(Porcentaje, 0) FROM ClienteDescuentos WHERE Ejercicio=2018 AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND Tipo='Buen Fin' AND idTipoDescuentoPersona IS NULL),
                (SELECT COALESCE(Porcentaje,0) FROM ClienteDescuentos WHERE Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND Tipo='Predial' AND idTipoDescuentoPersona IS NULL) ) / 100 as Descuento,";

            }else{
                $anioCondicion='';
            }

    $decuentoInapam=Funciones::ObtenValor("SELECT INAPAM FROM Padr_onCatastral WHERE id=".$DatosLecturaActual->paid, "INAPAM");
            $conditionDes=$decuentoInapam=='NULL' || $decuentoInapam==''? ' AND Descripci_on NOT LIKE "%INAPAM%" ':' AND Descripci_on LIKE "%INAPAM%" ';

            #precode($DescBorron,1,1);

    $lecturasCons="SELECT pal.id as palid,
    sum(pal.Consumo),
    pal.A_no,pal.Mes, pap.Cliente,
    pal.Status as EstatusPagado,
    sum(pap.ValorCatastral) as Tarifa,
    pap.SuperficieConstrucci_on,
    pap.SuperficieTerreno,
    pap.CuentaAnterior,
    pap.id as papid,
    pap.TipoPredio,
    pal.ConceptoCotizacion,
    pap.TipoDescuento,
    pal.TerrenoCosto,
    pal.ConstruccionCosto,
    sum(pal.Consumo) as ValorHistorial,
    sum(pal.Multas) Multas,
    sum(pal.GastosEjecucion) GastosEjecucion,
    sum(pal.GastosEmbargo) GastosEmbargo,
    sum(pal.OtrosGastos) OtrosGastos,
    $ConcionDescuentoClientes
            IF(pal.A_no<=2019, (SELECT COALESCE(Porcentaje, 0) FROM ClienteDescuentos WHERE Ejercicio=2019 AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND Tipo='Actualizaciones' AND idTipoDescuentoPersona IS NULL),0) AS DescuentoActualizaciones,
            IF(pal.A_no<=2019, (SELECT COALESCE(Porcentaje, 0) FROM ClienteDescuentos WHERE Ejercicio=2019 AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND Tipo='RecargosV2' AND idTipoDescuentoPersona IS NULL),0) AS DescuentoRecargos

            FROM Padr_onCatastral pap
            INNER JOIN Padr_onCatastralHistorial pal ON (pap.id=pal.Padr_onCatastral)
            WHERE
            pap.id =".$DatosLecturaActual->paid." AND
            pap.Cliente=".$cliente." AND
            pal.Status IN (0,1) AND pal.A_no>2013 AND CONCAT(pal.A_no,pal.Mes)<=".$DatosLecturaActual->A_no.$DatosLecturaActual->Mes." $anioCondicion GROUP BY pal.A_no,pal.Mes ORDER BY pal.A_no DESC, pal.Mes DESC";

            #precode($lecturasCons,1,1);
            $resultados="";
    $sumapredial=0;
    $sumaAct=0;
    $sumaRec=0;
    $sumaMulta=0;
    $sumaGastosEjecucion=0;
    $sumaGastosEmbargo=0;
    $sumaOtrosGastos=0;
    $sumaDescuentos=0;
    $sumaTotalAnio=0;



    $Messumapredial=0;
    $MessumaAct=0;
    $MessumaRec=0;
    $MessumaMulta=0;
    $MessumaGastosEjecucion=0;
    $MessumaGastosEmbargo=0;
    $MessumaOtrosGastos=0;
    $MessumaDescuentos=0;
    $MessumaTotalAnio=0;
    $Descuentos=0;
    $anioActual=0;

              $FechasVencimiento = ['01-15','01-31',
                                    '02-15','02-28',
                                    '03-15','03-31',
                                    '04-15','04-30',
                                    '05-15','05-31',
                                    '06-15','06-30',
                                    '07-15','07-31',
                                    '08-15','08-31',
                                    '09-15','09-30',
                                    '10-15','10-31',
                                    '11-15','11-30',
                                    '12-15','12-31'];



            $VencimientoEdoCuenta="";
            foreach ($FechasVencimiento as $valor){
                $Fecha="";
                $fechaVencimiento = date('Y-m-d', strtotime(date('Y-').$valor));

                if(date('D',strtotime($fechaVencimiento))=="Sat"){
                    $fechaVencimiento = strtotime ( '-1 day' , strtotime ( $fechaVencimiento ) ) ;
                    $fechaVencimiento = date ( 'Y-m-d' , $fechaVencimiento );
                }
                if(date('D',strtotime($fechaVencimiento))=="Sun"){
                    $fechaVencimiento = strtotime ( '-2 day' , strtotime ( $fechaVencimiento ) ) ;
                    $fechaVencimiento = date ( 'Y-m-d' , $fechaVencimiento );
                }

                if( strtotime($fechaVencimiento) >= strtotime(date("Y-m-d")) ){
                   $VencimientoEdoCuenta = $fechaVencimiento;
                   break;
                }
            }

    $ejecutaLect=DB::select($lecturasCons);

    foreach($ejecutaLect as $lectr){

                #precode($lectr,1);
        if($anioActual!=$lectr->A_no && $anioActual!=0){
            if($DescuentoVulnerable) {
                $datosDeConcepto = Funciones::ObtenValor("SELECT DISTINCT c3.Importe as c3Importe, c3.id as c3id
                FROM ConceptoCobroCaja c
                INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto )
                INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales )
                INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente )
                WHERE c2.AplicaEnSubtotal=0 AND c3.Cliente=$IdCliente AND
                c3.EjercicioFiscal=$anioActual AND
                c2.Cliente=$IdCliente AND c.id = $Concepto");
                $ImpMinimo=PredialController::CalculaImpuestoBaseMinima($IdCliente, $TipoPredio, $anioActual, $Concepto, $datosDeConcepto->c3Importe);
                if($MessumaTotalAnio<$ImpMinimo){
                    $sumaDescuentos=$sumaDescuentos-$MessumaDescuentos;
                    $sumaTotalAnio=$sumaTotalAnio+$MessumaDescuentos;
                    $MessumaDescuentos=$Messumapredial-$ImpMinimo;
                    $MessumaTotalAnio=$Messumapredial-$MessumaDescuentos;
                    $sumaDescuentos+=$MessumaDescuentos;
                    $sumaTotalAnio=$sumaTotalAnio-$MessumaDescuentos;
                }
            }
            $resultados.= "	<tr>
                        <td align='center'>".$anioActual."</td>
                        <td align='right'>".number_format($montoBase,2)."</td>
                        <td align='right'>".number_format($Messumapredial,2)."</td>
                        <td align='right'>".number_format($MessumaAct,2)."</td>
                        <td align='right'>".number_format($MessumaRec,2)."</td>

                        <td align='right'>".number_format($MessumaGastosEjecucion,2)."</td>
                        <td align='right'>".number_format($MessumaGastosEmbargo,2)."</td>

                                                    <td align='right'>".number_format($MessumaMulta,2)."</td>
                        <td align='right'>".number_format($MessumaOtrosGastos,2)."</td>
                        <td align='right'>".number_format($MessumaDescuentos,2)."</td>
                        <td align='right'>".number_format($MessumaTotalAnio,2)."</td>
                    </tr>";

            $Messumapredial=0;
            $MessumaAct=0;
            $MessumaRec=0;
            $MessumaMulta=0;
            $MessumaGastosEjecucion=0;
            $MessumaGastosEmbargo=0;
            $MessumaOtrosGastos=0;
            $MessumaDescuentos=0;
            $MessumaTotalAnio=0;
        }

        $montopredial = $lectr->ValorHistorial;

        $mes=($lectr->Mes*2)-1;
        $anio=$lectr->A_no;
        if(intval($mes)>12){
            $mes=1;
            $anio=$anio+1;
        }

       $mes=str_pad($mes, 2, "0", STR_PAD_LEFT);

        $dia=28;

        $fechaVencimiento= $anio."-".$mes."-".$dia;

        if(date('D',intval($fechaVencimiento))=="Sat"){
           $dia=$dia+2;
        }
        if(date('D',intval($fechaVencimiento))=="Sun"){
            $dia=$dia+1;
        }

        $fechaVencimiento= $anio."-".$mes."-".$dia;
        //  echo "<br />FV:".$fechaVencimiento." H:i:00";

            $fecha_actual = strtotime(date("Y-m-d H:i:00",time()));
            $fecha_entrada = strtotime(date($fechaVencimiento." H:i:00"));


            if($fecha_actual > $fecha_entrada){
                $recargosOK = floatval( str_replace(",","", number_format ( PredialController::CalculoRecargos($fechaVencimiento, $montopredial,$cliente) , 2 ) ) );
                $actualizacionesOK = floatval( str_replace(",","", number_format ( PredialController::CalculoActualizacion($fechaVencimiento, $montopredial), 2 ) ) );

            }else{
                $recargosOK  = 0;
                $actualizacionesOK = 0;
            }

                    //Configuracion del cliente para verificar si tiene habilidada la opcion de generar Act. y Rec.
                    if($configuracionGenerarRecargos==0){
                         $recargosOK = 0;
                    }
                    if($configuracionGenerarActualizaciones==0){
                         $actualizacionesOK = 0;
                    }
                    #echo precode($configuracionGenerarActualizaciones .'<=aqui',1,1); exit;

                    $montoBase=PredialController::CalcularValorCatastralConsMasTerreno($lectr->papid, $lectr->A_no,$cliente);
                    if($_SESSION['CELA_Cliente' . $_SESSION['CELA_Aleatorio']]==14)
                      $montoBase=Funciones::ObtenValor("SELECT (COALESCE(TerrenoCosto,0)+COALESCE(ConstruccionCosto,0)) AS BaseGravable FROM Padr_onCatastralHistorial WHERE Padr_onCatastral=".$lectr->papid." AND A_no=".$lectr->A_no." LIMIT 1", "BaseGravable");

                    $recargosOKDescuento=($recargosOK*$lectr->DescuentoRecargos)/100;
                    $actualizacionesOKDescuento=($actualizacionesOK*$lectr->DescuentoActualizaciones)/100;

        $Messumapredial+=$montopredial;
        $MessumaAct+=$actualizacionesOK;
        $MessumaRec+=$recargosOK;
        $MessumaMulta+=$lectr->Multas;
        $MessumaGastosEjecucion+=$lectr->GastosEjecucion;
        $MessumaGastosEmbargo+=$lectr->GastosEmbargo;
        $MessumaOtrosGastos+=$lectr->OtrosGastos;
        $MessumaDescuentos+= $lectr->Descuento+$recargosOKDescuento+$actualizacionesOKDescuento;

        $totalAnio=( $montopredial + $actualizacionesOK + $recargosOK+ $lectr->Multas + $lectr->GastosEjecucion + $lectr->GastosEmbargo + $lectr->OtrosGastos ) -($lectr->Descuento+$recargosOKDescuento+$actualizacionesOKDescuento) ;

        $MessumaTotalAnio+=$totalAnio;

        $sumapredial+=$montopredial;
        $sumaAct+=$actualizacionesOK;
        $sumaRec+=$recargosOK;
        $sumaMulta+=$lectr->Multas;
        $sumaGastosEjecucion+=$lectr->GastosEjecucion;
        $sumaGastosEmbargo+=$lectr->GastosEmbargo;
        $sumaOtrosGastos+=$lectr->OtrosGastos;
        $sumaDescuentos+=$lectr->Descuento+$recargosOKDescuento+$actualizacionesOKDescuento;
        $sumaTotalAnio+=$totalAnio;
        $anioActual=$lectr->A_no;
        $DescuentoVulnerable=($lectr->TipoDescuento>0? true:false);
        $TipoPredio=$lectr->TipoPredio;
        $Concepto=$lectr->ConceptoCotizacion;

        /**********************************************************************************************************/

    }

          //Condicion agregada para no permitir descuentos abajo de la base minima en grupos vulnerables
          if($DescuentoVulnerable) {
            $datosDeConcepto = Funciones::ObtenValor("SELECT DISTINCT c3.Importe as c3Importe, c3.id as c3id
            FROM ConceptoCobroCaja c
            INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto )
            INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales )
            INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente )
            WHERE c2.AplicaEnSubtotal=0 AND c3.Cliente=$IdCliente AND
            c3.EjercicioFiscal=$anioActual AND
            c2.Cliente=$IdCliente AND c.id = $Concepto");
            $ImpMinimo=PredialController::CalculaImpuestoBaseMinima($IdCliente, $TipoPredio, $anioActual, $Concepto, $datosDeConcepto->c3Importe);
            if($MessumaTotalAnio<$ImpMinimo){
                $sumaDescuentos=$sumaDescuentos-$MessumaDescuentos;
                $sumaTotalAnio=$sumaTotalAnio+$MessumaDescuentos;
                $MessumaDescuentos=$Messumapredial-$ImpMinimo;
                $MessumaTotalAnio=$Messumapredial-$MessumaDescuentos;
                $sumaDescuentos+=$MessumaDescuentos;
                $sumaTotalAnio=$sumaTotalAnio-$MessumaDescuentos;
            }

        }

             //   $MessumaDescuentos+=$lectr->Descuento;
              //  $sumaDescuentos+=$lectr->Descuento;

                $resultados.= "	<tr>
                            <td align='center'>".($anioActual==0? 2010: $anioActual )."</td>
                            <td align='right'>".number_format($montoBase,2)."</td>
                            <td align='right'>".number_format($Messumapredial,2)."</td>
                            <td align='right'>".number_format($MessumaAct,2)."</td>
                            <td align='right'>".number_format($MessumaRec,2)."</td>

                            <td align='right'>".number_format($MessumaGastosEjecucion,2)."</td>
                            <td align='right'>".number_format($MessumaGastosEmbargo,2)."</td>

                         <td align='right'>".number_format($MessumaMulta,2)."</td>

                            <td align='right'>".number_format($MessumaOtrosGastos,2)."</td>
                            <td align='right'>".number_format($MessumaDescuentos,2)."</td>
                            <td align='right'>".number_format($MessumaTotalAnio,2)."</td>
                        </tr>";

            #$sumaMulta=CalcularMultasPredial($DatosLecturaActual['paid'], $sumaTotalAnio-$Descuentos);
            $Decimales=explode(".", ($sumaTotalAnio-$Descuentos==0?'0.0':$sumaTotalAnio-$Descuentos) );

    $resultados.="<tr><td align='right' colspan='2'><b>Totales </b></td>
                        <td align='right'><b>".number_format($sumapredial,2)."</b></td>
                        <td align='right'><b>".number_format($sumaAct,2)."</b></td>
                        <td align='right'><b>".number_format($sumaRec,2)."</b></td>

                        <td align='right'><b>".number_format($sumaGastosEjecucion,2)."</b></td>
                        <td align='right'><b>".number_format($sumaGastosEmbargo,2)."</b></td>

                                                    <td align='right'><b>".number_format($sumaMulta,2)."</b></td>
                        <td align='right'><b>".number_format($sumaOtrosGastos,2)."</b></td>
                        <td align='right'><b>".number_format($sumaDescuentos+$Descuentos,2)."</b></td>
                        <td align='right'><b>".number_format($sumaTotalAnio-$Descuentos,2)."</b></td>
                    </tr>
                    <tr>
                    <td align='right' colspan='10'><b>Descuento por Redondeo</b></td>
                    <td align='right'><b>- ". number_format(floatval('0.'.$IdCliente),2)."</b></td>
                    </tr>
                    <tr><td colspan='11'>&nbsp;</td></tr>";

    $resultados.="
                    </table>
                    <table style='padding:-35px 0 0 0;margin:-10px 0 0 0;' border='0' width='787px'>
                  <tr>
                      <td colspan='11'><img width='787px' height='1px' src='". asset(Storage::url(env('IMAGES') . 'barraColores.png'))."'> <br /> </td>
                  </tr>
                  <tr>
                    <td align='right' colspan='9'><br /><span style='font-size: 20px; font-weight: bold;'>Total a Pagar</span><br /><br /></td>
                    <td align='right'  colspan='2'><br /><b><span style='font-size: 20px; font-weight: bold;'>$ ".number_format($sumaTotalAnio-$Descuentos,2)."</span></b><br /><br /></td>
                  </tr>

                  <tr>
                      <td colspan='11'><br />
                          <img width='787px' height='1px' src='". asset(Storage::url(env('IMAGES') . 'barraColores.png'))."'>
                      </td>
                  </tr>

                                       ".($ExisteDescuento=='NULL'? '':'<tr>
                                            <td colspan="11" class="text-right">
                                                <span style="color:red; font-size:12px;"><b>Estímulo Fiscal: '.$ExisteDescuento. ' </span>
                                            </td>
                                        </tr>').

                                            "<tr>
                        <td colspan='11' align='right'>
                            <span style='font-size:12px;'>Expedici&oacute;n: ".date('d/m/Y H:i:s')."</span> - <span style='font-size:12px;'>Vencimiento: ".date('d/m/Y',strtotime($VencimientoEdoCuenta))."</span> <span style='font-size:12px;'></span>
                        </td>
                    </tr>

                    <tr>
                        <td colspan='11' align='right'>
                            <span style='font-size:12px;'>Documento Informativo expedido a petici&oacute;n del Contribuyente, no es un requerimiento de pago.</span>
                        </td>
                    </tr>

                  ";
    return $resultados;
}

public static function ObtenValorPorClave($Clave, $Cliente){
	return Funciones::ObtenValor("SELECT Valor FROM ClienteDatos WHERE Cliente=".$Cliente." AND Indice='".$Clave."'", "Valor");

}

function CalculoRecargos($fechaConcepto, $ImporteConcepto, $cliente){

        //Es Recargo
           $Actualizacion		=PredialController::CalculoActualizacion($fechaConcepto, $ImporteConcepto);

          $FactorActualizacion=PredialController::CalculofactorActualizacion($fechaConcepto, $ImporteConcepto);


        $mesConocido=0;
        $SumaDeTasa=0;

        $fechaHoy= date("Y-m-d", strtotime ( '-1 month' , strtotime (  date('Y-m-d') )) );
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


function CalculoActualizacion($fechaConcepto, $ImporteConcepto){
    //Es Actualizacion
    $fechaHoy=date('Y-m-d');
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

function CalculofactorActualizacion($fechaConcepto, $ImporteConcepto){
    //Es Actualizacion
    $fechaHoy=date('Y-m-d');

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

function CalcularValorCatastralConsMasTerreno($IdPadron, $anio, $cliente , $regresaArray=0) {

    $ValorCatastral = PredialController::ImporteTotalDelPredioBK($IdPadron, $anio, $cliente, 1);

    $ValorCatastral['TipoConstruccionValor'] = ($ValorCatastral['TipoConstruccionValor'] == "NULL" ? NULL : $ValorCatastral['TipoConstruccionValor']);
    $datosPadron = Funciones::ObtenValor("SELECT * FROM Padr_onCatastral pc WHERE pc.id=" . $IdPadron);
    $datosConstruccionDetalle = "SELECT * FROM Padr_onConstruccionDetalle WHERE idPadron=" . $IdPadron;
    $ejecuta = DB::select($datosConstruccionDetalle);
    $datosConsArr = array();
    $consValor = 0;
    foreach ($ejecuta as $RegistroDetalle) {
        #precode($RegistroDetalle,1,1);
        $datosConsArr[] = $RegistroDetalle;

        $tipoConstValor = Funciones::ObtenValor("SELECT tce.Importe as Importe FROM TipoConstrucci_onValores tc INNER JOIN TipoConstrucci_onValoresEjercicioFiscal tce ON (tce.idTipoConstrucci_onValores=tc.id AND tce.EjercicioFiscal=" . $anio . ") WHERE Cliente=" . $cliente . " AND idTipoConstrucci_on=" . $RegistroDetalle->TipoConstruci_on, "Importe");

        $RegistroDetalle->Costo = floatval($tipoConstValor) * floatval($RegistroDetalle->SuperficieConstrucci_on) * ($RegistroDetalle->Indiviso / 100);
        #	precode($RegistroDetalle, 1);
        $consValor += floatval(str_replace(",", "", number_format($RegistroDetalle->Costo, 2)));
    }

    $tipoPredio = Funciones::ObtenValor("SELECT TipoPredio FROM Padr_onCatastral WHERE id=" . $IdPadron . " AND Cliente=" . $cliente, 'TipoPredio');

    $SuperficieDeTerreno = floatval(str_replace(",", "", $datosPadron->SuperficieTerreno));
    //Condicion para resolver lo de Rusticos

    if ($datosPadron->TipoPredio== 13) {
        $ValoresMinimosRusticos = Funciones::ObtenValor("SELECT Hasta FROM BaseGravableMinima WHERE TipoPredio=" . $datosPadron->TipoPredio . " AND Cliente=" . $cliente, 'Hasta');
        if ($ValoresMinimosRusticos != "NULL") {
            if (floatval($SuperficieDeTerreno) <= floatval($ValoresMinimosRusticos)) {
                $SuperficieDeTerreno = floatval($ValoresMinimosRusticos);
            }
        }
    }


    $costoTerrenoEnAnio = (str_replace(",", "", ($ValorCatastral['TipoPredioValor']>0? $ValorCatastral['TipoPredioValor']: 0) ) * ($datosPadron->Indiviso / 100) * str_replace(",", "", ($SuperficieDeTerreno)) );

    if($regresaArray==0)
        return $costoTerrenoEnAnio + $consValor;
    else
        return Array('CostoTerreno'=> $costoTerrenoEnAnio, "CostoConstruccion"=>$consValor);
}

function ImporteTotalDelPredioBK($padron, $anio, $cliente, $regresaArray=0){
	$datosPadron=Funciones::ObtenValor("SELECT  SuperficieConstrucci_on, SuperficieTerreno, TipoPredioValores, TipoConstrucci_onValores, TipoConstruci_on FROM Padr_onCatastral pc WHERE pc.id=".$padron);

    if(isset($datosPadron->TipoPredioValores) && !is_null($datosPadron->TipoPredioValores) ){
	$tipoPredioValor=Funciones::ObtenValor("SELECT  tpve.Importe FROM TipoPredioValores tpv
        INNER JOIN TipoPredioValoresEjercicioFiscal tpve ON (tpv.id=tpve.idTipoPredioValores AND tpve.EjercicioFiscal=".$anio.")
        WHERE tpv.id=".$datosPadron->TipoPredioValores, "Importe");
        #precode($tipoPredioValor,1,1);
            }else{
                $tipoPredioValor=0;
            }

            if(isset($datosPadron->TipoConstruci_on) && !is_null($datosPadron->TipoConstruci_on) ){

            $tipoConstruccionValor=Funciones::ObtenValor("SELECT tce.Importe
        FROM TipoConstrucci_onValores tc
        INNER JOIN TipoConstrucci_onValoresEjercicioFiscal tce ON (tce.idTipoConstrucci_onValores=tc.id AND tce.EjercicioFiscal=".$anio.")
        WHERE tc.Cliente=".$cliente." AND tc.idTipoConstrucci_on=".$datosPadron->TipoConstruci_on, "Importe");
            }else{
                $tipoConstruccionValor=0;
            }

            $array=array();
            if(isset($datosPadron->SuperficieConstrucci_on) && !is_null($datosPadron->SuperficieConstrucci_on) ){
                $datosPadron->SuperficieConstrucci_on=$datosPadron->SuperficieConstrucci_on;
            }else{
                $datosPadron->SuperficieConstrucci_on=0;
            }

            if(isset($datosPadron->SuperficieTerreno) && !is_null($datosPadron->SuperficieTerreno) ){
                $datosPadron->SuperficieTerreno=$datosPadron->SuperficieTerreno;
            }else{
                $datosPadron->SuperficieTerreno=0;
            }

                $valorCatastral=(floatval(str_replace(",","", (floatval($datosPadron->SuperficieConstrucci_on)>0? $datosPadron->SuperficieConstrucci_on:0)   ) ) *(( $tipoConstruccionValor)=="NULL"? 1:$tipoConstruccionValor) ) + (str_replace(",","", ($datosPadron->SuperficieTerreno!=0? $datosPadron->SuperficieTerreno: 0 ) )* ($tipoPredioValor=="NULL"? 1:$tipoPredioValor) );
            #	echo "<br />( ".$datosPadron['SuperficieConstrucci_on']." * ".$tipoConstruccionValor ." ) + ( ".$datosPadron['SuperficieTerreno']." * ".$tipoPredioValor." ) = ".$valorCatastral ;
        $array['ValorCatastral']=$valorCatastral;
        $array['SuperficieConstrucci_on']=$datosPadron->SuperficieConstrucci_on;
        $array['TipoConstruccionValor']=$tipoConstruccionValor;
        $array['SuperficieTerreno']=$datosPadron->SuperficieTerreno;
        $array['TipoPredioValor']=$tipoPredioValor;
            if($regresaArray==0)
                return $valorCatastral;
            else
                return $array;
}
public function  obtenerDatosFiscales(Request $request){
    $cliente=$request->Cliente;
    $IdPadron=$request->IdPadron;

    Funciones::selecionarBase( $cliente);

   $DatosFiscales = DB::table('Contribuyente as C')
   ->join('DatosFiscales as DF', 'C.DatosFiscales','=','DF.id')
   ->join('Padr_onCatastral as PC','PC.Contribuyente','=','C.id')
   ->select("DF.id", "DF.RFC","DF.NombreORaz_onSocial","DF.EntidadFederativa","DF.Municipio","DF.Localidad","DF.Colonia","DF.Calle","DF.N_umeroInterior","DF.N_umeroExterior","DF.C_odigoPostal","DF.Referencia","DF.R_egimenFiscal","C.id as idContribuyente")
   ->where('PC.Id', $IdPadron)
   ->first();
   return response()->json([
    'success' => '1',
    'datosFiscales'=>$DatosFiscales
    ], 200);


}


    public function obtenerEstadoDeCuentaPredialSuinpac(Request  $request){
        $cliente=$request->Cliente;
        $idPadron=$request->IdPadron;
        $idLectura=$request->IdLectura;

        $url = 'https://suinpac.com/Padr_onCatastralHistorialVistaPreviaEnLinea.php';
        $dataForPost = array(
            'Cliente'=> [
                "Padr_onPredial"=>$idPadron,
                "lectura"=>$idLectura,
                "cliente"=>$cliente,
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
        ], 200);
    }

    public function  obtenerEstadoDeCuentaPredial(Request $request){
        $cliente=$request->Cliente;
        $idPadron=$request->IdPadron;
        $idLectura=$request->IdLectura;
        Funciones::selecionarBase( $cliente);
        $DatosPadron= Funciones::ObtenValor("SELECT *,  pa.Cuenta as CuentaOK, (SELECT Nombre FROM Municipio WHERE id=d.Municipio) as Municipio,
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


        $Copropietarios="";
        $ConsultaCopropietarios = "SELECT ( SELECT CONCAT(IF (c.NombreComercial IS NULL OR c.NombreComercial='',
CONCAT_WS(' ',c.Nombres,c.ApellidoPaterno,c.ApellidoMaterno),c.NombreComercial))
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

        $DatosCliente=Funciones::ObtenValor("SELECT *, m.Nombre as Municipio, e.Nombre as Estado, cr.Ruta  as Logotipo
    FROM Cliente c
    INNER JOIN DatosFiscalesCliente d ON (d.id=c.DatosFiscales)
    INNER JOIN EntidadFederativa e ON (e.id=d.EntidadFederativa)
    INNER JOIN Municipio m ON (m.id=d.Municipio)
    INNER JOIN CelaRepositorioC cr ON (cr.idRepositorio=c.Logotipo)
    WHERE c.id=". $cliente);

        $ExisteDescuento=Funciones::ObtenValor("SELECT (SELECT Nombre FROM TipoDescuentoPersona WHERE id=cd.idTipoDescuentoPersona) TipoDescuento FROM Padr_onCatastral p INNER JOIN ClienteDescuentos cd ON (cd.idTipoDescuentoPersona=p.TipoDescuento) WHERE CURDATE() BETWEEN FechaInicial AND FechaFinal AND cd.Tipo='Predial' AND cd.Cliente=p.Cliente AND p.id=$idPadron","TipoDescuento");


        $fecha = date("Y-m-d");
        if($DatosPadron->TipoPredio==10){
            //aqui marca error
            $tablaDatos=obtieneDatosLecturaCatastralNuevoZofemat($datosCotizacion->id, $fecha, $Cliente, $Usuario);
            #$tablaDatos=obtieneDatosLecturaCatastralNuevoPredial($datosCotizacion['id']);
        }else{

            $countConceptos=DB::select("SELECT COUNT(c.id) as total FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN Cotizaci_on c ON(c.id=cac.Cotizaci_on) WHERE c.Tipo=3 and c.Padr_on=".$idPadron." and cac.Estatus=0 and c.FechaLimite IS NULL GROUP BY cac.A_no,cac.Mes ORDER BY cac.A_no DESC,cac.Mes DESC");
            $mostrar2020=true;

            foreach($countConceptos as $concepto){

                //si hay cotizaciones 2019 no se muestra 2020
                if($concepto->total>0){
                    $mostrar2020=false;
                }
            }

            if($mostrar2020){

                $CotizacionesPredial= Funciones::ObtenValor("SELECT GROUP_CONCAT(ct.id) AS Cotizaciones FROM Cotizaci_on ct WHERE ct.Tipo=3 AND ct.Padr_on=$idPadron", "Cotizaciones");
            }else{

                $CotizacionesPredial= Funciones::ObtenValor("SELECT GROUP_CONCAT(ct.id) AS Cotizaciones FROM Cotizaci_on ct INNER JOIN ConceptoAdicionalesCotizaci_on cac ON ( ct.id = cac.Cotizaci_on )  WHERE ct.Tipo=3   AND cac.Estatus =0  and cac.A_no<=".date("Y")." AND ct.Padr_on=$idPadron", "Cotizaciones");

            }

            $tablaDatos=PredialController::obtieneDatosLecturaCatastralNuevoPredial($CotizacionesPredial, $fecha, $cliente,$ExisteDescuento,$idLectura);

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
    <link href="'.asset(Storage::url(env('RESOURCES').'bootstrap.min.css')) .'" rel="stylesheet">

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
                    <span style="font-size: 20px;>Orden de Pago </span> <br />
                    <span  style="font-size: 12px;"><b>Estado de Cuenta:</b><span  style="color:#ff0000; font-size: 20px;"> '.$DatosPadron->CuentaAnterior.'</span></span>
                </td>
            </tr>
            <tr>
                <td colspan="6" align="right"><img width="787px"  height="1px" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" </td></tr>
        </table>
        <table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="787px">'
            .(($DatosPadron->TipoPredio==10)?
                '<tr>

                <td width="50%" style="vertical-align:top;" v-align="top">
                <br /><b>Datos de la Concesi&oacute;n</b><br />
                <br />	<b>Propietario:</b> '.$DatosPadron->Propietario.'<br />

                    <b>Ubicaci&oacute;n:</b> '.$DatosPadron->Ubicaci_on.' '.$DatosPadron->paColonia.'<br />
                    <b>Localidad:</b> '.$DatosPadron->LocalidadPredio.'<br />
                    <b>Municipio:</b> '.$DatosPadron->MunicipioPredio.'<br />
                    <b>Clave SUINPAC:</b> '.$DatosPadron->ClaveCatastral .'<br />
                    <b>Concesi&oacute;n:</b> '.$DatosPadron->CuentaOK .' <br />
                    <b>Cuenta Zofemat:</b> '.$DatosPadron->CuentaAnterior .'<br />
                    <b>Tipo:</b> '.$DatosPadron->TipoConcesion .'<br />
                    <b>Superficie:</b> '.number_format(str_replace(",", "",  $DatosPadron->SuperficieTerreno),2) .'<br />
                </td>

                <td  width="50%" style="vertical-align:top;" v-align="top">
                    <br /><b>Datos de Facturaci&oacute;n</b><br />
                    <br /><b>Razon Social:</b> '.$DatosPadron->NombreORaz_onSocial.'<br />
                    <b>RFC:</b> '.$DatosPadron->RFC.'<br />
                    <b>Direcci&oacute;n:</b> '.$DatosPadron->Calle.' '.$DatosPadron->N_umeroExterior.'
                     '.$DatosPadron->Colonia.', C.P. '.$DatosPadron->C_odigoPostal.'<br />
                    '.$DatosPadron->Municipio.', '.$DatosPadron->Estado.'.<br />
                </td>
            </tr>':
                '<tr>

                <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
                <br /><b>Datos del Predio</b><br />
                <br />	<b>Propietario:</b> '.$DatosPadron->Propietario.'<br />'.$Copropietarios.
                '<b>Ubicaci&oacute;n:</b> '.$DatosPadron->Ubicaci_on.' '.$DatosPadron->paColonia.'<br />
                    <b>Localidad:</b> '.$DatosPadron->LocalidadPredio.'<br />
                    <b>Municipio:</b> '.$DatosPadron->MunicipioPredio.'<br />
                    <b>Clave SUINPAC:</b> '.$DatosPadron->ClaveCatastral .'<br />
                    <b>Clave Catastral:</b> '.$DatosPadron->CuentaOK.' <br />
                    <b>Cuenta Predial:</b> '.$DatosPadron->CuentaAnterior .'<br />
                </td>

                <td colspan="3"  width="50%" style="vertical-align:top;" v-align="top">
                <br /><b>Datos de Facturaci&oacute;n</b><br />
                <br /><b>Razon Social:</b> '.$DatosPadron->NombreORaz_onSocial.'<br />
                    <b>RFC:</b> '.$DatosPadron->RFC.'<br />
                    <b>Direcci&oacute;n:</b> '.$DatosPadron->Calle.' '.$DatosPadron->N_umeroExterior.'
                     '.$DatosPadron->Colonia.', C.P. '.$DatosPadron->C_odigoPostal.'<br />
                    '.$DatosPadron->Municipio.', '.$DatosPadron->Estado.'.<br />
                </td>
            </tr>').
            '<tr>
                <td colspan="6">
                    <img width="787px" height="1px" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'"><br /> &nbsp;
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
                                                    ' .(($DatosPadron->TipoPredio==10)?'<td width="4%" align="center"><b>Bimestre</b></td>':'').
            '<td width="12%" align="center"><b>Base</b></td>
                            <td width="9%" align="center"><b>'.(($DatosPadron->TipoPredio==10)?'Derecho':'Predial').'</b></td>
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


//return $htmlGlobal;
        include( app_path() . '/Libs/Wkhtmltopdf.php' );
        try {
            $nombre = uniqid() . "_estadoCuenta" . $idPadron;
            #$wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'page_width'=>120,'page_height'=>200,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
            $wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'FooterStyleLeft'=>'Grupo Piacza - SUINPAC Contable', 'FooterStyleCenter'=>'Pag. [page] de [toPage]'));

            // $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'margins' => ['top' => 10, 'left' => 10,'right'=>10,'bottom'=>10]));
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




    public function  obtenerEstadoDeCuentaPredialCopia(Request $request){
        $cliente=$request->Cliente;
        $idPadron=$request->IdPadron;
        $idLectura=$request->IdLectura;
        Funciones::selecionarBase( $cliente);
        $DatosPadron= Funciones::ObtenValor("SELECT *,  pa.Cuenta as CuentaOK, (SELECT Nombre FROM Municipio WHERE id=d.Municipio) as Municipio,
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


        $Copropietarios="";
        $ConsultaCopropietarios = "SELECT ( SELECT CONCAT(IF (c.NombreComercial IS NULL OR c.NombreComercial='',
CONCAT_WS(' ',c.Nombres,c.ApellidoPaterno,c.ApellidoMaterno),c.NombreComercial))
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

        $DatosCliente=Funciones::ObtenValor("SELECT *, m.Nombre as Municipio, e.Nombre as Estado, cr.Ruta  as Logotipo
    FROM Cliente c
    INNER JOIN DatosFiscalesCliente d ON (d.id=c.DatosFiscales)
    INNER JOIN EntidadFederativa e ON (e.id=d.EntidadFederativa)
    INNER JOIN Municipio m ON (m.id=d.Municipio)
    INNER JOIN CelaRepositorioC cr ON (cr.idRepositorio=c.Logotipo)
    WHERE c.id=". $cliente);

        $ExisteDescuento=Funciones::ObtenValor("SELECT (SELECT Nombre FROM TipoDescuentoPersona WHERE id=cd.idTipoDescuentoPersona) TipoDescuento FROM Padr_onCatastral p INNER JOIN ClienteDescuentos cd ON (cd.idTipoDescuentoPersona=p.TipoDescuento) WHERE CURDATE() BETWEEN FechaInicial AND FechaFinal AND cd.Tipo='Predial' AND cd.Cliente=p.Cliente AND p.id=$idPadron","TipoDescuento");


        $fecha = date("Y-m-d");
        if($DatosPadron->TipoPredio==10){
            //aqui marca error
            $tablaDatos=obtieneDatosLecturaCatastralNuevoZofematCopia($datosCotizacion->id, $fecha, $Cliente, $Usuario);
            #$tablaDatos=obtieneDatosLecturaCatastralNuevoPredial($datosCotizacion['id']);
        }else{

            $countConceptos=DB::select("SELECT COUNT(c.id) as total FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN Cotizaci_on c ON(c.id=cac.Cotizaci_on) WHERE c.Tipo=3 and c.Padr_on=".$idPadron." and cac.Estatus=0 and c.FechaLimite IS NULL GROUP BY cac.A_no,cac.Mes ORDER BY cac.A_no DESC,cac.Mes DESC");
            $mostrar2020=true;

            foreach($countConceptos as $concepto){

                //si hay cotizaciones 2019 no se muestra 2020
                if($concepto->total>0){
                    $mostrar2020=false;
                }
            }

            if($mostrar2020){

                $CotizacionesPredial= Funciones::ObtenValor("SELECT GROUP_CONCAT(ct.id) AS Cotizaciones FROM Cotizaci_on ct WHERE ct.Tipo=3 AND ct.Padr_on=$idPadron", "Cotizaciones");
            }else{

                $CotizacionesPredial= Funciones::ObtenValor("SELECT GROUP_CONCAT(ct.id) AS Cotizaciones FROM Cotizaci_on ct INNER JOIN ConceptoAdicionalesCotizaci_on cac ON ( ct.id = cac.Cotizaci_on )  WHERE ct.Tipo=3   AND cac.Estatus =0  and cac.A_no<=".date("Y")." AND ct.Padr_on=$idPadron", "Cotizaciones");

            }

            $tablaDatos=PredialController::obtieneDatosLecturaCatastralNuevoPredialCopia($CotizacionesPredial, $fecha, $cliente,$ExisteDescuento,$idLectura);

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
    <link href="'.asset(Storage::url(env('RESOURCES').'bootstrap.min.css')) .'" rel="stylesheet">

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
                    <span style="font-size: 20px;>Orden de Pago </span> <br />
                    <span  style="font-size: 12px;"><b>Estado de Cuenta:</b><span  style="color:#ff0000; font-size: 20px;"> '.$DatosPadron->CuentaAnterior.'</span></span>
                </td>
            </tr>
            <tr>
                <td colspan="6" align="right"><img width="787px"  height="1px" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" </td></tr>
        </table>
        <table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="787px">'
            .(($DatosPadron->TipoPredio==10)?
                '<tr>

                <td width="50%" style="vertical-align:top;" v-align="top">
                <br /><b>Datos de la Concesi&oacute;n</b><br />
                <br />	<b>Propietario:</b> '.$DatosPadron->Propietario.'<br />

                    <b>Ubicaci&oacute;n:</b> '.$DatosPadron->Ubicaci_on.' '.$DatosPadron->paColonia.'<br />
                    <b>Localidad:</b> '.$DatosPadron->LocalidadPredio.'<br />
                    <b>Municipio:</b> '.$DatosPadron->MunicipioPredio.'<br />
                    <b>Clave SUINPAC:</b> '.$DatosPadron->ClaveCatastral .'<br />
                    <b>Concesi&oacute;n:</b> '.$DatosPadron->CuentaOK .' <br />
                    <b>Cuenta Zofemat:</b> '.$DatosPadron->CuentaAnterior .'<br />
                    <b>Tipo:</b> '.$DatosPadron->TipoConcesion .'<br />
                    <b>Superficie:</b> '.number_format(str_replace(",", "",  $DatosPadron->SuperficieTerreno),2) .'<br />
                </td>

                <td  width="50%" style="vertical-align:top;" v-align="top">
                    <br /><b>Datos de Facturaci&oacute;n</b><br />
                    <br /><b>Razon Social:</b> '.$DatosPadron->NombreORaz_onSocial.'<br />
                    <b>RFC:</b> '.$DatosPadron->RFC.'<br />
                    <b>Direcci&oacute;n:</b> '.$DatosPadron->Calle.' '.$DatosPadron->N_umeroExterior.'
                     '.$DatosPadron->Colonia.', C.P. '.$DatosPadron->C_odigoPostal.'<br />
                    '.$DatosPadron->Municipio.', '.$DatosPadron->Estado.'.<br />
                </td>
            </tr>':
                '<tr>

                <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
                <br /><b>Datos del Predio</b><br />
                <br />	<b>Propietario:</b> '.$DatosPadron->Propietario.'<br />'.$Copropietarios.
                '<b>Ubicaci&oacute;n:</b> '.$DatosPadron->Ubicaci_on.' '.$DatosPadron->paColonia.'<br />
                    <b>Localidad:</b> '.$DatosPadron->LocalidadPredio.'<br />
                    <b>Municipio:</b> '.$DatosPadron->MunicipioPredio.'<br />
                    <b>Clave SUINPAC:</b> '.$DatosPadron->ClaveCatastral .'<br />
                    <b>Clave Catastral:</b> '.$DatosPadron->CuentaOK.' <br />
                    <b>Cuenta Predial:</b> '.$DatosPadron->CuentaAnterior .'<br />
                </td>

                <td colspan="3"  width="50%" style="vertical-align:top;" v-align="top">
                <br /><b>Datos de Facturaci&oacute;n</b><br />
                <br /><b>Razon Social:</b> '.$DatosPadron->NombreORaz_onSocial.'<br />
                    <b>RFC:</b> '.$DatosPadron->RFC.'<br />
                    <b>Direcci&oacute;n:</b> '.$DatosPadron->Calle.' '.$DatosPadron->N_umeroExterior.'
                     '.$DatosPadron->Colonia.', C.P. '.$DatosPadron->C_odigoPostal.'<br />
                    '.$DatosPadron->Municipio.', '.$DatosPadron->Estado.'.<br />
                </td>
            </tr>').
            '<tr>
                <td colspan="6">
                    <img width="787px" height="1px" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'"><br /> &nbsp;
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
                                                    ' .(($DatosPadron->TipoPredio==10)?'<td width="4%" align="center"><b>Bimestre</b></td>':'').
            '<td width="12%" align="center"><b>Base</b></td>
                            <td width="9%" align="center"><b>'.(($DatosPadron->TipoPredio==10)?'Derecho':'Predial').'</b></td>
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


//return $htmlGlobal;
        include( app_path() . '/Libs/Wkhtmltopdf.php' );
        try {
            $nombre = uniqid() . "_estadoCuenta" . $idPadron;
            #$wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'page_width'=>120,'page_height'=>200,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
            $wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'FooterStyleLeft'=>'Grupo Piacza - SUINPAC Contable', 'FooterStyleCenter'=>'Pag. [page] de [toPage]'));

            // $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'margins' => ['top' => 10, 'left' => 10,'right'=>10,'bottom'=>10]));
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


    public function obtieneDatosLecturaCatastralNuevoZofemat($idCotizacion, $fechaCotizacion, $Cliente, $Usuario){

            $configuracionGenerarRecargos = PredialController::ObtenValorPorClave("GenerarRecargos", $Cliente);
            if(!$configuracionGenerarRecargos==1)
                $configuracionGenerarRecargos = 0;

            $configuracionGenerarActualizaciones= PredialController::ObtenValorPorClave("GenerarActualizaciones", $Cliente);
            if(!$configuracionGenerarActualizaciones==1)
                $configuracionGenerarActualizaciones = 0;

            $lecturasCons="SELECT
            pal.id AS palid,
            pap.id AS IdPadron,
            pap.SuperficieConstrucci_on,
            pap.SuperficieTerreno,
            pap.CuentaAnterior,
            pap.Cliente,
            pal.id AS idLectura,
            pal.A_no,
            pal.Mes,
            pal.GastosEjecucion,
            pal.GastosEmbargo,
            pal.OtrosGastos,
            pal.Multas,
            sum( CAC.Importe ) AS ValorHistorial,
            sum( CAC.Importe ) * (
            SELECT COALESCE
                ( Porcentaje, 0 )
            FROM
                ClienteDescuentos
            WHERE
                Ejercicio = pal.A_no
                AND Cliente = pap.Cliente
                AND CURDATE( ) BETWEEN FechaInicial
                AND FechaFinal
                LIMIT 0,
                1
            ) / 100 AS Descuento
            FROM
            Padr_onCatastral pap
            INNER JOIN Padr_onCatastralHistorial pal ON (pap.id=pal.Padr_onCatastral)
            INNER JOIN ConceptoAdicionalesCotizaci_on CAC ON (
                CAC.A_no = pal.A_no
                AND CAC.Mes = pal.Mes
                AND CAC.Padr_on = pal.Padr_onCatastral
                AND CAC.ConceptoAdicionales = pap.ConceptoCobroCaja
            )
            WHERE
            CAC.Cotizaci_on = ".$idCotizacion."
            AND pap.Cliente = ".$Cliente."
            GROUP BY
            pal.A_no,
            pal.Mes
            ORDER BY
            pal.A_no DESC,
            pal.Mes DESC";

            $DatosXML=Funciones::ObtenValor("SELECT * FROM XMLIngreso WHERE idCotizaci_on=".$idCotizacion);
            $DatosExtra=json_decode($DatosXML->DatosExtra, true);
            $descuentoManual=isset($DatosExtra->Descuento ) ? floatval(str_replace(",", "",  $DatosExtra->Descuento)) : 0;


            $Lectura= Funciones::ObtenValor($lecturasCons);

        $DatosLecturaActual=ObtenValor("SELECT *, ccc.id as cccid, ca.Importe as ImporteUnitario,  pa.id as paid, pl.Consumo as ValorHistorial, pl.Status as EstatusPagado, pl.id as plid,  ca.BaseCalculo, pl.A_no, pl.Mes
        FROM Padr_onCatastral pa
        INNER JOIN Padr_onCatastralHistorial pl ON (pa.id=pl.Padr_onCatastral)
        INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=pa.ConceptoCobroCaja)
        INNER JOIN ConceptoRetencionesAdicionales cra ON ( cra.Concepto= ccc.id )
        INNER JOIN ConceptoRetencionesAdicionalesCliente crac ON ( cra.id = crac.ConceptoRetencionesAdicionales  AND crac.Cliente=pa.Cliente)
        INNER JOIN ConceptoAdicionales ca ON (ca.ConceptoRetencionesAdicionalesCliente=crac.id)
        WHERE
        pl.id=".$Lectura->palid."
        ORDER BY pl.A_no DESC
        LIMIT 0, 1");

#precode($lecturasCons,1,1);
        $resultados="";
        $sumapredial=0;
        $sumaAct=0;
        $sumaRec=0;
        $sumaMulta=0;
        $sumaGastosEjecucion=0;
        $sumaGastosEmbargo=0;
        $sumaOtrosGastos=0;
        $sumaDescuentos=0;
        $sumaTotalAnio=0;

            $FechasVencimiento = ['01-18','01-31',
                                    '02-18','02-28',
                                    '03-18','03-31',
                                    '04-18','04-30',
                                    '05-18','05-31',
                                    '06-18','06-30',
                                    '07-18','07-31',
                                    '08-18','08-31',
                                    '09-18','09-30',
                                    '10-18','10-31',
                                    '11-18','11-30',
                                    '12-18','12-31'];



            $VencimientoEdoCuenta="";
            foreach ($FechasVencimiento as $valor){
                $Fecha="";
                $fechaVencimiento = date('Y-m-d', strtotime(date('Y-').$valor));

                if(date('D',strtotime($fechaVencimiento))=="Sat"){
                    $fechaVencimiento = strtotime ( '-1 day' , strtotime ( $fechaVencimiento ) ) ;
                    $fechaVencimiento = date ( 'Y-m-d' , $fechaVencimiento );
                }
                if(date('D',strtotime($fechaVencimiento))=="Sun"){
                    $fechaVencimiento = strtotime ( '-2 day' , strtotime ( $fechaVencimiento ) ) ;
                    $fechaVencimiento = date ( 'Y-m-d' , $fechaVencimiento );
                }

                if( strtotime($fechaVencimiento) >= strtotime(date("Y-m-d")) ){
                   $VencimientoEdoCuenta = $fechaVencimiento;
                   break;
                }
            }

    $ejecutaLect=DB::select($lecturasCons);
return $ejecutaLect;
    while($lectr=$ejecutaLect->fetch_assoc()){

        #precode($lectr,1,1);
    /**********************************************************************************************************/

        $ValorCatastral =ImporteTotalDelPredio($DatosLecturaActual['paid'], $lectr['A_no'], 1);

        $ValorCatastral['TipoConstruccionValor']=($ValorCatastral['TipoConstruccionValor']=="NULL"? NULL: $ValorCatastral['TipoConstruccionValor']);

        $datosPadron=ObtenValor("SELECT * FROM Padr_onCatastral pc WHERE pc.id=".$DatosLecturaActual['paid']);

        $datosConstruccionDetalle="SELECT * FROM Padr_onConstruccionDetalle WHERE idPadron=".$DatosLecturaActual['paid'];

        $ejecuta=$Conexion->query($datosConstruccionDetalle);
        $cont=0;
        $datosConsArr=array();
        $consValor=0;
        while($RegistroDetalle=$ejecuta->fetch_assoc()){

            $datosConsArr[]=$RegistroDetalle;

            $tipoConstValor= ObtenValor("SELECT tce.Importe as Importe FROM TipoConstrucci_onValores tc INNER JOIN TipoConstrucci_onValoresEjercicioFiscal tce ON (tce.idTipoConstrucci_onValores=tc.id AND tce.EjercicioFiscal=".$lectr['A_no'].") WHERE Cliente=". $Cliente ." AND idTipoConstrucci_on=".$RegistroDetalle['TipoConstruci_on'], "Importe") ;

            $RegistroDetalle['Costo'] = $tipoConstValor * $RegistroDetalle['SuperficieConstrucci_on'] * ($RegistroDetalle['Indiviso']/100);

            $consValor+=$RegistroDetalle['Costo'];
        }
        $datosJson = json_encode( $datosConsArr) ;



        $datosDeConcepto=ObtenValor("SELECT DISTINCT c3.Importe as c3Importe, c3.id as c3id
            FROM ConceptoCobroCaja c
            INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto )
            INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales )
            INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente )
            WHERE c2.AplicaEnSubtotal=0 AND c3.Cliente=".$Cliente." AND
            c3.EjercicioFiscal=".$lectr['A_no']." AND
            c2.Cliente=".$Cliente." AND c.id = ".$datosPadron['ConceptoCobroCaja']."");
        #precode($datosDeConcepto,1);
        $costoTerrenoEnAnio=(str_replace(",", "",  ($ValorCatastral['TipoPredioValor'])) *($datosPadron['Indiviso']/100)*str_replace(",", "",  ($datosPadron['SuperficieTerreno'])) );

        $datosConceptoAdicional=ObtieneAdicionalesPredialAntesDeHistorial($datosPadron['ConceptoCobroCaja'],  $Cliente, ($costoTerrenoEnAnio+$consValor), $datosDeConcepto['c3Importe'], $lectr['A_no'] );
        #precode($datosConceptoAdicional,1);
        $totalDelAno=$datosConceptoAdicional['SumaCompleta'];
        #precode($totalDelAno,1,1);
        //ciclo para agregar todos los historiales por anio
        #$existe=ObtenValor("SELECT id FROM Padr_onCatastralHistorial WHERE A_no=".$i." AND Padr_onCatastral=".$DatosLecturaActual['paid'], "id");
        #if($existe=="NULL"){

            $divTotalDelAnio=round($totalDelAno, 2);
            $divSuperficieTerreno=$datosPadron['SuperficieTerreno'];
            $divSuperficieConstruccion=$datosPadron['SuperficieConstrucci_on'];
            //$divCostroTeneroEnAnio= $costoTerrenoEnAnio;
            //$divConsValor= $consValor;
            // echo $lectr['A_no']."-01-01 _ ". $lectr['ValorHistorial']."<br />";
               //  $montopredial = $totalDelAno; //$lectr['ValorHistorial'];
           $montopredial = $lectr['ValorHistorial'];

            $mes=($lectr['Mes']*2)+1;
            $anio=$lectr['A_no'];
           if(intval($mes)>12){
                   $mes=1;
                   $anio=$anio+1;
               }

           $mes=str_pad($mes, 2, "0", STR_PAD_LEFT);

          //  $dia = date("d",(mktime(0,0,0,$mes+1,1,$lectr['A_no'])-1));
            $dia=18;
            /**/
            $fechaVencimiento= $anio."-".$mes."-".$dia;

            if(date('D',intval($fechaVencimiento))=="Sat"){
               $dia=$dia+2;
            }
            if(date('D',intval($fechaVencimiento))=="Sun"){
                $dia=$dia+1;
            }

            $fechaVencimiento= $anio."-".$mes."-".$dia;

                $fecha_actual =  strtotime(date($fechaCotizacion." H:i:00"));
                $fecha_entrada = strtotime(date($fechaVencimiento." H:i:00"));

                if($fecha_actual > $fecha_entrada){
                    $recargosOK = floatval( str_replace(",","", number_format ( CalculoRecargosFechaZofemat($fechaVencimiento, $montopredial, NULL, $Cliente ) , 2 ) ) );
                    $actualizacionesOK = floatval( str_replace(",","", number_format ( CalculoActualizacion($fechaVencimiento, $montopredial), 2 ) ) );
                }else{
                    $recargosOK		   = 0;
                    $actualizacionesOK = 0;
                }

                //Configuracion del cliente para verificar si tiene habilidada la opcion de generar Act. y Rec.
                if($configuracionGenerarRecargos==0){
                     $recargosOK = 0;
                }
                if($configuracionGenerarActualizaciones==0){
                     $actualizacionesOK = 0;
                }

            $montoBase=($costoTerrenoEnAnio + $consValor);

            $Descuentos=0;
            $totalAnio=$montopredial + $actualizacionesOK + $recargosOK + $lectr['Multas'] + $lectr['GastosEjecucion'] + $lectr['GastosEmbargo'] + $lectr['OtrosGastos'] - $Descuentos ;
            $sumapredial+=$montopredial;
            $sumaAct+=$actualizacionesOK;
            $sumaRec+=$recargosOK;
            $sumaMulta+=$lectr['Multas'];
            $sumaGastosEjecucion+=$lectr['GastosEjecucion'];
            $sumaGastosEmbargo+=$lectr['GastosEmbargo'];
            $sumaOtrosGastos+=$lectr['OtrosGastos'];
            $sumaDescuentos+=$Descuentos;
            $sumaTotalAnio+=$totalAnio;
                            $arr['Total']= $sumaTotalAnio;
            /**********************************************************************************************************/
    }


    $arr['Vigencia']="

                   <tr>
                      <td colspan='11'><br />
                          <img width='787px' height='1px' src='".AnfitrionURL()."bootstrap/img/barraColores.png'>
                      </td>
                  </tr>

                  <table style='padding:-35px 0 0 0;margin:-10px 0 0 0;' border='0'  width='100%'>



                                            <tr>
                        <td colspan='11' align='right'> <br />
                                                            <span style='font-size:12px;'>Expedici&oacute;n: ".date('d/m/Y H:i:s')."</span> -- <span style='font-size:12px;'>Vencimiento: ".date('d/m/Y',strtotime($VencimientoEdoCuenta))."</span> <span style='font-size:12px;'>".' '.$Usuario."</span>
                        </td>
                    </tr>

                    <tr>
                        <td colspan='11' align='right'>

                        </td>
                    </tr>



                  ";
    return $arr;
}


    public function obtieneDatosLecturaCatastralNuevoZofematCopia($idCotizacion, $fechaCotizacion, $Cliente, $Usuario){

        $configuracionGenerarRecargos = PredialController::ObtenValorPorClave("GenerarRecargos", $Cliente);
        if(!$configuracionGenerarRecargos==1)
            $configuracionGenerarRecargos = 0;

        $configuracionGenerarActualizaciones= PredialController::ObtenValorPorClave("GenerarActualizaciones", $Cliente);
        if(!$configuracionGenerarActualizaciones==1)
            $configuracionGenerarActualizaciones = 0;

        $montoBase = 0;
        $DescuentoVulnerable=false;
        $TipoPredio=0;
        $Concepto=0;

        $lecturasCons="SELECT
            pal.id AS palid,
            pap.id AS IdPadron,
            pap.SuperficieConstrucci_on,
            pap.SuperficieTerreno,
            pap.CuentaAnterior,
            pap.Cliente,
            pal.id AS idLectura,
            pal.A_no,
            pal.Mes,
            pal.GastosEjecucion,
            pal.GastosEmbargo,
            pal.OtrosGastos,
            pal.Multas,
            sum( CAC.Importe ) AS ValorHistorial,
            sum( CAC.Importe ) * (
            SELECT COALESCE
                ( Porcentaje, 0 )
            FROM
                ClienteDescuentos
            WHERE
                Ejercicio = pal.A_no
                AND Cliente = pap.Cliente
                AND CURDATE( ) BETWEEN FechaInicial
                AND FechaFinal
                LIMIT 0,
                1
            ) / 100 AS Descuento
            FROM
            Padr_onCatastral pap
            INNER JOIN Padr_onCatastralHistorial pal ON (pap.id=pal.Padr_onCatastral)
            INNER JOIN ConceptoAdicionalesCotizaci_on CAC ON (
                CAC.A_no = pal.A_no
                AND CAC.Mes = pal.Mes
                AND CAC.Padr_on = pal.Padr_onCatastral
                AND CAC.ConceptoAdicionales = pap.ConceptoCobroCaja
            )
            WHERE
            CAC.Cotizaci_on = ".$idCotizacion."
            AND pap.Cliente = ".$Cliente."
            GROUP BY
            pal.A_no,
            pal.Mes
            ORDER BY
            pal.A_no DESC,
            pal.Mes DESC";

        $DatosXML=Funciones::ObtenValor("SELECT * FROM XMLIngreso WHERE idCotizaci_on=".$idCotizacion);
        $DatosExtra=json_decode($DatosXML->DatosExtra, true);
        $descuentoManual=isset($DatosExtra->Descuento ) ? floatval(str_replace(",", "",  $DatosExtra->Descuento)) : 0;


        $Lectura= Funciones::ObtenValor($lecturasCons);

        $DatosLecturaActual=ObtenValor("SELECT *, ccc.id as cccid, ca.Importe as ImporteUnitario,  pa.id as paid, pl.Consumo as ValorHistorial, pl.Status as EstatusPagado, pl.id as plid,  ca.BaseCalculo, pl.A_no, pl.Mes
        FROM Padr_onCatastral pa
        INNER JOIN Padr_onCatastralHistorial pl ON (pa.id=pl.Padr_onCatastral)
        INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=pa.ConceptoCobroCaja)
        INNER JOIN ConceptoRetencionesAdicionales cra ON ( cra.Concepto= ccc.id )
        INNER JOIN ConceptoRetencionesAdicionalesCliente crac ON ( cra.id = crac.ConceptoRetencionesAdicionales  AND crac.Cliente=pa.Cliente)
        INNER JOIN ConceptoAdicionales ca ON (ca.ConceptoRetencionesAdicionalesCliente=crac.id)
        WHERE
        pl.id=".$Lectura->palid."
        ORDER BY pl.A_no DESC
        LIMIT 0, 1");

#precode($lecturasCons,1,1);
        $resultados="";
        $sumapredial=0;
        $sumaAct=0;
        $sumaRec=0;
        $sumaMulta=0;
        $sumaGastosEjecucion=0;
        $sumaGastosEmbargo=0;
        $sumaOtrosGastos=0;
        $sumaDescuentos=0;
        $sumaTotalAnio=0;

        $FechasVencimiento = ['01-18','01-31',
            '02-18','02-28',
            '03-18','03-31',
            '04-18','04-30',
            '05-18','05-31',
            '06-18','06-30',
            '07-18','07-31',
            '08-18','08-31',
            '09-18','09-30',
            '10-18','10-31',
            '11-18','11-30',
            '12-18','12-31'];



        $VencimientoEdoCuenta="";
        foreach ($FechasVencimiento as $valor){
            $Fecha="";
            $fechaVencimiento = date('Y-m-d', strtotime(date('Y-').$valor));

            if(date('D',strtotime($fechaVencimiento))=="Sat"){
                $fechaVencimiento = strtotime ( '-1 day' , strtotime ( $fechaVencimiento ) ) ;
                $fechaVencimiento = date ( 'Y-m-d' , $fechaVencimiento );
            }
            if(date('D',strtotime($fechaVencimiento))=="Sun"){
                $fechaVencimiento = strtotime ( '-2 day' , strtotime ( $fechaVencimiento ) ) ;
                $fechaVencimiento = date ( 'Y-m-d' , $fechaVencimiento );
            }

            if( strtotime($fechaVencimiento) >= strtotime(date("Y-m-d")) ){
                $VencimientoEdoCuenta = $fechaVencimiento;
                break;
            }
        }

        $ejecutaLect=DB::select($lecturasCons);
        return $ejecutaLect;
        while($lectr=$ejecutaLect->fetch_assoc()){

            #precode($lectr,1,1);
            /**********************************************************************************************************/

            $ValorCatastral =ImporteTotalDelPredio($DatosLecturaActual['paid'], $lectr['A_no'], 1);

            $ValorCatastral['TipoConstruccionValor']=($ValorCatastral['TipoConstruccionValor']=="NULL"? NULL: $ValorCatastral['TipoConstruccionValor']);

            $datosPadron=ObtenValor("SELECT * FROM Padr_onCatastral pc WHERE pc.id=".$DatosLecturaActual['paid']);

            $datosConstruccionDetalle="SELECT * FROM Padr_onConstruccionDetalle WHERE idPadron=".$DatosLecturaActual['paid'];

            $ejecuta=$Conexion->query($datosConstruccionDetalle);
            $cont=0;
            $datosConsArr=array();
            $consValor=0;
            while($RegistroDetalle=$ejecuta->fetch_assoc()){

                $datosConsArr[]=$RegistroDetalle;

                $tipoConstValor= ObtenValor("SELECT tce.Importe as Importe FROM TipoConstrucci_onValores tc INNER JOIN TipoConstrucci_onValoresEjercicioFiscal tce ON (tce.idTipoConstrucci_onValores=tc.id AND tce.EjercicioFiscal=".$lectr['A_no'].") WHERE Cliente=". $Cliente ." AND idTipoConstrucci_on=".$RegistroDetalle['TipoConstruci_on'], "Importe") ;

                $RegistroDetalle['Costo'] = $tipoConstValor * $RegistroDetalle['SuperficieConstrucci_on'] * ($RegistroDetalle['Indiviso']/100);

                $consValor+=$RegistroDetalle['Costo'];
            }
            $datosJson = json_encode( $datosConsArr) ;



            $datosDeConcepto=ObtenValor("SELECT DISTINCT c3.Importe as c3Importe, c3.id as c3id
            FROM ConceptoCobroCaja c
            INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto )
            INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales )
            INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente )
            WHERE c2.AplicaEnSubtotal=0 AND c3.Cliente=".$Cliente." AND
            c3.EjercicioFiscal=".$lectr['A_no']." AND
            c2.Cliente=".$Cliente." AND c.id = ".$datosPadron['ConceptoCobroCaja']."");
            #precode($datosDeConcepto,1);
            $costoTerrenoEnAnio=(str_replace(",", "",  ($ValorCatastral['TipoPredioValor'])) *($datosPadron['Indiviso']/100)*str_replace(",", "",  ($datosPadron['SuperficieTerreno'])) );

            $datosConceptoAdicional=ObtieneAdicionalesPredialAntesDeHistorial($datosPadron['ConceptoCobroCaja'],  $Cliente, ($costoTerrenoEnAnio+$consValor), $datosDeConcepto['c3Importe'], $lectr['A_no'] );
            #precode($datosConceptoAdicional,1);
            $totalDelAno=$datosConceptoAdicional['SumaCompleta'];
            #precode($totalDelAno,1,1);
            //ciclo para agregar todos los historiales por anio
            #$existe=ObtenValor("SELECT id FROM Padr_onCatastralHistorial WHERE A_no=".$i." AND Padr_onCatastral=".$DatosLecturaActual['paid'], "id");
            #if($existe=="NULL"){

            $divTotalDelAnio=round($totalDelAno, 2);
            $divSuperficieTerreno=$datosPadron['SuperficieTerreno'];
            $divSuperficieConstruccion=$datosPadron['SuperficieConstrucci_on'];
            //$divCostroTeneroEnAnio= $costoTerrenoEnAnio;
            //$divConsValor= $consValor;
            // echo $lectr['A_no']."-01-01 _ ". $lectr['ValorHistorial']."<br />";
            //  $montopredial = $totalDelAno; //$lectr['ValorHistorial'];
            $montopredial = $lectr['ValorHistorial'];

            $mes=($lectr['Mes']*2)+1;
            $anio=$lectr['A_no'];
            if(intval($mes)>12){
                $mes=1;
                $anio=$anio+1;
            }

            $mes=str_pad($mes, 2, "0", STR_PAD_LEFT);

            //  $dia = date("d",(mktime(0,0,0,$mes+1,1,$lectr['A_no'])-1));
            $dia=18;
            /**/
            $fechaVencimiento= $anio."-".$mes."-".$dia;

            if(date('D',intval($fechaVencimiento))=="Sat"){
                $dia=$dia+2;
            }
            if(date('D',intval($fechaVencimiento))=="Sun"){
                $dia=$dia+1;
            }

            $fechaVencimiento= $anio."-".$mes."-".$dia;

            $fecha_actual =  strtotime(date($fechaCotizacion." H:i:00"));
            $fecha_entrada = strtotime(date($fechaVencimiento." H:i:00"));

            if($fecha_actual > $fecha_entrada){
                $recargosOK = floatval( str_replace(",","", number_format ( CalculoRecargosFechaZofemat($fechaVencimiento, $montopredial, NULL, $Cliente ) , 2 ) ) );
                $actualizacionesOK = floatval( str_replace(",","", number_format ( CalculoActualizacion($fechaVencimiento, $montopredial), 2 ) ) );
            }else{
                $recargosOK		   = 0;
                $actualizacionesOK = 0;
            }

            //Configuracion del cliente para verificar si tiene habilidada la opcion de generar Act. y Rec.
            if($configuracionGenerarRecargos==0){
                $recargosOK = 0;
            }
            if($configuracionGenerarActualizaciones==0){
                $actualizacionesOK = 0;
            }

            $montoBase=($costoTerrenoEnAnio + $consValor);

            $Descuentos=0;
            $totalAnio=$montopredial + $actualizacionesOK + $recargosOK + $lectr['Multas'] + $lectr['GastosEjecucion'] + $lectr['GastosEmbargo'] + $lectr['OtrosGastos'] - $Descuentos ;
            $sumapredial+=$montopredial;
            $sumaAct+=$actualizacionesOK;
            $sumaRec+=$recargosOK;
            $sumaMulta+=$lectr['Multas'];
            $sumaGastosEjecucion+=$lectr['GastosEjecucion'];
            $sumaGastosEmbargo+=$lectr['GastosEmbargo'];
            $sumaOtrosGastos+=$lectr['OtrosGastos'];
            $sumaDescuentos+=$Descuentos;
            $sumaTotalAnio+=$totalAnio;
            $arr['Total']= $sumaTotalAnio;
            /**********************************************************************************************************/
        }


        $arr['Vigencia']="

                   <tr>
                      <td colspan='11'><br />
                          <img width='787px' height='1px' src='".AnfitrionURL()."bootstrap/img/barraColores.png'>
                      </td>
                  </tr>

                  <table style='padding:-35px 0 0 0;margin:-10px 0 0 0;' border='0'  width='100%'>



                                            <tr>
                        <td colspan='11' align='right'> <br />
                                                            <span style='font-size:12px;'>Expedici&oacute;n: ".date('d/m/Y H:i:s')."</span> -- <span style='font-size:12px;'>Vencimiento: ".date('d/m/Y',strtotime($VencimientoEdoCuenta))."</span> <span style='font-size:12px;'>".' '.$Usuario."</span>
                        </td>
                    </tr>

                    <tr>
                        <td colspan='11' align='right'>

                        </td>
                    </tr>



                  ";
        return $arr;
    }


    function obtieneDatosLecturaCatastralNuevoPredialCopia($idCotizacion, $fechaCotizacion, $Cliente,$ExisteDescuento,$Lectura){


        $montoBase = 0;
        $IdCliente=$Cliente;
        $configuracionGenerarRecargos = PredialController::ObtenValorPorClave("GenerarRecargos", $Cliente);
        if(!$configuracionGenerarRecargos==1)
            $configuracionGenerarRecargos = 0;

        $configuracionGenerarActualizaciones= PredialController::ObtenValorPorClave("GenerarActualizaciones", $Cliente);
        if(!$configuracionGenerarActualizaciones==1)
            $configuracionGenerarActualizaciones = 0;
        $montoBase = 0;
        $DescuentoVulnerable=false;
        $TipoPredio=0;
        $Concepto=0;
        $DatosLecturaActual=Funciones::ObtenValor("SELECT *, ccc.id as cccid, ca.Importe as ImporteUnitario,  pa.id as paid, pl.Consumo as ValorHistorial, pl.Status as EstatusPagado, pl.id as plid,  ca.BaseCalculo, pl.A_no, pl.Mes
			FROM Padr_onCatastral pa
			INNER JOIN Padr_onCatastralHistorial pl ON (pa.id=pl.Padr_onCatastral)
			INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=pa.ConceptoCobroCaja)
			INNER JOIN ConceptoRetencionesAdicionales cra ON ( cra.Concepto= ccc.id )
			INNER JOIN ConceptoRetencionesAdicionalesCliente crac ON ( cra.id = crac.ConceptoRetencionesAdicionales  AND crac.Cliente=pa.Cliente)
			INNER JOIN ConceptoAdicionales ca ON (ca.ConceptoRetencionesAdicionalesCliente=crac.id)
			WHERE
			pl.id=".$Lectura."
			ORDER BY pl.A_no DESC
			LIMIT 0, 1");

        //$anioCondicion=' AND pal.A_no<2020 ';
        $anioCondicion= '';
  /*
        $ConcionDescuentoClientes="IF(pap.TipoDescuento >0, (sum(pal.Consumo)*(SELECT COALESCE(Porcentaje,0) FROM ClienteDescuentos WHERE Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND Tipo=TRIM('Predial') AND idTipoDescuentoPersona=pap.TipoDescuento )/100 ), (sum(pal.Consumo)*(SELECT COALESCE(Porcentaje,0) FROM ClienteDescuentos WHERE Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND Tipo=TRIM('Predial') AND idTipoDescuentoPersona IS NULL AND Descripci_on NOT LIKE '%INAPAM%')/100 )) as Descuento,";

        if($IdCliente==35 && false) {
            $VerificaRezago= Funciones::ObtenValor("(SELECT COALESCE(COUNT(pch.id),0) Adeudo FROM Padr_onCatastralHistorial pch WHERE pch.Padr_onCatastral =".$DatosLecturaActual->paid." AND pch.`Status` IN (0,1) AND pch.A_no<=2019)", "Adeudo");
            if($VerificaRezago>0)
                $anioCondicion=' AND pal.A_no<2020 ';
            else
                $anioCondicion='';

            $ConcionDescuentoClientes=" sum(pal.Consumo)*IF(pal.A_no<=2018, (SELECT COALESCE(Porcentaje, 0) FROM ClienteDescuentos WHERE Ejercicio=2018 AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND Tipo='Buen Fin' AND idTipoDescuentoPersona IS NULL),
                    (SELECT COALESCE(Porcentaje,0) FROM ClienteDescuentos WHERE Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND Tipo='Predial' AND idTipoDescuentoPersona IS NULL) ) / 100 as Descuento,";

        }else
            $anioCondicion='';

        $decuentoInapam=Funciones::ObtenValor("SELECT INAPAM FROM Padr_onCatastral WHERE id=".$DatosLecturaActual->paid, "INAPAM");
        $conditionDes=$decuentoInapam=='NULL' || $decuentoInapam==''? ' AND Descripci_on NOT LIKE "%INAPAM%" ':' AND Descripci_on LIKE "%INAPAM%" ';
*/
        $lecturasCons="SELECT pal.id as palid,
		sum(pal.Consumo),
		pap.id as IdPredio,
		pap.Cliente,
        pap.Contribuyente as IdContribuyente,
		pal.TerrenoCosto,
		pal.ConstruccionCosto,
		pal.A_no,pal.Mes, pap.Cliente,
		pal.Status as EstatusPagado,
		sum(pap.ValorCatastral) as Tarifa,
		pap.SuperficieConstrucci_on,
		pap.SuperficieTerreno,
		pap.CuentaAnterior,
		pap.id as papid,
        pap.TipoPredio,
        pal.ConceptoCotizacion,
        pap.TipoDescuento,
		pal.TerrenoCosto,
		pal.ConstruccionCosto,
		sum(pal.Consumo) as ValorHistorial,
		sum(pal.Multas) Multas,
		sum(pal.GastosEjecucion) GastosEjecucion,
		sum(pal.GastosEmbargo) GastosEmbargo,
		sum(pal.OtrosGastos) OtrosGastos,
        (sum(pal.Consumo)*(SELECT COALESCE(Porcentaje,0) FROM ClienteDescuentos WHERE
        IF(TipoPadr_on IS NULL ,
        Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND TipoCotizaci_on=3 AND TipoConcepto=1  AND idTipoDescuentoPersona IS NULL ,
        Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND TipoCotizaci_on=3 AND TipoConcepto=1 AND TipoPadr_on=pap.TipoPredio AND idTipoDescuentoPersona IS NULL ) )/100 ) AS DescuentoAutomatico,
        (SELECT COALESCE(Porcentaje, 0) FROM ClienteDescuentos WHERE
			IF(TipoPadr_on IS NULL ,
				Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND TipoCotizaci_on=3 AND TipoConcepto=3 AND idTipoDescuentoPersona IS NULL ,
				Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND TipoCotizaci_on=3 AND TipoConcepto=3 AND TipoPadr_on=pap.TipoPredio AND idTipoDescuentoPersona IS NULL ) LIMIT 1 ) AS DescuentoActualizaciones,
		(SELECT COALESCE(Porcentaje, 0) FROM ClienteDescuentos WHERE
			IF(TipoPadr_on IS NULL ,
				Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND TipoCotizaci_on=3 AND TipoConcepto=2 AND idTipoDescuentoPersona IS NULL ,
				Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND TipoCotizaci_on=3 AND TipoConcepto=2 AND TipoPadr_on=pap.TipoPredio AND idTipoDescuentoPersona IS NULL ) LIMIT 1 ) AS DescuentoRecargos,
		(SELECT COALESCE(Porcentaje, 0) FROM ClienteDescuentos WHERE
			IF(TipoPadr_on IS NULL ,
				Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND TipoCotizaci_on=3 AND TipoConcepto=4 AND idTipoDescuentoPersona IS NULL ,
				Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND TipoCotizaci_on=3 AND TipoConcepto=4 AND TipoPadr_on=pap.TipoPredio AND idTipoDescuentoPersona IS NULL ) LIMIT 1 ) AS DescuentoMultas
        FROM Padr_onCatastral pap
        INNER JOIN Padr_onCatastralHistorial pal ON (pap.id=pal.Padr_onCatastral)
        WHERE
        pap.id =".$DatosLecturaActual->paid." AND
        pap.Cliente=".$IdCliente." AND
        pal.Status IN (0,1) AND CONCAT(pal.A_no,pal.Mes)>20146 AND CONCAT(pal.A_no,pal.Mes)<=".$DatosLecturaActual->A_no. $DatosLecturaActual->Mes. $anioCondicion. " GROUP BY pal.A_no,pal.Mes ORDER BY pal.A_no DESC, pal.Mes DESC";


#precode($descuentoManual,1,1);
        $Lectura= Funciones::ObtenValor($lecturasCons);

        $DatosLecturaActual=Funciones::ObtenValor("SELECT *, ccc.id as cccid, ca.Importe as ImporteUnitario,  pa.id as paid, pl.Consumo as ValorHistorial, pl.Status as EstatusPagado, pl.id as plid,  ca.BaseCalculo, pl.A_no, pl.Mes
        FROM Padr_onCatastral pa
        INNER JOIN Padr_onCatastralHistorial pl ON (pa.id=pl.Padr_onCatastral)
        INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=pa.ConceptoCobroCaja)
        INNER JOIN ConceptoRetencionesAdicionales cra ON ( cra.Concepto= ccc.id )
        INNER JOIN ConceptoRetencionesAdicionalesCliente crac ON ( cra.id = crac.ConceptoRetencionesAdicionales  AND crac.Cliente=pa.Cliente)
        INNER JOIN ConceptoAdicionales ca ON (ca.ConceptoRetencionesAdicionalesCliente=crac.id)
        WHERE
        pl.id=".$Lectura->palid."
        ORDER BY pl.A_no DESC
        LIMIT 0, 1");
#precode($lecturasCons,1,1);
        $resultados="";
        $sumapredial=0;
        $sumaAct=0;
        $sumaRec=0;
        $sumaMulta=0;
        $sumaGastosEjecucion=0;
        $sumaGastosEmbargo=0;
        $sumaOtrosGastos=0;
        $sumaDescuentos=0;
        $sumaTotalAnio=0;



        $Messumapredial=0;
        $MessumaAct=0;
        $MessumaRec=0;
        $MessumaMulta=0;
        $MessumaGastosEjecucion=0;
        $MessumaGastosEmbargo=0;
        $MessumaOtrosGastos=0;
        $MessumaDescuentos=0;
        $MessumaTotalAnio=0;
        $Descuentos=0;
        $anioActual=0;

        $FechasVencimiento = ['01-15','01-31',
            '02-15','02-28',
            '03-15','03-31',
            '04-15','04-30',
            '05-15','05-31',
            '06-15','06-30',
            '07-15','07-31',
            '08-15','08-31',
            '09-15','09-30',
            '10-15','10-31',
            '11-15','11-30',
            '12-15','12-31'];



        $VencimientoEdoCuenta="";
        foreach ($FechasVencimiento as $valor){
            $Fecha="";
            $fechaVencimiento = date('Y-m-d', strtotime(date('Y-').$valor));

            if(date('D',strtotime($fechaVencimiento))=="Sat"){
                $fechaVencimiento = strtotime ( '-1 day' , strtotime ( $fechaVencimiento ) ) ;
                $fechaVencimiento = date ( 'Y-m-d' , $fechaVencimiento );
            }
            if(date('D',strtotime($fechaVencimiento))=="Sun"){
                $fechaVencimiento = strtotime ( '-2 day' , strtotime ( $fechaVencimiento ) ) ;
                $fechaVencimiento = date ( 'Y-m-d' , $fechaVencimiento );
            }

            if( strtotime($fechaVencimiento) >= strtotime(date("Y-m-d")) ){
                $VencimientoEdoCuenta = $fechaVencimiento;
                break;
            }
        }

        $AuxEF = true;

        $ejecutaLect=DB::select($lecturasCons);
        $ExisteDescuento = NULL;

        foreach($ejecutaLect as $lectr){

            $DescuentoPersonaVulnerable =
                ObtenValor("SELECT COUNT(dd.id) as ExisteDescuento,
                    {$lectr->ValorHistorial} * ( SELECT COALESCE(Porcentaje, 0) FROM ClienteDescuentos WHERE
                    IF(TipoPadr_on IS NULL ,
                    Ejercicio={$lectr->A_no} AND Cliente={$lectr->Cliente} AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND TipoCotizaci_on=3 AND TipoConcepto=1 AND idTipoDescuentoPersona=d.TipoDescuento ,
                    Ejercicio={$lectr->A_no} AND Cliente={$lectr->Cliente} AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND TipoCotizaci_on=3 AND TipoConcepto=1 AND TipoPadr_on={$lectr->TipoPredio} AND idTipoDescuentoPersona=d.TipoDescuento ) ) /100  AS DescuentoPersonaVulnerable,
                    ( SELECT Nombre FROM TipoDescuentoPersona WHERE id = d.TipoDescuento ) as TipoDescuento
                    FROM Descuentos d INNER JOIN DetalleDescuentos dd ON (dd.idDescuento=d.id)
                    WHERE d.Contribuyente={$lectr->IdContribuyente} and dd.Padr_on={$lectr->IdPredio} and dd.TipoPadr_on=3 and dd.Estatus=1 and d.Estatus=1");

            #precode($DescuentoPersonaVulnerable,1);
            if($DescuentoPersonaVulnerable->ExisteDescuento>0){
                $lectr->TipoDescuento = 1;
                $lectr->Descuento = $DescuentoPersonaVulnerable->DescuentoPersonaVulnerable;
                $ExisteDescuento = $DescuentoPersonaVulnerable->TipoDescuento;
            }else{
                $lectr->TipoDescuento = 0;
                $lectr->Descuento = $lectr->DescuentoAutomatico;
            }

            //			echo "-".$montoBase."<br />";
            if($anioActual!=$lectr->A_no && $anioActual!=0){


                if($DescuentoVulnerable) {
                    $datosDeConcepto = Funciones::ObtenValor("SELECT DISTINCT c3.Importe as c3Importe, c3.id as c3id
                FROM ConceptoCobroCaja c
                INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto )
                INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales )
                INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente )
                WHERE c2.AplicaEnSubtotal=0 AND c3.Cliente=$IdCliente AND
                c3.EjercicioFiscal=$anioActual AND
                c2.Cliente=$IdCliente AND c.id = $Concepto");
                    $ImpMinimo=PredialController::CalculaImpuestoBaseMinima($IdCliente, $TipoPredio, $anioActual, $Concepto, $datosDeConcepto->c3Importe);
                    if($MessumaTotalAnio<$ImpMinimo){
                        $sumaDescuentos=$sumaDescuentos-$MessumaDescuentos;
                        $sumaTotalAnio=$sumaTotalAnio+$MessumaDescuentos;
                        $MessumaDescuentos=$Messumapredial-$ImpMinimo;
                        $MessumaTotalAnio=$Messumapredial-$MessumaDescuentos;
                        $sumaDescuentos+=$MessumaDescuentos;
                        $sumaTotalAnio=$sumaTotalAnio-$MessumaDescuentos;
                    }
                }
                $resultados.= "	<tr>
            <td align='center'>".$anioActual."</td>
            <td align='right'>".number_format($montoBase,2)."</td>
            <td align='right'>".number_format($Messumapredial,2)."</td>
            <td align='right'>".number_format($MessumaAct,2)."</td>
            <td align='right'>".number_format($MessumaRec,2)."</td>

            <td align='right'>".number_format($MessumaGastosEjecucion,2)."</td>
            <td align='right'>".number_format($MessumaGastosEmbargo,2)."</td>

             <td align='right'>".number_format($MessumaMulta,2)."</td>
            <td align='right'>".number_format($MessumaOtrosGastos,2)."</td>
            <td align='right'>".number_format($MessumaDescuentos,2)."</td>
            <td align='right'>".number_format($MessumaTotalAnio,2)."</td>
             </tr>";
                $Messumapredial=0;
                $MessumaAct=0;
                $MessumaRec=0;
                $MessumaMulta=0;
                $MessumaGastosEjecucion=0;
                $MessumaGastosEmbargo=0;
                $MessumaOtrosGastos=0;
                $MessumaDescuentos=0;
                $MessumaTotalAnio=0;

            }

            $montopredial = $lectr->ValorHistorial;

            $mes=($lectr->Mes*2)-1;
            $anio=$lectr->A_no;
            if(intval($mes)>12){
                $mes=1;
                $anio=$anio+1;
            }

            $mes=str_pad($mes, 2, "0", STR_PAD_LEFT);

            //  $dia = date("d",(mktime(0,0,0,$mes+1,1,$lectr['A_no'])-1));
            $dia=28;

            $fechaVencimiento= $anio."-".$mes."-".$dia;

            if(date('D',intval($fechaVencimiento))=="Sat"){
                $dia=$dia+2;
            }
            if(date('D',intval($fechaVencimiento))=="Sun"){
                $dia=$dia+1;
            }

            $fechaVencimiento= $anio."-".$mes."-".$dia;
            //  echo "<br />FV:".$fechaVencimiento." H:i:00";

            $fecha_actual = strtotime(date($fechaCotizacion." H:i:00"));
            $fecha_entrada = strtotime(date($fechaVencimiento." H:i:00"));
            if($fecha_actual > $fecha_entrada){
                $recargosOK = floatval( str_replace(",","", number_format ( PredialController::CalculoRecargos($fechaVencimiento, $montopredial, $Cliente) , 2 ) ) );
                $actualizacionesOK = floatval( str_replace(",","", number_format ( PredialController::CalculoActualizacion($fechaVencimiento, $montopredial), 2 ) ) );
            }else{
                $recargosOK = 0;
                $actualizacionesOK = 0;
            }

            //Configuracion del cliente para verificar si tiene habilidada la opcion de generar Act. y Rec.
            if($configuracionGenerarRecargos==0){
                $recargosOK = 0;
            }
            if($configuracionGenerarActualizaciones==0){
                $actualizacionesOK = 0;
            }

            $EFMultas=0;
            $EFGastosEjecucion=0;
            $EFGastosEmbargo=0;


            if($AuxEF && $Cliente!=14){
                $GastosEF = PredialController::EjecucionFiscalCajaV2($lectr->papid, 20);
                if( isset($GastosEF['EXISTE']) && $GastosEF['EXISTE']==1 ) {
                    foreach ($GastosEF['CONCEPTOS'] as $GastoEF) {
                        # precode($GastoEF,1);
                        switch ($GastoEF['Categoria']) {
                            case 2:
                                $EFMultas = $GastoEF['Importe']; //Multas
                                break;
                            case 4:
                                $EFGastosEjecucion = $GastoEF['Importe']; //GastosEjecucion
                                break;
                            case 5:
                                $EFGastosEmbargo = $GastoEF['Importe']; //GastosEmbargo
                                break;
                        }
                    }
                }
                $AuxEF=false;
            }


            $multasOK=$lectr->Multas+$EFMultas;###

            $montoBase=PredialController::CalcularValorCatastralConsMasTerreno($lectr->papid, $lectr->A_no, $Cliente);
            if($IdCliente==14)
                $montoBase=Funciones::ObtenValor("SELECT (COALESCE(TerrenoCosto,0)+COALESCE(ConstruccionCosto,0)) AS BaseGravable FROM Padr_onCatastralHistorial WHERE Padr_onCatastral=".$lectr->papid." AND A_no=".$lectr->A_no." LIMIT 1", "BaseGravable");

            $recargosOKDescuento=($recargosOK*$lectr->DescuentoRecargos)/100;
            $actualizacionesOKDescuento=($actualizacionesOK*$lectr->DescuentoActualizaciones)/100;

            // $DatosXML=Funciones::ObtenValor("SELECT DatosExtra, (SELECT COUNT(DISTINCT Mes) FROM ConceptoAdicionalesCotizaci_on WHERE Cotizaci_on=XMLIngreso.idCotizaci_on) AS TotalLecturas FROM XMLIngreso WHERE idCotizaci_on=".$lectr->IdCotizacion);

            $multasOKDescuento=($multasOK*$lectr->DescuentoMultas)/100;###

            //   $DatosExtra=json_decode($DatosXML->DatosExtra, true);


            $Messumapredial+=$montopredial;
            $MessumaAct+=$actualizacionesOK;
            $MessumaRec+=$recargosOK;
            $MessumaMulta+=$multasOK;
            $MessumaGastosEjecucion+=$lectr->GastosEjecucion+$EFGastosEjecucion;
            $MessumaGastosEmbargo+=$lectr->GastosEmbargo + $EFGastosEmbargo;
            $MessumaOtrosGastos+=$lectr->OtrosGastos;

            $MessumaDescuentos+= $lectr->Descuento+$recargosOKDescuento+$actualizacionesOKDescuento+$multasOKDescuento;
            $totalAnio=( $montopredial + $actualizacionesOK + $recargosOK+ $multasOK + $lectr->GastosEjecucion + $EFGastosEjecucion+ $lectr->GastosEmbargo + $EFGastosEmbargo + $lectr->OtrosGastos ) -($lectr->Descuento+$recargosOKDescuento+$actualizacionesOKDescuento+$multasOKDescuento) ;

            $MessumaTotalAnio+=$totalAnio;


            $sumapredial+=$montopredial;
            $sumaAct+=$actualizacionesOK;
            $sumaRec+=$recargosOK;
            $sumaMulta+=$multasOK;
            $sumaGastosEjecucion+=$lectr->GastosEjecucion + $EFGastosEjecucion;
            $sumaGastosEmbargo+=$lectr->GastosEmbargo + $EFGastosEmbargo;
            $sumaOtrosGastos+=$lectr->OtrosGastos;
            $sumaDescuentos+=$lectr->Descuento+$recargosOKDescuento+$actualizacionesOKDescuento+$multasOKDescuento;
            $sumaTotalAnio+=$totalAnio;
            $anioActual=$lectr->A_no;
            $DescuentoVulnerable=($lectr->TipoDescuento>0? true:false);
            $TipoPredio=$lectr->TipoPredio;
            $Concepto=$lectr->ConceptoCotizacion;

            /**********************************************************************************************************/

        }


        //Condicion agregada para no permitir descuentos abajo de la base minima en grupos vulnerables
        if($DescuentoVulnerable) {
            $datosDeConcepto = Funciones::ObtenValor("SELECT DISTINCT c3.Importe as c3Importe, c3.id as c3id
                FROM ConceptoCobroCaja c
                INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto )
                INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales )
                INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente )
                WHERE c2.AplicaEnSubtotal=0 AND c3.Cliente=$IdCliente AND
                c3.EjercicioFiscal=$anioActual AND
                c2.Cliente=$IdCliente AND c.id = $Concepto");
            $ImpMinimo=PredialController::CalculaImpuestoBaseMinima($IdCliente, $TipoPredio, $anioActual, $Concepto, $datosDeConcepto->c3Importe);
            if($MessumaTotalAnio<$ImpMinimo){
                $sumaDescuentos=$sumaDescuentos-$MessumaDescuentos;
                $sumaTotalAnio=$sumaTotalAnio+$MessumaDescuentos;
                $MessumaDescuentos=$Messumapredial-$ImpMinimo;
                $MessumaTotalAnio=$Messumapredial-$MessumaDescuentos;
                $sumaDescuentos+=$MessumaDescuentos;
                $sumaTotalAnio=$sumaTotalAnio-$MessumaDescuentos;
            }

        }
        $resultados.= "	<tr>
                            <td align='center'>".($anioActual==0? 2020: $anioActual )."</td>
                            <td align='right'>".number_format($montoBase,2)."</td>
                            <td align='right'>".number_format($Messumapredial,2)."</td>
                            <td align='right'>".number_format($MessumaAct,2)."</td>
                            <td align='right'>".number_format($MessumaRec,2)."</td>

                            <td align='right'>".number_format($MessumaGastosEjecucion,2)."</td>
                            <td align='right'>".number_format($MessumaGastosEmbargo,2)."</td>

                           <td align='right'>".number_format($MessumaMulta,2)."</td>

                            <td align='right'>".number_format($MessumaOtrosGastos,2)."</td>
                            <td align='right'>".number_format($MessumaDescuentos,2)."</td>
                            <td align='right'>".number_format($MessumaTotalAnio,2)."</td>
                        </tr>";

        #$sumaMulta=CalcularMultasPredial($DatosLecturaActual['paid'], $sumaTotalAnio-$Descuentos);
        //Code quitar decimales, se van al descuento, quedan cantidades cerradas
        $Decimales=explode(".", ($sumaTotalAnio-$Descuentos==0?'0.0':$sumaTotalAnio-$Descuentos) );

        $resultados.="<tr><td align='right' colspan='2'><b>Totales</b></td>
                        <td align='right'><b>".number_format($sumapredial,2)."</b></td>
                        <td align='right'><b>".number_format($sumaAct,2)."</b></td>
                        <td align='right'><b>".number_format($sumaRec,2)."</b></td>

                        <td align='right'><b>".number_format($sumaGastosEjecucion,2)."</b></td>
                        <td align='right'><b>".number_format($sumaGastosEmbargo,2)."</b></td>

                        <td align='right'><b>".number_format($sumaMulta,2)."</b></td>
                        <td align='right'><b>".number_format($sumaOtrosGastos,2)."</b></td>
                        <td align='right'><b>".number_format($sumaDescuentos+$Descuentos,2)."</b></td>
                        <td align='right'><b>".number_format($sumaTotalAnio-$Descuentos,2)."</b></td>
                    </tr>
                    <tr>
                        <td align='right' colspan='10'><b>Descuento por Redondeo</b></td>
                        <td align='right'><b>- ". number_format(floatval('0.'.$Decimales[1]),2)."</b></td>
                    </tr>
                    <tr><td colspan='11'>&nbsp;</td></tr>";

        $rutaBarcode = 'https://suinpac.piacza.com.mx/lib/barcode2.php?f=png&text=' . (isset($DatosLecturaActual->paid) ? $DatosLecturaActual->paid : '') . '&codetype=Code128&orientation=Horizontal&size=40&print=false';

        $resultados.="
                    </table>
                    <table style='padding:-35px 0 0 0;margin:-10px 0 0 0;' border='0' width='787px'>
                  <tr>
                      <td colspan='11'><img width='787px' height='1px' src='". asset(Storage::url(env('IMAGES') . 'barraColores.png'))."'> <br /> </td>
                  </tr>
                  <tr>
                                            <td align='right' colspan='3'>  <div align='right'>
                                            <br>
                         <img src = '".$rutaBarcode."' alt='Codigo de Barras' ><br/><br/>
                    </div></td>
                    <td align='right' colspan='6'><br /><span style='font-size: 20px; font-weight: bold;'>Total a Pagar</span><br /><br /></td>
                    <td align='right'  colspan='2'><br /><b><span style='font-size: 20px; font-weight: bold;'>$ ".number_format($sumaTotalAnio-$Descuentos-floatval('0.'.$Decimales[1]),2)."</span></b><br /><br /></td>
                  </tr>

                  <tr>
                      <td colspan='11'><br />
                          <img width='787px' height='1px' src='".asset(Storage::url(env('IMAGES') . 'barraColores.png'))."'>
                      </td>
                  </tr>

                                       ".($ExisteDescuento=='NULL'? '':'<tr>
                                            <td colspan="11" class="text-right">
                                                <span style="color:red; font-size:12px;"><b>Estímulo Fiscal: '.$ExisteDescuento. ' </span>
                                            </td>
                                        </tr>').

            "<tr>
                        <td colspan='11' align='right'>
                            <span style='font-size:12px;'>Expedici&oacute;n: ".date('d/m/Y H:i:s')."</span> - <span style='font-size:12px;'>Vencimiento: ".date('d/m/Y',strtotime($VencimientoEdoCuenta))."</span> <span style='font-size:12px;'>Pendiente</span>
                        </td>
                    </tr>

                    <tr>
                        <td colspan='11' align='right'>
                            <span style='font-size:12px;'>Documento Informativo expedido a petici&oacute;n del Contribuyente, no es un requerimiento de pago.</span>
                        </td>
                    </tr>

                  ";
        return $resultados;
    }

    function obtieneDatosLecturaCatastralNuevoPredial($idCotizacion, $fechaCotizacion, $Cliente,$ExisteDescuento,$Lectura){


        $montoBase = 0;
        $IdCliente=$Cliente;
        $configuracionGenerarRecargos = PredialController::ObtenValorPorClave("GenerarRecargos", $Cliente);
        if(!$configuracionGenerarRecargos==1)
            $configuracionGenerarRecargos = 0;

        $configuracionGenerarActualizaciones= PredialController::ObtenValorPorClave("GenerarActualizaciones", $Cliente);
        if(!$configuracionGenerarActualizaciones==1)
            $configuracionGenerarActualizaciones = 0;
        $montoBase = 0;
        $DescuentoVulnerable=false;
        $TipoPredio=0;
        $Concepto=0;
        $DatosLecturaActual=Funciones::ObtenValor("SELECT *, ccc.id as cccid, ca.Importe as ImporteUnitario,  pa.id as paid, pl.Consumo as ValorHistorial, pl.Status as EstatusPagado, pl.id as plid,  ca.BaseCalculo, pl.A_no, pl.Mes
			FROM Padr_onCatastral pa
			INNER JOIN Padr_onCatastralHistorial pl ON (pa.id=pl.Padr_onCatastral)
			INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=pa.ConceptoCobroCaja)
			INNER JOIN ConceptoRetencionesAdicionales cra ON ( cra.Concepto= ccc.id )
			INNER JOIN ConceptoRetencionesAdicionalesCliente crac ON ( cra.id = crac.ConceptoRetencionesAdicionales  AND crac.Cliente=pa.Cliente)
			INNER JOIN ConceptoAdicionales ca ON (ca.ConceptoRetencionesAdicionalesCliente=crac.id)
			WHERE
			pl.id=".$Lectura."
			ORDER BY pl.A_no DESC
			LIMIT 0, 1");

        $anioCondicion=' AND pal.A_no<2020 ';
        $ConcionDescuentoClientes="IF(pap.TipoDescuento >0, (sum(pal.Consumo)*(SELECT COALESCE(Porcentaje,0) FROM ClienteDescuentos WHERE Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND Tipo=TRIM('Predial') AND idTipoDescuentoPersona=pap.TipoDescuento )/100 ), (sum(pal.Consumo)*(SELECT COALESCE(Porcentaje,0) FROM ClienteDescuentos WHERE Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND Tipo=TRIM('Predial') AND idTipoDescuentoPersona IS NULL AND Descripci_on NOT LIKE '%INAPAM%')/100 )) as Descuento,";

        if($IdCliente==35 && false) {
            $VerificaRezago= Funciones::ObtenValor("(SELECT COALESCE(COUNT(pch.id),0) Adeudo FROM Padr_onCatastralHistorial pch WHERE pch.Padr_onCatastral =".$DatosLecturaActual->paid." AND pch.`Status` IN (0,1) AND pch.A_no<=2019)", "Adeudo");
            if($VerificaRezago>0)
                $anioCondicion=' AND pal.A_no<2020 ';
            else
                $anioCondicion='';

            $ConcionDescuentoClientes=" sum(pal.Consumo)*IF(pal.A_no<=2018, (SELECT COALESCE(Porcentaje, 0) FROM ClienteDescuentos WHERE Ejercicio=2018 AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND Tipo='Buen Fin' AND idTipoDescuentoPersona IS NULL),
                    (SELECT COALESCE(Porcentaje,0) FROM ClienteDescuentos WHERE Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND Tipo='Predial' AND idTipoDescuentoPersona IS NULL) ) / 100 as Descuento,";

        }else
            $anioCondicion='';

        $decuentoInapam=Funciones::ObtenValor("SELECT INAPAM FROM Padr_onCatastral WHERE id=".$DatosLecturaActual->paid, "INAPAM");
        $conditionDes=$decuentoInapam=='NULL' || $decuentoInapam==''? ' AND Descripci_on NOT LIKE "%INAPAM%" ':' AND Descripci_on LIKE "%INAPAM%" ';

        $lecturasCons="SELECT pal.id as palid,
		sum(pal.Consumo),
		pal.TerrenoCosto,
		pal.ConstruccionCosto,
		pal.A_no,pal.Mes, pap.Cliente,
		pal.Status as EstatusPagado,
		sum(pap.ValorCatastral) as Tarifa,
		pap.SuperficieConstrucci_on,
		pap.SuperficieTerreno,
		pap.CuentaAnterior,
		pap.id as papid,
        pap.TipoPredio,
        pal.ConceptoCotizacion,
        pap.TipoDescuento,
		pal.TerrenoCosto,
		pal.ConstruccionCosto,
		sum(pal.Consumo) as ValorHistorial,
		sum(pal.Multas) Multas,
		sum(pal.GastosEjecucion) GastosEjecucion,
		sum(pal.GastosEmbargo) GastosEmbargo,
		sum(pal.OtrosGastos) OtrosGastos,
        $ConcionDescuentoClientes
        (SELECT COALESCE(Porcentaje, 0) FROM ClienteDescuentos WHERE
			IF(TipoPadr_on IS NULL ,
				Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND TipoCotizaci_on=3 AND TipoConcepto=3 AND idTipoDescuentoPersona IS NULL ,
				Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND TipoCotizaci_on=3 AND TipoConcepto=3 AND TipoPadr_on=pap.TipoPredio AND idTipoDescuentoPersona IS NULL ) LIMIT 1 ) AS DescuentoActualizaciones,
		(SELECT COALESCE(Porcentaje, 0) FROM ClienteDescuentos WHERE
			IF(TipoPadr_on IS NULL ,
				Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND TipoCotizaci_on=3 AND TipoConcepto=2 AND idTipoDescuentoPersona IS NULL ,
				Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND TipoCotizaci_on=3 AND TipoConcepto=2 AND TipoPadr_on=pap.TipoPredio AND idTipoDescuentoPersona IS NULL ) LIMIT 1 ) AS DescuentoRecargos,
		(SELECT COALESCE(Porcentaje, 0) FROM ClienteDescuentos WHERE
				IF(TipoPadr_on IS NULL ,
						Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND TipoCotizaci_on=3 AND TipoConcepto=4 AND idTipoDescuentoPersona IS NULL ,
						Ejercicio=pal.A_no AND Cliente=pap.Cliente AND CURDATE() BETWEEN FechaInicial AND FechaFinal AND TipoCotizaci_on=3 AND TipoConcepto=4 AND TipoPadr_on=pap.TipoPredio AND idTipoDescuentoPersona IS NULL ) LIMIT 1 ) AS DescuentoMultas
        FROM Padr_onCatastral pap
        INNER JOIN Padr_onCatastralHistorial pal ON (pap.id=pal.Padr_onCatastral)
        WHERE
        pap.id =".$DatosLecturaActual->paid." AND
        pap.Cliente=".$IdCliente." AND
        pal.Status IN (0,1) AND CONCAT(pal.A_no,pal.Mes)>20146 AND CONCAT(pal.A_no,pal.Mes)<=".$DatosLecturaActual->A_no. $DatosLecturaActual->Mes. $anioCondicion. " GROUP BY pal.A_no,pal.Mes ORDER BY pal.A_no DESC, pal.Mes DESC";


#precode($descuentoManual,1,1);
        $Lectura= Funciones::ObtenValor($lecturasCons);

        $DatosLecturaActual=Funciones::ObtenValor("SELECT *, ccc.id as cccid, ca.Importe as ImporteUnitario,  pa.id as paid, pl.Consumo as ValorHistorial, pl.Status as EstatusPagado, pl.id as plid,  ca.BaseCalculo, pl.A_no, pl.Mes
        FROM Padr_onCatastral pa
        INNER JOIN Padr_onCatastralHistorial pl ON (pa.id=pl.Padr_onCatastral)
        INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=pa.ConceptoCobroCaja)
        INNER JOIN ConceptoRetencionesAdicionales cra ON ( cra.Concepto= ccc.id )
        INNER JOIN ConceptoRetencionesAdicionalesCliente crac ON ( cra.id = crac.ConceptoRetencionesAdicionales  AND crac.Cliente=pa.Cliente)
        INNER JOIN ConceptoAdicionales ca ON (ca.ConceptoRetencionesAdicionalesCliente=crac.id)
        WHERE
        pl.id=".$Lectura->palid."
        ORDER BY pl.A_no DESC
        LIMIT 0, 1");
#precode($lecturasCons,1,1);
        $resultados="";
        $sumapredial=0;
        $sumaAct=0;
        $sumaRec=0;
        $sumaMulta=0;
        $sumaGastosEjecucion=0;
        $sumaGastosEmbargo=0;
        $sumaOtrosGastos=0;
        $sumaDescuentos=0;
        $sumaTotalAnio=0;



        $Messumapredial=0;
        $MessumaAct=0;
        $MessumaRec=0;
        $MessumaMulta=0;
        $MessumaGastosEjecucion=0;
        $MessumaGastosEmbargo=0;
        $MessumaOtrosGastos=0;
        $MessumaDescuentos=0;
        $MessumaTotalAnio=0;
        $Descuentos=0;
        $anioActual=0;

        $FechasVencimiento = ['01-15','01-31',
            '02-15','02-28',
            '03-15','03-31',
            '04-15','04-30',
            '05-15','05-31',
            '06-15','06-30',
            '07-15','07-31',
            '08-15','08-31',
            '09-15','09-30',
            '10-15','10-31',
            '11-15','11-30',
            '12-15','12-31'];



        $VencimientoEdoCuenta="";
        foreach ($FechasVencimiento as $valor){
            $Fecha="";
            $fechaVencimiento = date('Y-m-d', strtotime(date('Y-').$valor));

            if(date('D',strtotime($fechaVencimiento))=="Sat"){
                $fechaVencimiento = strtotime ( '-1 day' , strtotime ( $fechaVencimiento ) ) ;
                $fechaVencimiento = date ( 'Y-m-d' , $fechaVencimiento );
            }
            if(date('D',strtotime($fechaVencimiento))=="Sun"){
                $fechaVencimiento = strtotime ( '-2 day' , strtotime ( $fechaVencimiento ) ) ;
                $fechaVencimiento = date ( 'Y-m-d' , $fechaVencimiento );
            }

            if( strtotime($fechaVencimiento) >= strtotime(date("Y-m-d")) ){
                $VencimientoEdoCuenta = $fechaVencimiento;
                break;
            }
        }

        $AuxEF = true;

        $ejecutaLect=DB::select($lecturasCons);

        foreach($ejecutaLect as $lectr){

            //			echo "-".$montoBase."<br />";
            if($anioActual!=$lectr->A_no && $anioActual!=0){


                if($DescuentoVulnerable) {
                    $datosDeConcepto = Funciones::ObtenValor("SELECT DISTINCT c3.Importe as c3Importe, c3.id as c3id
                FROM ConceptoCobroCaja c
                INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto )
                INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales )
                INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente )
                WHERE c2.AplicaEnSubtotal=0 AND c3.Cliente=$IdCliente AND
                c3.EjercicioFiscal=$anioActual AND
                c2.Cliente=$IdCliente AND c.id = $Concepto");
                    $ImpMinimo=PredialController::CalculaImpuestoBaseMinima($IdCliente, $TipoPredio, $anioActual, $Concepto, $datosDeConcepto->c3Importe);
                    if($MessumaTotalAnio<$ImpMinimo){
                        $sumaDescuentos=$sumaDescuentos-$MessumaDescuentos;
                        $sumaTotalAnio=$sumaTotalAnio+$MessumaDescuentos;
                        $MessumaDescuentos=$Messumapredial-$ImpMinimo;
                        $MessumaTotalAnio=$Messumapredial-$MessumaDescuentos;
                        $sumaDescuentos+=$MessumaDescuentos;
                        $sumaTotalAnio=$sumaTotalAnio-$MessumaDescuentos;
                    }
                }
                $resultados.= "	<tr>
            <td align='center'>".$anioActual."</td>
            <td align='right'>".number_format($montoBase,2)."</td>
            <td align='right'>".number_format($Messumapredial,2)."</td>
            <td align='right'>".number_format($MessumaAct,2)."</td>
            <td align='right'>".number_format($MessumaRec,2)."</td>

            <td align='right'>".number_format($MessumaGastosEjecucion,2)."</td>
            <td align='right'>".number_format($MessumaGastosEmbargo,2)."</td>

             <td align='right'>".number_format($MessumaMulta,2)."</td>
            <td align='right'>".number_format($MessumaOtrosGastos,2)."</td>
            <td align='right'>".number_format($MessumaDescuentos,2)."</td>
            <td align='right'>".number_format($MessumaTotalAnio,2)."</td>
             </tr>";
                $Messumapredial=0;
                $MessumaAct=0;
                $MessumaRec=0;
                $MessumaMulta=0;
                $MessumaGastosEjecucion=0;
                $MessumaGastosEmbargo=0;
                $MessumaOtrosGastos=0;
                $MessumaDescuentos=0;
                $MessumaTotalAnio=0;

            }

            $montopredial = $lectr->ValorHistorial;

            $mes=($lectr->Mes*2)-1;
            $anio=$lectr->A_no;
            if(intval($mes)>12){
                $mes=1;
                $anio=$anio+1;
            }

            $mes=str_pad($mes, 2, "0", STR_PAD_LEFT);

            //  $dia = date("d",(mktime(0,0,0,$mes+1,1,$lectr['A_no'])-1));
            $dia=28;

            $fechaVencimiento= $anio."-".$mes."-".$dia;

            if(date('D',intval($fechaVencimiento))=="Sat"){
                $dia=$dia+2;
            }
            if(date('D',intval($fechaVencimiento))=="Sun"){
                $dia=$dia+1;
            }

            $fechaVencimiento= $anio."-".$mes."-".$dia;
            //  echo "<br />FV:".$fechaVencimiento." H:i:00";

            $fecha_actual = strtotime(date($fechaCotizacion." H:i:00"));
            $fecha_entrada = strtotime(date($fechaVencimiento." H:i:00"));
            if($fecha_actual > $fecha_entrada){
                $recargosOK = floatval( str_replace(",","", number_format ( PredialController::CalculoRecargos($fechaVencimiento, $montopredial, $Cliente) , 2 ) ) );
                $actualizacionesOK = floatval( str_replace(",","", number_format ( PredialController::CalculoActualizacion($fechaVencimiento, $montopredial), 2 ) ) );
            }else{
                $recargosOK = 0;
                $actualizacionesOK = 0;
            }

            //Configuracion del cliente para verificar si tiene habilidada la opcion de generar Act. y Rec.
            if($configuracionGenerarRecargos==0){
                $recargosOK = 0;
            }
            if($configuracionGenerarActualizaciones==0){
                $actualizacionesOK = 0;
            }

            $EFMultas=0;
            $EFGastosEjecucion=0;
            $EFGastosEmbargo=0;


            if($AuxEF && $Cliente!=14){
                $GastosEF = PredialController::EjecucionFiscalCajaV2($lectr->papid, 20);
                if( isset($GastosEF['EXISTE']) && $GastosEF['EXISTE']==1 ) {
                    foreach ($GastosEF['CONCEPTOS'] as $GastoEF) {
                        # precode($GastoEF,1);
                        switch ($GastoEF['Categoria']) {
                            case 2:
                                $EFMultas = $GastoEF['Importe']; //Multas
                                break;
                            case 4:
                                $EFGastosEjecucion = $GastoEF['Importe']; //GastosEjecucion
                                break;
                            case 5:
                                $EFGastosEmbargo = $GastoEF['Importe']; //GastosEmbargo
                                break;
                        }
                    }
                }
                $AuxEF=false;
            }


            $multasOK=$lectr->Multas+$EFMultas;###

            $montoBase=PredialController::CalcularValorCatastralConsMasTerreno($lectr->papid, $lectr->A_no, $Cliente);
            if($IdCliente==14)
                $montoBase=Funciones::ObtenValor("SELECT (COALESCE(TerrenoCosto,0)+COALESCE(ConstruccionCosto,0)) AS BaseGravable FROM Padr_onCatastralHistorial WHERE Padr_onCatastral=".$lectr->papid." AND A_no=".$lectr->A_no." LIMIT 1", "BaseGravable");

            $recargosOKDescuento=($recargosOK*$lectr->DescuentoRecargos)/100;
            $actualizacionesOKDescuento=($actualizacionesOK*$lectr->DescuentoActualizaciones)/100;

            // $DatosXML=Funciones::ObtenValor("SELECT DatosExtra, (SELECT COUNT(DISTINCT Mes) FROM ConceptoAdicionalesCotizaci_on WHERE Cotizaci_on=XMLIngreso.idCotizaci_on) AS TotalLecturas FROM XMLIngreso WHERE idCotizaci_on=".$lectr->IdCotizacion);

            $multasOKDescuento=($multasOK*$lectr->DescuentoMultas)/100;###

            //   $DatosExtra=json_decode($DatosXML->DatosExtra, true);


            $Messumapredial+=$montopredial;
            $MessumaAct+=$actualizacionesOK;
            $MessumaRec+=$recargosOK;
            $MessumaMulta+=$multasOK;
            $MessumaGastosEjecucion+=$lectr->GastosEjecucion+$EFGastosEjecucion;
            $MessumaGastosEmbargo+=$lectr->GastosEmbargo + $EFGastosEmbargo;
            $MessumaOtrosGastos+=$lectr->OtrosGastos;

            $MessumaDescuentos+= $lectr->Descuento+$recargosOKDescuento+$actualizacionesOKDescuento+$multasOKDescuento;
            $totalAnio=( $montopredial + $actualizacionesOK + $recargosOK+ $multasOK + $lectr->GastosEjecucion + $EFGastosEjecucion+ $lectr->GastosEmbargo + $EFGastosEmbargo + $lectr->OtrosGastos ) -($lectr->Descuento+$recargosOKDescuento+$actualizacionesOKDescuento+$multasOKDescuento) ;

            $MessumaTotalAnio+=$totalAnio;


            $sumapredial+=$montopredial;
            $sumaAct+=$actualizacionesOK;
            $sumaRec+=$recargosOK;
            $sumaMulta+=$multasOK;
            $sumaGastosEjecucion+=$lectr->GastosEjecucion + $EFGastosEjecucion;
            $sumaGastosEmbargo+=$lectr->GastosEmbargo + $EFGastosEmbargo;
            $sumaOtrosGastos+=$lectr->OtrosGastos;
            $sumaDescuentos+=$lectr->Descuento+$recargosOKDescuento+$actualizacionesOKDescuento+$multasOKDescuento;
            $sumaTotalAnio+=$totalAnio;
            $anioActual=$lectr->A_no;
            $DescuentoVulnerable=($lectr->TipoDescuento>0? true:false);
            $TipoPredio=$lectr->TipoPredio;
            $Concepto=$lectr->ConceptoCotizacion;

            /**********************************************************************************************************/

        }


        //Condicion agregada para no permitir descuentos abajo de la base minima en grupos vulnerables
        if($DescuentoVulnerable) {
            $datosDeConcepto = Funciones::ObtenValor("SELECT DISTINCT c3.Importe as c3Importe, c3.id as c3id
                FROM ConceptoCobroCaja c
                INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto )
                INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales )
                INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente )
                WHERE c2.AplicaEnSubtotal=0 AND c3.Cliente=$IdCliente AND
                c3.EjercicioFiscal=$anioActual AND
                c2.Cliente=$IdCliente AND c.id = $Concepto");
            $ImpMinimo=PredialController::CalculaImpuestoBaseMinima($IdCliente, $TipoPredio, $anioActual, $Concepto, $datosDeConcepto->c3Importe);
            if($MessumaTotalAnio<$ImpMinimo){
                $sumaDescuentos=$sumaDescuentos-$MessumaDescuentos;
                $sumaTotalAnio=$sumaTotalAnio+$MessumaDescuentos;
                $MessumaDescuentos=$Messumapredial-$ImpMinimo;
                $MessumaTotalAnio=$Messumapredial-$MessumaDescuentos;
                $sumaDescuentos+=$MessumaDescuentos;
                $sumaTotalAnio=$sumaTotalAnio-$MessumaDescuentos;
            }

        }
        $resultados.= "	<tr>
                            <td align='center'>".($anioActual==0? 2020: $anioActual )."</td>
                            <td align='right'>".number_format($montoBase,2)."</td>
                            <td align='right'>".number_format($Messumapredial,2)."</td>
                            <td align='right'>".number_format($MessumaAct,2)."</td>
                            <td align='right'>".number_format($MessumaRec,2)."</td>

                            <td align='right'>".number_format($MessumaGastosEjecucion,2)."</td>
                            <td align='right'>".number_format($MessumaGastosEmbargo,2)."</td>

                           <td align='right'>".number_format($MessumaMulta,2)."</td>

                            <td align='right'>".number_format($MessumaOtrosGastos,2)."</td>
                            <td align='right'>".number_format($MessumaDescuentos,2)."</td>
                            <td align='right'>".number_format($MessumaTotalAnio,2)."</td>
                        </tr>";

        #$sumaMulta=CalcularMultasPredial($DatosLecturaActual['paid'], $sumaTotalAnio-$Descuentos);
        //Code quitar decimales, se van al descuento, quedan cantidades cerradas
        $Decimales=explode(".", ($sumaTotalAnio-$Descuentos==0?'0.0':$sumaTotalAnio-$Descuentos) );

        $resultados.="<tr><td align='right' colspan='2'><b>Totales</b></td>
                        <td align='right'><b>".number_format($sumapredial,2)."</b></td>
                        <td align='right'><b>".number_format($sumaAct,2)."</b></td>
                        <td align='right'><b>".number_format($sumaRec,2)."</b></td>

                        <td align='right'><b>".number_format($sumaGastosEjecucion,2)."</b></td>
                        <td align='right'><b>".number_format($sumaGastosEmbargo,2)."</b></td>

                        <td align='right'><b>".number_format($sumaMulta,2)."</b></td>
                        <td align='right'><b>".number_format($sumaOtrosGastos,2)."</b></td>
                        <td align='right'><b>".number_format($sumaDescuentos+$Descuentos,2)."</b></td>
                        <td align='right'><b>".number_format($sumaTotalAnio-$Descuentos,2)."</b></td>
                    </tr>
                    <tr>
                        <td align='right' colspan='10'><b>Descuento por Redondeo</b></td>
                        <td align='right'><b>- ". number_format(floatval('0.'.$Decimales[1]),2)."</b></td>
                    </tr>
                    <tr><td colspan='11'>&nbsp;</td></tr>";

        $rutaBarcode = 'https://suinpac.piacza.com.mx/lib/barcode2.php?f=png&text=' . (isset($DatosLecturaActual->paid) ? $DatosLecturaActual->paid : '') . '&codetype=Code128&orientation=Horizontal&size=40&print=false';

        $resultados.="
                    </table>
                    <table style='padding:-35px 0 0 0;margin:-10px 0 0 0;' border='0' width='787px'>
                  <tr>
                      <td colspan='11'><img width='787px' height='1px' src='". asset(Storage::url(env('IMAGES') . 'barraColores.png'))."'> <br /> </td>
                  </tr>
                  <tr>
                                            <td align='right' colspan='3'>  <div align='right'>
                                            <br>
                         <img src = '".$rutaBarcode."' alt='Codigo de Barras' ><br/><br/>
                    </div></td>
                    <td align='right' colspan='6'><br /><span style='font-size: 20px; font-weight: bold;'>Total a Pagar</span><br /><br /></td>
                    <td align='right'  colspan='2'><br /><b><span style='font-size: 20px; font-weight: bold;'>$ ".number_format($sumaTotalAnio-$Descuentos-floatval('0.'.$Decimales[1]),2)."</span></b><br /><br /></td>
                  </tr>

                  <tr>
                      <td colspan='11'><br />
                          <img width='787px' height='1px' src='".asset(Storage::url(env('IMAGES') . 'barraColores.png'))."'>
                      </td>
                  </tr>

                                       ".($ExisteDescuento=='NULL'? '':'<tr>
                                            <td colspan="11" class="text-right">
                                                <span style="color:red; font-size:12px;"><b>Estímulo Fiscal: '.$ExisteDescuento. ' </span>
                                            </td>
                                        </tr>').

            "<tr>
                        <td colspan='11' align='right'>
                            <span style='font-size:12px;'>Expedici&oacute;n: ".date('d/m/Y H:i:s')."</span> - <span style='font-size:12px;'>Vencimiento: ".date('d/m/Y',strtotime($VencimientoEdoCuenta))."</span> <span style='font-size:12px;'>Pendiente</span>
                        </td>
                    </tr>

                    <tr>
                        <td colspan='11' align='right'>
                            <span style='font-size:12px;'>Documento Informativo expedido a petici&oacute;n del Contribuyente, no es un requerimiento de pago.</span>
                        </td>
                    </tr>

                  ";
        return $resultados;
    }

    function EjecucionFiscalCajaV2(Request $request)
    {
        $cliente = $request->Cliente;
        $idPadron = $request->IdPadron;
        $tipoPadron = $request->TipoPadron;
        Funciones::selecionarBase($cliente);
        $DATOS = [];
        $DatosEjeucion = Funciones::ObtenValor("SELECT * FROM PadronEjecucionFiscalDetalle WHERE IdPadron='$idPadron' AND Estatus=1 ORDER BY Id DESC");
        if (isset($DatosEjeucion->result) && $DatosEjeucion->result == 'OK') {
            $DATOS['EXISTE'] = 1;
            $DATOS['CONCEPTOS'] = PredialController::obtenerDatosEjecucionFiscalV2($idPadron, $tipoPadron);
            $DATOS['ANIODESDE'] = substr($DatosEjeucion->AnioDesde, 0, 4);
            $DATOS['MESDESDE'] = substr($DatosEjeucion->AnioDesde, 4, 1);
        } else {
            $DATOS['EXISTE'] = 0;
        }
        return $DATOS;
    }

    public static function obtenerDatosEjecucionFiscalV2($idPadron, $tipoPadron)
    {
        $DATOS = [];
        $DatosEjeucionDetalle = Funciones::ObtenValor("SELECT SUM(GastoEjecucion) AS GastoEjecucionAnterior, SUM(GastoEmbargo) AS GastoEmbargoAnterior, SUM(Multas) AS MultasAnterior, SUM(OtrosGastos) AS OtrosGastosAnterior FROM PadronEjecucionFiscalDetalle efd WHERE efd.Estatus IN (1,2) AND efd.IdPadron='.$idPadron.';");
        $SQL = "SELECT * FROM MultaCategor_ia WHERE Categor_ia=" . $tipoPadron . "";
        $Resultado = DB::select($SQL);
        foreach ($Resultado as $Registro) {
            switch ($Registro->Multa) {
                case 2:
                    array_push($DATOS, ['Importe' => $DatosEjeucionDetalle->MultasAnterior, 'Concepto' => $Registro->Concepto, 'Categoria' => $Registro->Multa]);
                    break;
                case 4:
                    array_push($DATOS, ['Importe' => $DatosEjeucionDetalle->GastoEjecucionAnterior, 'Concepto' => $Registro->Concepto, 'Categoria' => $Registro->Multa]);
                    break;
                case 5:
                    array_push($DATOS, ['Importe' => $DatosEjeucionDetalle->GastoEmbargoAnterior, 'Concepto' => $Registro->Concepto, 'Categoria' => $Registro->Multa]);
                    break;
            }
        }
        return $DATOS;
    }


public static  function CalculaImpuestoBaseMinima($cliente, $tipopredio, $anio, $idconcepto, $ImporteConcepto){
    $BaseMinima=Funciones::ObtenValor("SELECT (BaseMinima) BaseMinima FROM BaseGravableMinima WHERE TipoPredio=$tipopredio AND EjercicioFiscal=$anio AND Cliente=$cliente", "BaseMinima");
    $datosConceptoAdicional = PredialController::ObtieneAdicionalesPredialAntesDeHistorial($idconcepto, $cliente, str_replace(",","",number_format((($BaseMinima)), 3)), $ImporteConcepto, $anio);
    return $datosConceptoAdicional['SumaCompleta'];
}

public static function ObtieneAdicionalesPredialAntesDeHistorial($Concepto, $cliente, $BaseCalculo, $ImporteConcepto, $ejercicio){

	$Datos=array();

	$Datos['baseCalculo']=$BaseCalculo;

	$ConsultaSelect = "SELECT DISTINCT c3.Importe as c3Importe, ra.id, ra.Descripci_on, ra.Cri, ra.PlanDeCuentas, ra.ConceptoCobro, ra.Porcentaje, ra.Proveedor, c2.AplicaIVA
	FROM ConceptoCobroCaja c
	INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto )
	INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales )
	INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente )
INNER JOIN RetencionesAdicionales ra ON (ra.id=c1.RetencionAdicional)
	WHERE   c3.Status=1 AND c2.AplicaEnSubtotal=0 AND c3.Cliente=".$cliente." AND c3.EjercicioFiscal=".$ejercicio." AND c2.Cliente=".$cliente." AND c.id = ".$Concepto."";
	#precode($ConsultaSelect,1);
	$ResultadoInserta = DB::select($ConsultaSelect);
	$i=0;
	//$Datos['Consumo']=$MetrosConsumidos;
	//$Datos['Cantidad']=$ImporteConcepto;
	 $Datos['Importe']=str_replace(",","",number_format((($BaseCalculo*$ImporteConcepto)/100) ,2 ));

	$Datos['Subtotal']=str_replace(",","", number_format((($BaseCalculo*$ImporteConcepto)/100) ,2 ));
	$sumaIVA          =str_replace(",","", number_format( (($BaseCalculo*$ImporteConcepto)/100) ,2 ));
	$Datos['sumaAdicionales']=0;

	foreach($ResultadoInserta as $filas){
		$i++;
		$ConceptoOperacion =str_replace(",","", number_format(($Datos['Importe']*floatval($filas->Porcentaje / 100 )  ),2));
		$ConceptoOperacion2=str_replace(",","",number_format(($Datos['Importe']*floatval($filas->Porcentaje / 100 )  ),2));

		$Datos['Adicional_'.$i]=$ConceptoOperacion;
		$Datos['sumaAdicionales']+=$ConceptoOperacion2;
		$Datos['Subtotal']+=$ConceptoOperacion2;
		if($filas->AplicaIVA==1){
			$sumaIVA+=$ConceptoOperacion2;
		}
	}

	$ConsultaSelectDespSubtotal = "SELECT DISTINCT ra.id, ra.Descripci_on, ra.Cri, ra.PlanDeCuentas, ra.ConceptoCobro, ra.Porcentaje, ra.Proveedor, c2.AplicaIVA
	FROM ConceptoCobroCaja c
	INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto )
	INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales )
	INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente )
INNER JOIN RetencionesAdicionales ra ON (ra.id=c1.RetencionAdicional)
	WHERE c2.AplicaEnSubtotal=1 AND c3.Cliente=".$cliente." AND c2.Cliente=".$cliente." AND c.id = ".$Concepto."";
	$ResultadoAdicionales = DB::select($ConsultaSelectDespSubtotal);
	//$i=0;
	//precode($ConsultaSelectDespSubtotal,1);
	foreach($ResultadoAdicionales as $filas){
		$i++;
		#precode($filas,1 );
		if($filas->AplicaIVA==1){

			$ConceptoOperacion =str_replace(",","",number_format(($sumaIVA*floatval($filas->Porcentaje / 100 )  ),2));
			$ConceptoOperacion2=str_replace(",","",number_format(($sumaIVA*floatval($filas->Porcentaje / 100 )  ),2));
		}else{
			$ConceptoOperacion =str_replace(",","",number_format(($Datos['Subtotal']*floatval($filas->Porcentaje / 100 )  ),2));
			$ConceptoOperacion2=str_replace(",","",number_format(($Datos['Subtotal']*floatval($filas->Porcentaje / 100 )  ),2));
		}

		$Datos['Adicional'.$i]=$ConceptoOperacion;
		$Datos['sumaAdicionales']+=$ConceptoOperacion2;
	}

	//$Datos['sumaAdicionales']=truncate_number($Datos['sumaAdicionales'],2 ));
	//echo "<br />ss:".$Datos['Subtotal']       =str_replace(",","", truncate_number(floatval(str_replace(",","",$Datos['Subtotal'])        ),2 ));
	$Datos['SumaCompleta']   = $Datos['sumaAdicionales'] + $Datos['Importe'] ;

	$Datos['cantidadAdicional']=$i;
#	precode($Datos,1,1);

	return ($Datos);
}

public static function buscarTipoDeslinde(Request $request){
    $cliente=$request->Cliente;
    $idPadron=$request->IdPadron;
    $anio=date("Y");
    Funciones::selecionarBase($cliente);

    $datosPadron=DB::select("SELECT *,  COALESCE( REPLACE(SuperficieTerreno, ',','')*(Indiviso/100),0) as SuperfieTerrenoIndiviso FROM Padr_onCatastral WHERE id=".$idPadron);
    #echo $ListadoDocumentos; exit;
    $Select=0;

    $totalConstruccion=Funciones::ObtenValor("SELECT COUNT(*) AS TotalConstrucciones FROM Padr_onConstruccionDetalle WHERE idPadron=".$idPadron." AND TipoConstruci_on!=264;", "TotalConstrucciones");

    $condition="";
    if($datosPadron[0]->TipoPredio== 13)
        $condition="";
    else
        $condition=$totalConstruccion==0? " Descripci_on LIKE '%Bald%' AND ":" Descripci_on LIKE '%Construidos%' AND ";
    #precode($totalConstruccion,1,1);

    $superficie=str_replace(",","",$datosPadron[0]->SuperfieTerrenoIndiviso);

    $datosConcepto= DB::select("SELECT id,Descripci_on,Desde,Hasta FROM ConceptoCobroCaja WHERE "
            .$condition."TipoPredio=".$datosPadron[0]->TipoPredio." AND (Desde<=".$superficie." AND Hasta>=".$superficie.") AND Descripci_on NOT LIKE 'Impuesto Predial%' AND CatalogoDocumento=3 AND id IN (
    SELECT Concepto FROM ConceptoRetencionesAdicionales WHERE id IN (
    SELECT ConceptoRetencionesAdicionales FROM ConceptoRetencionesAdicionalesCliente WHERE id IN (
    SELECT ConceptoRetencionesAdicionalesCliente FROM ConceptoAdicionales WHERE `Status`=1  AND Cliente=" .$cliente .
        " AND EjercicioFiscal=" . $anio."))) ");

   #precode($datosConcepto,1,1);

    return response()->json([
        'success' =>  1,
        'ConceptoCobroCajaDeslinde' =>  $datosConcepto[0],

    ], 200);
}




}
