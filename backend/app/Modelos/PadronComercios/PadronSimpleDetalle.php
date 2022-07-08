<?php

namespace App\Modelos\PadronComercios;

use Illuminate\Database\Eloquent\Model;

class PadronSimpleDetalle extends Model
{
    protected $table = 'PadronSimpleDetalle';
    protected $fillable = ['id'];

    public $timestamps = false;
}
