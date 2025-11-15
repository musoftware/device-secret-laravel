<?php

/**
 * Copyright (c) Musoftware. All rights reserved.
 * Licensed under the MIT License. See LICENSE file in the project root for full license information.
 */

namespace MusoftwareDeviceSecret;

/**
 * Cross-platform implementation of ComputerCharacteristicsInterface.
 * Automatically detects the OS and uses appropriate methods for each platform.
 * 
 * Supports:
 * - Windows: WMI (if COM extension available) or fallback methods
 * - Linux: /sys filesystem, dmidecode, /etc/machine-id
 * - macOS: system_profiler, ioreg
 */
class CrossPlatformComputerCharacteristics implements ComputerCharacteristicsInterface
{
    /**
     * Gets a list of characteristics for the current computer.
     * Automatically detects the OS and uses the appropriate method.
     *
     * @return \Generator|array
     */
    public function getCharacteristicsForCurrentComputer()
    {
        $osFamily = PHP_OS_FAMILY;

        switch ($osFamily) {
            case 'Windows':
                yield from $this->getWindowsCharacteristics();
                break;
            case 'Linux':
                yield from $this->getLinuxCharacteristics();
                break;
            case 'Darwin': // macOS
                yield from $this->getMacOSCharacteristics();
                break;
            default:
                // Fallback for unknown OS
                yield from $this->getFallbackCharacteristics();
                break;
        }
    }

    /**
     * Gets characteristics for Windows systems.
     *
     * @return \Generator|array
     */
    private function getWindowsCharacteristics()
    {
        // Try WMI first if COM extension is available
        if (extension_loaded('com_dotnet')) {
            try {
                yield $this->getWindowsWMIValue('ProcessorID', 'Win32_Processor');
                yield $this->getWindowsWMIValue('SerialNumber', 'Win32_BIOS');
                yield $this->getWindowsWMIValue('SerialNumber', 'Win32_BaseBoard');
                yield $this->getWindowsWMIValue('SerialNumber', 'Win32_PhysicalMedia');
                return;
            } catch (\Exception $e) {
                // Fall through to alternative methods
            }
        }

        // Fallback: Use system information via command line
        yield $this->getWindowsCommandValue('wmic cpu get ProcessorId /value');
        yield $this->getWindowsCommandValue('wmic bios get SerialNumber /value');
        yield $this->getWindowsCommandValue('wmic baseboard get SerialNumber /value');
        yield $this->getWindowsCommandValue('wmic diskdrive get SerialNumber /value');
    }

    /**
     * Gets characteristics for Linux systems.
     *
     * @return \Generator|array
     */
    private function getLinuxCharacteristics()
    {
        // Try /etc/machine-id first (most reliable on modern Linux)
        $machineId = $this->readFile('/etc/machine-id');
        if ($machineId) {
            yield $machineId;
        }

        // Try /sys filesystem for hardware info
        yield $this->readFile('/sys/class/dmi/id/product_uuid');
        yield $this->readFile('/sys/class/dmi/id/product_serial');
        yield $this->readFile('/sys/class/dmi/id/board_serial');

        // Try dmidecode if available
        yield $this->getLinuxCommandValue('dmidecode -s processor-uuid');
        yield $this->getLinuxCommandValue('dmidecode -s system-uuid');
        yield $this->getLinuxCommandValue('dmidecode -s baseboard-serial-number');

        // Additional fallback: CPU info
        $cpuInfo = $this->readFile('/proc/cpuinfo');
        if ($cpuInfo) {
            // Extract serial or unique identifiers from cpuinfo
            if (preg_match('/Serial\s*:\s*([^\n]+)/i', $cpuInfo, $matches)) {
                yield trim($matches[1]);
            }
        }
    }

