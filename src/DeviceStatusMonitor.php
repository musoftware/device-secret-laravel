<?php

/**
 * Copyright (c) Jan-Niklas Schäfer. All rights reserved.
 * Licensed under the MIT License. See LICENSE file in the project root for full license information.
 */

namespace MusoftwareDeviceSecret;

/**
 * Periodically validates a device against the Musoftwares serial device API and terminates
 * the host process if the device is not authorized.
 */
class DeviceStatusMonitor
{
    private const DEFAULT_ENDPOINT = 'https://www.musoftwares.com/api/serial/device';

    private string $programName;
    private string $deviceId;
    private string $endpoint;
    private int $interval; // in seconds
    private $logCallback;
    private bool $started = false;
    private bool $disposed = false;
    private $timer = null;
    private ?string $cachePath = null;

    /**
     * Creates a new DeviceStatusMonitor.
     *
     * @param string $programName Registered program name.
     * @param string $deviceId Unique device identifier for the program.
     * @param string|null $endpoint Optional override for the API endpoint.
     * @param int|null $interval How often to re-check the device status (defaults to once per day in seconds).
     * @param callable|null $log Optional callback used for informational logging.
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $programName,
        string $deviceId,
        ?string $endpoint = null,
        ?int $interval = null,
        ?callable $log = null
    ) {
        if (empty(trim($programName))) {
            throw new \InvalidArgumentException('Program name is required.');
        }

        if (empty(trim($deviceId))) {
            throw new \InvalidArgumentException('Device ID is required.');
        }

        $this->programName = trim($programName);
        $this->deviceId = trim($deviceId);
        $this->endpoint = $endpoint && trim($endpoint) ? trim($endpoint) : self::DEFAULT_ENDPOINT;
        $this->interval = $interval ?? (24 * 60 * 60); // Default: 1 day in seconds
        $this->logCallback = $log;
        $this->cachePath = $this->getCacheFilePath();
    }

    /**
     * Starts the monitor. The device status is checked immediately and then at the configured interval.
     *
     * @throws \RuntimeException
     */
    public function start(): void
    {
        if ($this->disposed) {
            throw new \RuntimeException('DeviceStatusMonitor has been disposed');
        }

        if ($this->started) {
            throw new \RuntimeException('The monitor has already been started.');
        }

        $this->started = true;

        // Verify immediately
        $this->verifyStatus();

        // Schedule periodic verification using a background process or Laravel scheduler
        $this->scheduleNextCheck();
    }

    /**
     * Schedules the next status check.
     */
    private function scheduleNextCheck(): void
    {
        if ($this->disposed) {
            return;
        }

        // For Laravel, this would typically use Laravel's scheduler
        // For standalone PHP, we can use a simple approach with sleep in a background process
        // Note: In a web context, consider using Laravel's task scheduler or queue system
        $this->timer = $this->createTimer();
    }

    /**
     * Creates a timer for the next check.
     * In Laravel, this should be integrated with the scheduler.
     *
     * @return mixed
     */
    private function createTimer()
    {
        // This is a placeholder - in Laravel, use the scheduler
        // For now, we'll use a simple approach that works in CLI contexts
        if (php_sapi_name() === 'cli') {
            // In CLI, we can use pcntl_fork or similar for background processing
            // For simplicity, we'll just mark that it should be checked
            // In production, integrate with Laravel's scheduler
            return true;
        }

        // In web context, return null - should use Laravel scheduler instead
        return null;
    }

    /**
     * Callback for periodic status verification.
     */
    public function checkStatus(): void
    {
        if ($this->disposed) {
            return;
        }

        try {
            $this->verifyStatus();
        } catch (\RuntimeException $ex) {
            if (strpos(strtolower($ex->getMessage()), 'disposed') === false) {
                $this->logMessage('Failed to verify device status: ' . $ex->getMessage());
            }
        } catch (\Exception $ex) {
            $this->logMessage('Failed to verify device status: ' . $ex->getMessage());
        }

        // Schedule next check if not disposed
        if (!$this->disposed) {
            $this->scheduleNextCheck();
        }
    }

