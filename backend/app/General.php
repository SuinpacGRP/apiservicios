<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class General extends Model
{
    protected $table = 'Cliente';
    
    protected $fillable = ['id'];

    public $timestamps = false;
}
