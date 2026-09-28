<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use InvalidArgumentException;
use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Exception\BarcodeException;
use PhpSoftBox\Barcode\Exception\UnsupportedBarcodeTypeException;
use PhpSoftBox\Barcode\Generator\QrGenerator;
use PhpSoftBox\Barcode\QrErrorCorrectionLevel;
use PhpSoftBox\Barcode\QrLogoOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function imagecolorat;
use function imagecreatefromstring;
use function imagesx;
use function imagesy;
use function intdiv;
use function max;
use function min;
use function str_repeat;
use function str_starts_with;

#[CoversClass(QrGenerator::class)]
#[CoversClass(QrLogoOptions::class)]
#[CoversMethod(QrGenerator::class, 'generate')]
final class QrGeneratorTest extends TestCase
{
    private const string MARK_SVG      = __DIR__ . '/fixtures/qr-mark.svg';
    private const string MARK_PNG      = __DIR__ . '/fixtures/qr-mark.png';
    private const string MARK_WIDE_SVG = __DIR__ . '/fixtures/qr-mark-wide.svg';

    /**
     * Адрес подписки из ТЗ с фиктивным токеном: 32 и 64 шестнадцатеричных символа, 124 байта.
     */
    private const string SUBSCRIPTION_URL = 'https://s.safe-point.local/0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f.a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9a9';

