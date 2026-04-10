<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Support;

use PhpSoftBox\Barcode\Exception\BarcodeException;

use function array_fill;
use function array_merge;
use function count;
use function ctype_digit;
use function intdiv;
use function ord;
use function strlen;
use function substr;

final class DataMatrixEncoder
{
    /**
     * Square ECC200 symbols.
     *
     * @var list<array{
     *   symbolRows:int,
     *   symbolCols:int,
     *   dataRegionRows:int,
     *   dataRegionCols:int,
     *   verticalRegions:int,
     *   horizontalRegions:int,
     *   dataCodewords:int,
     *   eccCodewords:int,
     *   interleavedBlockCount:int,
     *   rsBlockDataCodewords:int,
     *   rsBlockEccCodewords:int
     * }>
     */
    private const SYMBOLS = [
        ['symbolRows' => 10, 'symbolCols' => 10, 'dataRegionRows' => 8, 'dataRegionCols' => 8, 'verticalRegions' => 1, 'horizontalRegions' => 1, 'dataCodewords' => 3, 'eccCodewords' => 5, 'interleavedBlockCount' => 1, 'rsBlockDataCodewords' => 3, 'rsBlockEccCodewords' => 5],
        ['symbolRows' => 12, 'symbolCols' => 12, 'dataRegionRows' => 10, 'dataRegionCols' => 10, 'verticalRegions' => 1, 'horizontalRegions' => 1, 'dataCodewords' => 5, 'eccCodewords' => 7, 'interleavedBlockCount' => 1, 'rsBlockDataCodewords' => 5, 'rsBlockEccCodewords' => 7],
        ['symbolRows' => 14, 'symbolCols' => 14, 'dataRegionRows' => 12, 'dataRegionCols' => 12, 'verticalRegions' => 1, 'horizontalRegions' => 1, 'dataCodewords' => 8, 'eccCodewords' => 10, 'interleavedBlockCount' => 1, 'rsBlockDataCodewords' => 8, 'rsBlockEccCodewords' => 10],
        ['symbolRows' => 16, 'symbolCols' => 16, 'dataRegionRows' => 14, 'dataRegionCols' => 14, 'verticalRegions' => 1, 'horizontalRegions' => 1, 'dataCodewords' => 12, 'eccCodewords' => 12, 'interleavedBlockCount' => 1, 'rsBlockDataCodewords' => 12, 'rsBlockEccCodewords' => 12],
        ['symbolRows' => 18, 'symbolCols' => 18, 'dataRegionRows' => 16, 'dataRegionCols' => 16, 'verticalRegions' => 1, 'horizontalRegions' => 1, 'dataCodewords' => 18, 'eccCodewords' => 14, 'interleavedBlockCount' => 1, 'rsBlockDataCodewords' => 18, 'rsBlockEccCodewords' => 14],
        ['symbolRows' => 20, 'symbolCols' => 20, 'dataRegionRows' => 18, 'dataRegionCols' => 18, 'verticalRegions' => 1, 'horizontalRegions' => 1, 'dataCodewords' => 22, 'eccCodewords' => 18, 'interleavedBlockCount' => 1, 'rsBlockDataCodewords' => 22, 'rsBlockEccCodewords' => 18],
        ['symbolRows' => 22, 'symbolCols' => 22, 'dataRegionRows' => 20, 'dataRegionCols' => 20, 'verticalRegions' => 1, 'horizontalRegions' => 1, 'dataCodewords' => 30, 'eccCodewords' => 20, 'interleavedBlockCount' => 1, 'rsBlockDataCodewords' => 30, 'rsBlockEccCodewords' => 20],
        ['symbolRows' => 24, 'symbolCols' => 24, 'dataRegionRows' => 22, 'dataRegionCols' => 22, 'verticalRegions' => 1, 'horizontalRegions' => 1, 'dataCodewords' => 36, 'eccCodewords' => 24, 'interleavedBlockCount' => 1, 'rsBlockDataCodewords' => 36, 'rsBlockEccCodewords' => 24],
        ['symbolRows' => 26, 'symbolCols' => 26, 'dataRegionRows' => 24, 'dataRegionCols' => 24, 'verticalRegions' => 1, 'horizontalRegions' => 1, 'dataCodewords' => 44, 'eccCodewords' => 28, 'interleavedBlockCount' => 1, 'rsBlockDataCodewords' => 44, 'rsBlockEccCodewords' => 28],
        ['symbolRows' => 32, 'symbolCols' => 32, 'dataRegionRows' => 14, 'dataRegionCols' => 14, 'verticalRegions' => 2, 'horizontalRegions' => 2, 'dataCodewords' => 62, 'eccCodewords' => 36, 'interleavedBlockCount' => 1, 'rsBlockDataCodewords' => 62, 'rsBlockEccCodewords' => 36],
        ['symbolRows' => 36, 'symbolCols' => 36, 'dataRegionRows' => 16, 'dataRegionCols' => 16, 'verticalRegions' => 2, 'horizontalRegions' => 2, 'dataCodewords' => 86, 'eccCodewords' => 42, 'interleavedBlockCount' => 1, 'rsBlockDataCodewords' => 86, 'rsBlockEccCodewords' => 42],
        ['symbolRows' => 40, 'symbolCols' => 40, 'dataRegionRows' => 18, 'dataRegionCols' => 18, 'verticalRegions' => 2, 'horizontalRegions' => 2, 'dataCodewords' => 114, 'eccCodewords' => 48, 'interleavedBlockCount' => 1, 'rsBlockDataCodewords' => 114, 'rsBlockEccCodewords' => 48],
        ['symbolRows' => 44, 'symbolCols' => 44, 'dataRegionRows' => 20, 'dataRegionCols' => 20, 'verticalRegions' => 2, 'horizontalRegions' => 2, 'dataCodewords' => 144, 'eccCodewords' => 56, 'interleavedBlockCount' => 1, 'rsBlockDataCodewords' => 144, 'rsBlockEccCodewords' => 56],
        ['symbolRows' => 48, 'symbolCols' => 48, 'dataRegionRows' => 22, 'dataRegionCols' => 22, 'verticalRegions' => 2, 'horizontalRegions' => 2, 'dataCodewords' => 174, 'eccCodewords' => 68, 'interleavedBlockCount' => 1, 'rsBlockDataCodewords' => 174, 'rsBlockEccCodewords' => 68],
        ['symbolRows' => 52, 'symbolCols' => 52, 'dataRegionRows' => 24, 'dataRegionCols' => 24, 'verticalRegions' => 2, 'horizontalRegions' => 2, 'dataCodewords' => 204, 'eccCodewords' => 84, 'interleavedBlockCount' => 2, 'rsBlockDataCodewords' => 102, 'rsBlockEccCodewords' => 42],
        ['symbolRows' => 64, 'symbolCols' => 64, 'dataRegionRows' => 14, 'dataRegionCols' => 14, 'verticalRegions' => 4, 'horizontalRegions' => 4, 'dataCodewords' => 280, 'eccCodewords' => 112, 'interleavedBlockCount' => 2, 'rsBlockDataCodewords' => 140, 'rsBlockEccCodewords' => 56],
        ['symbolRows' => 72, 'symbolCols' => 72, 'dataRegionRows' => 16, 'dataRegionCols' => 16, 'verticalRegions' => 4, 'horizontalRegions' => 4, 'dataCodewords' => 368, 'eccCodewords' => 144, 'interleavedBlockCount' => 4, 'rsBlockDataCodewords' => 92, 'rsBlockEccCodewords' => 36],
        ['symbolRows' => 80, 'symbolCols' => 80, 'dataRegionRows' => 18, 'dataRegionCols' => 18, 'verticalRegions' => 4, 'horizontalRegions' => 4, 'dataCodewords' => 456, 'eccCodewords' => 192, 'interleavedBlockCount' => 4, 'rsBlockDataCodewords' => 114, 'rsBlockEccCodewords' => 48],
        ['symbolRows' => 88, 'symbolCols' => 88, 'dataRegionRows' => 20, 'dataRegionCols' => 20, 'verticalRegions' => 4, 'horizontalRegions' => 4, 'dataCodewords' => 576, 'eccCodewords' => 224, 'interleavedBlockCount' => 4, 'rsBlockDataCodewords' => 144, 'rsBlockEccCodewords' => 56],
        ['symbolRows' => 96, 'symbolCols' => 96, 'dataRegionRows' => 22, 'dataRegionCols' => 22, 'verticalRegions' => 4, 'horizontalRegions' => 4, 'dataCodewords' => 696, 'eccCodewords' => 272, 'interleavedBlockCount' => 4, 'rsBlockDataCodewords' => 174, 'rsBlockEccCodewords' => 68],
        ['symbolRows' => 104, 'symbolCols' => 104, 'dataRegionRows' => 24, 'dataRegionCols' => 24, 'verticalRegions' => 4, 'horizontalRegions' => 4, 'dataCodewords' => 816, 'eccCodewords' => 336, 'interleavedBlockCount' => 6, 'rsBlockDataCodewords' => 136, 'rsBlockEccCodewords' => 56],
        ['symbolRows' => 120, 'symbolCols' => 120, 'dataRegionRows' => 18, 'dataRegionCols' => 18, 'verticalRegions' => 6, 'horizontalRegions' => 6, 'dataCodewords' => 1050, 'eccCodewords' => 408, 'interleavedBlockCount' => 6, 'rsBlockDataCodewords' => 175, 'rsBlockEccCodewords' => 68],
        ['symbolRows' => 132, 'symbolCols' => 132, 'dataRegionRows' => 20, 'dataRegionCols' => 20, 'verticalRegions' => 6, 'horizontalRegions' => 6, 'dataCodewords' => 1304, 'eccCodewords' => 496, 'interleavedBlockCount' => 8, 'rsBlockDataCodewords' => 163, 'rsBlockEccCodewords' => 62],
        ['symbolRows' => 144, 'symbolCols' => 144, 'dataRegionRows' => 22, 'dataRegionCols' => 22, 'verticalRegions' => 6, 'horizontalRegions' => 6, 'dataCodewords' => 1558, 'eccCodewords' => 620, 'interleavedBlockCount' => 10, 'rsBlockDataCodewords' => 156, 'rsBlockEccCodewords' => 62],
    ];

