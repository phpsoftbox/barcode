<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Exception\BarcodeException;
use PhpSoftBox\Barcode\Exception\UnsupportedBarcodeTypeException;
use PhpSoftBox\Barcode\Generator\DataMatrixGenerator;
use PhpSoftBox\Barcode\Support\DataMatrixEncoder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chr;
use function count;
use function function_exists;
use function str_contains;
use function str_repeat;
use function str_starts_with;

final class DataMatrixGeneratorTest extends TestCase
{
    #[Test]
    public function testGeneratesDataMatrixSvg(): void
    {
        $generator = new DataMatrixGenerator();

        $result = $generator->generate(
            data: 'DM-460123456789',
            type: BarcodeType::DataMatrix,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg, height: 256),
        );

        $this->assertSame('image/svg+xml', $result->mimeType);
        $this->assertSame(256, $result->width);
        $this->assertSame(256, $result->height);
        $this->assertTrue(str_contains($result->content, '<svg'));

        TestArtifactStorage::save('datamatrix-generator-svg', 'svg', $result->content);
    }

    #[Test]
    public function testGeneratesDataMatrixPng(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required for PNG generation.');
        }

        $generator = new DataMatrixGenerator();

        $result = $generator->generate(
            data: 'DM-460123456789',
            type: BarcodeType::DataMatrix,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Png, height: 256),
        );

        $this->assertSame('image/png', $result->mimeType);
        $this->assertSame(256, $result->width);
        $this->assertSame(256, $result->height);
        $this->assertTrue(str_starts_with($result->content, "\x89PNG"));

        TestArtifactStorage::save('datamatrix-generator-png', 'png', $result->content);
    }

    #[Test]
    public function testGeneratesDataMatrixForLongIdentityMarkPayload(): void
    {
        $generator      = new DataMatrixGenerator();
        $groupSeparator = chr(29);
        $payload        = '01046303975346022158/bpYpr&oYyn'
            . $groupSeparator . '9180C3'
            . $groupSeparator . '929GkX5x09Nm07i5xlN/d81bErqewdpKU8L9xAEj0sFuF+1heXxdYd+1/3tunixQQpNbinE60k41ZnnYyhCmVByw==';

        $result = $generator->generate(
            data: $payload,
            type: BarcodeType::DataMatrix,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg, height: 256),
        );

        $this->assertSame('image/svg+xml', $result->mimeType);
        $this->assertTrue(str_contains($result->content, '<svg'));

        TestArtifactStorage::save('datamatrix-generator-identity-mark-svg', 'svg', $result->content);
    }

    #[Test]
    public function testEncoderBuildsMultiRegionMatrixForLongPayload(): void
    {
        $encoder = new DataMatrixEncoder();

        $matrix = $encoder->encode(str_repeat('A', 120));

        $this->assertGreaterThan(26, count($matrix));
        $this->assertSame(count($matrix), count($matrix[0]));
    }

    #[Test]
    public function testThrowsForUnsupportedType(): void
    {
        $generator = new DataMatrixGenerator();

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
        $generator = new DataMatrixGenerator();

        $this->expectException(BarcodeException::class);

        $generator->generate(
            data: str_repeat('A', 1600),
            type: BarcodeType::DataMatrix,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
        );
    }
}
