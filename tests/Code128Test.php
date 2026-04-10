<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\Exception\BarcodeException;
use PhpSoftBox\Barcode\Support\Code128;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chr;

#[CoversClass(Code128::class)]
#[CoversMethod(Code128::class, 'encode')]
#[CoversMethod(Code128::class, 'checksum')]
final class Code128Test extends TestCase
{
    /**
     * Проверим набор B для текста и контрольную сумму: 104 + 1*33 + 2*34 = 205, 205 % 103 = 102.
     *
     * @see Code128::encode()
     */
    #[Test]
    public function encodeUsesCodeSetBForText(): void
    {
        self::assertSame(
            [Code128::START_B, 33, 34, 102, Code128::STOP],
            Code128::encode('AB'),
        );
    }

    /**
     * Проверим, что цифровое значение чётной длины целиком кодируется набором C парами цифр.
     *
     * @see Code128::encode()
     */
    #[Test]
    public function encodeUsesCodeSetCForEvenDigits(): void
    {
        // 105 + 1*12 + 2*34 = 185, 185 % 103 = 82.
        self::assertSame(
            [Code128::START_C, 12, 34, 82, Code128::STOP],
            Code128::encode('1234'),
        );
    }

    /**
     * Проверим, что нечётная последовательность цифр выравнивается одной цифрой в наборе B.
     *
     * @see Code128::encode()
     */
    #[Test]
    public function encodeAlignsOddDigitRun(): void
    {
        // 104 + 1*17 + 2*99 + 3*23 + 4*45 = 568, 568 % 103 = 53.
        self::assertSame(
            [Code128::START_B, 17, Code128::CODE_C, 23, 45, 53, Code128::STOP],
            Code128::encode('12345'),
        );
    }

    /**
     * Проверим переключение в набор A для управляющего символа и обратно в B для строчной буквы.
     *
     * @see Code128::encode()
     */
    #[Test]
    public function encodeSwitchesCodeSetForControlCharacter(): void
    {
        // 104 + 1*65 + 2*101 + 3*73 + 4*100 + 5*66 = 1320, 1320 % 103 = 84.
        self::assertSame(
            [Code128::START_B, 65, Code128::CODE_A, 73, Code128::CODE_B, 66, 84, Code128::STOP],
            Code128::encode('a' . chr(9) . 'b'),
        );
    }

    /**
     * Проверим, что байты вне ASCII отклоняются.
     *
     * @see Code128::encode()
     */
    #[Test]
    public function encodeRejectsNonAscii(): void
    {
        $this->expectException(BarcodeException::class);
        $this->expectExceptionMessage('ASCII');

        Code128::encode('Ж');
    }

    /**
     * Проверим, что пустое значение отклоняется.
     *
     * @see Code128::encode()
     */
    #[Test]
    public function encodeRejectsEmptyValue(): void
    {
        $this->expectException(BarcodeException::class);

        Code128::encode('');
    }
}
