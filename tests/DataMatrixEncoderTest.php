<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\Support\DataMatrixEncoder;
use PHPUnit\Framework\Attributes\{CoversClass, CoversMethod, DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function str_repeat;

#[CoversClass(DataMatrixEncoder::class)]
#[CoversMethod(DataMatrixEncoder::class, 'encode')]
final class DataMatrixEncoderTest extends TestCase
{
    /**
     * Проверяет все четыре границы каждого региона для всех поддерживаемых размеров ECC200.
     *
     * @see DataMatrixEncoder::encode()
     */
    #[Test]
    #[DataProvider('symbols')]
    public function preservesRegionFinderPatterns(int $size, int $capacity, int $region): void
    {
        $matrix = new DataMatrixEncoder()->encode(str_repeat('A', $capacity));

        $this->assertCount($size, $matrix);
        foreach ($matrix as $row) {
            $this->assertCount($size, $row);
        }

        for ($top = 0; $top < $size; $top += $region) {
            for ($left = 0; $left < $size; $left += $region) {
                for ($offset = 0; $offset < $region; $offset++) {
                    $this->assertTrue($matrix[$top + $offset][$left], 'Solid left edge');
                    $this->assertTrue($matrix[$top + $region - 1][$left + $offset], 'Solid bottom edge');
                    $this->assertSame($offset % 2 === 0, $matrix[$top][$left + $offset], 'Alternating top edge');
                    $this->assertSame($offset % 2 === 1, $matrix[$top + $offset][$left + $region - 1], 'Alternating right edge');
                }
            }
        }
    }

    /**
     * Проверяет штатный выбор размера для регрессии 36×36 и границ ёмкости с учётом сжатия пар цифр.
     *
     * @see DataMatrixEncoder::encode()
     */
    #[Test]
    #[DataProvider('payloads')]
    public function selectsSizeByEncodedCapacity(string $payload, int $size): void
    {
        $this->assertCount($size, new DataMatrixEncoder()->encode($payload));
    }

    /** @return iterable<array{int, int, int}> */
    public static function symbols(): iterable
    {
        yield from DataMatrixCases::symbols();
    }

    /** @return iterable<string, array{string, int}> */
    public static function payloads(): iterable
    {
        yield from DataMatrixCases::payloads();
    }
}
