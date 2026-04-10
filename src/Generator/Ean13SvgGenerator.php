<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Generator;

use PhpSoftBox\Barcode\BarcodeGeneratorInterface;
use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeResult;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Exception\UnsupportedBarcodeTypeException;
use PhpSoftBox\Barcode\Support\Ean13;
use PhpSoftBox\Barcode\Support\Ean13Modules;

use function sprintf;
use function str_split;

final class Ean13SvgGenerator implements BarcodeGeneratorInterface
{
    public function supports(BarcodeType $type, BarcodeOutputFormat $format): bool
    {
        return $type === BarcodeType::Ean13 && $format === BarcodeOutputFormat::Svg;
    }

    public function generate(string $data, BarcodeType $type, ?BarcodeOptions $options = null): BarcodeResult
    {
        $resolvedOptions = $options ?? new BarcodeOptions();
        if (!$this->supports($type, $resolvedOptions->format)) {
            throw new UnsupportedBarcodeTypeException('Ean13SvgGenerator supports only EAN-13 in SVG format.');
        }

        $normalized = Ean13::normalize($data);
        $modules    = Ean13Modules::build($normalized);

        $quietZone  = 11;
        $barHeight  = $resolvedOptions->height;
        $textHeight = 14;
        $fullHeight = $barHeight + $textHeight + $resolvedOptions->margin;
        $fullWidth  = ($quietZone * 2 + 95) * $resolvedOptions->moduleWidth;
        $x          = $quietZone * $resolvedOptions->moduleWidth;
        $y          = $resolvedOptions->margin / 2;

        $barsSvg = '';
        foreach (str_split($modules) as $bit) {
            if ($bit === '1') {
                $barsSvg .= sprintf(
                    '<rect x="%d" y="%d" width="%d" height="%d" fill="#000"/>',
                    $x,
                    $y,
                    $resolvedOptions->moduleWidth,
                    $barHeight,
                );
            }

            $x += $resolvedOptions->moduleWidth;
        }

        $textSvg = sprintf(
            '<text x="%d" y="%d" font-size="12" font-family="monospace">%s</text>',
            $quietZone * $resolvedOptions->moduleWidth,
            $y + $barHeight + 12,
            $normalized,
        );

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">%s%s</svg>',
            $fullWidth,
            $fullHeight,
            $fullWidth,
            $fullHeight,
            $barsSvg,
            $textSvg,
        );

        return new BarcodeResult(
            content: $svg,
            mimeType: 'image/svg+xml',
            width: $fullWidth,
            height: $fullHeight,
        );
    }
}
