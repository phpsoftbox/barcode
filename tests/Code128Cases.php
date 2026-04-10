<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use function chr;
use function str_repeat;

/**
 * Общий набор случаев Code 128 для PHPUnit и независимого декодера.
 */
final class Code128Cases
{
    /**
     * Имя случая => значение, которое должно вернуться со сканера.
     *
     * @return array<string, string>
     */
    public static function values(): array
    {
        return [
            'text'              => 'WMS-42',
            'digits-even'       => '12345678',
            'digits-odd'        => '12345',
            'mixed-text-digits' => 'BOX-000123456789',
            'digits-then-text'  => '4601234567890KG',
            'lowercase'         => 'cell-a1',
            'control-character' => 'A' . chr(9) . 'B',
            'punctuation'       => 'a&b/c+d%e',
            'single-character'  => 'X',
            'two-digits'        => '42',
            'long-value'        => str_repeat('AB12', 6),
        ];
    }
}
