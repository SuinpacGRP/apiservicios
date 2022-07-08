<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model {
    
    protected $table = 'Cotizaci_on';

    protected $fillable = ['id'];

    public $timestamps = false;
}
