<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Generator\Ean13PngGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function function_exists;
use function str_starts_with;

final class Ean13PngGeneratorTest extends TestCase
{
    #[Test]
    public function testGeneratesPngWithNormalizedCode(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required for PNG generation.');
        }

        $generator = new Ean13PngGenerator();

        $result = $generator->generate(
            data: '460123456789',
            type: BarcodeType::Ean13,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Png),
        );

        $this->assertSame('image/png', $result->mimeType);
        $this->assertTrue(str_starts_with($result->content, "\x89PNG"));

        TestArtifactStorage::save('ean13-png-generator', 'png', $result->content);
    }
}