    /**
     * Gets characteristics for macOS systems.
     *
     * @return \Generator|array
     */
    private function getMacOSCharacteristics()
    {
        // Use system_profiler for hardware info
        yield $this->getMacOSCommandValue('system_profiler SPHardwareDataType | grep "Serial Number"');
        yield $this->getMacOSCommandValue('system_profiler SPHardwareDataType | grep "Hardware UUID"');

        // Use ioreg for I/O Registry (more reliable)
        yield $this->getMacOSCommandValue('ioreg -rd1 -c IOPlatformExpertDevice | grep -E \'(UUID|IOPlatformUUID)\'');
        yield $this->getMacOSCommandValue('ioreg -l | grep IOPlatformSerialNumber');

        // Additional: System UUID
        yield $this->getMacOSCommandValue('system_profiler SPHardwareDataType | grep "Hardware UUID" | awk \'{print $3}\'');
    }

    /**
     * Fallback characteristics for unknown OS types.
     *
     * @return \Generator|array
     */
    private function getFallbackCharacteristics()
    {
        // Use hostname and other available info
        yield gethostname();
        yield php_uname('n'); // nodename
        yield php_uname('m'); // machine type
    }

    /**
     * Gets a value from Windows WMI.
     *
     * @param string $property The property name to retrieve
     * @param string $type The WMI class name
     * @return string The property value(s) concatenated
     */
    private function getWindowsWMIValue(string $property, string $type): string
    {
        $result = '';
        try {
            $wmi = new \COM('winmgmts://./root/cimv2');
            $items = $wmi->ExecQuery("SELECT {$property} FROM {$type}");
            
            foreach ($items as $item) {
                if (isset($item->$property) && !empty($item->$property)) {
                    $value = trim($item->$property);
                    if ($value && $value !== 'To be filled by O.E.M.' && $value !== 'Default string') {
                        $result .= $value;
                    }
                }
            }
        } catch (\Exception $e) {
            // Return empty string on failure
        }
        
        return $result ?: '';
    }

    /**
     * Gets a value from Windows command line.
     *
     * @param string $command The command to execute
     * @return string The command output
     */
    private function getWindowsCommandValue(string $command): string
    {
        try {
            $output = shell_exec($command . ' 2>&1');
            if ($output) {
                // Extract value from key=value format
                $lines = explode("\n", trim($output));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, '=') !== false) {
                        $parts = explode('=', $line, 2);
                        if (count($parts) === 2 && !empty(trim($parts[1]))) {
                            $value = trim($parts[1]);
                            if ($value && $value !== 'To be filled by O.E.M.' && $value !== 'Default string') {
                                return $value;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Return empty string on failure
        }
        
        return '';
    }

    /**
     * Gets a value from Linux command line.
     *
     * @param string $command The command to execute
     * @return string The command output
     */
    private function getLinuxCommandValue(string $command): string
    {
        try {
            $output = shell_exec($command . ' 2>/dev/null');
            if ($output) {
                $value = trim($output);
                if ($value && strpos(strtolower($value), 'not available') === false) {
                    return $value;
                }
            }
        } catch (\Exception $e) {
            // Return empty string on failure
        }
        
        return '';
    }

    /**
     * Gets a value from macOS command line.
     *
     * @param string $command The command to execute
     * @return string The command output
     */
    private function getMacOSCommandValue(string $command): string
    {
        try {
            $output = shell_exec($command . ' 2>/dev/null');
            if ($output) {
                $value = trim($output);
                // Extract value from key: value format or other formats
                if (strpos($value, ':') !== false) {
                    $parts = explode(':', $value, 2);
                    if (count($parts) === 2) {
                        $value = trim($parts[1]);
                    }
                }
                // Remove quotes if present
                $value = trim($value, '"\'');
                if ($value) {
                    return $value;
                }
            }
        } catch (\Exception $e) {
            // Return empty string on failure
        }
        
        return '';
    }

    /**
     * Reads a file and returns its contents.
     *
     * @param string $path The file path
     * @return string The file contents or empty string
     */
    private function readFile(string $path): string
    {
        try {
            if (file_exists($path) && is_readable($path)) {
                $content = file_get_contents($path);
                if ($content !== false) {
                    $value = trim($content);
                    if ($value) {
                        return $value;
                    }
                }
            }
        } catch (\Exception $e) {
            // Return empty string on failure
        }
        
        return '';
    }
}
