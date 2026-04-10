<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use InvalidArgumentException;
use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Exception\BarcodeException;
use PhpSoftBox\Barcode\Exception\UnsupportedBarcodeTypeException;
use PhpSoftBox\Barcode\Generator\QrGenerator;
use PhpSoftBox\Barcode\QrErrorCorrectionLevel;
use PhpSoftBox\Barcode\QrLogoOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function base64_decode;
use function file_put_contents;
use function function_exists;
use function imagecolorallocate;
use function imagecreatetruecolor;
use function imagefilledrectangle;
use function imagepng;
use function ob_get_clean;
use function ob_start;
use function str_contains;
use function str_repeat;
use function str_starts_with;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class QrGeneratorTest extends TestCase
{
    private const string FIXTURE_LOGO_SVG = __DIR__ . '/fixtures/logo.svg';

    #[Test]
    public function testGeneratesQrSvg(): void
    {
        $generator = new QrGenerator();

        $result = $generator->generate(
            data: 'P1-R1-C1',
            type: BarcodeType::Qr,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg, height: 256),
        );

        $this->assertSame('image/svg+xml', $result->mimeType);
        $this->assertSame(256, $result->width);
        $this->assertSame(256, $result->height);
        $this->assertTrue(str_contains($result->content, '<svg'));

        TestArtifactStorage::save('qr-generator-svg', 'svg', $result->content);
    }

    #[Test]
    public function testGeneratesQrPng(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required for PNG generation.');
        }

        $generator = new QrGenerator();

        $result = $generator->generate(
            data: 'P1-R1-C1',
            type: BarcodeType::Qr,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Png, height: 256),
        );

        $this->assertSame('image/png', $result->mimeType);
        $this->assertSame(256, $result->width);
        $this->assertSame(256, $result->height);
        $this->assertTrue(str_starts_with($result->content, "\x89PNG"));

        TestArtifactStorage::save('qr-generator-png', 'png', $result->content);
    }

    #[Test]
    public function testGeneratesQrSvgWithLogo(): void
    {
        $generator = new QrGenerator();
        $logoPath  = $this->createTempLogoPng();

        try {
            $result = $generator->generate(
                data: 'P1-R1-C1',
                type: BarcodeType::Qr,
                options: new BarcodeOptions(
                    format: BarcodeOutputFormat::Svg,
                    height: 256,
                    qrLogo: new QrLogoOptions(
                        path: $logoPath,
                        sizeRatio: 0.18,
                        padding: 6,
                        backgroundColor: '#F3F4F6',
                        borderColor: '#111827',
                        borderWidth: 2,
                        cornerRadius: 10,
                    ),
                ),
            );
        } finally {
            unlink($logoPath);
        }

        $this->assertSame('image/svg+xml', $result->mimeType);
        $this->assertTrue(str_contains($result->content, '<image'));
        $this->assertTrue(str_contains($result->content, 'data:image/png;base64,'));
        $this->assertTrue(str_contains($result->content, 'fill="#F3F4F6"'));
        $this->assertTrue(str_contains($result->content, 'stroke="#111827"'));
        $this->assertTrue(str_contains($result->content, 'stroke-width="2"'));

        TestArtifactStorage::save('qr-generator-logo-svg', 'svg', $result->content);
    }

    #[Test]
    public function testGeneratesQrSvgWithExternalSvgLogo(): void
    {
        $generator = new QrGenerator();

        $result = $generator->generate(
            data: 'P1-R1-C1',
            type: BarcodeType::Qr,
            options: new BarcodeOptions(
                format: BarcodeOutputFormat::Svg,
                height: 256,
                qrLogo: new QrLogoOptions(path: self::FIXTURE_LOGO_SVG, sizeRatio: 0.18, padding: 6),
            ),
        );

        $this->assertSame('image/svg+xml', $result->mimeType);
        $this->assertTrue(str_contains($result->content, '<image'));
        $this->assertTrue(str_contains($result->content, 'data:image/svg+xml;base64,'));

        TestArtifactStorage::save('qr-generator-logo-external-svg', 'svg', $result->content);
    }

    #[Test]
    public function testGeneratesQrPngWithLogo(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required for PNG generation.');
        }

        $generator = new QrGenerator();
        $logoPath  = $this->createTempLogoPng();

        try {
            $result = $generator->generate(
                data: 'P1-R1-C1',
                type: BarcodeType::Qr,
                options: new BarcodeOptions(
                    format: BarcodeOutputFormat::Png,
                    height: 256,
                    qrErrorCorrection: QrErrorCorrectionLevel::H,
                    qrLogo: new QrLogoOptions(path: $logoPath, sizeRatio: 0.18, padding: 6),
                ),
            );
        } finally {
            unlink($logoPath);
        }

        $this->assertSame('image/png', $result->mimeType);
        $this->assertTrue(str_starts_with($result->content, "\x89PNG"));

        TestArtifactStorage::save('qr-generator-logo-png', 'png', $result->content);
    }

    #[Test]
    public function testThrowsForUnsupportedType(): void
    {
        $generator = new QrGenerator();

        $this->expectException(UnsupportedBarcodeTypeException::class);

        $generator->generate(
            data: '460123456789',
            type: BarcodeType::Ean13,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
        );
    }

    #[Test]
    public function testThrowsForTooLargePayload(): void
    {
        $generator = new QrGenerator();

        $this->expectException(BarcodeException::class);

        $generator->generate(
            data: str_repeat('A', 300),
            type: BarcodeType::Qr,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
        );
    }

    #[Test]
    public function testThrowsForMissingLogoFile(): void
    {
        $generator = new QrGenerator();

        $this->expectException(BarcodeException::class);

        $generator->generate(
            data: 'P1-R1-C1',
            type: BarcodeType::Qr,
            options: new BarcodeOptions(
                format: BarcodeOutputFormat::Svg,
                qrLogo: new QrLogoOptions('/tmp/does-not-exist-logo.png'),
            ),
        );
    }

    #[Test]
    public function testThrowsForInvalidLogoColor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new QrLogoOptions(path: '/tmp/logo.png', backgroundColor: 'white');
    }

    private function createTempLogoPng(): string
    {
        $path = sys_get_temp_dir() . '/barcode-logo-' . uniqid('', true) . '.png';

        if (function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor(48, 48);
            $white = imagecolorallocate($image, 255, 255, 255);
            $black = imagecolorallocate($image, 0, 0, 0);
            imagefilledrectangle($image, 0, 0, 47, 47, $white);
            imagefilledrectangle($image, 8, 8, 39, 39, $black);

            ob_start();
            imagepng($image);
            $raw = ob_get_clean();
            $this->assertIsString($raw);
            file_put_contents($path, $raw);

            return $path;
        }

        $raw = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAQAAABFaP0WAAAADUlEQVR42mNgYGD4DwABBAEAm6P6TwAAAABJRU5ErkJggg==',
            true,
        );

        $this->assertIsString($raw);
        file_put_contents($path, $raw);

        return $path;
    }
}
