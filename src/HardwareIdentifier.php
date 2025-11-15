<?php

/**
 * Copyright (c) Musoftware. All rights reserved.
 * Licensed under the MIT License. See LICENSE file in the project root for full license information.
 */

namespace MusoftwareDeviceSecret;

/**
 * Interface that abstracts an OS specific implementation for creating characteristics of the current computer.
 */
interface ComputerCharacteristicsInterface
{
    /**
     * Gets a list of characteristics for the current computer.
     *
     * @return \Generator|array
     */
    public function getCharacteristicsForCurrentComputer();
}

/**
 * Class that provides methods for generating / verifying hardware identifiers.
 */
class HardwareIdentifier
{
    private const PART_SIZE = 8;
    private const SEPARATOR = '-';
    private const CHECKSUM_LENGTH = 4;
    public const NO_HARDWARE_IDENTIFIER = 'NO_HARDWARE_ID';

    private static ?CheckSumAppender $checkSumAppender = null;
    private static ?Encoder $encoder = null;
    private static ?ComputerCharacteristicsInterface $computerCharacteristics = null;

    /**
     * Initialize static properties.
     */
    private static function initialize(): void
    {
        if (self::$checkSumAppender === null) {
            self::$checkSumAppender = new CheckSumAppender(
                self::SEPARATOR,
                new CheckSum(Constants::VALID_CHARACTERS, self::CHECKSUM_LENGTH)
            );
        }

        if (self::$encoder === null) {
            self::$encoder = new Encoder(Constants::VALID_CHARACTERS, self::PART_SIZE);
        }
    }

    /**
     * Sets the ComputerCharacteristics implementation to use.
     *
     * @param ComputerCharacteristicsInterface $computerCharacteristics
     */
    public static function setComputerCharacteristics(ComputerCharacteristicsInterface $computerCharacteristics): void
    {
        self::$computerCharacteristics = $computerCharacteristics;
    }

    /**
     * Gets the hardware identifier for the current computer.
     * Automatically uses CrossPlatformComputerCharacteristics if none is set.
     *
     * @return string
     * @throws \RuntimeException
     */
    public static function forCurrentComputer(): string
    {
        self::initialize();

        // Auto-initialize with CrossPlatformComputerCharacteristics if not set
        if (self::$computerCharacteristics === null) {
            self::$computerCharacteristics = new CrossPlatformComputerCharacteristics();
        }

        // Collect and filter characteristics
        $rawCharacteristics = [];
        foreach (self::$computerCharacteristics->getCharacteristicsForCurrentComputer() as $characteristic) {
            $value = is_string($characteristic) ? trim($characteristic) : (string)$characteristic;
            
            // Filter out empty, invalid, or placeholder values
            if (empty($value) || 
                $value === 'To be filled by O.E.M.' || 
                $value === 'Default string' ||
                stripos($value, 'not available') !== false ||
                stripos($value, 'not specified') !== false ||
                strlen($value) < 3) {
                continue;
            }
            
            $rawCharacteristics[] = $value;
        }

        // Remove duplicates while preserving order
        $uniqueCharacteristics = [];
        $seenHashes = [];
        foreach ($rawCharacteristics as $characteristic) {
            $hash = md5($characteristic, true);
            $hashKey = bin2hex($hash);
            
            // Only add if we haven't seen this hash before
            if (!isset($seenHashes[$hashKey])) {
                $uniqueCharacteristics[] = $characteristic;
                $seenHashes[$hashKey] = true;
            }
        }

        // Add fallback characteristics if we don't have enough unique ones
        if (count($uniqueCharacteristics) < 4) {
            $fallbacks = self::getFallbackCharacteristics();
            foreach ($fallbacks as $fallback) {
                if (empty($fallback)) {
                    continue;
                }
                
                $hash = md5($fallback, true);
                $hashKey = bin2hex($hash);
                
                // Only add if not already present
                if (!isset($seenHashes[$hashKey])) {
                    $uniqueCharacteristics[] = $fallback;
                    $seenHashes[$hashKey] = true;
                    
                    // Stop if we have enough characteristics
                    if (count($uniqueCharacteristics) >= 6) {
                        break;
                    }
                }
            }
        }

        // Ensure we have at least one characteristic
        if (empty($uniqueCharacteristics)) {
            $uniqueCharacteristics[] = gethostname() ?: php_uname('n') ?: 'unknown';
        }

        // Encode each unique characteristic
        $encodedCharacteristics = [];
        foreach ($uniqueCharacteristics as $characteristic) {
            $bytes = $characteristic;
            $md5Hash = md5($bytes, true); // true = raw binary output
            $encodedCharacteristics[] = self::$encoder->encode($md5Hash);
        }

        $hardwareKey = implode(self::SEPARATOR, $encodedCharacteristics);
        return self::$checkSumAppender->append($hardwareKey);
    }

