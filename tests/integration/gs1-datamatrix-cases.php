<?php

declare(strict_types=1);

use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Generator\DataMatrixGenerator;
use PhpSoftBox\Barcode\Tests\Gs1DataMatrixCases;

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Gs1DataMatrixCases.php';

$generator = new DataMatrixGenerator();

foreach (Gs1DataMatrixCases::cases() as $name => $case) {
    $images = [];
    foreach ([BarcodeOutputFormat::Svg, BarcodeOutputFormat::Png] as $format) {
        $images[$format->value] = base64_encode($generator->generate(
            data: $case['data'],
            type: BarcodeType::DataMatrix,
            options: new BarcodeOptions(
                format: $format,
                height: 256,
                margin: 16,
                gs1: $case['gs1'],
            ),
        )->content);
    }

    echo json_encode([
        'name'      => $name,
        'payload'   => base64_encode($case['data']),
        'symbology' => $case['symbology'],
        'images'    => $images,
    ], JSON_THROW_ON_ERROR) . "\n";
}
