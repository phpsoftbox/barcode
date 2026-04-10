<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Generator;

use PhpSoftBox\Barcode\BarcodeGeneratorInterface;
use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeResult;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Exception\UnsupportedBarcodeTypeException;
use PhpSoftBox\Barcode\Support\Code39;
use PhpSoftBox\Barcode\Support\Code39Modules;
use PhpSoftBox\Barcode\Support\LinearRenderer;

final class Code39PngGenerator implements BarcodeGeneratorInterface
{
    public function supports(BarcodeType $type, BarcodeOutputFormat $format): bool
    {
        return $type === BarcodeType::Code39 && $format === BarcodeOutputFormat::Png;
    }

    public function generate(string $data, BarcodeType $type, ?BarcodeOptions $options = null): BarcodeResult
    {
        $resolvedOptions = $options ?? new BarcodeOptions();
        if (!$this->supports($type, $resolvedOptions->format)) {
            throw new UnsupportedBarcodeTypeException('Code39PngGenerator supports only Code 39 in PNG format.');
        }

        $modules = Code39Modules::build(
            Code39::payload($data, $resolvedOptions),
            $resolvedOptions->code39WideRatio,
        );

        return new LinearRenderer(Code39SvgGenerator::QUIET_ZONE)->png($modules, $data, $resolvedOptions);
    }
}
