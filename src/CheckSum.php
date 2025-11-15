<?php

/**
 * Copyright (c) Musoftware. All rights reserved.
 * Licensed under the MIT License. See LICENSE file in the project root for full license information.
 */

namespace MusoftwareDeviceSecret;

/**
 * Class for creating check sum for an array of bytes.
 */
class CheckSum
{
    private array $validCharacters;
    private int $length;

    /**
     * Creates a new instance of the class.
     *
     * @param string|null $supportedCharacters List of supported characters for the check sum.
     *                                         If null, uses [A-Z] and [0-9].
     * @param int|null $length The length of the check sum. Required if supportedCharacters is provided.
     * @throws \InvalidArgumentException
     */
    public function __construct(?string $supportedCharacters = null, ?int $length = null)
    {
        if ($supportedCharacters === null) {
            $supportedCharacters = Constants::VALID_CHARACTERS;
        }

        if (empty($supportedCharacters)) {
            throw new \InvalidArgumentException('supportedCharacters cannot be empty');
        }

        if ($length === null || $length <= 0) {
            throw new \InvalidArgumentException('length must be a positive number');
        }

        // Remove duplicates while preserving order
        $this->validCharacters = array_values(array_unique(str_split($supportedCharacters)));
        $this->length = $length;
    }

    /**
     * Gets the length of the check sum.
     *
     * @return int
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * Creates the check sum for the specified byte array.
     *
     * @param string $bytesArray The byte array to create the check sum for.
     * @return string A string that represents the check sum of the specified byte array.
     */
    public function create(string $bytesArray): string
    {
        $bytesArray = $this->adjustSize($bytesArray, $this->length);

        $checksum = '';
        for ($i = 0; $i < strlen($bytesArray); $i++) {
            $checksum .= $this->toChecksumChar(ord($bytesArray[$i]));
        }

        return $checksum;
    }

    /**
     * Converts a byte to a checksum character.
     *
     * @param int $byteValue
     * @return string
     */
    private function toChecksumChar(int $byteValue): string
    {
        return $this->validCharacters[$byteValue % count($this->validCharacters)];
    }

    /**
     * Adjusts the size of the byte array to the specified length.
     *
     * @param string $bytesArray
     * @param int $length
     * @return string
     */
    private function adjustSize(string $bytesArray, int $length): string
    {
        $bytesLength = strlen($bytesArray);
        if ($bytesLength > $length) {
            return $this->reduceSize($bytesArray, $length);
        }
        if ($bytesLength < $length) {
            return $this->inflateSize($bytesArray, $length);
        }
        return $bytesArray;
    }

    /**
     * Inflates the byte array to the specified length.
     *
     * @param string $bytesArray
     * @param int $length
     * @return string
     */
    private function inflateSize(string $bytesArray, int $length): string
    {
        $result = array_fill(0, $length, 0);
        $bytesLength = strlen($bytesArray);
        for ($i = 0; $i < $length; $i++) {
            $result[$i] ^= ord($bytesArray[$i % $bytesLength]);
        }
        return implode('', array_map('chr', $result));
    }

    /**
     * Reduces the byte array to the specified length.
     *
     * @param string $bytesArray
     * @param int $length
     * @return string
     */
    private function reduceSize(string $bytesArray, int $length): string
    {
        $result = array_fill(0, $length, 0);
        $bytesLength = strlen($bytesArray);
        for ($i = 0; $i < $bytesLength; $i++) {
            $result[$i % $length] ^= ord($bytesArray[$i]);
        }
        return implode('', array_map('chr', $result));
    }
}

