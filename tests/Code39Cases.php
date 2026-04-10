<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\Code39Charset;

use function chr;

/**
 * Общий набор случаев Code 39 для PHPUnit и независимого декодера.
 */
final class Code39Cases
{
    /**
     * Имя случая => данные, набор символов, контрольная сумма, отношение ширин и ожидаемый текст после сканирования.
     *
     * @return array<string, array{
     *     data: string,
     *     charset: Code39Charset,
     *     checksum: bool,
     *     wideRatio: int,
     *     expected: string,
     * }>
     */
    public static function cases(): array
    {
        return [
            'digits' => [
                'data'      => '1234567890',
                'charset'   => Code39Charset::Standard,
                'checksum'  => false,
                'wideRatio' => 2,
                'expected'  => '1234567890',
            ],
            'standard-punctuation' => [
                'data'      => 'CELL-A1 $./+%',
                'charset'   => Code39Charset::Standard,
                'checksum'  => false,
                'wideRatio' => 2,
                'expected'  => 'CELL-A1 $./+%',
            ],
            'lowercase-normalized' => [
                'data'      => 'cell-a1',
                'charset'   => Code39Charset::Standard,
                'checksum'  => false,
                'wideRatio' => 2,
                // Стандартный набор знает только верхний регистр.
                'expected' => 'CELL-A1',
            ],
            'checksum' => [
                'data'      => 'CODE39',
                'charset'   => Code39Charset::Standard,
                'checksum'  => true,
                'wideRatio' => 2,
                // Сканер без проверки mod 43 отдаёт контрольный символ как обычный.
                'expected' => 'CODE39W',
            ],
            'wide-ratio-3' => [
                'data'      => 'WMS-42',
                'charset'   => Code39Charset::Standard,
                'checksum'  => false,
                'wideRatio' => 3,
                'expected'  => 'WMS-42',
            ],
            'long-value' => [
                'data'      => 'ABCDEFGHIJKLMNOPQRST0123456789',
                'charset'   => Code39Charset::Standard,
                'checksum'  => false,
                'wideRatio' => 2,
                'expected'  => 'ABCDEFGHIJKLMNOPQRST0123456789',
            ],
            'full-ascii-lowercase' => [
                'data'      => 'cell-a1',
                'charset'   => Code39Charset::FullAscii,
                'checksum'  => false,
                'wideRatio' => 2,
                'expected'  => 'cell-a1',
            ],
            'full-ascii-symbols' => [
                'data'      => 'Order#12,34',
                'charset'   => Code39Charset::FullAscii,
                'checksum'  => false,
                'wideRatio' => 2,
                'expected'  => 'Order#12,34',
            ],
            'full-ascii-control' => [
                'data'      => 'A' . chr(9) . 'B',
                'charset'   => Code39Charset::FullAscii,
                'checksum'  => false,
                'wideRatio' => 2,
                'expected'  => 'A' . chr(9) . 'B',
            ],
            'full-ascii-checksum' => [
                'data'      => 'box-7',
                'charset'   => Code39Charset::FullAscii,
                'checksum'  => true,
                'wideRatio' => 2,
                // Контрольный символ считается по значению после escape-замен: +B+O+X-7 → 'J'.
                'expected' => 'box-7J',
            ],
        ];
    }
}
