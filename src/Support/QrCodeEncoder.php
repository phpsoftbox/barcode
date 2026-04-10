<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Support;

use PhpSoftBox\Barcode\Exception\BarcodeException;
use PhpSoftBox\Barcode\QrErrorCorrectionLevel;

use function abs;
use function array_fill;
use function count;
use function floor;
use function intdiv;
use function ord;
use function strlen;

final class QrCodeEncoder
{
    /** @var array<string, array{data: array<int, int>, ecc: array<int, int>, blocks: array<int, int>}> */
    private const CAPACITY = [
        'M' => [
            'data' => [
                1  => 16,
                2  => 28,
                3  => 44,
                4  => 64,
                5  => 86,
                6  => 108,
                7  => 124,
                8  => 154,
                9  => 182,
                10 => 216,
            ],
            'ecc' => [
                1  => 10,
                2  => 16,
                3  => 26,
                4  => 18,
                5  => 24,
                6  => 16,
                7  => 18,
                8  => 22,
                9  => 22,
                10 => 26,
            ],
            'blocks' => [
                1  => 1,
                2  => 1,
                3  => 1,
                4  => 2,
                5  => 2,
                6  => 4,
                7  => 4,
                8  => 4,
                9  => 5,
                10 => 5,
            ],
        ],
        'H' => [
            'data' => [
                1  => 9,
                2  => 16,
                3  => 26,
                4  => 36,
                5  => 46,
                6  => 60,
                7  => 66,
                8  => 86,
                9  => 100,
                10 => 122,
            ],
            'ecc' => [
                1  => 17,
                2  => 28,
                3  => 22,
                4  => 16,
                5  => 22,
                6  => 28,
                7  => 26,
                8  => 26,
                9  => 24,
                10 => 28,
            ],
            'blocks' => [
                1  => 1,
                2  => 1,
                3  => 2,
                4  => 4,
                5  => 4,
                6  => 4,
                7  => 5,
                8  => 6,
                9  => 8,
                10 => 8,
            ],
        ],
    ];

