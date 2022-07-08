<?php

namespace App\Http\Controllers\PortalNotarios;

use App\Funciones;
use App\Http\Controllers\Controller;
use App\PadronAguaLectura;
use App\PadronAguaPotable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use \Illuminate\Support\Facades\Config;

class PortalNotariosController extends Controller
{
    /**
     * ! Se asigna el middleware  al costructor
     */
    public function __construct()
    {
        #$this->middleware( 'jwt', ['except' => ['getToken']] );
       
    }
    

}
