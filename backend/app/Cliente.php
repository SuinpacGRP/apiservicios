<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'Cliente';

    protected $fillable = ['id', 'Descripci_on'];

    public $timestamps = false;
}
