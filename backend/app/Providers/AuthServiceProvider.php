<?php

namespace App\Providers;

use App\Extensions\MyEloquentUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        #'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     * TODO: Registrammos un Provider Llamado custom_user Usando MyeloquentUserProvider 
     * TODO: Para Usar Autenticacion Personalizada
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Auth::provider('custom_user', function ($app, array $config) {
            $model = $app['config']['auth.providers.users.model'];
            return new MyEloquentUserProvider($app['hash'], $model);
        });
    }
}