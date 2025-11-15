<?php

/**
 * Copyright (c) Musoftware. All rights reserved.
 * Licensed under the MIT License. See LICENSE file in the project root for full license information.
 */

namespace MusoftwareDeviceSecret;

/**
 * PHP implementation of C#'s Random class to match C# behavior exactly.
 */
class CSharpRandom
{
    private int $seed;
    private int $inext = 0;
    private int $inextp = 21;
    private array $seedArray = [];

    /**
     * Creates a new instance with the specified seed.
     *
     * @param int $seed
     */
    public function __construct(int $seed)
    {
        $this->seed = $seed;
        $this->seedArray = array_fill(0, 56, 0);

        // Initialize seed array (C# Random initialization)
        // int.MinValue in C# is -2147483648
        $subtraction = ($seed == -2147483648) ? 161803398 : abs($seed);
        $mj = 161803398 - $subtraction;
        $this->seedArray[55] = $mj;
        $mk = 1;

        // C# uses a specific algorithm to fill the seed array
        for ($i = 1; $i < 56; $i++) {
            $ii = (21 * $i) % 55;
            $this->seedArray[$ii] = $mk;
            $mk = $mj - $mk;
            if ($mk < 0) {
                $mk += 2147483647;
            }
            $mj = $this->seedArray[$ii];
        }

        // Additional initialization pass (C# does this)
        for ($k = 1; $k < 5; $k++) {
            for ($i = 1; $i < 56; $i++) {
                $this->seedArray[$i] -= $this->seedArray[1 + (($i + 30) % 55)];
                if ($this->seedArray[$i] < 0) {
                    $this->seedArray[$i] += 2147483647;
                }
            }
        }

        $this->inext = 0;
        $this->inextp = 21;
    }

    /**
     * Fill buffer with random bytes (matching C# Random.NextBytes).
     *
     * @param array &$buffer Reference to buffer array to fill
     */
    public function nextBytes(array &$buffer): void
    {
        // C#'s NextBytes calls InternalSample() for each byte
        // Skip first InternalSample() call to match C# behavior
        $this->internalSample(); // Skip first call to match C# behavior
        for ($i = 0; $i < count($buffer); $i++) {
            $sample = $this->internalSample();
            $buffer[$i] = $sample & 0xFF;
        }
    }

    /**
     * Internal sample method matching C#.
     *
     * @return int
     */
    private function internalSample(): int
    {
        $retVal = $this->seedArray[$this->inext] - $this->seedArray[$this->inextp];
        if ($retVal < 0) {
            $retVal += 2147483647;
        }

        $this->seedArray[$this->inext] = $retVal;
        $this->inext++;
        if ($this->inext >= 56) {
            $this->inext = 1;
        }

        $this->inextp++;
        if ($this->inextp >= 56) {
            $this->inextp = 1;
        }

        return $retVal;
    }
}

