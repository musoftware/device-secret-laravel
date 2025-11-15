<?php

/**
 * Copyright (c) Musoftware. All rights reserved.
 * Licensed under the MIT License. See LICENSE file in the project root for full license information.
 */

namespace MusoftwareDeviceSecret;

/**
 * Encoder class for encoding byte arrays into strings.
 */
class Encoder
{
    private string $validCharacters;
    private ?int $encodingLength;

    /**
     * Creates a new instance of the Encoder.
     *
     * @param string|null $validCharacters String of valid characters for encoding. If null, uses default [A-Z0-9].
     * @param int|null $encodingLength Length of the encoded output. If null or negative, uses input length.
     */
    public function __construct(?string $validCharacters = null, ?int $encodingLength = null)
    {
        if ($validCharacters === null) {
            $validCharacters = Constants::VALID_CHARACTERS;
        }

        $this->validCharacters = $validCharacters;
        $this->encodingLength = $encodingLength;
    }

    /**
     * Encodes the input data.
     *
     * @param string|array $inputData Either a string or byte array to encode.
     * @return string Encoded string.
     * @throws \InvalidArgumentException
     */
    public function encode($inputData): string
    {
        if (is_string($inputData)) {
            $bytesArray = $inputData;
            $length = ($this->encodingLength !== null && $this->encodingLength >= 0) 
                ? $this->encodingLength 
                : strlen($inputData);
            return $this->encodeBytes($bytesArray, $length);
        } elseif (is_array($inputData)) {
            // Convert array of bytes to string
            $bytesArray = implode('', array_map('chr', $inputData));
            $length = ($this->encodingLength !== null && $this->encodingLength >= 0) 
                ? $this->encodingLength 
                : count($inputData);
            return $this->encodeBytes($bytesArray, $length);
        } else {
            throw new \InvalidArgumentException('inputData must be a string or array');
        }
    }

    /**
     * Internal method to encode bytes to the specified length.
     *
     * @param string $bytesArray
     * @param int $length
     * @return string
     * @throws \InvalidArgumentException
     */
    private function encodeBytes(string $bytesArray, int $length): string
    {
        if ($length < 0) {
            throw new \InvalidArgumentException('length must be a positive number');
        }

        if ($bytesArray === null) {
            throw new \InvalidArgumentException('bytesArray cannot be null');
        }

        // Match C# behavior: create a new Random(99) for each encode call
        $rng = new CSharpRandom(99);

        // Create random buffer - match C# NextBytes() behavior
        $buffer = array_fill(0, $length, 0);
        $rng->nextBytes($buffer);

        // XOR with input bytes
        $bytesLength = strlen($bytesArray);
        for ($i = 0; $i < $length; $i++) {
            $buffer[$i] ^= ord($bytesArray[$i % $bytesLength]);
        }

        // Convert to string using valid characters
        $result = '';
        $validCharsLength = strlen($this->validCharacters);
        for ($i = 0; $i < $length; $i++) {
            $result .= $this->validCharacters[$buffer[$i] % $validCharsLength];
        }

        return $result;
    }
}

