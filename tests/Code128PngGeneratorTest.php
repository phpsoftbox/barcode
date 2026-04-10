<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Generator\Code128PngGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function function_exists;
use function getimagesizefromstring;

#[CoversClass(Code128PngGenerator::class)]
#[CoversMethod(Code128PngGenerator::class, 'generate')]
final class Code128PngGeneratorTest extends TestCase
{
    /**
     * Проверим, что PNG рисуется с размерами из расчёта модулей.
     *
     * @see Code128PngGenerator::generate()
     */
    #[Test]
    public function generatesPngWithCalculatedSize(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required for PNG generation.');
        }

        $result = new Code128PngGenerator()->generate(
            data: 'WMS-42',
            type: BarcodeType::Code128,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Png),
        );

        $this->assertSame('image/png', $result->mimeType);

        $size = getimagesizefromstring($result->content);
        $this->assertIsArray($size);
        $this->assertSame($result->width, $size[0]);
        $this->assertSame($result->height, $size[1]);

        TestArtifactStorage::save('code128-png-generator', 'png', $result->content);
    }
}
