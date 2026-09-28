<?php

declare(strict_types=1);

use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Generator\QrGenerator;
use PhpSoftBox\Barcode\QrErrorCorrectionLevel;
use PhpSoftBox\Barcode\QrLogoOptions;
use PhpSoftBox\Barcode\Support\QrCodeEncoder;

require __DIR__ . '/../../vendor/autoload.php';

// Фиктивный токен подписки: 32 и 64 шестнадцатеричных символа, адрес — 124 байта.
$subscriptionUrl = 'https://s.safe-point.local/' . str_repeat('0f', 16) . '.' . str_repeat('a9', 32);
$longUrl         = $subscriptionUrl . '?' . str_repeat('utm=x&', 29);
$markSvg         = __DIR__ . '/../fixtures/qr-mark.svg';
$markPng         = __DIR__ . '/../fixtures/qr-mark.png';

// Рекомендуемое оформление: знак около 16% стороны символа на светлой скруглённой подложке около 25%.
$recommended = static fn (string $path): QrLogoOptions => new QrLogoOptions(
    path: $path,
    sizeRatio: 0.16,
    padding: 5,
    backgroundColor: '#EEF2FF',
    cornerRadius: 8,
);

$m = QrErrorCorrectionLevel::M;
$h = QrErrorCorrectionLevel::H;

// Имя → [данные, уровень, высота, знак для SVG, знак для PNG, ширины растра SVG].
$cases = [
    'short-m'               => ['P1-R1-C1', $m, 256, null, null, []],
    'short-h'               => ['P1-R1-C1', $h, 256, null, null, []],
    'utf8-m'                => ['Ячейка П1-Р1-Я1 · склад №7', $m, 256, null, null, []],
    'v10-h-full'            => [str_repeat('h', QrCodeEncoder::byteCapacity(10, $h)), $h, 400, null, null, []],
    'v11-h-first'           => [str_repeat('h', QrCodeEncoder::byteCapacity(10, $h) + 1), $h, 400, null, null, []],
    'v26-m-full'            => [str_repeat('m', QrCodeEncoder::byteCapacity(26, $m)), $m, 560, null, null, []],
    'v27-m-first'           => [str_repeat('m', QrCodeEncoder::byteCapacity(26, $m) + 1), $m, 560, null, null, []],
    'v40-m-full'            => [str_repeat('M', QrCodeEncoder::byteCapacity(40, $m)), $m, 740, null, null, []],
    'v40-h-full'            => [str_repeat('H', QrCodeEncoder::byteCapacity(40, $h)), $h, 740, null, null, []],
    'subscription-url'      => [$subscriptionUrl, $h, 256, null, null, []],
    'subscription-url-logo' => [$subscriptionUrl, $h, 256, $recommended($markSvg), $recommended($markPng), []],
    // 192 CSS-пикселя на экранах с плотностью 1, 2 и 3.
    'subscription-url-192css' => [$subscriptionUrl, $h, 192, $recommended($markSvg), $recommended($markPng), [192, 384, 576]],
    'long-url-logo'           => [$longUrl, $h, 384, $recommended($markSvg), $recommended($markPng), []],
    // Близко к пределу проверки читаемости: логотип занимает 2/3 исправимых кодовых слов блока.
    'subscription-url-large-logo' => [
        $subscriptionUrl,
        $h,
        192,
        new QrLogoOptions(path: $markSvg, sizeRatio: 0.25, padding: 6),
        new QrLogoOptions(path: $markPng, sizeRatio: 0.25, padding: 6),
        [],
    ],
];

$encoder   = new QrCodeEncoder();
$generator = new QrGenerator();

foreach ($cases as $name => [$data, $level, $height, $svgLogo, $pngLogo, $rasterWidths]) {
    $images = [];
    foreach ([[BarcodeOutputFormat::Svg, $svgLogo], [BarcodeOutputFormat::Png, $pngLogo]] as [$format, $logo]) {
        $images[$format->value] = base64_encode($generator->generate(
            data: $data,
            type: BarcodeType::Qr,
            options: new BarcodeOptions(format: $format, height: $height, margin: 16, qrErrorCorrection: $level, qrLogo: $logo),
        )->content);
    }

    $symbol = $encoder->encodeSymbol($data, $svgLogo !== null ? $h : $level);

    echo json_encode([
        'name'         => $name,
        'payload'      => base64_encode($data),
        'version'      => $symbol->version,
        'logo'         => $svgLogo !== null,
        'matrix'       => $svgLogo === null ? $symbol->matrix : null,
        'rasterWidths' => $rasterWidths,
        'images'       => $images,
    ], JSON_THROW_ON_ERROR) . "\n";
}