    private ReedSolomon $reedSolomon;

    public function __construct()
    {
        $this->reedSolomon = ReedSolomon::forDataMatrix();
    }

    /**
     * @return list<list<bool>>
     */
    public function encode(string $data): array
    {
        $encoded   = $this->encodeAscii($data);
        $symbol    = $this->resolveSymbol(count($encoded));
        $padded    = $this->padCodewords($encoded, $symbol['dataCodewords']);
        $codewords = $this->addErrorCorrection($padded, $symbol);
        $dataBits  = $this->placeCodewords(
            $codewords,
            $symbol['dataRegionRows'] * $symbol['verticalRegions'],
            $symbol['dataRegionCols'] * $symbol['horizontalRegions'],
        );

        return $this->addFinderPattern($dataBits, $symbol);
    }

    /**
     * @return list<int>
     */
    private function encodeAscii(string $data): array
    {
        $codewords = [];
        $size      = strlen($data);
        $index     = 0;

        while ($index < $size) {
            if ($index + 1 < $size) {
                $pair = substr($data, $index, 2);
                if (ctype_digit($pair)) {
                    $codewords[] = 130 + (int) $pair;
                    $index += 2;
                    continue;
                }
            }

            $byte = ord($data[$index]);
            if ($byte <= 127) {
                $codewords[] = $byte + 1;
            } else {
                $codewords[] = 235;
                $codewords[] = $byte - 127;
            }

            $index++;
        }

        return $codewords;
    }

