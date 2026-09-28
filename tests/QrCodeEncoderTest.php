<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use PhpSoftBox\Barcode\Exception\BarcodeException;
use PhpSoftBox\Barcode\QrErrorCorrectionLevel;
use PhpSoftBox\Barcode\Support\QrCodeEncoder;
use PhpSoftBox\Barcode\Support\QrSymbol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;
use function intdiv;
use function ksort;
use function str_repeat;
use function strlen;

#[CoversClass(QrCodeEncoder::class)]
#[CoversClass(QrSymbol::class)]
#[CoversMethod(QrCodeEncoder::class, 'encode')]
#[CoversMethod(QrCodeEncoder::class, 'encodeSymbol')]
#[CoversMethod(QrCodeEncoder::class, 'byteCapacity')]
#[CoversMethod(QrCodeEncoder::class, 'alignmentPatternPositions')]
final class QrCodeEncoderTest extends TestCase
{
    /**
     * Проверим ёмкость байтового режима по таблице 7 ISO/IEC 18004 для версий от 1 до 40.
     *
     * @see QrCodeEncoder::byteCapacity()
     */
    #[Test]
    #[DataProvider('capacityCases')]
    public function byteCapacityMatchesStandard(int $version, QrErrorCorrectionLevel $level, int $expected): void
    {
        self::assertSame($expected, QrCodeEncoder::byteCapacity($version, $level));
    }

    /**
     * @return iterable<string, array{int, QrErrorCorrectionLevel, int}>
     */
    public static function capacityCases(): iterable
    {
        yield 'v1 M' => [1, QrErrorCorrectionLevel::M, 14];
        yield 'v1 H' => [1, QrErrorCorrectionLevel::H, 7];
        yield 'v10 M' => [10, QrErrorCorrectionLevel::M, 213];
        yield 'v10 H' => [10, QrErrorCorrectionLevel::H, 119];
        yield 'v11 H' => [11, QrErrorCorrectionLevel::H, 137];
        yield 'v20 M' => [20, QrErrorCorrectionLevel::M, 666];
        yield 'v20 H' => [20, QrErrorCorrectionLevel::H, 382];
        yield 'v30 M' => [30, QrErrorCorrectionLevel::M, 1370];
        yield 'v30 H' => [30, QrErrorCorrectionLevel::H, 742];
        yield 'v40 M' => [40, QrErrorCorrectionLevel::M, 2331];
        yield 'v40 H' => [40, QrErrorCorrectionLevel::H, 1273];
    }

    /**
     * Проверим, что данные ровно на ёмкость версии кодируются этой версией, а на байт больше — следующей.
     *
     * @see QrCodeEncoder::encodeSymbol()
     */
    #[Test]
    #[DataProvider('boundaryCases')]
    public function choosesMinimalVersionAtBoundary(int $version, QrErrorCorrectionLevel $level): void
    {
        $capacity = QrCodeEncoder::byteCapacity($version, $level);
        $encoder  = new QrCodeEncoder();

        // Ровно на ёмкость — та же версия.
        $fits = $encoder->encodeSymbol(str_repeat('a', $capacity), $level);
        self::assertSame($version, $fits->version);
        self::assertSame($version * 4 + 17, $fits->size());

        // На байт больше — следующая.
        $next = $encoder->encodeSymbol(str_repeat('a', $capacity + 1), $level);
        self::assertSame($version + 1, $next->version);
    }

    /**
     * @return iterable<string, array{int, QrErrorCorrectionLevel}>
     */
    public static function boundaryCases(): iterable
    {
        yield 'v1→v2 M' => [1, QrErrorCorrectionLevel::M];
        yield 'v9→v10 H (длина в 16 бит)' => [9, QrErrorCorrectionLevel::H];
        yield 'v10→v11 H' => [10, QrErrorCorrectionLevel::H];
        yield 'v26→v27 M' => [26, QrErrorCorrectionLevel::M];
        yield 'v39→v40 H' => [39, QrErrorCorrectionLevel::H];
    }

    /**
     * Проверим, что адрес подписки длиной 124 байта с уровнем H кодируется версией 11, а не отклоняется.
     *
     * @see QrCodeEncoder::encodeSymbol()
     */
    #[Test]
    public function encodesSubscriptionUrlWithVersion11(): void
    {
        $url = 'https://s.safe-point.local/' . str_repeat('0f', 16) . '.' . str_repeat('a9', 32);

        $symbol = new QrCodeEncoder()->encodeSymbol($url, QrErrorCorrectionLevel::H);

        self::assertSame(124, strlen($url));
        self::assertSame(11, $symbol->version);
        self::assertCount(61, $symbol->matrix);
    }

