<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class EncabezadoContabilidad extends Model {
    
    protected $table = 'EncabezadoContabilidad';

    protected $fillable = ['id'];

    public $timestamps = false;
}
