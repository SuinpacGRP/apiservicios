<?php

namespace App\Http\Controllers;

use App\General;
use App\Funciones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConexionController extends Controller
{
    
    function conectarBase(Request $request){
        $Cliente = $request->Cliente;

        Funciones::selecionarBase($Cliente);

        $datos = DB::select("SELECT COUNT(id) AS Registros FROM Padr_onAguaPotable");

        return $datos;
    }

    function nombreBase(){
        $datos = DB::select("SELECT COUNT(id) AS Registros FROM Padr_onAguaPotable");

        return $datos;
        #return DB::connection()->getDataBaseName();
    }

}