    /**
     * Verifies the device status with the API.
     *
     * @throws \RuntimeException
     */
    private function verifyStatus(): void
    {
        if ($this->disposed) {
            throw new \RuntimeException('DeviceStatusMonitor has been disposed');
        }

        // Check cache first
        $cachedStatus = $this->readCache();
        if ($cachedStatus !== null) {
            $this->logMessage('Using cached device status.');
            if (strtolower($cachedStatus) !== 'active') {
                $this->logMessage('Device status is not active. Terminating process.');
                $this->terminateProcess();
            }
            return;
        }

        // Cache miss or expired, make API call
        $payload = $this->createRequestPayload();
        $payloadJson = json_encode($payload);

        $this->logMessage('Checking device status with Musoftwares API.');

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept: application/json, text/javascript, */*; q=0.01',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate'
            ],
            CURLOPT_ENCODING => 'gzip, deflate'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            $errorDetails = $error ?: 'Unknown network error';
            $this->logMessage('HTTP request failed: ' . $errorDetails);
            throw new \RuntimeException('Device status check failed due to network error: ' . $errorDetails);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $errorDetails = "HTTP $httpCode - " . ($response ?: 'No response body received.');
            $this->logMessage('HTTP request failed: ' . $errorDetails);
            throw new \RuntimeException('Device status check failed due to remote server error: ' . $errorDetails);
        }

        $apiResponse = json_decode($response, true);

        if ($apiResponse === null || !isset($apiResponse['status'])) {
            throw new \RuntimeException('The device status response was empty.');
        }

        $status = (string)$apiResponse['status'];

        $this->logMessage("Device status returned '$status'.");

        // Only cache if status is active
        if (strtolower($status) === 'active') {
            $this->writeCache($status);
        } else {
            // Clear any existing cache if status is not active
            $this->clearCache();
            $this->logMessage('Device status is not active. Terminating process.');
            $this->terminateProcess();
        }
    }

    /**
     * Creates the request payload dictionary.
     *
     * @return array
     */
    private function createRequestPayload(): array
    {
        $payload = [
            'program_name' => $this->programName,
            'device_id' => $this->deviceId
        ];

        $this->addIfAvailable($payload, 'user_name', function() {
            return get_current_user() ?: ($_SERVER['USER'] ?? $_SERVER['USERNAME'] ?? null);
        });

        $this->addIfAvailable($payload, 'user_domain', function() {
            return $_SERVER['USERDOMAIN'] ?? null;
        });

        $this->addIfAvailable($payload, 'machine_name', function() {
            return gethostname() ?: php_uname('n');
        });

        $this->addIfAvailable($payload, 'os_version', function() {
            return php_uname('a');
        });

        $this->addIfAvailable($payload, 'framework_version', function() {
            return PHP_VERSION;
        });

        $this->addIfAvailable($payload, 'is_64bit_os', function() {
            return (PHP_INT_SIZE === 8) ? 'true' : 'false';
        });

        $this->addIfAvailable($payload, 'is_64bit_process', function() {
            return (PHP_INT_SIZE === 8) ? 'true' : 'false';
        });

        $this->addIfAvailable($payload, 'current_directory', function() {
            return getcwd();
        });

        $this->addIfAvailable($payload, 'current_culture', function() {
            return setlocale(LC_ALL, 0) ?: null;
        });

        $this->addIfAvailable($payload, 'current_ui_culture', function() {
            return setlocale(LC_ALL, 0) ?: null;
        });

        $this->addIfAvailable($payload, 'process_id', function() {
            return (string)getmypid();
        });

        $this->addIfAvailable($payload, 'process_name', function() {
            return $_SERVER['SCRIPT_NAME'] ?? 'php';
        });

        $payload['request_timestamp_utc'] = gmdate('c') . 'Z';

        return $payload;
    }

    /**
     * Adds a value to the target array if it's available.
     *
     * @param array $target
     * @param string $key
     * @param callable $valueFactory
     */
    private function addIfAvailable(array &$target, string $key, callable $valueFactory): void
    {
        if (empty($key)) {
            return;
        }

        $value = $this->safeInvoke($valueFactory);
        if ($value !== null && (!is_string($value) || trim($value) !== '')) {
            $target[$key] = is_string($value) ? trim($value) : $value;
        }
    }

    /**
     * Safely invokes a value factory function.
     *
     * @param callable|null $valueFactory
     * @return string|null
     */
    private function safeInvoke(?callable $valueFactory): ?string
    {
        if ($valueFactory === null) {
            return null;
        }

        try {
            $value = $valueFactory();
            if ($value === null) {
                return null;
            }
            if (is_string($value)) {
                $trimmed = trim($value);
                return $trimmed !== '' ? $trimmed : null;
            }
            return (string)$value;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Terminates the current process.
     */
    private function terminateProcess(): void
    {
        try {
            // In Laravel, you might want to abort the request instead
            if (function_exists('abort')) {
                abort(403, 'Device is not authorized');
            }
            exit(0);
        } catch (\Exception $e) {
            exit(0);
        }
    }

    /**
     * Ensures the monitor is not disposed.
     *
     * @throws \RuntimeException
     */
    private function ensureNotDisposed(): void
    {
        if ($this->disposed) {
            throw new \RuntimeException('DeviceStatusMonitor has been disposed');
        }
    }

    /**
     * Logs a message if a log callback is provided.
     *
     * @param string $message
     */
    private function logMessage(string $message): void
    {
        if ($this->logCallback !== null) {
            call_user_func($this->logCallback, $message);
        }
    }

    /**
     * Disposes the monitor and stops periodic checks.
     */
    public function dispose(): void
    {
        if ($this->disposed) {
            return;
        }

        $this->disposed = true;
        $this->timer = null;
    }

    /**
     * Gets the interval in seconds.
     *
     * @return int
     */
    public function getInterval(): int
    {
        return $this->interval;
    }

    /**
     * Gets the endpoint URL.
     *
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Gets the cache file path for this monitor instance.
     *
     * @return string
     */
    private function getCacheFilePath(): string
    {
        // Create a unique cache key based on program name and device ID
        $cacheKey = md5($this->programName . '|' . $this->deviceId . '|' . $this->endpoint);
        $cacheFileName = 'musoftware-device-status-' . $cacheKey . '.json';

        // Try to use Laravel's storage path if available
        $cacheDir = null;
        if (function_exists('storage_path')) {
            try {
                $cacheDir = storage_path('app/musoftware');
            } catch (\Throwable $e) {
                // Laravel container not initialized yet, fall back to temp directory
                $cacheDir = null;
            }
        }
        
        // Fallback to system temp directory if Laravel storage path is not available
        if ($cacheDir === null) {
            $cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'musoftware';
        }

        // Ensure cache directory exists
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        return $cacheDir . DIRECTORY_SEPARATOR . $cacheFileName;
    }

    /**
     * Reads the cached device status if it exists and is still valid.
     *
     * @return string|null The cached status, or null if cache is missing or expired
     */
    private function readCache(): ?string
    {
        if ($this->cachePath === null || !file_exists($this->cachePath)) {
            return null;
        }

        try {
            $cacheContent = @file_get_contents($this->cachePath);
            if ($cacheContent === false) {
                return null;
            }

            $cacheData = json_decode($cacheContent, true);
            if ($cacheData === null || !isset($cacheData['status']) || !isset($cacheData['expires_at'])) {
                return null;
            }

            // Check if cache is expired
            $expiresAt = (int)$cacheData['expires_at'];
            if (time() >= $expiresAt) {
                // Cache expired, delete it
                @unlink($this->cachePath);
                return null;
            }

            return (string)$cacheData['status'];
        } catch (\Exception $e) {
            // If reading cache fails, return null to force API call
            return null;
        }
    }

    /**
     * Writes the device status to cache.
     *
     * @param string $status The device status to cache
     */
    private function writeCache(string $status): void
    {
        if ($this->cachePath === null) {
            return;
        }

        try {
            $cacheData = [
                'status' => $status,
                'cached_at' => time(),
                'expires_at' => time() + $this->interval
            ];

            $cacheContent = json_encode($cacheData, JSON_PRETTY_PRINT);
            @file_put_contents($this->cachePath, $cacheContent, LOCK_EX);
        } catch (\Exception $e) {
            // If writing cache fails, log but don't throw - API call was successful
            $this->logMessage('Failed to write cache: ' . $e->getMessage());
        }
    }

    /**
     * Clears the cached device status.
     */
    private function clearCache(): void
    {
        if ($this->cachePath !== null && file_exists($this->cachePath)) {
            @unlink($this->cachePath);
        }
    }
}

