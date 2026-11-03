<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Graph;

class GenealogyTraversalResult
{
    /**
     * @param  list<string>  $visitedNodes  List of "TYPE:ID" strings
     */
    public function __construct(
        public readonly string $rootType,
        public readonly string $rootId,
        public readonly string $direction,
        public readonly array $visitedNodes,
        public readonly string $certificateHash,
        public readonly bool $certificateVerified = true,
    ) {}

    public function reaches(string $entityType, string $entityId): bool
    {
        $target = "{$entityType}:{$entityId}";

        return in_array($target, $this->visitedNodes, true);
    }

    /**
     * @return list<array{type: string, id: string}>
     */
    public function getNodes(): array
    {
        $result = [];
        foreach ($this->visitedNodes as $node) {
            $parts = explode(':', $node, 2);
            $result[] = [
                'type' => $parts[0],
                'id' => $parts[1] ?? '',
            ];
        }

        return $result;
    }
}
