<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Program Name
    |--------------------------------------------------------------------------
    |
    | The default program name to use when creating DeviceStatusMonitor instances.
    |
    */
    'default_program_name' => env('MUSOFTWARE_PROGRAM_NAME', 'Laravel Application'),

    /*
    |--------------------------------------------------------------------------
    | Default Device ID
    |--------------------------------------------------------------------------
    |
    | The default device ID to use. If not set, you must provide it when
    | creating DeviceStatusMonitor instances.
    |
    */
    'default_device_id' => env('MUSOFTWARE_DEVICE_ID', null),

    /*
    |--------------------------------------------------------------------------
    | API Endpoint
    |--------------------------------------------------------------------------
    |
    | The API endpoint for device status checks.
    |
    */
    'endpoint' => env('MUSOFTWARE_ENDPOINT', 'https://www.mu-hub.com/api/serial/device'),

    /*
    |--------------------------------------------------------------------------
    | Check Interval
    |--------------------------------------------------------------------------
    |
    | How often to check the device status (in seconds).
    | Default: 86400 (24 hours)
    |
    */
    'interval' => env('MUSOFTWARE_INTERVAL', 86400),

    /*
    |--------------------------------------------------------------------------
    | Enable Monitoring
    |--------------------------------------------------------------------------
    |
    | Whether to enable device status monitoring by default.
    |
    */
    'enabled' => env('MUSOFTWARE_ENABLED', false),
];

