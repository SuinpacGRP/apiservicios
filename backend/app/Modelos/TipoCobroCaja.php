<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class TipoCobroCaja extends Model {
    
    protected $table = 'TipoCobroCaja';

    protected $fillable = ['id'];

    public $timestamps = false;
}
