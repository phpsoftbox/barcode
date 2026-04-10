<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use function str_repeat;

/**
 * Коды маркировки для PHPUnit и независимого декодера.
 *
 * Разделитель элементов переменной длины — GS (0x1D), как в кодах «Честного знака».
 */
final class Gs1DataMatrixCases
{
    private const string GS = "\x1D";

    /**
     * Имя случая => данные и флаг GS1, с которым строится символ.
     *
     * @return array<string, array{data: string, gs1: bool, symbology: string}>
     */
    public static function cases(): array
    {
        $gtinAndSerial = '010463002345012321AbC12345defGh';
        $verification  = self::GS . '91EE06' . self::GS . '92';

        return [
            // Крипто-хвост 44 символа: обычный код маркировки табака и обуви.
            'kiz-crypto-44' => [
                'data'      => $gtinAndSerial . $verification . str_repeat('Ab1+/', 8) . 'Cd9=',
                'gs1'       => true,
                'symbology' => ']d2',
            ],
            // Крипто-хвост 88 символов: код маркировки с расширенной проверкой.
            'kiz-crypto-88' => [
                'data'      => $gtinAndSerial . $verification . str_repeat('Xy7-_', 17) . 'Zz2=',
                'gs1'       => true,
                'symbology' => ']d2',
            ],
            'kiz-without-gs1' => [
                'data'      => $gtinAndSerial . $verification . str_repeat('Ab1+/', 8) . 'Cd9=',
                'gs1'       => false,
                'symbology' => ']d1',
            ],
            // 124 цифры — полная ёмкость символа 32x32: с FNC1 нужен следующий размер.
            'capacity-boundary' => [
                'data'      => str_repeat('1234567890', 12) . '1234',
                'gs1'       => true,
                'symbology' => ']d2',
            ],
        ];
    }
}
