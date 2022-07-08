<?php

namespace App\Checador;

use Illuminate\Database\Eloquent\Model;

class CatalogoIncidencia extends Model
{
    protected $table = 'Cat_alogoIncidencia';

    #protected $fillable = [ 'idAcceso, FechaDeAcceso, idUsuario, Tabla, Acci_on'];

    public $timestamps = false;
}
