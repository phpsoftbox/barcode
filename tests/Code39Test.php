<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\Code39Charset;
use PhpSoftBox\Barcode\Exception\BarcodeException;
use PhpSoftBox\Barcode\Support\Code39;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chr;

#[CoversClass(Code39::class)]
#[CoversMethod(Code39::class, 'encode')]
#[CoversMethod(Code39::class, 'checksumCharacter')]
final class Code39Test extends TestCase
{
    /**
     * Проверим, что стандартный набор принимает свои символы без изменений.
     *
     * @see Code39::encode()
     */
    #[Test]
    public function encodeKeepsStandardCharacters(): void
    {
        self::assertSame('ABC-123 $./+%', Code39::encode('ABC-123 $./+%'));
    }

    /**
     * Проверим, что строчные буквы в стандартном наборе приводятся к верхнему регистру.
     *
     * @see Code39::encode()
     */
    #[Test]
    public function encodeUppercasesLowercaseInStandardCharset(): void
    {
        self::assertSame('CELL-A1', Code39::encode('cell-a1'));
    }

    /**
     * Проверим, что символ вне стандартного набора отклоняется с подсказкой про Full ASCII.
     *
     * @see Code39::encode()
     */
    #[Test]
    public function encodeRejectsUnsupportedStandardCharacter(): void
    {
        $this->expectException(BarcodeException::class);
        $this->expectExceptionMessage('full ASCII');

        Code39::encode('A#1');
    }

    /**
     * Проверим, что в Full ASCII строчные буквы кодируются парами +A..+Z.
     *
     * @see Code39::encode()
     */
    #[Test]
    public function encodeEscapesLowercaseInFullAscii(): void
    {
        self::assertSame('+A+B1', Code39::encode('ab1', Code39Charset::FullAscii));
    }

    /**
     * Проверим, что в Full ASCII управляющий символ кодируется парой с префиксом $.
     *
     * @see Code39::encode()
     */
    #[Test]
    public function encodeEscapesControlCharacterInFullAscii(): void
    {
        // Табуляция — девятый управляющий символ, то есть $I.
        self::assertSame('A$IB', Code39::encode('A' . chr(9) . 'B', Code39Charset::FullAscii));
    }

    /**
     * Проверим, что Full ASCII отклоняет байты вне ASCII.
     *
     * @see Code39::encode()
     */
    #[Test]
    public function encodeRejectsNonAsciiInFullAscii(): void
    {
        $this->expectException(BarcodeException::class);
        $this->expectExceptionMessage('ASCII');

        Code39::encode('Ж', Code39Charset::FullAscii);
    }

    /**
     * Проверим, что пустое значение отклоняется.
     *
     * @see Code39::encode()
     */
    #[Test]
    public function encodeRejectsEmptyValue(): void
    {
        $this->expectException(BarcodeException::class);

        Code39::encode('');
    }

    /**
     * Проверим контрольный символ mod 43 на известном примере.
     *
     * @see Code39::checksumCharacter()
     */
    #[Test]
    public function checksumCharacterUsesMod43(): void
    {
        // C(12) + O(24) + D(13) + E(14) + 3 + 9 = 75; 75 % 43 = 32 → 'W'.
        self::assertSame('W', Code39::checksumCharacter('CODE39'));
    }
}
