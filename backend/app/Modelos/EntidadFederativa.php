<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class EntidadFederativa extends Model {
    
    protected $table = 'EntidadFederativa';

    protected $fillable = ['id'];

    public $timestamps = false;
}
