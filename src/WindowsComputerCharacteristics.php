<?php

/**
 * Copyright (c) Musoftware. All rights reserved.
 * Licensed under the MIT License. See LICENSE file in the project root for full license information.
 */

namespace MusoftwareDeviceSecret;

/**
 * Cross-platform implementation of ComputerCharacteristicsInterface.
 * 
 * Works on Windows, Linux, and macOS by using platform-specific methods
 * to retrieve hardware characteristics.
 */
class WindowsComputerCharacteristics implements ComputerCharacteristicsInterface
{
    /**
     * Gets a list of characteristics for the current computer.
     *
     * @return \Generator|array
     */
    public function getCharacteristicsForCurrentComputer()
    {
        $os = PHP_OS_FAMILY;

        if ($os === 'Windows') {
            yield from $this->getWindowsCharacteristics();
        } elseif ($os === 'Linux') {
            yield from $this->getLinuxCharacteristics();
        } elseif ($os === 'Darwin') {
            yield from $this->getMacOSCharacteristics();
        } else {
            // Fallback for unknown OS
            yield from $this->getFallbackCharacteristics();
        }
    }

    /**
     * Gets hardware characteristics for Windows using WMI.
     *
     * @return \Generator
     */
    private function getWindowsCharacteristics(): \Generator
    {
        if (extension_loaded('com_dotnet')) {
            try {
                yield $this->getWmiValue('ProcessorID', 'Win32_Processor');
                yield $this->getWmiValue('SerialNumber', 'Win32_BIOS');
                yield $this->getWmiValue('SerialNumber', 'Win32_BaseBoard');
                yield $this->getWmiValue('SerialNumber', 'Win32_PhysicalMedia');
                return;
            } catch (\Exception $e) {
                // Fall through to alternative methods
            }
        }

        // Alternative: Try using wmic command
        yield $this->getCommandValue('wmic cpu get ProcessorId /value', 'ProcessorId');
        yield $this->getCommandValue('wmic bios get serialnumber /value', 'SerialNumber');
        yield $this->getCommandValue('wmic baseboard get serialnumber /value', 'SerialNumber');
        yield $this->getCommandValue('wmic diskdrive get serialnumber /value', 'SerialNumber');
    }

    /**
     * Gets hardware characteristics for Linux.
     *
     * @return \Generator
     */
    private function getLinuxCharacteristics(): \Generator
    {
        // Try dmidecode first (most reliable, but requires root)
        if ($this->commandExists('dmidecode')) {
            yield $this->getDmidecodeValue('processor', 'ID');
            yield $this->getDmidecodeValue('bios', 'Serial Number');
            yield $this->getDmidecodeValue('baseboard', 'Serial Number');
            yield $this->getDmidecodeValue('system', 'Serial Number');
            return;
        }

        // Fallback: Use /proc and /sys filesystem
        yield $this->getFileValue('/proc/cpuinfo', 'Serial');
        yield $this->getFileValue('/sys/class/dmi/id/product_serial', '');
        yield $this->getFileValue('/sys/class/dmi/id/board_serial', '');
        yield $this->getFileValue('/sys/class/dmi/id/chassis_serial', '');
    }

    /**
     * Gets hardware characteristics for macOS.
     *
     * @return \Generator
     */
    private function getMacOSCharacteristics(): \Generator
    {
        yield $this->getCommandValue('system_profiler SPHardwareDataType | grep "Serial Number"', 'Serial Number');
        yield $this->getCommandValue('system_profiler SPHardwareDataType | grep "Hardware UUID"', 'Hardware UUID');
        yield $this->getCommandValue('ioreg -l | grep IOPlatformSerialNumber', 'IOPlatformSerialNumber');
        yield $this->getCommandValue('system_profiler SPHardwareDataType | grep "Model Identifier"', 'Model Identifier');
    }

    /**
     * Gets fallback characteristics when OS is unknown or methods fail.
     *
     * @return \Generator
     */
    private function getFallbackCharacteristics(): \Generator
    {
        // Use hostname and other available info
        yield gethostname();
        yield php_uname('m'); // machine type
        yield php_uname('n'); // hostname
        yield php_uname('r'); // release
    }

