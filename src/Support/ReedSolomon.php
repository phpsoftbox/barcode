<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Support;

use InvalidArgumentException;

use function array_fill;
use function array_merge;
use function array_slice;
use function count;

final class ReedSolomon
{
    /** @var list<int> */
    private array $expTable;

    /** @var list<int> */
    private array $logTable;

    public function __construct(
        private readonly int $primitive,
        private readonly int $generatorBase,
    ) {
        $this->expTable = array_fill(0, 512, 0);
        $this->logTable = array_fill(0, 256, 0);

        $value = 1;
        for ($index = 0; $index < 255; $index++) {
            $this->expTable[$index] = $value;
            $this->logTable[$value] = $index;

            $value <<= 1;
            if (($value & 0x100) !== 0) {
                $value ^= $this->primitive;
            }
        }

        for ($index = 255; $index < 512; $index++) {
            $this->expTable[$index] = $this->expTable[$index - 255];
        }
    }

    public static function forQr(): self
    {
        return new self(0x11d, 0);
    }

    public static function forDataMatrix(): self
    {
        return new self(0x12d, 1);
    }

    /**
     * @param list<int> $dataCodewords
     *
     * @return list<int>
     */
    public function encode(array $dataCodewords, int $eccCodewords): array
    {
        if ($eccCodewords <= 0) {
            throw new InvalidArgumentException('ECC codewords must be greater than zero.');
        }

        $generator = $this->buildGenerator($eccCodewords);
        $buffer    = array_merge($dataCodewords, array_fill(0, $eccCodewords, 0));

        $dataCount = count($dataCodewords);
        $genCount  = count($generator);

        for ($index = 0; $index < $dataCount; $index++) {
            $factor = $buffer[$index];
            if ($factor === 0) {
                continue;
            }

            for ($offset = 0; $offset < $genCount; $offset++) {
                $buffer[$index + $offset] ^= $this->multiply($generator[$offset], $factor);
            }
        }

        return array_slice($buffer, $dataCount);
    }

    /**
     * @return list<int>
     */
    private function buildGenerator(int $degree): array
    {
        $generator = [1];

        for ($index = 0; $index < $degree; $index++) {
            $coefficient = $this->expTable[$index + $this->generatorBase];
            $next        = array_fill(0, count($generator) + 1, 0);

            $size = count($generator);
            for ($position = 0; $position < $size; $position++) {
                $next[$position] ^= $generator[$position];
                $next[$position + 1] ^= $this->multiply($generator[$position], $coefficient);
            }

            $generator = $next;
        }

        return $generator;
    }

    private function multiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        return $this->expTable[$this->logTable[$a] + $this->logTable[$b]];
    }
}
