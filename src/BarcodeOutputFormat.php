<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode;

enum BarcodeOutputFormat: string
{
    case Svg = 'svg';
    case Png = 'png';
}
