<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class PadronAguaPotable extends Model
{
    #protected $connection = 'mysql';
    #protected $connection = 'piacza_suinpac2';

    protected $table = 'Padr_onAguaPotable';

    protected $fillable = ['id'];

    public $timestamps = false;
}
