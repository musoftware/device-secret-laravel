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

        // Auto-publish config if it doesn't exist
        $configPath = config_path('musoftware-device-secret.php');
        if (!file_exists($configPath)) {
            $this->publishes([
                __DIR__ . '/../config/musoftware-device-secret.php' => $configPath,
            ], 'config');
            
            // Ensure config directory exists
            if (!is_dir(config_path())) {
                mkdir(config_path(), 0755, true);
            }
            
            // Copy the config file
            copy(
                __DIR__ . '/../config/musoftware-device-secret.php',
                $configPath
            );
        }
    }
}