    /**
     * @return array{
     *   symbolRows:int,
     *   symbolCols:int,
     *   dataRegionRows:int,
     *   dataRegionCols:int,
     *   verticalRegions:int,
     *   horizontalRegions:int,
     *   dataCodewords:int,
     *   eccCodewords:int,
     *   interleavedBlockCount:int,
     *   rsBlockDataCodewords:int,
     *   rsBlockEccCodewords:int
     * }
     */
    private function resolveSymbol(int $codewordCount): array
    {
        foreach (self::SYMBOLS as $symbol) {
            if ($codewordCount <= $symbol['dataCodewords']) {
                return $symbol;
            }
        }

        throw new BarcodeException('DataMatrix payload is too large for current encoder (max square ECC200 symbol is 144x144).');
    }

    /**
     * @param list<int> $codewords
     *
     * @return list<int>
     */
    private function padCodewords(array $codewords, int $capacity): array
    {
        if (count($codewords) === $capacity) {
            return $codewords;
        }

        $codewords[] = 129;

        while (count($codewords) < $capacity) {
            $position    = count($codewords) + 1;
            $pseudo      = ((149 * $position) % 253) + 1;
            $randomized  = 129 + $pseudo;
            $codewords[] = $randomized <= 254 ? $randomized : $randomized - 254;
        }

        return $codewords;
    }

