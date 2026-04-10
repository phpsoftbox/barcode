<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Generator\Ean13SvgGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_contains;

final class Ean13SvgGeneratorTest extends TestCase
{
    /**
     * Проверяет генерацию SVG-штрихкода EAN-13 с нормализованным значением.
     */
    #[Test]
    public function testGeneratesSvgWithNormalizedCode(): void
    {
        $generator = new Ean13SvgGenerator();

        $result = $generator->generate(
            data: '460123456789',
            type: BarcodeType::Ean13,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
        );

        $this->assertSame('image/svg+xml', $result->mimeType);
        $this->assertTrue(str_contains($result->content, '<svg'));
        $this->assertTrue(str_contains($result->content, '4601234567893'));

        TestArtifactStorage::save('ean13-svg-generator', 'svg', $result->content);
    }
}
