<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class Contribuyente extends Model {
    
    protected $table = 'Contribuyente';

    protected $fillable = ['id'];

    public $timestamps = false;
}
