<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode;

enum QrErrorCorrectionLevel: string
{
    case M = 'M';
    case H = 'H';
}
