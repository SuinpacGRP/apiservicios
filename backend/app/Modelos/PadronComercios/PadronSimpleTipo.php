<?php

namespace App\Modelos\PadronComercios;

use Illuminate\Database\Eloquent\Model;

class PadronSimpleTipo extends Model
{
    protected $table = 'PadronSimpleTipo';
    protected $fillable = ['id'];

    public $timestamps = false;
}
