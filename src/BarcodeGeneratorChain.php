<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode;

use PhpSoftBox\Barcode\Exception\UnsupportedBarcodeTypeException;

use function sprintf;

final readonly class BarcodeGeneratorChain implements BarcodeGeneratorInterface
{
    /**
     * @param list<BarcodeGeneratorInterface> $generators
     */
    public function __construct(
        private array $generators,
    ) {
    }

    public function supports(BarcodeType $type, BarcodeOutputFormat $format): bool
    {
        foreach ($this->generators as $generator) {
            if ($generator->supports($type, $format)) {
                return true;
            }
        }

        return false;
    }

    public function generate(string $data, BarcodeType $type, ?BarcodeOptions $options = null): BarcodeResult
    {
        $resolvedOptions = $options ?? new BarcodeOptions();

        foreach ($this->generators as $generator) {
            if ($generator->supports($type, $resolvedOptions->format)) {
                return $generator->generate($data, $type, $resolvedOptions);
            }
        }

        throw new UnsupportedBarcodeTypeException(sprintf(
            'No barcode generator found for type "%s" and format "%s".',
            $type->value,
            $resolvedOptions->format->value,
        ));
    }
}
