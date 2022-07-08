<?php

namespace App\ModelosNotarios;

use Illuminate\Database\Eloquent\Model;

class TramitesISAINotarios extends Model
{
    protected $table = 'Padr_onCatastralTramitesISAINotarios';
    
    protected $fillable = ['id'];

    public $timestamps = false;
}
