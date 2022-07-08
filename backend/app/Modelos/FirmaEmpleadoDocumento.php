<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class FirmaEmpleadoDocumento extends Model {
    
    protected $table = 'FirmaEmpleadoDocumento';

    protected $fillable = ['id'];

    public $timestamps = false;
}
