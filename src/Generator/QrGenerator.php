<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Generator;

use GdImage;
use PhpSoftBox\Barcode\BarcodeGeneratorInterface;
use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeResult;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Exception\BarcodeException;
use PhpSoftBox\Barcode\Exception\UnsupportedBarcodeTypeException;
use PhpSoftBox\Barcode\QrErrorCorrectionLevel;
use PhpSoftBox\Barcode\QrLogoOptions;
use PhpSoftBox\Barcode\Support\MatrixRenderer;
use PhpSoftBox\Barcode\Support\QrCodeEncoder;
use PhpSoftBox\Barcode\Support\QrSymbol;

use function base64_encode;
use function count;
use function file_get_contents;
use function floor;
use function function_exists;
use function getimagesizefromstring;
use function hexdec;
use function imagealphablending;
use function imagecolorallocate;
use function imagecolorallocatealpha;
use function imagecopyresampled;
use function imagecreatefromstring;
use function imagecreatetruecolor;
use function imagefill;
use function imagefilledellipse;
use function imagefilledrectangle;
use function imagepng;
use function imagesavealpha;
use function imagesx;
use function imagesy;
use function intdiv;
use function is_array;
use function is_file;
use function is_string;
use function max;
use function min;
use function ob_get_clean;
use function ob_start;
use function pathinfo;
use function preg_match;
use function preg_split;
use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;
use function trim;

use const PATHINFO_EXTENSION;

/**
 * Генератор QR в SVG и PNG, при необходимости — с логотипом в центре.
 *
 * С логотипом уровень коррекции всегда `H`. Перед отрисовкой генератор проверяет, что подложка логотипа не закрывает
 * служебные узоры и что каждый блок коррекции теряет не больше кодовых слов, чем восстанавливает с запасом
 * (см. {@see self::LOGO_ERROR_BUDGET}); иначе — {@see BarcodeException}, а не нечитаемый код.
 */
final class QrGenerator implements BarcodeGeneratorInterface
{
    /**
     * Доля исправляющей способности блока, которую может занять логотип. Остаток — запас на блики, размытие и печать.
     */
    private const float LOGO_ERROR_BUDGET = 0.75;

    /**
     * Во сколько раз увеличивается подложка при отрисовке в PNG перед уменьшением: сглаживает скруглённые углы.
     */
    private const int PNG_SUPERSAMPLING = 4;

    public function __construct(
        private readonly QrCodeEncoder $encoder = new QrCodeEncoder(),
        private readonly MatrixRenderer $renderer = new MatrixRenderer(),
    ) {
    }

    public function supports(BarcodeType $type, BarcodeOutputFormat $format): bool
    {
        if ($type !== BarcodeType::Qr) {
            return false;
        }

        return $format === BarcodeOutputFormat::Svg || $format === BarcodeOutputFormat::Png;
    }

    public function generate(string $data, BarcodeType $type, ?BarcodeOptions $options = null): BarcodeResult
    {
        $resolvedOptions = $options ?? new BarcodeOptions();
        if (!$this->supports($type, $resolvedOptions->format)) {
            throw new UnsupportedBarcodeTypeException('QrGenerator supports only QR format.');
        }

        $logo  = $resolvedOptions->qrLogo;
        $level = $logo !== null ? QrErrorCorrectionLevel::H : $resolvedOptions->qrErrorCorrection;

        $symbol = $this->encoder->encodeSymbol($data, $level);
        $matrix = $symbol->matrix;
        $size   = $resolvedOptions->height;
        $margin = $resolvedOptions->margin;

        $logoLayout = null;
        if ($logo !== null) {
            $logoLayout = $this->prepareLogo($symbol, $logo, $size, $margin, $resolvedOptions->format);
        }

        if ($resolvedOptions->format === BarcodeOutputFormat::Png) {
            $content = $this->renderer->renderPng($matrix, $size, $margin);
            if ($logo !== null && $logoLayout !== null) {
                $content = $this->overlayLogoOnPng($content, $logo, $logoLayout);
            }

            return new BarcodeResult(content: $content, mimeType: 'image/png', width: $size, height: $size);
        }

        $content = $this->renderer->renderSvg($matrix, $size, $margin);
        if ($logo !== null && $logoLayout !== null) {
            $content = $this->overlayLogoOnSvg($content, $logo, $logoLayout);
        }

        return new BarcodeResult(content: $content, mimeType: 'image/svg+xml', width: $size, height: $size);
    }

