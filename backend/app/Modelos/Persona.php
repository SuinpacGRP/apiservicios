<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    protected $table = 'Persona';

    protected $primaryKey = 'id';

    #protected $fillable = [ 'id', ];

    public $timestamps = false;

    public function datosFiscales(){
        return $this->hasOne('App\Modelos\DatosFiscales', 'id', 'DatosFiscales');
    }
}
