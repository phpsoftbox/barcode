<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Generator;

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

use function base64_encode;
use function file_get_contents;
use function floor;
use function function_exists;
use function hexdec;
use function imagealphablending;
use function imagearc;
use function imagecolorallocate;
use function imagecopyresampled;
use function imagecreatefromstring;
use function imagefilledellipse;
use function imagefilledrectangle;
use function imageline;
use function imagepng;
use function imagerectangle;
use function imagesavealpha;
use function imagesx;
use function imagesy;
use function is_file;
use function is_string;
use function max;
use function min;
use function ob_get_clean;
use function ob_start;
use function pathinfo;
use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;

use const PATHINFO_EXTENSION;

final class QrGenerator implements BarcodeGeneratorInterface
{
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

        $level = $resolvedOptions->qrErrorCorrection;
        if ($resolvedOptions->qrLogo !== null && $level !== QrErrorCorrectionLevel::H) {
            $level = QrErrorCorrectionLevel::H;
        }

        $matrix = $this->encoder->encode($data, $level);
        if ($resolvedOptions->format === BarcodeOutputFormat::Png) {
            $content = $this->renderer->renderPng($matrix, $resolvedOptions->height, $resolvedOptions->margin);
            if ($resolvedOptions->qrLogo !== null) {
                $content = $this->overlayLogoOnPng($content, $resolvedOptions->qrLogo);
            }

            return new BarcodeResult(
                content: $content,
                mimeType: 'image/png',
                width: $resolvedOptions->height,
                height: $resolvedOptions->height,
            );
        }

        $content = $this->renderer->renderSvg($matrix, $resolvedOptions->height, $resolvedOptions->margin);
        if ($resolvedOptions->qrLogo !== null) {
            $content = $this->overlayLogoOnSvg($content, $resolvedOptions->height, $resolvedOptions->qrLogo);
        }

