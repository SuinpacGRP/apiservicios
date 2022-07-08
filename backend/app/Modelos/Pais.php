<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class Pais extends Model {
    
    protected $table = 'Pa_is';

    protected $fillable = ['id'];

    public $timestamps = false;
}
