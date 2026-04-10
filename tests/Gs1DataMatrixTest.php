<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Generator\DataMatrixGenerator;
use PhpSoftBox\Barcode\Support\DataMatrixEncoder;
use PhpSoftBox\Barcode\Support\MatrixRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;
use function str_repeat;

#[CoversClass(DataMatrixEncoder::class)]
#[CoversClass(DataMatrixGenerator::class)]
#[CoversMethod(DataMatrixEncoder::class, 'codewords')]
#[CoversMethod(DataMatrixEncoder::class, 'encode')]
#[CoversMethod(DataMatrixGenerator::class, 'generate')]
final class Gs1DataMatrixTest extends TestCase
{
    /**
     * Проверим, что без флага кодовые слова прежние: восемь пар цифр GTIN.
     *
     * @see DataMatrixEncoder::codewords()
     */
    #[Test]
    public function codewordsWithoutGs1AreUnchanged(): void
    {
        self::assertSame(
            [131, 134, 193, 130, 153, 175, 131, 153],
            new DataMatrixEncoder()->codewords('0104630023450123'),
        );
    }

    /**
     * Проверим, что с флагом первым идёт FNC1, а остальные кодовые слова не меняются.
     *
     * @see DataMatrixEncoder::codewords()
     */
    #[Test]
    public function codewordsWithGs1StartWithFnc1(): void
    {
        $encoder = new DataMatrixEncoder();
        $data    = '0104630023450123';

        self::assertSame(
            [DataMatrixEncoder::FNC1, ...$encoder->codewords($data)],
            $encoder->codewords($data, gs1: true),
        );
    }

    /**
     * Проверим, что разделитель GS остаётся ASCII-символом 29 (кодовое слово 30), а не FNC1.
     *
     * @see DataMatrixEncoder::codewords()
     */
    #[Test]
    public function groupSeparatorStaysAsciiCodeword(): void
    {
        $codewords = new DataMatrixEncoder()->codewords("91EE06\x1D92ABC", gs1: true);

        self::assertSame(DataMatrixEncoder::FNC1, $codewords[0]);
        self::assertContains(30, $codewords);
    }

    /**
     * Проверим, что на границе ёмкости символ становится на ступень больше: FNC1 занимает кодовое слово.
     *
     * @see DataMatrixEncoder::encode()
     */
    #[Test]
    public function symbolGrowsAtCapacityBoundary(): void
    {
        // 124 цифры — ровно 62 кодовых слова, то есть полная ёмкость символа 32x32.
        $data    = str_repeat('1234567890', 12) . '1234';
        $encoder = new DataMatrixEncoder();

        self::assertSame(62, count($encoder->codewords($data)));
        self::assertCount(32, $encoder->encode($data));
        self::assertCount(36, $encoder->encode($data, gs1: true));
    }

    /**
     * Проверим, что генератор передаёт флаг в кодировщик.
     *
     * @see DataMatrixGenerator::generate()
     */
    #[Test]
    public function generatorPassesGs1FlagToEncoder(): void
    {
        $encoder = new DataMatrixEncoder();
        $data    = '010463002345012321ABCDE12345678';

        $result = new DataMatrixGenerator()->generate(
            data: $data,
            type: BarcodeType::DataMatrix,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg, height: 256, gs1: true),
        );

        self::assertSame(
            new MatrixRenderer()->renderSvg($encoder->encode($data, gs1: true), 256, 10),
            $result->content,
        );

        TestArtifactStorage::save('gs1-datamatrix-generator', 'svg', $result->content);
    }
}