    /**
     * @param list<int> $dataCodewords
     * @param array{
     *   dataCodewords:int,
     *   eccCodewords:int,
     *   interleavedBlockCount:int,
     *   rsBlockDataCodewords:int,
     *   rsBlockEccCodewords:int
     * } $symbol
     *
     * @return list<int>
     */
    private function addErrorCorrection(array $dataCodewords, array $symbol): array
    {
        $blockCount = $symbol['interleavedBlockCount'];
        if ($blockCount === 1) {
            return array_merge(
                $dataCodewords,
                $this->reedSolomon->encode($dataCodewords, $symbol['eccCodewords']),
            );
        }

        $fullCodewords = array_merge(
            $dataCodewords,
            array_fill(0, $symbol['eccCodewords'], 0),
        );

        for ($block = 0; $block < $blockCount; $block++) {
            $blockData = [];
            for ($index = $block; $index < $symbol['dataCodewords']; $index += $blockCount) {
                $blockData[] = $dataCodewords[$index];
            }

            $expectedDataLength = $this->blockDataLength($symbol, $block);
            if (count($blockData) !== $expectedDataLength) {
                throw new BarcodeException('DataMatrix interleaved block size mismatch.');
            }

            $ecc = $this->reedSolomon->encode($blockData, $symbol['rsBlockEccCodewords']);
            for ($index = 0; $index < count($ecc); $index++) {
                $fullCodewords[$symbol['dataCodewords'] + $block + ($index * $blockCount)] = $ecc[$index];
            }
        }

        return $fullCodewords;
    }

    /**
     * @param array{dataCodewords:int,interleavedBlockCount:int,rsBlockDataCodewords:int} $symbol
     */
    private function blockDataLength(array $symbol, int $block): int
    {
        if ($symbol['dataCodewords'] === 1558) {
            return $block < 8 ? 156 : 155;
        }

        return $symbol['rsBlockDataCodewords'];
    }

    /**
     * @param list<int> $codewords
     *
     * @return list<list<bool>>
     */
    private function placeCodewords(array $codewords, int $rows, int $cols): array
    {
        $bits = array_fill(0, $rows, array_fill(0, $cols, -1));

        $position = 0;
        $row      = 4;
        $col      = 0;

        do {
            if ($row === $rows && $col === 0) {
                $this->corner1($bits, $codewords, $position++);
            }

            if ($row === $rows - 2 && $col === 0 && $cols % 4 !== 0) {
                $this->corner2($bits, $codewords, $position++);
            }

            if ($row === $rows - 2 && $col === 0 && $cols % 8 === 4) {
                $this->corner3($bits, $codewords, $position++);
            }

            if ($row === $rows + 4 && $col === 2 && $cols % 8 === 0) {
                $this->corner4($bits, $codewords, $position++);
            }

            do {
                if ($row < $rows && $col >= 0 && !$this->hasBit($bits, $row, $col)) {
                    $this->utah($bits, $codewords, $row, $col, $position++);
                }

                $row -= 2;
                $col += 2;
            } while ($row >= 0 && $col < $cols);

            $row += 1;
            $col += 3;

            do {
                if ($row >= 0 && $col < $cols && !$this->hasBit($bits, $row, $col)) {
                    $this->utah($bits, $codewords, $row, $col, $position++);
                }

                $row += 2;
                $col -= 2;
            } while ($row < $rows && $col >= 0);

            $row += 3;
            $col += 1;
        } while ($row < $rows || $col < $cols);

        if (!$this->hasBit($bits, $rows - 1, $cols - 1)) {
            $bits[$rows - 1][$cols - 1] = 1;
            $bits[$rows - 2][$cols - 2] = 1;
        }

        $matrix = [];
        for ($r = 0; $r < $rows; $r++) {
            $line = [];
            for ($c = 0; $c < $cols; $c++) {
                $line[] = $bits[$r][$c] === 1;
            }

            $matrix[] = $line;
        }

        return $matrix;
    }

