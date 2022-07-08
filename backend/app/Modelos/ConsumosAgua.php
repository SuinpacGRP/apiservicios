<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;

class ConsumosAgua extends Model
{
    protected $table = 'ConsumosAgua';

    protected $fillable = ['id'];

    public $timestamps = false;
}
