<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class CatalogoUnidad  extends Model {
    
    protected $table = 'Catalogo_Unidad';

    protected $fillable = ['id'];

    public $timestamps = false;
}
