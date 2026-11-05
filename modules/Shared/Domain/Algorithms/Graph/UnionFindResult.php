<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Graph;

final readonly class UnionFindResult
{
    /**
     * @param  list<string>  $elements
     * @param  list<array{0: string, 1: string}>  $edges
     * @param  array<string, string>  $parents
     * @param  array<string, int>  $ranks
     * @param  array<string, list<string>>  $components  representative => members
     */
    public function __construct(
        public array $elements,
        public array $edges,
        public array $parents,
        public array $ranks,
        public array $components,
        public int $componentCount,
        public int $maxComponentSize,
    ) {}

    /**
     * @return list<list<string>> Komponen dengan ukuran > 1 (indikasi sindikat / ring)
     */
    public function rings(int $minSize = 2): array
    {
        $rings = [];
        foreach ($this->components as $members) {
            if (count($members) >= $minSize) {
                $rings[] = $members;
            }
        }

        return $rings;
    }
}
