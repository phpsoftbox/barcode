<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode;

enum BarcodeType: string
{
    case Ean13      = 'ean13';
    case Code128    = 'code128';
    case Qr         = 'qr';
    case DataMatrix = 'datamatrix';
}
