<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class Municipio extends Model {
    
    protected $table = 'Municipio';

    protected $fillable = ['id'];

    public $timestamps = false;
}