    /**
     * Gets fallback characteristics when hardware IDs are not available.
     * These are used to ensure uniqueness even in hosting environments.
     *
     * @return array
     */
    private static function getFallbackCharacteristics(): array
    {
        $fallbacks = [];

        // Hostname (usually unique per server)
        $hostname = gethostname();
        if ($hostname) {
            $fallbacks[] = $hostname;
        }

        // System uname information
        $unameN = php_uname('n');
        if ($unameN && $unameN !== $hostname) {
            $fallbacks[] = $unameN;
        }

        // Machine type
        $unameM = php_uname('m');
        if ($unameM) {
            $fallbacks[] = $unameM;
        }

        // OS release
        $unameR = php_uname('r');
        if ($unameR) {
            $fallbacks[] = $unameR;
        }

        // MAC address (first available network interface)
        $macAddress = self::getMacAddress();
        if ($macAddress) {
            $fallbacks[] = $macAddress;
        }

        // Server name from $_SERVER if available
        if (isset($_SERVER['SERVER_NAME']) && !empty($_SERVER['SERVER_NAME'])) {
            $fallbacks[] = $_SERVER['SERVER_NAME'];
        }

        // Document root (can be unique per installation)
        if (isset($_SERVER['DOCUMENT_ROOT']) && !empty($_SERVER['DOCUMENT_ROOT'])) {
            $fallbacks[] = $_SERVER['DOCUMENT_ROOT'];
        }

        return $fallbacks;
    }

    /**
     * Gets the MAC address of the first available network interface.
     *
     * @return string|null
     */
    private static function getMacAddress(): ?string
    {
        $osFamily = PHP_OS_FAMILY;

        if ($osFamily === 'Windows') {
            // Try to get MAC address via getmac command
            $output = @shell_exec('getmac /fo csv /nh 2>&1');
            if ($output) {
                $lines = explode("\n", trim($output));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }
                    // Parse CSV format: "Connection Name","Network Adapter","Physical Address","Transport Name"
                    if (preg_match('/"([0-9A-F]{2}[:-][0-9A-F]{2}[:-][0-9A-F]{2}[:-][0-9A-F]{2}[:-][0-9A-F]{2}[:-][0-9A-F]{2})"/i', $line, $matches)) {
                        return strtoupper(str_replace('-', ':', $matches[1]));
                    }
                }
            }
        } elseif ($osFamily === 'Linux') {
            // Try to get MAC address from /sys/class/net
            $netDirs = @glob('/sys/class/net/*/address');
            if ($netDirs) {
                foreach ($netDirs as $netFile) {
                    // Skip loopback and virtual interfaces
                    if (strpos($netFile, '/lo/') !== false || 
                        strpos($netFile, '/docker') !== false ||
                        strpos($netFile, '/veth') !== false) {
                        continue;
                    }
                    
                    $mac = @trim(@file_get_contents($netFile));
                    if ($mac && preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/i', $mac)) {
                        return strtoupper($mac);
                    }
                }
            }

            // Fallback: try ip command
            $output = @shell_exec('ip link show 2>/dev/null | grep -E "^\s+link/ether" | head -1');
            if ($output && preg_match('/([0-9a-f]{2}:){5}[0-9a-f]{2}/i', $output, $matches)) {
                return strtoupper($matches[0]);
            }
        } elseif ($osFamily === 'Darwin') {
            // macOS: use networksetup or ifconfig
            $output = @shell_exec('ifconfig 2>/dev/null | grep -E "^\s+ether" | head -1');
            if ($output && preg_match('/([0-9a-f]{2}:){5}[0-9a-f]{2}/i', $output, $matches)) {
                return strtoupper($matches[0]);
            }
        }

        return null;
    }

    /**
     * Checks if at least 2 of 4 hardware components are valid.
     * Note: CheckSum is ignored by this check.
     *
     * @param string $hardwareIdentifier The hardware identifier to check
     * @return bool True if at least 2 of 4 hardware components are valid.
     * @throws \RuntimeException
     */
    public static function isValidForCurrentComputer(string $hardwareIdentifier): bool
    {
        if ($hardwareIdentifier === self::NO_HARDWARE_IDENTIFIER) {
            return true;
        }
        return self::arePartialEqual($hardwareIdentifier, self::forCurrentComputer());
    }

    /**
     * Returns true if at least 2 of the sub codes are equal.
     *
     * @param string $hardwareIdentifier1 The first hardware identifier to compare.
     * @param string $hardwareIdentifier2 The second hardware identifier to compare.
     * @return bool
     * @throws \InvalidArgumentException
     */
    public static function arePartialEqual(string $hardwareIdentifier1, string $hardwareIdentifier2): bool
    {
        if ($hardwareIdentifier1 === null) {
            throw new \InvalidArgumentException('hardwareIdentifier1 cannot be null');
        }
        if ($hardwareIdentifier2 === null) {
            throw new \InvalidArgumentException('hardwareIdentifier2 cannot be null');
        }

        $split1 = explode(self::SEPARATOR, $hardwareIdentifier1);
        $split2 = explode(self::SEPARATOR, $hardwareIdentifier2);

        if (count($split1) !== count($split2)) {
            return false;
        }

        $validCharacteristicsCount = 0.0;
        $characteristicsCount = count($split1) - 1; // last element is the check sum

        for ($i = 0; $i < $characteristicsCount; $i++) {
            if ($split1[$i] === $split2[$i]) {
                $validCharacteristicsCount += 1.0;
            }
        }

        if ($validCharacteristicsCount == 0) {
            return false;
        }

        $validCharacteristicsRatio = $characteristicsCount / $validCharacteristicsCount;

        return $validCharacteristicsRatio <= 2.1;
    }

    /**
     * Checks if the check sum is valid.
     *
     * @param string $hardwareIdentifier The hardware identifier to check.
     * @return bool True if the hardware identifier has a valid check sum or the hardware
     *              identifier is equal to NO_HARDWARE_IDENTIFIER; otherwise false.
     */
    public static function isChecksumValid(string $hardwareIdentifier): bool
    {
        if ($hardwareIdentifier === self::NO_HARDWARE_IDENTIFIER) {
            return true;
        }

        self::initialize();
        return self::$checkSumAppender->verify($hardwareIdentifier);
    }
}

