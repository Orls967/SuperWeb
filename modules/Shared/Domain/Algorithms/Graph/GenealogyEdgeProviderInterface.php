<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Graph;

interface GenealogyEdgeProviderInterface
{
    /**
     * @return list<array{type: string, id: string}>
     */
    public function getOutgoingEdges(string $entityType, string $entityId): array;

    /**
     * @return list<array{type: string, id: string}>
     */
    public function getIncomingEdges(string $entityType, string $entityId): array;
}
