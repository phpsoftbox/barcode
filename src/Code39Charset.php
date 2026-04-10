<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode;

/**
 * Набор символов Code 39.
 */
enum Code39Charset: string
{
    /**
     * 43 символа: 0-9, A-Z, пробел и - . $ / + % . Строчные буквы приводятся к верхнему регистру.
     */
    case Standard = 'standard';

    /**
     * Любой ASCII-символ через escape-пары ($A, %B, /C, +D). Сканер должен быть в режиме Full ASCII.
     */
    case FullAscii = 'full-ascii';
}
