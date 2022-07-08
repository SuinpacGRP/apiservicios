<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model {
    
    protected $table = 'Cliente';

    protected $fillable = ['id'];

    public $timestamps = false;
}
