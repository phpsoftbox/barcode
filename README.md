# PhpSoftBox Barcode

Компонент генерации 1D/2D кодов для PhpSoftBox.

## Что поддерживается

- EAN-13 (встроенные SVG/PNG генераторы);
- QR (встроенный генератор, форматы SVG/PNG);
- DataMatrix (встроенный генератор, форматы SVG/PNG);
- роутинг генерации через цепочку `BarcodeGeneratorChain`.

Текущие ограничения первой итерации:
- QR: уровни коррекции `M` и `H`, версии до `10`;
- DataMatrix: квадратные ECC200-символы от `10x10` до `144x144`.

## Базовое использование

```php
use PhpSoftBox\Barcode\BarcodeGeneratorChain;
use PhpSoftBox\Barcode\BarcodeOptions;
use PhpSoftBox\Barcode\BarcodeOutputFormat;
use PhpSoftBox\Barcode\BarcodeType;
use PhpSoftBox\Barcode\Generator\DataMatrixGenerator;
use PhpSoftBox\Barcode\Generator\Ean13PngGenerator;
use PhpSoftBox\Barcode\Generator\Ean13SvgGenerator;
use PhpSoftBox\Barcode\Generator\QrGenerator;
use PhpSoftBox\Barcode\QrErrorCorrectionLevel;
use PhpSoftBox\Barcode\QrLogoOptions;

$generator = new BarcodeGeneratorChain([
    new Ean13SvgGenerator(),
    new Ean13PngGenerator(),
    new QrGenerator(),
    new DataMatrixGenerator(),
]);

$ean = $generator->generate(
    data: '460123456789',
    type: BarcodeType::Ean13, // вернет PNG
    options: new BarcodeOptions(format: BarcodeOutputFormat::Png),
);

$eanSvg = $generator->generate(
    data: '460123456789',
    type: BarcodeType::Ean13, // вернет SVG
    options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
);

$qr = $generator->generate(
    data: 'P1-R1-C1',
    type: BarcodeType::Qr, // QR с логотипом в центре
    options: new BarcodeOptions(
        format: BarcodeOutputFormat::Svg,
        height: 256,
        qrErrorCorrection: QrErrorCorrectionLevel::H,
        qrLogo: new QrLogoOptions(
            path: __DIR__ . '/logo.png',
            sizeRatio: 0.18,
            padding: 6,
            backgroundColor: '#F3F4F6',
            borderColor: '#111827',
            borderWidth: 2,
            cornerRadius: 10,
        ),
    ),
);

$dm = $generator->generate(
    data: 'DM-460123456789',
    type: BarcodeType::DataMatrix,
    options: new BarcodeOptions(format: BarcodeOutputFormat::Svg, height: 256),
);
```

## EAN-13

`Ean13::normalize()`:

- принимает 12 цифр и автоматически рассчитывает контрольную цифру;
- принимает 13 цифр и проверяет корректность контрольной цифры;
- бросает исключение при некорректном формате/чексумме.

## QR с логотипом

- при `qrLogo` генератор автоматически использует уровень коррекции `H`;
- `QrLogoOptions` поддерживает настройку размера/паддинга и стиля зоны под логотип:
  `backgroundColor`, `borderColor`, `borderWidth`, `cornerRadius`.

## Тестовые артефакты

- перед запуском PHPUnit папка `local/tests/barcode` очищается автоматически;
- генерационные тесты сохраняют SVG/PNG артефакты в `local/tests/barcode`;
- при необходимости сохранение артефактов можно отключить: `BARCODE_TEST_SAVE_ARTIFACTS=0 vendor/bin/phpunit`.
