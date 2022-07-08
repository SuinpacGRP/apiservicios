<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class XML extends Model {
    
    protected $table = 'XML';

    protected $fillable = ['id'];

    public $timestamps = false;
}
