<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Generator\Code128SvgGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_contains;

#[CoversClass(Code128SvgGenerator::class)]
#[CoversMethod(Code128SvgGenerator::class, 'generate')]
#[CoversMethod(Code128SvgGenerator::class, 'supports')]
final class Code128SvgGeneratorTest extends TestCase
{
    /**
     * Проверим SVG с подписью и шириной: пять символов по 11 модулей, завершающий штрих и свободные зоны.
     *
     * @see Code128SvgGenerator::generate()
     */
    #[Test]
    public function generatesSvgWithHumanReadableText(): void
    {
        $result = new Code128SvgGenerator()->generate(
            data: 'AB',
            type: BarcodeType::Code128,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
        );

        $this->assertSame('image/svg+xml', $result->mimeType);
        $this->assertTrue(str_contains($result->content, '>AB</text>'));
        // Старт, два символа, контрольная сумма и стоп.
        $this->assertSame((11 * 5 + 2 + 20) * 2, $result->width);

        TestArtifactStorage::save('code128-svg-generator', 'svg', $result->content);
    }

    /**
     * Проверим, что цифры упаковываются по две в символ и код получается короче.
     *
     * @see Code128SvgGenerator::generate()
     */
    #[Test]
    public function packsDigitPairsIntoSingleSymbols(): void
    {
        $generator = new Code128SvgGenerator();
        $options   = new BarcodeOptions(format: BarcodeOutputFormat::Svg);

        $digits = $generator->generate(data: '12345678', type: BarcodeType::Code128, options: $options);
        $text   = $generator->generate(data: 'ABCDEFGH', type: BarcodeType::Code128, options: $options);

        // Восемь цифр — четыре символа, восемь букв — восемь символов.
        $this->assertSame((11 * 7 + 2 + 20) * 2, $digits->width);
        $this->assertSame((11 * 11 + 2 + 20) * 2, $text->width);
    }

    /**
     * Проверим, что подпись экранируется и не ломает SVG.
     *
     * @see Code128SvgGenerator::generate()
     */
    #[Test]
    public function escapesHumanReadableText(): void
    {
        $result = new Code128SvgGenerator()->generate(
            data: 'a&b',
            type: BarcodeType::Code128,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
        );

        $this->assertTrue(str_contains($result->content, '>a&amp;b</text>'));
    }

    /**
     * Проверим, что генератор не берётся за чужой тип и формат.
     *
     * @see Code128SvgGenerator::supports()
     */
    #[Test]
    public function doesNotSupportOtherTypesAndFormats(): void
    {
        $generator = new Code128SvgGenerator();

        $this->assertTrue($generator->supports(BarcodeType::Code128, BarcodeOutputFormat::Svg));
        $this->assertFalse($generator->supports(BarcodeType::Code128, BarcodeOutputFormat::Png));
        $this->assertFalse($generator->supports(BarcodeType::Code39, BarcodeOutputFormat::Svg));
    }
}
