<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Support;

use PhpSoftBox\Barcode\Exception\BarcodeException;

use function ctype_digit;
use function ord;
use function sprintf;
use function strlen;
use function substr;

/**
 * Кодирование данных Code 128 в значения символов.
 *
 * Набор выбирается автоматически: C для последовательностей цифр, A для управляющих символов, иначе B.
 * Контрольная сумма обязательна и считается по модулю 103.
 */
final class Code128
{
    public const int START_A = 103;
    public const int START_B = 104;
    public const int START_C = 105;
    public const int CODE_A  = 101;
    public const int CODE_B  = 100;
    public const int CODE_C  = 99;
    public const int STOP    = 106;

    /**
     * Минимальная длина последовательности цифр, ради которой стоит переключаться в набор C.
     */
    private const int CODE_C_RUN = 4;

    /**
     * Значения символов: стартовый код, данные, контрольная сумма и стоп-код.
     *
     * @return list<int>
     */
    public static function encode(string $value): array
    {
        if ($value === '') {
            throw new BarcodeException('Code 128 value must not be empty.');
        }

        $length = strlen($value);
        for ($index = 0; $index < $length; $index++) {
            if (ord($value[$index]) > 127) {
                throw new BarcodeException('Code 128 accepts only ASCII characters.');
            }
        }

        $codes    = [];
        $position = 0;
        $codeSet  = self::startCode($value, $codes);

        while ($position < $length) {
            if ($codeSet === self::START_C) {
                if (self::digitsAt($value, $position) >= 2) {
                    $codes[] = (int) substr($value, $position, 2);
                    $position += 2;

                    continue;
                }

                // Цифры закончились: возвращаемся в набор для обычных символов.
                $codeSet = self::characterCodeSet($value[$position]) === self::START_A ? self::START_A : self::START_B;
                $codes[] = $codeSet === self::START_A ? self::CODE_A : self::CODE_B;

                continue;
            }

            $digits = self::digitsAt($value, $position);
            if ($digits >= self::CODE_C_RUN) {
                // Нечётную последовательность выравниваем: одна цифра уходит в текущий набор.
                if ($digits % 2 === 1) {
                    $codes[] = self::characterValue($value[$position], $codeSet);
                    $position++;
                }

                $codeSet = self::START_C;
                $codes[] = self::CODE_C;

                continue;
            }

            $required = self::characterCodeSet($value[$position]);
            if ($required !== $codeSet && $required !== self::START_B) {
                $codeSet = self::START_A;
                $codes[] = self::CODE_A;

                continue;
            }

            if ($codeSet === self::START_A && !self::isInCodeSetA($value[$position])) {
                $codeSet = self::START_B;
                $codes[] = self::CODE_B;

                continue;
            }

            $codes[] = self::characterValue($value[$position], $codeSet);
            $position++;
        }

        $codes[] = self::checksum($codes);
        $codes[] = self::STOP;

        return $codes;
    }

    /**
     * Контрольная сумма по модулю 103: стартовый код плюс значения данных с их позициями.
     *
     * @param list<int> $codes стартовый код и данные
     */
    public static function checksum(array $codes): int
    {
        $sum   = $codes[0] ?? 0;
        $count = 0;
        foreach ($codes as $index => $code) {
            if ($index === 0) {
                continue;
            }

            $count++;
            $sum += $code * $count;
        }

        return $sum % 103;
    }

    /**
     * Выбирает стартовый код и записывает его в набор значений.
     *
     * @param list<int> $codes
     */
    private static function startCode(string $value, array &$codes): int
    {
        $digits = self::digitsAt($value, 0);

        // Полностью цифровое значение чётной длины целиком помещается в набор C.
        $start = match (true) {
            $digits === strlen($value) && $digits % 2 === 0 && $digits >= 2 => self::START_C,
            $digits >= self::CODE_C_RUN && $digits % 2 === 0                => self::START_C,
            self::characterCodeSet($value[0]) === self::START_A             => self::START_A,
            default                                                         => self::START_B,
        };

        $codes[] = $start;

        return $start;
    }

    /**
     * Сколько подряд идущих цифр начинается с позиции.
     */
    private static function digitsAt(string $value, int $position): int
    {
        $digits = 0;
        $length = strlen($value);
        while ($position + $digits < $length && ctype_digit($value[$position + $digits])) {
            $digits++;
        }

        return $digits;
    }

    /**
     * Набор, необходимый символу: управляющие символы есть только в A, строчные буквы — только в B.
     */
    private static function characterCodeSet(string $character): int
    {
        return ord($character) < 32 ? self::START_A : self::START_B;
    }

    private static function isInCodeSetA(string $character): bool
    {
        $code = ord($character);

        return $code < 96;
    }

    private static function characterValue(string $character, int $codeSet): int
    {
        $code = ord($character);

        if ($codeSet === self::START_A) {
            return $code < 32 ? $code + 64 : $code - 32;
        }

        if ($code < 32) {
            throw new BarcodeException(sprintf('Character 0x%02X requires Code 128 set A.', $code));
        }

        return $code - 32;
    }
}
