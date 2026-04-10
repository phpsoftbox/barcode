<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\Exception\BarcodeException;
use PhpSoftBox\Barcode\Support\Code128;
use PhpSoftBox\Barcode\Support\Code128Modules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_sum;
use function array_unique;
use function count;
use function str_split;
use function strlen;

#[CoversClass(Code128Modules::class)]
#[CoversMethod(Code128Modules::class, 'build')]
final class Code128ModulesTest extends TestCase
{
    /**
     * Проверим, что в таблице 107 символов и все шаблоны различны: опечатка дала бы повтор.
     *
     * @see Code128Modules::build()
     */
    #[Test]
    public function patternsAreUnique(): void
    {
        $patterns = Code128Modules::patterns();

        self::assertCount(107, $patterns);
        self::assertCount(107, array_unique($patterns));
    }

    /**
     * Проверим ширину символов: 11 модулей и шесть элементов, у стоп-символа — 13 модулей и семь.
     *
     * @see Code128Modules::build()
     */
    #[Test]
    public function patternsUseElevenModules(): void
    {
        $patterns = Code128Modules::patterns();
        $stop     = $patterns[Code128::STOP];

        foreach ($patterns as $value => $pattern) {
            if ($value === Code128::STOP) {
                continue;
            }

            self::assertSame(6, strlen($pattern), 'Symbol ' . $value);
            self::assertSame(11, array_sum(str_split($pattern)), 'Symbol ' . $value);
        }

        self::assertSame(7, strlen($stop));
        self::assertSame(13, array_sum(str_split($stop)));
    }

    /**
     * Проверим, что длина модулей складывается из символов по 11 модулей плюс завершающий штрих.
     *
     * @see Code128Modules::build()
     */
    #[Test]
    public function buildConcatenatesSymbolModules(): void
    {
        $codes = Code128::encode('AB');

        self::assertSame(11 * count($codes) + 2, strlen(Code128Modules::build($codes)));
    }

    /**
     * Проверим, что значение вне таблицы отклоняется.
     *
     * @see Code128Modules::build()
     */
    #[Test]
    public function buildRejectsUnknownSymbolValue(): void
    {
        $this->expectException(BarcodeException::class);

        Code128Modules::build([107]);
    }
}
