<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode;

use InvalidArgumentException;

final readonly class BarcodeOptions
{
    public function __construct(
        public BarcodeOutputFormat $format = BarcodeOutputFormat::Svg,
        public int $moduleWidth = 2,
        public int $height = 64,
        public int $margin = 10,
        public QrErrorCorrectionLevel $qrErrorCorrection = QrErrorCorrectionLevel::M,
        public ?QrLogoOptions $qrLogo = null,
    ) {
        if ($moduleWidth <= 0 || $height <= 0 || $margin < 0) {
            throw new InvalidArgumentException('Invalid barcode options values.');
        }
    }
}
