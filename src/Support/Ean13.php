<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Support;

use PhpSoftBox\Barcode\Exception\BarcodeException;

use function ctype_digit;
use function sprintf;
use function strlen;
use function substr;

final class Ean13
{
    public static function normalize(string $value): string
    {
        if (!ctype_digit($value)) {
            throw new BarcodeException('EAN-13 accepts only digits.');
        }

        $length = strlen($value);
        if ($length === 12) {
            return $value . self::calculateCheckDigit($value);
        }

        if ($length !== 13) {
            throw new BarcodeException('EAN-13 value length must be 12 or 13 digits.');
        }

        $payload  = substr($value, 0, 12);
        $actual   = (int) substr($value, 12, 1);
        $expected = self::calculateCheckDigit($payload);
        if ($actual !== $expected) {
            throw new BarcodeException(sprintf(
                'Invalid EAN-13 check digit: expected %d, got %d.',
                $expected,
                $actual,
            ));
        }

        return $value;
    }

    public static function calculateCheckDigit(string $payload): int
    {
        if (!ctype_digit($payload) || strlen($payload) !== 12) {
            throw new BarcodeException('EAN-13 payload must contain exactly 12 digits.');
        }

        $sumOdd  = 0;
        $sumEven = 0;
        for ($index = 0; $index < 12; $index++) {
            $digit = (int) $payload[$index];
            if ($index % 2 === 0) {
                $sumOdd += $digit;
                continue;
            }

            $sumEven += $digit;
        }

        $sum = $sumOdd + ($sumEven * 3);

        return (10 - ($sum % 10)) % 10;
    }
}
