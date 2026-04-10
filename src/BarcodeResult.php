<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode;

final readonly class BarcodeResult
{
    public function __construct(
        public string $content,
        public string $mimeType,
        public int $width,
        public int $height,
    ) {
    }
}
