# PhpSoftBox Barcode

Компонент генерации 1D/2D кодов для PhpSoftBox.

## Что поддерживается

- EAN-13 (встроенные SVG/PNG генераторы);
- Code 39, стандартный набор и Full ASCII (встроенные SVG/PNG генераторы);
- Code 128 с автоматическим выбором наборов A/B/C (встроенные SVG/PNG генераторы);
- QR (встроенный генератор, форматы SVG/PNG);
- DataMatrix (встроенный генератор, форматы SVG/PNG), включая GS1 DataMatrix для кодов маркировки;
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
use PhpSoftBox\Barcode\Generator\Code128PngGenerator;
use PhpSoftBox\Barcode\Generator\Code128SvgGenerator;
use PhpSoftBox\Barcode\Generator\Code39PngGenerator;
use PhpSoftBox\Barcode\Generator\Code39SvgGenerator;
use PhpSoftBox\Barcode\Generator\DataMatrixGenerator;
use PhpSoftBox\Barcode\Generator\Ean13PngGenerator;
use PhpSoftBox\Barcode\Generator\Ean13SvgGenerator;
use PhpSoftBox\Barcode\Generator\QrGenerator;
use PhpSoftBox\Barcode\QrErrorCorrectionLevel;
use PhpSoftBox\Barcode\QrLogoOptions;

$generator = new BarcodeGeneratorChain([
    new Ean13SvgGenerator(),
    new Ean13PngGenerator(),
    new Code39SvgGenerator(),
    new Code39PngGenerator(),
    new Code128SvgGenerator(),
    new Code128PngGenerator(),
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

## Code 39

```php
$code39 = $generator->generate(
    data: 'CELL-A1',
    type: BarcodeType::Code39,
    options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
);
```

- стандартный набор: `0-9`, `A-Z`, пробел и `- . $ / + %`; строчные буквы приводятся к верхнему регистру;
- `code39Charset: Code39Charset::FullAscii` кодирует любой ASCII-символ escape-парами (`+A`, `$I`, `%V`);
- `code39Checksum: true` добавляет контрольный символ mod 43 (по умолчанию выключен);
- `code39WideRatio` — отношение широкого элемента к узкому, `2` (по умолчанию) или `3`.

Подробности и ограничения — в [документации Code 39](docs/code39.md).

## Code 128

```php
$code128 = $generator->generate(
    data: 'BOX-000123456789',
    type: BarcodeType::Code128,
    options: new BarcodeOptions(format: BarcodeOutputFormat::Svg),
);
```

- принимает любые символы ASCII (0-127);
- наборы A, B и C выбираются автоматически: последовательности цифр пакуются по две в символ;
- контрольная сумма mod 103 обязательна по стандарту и добавляется всегда;
- дополнительных опций не требуется.

Подробности — в [документации Code 128](docs/code128.md).

## GS1 DataMatrix

Для кодов маркировки («Честный знак») нужен ведущий FNC1, иначе сканер сообщает символику `]d1`
вместо `]d2`:

```php
$mark = $generator->generate(
    data: $markCode,
    type: BarcodeType::DataMatrix,
    options: new BarcodeOptions(format: BarcodeOutputFormat::Svg, height: 184, gs1: true),
);
```

По умолчанию флаг выключен и вывод не меняется. Подробности — в
[документации GS1 DataMatrix](docs/gs1-datamatrix.md).

## QR с логотипом

- при `qrLogo` генератор автоматически использует уровень коррекции `H`;
- `QrLogoOptions` поддерживает настройку размера/паддинга и стиля зоны под логотип:
  `backgroundColor`, `borderColor`, `borderWidth`, `cornerRadius`.

## Тестовые артефакты

- перед запуском PHPUnit папка `local/tests/barcode` очищается автоматически;
- генерационные тесты сохраняют SVG/PNG артефакты в `local/tests/barcode`;
- при необходимости сохранение артефактов можно отключить: `BARCODE_TEST_SAVE_ARTIFACTS=0 vendor/bin/phpunit`.

Для Code 39, Code 128, GS1 DataMatrix и DataMatrix есть отдельные проверки независимым декодером — см.
[Code 39](docs/code39.md), [Code 128](docs/code128.md), [GS1 DataMatrix](docs/gs1-datamatrix.md) и ниже.

Для DataMatrix есть отдельная [проверка независимыми декодерами](docs/datamatrix-testing.md):
ZXing-C++ и libdmtx побайтно проверяют матрицы, SVG, PNG и растрированный PDF.
Этот прогон обязателен перед выпуском изменений кодировщика или рендерера DataMatrix;
обычный PHPUnit не требует установки внешних декодеров.
