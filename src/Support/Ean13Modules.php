<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Support;

use function substr;

final class Ean13Modules
{
    /** @var array<string, string> */
    private const L_PATTERNS = [
        '0' => '0001101',
        '1' => '0011001',
        '2' => '0010011',
        '3' => '0111101',
        '4' => '0100011',
        '5' => '0110001',
        '6' => '0101111',
        '7' => '0111011',
        '8' => '0110111',
        '9' => '0001011',
    ];

    /** @var array<string, string> */
    private const G_PATTERNS = [
        '0' => '0100111',
        '1' => '0110011',
        '2' => '0011011',
        '3' => '0100001',
        '4' => '0011101',
        '5' => '0111001',
        '6' => '0000101',
        '7' => '0010001',
        '8' => '0001001',
        '9' => '0010111',
    ];

    /** @var array<string, string> */
    private const R_PATTERNS = [
        '0' => '1110010',
        '1' => '1100110',
        '2' => '1101100',
        '3' => '1000010',
        '4' => '1011100',
        '5' => '1001110',
        '6' => '1010000',
        '7' => '1000100',
        '8' => '1001000',
        '9' => '1110100',
    ];

    /** @var array<string, string> */
    private const PARITY = [
        '0' => 'LLLLLL',
        '1' => 'LLGLGG',
        '2' => 'LLGGLG',
        '3' => 'LLGGGL',
        '4' => 'LGLLGG',
        '5' => 'LGGLLG',
        '6' => 'LGGGLL',
        '7' => 'LGLGLG',
        '8' => 'LGLGGL',
        '9' => 'LGGLGL',
    ];

    public static function build(string $ean13): string
    {
        $firstDigit = $ean13[0];
        $left       = substr($ean13, 1, 6);
        $right      = substr($ean13, 7, 6);

        $leftEncoded = '';
        $parity      = self::PARITY[$firstDigit];
        for ($index = 0; $index < 6; $index++) {
            $digit = $left[$index];
            $leftEncoded .= $parity[$index] === 'L'
                ? self::L_PATTERNS[$digit]
                : self::G_PATTERNS[$digit];
        }

        $rightEncoded = '';
        for ($index = 0; $index < 6; $index++) {
            $rightEncoded .= self::R_PATTERNS[$right[$index]];
        }

        return '101' . $leftEncoded . '01010' . $rightEncoded . '101';
    }
}