    /** @var array<int, list<int>> */
    private const ALIGNMENT_PATTERN_POSITIONS = [
        1  => [],
        2  => [6, 18],
        3  => [6, 22],
        4  => [6, 26],
        5  => [6, 30],
        6  => [6, 34],
        7  => [6, 22, 38],
        8  => [6, 24, 42],
        9  => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    private ReedSolomon $reedSolomon;

    public function __construct()
    {
        $this->reedSolomon = ReedSolomon::forQr();
    }

    /**
     * @return list<list<bool>>
     */
    public function encode(string $data, QrErrorCorrectionLevel $level = QrErrorCorrectionLevel::M): array
    {
        $bytes   = $this->toBytes($data);
        $version = $this->chooseVersion(count($bytes), $level);

        $dataCodewords = $this->buildDataCodewords($bytes, $version, $level);
        $codewords     = $this->addErrorCorrection($dataCodewords, $version, $level);

        return $this->buildMatrix($version, $codewords, $level);
    }

    /**
     * @return list<int>
     */
    private function toBytes(string $data): array
    {
        $bytes = [];
        $size  = strlen($data);

        for ($index = 0; $index < $size; $index++) {
            $bytes[] = ord($data[$index]);
        }

        return $bytes;
    }

    private function chooseVersion(int $byteCount, QrErrorCorrectionLevel $level): int
    {
        $capacity = $this->levelData($level)['data'];
        foreach ($capacity as $version => $dataCodewords) {
            $countBits    = $version <= 9 ? 8 : 16;
            $required     = 4 + $countBits + ($byteCount * 8);
            $capacityBits = $dataCodewords * 8;

            if ($required <= $capacityBits) {
                return $version;
            }
        }

        throw new BarcodeException('QR payload is too large for current encoder (max version 10).');
    }

    /**
     * @param list<int> $bytes
     *
     * @return list<int>
     */
    private function buildDataCodewords(array $bytes, int $version, QrErrorCorrectionLevel $level): array
    {
        $capacityBits = $this->levelData($level)['data'][$version] * 8;
        $countBits    = $version <= 9 ? 8 : 16;
        $bits         = [];

        $this->appendBits($bits, 0b0100, 4);
        $this->appendBits($bits, count($bytes), $countBits);
        foreach ($bytes as $byte) {
            $this->appendBits($bits, $byte, 8);
        }

        $terminator = $capacityBits - count($bits);
        if ($terminator > 4) {
            $terminator = 4;
        }

        if ($terminator > 0) {
            $this->appendBits($bits, 0, $terminator);
        }

        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $codewords = [];
        $bitCount  = count($bits);
        for ($index = 0; $index < $bitCount; $index += 8) {
            $value = 0;
            for ($offset = 0; $offset < 8; $offset++) {
                $value = ($value << 1) | $bits[$index + $offset];
            }

            $codewords[] = $value;
        }

        $pad = 0xEC;
        while (count($codewords) < $this->levelData($level)['data'][$version]) {
            $codewords[] = $pad;
            $pad         = $pad === 0xEC ? 0x11 : 0xEC;
        }

        return $codewords;
    }

    /**
     * @param list<int> $dataCodewords
     *
     * @return list<int>
     */
    private function addErrorCorrection(array $dataCodewords, int $version, QrErrorCorrectionLevel $level): array
    {
        $levelData        = $this->levelData($level);
        $numBlocks        = $levelData['blocks'][$version];
        $eccPerBlock      = $levelData['ecc'][$version];
        $totalData        = count($dataCodewords);
        $shortBlockLength = intdiv($totalData, $numBlocks);
        $numLongBlocks    = $totalData % $numBlocks;
        $blocks           = [];
        $errorCorrection  = [];
        $offset           = 0;

        for ($index = 0; $index < $numBlocks; $index++) {
            $dataLength = $shortBlockLength + ($index >= ($numBlocks - $numLongBlocks) ? 1 : 0);
            $blockData  = [];

            for ($position = 0; $position < $dataLength; $position++) {
                $blockData[] = $dataCodewords[$offset + $position];
            }

            $offset += $dataLength;

            $blocks[]          = $blockData;
            $errorCorrection[] = $this->reedSolomon->encode($blockData, $eccPerBlock);
        }

        $interleaved = [];
        $maxDataSize = $shortBlockLength + ($numLongBlocks > 0 ? 1 : 0);
        for ($position = 0; $position < $maxDataSize; $position++) {
            foreach ($blocks as $block) {
                if ($position < count($block)) {
                    $interleaved[] = $block[$position];
                }
            }
        }

        for ($position = 0; $position < $eccPerBlock; $position++) {
            foreach ($errorCorrection as $eccBlock) {
                $interleaved[] = $eccBlock[$position];
            }
        }

        return $interleaved;
    }

    /**
     * @param list<int> $codewords
     *
     * @return list<list<bool>>
     */
    private function buildMatrix(int $version, array $codewords, QrErrorCorrectionLevel $level): array
    {
        $size       = $version * 4 + 17;
        $modules    = array_fill(0, $size, array_fill(0, $size, -1));
        $isFunction = array_fill(0, $size, array_fill(0, $size, false));

        $this->drawFunctionPatterns($modules, $isFunction, $version);
        $this->drawCodewords($modules, $isFunction, $codewords);

        $bestMatrix  = [];
        $bestPenalty = null;

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = $this->cloneMatrix($modules);
            $this->applyMask($candidate, $isFunction, $mask);
            $this->drawFormatBits($candidate, $mask, $level);

            if ($version >= 7) {
                $this->drawVersionBits($candidate, $version);
            }

            $penalty = $this->penaltyScore($candidate);
            if ($bestPenalty === null || $penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMatrix  = $candidate;
            }
        }

        return $this->toBoolMatrix($bestMatrix);
    }

