<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Support;

use PhpSoftBox\Barcode\QrErrorCorrectionLevel;

use function count;
use function intdiv;

/**
 * Закодированный QR-символ: матрица и раскладка кодовых слов по модулям и блокам коррекции.
 *
 * Раскладка нужна, чтобы оценить, сколько кодовых слов каждого блока теряется под логотипом.
 */
final readonly class QrSymbol
{
    /**
     * @param list<list<bool>> $matrix
     * @param list<list<int>> $moduleCodewords номер кодового слова в модуле; -1 — служебный или остаточный модуль
     * @param list<int> $codewordBlocks номер блока коррекции для каждого кодового слова
     */
    public function __construct(
        public int $version,
        public QrErrorCorrectionLevel $level,
        public array $matrix,
        public array $moduleCodewords,
        public array $codewordBlocks,
        public int $eccCodewordsPerBlock,
    ) {
    }

    public function size(): int
    {
        return count($this->matrix);
    }

    /**
     * Сколько ошибочных кодовых слов исправляет каждый блок: половина кодовых слов коррекции.
     */
    public function correctableCodewordsPerBlock(): int
    {
        return intdiv($this->eccCodewordsPerBlock, 2);
    }

    /**
     * Сколько кодовых слов каждого блока затрагивают перекрытые модули.
     *
     * @param iterable<array{0: int, 1: int}> $cells пары [строка, столбец]
     * @return array<int, int> номер блока → число затронутых кодовых слов
     */
    public function damagedCodewordsPerBlock(iterable $cells): array
    {
        $damaged = [];
        foreach ($cells as [$row, $col]) {
            $codeword = $this->moduleCodewords[$row][$col] ?? -1;
            if ($codeword >= 0) {
                $damaged[$codeword] = true;
            }
        }

        $perBlock = [];
        foreach ($damaged as $codeword => $_) {
            $block            = $this->codewordBlocks[$codeword];
            $perBlock[$block] = ($perBlock[$block] ?? 0) + 1;
        }

        return $perBlock;
    }

    /**
     * Модуль поисковых узоров, разделителей, синхронизации, формата или версии: без них символ не находится или не
     * декодируется. Выравнивающие узоры сюда не входят — сканеры восстанавливают их положение.
     */
    public function isCriticalFunctionModule(int $row, int $col): bool
    {
        $size = $this->size();

        if ($row === 6 || $col === 6) {
            return true;
        }

        if (($row < 9 && $col < 9) || ($row < 9 && $col >= $size - 8) || ($row >= $size - 8 && $col < 9)) {
            return true;
        }

        if ($this->version < 7) {
            return false;
        }

        return ($row < 6 && $col >= $size - 11 && $col < $size - 8)
            || ($col < 6 && $row >= $size - 11 && $row < $size - 8);
    }
}
