<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $table = 'CelaUsuario';

    protected $primaryKey = 'idUsuario';

    protected $fillable = [ 'Usuario', 'Contrase_na', ];

    protected $hidden = [
        'idUsuario','Contrase_na', 'Cliente', 'Usuario', 'RolTmp', 'ClaveImap',
        'idUsuario', 'Rol', 'CajeroDeCobro', 'AreaRecaudadora', 'CajaDeCobro',
        'PorcentajeLimiteDescuento', 'ImporteLimiteDescuento', 'UsuarioImap'
    ];

    public $timestamps = false;

    /**
     * TODO: Sobreescribimos el metodo para cambiar el nombre del campo password a Contrase_na
     * @return fiel/Contrase_na
     */
    public function getAuthPassword()
    {
        return $this->Contrase_na;
    }
    
    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    
    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * The attributes that should be hidden for arrays.
     * @var array
     */
    /*protected $hidden = [
        'password', 'remember_token',
    ];*/

    #protected $casts = ['Usuario' => 'string','Contrase_na' => 'string'];
}