<?php

declare(strict_types=1);

use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Generator\Code128PngGenerator;
use PhpSoftBox\Barcode\Generator\Code128SvgGenerator;
use PhpSoftBox\Barcode\Tests\Code128Cases;

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Code128Cases.php';

$generators = [
    BarcodeOutputFormat::Svg->value => new Code128SvgGenerator(),
    BarcodeOutputFormat::Png->value => new Code128PngGenerator(),
];

foreach (Code128Cases::values() as $name => $value) {
    $images = [];
    foreach ($generators as $format => $generator) {
        $images[$format] = base64_encode($generator->generate(
            data: $value,
            type: BarcodeType::Code128,
            options: new BarcodeOptions(
                format: BarcodeOutputFormat::from($format),
                // Три пикселя на модуль: столько же нужно печати, чтобы штрихи не сливались.
                moduleWidth: 3,
                height: 80,
                margin: 16,
            ),
        )->content);
    }

    echo json_encode([
        'name'     => $name,
        'expected' => base64_encode($value),
        'images'   => $images,
    ], JSON_THROW_ON_ERROR) . "\n";
}
