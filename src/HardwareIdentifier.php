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

        $encodedCharacteristics = [];
        foreach (self::$computerCharacteristics->getCharacteristicsForCurrentComputer() as $characteristic) {
            $bytes = $characteristic;
            $md5Hash = md5($bytes, true); // true = raw binary output

            $encodedCharacteristics[] = self::$encoder->encode($md5Hash);
        }

        $hardwareKey = implode(self::SEPARATOR, $encodedCharacteristics);
        return self::$checkSumAppender->append($hardwareKey);
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

