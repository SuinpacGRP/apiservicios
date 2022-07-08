<?php

namespace App\ModelosNotarios;

use Illuminate\Database\Eloquent\Model;

class TrmitesISAINotarios extends Model
{
    
    protected $table = 'Padr_onCatastralTramitesISAINotarios';
    
    protected $fillable = ['id'];

    public $timestamps = false;    
}
