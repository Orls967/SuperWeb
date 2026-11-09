<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Sertifikat Kriptografis Graf Keterjangkauan Kelas A3 (W8-D04).
 */
final class ClassReachabilityCertificate
{
    /**
     * @param  list<string>  $roots
     * @param  list<string>  $reachable
     * @param  list<string>  $orphans
     * @param  list<list<string>>  $stronglyConnectedComponents
     */
    public function __construct(
        public readonly string $graphHash,
        public readonly int $totalNodes,
        public readonly int $totalEdges,
        public readonly array $roots,
        public readonly array $reachable,
        public readonly array $orphans,
        public readonly array $stronglyConnectedComponents,
        public readonly float $orphanRatio,
        public readonly string $signature,
        public readonly string $issuedAt
    ) {}

    public static function create(
        string $graphHash,
        int $nodes,
        int $edges,
        array $roots,
        array $reachable,
        array $orphans,
        array $sccs = []
    ): self {
        $orphanRatio = $nodes > 0 ? round(count($orphans) / $nodes, 4) : 0.0;
        $issued = date('c');

        $payload = json_encode([
            'graph_hash' => $graphHash,
            'nodes' => $nodes,
            'edges' => $edges,
            'roots' => $roots,
            'reachable' => $reachable,
            'orphans' => $orphans,
            'sccs' => $sccs,
            'orphan_ratio' => $orphanRatio,
            'issued_at' => $issued,
        ], JSON_THROW_ON_ERROR);

        $sig = hash_hmac('sha256', (string) $payload, 'PlatformQualitySecretKey_A3');

        return new self(
            graphHash: $graphHash,
            totalNodes: $nodes,
            totalEdges: $edges,
            roots: $roots,
            reachable: $reachable,
            orphans: $orphans,
            stronglyConnectedComponents: $sccs,
            orphanRatio: $orphanRatio,
            signature: $sig,
            issuedAt: $issued
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'graph_hash' => $this->graphHash,
            'total_nodes' => $this->totalNodes,
            'total_edges' => $this->totalEdges,
            'roots_count' => count($this->roots),
            'reachable_count' => count($this->reachable),
            'orphan_count' => count($this->orphans),
            'orphan_ratio' => $this->orphanRatio,
            'signature' => $this->signature,
            'issued_at' => $this->issuedAt,
        ];
    }
}
