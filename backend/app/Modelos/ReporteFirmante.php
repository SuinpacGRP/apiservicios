<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class ReporteFirmante extends Model {
    
    protected $table = 'Reporte_Firmante';

    protected $fillable = ['id'];

    public $timestamps = false;
}
