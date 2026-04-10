<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Support;

use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\Code39Charset;
use PhpSoftBox\Barcode\Exception\BarcodeException;

use function array_search;
use function chr;
use function ord;
use function sprintf;
use function str_contains;
use function str_split;
use function strlen;
use function strtoupper;

/**
 * Подготовка данных Code 39: набор символов, Full ASCII и контрольная сумма mod 43.
 */
final class Code39
{
    /**
     * Символы Code 39 в порядке их значений для контрольной суммы mod 43.
     */
    public const string CHARSET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-. $/+%';

    /**
     * Приводит значение к набору символов Code 39.
     *
     * В режиме Full ASCII символы вне базового набора заменяются escape-парами,
     * поэтому результат всегда состоит только из символов CHARSET.
     */
    public static function encode(string $value, Code39Charset $charset = Code39Charset::Standard): string
    {
        if ($value === '') {
            throw new BarcodeException('Code 39 value must not be empty.');
        }

        return $charset === Code39Charset::FullAscii
            ? self::encodeFullAscii($value)
            : self::encodeStandard($value);
    }

    /**
     * Готовое к отрисовке значение: набор символов и, если включена, контрольная сумма.
     */
    public static function payload(string $value, BarcodeOptions $options): string
    {
        $payload = self::encode($value, $options->code39Charset);

        return $options->code39Checksum ? $payload . self::checksumCharacter($payload) : $payload;
    }

    /**
     * Контрольный символ mod 43 для уже подготовленного значения.
     */
    public static function checksumCharacter(string $payload): string
    {
        $sum = 0;
        foreach (str_split($payload) as $character) {
            $sum += self::valueOf($character);
        }

        return self::CHARSET[$sum % 43];
    }

    /**
     * Значение символа для контрольной суммы.
     */
    public static function valueOf(string $character): int
    {
        $value = array_search($character, str_split(self::CHARSET), true);
        if ($value === false) {
            throw new BarcodeException(sprintf('Character "%s" is not supported by Code 39.', $character));
        }

        return $value;
    }

    private static function encodeStandard(string $value): string
    {
        // Code 39 знает только верхний регистр, поэтому строчные буквы приводим к нему.
        $upper = strtoupper($value);

        foreach (str_split($upper) as $character) {
            if (!str_contains(self::CHARSET, $character)) {
                throw new BarcodeException(sprintf(
                    'Character "%s" is not supported by standard Code 39. Use the full ASCII charset.',
                    $character,
                ));
            }
        }

        return $upper;
    }

    private static function encodeFullAscii(string $value): string
    {
        $encoded = '';
        $length  = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $code = ord($value[$index]);
            if ($code > 127) {
                throw new BarcodeException('Full ASCII Code 39 accepts only ASCII characters.');
            }

            $encoded .= self::fullAsciiPair($code);
        }

        return $encoded;
    }

    /**
     * Escape-пара Full ASCII для кода символа.
     */
    private static function fullAsciiPair(int $code): string
    {
        return match (true) {
            $code === 0                => '%U',
            $code >= 1 && $code <= 26  => '$' . chr(64 + $code),
            $code >= 27 && $code <= 31 => '%' . chr(65 + $code - 27),
            $code === 32               => ' ',
            $code >= 33 && $code <= 44 => '/' . chr(65 + $code - 33),
            $code === 45, $code === 46 => chr($code),
            $code === 47                 => '/O',
            $code >= 48 && $code <= 57   => chr($code),
            $code === 58                 => '/Z',
            $code >= 59 && $code <= 63   => '%' . chr(70 + $code - 59),
            $code === 64                 => '%V',
            $code >= 65 && $code <= 90   => chr($code),
            $code >= 91 && $code <= 95   => '%' . chr(75 + $code - 91),
            $code === 96                 => '%W',
            $code >= 97 && $code <= 122  => '+' . chr($code - 32),
            $code >= 123 && $code <= 126 => '%' . chr(80 + $code - 123),
            default                      => '%T',
        };
    }
}