    /**
     * @param list<int> $bits
     */
    private function appendBits(array &$bits, int $value, int $length): void
    {
        for ($index = $length - 1; $index >= 0; $index--) {
            $bits[] = ($value >> $index) & 1;
        }
    }

    /**
     * @param list<list<int>> $modules
     * @param list<list<bool>> $isFunction
     */
    private function drawFunctionPatterns(array &$modules, array &$isFunction, int $version): void
    {
        $size = count($modules);

        $this->drawFinderPattern($modules, $isFunction, 0, 0);
        $this->drawFinderPattern($modules, $isFunction, $size - 7, 0);
        $this->drawFinderPattern($modules, $isFunction, 0, $size - 7);

        for ($index = 0; $index < $size; $index++) {
            if (!$isFunction[6][$index]) {
                $this->setFunctionModule($modules, $isFunction, $index, 6, $index % 2 === 0 ? 1 : 0);
            }

            if (!$isFunction[$index][6]) {
                $this->setFunctionModule($modules, $isFunction, 6, $index, $index % 2 === 0 ? 1 : 0);
            }
        }

        foreach (self::ALIGNMENT_PATTERN_POSITIONS[$version] as $row) {
            foreach (self::ALIGNMENT_PATTERN_POSITIONS[$version] as $col) {
                if ($isFunction[$row][$col]) {
                    continue;
                }

                $this->drawAlignmentPattern($modules, $isFunction, $col, $row);
            }
        }

        for ($index = 0; $index <= 8; $index++) {
            if ($index !== 6) {
                $this->setFunctionModule($modules, $isFunction, 8, $index, 0);
                $this->setFunctionModule($modules, $isFunction, $index, 8, 0);
            }
        }

        for ($index = 0; $index < 8; $index++) {
            $this->setFunctionModule($modules, $isFunction, $size - 1 - $index, 8, 0);
            $this->setFunctionModule($modules, $isFunction, 8, $size - 1 - $index, 0);
        }

        $this->setFunctionModule($modules, $isFunction, 8, $size - 8, 1);

        if ($version < 7) {
            return;
        }

        for ($index = 0; $index < 6; $index++) {
            for ($offset = 0; $offset < 3; $offset++) {
                $this->setFunctionModule($modules, $isFunction, $size - 11 + $offset, $index, 0);
                $this->setFunctionModule($modules, $isFunction, $index, $size - 11 + $offset, 0);
            }
        }
    }

