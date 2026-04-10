<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\BarcodeGeneratorChain;
use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeResult;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Generator\Code128SvgGenerator;
use PhpSoftBox\Barcode\Generator\Code39SvgGenerator;
use PhpSoftBox\Barcode\Generator\DataMatrixGenerator;
use PhpSoftBox\Barcode\Generator\Ean13PngGenerator;
use PhpSoftBox\Barcode\Generator\Ean13SvgGenerator;
use PhpSoftBox\Barcode\Generator\QrGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function function_exists;
use function str_contains;
use function str_starts_with;

final class BarcodeGeneratorChainTest extends TestCase
{
    /**
     * Проверим, что цепочка выбирает генератор Code 128 по типу и формату.
     */
    #[Test]
    public function testDelegatesToCode128SvgGenerator(): void
    {
        $chain = new BarcodeGeneratorChain([
            new Code39SvgGenerator(),
            new Code128SvgGenerator(),
        ]);

        $result = $chain->generate(
            data: 'WMS-42',
            type: BarcodeType::Code128,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
        );

        $this->assertSame('image/svg+xml', $result->mimeType);
        $this->assertTrue(str_contains($result->content, '>WMS-42</text>'));

        TestArtifactStorage::save('barcode-generator-chain-code128', 'svg', $result->content);
    }

    /**
     * Проверим, что цепочка выбирает генератор Code 39 по типу и формату.
     */
    #[Test]
    public function testDelegatesToCode39SvgGenerator(): void
    {
        $chain = new BarcodeGeneratorChain([
            new Ean13SvgGenerator(),
            new Code39SvgGenerator(),
        ]);

        $result = $chain->generate(
            data: 'CELL-A1',
            type: BarcodeType::Code39,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
        );

        $this->assertSame('image/svg+xml', $result->mimeType);
        $this->assertTrue(str_contains($result->content, '>CELL-A1</text>'));

        TestArtifactStorage::save('barcode-generator-chain-code39', 'svg', $result->content);
    }

    /**
     * Проверяет, что цепочка делегирует генерацию подходящему генератору.
     */
    #[Test]
    public function testDelegatesToMatchingGenerator(): void
    {
        $chain = new BarcodeGeneratorChain([
            new Ean13SvgGenerator(),
            new Ean13PngGenerator(),
        ]);

        $result = $chain->generate(
            data: '460123456789',
            type: BarcodeType::Ean13,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
        );

        $this->assertInstanceOf(BarcodeResult::class, $result);
        $this->assertSame('image/svg+xml', $result->mimeType);

        TestArtifactStorage::save('barcode-generator-chain-ean13', 'svg', $result->content);
    }

    #[Test]
    public function testDelegatesToEan13PngGenerator(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required for PNG generation.');
        }

        $chain = new BarcodeGeneratorChain([
            new Ean13SvgGenerator(),
            new Ean13PngGenerator(),
        ]);

        $result = $chain->generate(
            data: '460123456789',
            type: BarcodeType::Ean13,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Png),
        );

        $this->assertInstanceOf(BarcodeResult::class, $result);
        $this->assertSame('image/png', $result->mimeType);
        $this->assertTrue(str_starts_with($result->content, "\x89PNG"));

        TestArtifactStorage::save('barcode-generator-chain-ean13-png', 'png', $result->content);
    }

    #[Test]
    public function testDelegatesQrToQrGenerator(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required for PNG generation.');
        }

        $chain = new BarcodeGeneratorChain([
            new Ean13SvgGenerator(),
            new QrGenerator(),
        ]);

        $result = $chain->generate(
            data: 'P1-R1-C1',
            type: BarcodeType::Qr,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Png, height: 256),
        );

        $this->assertInstanceOf(BarcodeResult::class, $result);
        $this->assertSame('image/png', $result->mimeType);
        $this->assertTrue(str_starts_with($result->content, "\x89PNG"));

        TestArtifactStorage::save('barcode-generator-chain-qr', 'png', $result->content);
    }

    #[Test]
    public function testDelegatesDataMatrixToGenerator(): void
    {
        $chain = new BarcodeGeneratorChain([
            new Ean13SvgGenerator(),
            new QrGenerator(),
            new DataMatrixGenerator(),
        ]);

        $result = $chain->generate(
            data: 'DM-12345',
            type: BarcodeType::DataMatrix,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg, height: 256),
        );

        $this->assertInstanceOf(BarcodeResult::class, $result);
        $this->assertSame('image/svg+xml', $result->mimeType);
        $this->assertTrue(str_starts_with($result->content, '<svg'));

        TestArtifactStorage::save('barcode-generator-chain-datamatrix', 'svg', $result->content);
    }
}