    /**
     * Проверим генерацию QR в SVG без логотипа.
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    public function generatesSvg(): void
    {
        $result = new QrGenerator()->generate(
            data: 'P1-R1-C1',
            type: BarcodeType::Qr,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg, height: 256),
        );

        self::assertSame('image/svg+xml', $result->mimeType);
        self::assertSame(256, $result->width);
        self::assertSame(256, $result->height);
        self::assertStringContainsString('<svg', $result->content);

        TestArtifactStorage::save('qr-generator-svg', 'svg', $result->content);
    }

    /**
     * Проверим генерацию QR в PNG без логотипа.
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    #[RequiresPhpExtension('gd')]
    public function generatesPng(): void
    {
        $result = new QrGenerator()->generate(
            data: 'P1-R1-C1',
            type: BarcodeType::Qr,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Png, height: 256),
        );

        self::assertSame('image/png', $result->mimeType);
        self::assertSame(256, $result->width);
        self::assertTrue(str_starts_with($result->content, "\x89PNG"));

        TestArtifactStorage::save('qr-generator-png', 'png', $result->content);
    }

    /**
     * Проверим, что длинные данные без логотипа кодируются версией выше 10 (300 байт при уровне M — версия 13).
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    public function generatesLongPayloadWithoutLogo(): void
    {
        $result = new QrGenerator()->generate(
            data: str_repeat('A', 300),
            type: BarcodeType::Qr,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg, height: 512),
        );

        // Версия 13 — 69 модулей: 492 / 69 = 7 пикселей на модуль, отступ (512 - 483) / 2 = 14.
        self::assertStringContainsString('<rect x="14" y="14" width="7" height="7" fill="#000"/>', $result->content);
    }

    /**
     * Проверим, что сторона знака считается от стороны символа без поля: версия 2 (25 модулей по 9 пикселей)
     * даёт символ 225 пикселей, при sizeRatio 0.2 — знак 45 пикселей.
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    public function sizesLogoRelativeToSymbol(): void
    {
        $result = new QrGenerator()->generate(
            data: 'P1-R1-C1',
            type: BarcodeType::Qr,
            options: new BarcodeOptions(
                format: BarcodeOutputFormat::Svg,
                height: 256,
                qrLogo: new QrLogoOptions(path: self::MARK_SVG, sizeRatio: 0.2, padding: 4),
            ),
        );

        // Символ с 15 по 240 пиксель, центр — 127.5: знак 45×45 с 105, подложка на 4 пикселя шире.
        self::assertStringContainsString('<rect x="101" y="101" width="53" height="53" rx="6" ry="6" fill="#FFFFFF"/>', $result->content);
        self::assertStringContainsString('x="105" y="105" width="45" height="45"', $result->content);
    }

    /**
     * Проверим оформление подложки в SVG: заливка со скруглением и обводка внутри подложки, как в PNG.
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    public function drawsSvgLogoBackgroundWithInnerBorder(): void
    {
        $result = new QrGenerator()->generate(
            data: 'P1-R1-C1',
            type: BarcodeType::Qr,
            options: new BarcodeOptions(
                format: BarcodeOutputFormat::Svg,
                height: 256,
                qrLogo: new QrLogoOptions(
                    path: self::MARK_SVG,
                    sizeRatio: 0.18,
                    padding: 6,
                    backgroundColor: '#F3F4F6',
                    borderColor: '#111827',
                    borderWidth: 2,
                    cornerRadius: 10,
                ),
            ),
        );

        // Знак 40 пикселей с 107, подложка шире на padding + borderWidth = 8: 99…155.
        self::assertStringContainsString('<rect x="99" y="99" width="56" height="56" rx="10" ry="10" fill="#F3F4F6"/>', $result->content);
        self::assertStringContainsString(
            '<rect x="100" y="100" width="54" height="54" rx="9" ry="9" fill="none" stroke="#111827" stroke-width="2"/>',
            $result->content,
        );
        self::assertStringContainsString('data:image/svg+xml;base64,', $result->content);

        TestArtifactStorage::save('qr-generator-logo-svg', 'svg', $result->content);
    }

    /**
     * Проверим, что неквадратный знак вписывается в квадрат с сохранением пропорций: viewBox 200×100 при стороне
     * 45 пикселей даёт знак 45×22 по центру символа.
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    public function keepsLogoAspectRatioFromSvgViewBox(): void
    {
        $result = new QrGenerator()->generate(
            data: 'P1-R1-C1',
            type: BarcodeType::Qr,
            options: new BarcodeOptions(
                format: BarcodeOutputFormat::Svg,
                height: 256,
                qrLogo: new QrLogoOptions(path: self::MARK_WIDE_SVG, sizeRatio: 0.2, padding: 4),
            ),
        );

        self::assertStringContainsString('x="105" y="116" width="45" height="22"', $result->content);
    }

    /**
     * Проверим адрес подписки из ТЗ (124 байта) с рекомендуемым оформлением знака в SVG: подложка около 25%,
     * знак около 16% стороны символа.
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    public function generatesSubscriptionUrlWithLogoSvg(): void
    {
        $result = new QrGenerator()->generate(
            data: self::SUBSCRIPTION_URL,
            type: BarcodeType::Qr,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg, height: 192, margin: 8, qrLogo: $this->recommendedLogo(self::MARK_SVG)),
        );

        self::assertSame('image/svg+xml', $result->mimeType);
        self::assertStringContainsString('fill="#EEF2FF"', $result->content);

        TestArtifactStorage::save('qr-subscription-url-logo', 'svg', $result->content);
    }

    /**
     * Проверим адрес подписки из ТЗ с растровым знаком в PNG размером 192 пикселя.
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    #[RequiresPhpExtension('gd')]
    public function generatesSubscriptionUrlWithLogoPng(): void
    {
        $result = new QrGenerator()->generate(
            data: self::SUBSCRIPTION_URL,
            type: BarcodeType::Qr,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Png, height: 192, margin: 8, qrLogo: $this->recommendedLogo(self::MARK_PNG)),
        );

        self::assertSame('image/png', $result->mimeType);
        self::assertTrue(str_starts_with($result->content, "\x89PNG"));

        TestArtifactStorage::save('qr-subscription-url-logo', 'png', $result->content);
    }

    /**
     * Проверим, что подложка в PNG действительно скруглена: угол её габарита остаётся цветом QR, а середина края —
     * цветом подложки.
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    #[RequiresPhpExtension('gd')]
    public function roundsPngLogoBackground(): void
    {
        $result = new QrGenerator()->generate(
            data: self::SUBSCRIPTION_URL,
            type: BarcodeType::Qr,
            options: new BarcodeOptions(
                format: BarcodeOutputFormat::Png,
                height: 384,
                qrLogo: new QrLogoOptions(path: self::MARK_PNG, sizeRatio: 0.16, padding: 10, backgroundColor: '#FF00FF', cornerRadius: 12),
            ),
        );

        $image = imagecreatefromstring($result->content);
        self::assertNotFalse($image);

        // Габарит подложки — по пикселям её цвета.
        [$left, $top, $right] = [imagesx($image), imagesy($image), 0];
        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                if ((imagecolorat($image, $x, $y) & 0xFFFFFF) === 0xFF00FF) {
                    $left  = min($left, $x);
                    $top   = min($top, $y);
                    $right = max($right, $x);
                }
            }
        }

        self::assertNotSame(0xFF00FF, imagecolorat($image, $left, $top) & 0xFFFFFF);
        self::assertSame(0xFF00FF, imagecolorat($image, intdiv($left + $right, 2), $top) & 0xFFFFFF);
    }

    /**
     * Проверим адрес порядка 300 байт с логотипом: версия выше 11, код генерируется в обоих форматах.
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    #[RequiresPhpExtension('gd')]
    public function generatesLongUrlWithLogo(): void
    {
        $url = self::SUBSCRIPTION_URL . '?' . str_repeat('utm=x&', 29);

        foreach ([[BarcodeOutputFormat::Svg, self::MARK_SVG], [BarcodeOutputFormat::Png, self::MARK_PNG]] as [$format, $mark]) {
            $result = new QrGenerator()->generate(
                data: $url,
                type: BarcodeType::Qr,
                options: new BarcodeOptions(format: $format, height: 384, qrLogo: $this->recommendedLogo($mark)),
            );

            self::assertSame(384, $result->width);
            TestArtifactStorage::save('qr-long-url-logo', $format->value, $result->content);
        }
    }

    /**
     * Проверим, что логотип, закрывающий больше кодовых слов, чем восстанавливает коррекция с запасом, отклоняется,
     * а не даёт нечитаемый код.
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    public function rejectsLogoThatBreaksReadability(): void
    {
        $this->expectException(BarcodeException::class);
        $this->expectExceptionMessageMatches('/^QR logo hides \d+ codewords of error correction block \d+ in version 18/');

        new QrGenerator()->generate(
            data: str_repeat('x', 300),
            type: BarcodeType::Qr,
            options: new BarcodeOptions(
                format: BarcodeOutputFormat::Svg,
                height: 256,
                qrLogo: new QrLogoOptions(path: self::MARK_SVG, sizeRatio: 0.35, padding: 12),
            ),
        );
    }

    /**
     * Проверим, что логотип, задевающий служебные узоры маленького символа, отклоняется.
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    public function rejectsLogoCoveringFunctionPatterns(): void
    {
        $this->expectException(BarcodeException::class);
        $this->expectExceptionMessage('QR logo covers finder, timing or format modules of version 1');

        new QrGenerator()->generate(
            data: 'P1',
            type: BarcodeType::Qr,
            options: new BarcodeOptions(
                format: BarcodeOutputFormat::Svg,
                height: 256,
                qrLogo: new QrLogoOptions(path: self::MARK_SVG, sizeRatio: 0.35, padding: 30),
            ),
        );
    }

    /**
     * Проверим, что с логотипом уровень коррекции повышается до H: при уровне M и данных на ёмкость версии 1 уровня M
     * выбирается версия 2.
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    public function forcesHighErrorCorrectionWithLogo(): void
    {
        $withoutLogo = new QrGenerator()->generate(
            data: str_repeat('a', 14),
            type: BarcodeType::Qr,
            options: new BarcodeOptions(height: 210, margin: 0, qrErrorCorrection: QrErrorCorrectionLevel::M),
        );
        $withLogo = new QrGenerator()->generate(
            data: str_repeat('a', 14),
            type: BarcodeType::Qr,
            options: new BarcodeOptions(
                height: 210,
                margin: 0,
                qrErrorCorrection: QrErrorCorrectionLevel::M,
                qrLogo: new QrLogoOptions(path: self::MARK_SVG, sizeRatio: 0.1, padding: 1),
            ),
        );

        // Версия 1 — 21 модуль по 10 пикселей; с логотипом уровень H — версия 2, 25 модулей по 8 пикселей.
        self::assertStringContainsString('width="10" height="10"', $withoutLogo->content);
        self::assertStringContainsString('width="8" height="8"', $withLogo->content);
    }

    /**
     * Проверим, что SVG-знак для PNG отклоняется понятной ошибкой: GD не растрирует SVG.
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    #[RequiresPhpExtension('gd')]
    public function rejectsSvgLogoForPngOutput(): void
    {
        $this->expectException(BarcodeException::class);
        $this->expectExceptionMessage('QR logo for PNG output must be a raster image');

        new QrGenerator()->generate(
            data: 'P1-R1-C1',
            type: BarcodeType::Qr,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Png, qrLogo: new QrLogoOptions(path: self::MARK_SVG)),
        );
    }

    /**
     * Проверим, что генератор отклоняет не-QR тип.
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    public function throwsForUnsupportedType(): void
    {
        $this->expectException(UnsupportedBarcodeTypeException::class);

        new QrGenerator()->generate(
            data: '460123456789',
            type: BarcodeType::Ean13,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
        );
    }

    /**
     * Проверим, что данные сверх ёмкости версии 40 (2331 байт при уровне M) отклоняются.
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    public function throwsForTooLargePayload(): void
    {
        $this->expectException(BarcodeException::class);
        $this->expectExceptionMessage('exceeds the capacity of version 40');

        new QrGenerator()->generate(
            data: str_repeat('A', 2332),
            type: BarcodeType::Qr,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
        );
    }

    /**
     * Проверим, что отсутствующий файл логотипа даёт понятную ошибку.
     *
     * @see QrGenerator::generate()
     */
    #[Test]
    public function throwsForMissingLogoFile(): void
    {
        $this->expectException(BarcodeException::class);
        $this->expectExceptionMessage('QR logo file not found');

        new QrGenerator()->generate(
            data: 'P1-R1-C1',
            type: BarcodeType::Qr,
            options: new BarcodeOptions(format: BarcodeOutputFormat::Svg, qrLogo: new QrLogoOptions('/tmp/does-not-exist-logo.png')),
        );
    }

    /**
     * Проверим, что цвет подложки задаётся только в hex.
     *
     * @see QrLogoOptions::__construct()
     */
    #[Test]
    public function throwsForInvalidLogoColor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QrLogoOptions(path: '/tmp/logo.png', backgroundColor: 'white');
    }

    /**
     * Рекомендуемое оформление из ТЗ: знак около 16% стороны символа на светлой скруглённой подложке около 25%.
     */
    private function recommendedLogo(string $path): QrLogoOptions
    {
        return new QrLogoOptions(
            path: $path,
            sizeRatio: 0.16,
            padding: 5,
            backgroundColor: '#EEF2FF',
            cornerRadius: 8,
        );
    }
}
