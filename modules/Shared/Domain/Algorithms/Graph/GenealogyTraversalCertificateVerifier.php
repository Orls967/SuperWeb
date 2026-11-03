<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Graph;

use Closure;

class GenealogyTraversalCertificateVerifier
{
    /**
     * Verifikasi penutupan graf: bandingkan hasil BFS terhadap DFS oracle independen.
     *
     * @param  Closure(string, string, string): list<array{type: string, id: string}>  $edgeLoader
     */
    public function verify(
        string $rootType,
        string $rootId,
        string $direction,
        GenealogyTraversalResult $result,
        Closure $edgeLoader
    ): bool {
        $visited = [];
        $dfs = function (string $type, string $id) use (&$dfs, &$visited, $edgeLoader, $direction): void {
            $key = "{$type}:{$id}";
            if (isset($visited[$key])) {
                return;
            }
            $visited[$key] = true;

            $neighbors = $edgeLoader($type, $id, $direction);
            foreach ($neighbors as $neighbor) {
                $dfs($neighbor['type'], $neighbor['id']);
            }
        };

        $dfs($rootType, $rootId);

        $dfsNodes = array_keys($visited);
        sort($dfsNodes);

        $bfsNodes = $result->visitedNodes;
        sort($bfsNodes);

        if ($dfsNodes !== $bfsNodes) {
            return false;
        }

        $expectedHash = hash('sha256', implode('|', $dfsNodes));

        return hash_equals($expectedHash, $result->certificateHash);
    }
}
