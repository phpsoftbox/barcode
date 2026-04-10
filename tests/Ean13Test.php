<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\Exception\BarcodeException;
use PhpSoftBox\Barcode\Support\Ean13;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Ean13Test extends TestCase
{
    /**
     * Проверяет расчет контрольной цифры EAN-13.
     */
    #[Test]
    public function testCalculatesCheckDigit(): void
    {
        $this->assertSame(3, Ean13::calculateCheckDigit('460123456789'));
    }

    /**
     * Проверяет нормализацию 12-значного кода в валидный 13-значный EAN.
     */
    #[Test]
    public function testNormalizes12DigitsTo13(): void
    {
        $this->assertSame('4601234567893', Ean13::normalize('460123456789'));
    }

    /**
     * Проверяет, что при неверной контрольной цифре выбрасывается исключение.
     */
    #[Test]
    public function testThrowsOnInvalidChecksum(): void
    {
        $this->expectException(BarcodeException::class);
        Ean13::normalize('4601234567891');
    }
}