    /**
     * @param list<list<int>> $bits
     * @param list<int> $codewords
     */
    private function utah(array &$bits, array $codewords, int $row, int $col, int $position): void
    {
        $this->module($bits, $codewords, $row - 2, $col - 2, $position, 1);
        $this->module($bits, $codewords, $row - 2, $col - 1, $position, 2);
        $this->module($bits, $codewords, $row - 1, $col - 2, $position, 3);
        $this->module($bits, $codewords, $row - 1, $col - 1, $position, 4);
        $this->module($bits, $codewords, $row - 1, $col, $position, 5);
        $this->module($bits, $codewords, $row, $col - 2, $position, 6);
        $this->module($bits, $codewords, $row, $col - 1, $position, 7);
        $this->module($bits, $codewords, $row, $col, $position, 8);
    }

    /**
     * @param list<list<int>> $bits
     * @param list<int> $codewords
     */
    private function corner1(array &$bits, array $codewords, int $position): void
    {
        $rows = count($bits);
        $cols = count($bits[0]);

        $this->module($bits, $codewords, $rows - 1, 0, $position, 1);
        $this->module($bits, $codewords, $rows - 1, 1, $position, 2);
        $this->module($bits, $codewords, $rows - 1, 2, $position, 3);
        $this->module($bits, $codewords, 0, $cols - 2, $position, 4);
        $this->module($bits, $codewords, 0, $cols - 1, $position, 5);
        $this->module($bits, $codewords, 1, $cols - 1, $position, 6);
        $this->module($bits, $codewords, 2, $cols - 1, $position, 7);
        $this->module($bits, $codewords, 3, $cols - 1, $position, 8);
    }

    /**
     * @param list<list<int>> $bits
     * @param list<int> $codewords
     */
    private function corner2(array &$bits, array $codewords, int $position): void
    {
        $rows = count($bits);
        $cols = count($bits[0]);

        $this->module($bits, $codewords, $rows - 3, 0, $position, 1);
        $this->module($bits, $codewords, $rows - 2, 0, $position, 2);
        $this->module($bits, $codewords, $rows - 1, 0, $position, 3);
        $this->module($bits, $codewords, 0, $cols - 4, $position, 4);
        $this->module($bits, $codewords, 0, $cols - 3, $position, 5);
        $this->module($bits, $codewords, 0, $cols - 2, $position, 6);
        $this->module($bits, $codewords, 0, $cols - 1, $position, 7);
        $this->module($bits, $codewords, 1, $cols - 1, $position, 8);
    }

    /**
     * @param list<list<int>> $bits
     * @param list<int> $codewords
     */
    private function corner3(array &$bits, array $codewords, int $position): void
    {
        $rows = count($bits);
        $cols = count($bits[0]);

        $this->module($bits, $codewords, $rows - 3, 0, $position, 1);
        $this->module($bits, $codewords, $rows - 2, 0, $position, 2);
        $this->module($bits, $codewords, $rows - 1, 0, $position, 3);
        $this->module($bits, $codewords, 0, $cols - 2, $position, 4);
        $this->module($bits, $codewords, 0, $cols - 1, $position, 5);
        $this->module($bits, $codewords, 1, $cols - 1, $position, 6);
        $this->module($bits, $codewords, 2, $cols - 1, $position, 7);
        $this->module($bits, $codewords, 3, $cols - 1, $position, 8);
    }

