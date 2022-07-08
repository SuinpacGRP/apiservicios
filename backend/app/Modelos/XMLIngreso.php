<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class XMLIngreso extends Model {
    
    protected $table = 'XMLIngreso';

    protected $fillable = ['id'];

    public $timestamps = false;
}