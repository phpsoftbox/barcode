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

final class Code128SvgGenerator implements BarcodeGeneratorInterface
{
    /**
     * Свободная зона в модулях: стандарт требует не меньше десяти.
     */
    public const int QUIET_ZONE = 10;

    public function supports(BarcodeType $type, BarcodeOutputFormat $format): bool
    {
        return $type === BarcodeType::Code128 && $format === BarcodeOutputFormat::Svg;
    }

    public function generate(string $data, BarcodeType $type, ?BarcodeOptions $options = null): BarcodeResult
    {
        $resolvedOptions = $options ?? new BarcodeOptions();
        if (!$this->supports($type, $resolvedOptions->format)) {
            throw new UnsupportedBarcodeTypeException('Code128SvgGenerator supports only Code 128 in SVG format.');
        }

        $modules = Code128Modules::build(Code128::encode($data));

        return new LinearRenderer(self::QUIET_ZONE)->svg($modules, $data, $resolvedOptions);
    }
}