        return new BarcodeResult(
            content: $content,
            mimeType: 'image/svg+xml',
            width: $resolvedOptions->height,
            height: $resolvedOptions->height,
        );
    }

    private function overlayLogoOnSvg(string $svg, int $size, QrLogoOptions $logo): string
    {
        $logoBytes = $this->loadLogoBytes($logo->path);
        $mimeType  = $this->resolveMimeType($logo->path);
        $logoSide  = max(1, (int) floor($size * $logo->sizeRatio));
        $logoX     = (int) floor(($size - $logoSide) / 2);
        $logoY     = (int) floor(($size - $logoSide) / 2);
        $inset     = $logo->padding + $logo->borderWidth;
        $bgX       = max(0, $logoX - $inset);
        $bgY       = max(0, $logoY - $inset);
        $bgSize    = min($size - $bgX, $logoSide + ($inset * 2));
        $radius    = min(max(0, $logo->cornerRadius), (int) floor($bgSize / 2));
        $stroke    = '';
        if ($logo->borderColor !== null && $logo->borderWidth > 0) {
            $stroke = sprintf(
                ' stroke="%s" stroke-width="%d" stroke-linejoin="round"',
                $logo->borderColor,
                $logo->borderWidth,
            );
        }

        $overlay = sprintf(
            '<rect x="%d" y="%d" width="%d" height="%d" fill="%s" rx="%d" ry="%d"%s/>',
            $bgX,
            $bgY,
            $bgSize,
            $bgSize,
            $logo->backgroundColor,
            $radius,
            $radius,
            $stroke,
        );
        $overlay .= sprintf(
            '<image href="data:%s;base64,%s" x="%d" y="%d" width="%d" height="%d" preserveAspectRatio="xMidYMid meet"/>',
            $mimeType,
            base64_encode($logoBytes),
            $logoX,
            $logoY,
            $logoSide,
            $logoSide,
        );

        return str_replace('</svg>', $overlay . '</svg>', $svg);
    }

    private function overlayLogoOnPng(string $png, QrLogoOptions $logo): string
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new BarcodeException('PNG logo overlay requires GD extension.');
        }

        $qrImage = imagecreatefromstring($png);
        if ($qrImage === false) {
            throw new BarcodeException('Unable to parse generated PNG content.');
        }

        $logoBytes = $this->loadLogoBytes($logo->path);
        $logoImage = imagecreatefromstring($logoBytes);
        if ($logoImage === false) {
            throw new BarcodeException('QR logo must be a raster image (PNG/JPEG/GIF/WebP) for PNG output.');
        }

        $canvasSize = imagesx($qrImage);
        $sourceW    = imagesx($logoImage);
        $sourceH    = imagesy($logoImage);
        $maxSide    = max(1, (int) floor($canvasSize * $logo->sizeRatio));
        $scale      = min($maxSide / $sourceW, $maxSide / $sourceH);
        $destW      = max(1, (int) floor($sourceW * $scale));
        $destH      = max(1, (int) floor($sourceH * $scale));
        $destX      = (int) floor(($canvasSize - $destW) / 2);
        $destY      = (int) floor(($canvasSize - $destH) / 2);
        $inset      = $logo->padding + $logo->borderWidth;
        $bgX        = max(0, $destX - $inset);
        $bgY        = max(0, $destY - $inset);
        $bgW        = min($canvasSize - $bgX, $destW + ($inset * 2));
        $bgH        = min($canvasSize - $bgY, $destH + ($inset * 2));

        imagealphablending($qrImage, true);
        imagesavealpha($qrImage, true);

        $this->drawLogoBackground($qrImage, $bgX, $bgY, $bgW, $bgH, $logo);
        imagecopyresampled($qrImage, $logoImage, $destX, $destY, 0, 0, $destW, $destH, $sourceW, $sourceH);

        ob_start();
        imagepng($qrImage);
        $result = ob_get_clean();
        if (!is_string($result)) {
            throw new BarcodeException('Unable to render PNG output.');
        }

        return $result;
    }

    private function drawLogoBackground($image, int $x, int $y, int $width, int $height, QrLogoOptions $logo): void
    {
        $x2     = $x + $width - 1;
        $y2     = $y + $height - 1;
        $radius = min($logo->cornerRadius, (int) floor(min($width, $height) / 2));

        $bgColor = $this->parseHexColor($logo->backgroundColor);
        $fill    = imagecolorallocate($image, $bgColor['r'], $bgColor['g'], $bgColor['b']);
        $this->drawFilledRoundedRect($image, $x, $y, $x2, $y2, $radius, $fill);

        if ($logo->borderColor === null || $logo->borderWidth <= 0) {
            return;
        }

        $borderColor = $this->parseHexColor($logo->borderColor);
        $stroke      = imagecolorallocate($image, $borderColor['r'], $borderColor['g'], $borderColor['b']);
        $maxWidth    = (int) floor(min($width, $height) / 2);
        $borderWidth = min($logo->borderWidth, $maxWidth);

        for ($line = 0; $line < $borderWidth; $line++) {
            $lineX1 = $x + $line;
            $lineY1 = $y + $line;
            $lineX2 = $x2 - $line;
            $lineY2 = $y2 - $line;
            if ($lineX1 > $lineX2 || $lineY1 > $lineY2) {
                break;
            }

            $lineRadius = max(0, $radius - $line);
            $this->drawRoundedRectOutline($image, $lineX1, $lineY1, $lineX2, $lineY2, $lineRadius, $stroke);
        }
    }

    private function drawFilledRoundedRect($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
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

    private function drawRoundedRectOutline($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        if ($radius <= 0) {
            imagerectangle($image, $x1, $y1, $x2, $y2, $color);

            return;
        }

        $diameter = $radius * 2;

        imageline($image, $x1 + $radius, $y1, $x2 - $radius, $y1, $color);
        imageline($image, $x1 + $radius, $y2, $x2 - $radius, $y2, $color);
        imageline($image, $x1, $y1 + $radius, $x1, $y2 - $radius, $color);
        imageline($image, $x2, $y1 + $radius, $x2, $y2 - $radius, $color);

        imagearc($image, $x1 + $radius, $y1 + $radius, $diameter, $diameter, 180, 270, $color);
        imagearc($image, $x2 - $radius, $y1 + $radius, $diameter, $diameter, 270, 360, $color);
        imagearc($image, $x2 - $radius, $y2 - $radius, $diameter, $diameter, 0, 90, $color);
        imagearc($image, $x1 + $radius, $y2 - $radius, $diameter, $diameter, 90, 180, $color);
    }

    /**
     * @return array{r: int, g: int, b: int}
     */
    private function parseHexColor(string $hex): array
    {
        $normalized = $hex;
        if (strlen($normalized) === 4) {
            $normalized = '#' . $normalized[1] . $normalized[1] . $normalized[2] . $normalized[2] . $normalized[3] . $normalized[3];
        }

        return [
            'r' => hexdec(substr($normalized, 1, 2)),
            'g' => hexdec(substr($normalized, 3, 2)),
            'b' => hexdec(substr($normalized, 5, 2)),
        ];
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
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'svg'   => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
