<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Generator;

use PhpSoftBox\Barcode\BarcodeGeneratorInterface;
use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeResult;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Exception\UnsupportedBarcodeTypeException;
use PhpSoftBox\Barcode\Support\DataMatrixEncoder;
use PhpSoftBox\Barcode\Support\MatrixRenderer;

final class DataMatrixGenerator implements BarcodeGeneratorInterface
{
    public function __construct(
        private readonly DataMatrixEncoder $encoder = new DataMatrixEncoder(),
        private readonly MatrixRenderer $renderer = new MatrixRenderer(),
    ) {
    }

    public function supports(BarcodeType $type, BarcodeOutputFormat $format): bool
    {
        if ($type !== BarcodeType::DataMatrix) {
            return false;
        }

        return $format === BarcodeOutputFormat::Svg || $format === BarcodeOutputFormat::Png;
    }

    public function generate(string $data, BarcodeType $type, ?BarcodeOptions $options = null): BarcodeResult
    {
        $resolvedOptions = $options ?? new BarcodeOptions();
        if (!$this->supports($type, $resolvedOptions->format)) {
            throw new UnsupportedBarcodeTypeException('DataMatrixGenerator supports only DataMatrix format.');
        }

        $matrix = $this->encoder->encode($data);
        if ($resolvedOptions->format === BarcodeOutputFormat::Png) {
            return new BarcodeResult(
                content: $this->renderer->renderPng($matrix, $resolvedOptions->height, $resolvedOptions->margin),
                mimeType: 'image/png',
                width: $resolvedOptions->height,
                height: $resolvedOptions->height,
            );
        }

        return new BarcodeResult(
            content: $this->renderer->renderSvg($matrix, $resolvedOptions->height, $resolvedOptions->margin),
            mimeType: 'image/svg+xml',
            width: $resolvedOptions->height,
            height: $resolvedOptions->height,
        );
    }
}