    /**
     * Проверим, что данные сверх ёмкости версии 40 отклоняются с указанием предела.
     *
     * @see QrCodeEncoder::encode()
     */
    #[Test]
    public function throwsWhenPayloadExceedsVersion40(): void
    {
        $this->expectException(BarcodeException::class);
        $this->expectExceptionMessage('QR payload of 1274 bytes exceeds the capacity of version 40 with error correction H (1273 bytes).');

        new QrCodeEncoder()->encode(str_repeat('a', 1274), QrErrorCorrectionLevel::H);
    }

    /**
     * Проверим координаты выравнивающих узоров по таблице приложения E ISO/IEC 18004.
     *
     * @see QrCodeEncoder::alignmentPatternPositions()
     */
    #[Test]
    #[DataProvider('alignmentCases')]
    public function alignmentPatternPositionsMatchStandard(int $version, array $expected): void
    {
        self::assertSame($expected, QrCodeEncoder::alignmentPatternPositions($version));
    }

    /**
     * @return iterable<string, array{int, list<int>}>
     */
    public static function alignmentCases(): iterable
    {
        yield 'v1' => [1, []];
        yield 'v2' => [2, [6, 18]];
        yield 'v7' => [7, [6, 22, 38]];
        yield 'v14' => [14, [6, 26, 46, 66]];
        yield 'v21' => [21, [6, 28, 50, 72, 94]];
        yield 'v32' => [32, [6, 34, 60, 86, 112, 138]];
        yield 'v36' => [36, [6, 24, 50, 76, 102, 128, 154]];
        yield 'v40' => [40, [6, 30, 58, 86, 114, 142, 170]];
    }

    /**
     * Проверим биты версии (18 бит с кодом БЧХ) в обоих блоках матрицы по таблице D.1 ISO/IEC 18004.
     *
     * @see QrCodeEncoder::encodeSymbol()
     */
    #[Test]
    #[DataProvider('versionInformationCases')]
    public function drawsVersionInformation(int $version, int $code): void
    {
        $level  = QrErrorCorrectionLevel::M;
        $data   = str_repeat('a', QrCodeEncoder::byteCapacity($version, $level));
        $matrix = new QrCodeEncoder()->encode($data, $level);

        $size = count($matrix);

        for ($bit = 0; $bit < 18; $bit++) {
            $expected = (($code >> $bit) & 1) === 1;
            $near     = intdiv($bit, 3);
            $far      = $size - 11 + $bit % 3;

            self::assertSame($expected, $matrix[$near][$far], 'Top-right version block, bit ' . $bit);
            self::assertSame($expected, $matrix[$far][$near], 'Bottom-left version block, bit ' . $bit);
        }
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function versionInformationCases(): iterable
    {
        yield 'v7' => [7, 0x07C94];
        yield 'v21' => [21, 0x15683];
        yield 'v40' => [40, 0x28C69];
    }

    /**
     * Проверим раскладку кодовых слов по блокам: если закрыть весь символ версии 11 уровня H, каждый из 11 блоков
     * теряет все свои кодовые слова — 3 коротких блока по 12 + 24 и 8 длинных по 13 + 24.
     *
     * @see QrSymbol::damagedCodewordsPerBlock()
     * @see QrSymbol::correctableCodewordsPerBlock()
     */
    #[Test]
    public function reportsDamagedCodewordsPerBlock(): void
    {
        $symbol = new QrCodeEncoder()->encodeSymbol(str_repeat('a', 124), QrErrorCorrectionLevel::H);

        $cells = [];
        for ($row = 0; $row < $symbol->size(); $row++) {
            for ($col = 0; $col < $symbol->size(); $col++) {
                $cells[] = [$row, $col];
            }
        }

        $damaged = $symbol->damagedCodewordsPerBlock($cells);
        ksort($damaged);

        self::assertSame([36, 36, 36, 37, 37, 37, 37, 37, 37, 37, 37], $damaged);
        self::assertSame(12, $symbol->correctableCodewordsPerBlock());
    }

    /**
     * Проверим, что служебными считаются синхронизация, поисковые узоры с форматом и блоки версии, но не центр.
     *
     * @see QrSymbol::isCriticalFunctionModule()
     */
    #[Test]
    public function detectsCriticalFunctionModules(): void
    {
        $symbol = new QrCodeEncoder()->encodeSymbol(str_repeat('a', 124), QrErrorCorrectionLevel::H);

        self::assertTrue($symbol->isCriticalFunctionModule(6, 30));
        self::assertTrue($symbol->isCriticalFunctionModule(8, 8));
        self::assertTrue($symbol->isCriticalFunctionModule(2, 52));
        self::assertTrue($symbol->isCriticalFunctionModule(52, 2));
        self::assertFalse($symbol->isCriticalFunctionModule(30, 30));
    }
}
