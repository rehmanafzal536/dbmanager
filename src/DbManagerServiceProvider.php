<?php

namespace Devtoolkit\DbManager;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;

class DbManagerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/dbmanager.php', 'dbmanager');

        $this->publishes([
            __DIR__.'/../config/dbmanager.php' => config_path('dbmanager.php'),
        ], 'dbmanager-config');

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('dbmanager.auth', DbManagerAuthMiddleware::class);

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'dbmanager');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/dbmanager'),
        ], 'dbmanager-views');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/dbmanager.php', 'dbmanager');
    }
}
