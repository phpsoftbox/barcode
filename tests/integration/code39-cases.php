<?php

declare(strict_types=1);

use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Generator\Code39PngGenerator;
use PhpSoftBox\Barcode\Generator\Code39SvgGenerator;
use PhpSoftBox\Barcode\Tests\Code39Cases;

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Code39Cases.php';

$generators = [
    BarcodeOutputFormat::Svg->value => new Code39SvgGenerator(),
    BarcodeOutputFormat::Png->value => new Code39PngGenerator(),
];

foreach (Code39Cases::cases() as $name => $case) {
    $images = [];
    foreach ($generators as $format => $generator) {
        $images[$format] = base64_encode($generator->generate(
            data: $case['data'],
            type: BarcodeType::Code39,
            options: new BarcodeOptions(
                format: BarcodeOutputFormat::from($format),
                // Три пикселя на узкий модуль: столько же нужно печати, чтобы штрихи не сливались.
                moduleWidth: 3,
                height: 80,
                margin: 16,
                code39Charset: $case['charset'],
                code39Checksum: $case['checksum'],
                code39WideRatio: $case['wideRatio'],
            ),
        )->content);
    }

    echo json_encode([
        'name'     => $name,
        'expected' => base64_encode($case['expected']),
        'charset'  => $case['charset']->value,
        'images'   => $images,
    ], JSON_THROW_ON_ERROR) . "\n";
}
