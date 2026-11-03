<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Services\AgriProcessing;

use Modules\Manufacturing\Domain\Models\AgriProcessing\MfgAgriGenealogyEdge;
use Modules\Shared\Domain\Algorithms\Graph\GenealogyEdgeProviderInterface;

class MfgAgriGenealogyService implements GenealogyEdgeProviderInterface
{
    /**
     * @return list<array{type: string, id: string}>
     */
    public function getOutgoingEdges(string $entityType, string $entityId): array
    {
        return MfgAgriGenealogyEdge::where('parent_entity_type', $entityType)
            ->where('parent_entity_id', $entityId)
            ->get()
            ->map(fn ($row) => ['type' => (string) $row->child_entity_type, 'id' => (string) $row->child_entity_id])
            ->values()
            ->toArray();
    }

    /**
     * @return list<array{type: string, id: string}>
     */
    public function getIncomingEdges(string $entityType, string $entityId): array
    {
        return MfgAgriGenealogyEdge::where('child_entity_type', $entityType)
            ->where('child_entity_id', $entityId)
            ->get()
            ->map(fn ($row) => ['type' => (string) $row->parent_entity_type, 'id' => (string) $row->parent_entity_id])
            ->values()
            ->toArray();
    }
}
