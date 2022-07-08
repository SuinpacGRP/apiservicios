<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class ConceptoAdicionalCotizacion extends Model {
    
    protected $table = 'ConceptoAdicionalesCotizaci_on';

    protected $fillable = ['id'];

    public $timestamps = false;
}