    /**
     * @param list<list<int>> $modules
     * @param list<list<bool>> $isFunction
     */
    private function drawFinderPattern(array &$modules, array &$isFunction, int $x, int $y): void
    {
        $size = count($modules);
        for ($dy = -1; $dy <= 7; $dy++) {
            for ($dx = -1; $dx <= 7; $dx++) {
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx < 0 || $xx >= $size || $yy < 0 || $yy >= $size) {
                    continue;
                }

                $inside = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6;
                $isDark = $inside
                    && ($dx === 0
                        || $dx === 6
                        || $dy === 0
                        || $dy === 6
                        || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));

                $this->setFunctionModule($modules, $isFunction, $xx, $yy, $isDark ? 1 : 0);
            }
        }
    }

    /**
     * @param list<list<int>> $modules
     * @param list<list<bool>> $isFunction
     */
    private function drawAlignmentPattern(array &$modules, array &$isFunction, int $x, int $y): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $distance = abs($dx) > abs($dy) ? abs($dx) : abs($dy);
                $isDark   = $distance === 2 || ($dx === 0 && $dy === 0);
                $this->setFunctionModule($modules, $isFunction, $x + $dx, $y + $dy, $isDark ? 1 : 0);
            }
        }
    }

    /**
     * @param list<list<int>> $modules
     * @param list<list<bool>> $isFunction
     * @param list<int> $codewords
     */
    private function drawCodewords(array &$modules, array $isFunction, array $codewords): void
    {
        $size     = count($modules);
        $bitIndex = 0;
        $bitCount = count($codewords) * 8;

        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right--;
            }

            for ($vert = 0; $vert < $size; $vert++) {
                $y = ((($right + 1) & 2) === 0) ? $size - 1 - $vert : $vert;
                for ($offset = 0; $offset < 2; $offset++) {
                    $x = $right - $offset;
                    if ($isFunction[$y][$x]) {
                        continue;
                    }

                    $bit = 0;
                    if ($bitIndex < $bitCount) {
                        $byte = $codewords[intdiv($bitIndex, 8)];
                        $bit  = ($byte >> (7 - ($bitIndex % 8))) & 1;
                        $bitIndex++;
                    }

                    $modules[$y][$x] = $bit;
                }
            }
        }
    }

    /**
     * @param list<list<int>> $modules
     * @param list<list<bool>> $isFunction
     */
    private function applyMask(array &$modules, array $isFunction, int $mask): void
    {
        $size = count($modules);

        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if ($isFunction[$row][$col] || !$this->maskCondition($mask, $col, $row)) {
                    continue;
                }

                $modules[$row][$col] ^= 1;
            }
        }
    }

    private function maskCondition(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0       => ($x + $y) % 2 === 0,
            1       => $y % 2 === 0,
            2       => $x % 3 === 0,
            3       => ($x + $y) % 3 === 0,
            4       => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
            5       => (($x * $y) % 2) + (($x * $y) % 3) === 0,
            6       => (((($x * $y) % 2) + (($x * $y) % 3)) % 2) === 0,
            7       => (((($x + $y) % 2) + (($x * $y) % 3)) % 2) === 0,
            default => false,
        };
    }

    /**
     * @param list<list<int>> $modules
     */
    private function drawFormatBits(array &$modules, int $mask, QrErrorCorrectionLevel $level): void
    {
        $size = count($modules);
        $data = ($this->levelFormatBitsPrefix($level) << 3) | $mask;
        $rem  = $data;

        for ($index = 0; $index < 10; $index++) {
            $rem = ($rem << 1) ^ ((($rem >> 9) & 1) === 1 ? 0x537 : 0);
        }

        $bits = (($data << 10) | $rem) ^ 0x5412;

        for ($index = 0; $index <= 5; $index++) {
            $modules[$index][8] = $this->getBit($bits, $index);
        }
        $modules[7][8] = $this->getBit($bits, 6);
        $modules[8][8] = $this->getBit($bits, 7);
        $modules[8][7] = $this->getBit($bits, 8);

        for ($index = 9; $index < 15; $index++) {
            $modules[8][14 - $index] = $this->getBit($bits, $index);
        }

        for ($index = 0; $index < 8; $index++) {
            $modules[8][$size - 1 - $index] = $this->getBit($bits, $index);
        }

        for ($index = 8; $index < 15; $index++) {
            $modules[$size - 15 + $index][8] = $this->getBit($bits, $index);
        }

        $modules[$size - 8][8] = 1;
    }

    /**
     * @return array{data: array<int, int>, ecc: array<int, int>, blocks: array<int, int>}
     */
    private function levelData(QrErrorCorrectionLevel $level): array
    {
        return self::CAPACITY[$level->value];
    }

    private function levelFormatBitsPrefix(QrErrorCorrectionLevel $level): int
    {
        return match ($level) {
            QrErrorCorrectionLevel::M => 0b00,
            QrErrorCorrectionLevel::H => 0b10,
        };
    }

    /**
     * @param list<list<int>> $modules
     */
    private function drawVersionBits(array &$modules, int $version): void
    {
        $size = count($modules);
        $rem  = $version;

        for ($index = 0; $index < 12; $index++) {
            $rem = ($rem << 1) ^ ((($rem >> 11) & 1) === 1 ? 0x1f25 : 0);
        }

        $bits = ($version << 12) | $rem;

        for ($index = 0; $index < 18; $index++) {
            $bit             = $this->getBit($bits, $index);
            $a               = $size - 11 + ($index % 3);
            $b               = intdiv($index, 3);
            $modules[$b][$a] = $bit;
            $modules[$a][$b] = $bit;
        }
    }

    private function getBit(int $value, int $index): int
    {
        return ($value >> $index) & 1;
    }

    /**
     * @param list<list<int>> $modules
     */
    private function penaltyScore(array $modules): int
    {
        $size    = count($modules);
        $penalty = 0;

        for ($row = 0; $row < $size; $row++) {
            $runColor = $modules[$row][0];
            $runLen   = 1;

            for ($col = 1; $col < $size; $col++) {
                if ($modules[$row][$col] === $runColor) {
                    $runLen++;
                    continue;
                }

                $penalty += $this->runPenalty($runLen);
                $runColor = $modules[$row][$col];
                $runLen   = 1;
            }

            $penalty += $this->runPenalty($runLen);
        }

        for ($col = 0; $col < $size; $col++) {
            $runColor = $modules[0][$col];
            $runLen   = 1;

            for ($row = 1; $row < $size; $row++) {
                if ($modules[$row][$col] === $runColor) {
                    $runLen++;
                    continue;
                }

                $penalty += $this->runPenalty($runLen);
                $runColor = $modules[$row][$col];
                $runLen   = 1;
            }

            $penalty += $this->runPenalty($runLen);
        }

        for ($row = 0; $row < $size - 1; $row++) {
            for ($col = 0; $col < $size - 1; $col++) {
                $value = $modules[$row][$col];
                if ($value === $modules[$row][$col + 1]
                    && $value === $modules[$row + 1][$col]
                    && $value === $modules[$row + 1][$col + 1]
                ) {
                    $penalty += 3;
                }
            }
        }

        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col <= $size - 11; $col++) {
                if ($this->matchesPattern($modules[$row], $col)) {
                    $penalty += 40;
                }
            }
        }

        for ($col = 0; $col < $size; $col++) {
            for ($row = 0; $row <= $size - 11; $row++) {
                $columnSlice = [];
                for ($offset = 0; $offset < 11; $offset++) {
                    $columnSlice[] = $modules[$row + $offset][$col];
                }

                if ($this->isPattern($columnSlice)) {
                    $penalty += 40;
                }
            }
        }

        $darkModules = 0;
        foreach ($modules as $row) {
            foreach ($row as $value) {
                if ($value === 1) {
                    $darkModules++;
                }
            }
        }

        $total     = $size * $size;
        $darkRatio = ($darkModules * 100.0) / $total;
        $deviation = abs($darkRatio - 50.0);
        $penalty += (int) floor($deviation / 5.0) * 10;

        return $penalty;
    }

    private function runPenalty(int $length): int
    {
        if ($length < 5) {
            return 0;
        }

        return $length - 2;
    }

    /**
     * @param list<int> $line
     */
    private function matchesPattern(array $line, int $offset): bool
    {
        $slice = [];
        for ($index = 0; $index < 11; $index++) {
            $slice[] = $line[$offset + $index];
        }

        return $this->isPattern($slice);
    }

    /**
     * @param list<int> $slice
     */
    private function isPattern(array $slice): bool
    {
        return $slice === [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0]
            || $slice === [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];
    }

    /**
     * @param list<list<int>> $modules
     * @param list<list<bool>> $isFunction
     */
    private function setFunctionModule(array &$modules, array &$isFunction, int $x, int $y, int $value): void
    {
        $modules[$y][$x]    = $value;
        $isFunction[$y][$x] = true;
    }

    /**
     * @param list<list<int>> $modules
     *
     * @return list<list<int>>
     */
    private function cloneMatrix(array $modules): array
    {
        $copy = [];
        foreach ($modules as $row) {
            $copy[] = $row;
        }

        return $copy;
    }

    /**
     * @param list<list<int>> $modules
     *
     * @return list<list<bool>>
     */
    private function toBoolMatrix(array $modules): array
    {
        $matrix = [];
        foreach ($modules as $row) {
            $boolRow = [];
            foreach ($row as $value) {
                $boolRow[] = $value === 1;
            }

            $matrix[] = $boolRow;
        }

        return $matrix;
    }
}
