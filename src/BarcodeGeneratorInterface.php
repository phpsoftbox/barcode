<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode;

interface BarcodeGeneratorInterface
{
    public function supports(BarcodeType $type, BarcodeOutputFormat $format): bool;

    public function generate(string $data, BarcodeType $type, ?BarcodeOptions $options = null): BarcodeResult;
}
