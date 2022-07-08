<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class DatosFiscales extends Model
{
    protected $table = 'DatosFiscales';

    protected $primaryKey = 'id';

    public $timestamps = false;

    public function persona(){
        return $this->belongsTo('App\Modelos\Persona', 'id', 'DatosFiscales');
    }
}
