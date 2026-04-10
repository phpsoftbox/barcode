<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Support;

use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeResult;
use PhpSoftBox\Barcode\Exception\BarcodeException;

use function function_exists;
use function htmlspecialchars;
use function imagecolorallocate;
use function imagecreatetruecolor;
use function imagefilledrectangle;
use function imagepng;
use function imagestring;
use function is_string;
use function ob_get_clean;
use function ob_start;
use function sprintf;
use function str_split;
use function strlen;

use const ENT_QUOTES;
use const ENT_XML1;

/**
 * Отрисовка линейных кодов: штрихи по строке модулей плюс подпись под ними.
 */
final class LinearRenderer
{
    private const int TEXT_HEIGHT = 14;

    public function __construct(
        private readonly int $quietZone,
    ) {
    }

    public function svg(string $modules, string $text, BarcodeOptions $options): BarcodeResult
    {
        [$width, $height, $startX, $startY] = $this->layout($modules, $options);

        $barsSvg = '';
        $x       = $startX;
        foreach (str_split($modules) as $bit) {
            if ($bit === '1') {
                $barsSvg .= sprintf(
                    '<rect x="%d" y="%d" width="%d" height="%d" fill="#000"/>',
                    $x,
                    $startY,
                    $options->moduleWidth,
                    $options->height,
                );
            }

            $x += $options->moduleWidth;
        }

        $textSvg = sprintf(
            '<text x="%d" y="%d" font-size="12" font-family="monospace">%s</text>',
            $startX,
            $startY + $options->height + 12,
            htmlspecialchars($text, ENT_QUOTES | ENT_XML1),
        );

        // Белый фон: при растеризации и печати прозрачный фон темнеет, и штрихи сливаются с подложкой.
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">'
            . '<rect width="%d" height="%d" fill="#fff"/>%s%s</svg>',
            $width,
            $height,
            $width,
            $height,
            $width,
            $height,
            $barsSvg,
            $textSvg,
        );

        return new BarcodeResult(
            content: $svg,
            mimeType: 'image/svg+xml',
            width: $width,
            height: $height,
        );
    }

    public function png(string $modules, string $text, BarcodeOptions $options): BarcodeResult
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new BarcodeException('PNG rendering requires GD extension.');
        }

        [$width, $height, $startX, $startY] = $this->layout($modules, $options);

        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new BarcodeException('Unable to create PNG canvas.');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $white);

        $x = $startX;
        foreach (str_split($modules) as $bit) {
            if ($bit === '1') {
                imagefilledrectangle(
                    $image,
                    $x,
                    $startY,
                    $x + $options->moduleWidth - 1,
                    $startY + $options->height - 1,
                    $black,
                );
            }

            $x += $options->moduleWidth;
        }

        imagestring($image, 2, $startX, $startY + $options->height + 2, $text, $black);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();

        if (!is_string($png)) {
            throw new BarcodeException('Unable to render PNG output.');
        }

        return new BarcodeResult(
            content: $png,
            mimeType: 'image/png',
            width: $width,
            height: $height,
        );
    }

    /**
     * Размеры изображения и точка начала штрихов.
     *
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function layout(string $modules, BarcodeOptions $options): array
    {
        $width  = (strlen($modules) + $this->quietZone * 2) * $options->moduleWidth;
        $height = $options->height + self::TEXT_HEIGHT + $options->margin;

        return [$width, $height, $this->quietZone * $options->moduleWidth, (int) ($options->margin / 2)];
    }
}
