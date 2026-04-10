<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\Exception\BarcodeException;
use PhpSoftBox\Barcode\Support\Code39Modules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_ends_with;
use function str_starts_with;
use function strlen;

#[CoversClass(Code39Modules::class)]
#[CoversMethod(Code39Modules::class, 'build')]
final class Code39ModulesTest extends TestCase
{
    /**
     * Проверим, что значение обрамляется символом-разделителем `*`.
     *
     * @see Code39Modules::build()
     */
    #[Test]
    public function buildWrapsPayloadWithDelimiter(): void
    {
        $modules = Code39Modules::build('A');

        // Шаблон `*` при отношении 2:1.
        $delimiter = '100101101101';

        self::assertTrue(str_starts_with($modules, $delimiter));
        self::assertTrue(str_ends_with($modules, $delimiter));
    }

    /**
     * Проверим ширину символа: шесть узких и три широких элемента плюс узкий пробел между символами.
     *
     * @see Code39Modules::build()
     */
    #[Test]
    public function buildUsesNarrowGapBetweenCharacters(): void
    {
        // Три символа (*A*) по 6 + 3 * 2 модулей и два разделяющих пробела.
        self::assertSame(3 * 12 + 2, strlen(Code39Modules::build('A')));
    }

    /**
     * Проверим, что отношение 3:1 расширяет только широкие элементы.
     *
     * @see Code39Modules::build()
     */
    #[Test]
    public function buildAppliesWideRatio(): void
    {
        self::assertSame(3 * 15 + 2, strlen(Code39Modules::build('A', 3)));
    }

    /**
     * Проверим, что недопустимое отношение ширин отклоняется.
     *
     * @see Code39Modules::build()
     */
    #[Test]
    public function buildRejectsInvalidRatio(): void
    {
        $this->expectException(BarcodeException::class);

        Code39Modules::build('A', 4);
    }
}