    /**
     * @param list<list<int>> $bits
     * @param list<int> $codewords
     */
    private function corner4(array &$bits, array $codewords, int $position): void
    {
        $rows = count($bits);
        $cols = count($bits[0]);

        $this->module($bits, $codewords, $rows - 1, 0, $position, 1);
        $this->module($bits, $codewords, $rows - 1, $cols - 1, $position, 2);
        $this->module($bits, $codewords, 0, $cols - 3, $position, 3);
        $this->module($bits, $codewords, 0, $cols - 2, $position, 4);
        $this->module($bits, $codewords, 0, $cols - 1, $position, 5);
        $this->module($bits, $codewords, 1, $cols - 3, $position, 6);
        $this->module($bits, $codewords, 1, $cols - 2, $position, 7);
        $this->module($bits, $codewords, 1, $cols - 1, $position, 8);
    }

    /**
     * @param list<list<int>> $bits
     * @param list<int> $codewords
     */
    private function module(
        array &$bits,
        array $codewords,
        int $row,
        int $col,
        int $position,
        int $bit,
    ): void {
        $rows = count($bits);
        $cols = count($bits[0]);

        if ($row < 0) {
            $row += $rows;
            $col += 4 - (($rows + 4) % 8);
        }

        if ($col < 0) {
            $col += $cols;
            $row += 4 - (($cols + 4) % 8);
        }

        $bits[$row][$col] = $this->codewordBit($codewords, $position, $bit) ? 1 : 0;
    }

    /**
     * @param list<int> $codewords
     */
    private function codewordBit(array $codewords, int $position, int $bit): bool
    {
        $codeword = $codewords[$position] ?? 0;

        return (($codeword >> (8 - $bit)) & 1) === 1;
    }

    /**
     * @param list<list<int>> $bits
     */
    private function hasBit(array $bits, int $row, int $col): bool
    {
        return $bits[$row][$col] >= 0;
    }

    /**
     * @param list<list<bool>> $dataBits
     * @param array{
     *   dataRegionRows:int,
     *   dataRegionCols:int,
     *   verticalRegions:int,
     *   horizontalRegions:int
     * } $symbol
     *
     * @return list<list<bool>>
     */
    private function addFinderPattern(array $dataBits, array $symbol): array
    {
        $dataRegionRows = $symbol['dataRegionRows'];
        $dataRegionCols = $symbol['dataRegionCols'];
        $regionHeight   = $dataRegionRows + 2;
        $regionWidth    = $dataRegionCols + 2;
        $rows           = ($dataRegionRows * $symbol['verticalRegions']) + ($symbol['verticalRegions'] * 2);
        $cols           = ($dataRegionCols * $symbol['horizontalRegions']) + ($symbol['horizontalRegions'] * 2);
        $matrix         = array_fill(0, $rows, array_fill(0, $cols, false));

        for ($row = 0; $row < $rows; $row++) {
            $regionRow   = intdiv($row, $regionHeight);
            $rowInRegion = $row % $regionHeight;

            for ($col = 0; $col < $cols; $col++) {
                $regionCol   = intdiv($col, $regionWidth);
                $colInRegion = $col % $regionWidth;

                if ($colInRegion === 0 || $rowInRegion === $dataRegionRows + 1) {
                    $matrix[$row][$col] = true;
                    continue;
                }

                if ($rowInRegion === 0) {
                    $matrix[$row][$col] = $colInRegion % 2 === 0;
                    continue;
                }

                if ($colInRegion === $dataRegionCols + 1) {
                    $matrix[$row][$col] = $rowInRegion % 2 === 0;
                    continue;
                }

                $dataRow            = ($regionRow * $dataRegionRows) + $rowInRegion - 1;
                $dataCol            = ($regionCol * $dataRegionCols) + $colInRegion - 1;
                $matrix[$row][$col] = $dataBits[$dataRow][$dataCol];
            }
        }

        return $matrix;
    }
}
