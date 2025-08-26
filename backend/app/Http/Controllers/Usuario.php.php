<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Usuario extends Authenticatable implements JWTSubject
{
    protected $table = 'CelaUsuario';
    protected $connection = 'mysql_secundaria';
    protected $primaryKey = 'IdUsuario'; // Cambia si tu llave primaria tiene otro nombre
    public $timestamps = false; // Si tu tabla no tiene created_at y updated_at

    protected $fillable = ['Usuario', 'Contrase_na', 'EstadoActual']; // campos que usas

    // Métodos requeridos por JWTSubject:
    public function getJWTIdentifier()
    {
        return $this->getKey(); // normalmente el id del usuario
    }

    public function getJWTCustomClaims()
    {
        return []; // aquí puedes poner claims extras si quieres
    }
}
