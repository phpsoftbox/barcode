<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\QrErrorCorrectionLevel;
use PhpSoftBox\Barcode\Support\QrCodeEncoder;
use PHPUnit\Framework\Attributes\{CoversClass, CoversMethod, DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function abs;
use function max;
use function str_repeat;

#[CoversClass(QrCodeEncoder::class)]
#[CoversMethod(QrCodeEncoder::class, 'encode')]
final class QrAlignmentPatternsTest extends TestCase
{
    /**
     * Проверяет сохранение alignment-паттернов на timing-линиях QR версий 7–10 с коррекцией M и H.
     *
     * @see QrCodeEncoder::encode()
     */
    #[Test]
    #[DataProvider('alignmentPatternCases')]
    public function preservesAlignmentPatternsOnTimingLines(
        int $length,
        int $size,
        int $center,
        QrErrorCorrectionLevel $level,
    ): void {
        $matrix = new QrCodeEncoder()->encode(str_repeat('x', $length), $level);

        $this->assertCount($size, $matrix);
        foreach ([[6, $center], [$center, 6]] as [$x, $y]) {
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $dark = max(abs($dx), abs($dy)) !== 1;
                    $this->assertSame($dark, $matrix[$y + $dy][$x + $dx], 'Missing alignment module for QR size ' . $size);
                }
            }
        }
    }

    /**
     * Длины payload подобраны так, чтобы энкодер выбрал указанную версию при заданном уровне коррекции.
     *
     * @return iterable<string, array{int, int, int, QrErrorCorrectionLevel}>
     */
    public static function alignmentPatternCases(): iterable
    {
        yield 'v7 M' => [110, 45, 22, QrErrorCorrectionLevel::M];
        yield 'v8 M' => [130, 49, 24, QrErrorCorrectionLevel::M];
        yield 'v9 M' => [164, 53, 26, QrErrorCorrectionLevel::M];
        yield 'v10 M' => [190, 57, 28, QrErrorCorrectionLevel::M];
        yield 'v7 H' => [59, 45, 22, QrErrorCorrectionLevel::H];
        yield 'v8 H' => [65, 49, 24, QrErrorCorrectionLevel::H];
        yield 'v9 H' => [85, 53, 26, QrErrorCorrectionLevel::H];
        yield 'v10 H' => [99, 57, 28, QrErrorCorrectionLevel::H];
    }
}
