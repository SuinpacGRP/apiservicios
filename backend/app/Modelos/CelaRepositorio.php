<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class CelaRepositorio extends Model {
    
    protected $table = 'CelaRepositorio';

    protected $fillable = ['id'];

    public $timestamps = false;
}
