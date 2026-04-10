<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode;

use InvalidArgumentException;

use function is_string;
use function preg_match;
use function trim;

final readonly class QrLogoOptions
{
    public function __construct(
        public string $path,
        public float $sizeRatio = 0.20,
        public int $padding = 6,
        public string $backgroundColor = '#FFFFFF',
        public ?string $borderColor = null,
        public int $borderWidth = 0,
        public int $cornerRadius = 6,
    ) {
        if (trim($path) === '') {
            throw new InvalidArgumentException('QR logo path cannot be empty.');
        }

        if ($sizeRatio <= 0.0 || $sizeRatio > 0.35) {
            throw new InvalidArgumentException('QR logo size ratio must be in range (0, 0.35].');
        }

        if ($padding < 0) {
            throw new InvalidArgumentException('QR logo padding cannot be negative.');
        }

        if (!self::isValidHexColor($backgroundColor)) {
            throw new InvalidArgumentException('QR logo backgroundColor must be a hex color (#RGB or #RRGGBB).');
        }

        if ($borderColor !== null && !self::isValidHexColor($borderColor)) {
            throw new InvalidArgumentException('QR logo borderColor must be a hex color (#RGB or #RRGGBB).');
        }

        if ($borderWidth < 0 || $borderWidth > 20) {
            throw new InvalidArgumentException('QR logo borderWidth must be in range [0, 20].');
        }

        if ($cornerRadius < 0 || $cornerRadius > 128) {
            throw new InvalidArgumentException('QR logo cornerRadius must be in range [0, 128].');
        }
    }

    private static function isValidHexColor(string $value): bool
    {
        return is_string($value) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1;
    }
}