    /**
     * Загружает логотип, рассчитывает его положение и проверяет, что код останется читаемым.
     *
     * @return array{bytes: string, mime: string, x: int, y: int, width: int, height: int, bgX: int, bgY: int, bgWidth: int, bgHeight: int}
     */
    private function prepareLogo(
        QrSymbol $symbol,
        QrLogoOptions $logo,
        int $size,
        int $margin,
        BarcodeOutputFormat $format,
    ): array {
        $bytes = $this->loadLogoBytes($logo->path);
        $mime  = $this->resolveMimeType($logo->path);

        if ($format === BarcodeOutputFormat::Png && $mime === 'image/svg+xml') {
            throw new BarcodeException('QR logo for PNG output must be a raster image (PNG/JPEG/GIF/WebP); SVG logos are supported in SVG output.');
        }

        [$sourceWidth, $sourceHeight] = $this->logoDimensions($bytes, $mime);

        $geometry   = $this->renderer->resolveGeometry($symbol->matrix, $size, $margin);
        $symbolSide = $geometry['moduleSize'] * $geometry['matrixSize'];
        $center     = $geometry['offset'] + $symbolSide / 2;

        // Знак вписывается в квадрат sizeRatio × сторона символа с сохранением пропорций.
        $maxSide = max(1, (int) floor($symbolSide * $logo->sizeRatio));
        $scale   = min($maxSide / $sourceWidth, $maxSide / $sourceHeight);
        $width   = max(1, (int) floor($sourceWidth * $scale));
        $height  = max(1, (int) floor($sourceHeight * $scale));
        $x       = (int) floor($center - $width / 2);
        $y       = (int) floor($center - $height / 2);

        $inset    = $logo->padding + $logo->borderWidth;
        $bgX      = max(0, $x - $inset);
        $bgY      = max(0, $y - $inset);
        $bgWidth  = min($size - $bgX, $width + $inset * 2);
        $bgHeight = min($size - $bgY, $height + $inset * 2);

        $this->assertReadable($symbol, $geometry, $bgX, $bgY, $bgWidth, $bgHeight);

        return [
            'bytes'    => $bytes,
            'mime'     => $mime,
            'x'        => $x,
            'y'        => $y,
            'width'    => $width,
            'height'   => $height,
            'bgX'      => $bgX,
            'bgY'      => $bgY,
            'bgWidth'  => $bgWidth,
            'bgHeight' => $bgHeight,
        ];
    }

    /**
     * Подложка не должна задевать служебные узоры, а каждый блок коррекции — терять больше кодовых слов, чем
     * {@see self::LOGO_ERROR_BUDGET} от исправимых. Потерянным считается модуль, центр которого под подложкой: сканер
     * читает модуль по центру.
     *
     * @param array{canvasSize: int, matrixSize: int, moduleSize: int, offset: int} $geometry
     */
    private function assertReadable(QrSymbol $symbol, array $geometry, int $x, int $y, int $width, int $height): void
    {
        $module = $geometry['moduleSize'];
        $offset = $geometry['offset'];
        $cells  = [];

        for ($row = 0; $row < $geometry['matrixSize']; $row++) {
            $centerY = $offset + $row * $module + $module / 2;
            if ($centerY < $y || $centerY >= $y + $height) {
                continue;
            }

            for ($col = 0; $col < $geometry['matrixSize']; $col++) {
                $centerX = $offset + $col * $module + $module / 2;
                if ($centerX < $x || $centerX >= $x + $width) {
                    continue;
                }

                if ($symbol->isCriticalFunctionModule($row, $col)) {
                    throw new BarcodeException(sprintf(
                        'QR logo covers finder, timing or format modules of version %d; reduce sizeRatio or padding.',
                        $symbol->version,
                    ));
                }

                $cells[] = [$row, $col];
            }
        }

        $limit = (int) floor($symbol->correctableCodewordsPerBlock() * self::LOGO_ERROR_BUDGET);
        foreach ($symbol->damagedCodewordsPerBlock($cells) as $block => $damaged) {
            if ($damaged > $limit) {
                throw new BarcodeException(sprintf(
                    'QR logo hides %d codewords of error correction block %d in version %d (at most %d allowed); reduce sizeRatio or padding, or increase the image size.',
                    $damaged,
                    $block + 1,
                    $symbol->version,
                    $limit,
                ));
            }
        }
    }

