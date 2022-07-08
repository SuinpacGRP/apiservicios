<?php

namespace App\Checador;

use Illuminate\Database\Eloquent\Model;

class AsistenciaHorario extends Model
{
    protected $table = 'AsistenciaHorario';

    #protected $fillable = [ 'idAcceso, FechaDeAcceso, idUsuario, Tabla, Acci_on'];

    public $timestamps = false;
}
