<?php

namespace App\Modelos\PadronComercios;

use Illuminate\Database\Eloquent\Model;

class PadronSimple extends Model
{
    protected $table = 'PadronSimple';
    protected $fillable = ['id'];

    public $timestamps = false;
}