    /**
     * @param array{bytes: string, mime: string, x: int, y: int, width: int, height: int, bgX: int, bgY: int, bgWidth: int, bgHeight: int} $layout
     */
    private function overlayLogoOnSvg(string $svg, QrLogoOptions $logo, array $layout): string
    {
        $radius = min($logo->cornerRadius, intdiv(min($layout['bgWidth'], $layout['bgHeight']), 2));

        $overlay = sprintf(
            '<rect x="%d" y="%d" width="%d" height="%d" rx="%d" ry="%d" fill="%s"/>',
            $layout['bgX'],
            $layout['bgY'],
            $layout['bgWidth'],
            $layout['bgHeight'],
            $radius,
            $radius,
            $logo->backgroundColor,
        );

        // Обводка внутри подложки, как в PNG: линия толщиной w смещена на w/2 внутрь.
        $border = min($logo->borderWidth, intdiv(min($layout['bgWidth'], $layout['bgHeight']), 2));
        if ($logo->borderColor !== null && $border > 0) {
            $half = $border / 2;
            $overlay .= sprintf(
                '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" ry="%s" fill="none" stroke="%s" stroke-width="%d"/>',
                $this->number($layout['bgX'] + $half),
                $this->number($layout['bgY'] + $half),
                $this->number($layout['bgWidth'] - $border),
                $this->number($layout['bgHeight'] - $border),
                $this->number(max(0, $radius - $half)),
                $this->number(max(0, $radius - $half)),
                $logo->borderColor,
                $border,
            );
        }

        $overlay .= sprintf(
            '<image href="data:%s;base64,%s" x="%d" y="%d" width="%d" height="%d" preserveAspectRatio="xMidYMid meet"/>',
            $layout['mime'],
            base64_encode($layout['bytes']),
            $layout['x'],
            $layout['y'],
            $layout['width'],
            $layout['height'],
        );

        return str_replace('</svg>', $overlay . '</svg>', $svg);
    }

