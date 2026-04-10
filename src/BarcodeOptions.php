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
        public Code39Charset $code39Charset = Code39Charset::Standard,
        public bool $code39Checksum = false,
        public int $code39WideRatio = 2,
        /**
         * GS1 DataMatrix: символ начинается с FNC1. Для остальных типов флаг игнорируется.
         */
        public bool $gs1 = false,
    ) {
        if ($moduleWidth <= 0 || $height <= 0 || $margin < 0) {
            throw new InvalidArgumentException('Invalid barcode options values.');
        }

        // Стандарт допускает отношение широкого элемента к узкому от 2:1 до 3:1.
        if ($code39WideRatio < 2 || $code39WideRatio > 3) {
            throw new InvalidArgumentException('Code 39 wide-to-narrow ratio must be 2 or 3.');
        }
    }
}
