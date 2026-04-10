<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use function str_repeat;

final class DataMatrixCases
{
    public const string REGRESSION_PAYLOAD = "0104680856767386215Za_eplTkW,yS\x1D91EE12\x1D92vv2Z82w5GMas7CtsqTxqKL5z8k/eFUNS0YWKCCyo8N8=";

    /**
     * Независимая таблица квадратных ECC200: размер, ёмкость данных, размер региона с рамкой.
     *
     * @return iterable<array{int, int, int}>
     */
    public static function symbols(): iterable
    {
        yield [10, 3, 10];
        yield [12, 5, 12];
        yield [14, 8, 14];
        yield [16, 12, 16];
        yield [18, 18, 18];
        yield [20, 22, 20];
        yield [22, 30, 22];
        yield [24, 36, 24];
        yield [26, 44, 26];
        yield [32, 62, 16];
        yield [36, 86, 18];
        yield [40, 114, 20];
        yield [44, 144, 22];
        yield [48, 174, 24];
        yield [52, 204, 26];
        yield [64, 280, 16];
        yield [72, 368, 18];
        yield [80, 456, 20];
        yield [88, 576, 22];
        yield [96, 696, 24];
        yield [104, 816, 26];
        yield [120, 1050, 20];
        yield [132, 1304, 22];
        yield [144, 1558, 24];
    }

    /**
     * Проверочные данные с известной ёмкостью после ASCII-кодирования, а не по длине строки.
     *
     * @return iterable<string, array{string, int}>
     */
    public static function payloads(): iterable
    {
        yield 'regression-36' => [self::REGRESSION_PAYLOAD, 36];

        foreach (self::symbols() as [$size, $capacity]) {
            yield 'full-' . $size => [str_repeat('A', $capacity), $size];
        }

        foreach ([[62, 32, 36], [86, 36, 40], [114, 40, 44], [144, 44, 48]] as [$capacity, $size, $next]) {
            foreach ([-1, 0, 1] as $offset) {
                $length   = $capacity + $offset;
                $expected = $offset === 1 ? $next : $size;

                yield 'ascii-' . $length => [str_repeat('a', $length), $expected];
                yield 'digits-' . $length => [str_repeat('12', $length), $expected];
                // 01 занимает одно кодовое слово, остальные девять символов — по одному.
                $prefix = "01,a\"'\x1D+/=Z";
                yield 'mixed-' . $length => [$prefix . str_repeat('x', $length - 10), $expected];
            }
        }
    }
}
