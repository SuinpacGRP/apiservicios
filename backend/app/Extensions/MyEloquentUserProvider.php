<?php
namespace App\Extensions;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;

class MyEloquentUserProvider extends EloquentUserProvider
{
    /**
     * ? Valida un usuario con los datos recibidos.
     * TODO: Aqui se evalua el campo password con encriptacion md5() en lugar de bcrypt()
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(UserContract $user, array $credentials)
    {
        $plain = $credentials['password'];
        $hashed_value = $user->getAuthPassword();
        return $hashed_value == md5($plain);
    }
}