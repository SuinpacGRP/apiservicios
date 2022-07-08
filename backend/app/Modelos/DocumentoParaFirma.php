<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class DocumentoParaFirma extends Model {
    
    protected $table = 'DocumentoParaFirma';

    protected $fillable = ['id'];

    public $timestamps = false;
}
