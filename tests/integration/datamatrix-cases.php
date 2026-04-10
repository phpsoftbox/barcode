<?php

declare(strict_types=1);

use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Generator\DataMatrixGenerator;
use PhpSoftBox\Barcode\Support\DataMatrixEncoder;
use PhpSoftBox\Barcode\Tests\DataMatrixCases;

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../DataMatrixCases.php';

$encoder   = new DataMatrixEncoder();
$generator = new DataMatrixGenerator();

foreach (DataMatrixCases::payloads() as $name => [$payload, $size]) {
    // Четыре пикселя на модуль и quiet zone по четыре модуля с каждой стороны.
    $height = ($size + 8) * 4;
    $images = [];
    foreach ([BarcodeOutputFormat::Svg, BarcodeOutputFormat::Png] as $format) {
        $images[$format->value] = base64_encode($generator->generate(
            data: $payload,
            type: BarcodeType::DataMatrix,
            options: new BarcodeOptions(format: $format, height: $height, margin: 16),
        )->content);
    }

    echo json_encode([
        'name'    => $name,
        'payload' => base64_encode($payload),
        'size'    => $size,
        'matrix'  => $encoder->encode($payload),
        'images'  => $images,
    ], JSON_THROW_ON_ERROR) . "\n";
}
