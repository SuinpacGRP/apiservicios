<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CelaRepositorio extends Model
{
    protected $table = 'CelaRepositorio';

    protected $fillable = ['idRepositorio'];

    public $timestamps = false;
}
