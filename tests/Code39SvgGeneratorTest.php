<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Code39Charset;
use PhpSoftBox\Barcode\Generator\Code39SvgGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_contains;
use function substr_count;

#[CoversClass(Code39SvgGenerator::class)]
#[CoversMethod(Code39SvgGenerator::class, 'generate')]
#[CoversMethod(Code39SvgGenerator::class, 'supports')]
final class Code39SvgGeneratorTest extends TestCase
{
    /**
     * Проверим, что SVG содержит исходное значение подписью и рассчитанную ширину.
     *
     * @see Code39SvgGenerator::generate()
     */
    #[Test]
    public function generatesSvgWithHumanReadableText(): void
    {
        $result = new Code39SvgGenerator()->generate(
            data: 'CELL-A1',
            type: BarcodeType::Code39,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
        );

        $this->assertSame('image/svg+xml', $result->mimeType);
        $this->assertTrue(str_contains($result->content, '<svg'));
        $this->assertTrue(str_contains($result->content, '>CELL-A1</text>'));

        // 9 символов (*CELL-A1*) по 12 модулей, 8 разделяющих пробелов и свободные зоны по 10 модулей.
        $this->assertSame((9 * 12 + 8 + 20) * 2, $result->width);

        TestArtifactStorage::save('code39-svg-generator', 'svg', $result->content);
    }

    /**
     * Проверим, что контрольный символ добавляет к изображению ещё один символ Code 39.
     *
     * @see Code39SvgGenerator::generate()
     */
    #[Test]
    public function checksumOptionAddsOneCharacter(): void
    {
        $generator = new Code39SvgGenerator();

        $plain = $generator->generate(
            data: 'CODE39',
            type: BarcodeType::Code39,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
        );

        $withChecksum = $generator->generate(
            data: 'CODE39',
            type: BarcodeType::Code39,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg, code39Checksum: true),
        );

        // Символ Code 39 занимает 12 модулей и ещё один модуль на разделяющий пробел.
        $this->assertSame($plain->width + 13 * 2, $withChecksum->width);
        // Подпись остаётся исходным значением: контрольный символ в ней не показывается.
        $this->assertTrue(str_contains($withChecksum->content, '>CODE39</text>'));
    }

    /**
     * Проверим, что отношение 3:1 делает изображение шире при той же ширине модуля.
     *
     * @see Code39SvgGenerator::generate()
     */
    #[Test]
    public function wideRatioOptionWidensBars(): void
    {
        $generator = new Code39SvgGenerator();

        $narrow = $generator->generate(
            data: 'A',
            type: BarcodeType::Code39,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
        );

        $wide = $generator->generate(
            data: 'A',
            type: BarcodeType::Code39,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg, code39WideRatio: 3),
        );

        $this->assertSame((3 * 12 + 2 + 20) * 2, $narrow->width);
        $this->assertSame((3 * 15 + 2 + 20) * 2, $wide->width);
    }

    /**
     * Проверим, что подпись экранируется и не ломает SVG.
     *
     * @see Code39SvgGenerator::generate()
     */
    #[Test]
    public function escapesHumanReadableText(): void
    {
        $result = new Code39SvgGenerator()->generate(
            data: 'a&b',
            type: BarcodeType::Code39,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg, code39Charset: Code39Charset::FullAscii),
        );

        $this->assertTrue(str_contains($result->content, '>a&amp;b</text>'));
        $this->assertSame(0, substr_count($result->content, '>a&b<'));
    }

    /**
     * Проверим, что генератор не берётся за чужой формат.
     *
     * @see Code39SvgGenerator::supports()
     */
    #[Test]
    public function doesNotSupportPngFormat(): void
    {
        $generator = new Code39SvgGenerator();

        $this->assertTrue($generator->supports(BarcodeType::Code39, BarcodeOutputFormat::Svg));
        $this->assertFalse($generator->supports(BarcodeType::Code39, BarcodeOutputFormat::Png));
        $this->assertFalse($generator->supports(BarcodeType::Ean13, BarcodeOutputFormat::Svg));
    }
}
