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
use PhpSoftBox\Barcode\Support\Ean13;
use PhpSoftBox\Barcode\Support\Ean13Modules;

use function function_exists;
use function imagecolorallocate;
use function imagecreatetruecolor;
use function imagefilledrectangle;
use function imagepng;
use function imagestring;
use function is_string;
use function ob_get_clean;
use function ob_start;
use function str_split;

final class Ean13PngGenerator implements BarcodeGeneratorInterface
{
    public function supports(BarcodeType $type, BarcodeOutputFormat $format): bool
    {
        return $type === BarcodeType::Ean13 && $format === BarcodeOutputFormat::Png;
    }

    public function generate(string $data, BarcodeType $type, ?BarcodeOptions $options = null): BarcodeResult
    {
        $resolvedOptions = $options ?? new BarcodeOptions();
        if (!$this->supports($type, $resolvedOptions->format)) {
            throw new UnsupportedBarcodeTypeException('Ean13PngGenerator supports only EAN-13 in PNG format.');
        }

        if (!function_exists('imagecreatetruecolor')) {
            throw new BarcodeException('PNG rendering requires GD extension.');
        }

        $normalized = Ean13::normalize($data);
        $modules    = Ean13Modules::build($normalized);

        $quietZone  = 11;
        $barHeight  = $resolvedOptions->height;
        $textHeight = 14;
        $fullHeight = $barHeight + $textHeight + $resolvedOptions->margin;
        $fullWidth  = ($quietZone * 2 + 95) * $resolvedOptions->moduleWidth;
        $x          = $quietZone * $resolvedOptions->moduleWidth;
        $y          = (int) ($resolvedOptions->margin / 2);

        $image = imagecreatetruecolor($fullWidth, $fullHeight);
        if ($image === false) {
            throw new BarcodeException('Unable to create PNG canvas.');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $fullWidth - 1, $fullHeight - 1, $white);

        foreach (str_split($modules) as $bit) {
            if ($bit === '1') {
                imagefilledrectangle(
                    $image,
                    $x,
                    $y,
                    $x + $resolvedOptions->moduleWidth - 1,
                    $y + $barHeight - 1,
                    $black,
                );
            }

            $x += $resolvedOptions->moduleWidth;
        }

        imagestring(
            $image,
            2,
            $quietZone * $resolvedOptions->moduleWidth,
            $y + $barHeight + 2,
            $normalized,
            $black,
        );

        ob_start();
        imagepng($image);
        $png = ob_get_clean();

        if (!is_string($png)) {
            throw new BarcodeException('Unable to render PNG output.');
        }

        return new BarcodeResult(
            content: $png,
            mimeType: 'image/png',
            width: $fullWidth,
            height: $fullHeight,
        );
    }
}