    /**
     * Gets a value from Windows WMI.
     *
     * @param string $property The property name to retrieve
     * @param string $type The WMI class name
     * @return string The property value(s) concatenated
     */
    private function getWmiValue(string $property, string $type): string
    {
        $result = '';
        try {
            $wmi = new \COM('winmgmts://./root/cimv2');
            $items = $wmi->ExecQuery("SELECT {$property} FROM {$type}");
            
            foreach ($items as $item) {
                if (isset($item->$property)) {
                    $value = $item->$property;
                    if ($value !== null && $value !== '') {
                        $result .= $value;
                    }
                }
            }
        } catch (\Exception $e) {
            // If WMI fails, use fallback
            $result = $property . $type;
        }
        
        return $result !== '' ? $result : $property . $type;
    }

    /**
     * Gets a value from a command output.
     *
     * @param string $command The command to execute
     * @param string $key The key to search for in output
     * @return string The extracted value
     */
    private function getCommandValue(string $command, string $key): string
    {
        $result = '';
        try {
            $output = [];
            $returnVar = 0;
            @exec($command . ' 2>/dev/null', $output, $returnVar);
            
            if ($returnVar === 0 && !empty($output)) {
                $outputStr = implode("\n", $output);
                
                if ($key === '') {
                    // If no key specified, use the whole output
                    $result = trim($outputStr);
                } else {
                    // Extract value after the key
                    if (preg_match('/' . preg_quote($key, '/') . '\s*[=:]\s*(.+)/i', $outputStr, $matches)) {
                        $result = trim($matches[1]);
                    } elseif (preg_match('/' . preg_quote($key, '/') . '\s+(.+)/i', $outputStr, $matches)) {
                        $result = trim($matches[1]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore errors
        }
        
        return $result !== '' ? $result : $key . PHP_OS_FAMILY;
    }

    /**
     * Gets a value from dmidecode command (Linux).
     *
     * @param string $type The dmidecode type
     * @param string $key The key to search for
     * @return string The extracted value
     */
    private function getDmidecodeValue(string $type, string $key): string
    {
        $result = '';
        try {
            $output = [];
            $returnVar = 0;
            @exec("sudo dmidecode -t {$type} 2>/dev/null", $output, $returnVar);
            
            if ($returnVar === 0 && !empty($output)) {
                $outputStr = implode("\n", $output);
                if (preg_match('/' . preg_quote($key, '/') . ':\s*(.+)/i', $outputStr, $matches)) {
                    $result = trim($matches[1]);
                }
            }
        } catch (\Exception $e) {
            // Ignore errors
        }
        
        return $result !== '' ? $result : $key . $type;
    }

    /**
     * Gets a value from a file (Linux /proc or /sys).
     *
     * @param string $filePath The file path to read
     * @param string $key The key to search for (if file is key-value format)
     * @return string The extracted value
     */
    private function getFileValue(string $filePath, string $key): string
    {
        $result = '';
        try {
            if (file_exists($filePath) && is_readable($filePath)) {
                $content = @file_get_contents($filePath);
                
                if ($content !== false) {
                    if ($key === '') {
                        // If no key, use the whole content
                        $result = trim($content);
                    } else {
                        // Extract value after the key
                        if (preg_match('/' . preg_quote($key, '/') . '\s*[:\s]+\s*(.+)/i', $content, $matches)) {
                            $result = trim($matches[1]);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore errors
        }
        
        return $result !== '' ? $result : basename($filePath);
    }

    /**
     * Checks if a command exists in the system.
     *
     * @param string $command The command to check
     * @return bool True if command exists
     */
    private function commandExists(string $command): bool
    {
        $whereIsCommand = (PHP_OS_FAMILY === 'Windows') ? 'where' : 'which';
        $output = [];
        @exec("{$whereIsCommand} {$command} 2>/dev/null", $output, $returnVar);
        return $returnVar === 0;
    }
}

