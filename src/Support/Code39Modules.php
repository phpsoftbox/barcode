<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Support;

use PhpSoftBox\Barcode\Exception\BarcodeException;

use function sprintf;
use function str_repeat;
use function str_split;

/**
 * Построение модулей Code 39.
 *
 * Каждый символ — девять элементов: пять штрихов и четыре пробела, которые чередуются, начиная со штриха.
 * В шаблонах `1` — узкий элемент, `2` — широкий. Символы разделяются узким пробелом.
 */
final class Code39Modules
{
    public const string DELIMITER = '*';

    /** @var array<string, string> */
    private const PATTERNS = [
        '0' => '111221211',
        '1' => '211211112',
        '2' => '112211112',
        '3' => '212211111',
        '4' => '111221112',
        '5' => '211221111',
        '6' => '112221111',
        '7' => '111211212',
        '8' => '211211211',
        '9' => '112211211',
        'A' => '211112112',
        'B' => '112112112',
        'C' => '212112111',
        'D' => '111122112',
        'E' => '211122111',
        'F' => '112122111',
        'G' => '111112212',
        'H' => '211112211',
        'I' => '112112211',
        'J' => '111122211',
        'K' => '211111122',
        'L' => '112111122',
        'M' => '212111121',
        'N' => '111121122',
        'O' => '211121121',
        'P' => '112121121',
        'Q' => '111111222',
        'R' => '211111221',
        'S' => '112111221',
        'T' => '111121221',
        'U' => '221111112',
        'V' => '122111112',
        'W' => '222111111',
        'X' => '121121112',
        'Y' => '221121111',
        'Z' => '122121111',
        '-' => '121111212',
        '.' => '221111211',
        ' ' => '122111211',
        '$' => '121212111',
        '/' => '121211121',
        '+' => '121112121',
        '%' => '111212121',
        '*' => '121121211',
    ];

    /**
     * Возвращает модули: `1` — тёмный, `0` — светлый; без свободных зон.
     *
     * Значение уже должно состоять из символов Code 39 (см. Code39::encode()).
     */
    public static function build(string $payload, int $wideRatio = 2): string
    {
        if ($wideRatio < 2 || $wideRatio > 3) {
            throw new BarcodeException('Code 39 wide-to-narrow ratio must be 2 or 3.');
        }

        $characters = [self::DELIMITER, ...str_split($payload), self::DELIMITER];

        $modules = '';
        foreach ($characters as $index => $character) {
            if ($index > 0) {
                // Узкий пробел между символами.
                $modules .= '0';
            }

            $modules .= self::characterModules($character, $wideRatio);
        }

        return $modules;
    }

    private static function characterModules(string $character, int $wideRatio): string
    {
        $pattern = self::PATTERNS[$character] ?? null;
        if ($pattern === null) {
            throw new BarcodeException(sprintf('Character "%s" is not supported by Code 39.', $character));
        }

        $modules = '';
        foreach (str_split($pattern) as $position => $width) {
            $isBar = $position % 2 === 0;
            $modules .= str_repeat($isBar ? '1' : '0', $width === '2' ? $wideRatio : 1);
        }

        return $modules;
    }
}
