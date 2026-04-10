<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Generator;

use PhpSoftBox\Barcode\BarcodeGeneratorInterface;
use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeResult;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Exception\UnsupportedBarcodeTypeException;
use PhpSoftBox\Barcode\Support\Code128;
use PhpSoftBox\Barcode\Support\Code128Modules;
use PhpSoftBox\Barcode\Support\LinearRenderer;

final class Code128PngGenerator implements BarcodeGeneratorInterface
{
    public function supports(BarcodeType $type, BarcodeOutputFormat $format): bool
    {
        return $type === BarcodeType::Code128 && $format === BarcodeOutputFormat::Png;
    }

    public function generate(string $data, BarcodeType $type, ?BarcodeOptions $options = null): BarcodeResult
    {
        $resolvedOptions = $options ?? new BarcodeOptions();
        if (!$this->supports($type, $resolvedOptions->format)) {
            throw new UnsupportedBarcodeTypeException('Code128PngGenerator supports only Code 128 in PNG format.');
        }

        $modules = Code128Modules::build(Code128::encode($data));

        return new LinearRenderer(Code128SvgGenerator::QUIET_ZONE)->png($modules, $data, $resolvedOptions);
    }
}
