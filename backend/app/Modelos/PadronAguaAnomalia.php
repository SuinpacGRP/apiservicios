<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class PadronAguaAnomalia extends Model
{
    protected $table = 'Padr_onAguaCatalogoAnomalia';

    protected $fillable = ['id'];

    public $timestamps = false;
}