    /**
     * @param array{bytes: string, mime: string, x: int, y: int, width: int, height: int, bgX: int, bgY: int, bgWidth: int, bgHeight: int} $layout
     */
    private function overlayLogoOnPng(string $png, QrLogoOptions $logo, array $layout): string
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new BarcodeException('PNG logo overlay requires GD extension.');
        }

        $qrImage = imagecreatefromstring($png);
        if ($qrImage === false) {
            throw new BarcodeException('Unable to parse generated PNG content.');
        }

        $logoImage = imagecreatefromstring($layout['bytes']);
        if ($logoImage === false) {
            throw new BarcodeException('QR logo must be a raster image (PNG/JPEG/GIF/WebP) for PNG output.');
        }

        imagealphablending($qrImage, true);
        imagesavealpha($qrImage, true);

        $background = $this->renderBackground($layout['bgWidth'], $layout['bgHeight'], $logo);
        imagecopyresampled(
            $qrImage,
            $background,
            $layout['bgX'],
            $layout['bgY'],
            0,
            0,
            $layout['bgWidth'],
            $layout['bgHeight'],
            imagesx($background),
            imagesy($background),
        );

        imagecopyresampled(
            $qrImage,
            $logoImage,
            $layout['x'],
            $layout['y'],
            0,
            0,
            $layout['width'],
            $layout['height'],
            imagesx($logoImage),
            imagesy($logoImage),
        );

        ob_start();
        imagepng($qrImage);
        $result = ob_get_clean();
        if (!is_string($result)) {
            throw new BarcodeException('Unable to render PNG output.');
        }

        return $result;
    }

    /**
     * Подложка с прозрачными углами, нарисованная в увеличенном масштабе: после уменьшения скругление сглажено.
     */
    private function renderBackground(int $width, int $height, QrLogoOptions $logo): GdImage
    {
        $scale  = self::PNG_SUPERSAMPLING;
        $radius = min($logo->cornerRadius, intdiv(min($width, $height), 2)) * $scale;
        $border = min($logo->borderWidth, intdiv(min($width, $height), 2)) * $scale;
        $image  = imagecreatetruecolor($width * $scale, $height * $scale);

        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

        $fill  = $this->allocateColor($image, $logo->backgroundColor);
        $right = $width * $scale - 1;
        $lower = $height * $scale - 1;

        if ($logo->borderColor !== null && $border > 0) {
            $this->drawFilledRoundedRect($image, 0, 0, $right, $lower, $radius, $this->allocateColor($image, $logo->borderColor));
            $this->drawFilledRoundedRect($image, $border, $border, $right - $border, $lower - $border, max(0, $radius - $border), $fill);
        } else {
            $this->drawFilledRoundedRect($image, 0, 0, $right, $lower, $radius, $fill);
        }

        return $image;
    }

    private function drawFilledRoundedRect(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        if ($radius <= 0) {
            imagefilledrectangle($image, $x1, $y1, $x2, $y2, $color);

            return;
        }

        $diameter = $radius * 2;

        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);

        imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $diameter, $diameter, $color);
        imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $diameter, $diameter, $color);
        imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $diameter, $diameter, $color);
        imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $diameter, $diameter, $color);
    }

    private function allocateColor(GdImage $image, string $hex): int
    {
        $normalized = $hex;
        if (strlen($normalized) === 4) {
            $normalized = '#' . $normalized[1] . $normalized[1] . $normalized[2] . $normalized[2] . $normalized[3] . $normalized[3];
        }

        return (int) imagecolorallocate(
            $image,
            (int) hexdec(substr($normalized, 1, 2)),
            (int) hexdec(substr($normalized, 3, 2)),
            (int) hexdec(substr($normalized, 5, 2)),
        );
    }

    /**
     * Собственные размеры логотипа для сохранения пропорций: растр — по заголовку, SVG — по width/height или viewBox.
     *
     * @return array{0: float, 1: float}
     */
    private function logoDimensions(string $bytes, string $mime): array
    {
        if ($mime !== 'image/svg+xml') {
            $info = getimagesizefromstring($bytes);
            if (!is_array($info) || $info[0] <= 0 || $info[1] <= 0) {
                throw new BarcodeException('QR logo image cannot be read.');
            }

            return [(float) $info[0], (float) $info[1]];
        }

        if (preg_match('/<svg\b[^>]*>/i', $bytes, $tag) !== 1) {
            throw new BarcodeException('QR logo SVG has no <svg> element.');
        }

        $width  = $this->svgLength($tag[0], 'width');
        $height = $this->svgLength($tag[0], 'height');
        if ($width !== null && $height !== null) {
            return [$width, $height];
        }

        if (preg_match('/\sviewBox\s*=\s*["\']([^"\']+)["\']/i', $tag[0], $match) === 1) {
            $parts = preg_split('/[\s,]+/', trim($match[1]));
            if (is_array($parts) && count($parts) === 4 && (float) $parts[2] > 0 && (float) $parts[3] > 0) {
                return [(float) $parts[2], (float) $parts[3]];
            }
        }

        return [1.0, 1.0];
    }

    private function svgLength(string $tag, string $attribute): ?float
    {
        if (preg_match('/\s' . $attribute . '\s*=\s*["\']\s*([0-9]*\.?[0-9]+)\s*(px)?\s*["\']/i', $tag, $match) !== 1) {
            return null;
        }

        $value = (float) $match[1];

        return $value > 0 ? $value : null;
    }

    private function number(float|int $value): string
    {
        return (string) (floor($value) === (float) $value ? (int) $value : $value);
    }

    private function loadLogoBytes(string $path): string
    {
        if (!is_file($path)) {
            throw new BarcodeException(sprintf('QR logo file not found: %s', $path));
        }

        $content = file_get_contents($path);
        if (!is_string($content) || $content === '') {
            throw new BarcodeException(sprintf('QR logo file cannot be read: %s', $path));
        }

        return $content;
    }

    private function resolveMimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png'         => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'svg'         => 'image/svg+xml',
            default       => 'application/octet-stream',
        };
    }
}
