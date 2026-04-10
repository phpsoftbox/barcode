<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Support;

use PhpSoftBox\Barcode\Exception\BarcodeException;

use function count;
use function floor;
use function function_exists;
use function imagecolorallocate;
use function imagecreatetruecolor;
use function imagefilledrectangle;
use function imagepng;
use function is_array;
use function is_string;
use function ob_get_clean;
use function ob_start;
use function sprintf;

final class MatrixRenderer
{
    /**
     * @param list<list<bool>> $matrix
     */
    public function renderSvg(array $matrix, int $size, int $margin): string
    {
        $geometry = $this->resolveGeometry($matrix, $size, $margin);

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d"><rect width="%d" height="%d" fill="#fff"/>',
            $geometry['canvasSize'],
            $geometry['canvasSize'],
            $geometry['canvasSize'],
            $geometry['canvasSize'],
            $geometry['canvasSize'],
            $geometry['canvasSize'],
        );

        for ($row = 0; $row < $geometry['matrixSize']; $row++) {
            for ($col = 0; $col < $geometry['matrixSize']; $col++) {
                if (!$matrix[$row][$col]) {
                    continue;
                }

                $x = $geometry['offset'] + ($col * $geometry['moduleSize']);
                $y = $geometry['offset'] + ($row * $geometry['moduleSize']);

                $svg .= sprintf(
                    '<rect x="%d" y="%d" width="%d" height="%d" fill="#000"/>',
                    $x,
                    $y,
                    $geometry['moduleSize'],
                    $geometry['moduleSize'],
                );
            }
        }

        return $svg . '</svg>';
    }

    /**
     * @param list<list<bool>> $matrix
     */
    public function renderPng(array $matrix, int $size, int $margin): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new BarcodeException('PNG rendering requires GD extension.');
        }

        $geometry = $this->resolveGeometry($matrix, $size, $margin);
        $image    = imagecreatetruecolor($geometry['canvasSize'], $geometry['canvasSize']);
        if ($image === false) {
            throw new BarcodeException('Unable to create PNG canvas.');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        imagefilledrectangle(
            $image,
            0,
            0,
            $geometry['canvasSize'] - 1,
            $geometry['canvasSize'] - 1,
            $white,
        );

        for ($row = 0; $row < $geometry['matrixSize']; $row++) {
            for ($col = 0; $col < $geometry['matrixSize']; $col++) {
                if (!$matrix[$row][$col]) {
                    continue;
                }

                $x1 = $geometry['offset'] + ($col * $geometry['moduleSize']);
                $y1 = $geometry['offset'] + ($row * $geometry['moduleSize']);
                $x2 = $x1 + $geometry['moduleSize'] - 1;
                $y2 = $y1 + $geometry['moduleSize'] - 1;

                imagefilledrectangle($image, $x1, $y1, $x2, $y2, $black);
            }
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();

        if (!is_string($png)) {
            throw new BarcodeException('Unable to render PNG output.');
        }

        return $png;
    }

    /**
     * @param list<list<bool>> $matrix
     *
     * @return array{canvasSize: int, matrixSize: int, moduleSize: int, offset: int}
     */
    private function resolveGeometry(array $matrix, int $size, int $margin): array
    {
        if ($size <= 0 || $margin < 0) {
            throw new BarcodeException('Invalid matrix rendering options.');
        }

        $matrixSize = count($matrix);
        if ($matrixSize === 0) {
            throw new BarcodeException('Matrix cannot be empty.');
        }

        foreach ($matrix as $row) {
            if (!is_array($row) || count($row) !== $matrixSize) {
                throw new BarcodeException('Matrix must be square.');
            }
        }

        $available = $size - ($margin * 2);
        if ($available <= 0) {
            throw new BarcodeException('Image size is too small for requested margin.');
        }

        $moduleSize = (int) floor($available / $matrixSize);
        if ($moduleSize <= 0) {
            throw new BarcodeException('Image size is too small for matrix modules.');
        }

        $contentSize = $moduleSize * $matrixSize;
        $offset      = (int) floor(($size - $contentSize) / 2);

        return [
            'canvasSize' => $size,
            'matrixSize' => $matrixSize,
            'moduleSize' => $moduleSize,
            'offset'     => $offset,
        ];
    }
}
