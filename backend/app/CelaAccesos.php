<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CelaAccesos extends Model
{
    protected $table = 'CelaAccesos';

    protected $fillable = [ 'idAcceso, FechaDeAcceso, idUsuario, Tabla, Acci_on'];

    public $timestamps = false;
}
