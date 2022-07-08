<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class PadronAguaLectura extends Model
{
    protected $table = 'Padr_onDeAguaLectura';

    protected $fillable = ['id', 'TipoToma'];

    public $timestamps = false;
}