<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Graph;

use Closure;
use SplQueue;

class GenealogyTraversal
{
    private ?GenealogyEdgeProviderInterface $edgeProvider = null;

    /**
     * @param  (Closure(string, string, string): list<array{type: string, id: string}>)|null  $customLoader
     */
    public function __construct(
        ?GenealogyEdgeProviderInterface $edgeProvider = null,
        private ?Closure $customLoader = null,
    ) {
        if ($edgeProvider !== null) {
            $this->edgeProvider = $edgeProvider;
        } elseif (function_exists('app') && app()->bound(GenealogyEdgeProviderInterface::class)) {
            $this->edgeProvider = app(GenealogyEdgeProviderInterface::class);
        }
    }

    public function traverseForward(string $rootType, string $rootId): GenealogyTraversalResult
    {
        return $this->traverse($rootType, $rootId, 'FORWARD');
    }

    public function traverseBackward(string $rootType, string $rootId): GenealogyTraversalResult
    {
        return $this->traverse($rootType, $rootId, 'BACKWARD');
    }

    public function traverse(string $rootType, string $rootId, string $direction): GenealogyTraversalResult
    {
        $edgeLoader = $this->resolveEdgeLoader();

        $visited = [];
        $queue = new SplQueue;

        $rootKey = "{$rootType}:{$rootId}";
        $visited[$rootKey] = true;
        $queue->enqueue(['type' => $rootType, 'id' => $rootId]);

        while (! $queue->isEmpty()) {
            /** @var array{type: string, id: string} $current */
            $current = $queue->dequeue();
            $neighbors = $edgeLoader($current['type'], $current['id'], $direction);

            foreach ($neighbors as $neighbor) {
                $nType = (string) $neighbor['type'];
                $nId = (string) $neighbor['id'];
                $nKey = "{$nType}:{$nId}";
                if (! isset($visited[$nKey])) {
                    $visited[$nKey] = true;
                    $queue->enqueue(['type' => $nType, 'id' => $nId]);
                }
            }
        }

        $visitedNodes = array_keys($visited);
        sort($visitedNodes);

        $certificateHash = hash('sha256', implode('|', $visitedNodes));

        // Verifikasi sertifikat penutupan menggunakan DFS oracle
        $verifier = new GenealogyTraversalCertificateVerifier;
        $verified = $verifier->verify(
            $rootType,
            $rootId,
            $direction,
            new GenealogyTraversalResult(
                rootType: $rootType,
                rootId: $rootId,
                direction: $direction,
                visitedNodes: $visitedNodes,
                certificateHash: $certificateHash,
                certificateVerified: true
            ),
            $edgeLoader
        );

        return new GenealogyTraversalResult(
            rootType: $rootType,
            rootId: $rootId,
            direction: $direction,
            visitedNodes: $visitedNodes,
            certificateHash: $certificateHash,
            certificateVerified: $verified
        );
    }

    private function resolveEdgeLoader(): Closure
    {
        if ($this->customLoader !== null) {
            return $this->customLoader;
        }

        if ($this->edgeProvider !== null) {
            $provider = $this->edgeProvider;

            return function (string $type, string $id, string $direction) use ($provider): array {
                if ($direction === 'FORWARD') {
                    return $provider->getOutgoingEdges($type, $id);
                }

                return $provider->getIncomingEdges($type, $id);
            };
        }

        return fn (string $type, string $id, string $direction): array => [];
    }
}
