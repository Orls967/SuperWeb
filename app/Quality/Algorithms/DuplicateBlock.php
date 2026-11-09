<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

final class DuplicateBlock
{
    public function __construct(
        public readonly string $fileA,
        public readonly int $indexA,
        public readonly string $fileB,
        public readonly int $indexB,
        public readonly int $length,
    ) {
    }

    /**
     * @return array{fileA: string, indexA: int, fileB: string, indexB: int, length: int}
     */
    public function toArray(): array
    {
        return [
            'fileA' => $this->fileA,
            'indexA' => $this->indexA,
            'fileB' => $this->fileB,
            'indexB' => $this->indexB,
            'length' => $this->length,
        ];
    }
}

