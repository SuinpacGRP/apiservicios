<?php

namespace App\ModelosNotarios;

use Illuminate\Database\Eloquent\Model;

class PadronCatastral extends Model
{
    protected $table = 'Padr_onCatastral';
    
    protected $fillable = ['id'];

    public $timestamps = false;
}
