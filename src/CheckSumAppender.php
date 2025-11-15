<?php

/**
 * Copyright (c) Musoftware. All rights reserved.
 * Licensed under the MIT License. See LICENSE file in the project root for full license information.
 */

namespace MusoftwareDeviceSecret;

/**
 * The CheckSumAppender can be used to append the check sum to a string and verify strings which have a checksum appended.
 */
class CheckSumAppender
{
    private string $separator;
    private CheckSum $checksum;

    /**
     * Creates a new instance of the class.
     *
     * @param string $separator The separator that separates the check sum from the string.
     * @param CheckSum $checksum CheckSum instance to use for creating the check sum.
     * @throws \InvalidArgumentException
     */
    public function __construct(string $separator, CheckSum $checksum)
    {
        if ($separator === null) {
            throw new \InvalidArgumentException('separator cannot be null');
        }
        if ($checksum === null) {
            throw new \InvalidArgumentException('checksum cannot be null');
        }

        $this->separator = $separator;
        $this->checksum = $checksum;
    }

    /**
     * Appends the check sum to the specified string.
     *
     * @param string $inputToAppendChecksum The string to append the check sum to.
     * @return string The specified string + the separator + the check sum.
     */
    public function append(string $inputToAppendChecksum): string
    {
        $checksum = $this->getChecksum($inputToAppendChecksum);
        return $inputToAppendChecksum . $this->separator . $checksum;
    }

    /**
     * Verifies if the specified string (which includes the check sum) is valid.
     *
     * @param string $inputWithChecksumToVerify The string + separator + check sum.
     * @return bool True if the check sum is valid; otherwise false.
     * @throws \InvalidArgumentException
     */
    public function verify(string $inputWithChecksumToVerify): bool
    {
        if ($inputWithChecksumToVerify === null) {
            throw new \InvalidArgumentException('inputWithChecksumToVerify cannot be null');
        }

        $inputLength = strlen($inputWithChecksumToVerify) - $this->checksum->getLength() - strlen($this->separator);
        if ($inputLength <= 0) {
            return false;
        }

        $inputStr = substr($inputWithChecksumToVerify, 0, $inputLength);
        $checksum = $this->getChecksum($inputStr);
        return $inputWithChecksumToVerify === ($inputStr . $this->separator . $checksum);
    }

    /**
     * Gets the checksum for the specified input.
     *
     * @param string $inputToAppendChecksum
     * @return string
     */
    private function getChecksum(string $inputToAppendChecksum): string
    {
        return $this->checksum->create($inputToAppendChecksum);
    }
}

