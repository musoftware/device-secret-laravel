<?php

namespace MusoftwareDeviceSecret;

use Illuminate\Support\ServiceProvider;

class MusoftwareDeviceSecretServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/musoftware-device-secret.php',
            'musoftware-device-secret'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/musoftware-device-secret.php' => config_path('musoftware-device-secret.php'),
        ], 'config');
    }
}

