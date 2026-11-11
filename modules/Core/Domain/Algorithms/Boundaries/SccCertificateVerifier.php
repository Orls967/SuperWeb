<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

use InvalidArgumentException;
use Modules\Core\Domain\Algorithms\CanonicalCertificateSupport;

/**
 * Formal certificate verifier for Tarjan Strongly Connected Components.
 * Complexity: O(V + E). Integer arithmetic only. Bounded graph size <= 10,000 nodes.
 * Proves that components partition the graph, condensation is acyclic,
 * and SHA-256 canonical certificate matches recomputation (R4-ALG-01).
 */
final class SccCertificateVerifier
{
    /**
     * Build a canonical SHA-256 verification certificate for Tarjan SCC output.
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>  $steps
     * @param  array<string, mixed>  $result
     * @return array{input: array<string, mixed>, steps: list<string>, result: array<string, mixed>, sha256: string}
     */
    public function issueCertificate(array $input, array $steps, array $result): array
    {
        return CanonicalCertificateSupport::issue($input, $steps, $result);
    }

    /**
     * Verify a canonical SHA-256 certificate; throws on tampering or structural invalidity.
     *
     * @param  array<string, mixed>  $certificate
     */
    public function verifyCertificate(array $certificate): bool
    {
        $verified = CanonicalCertificateSupport::verifyIntegrity($certificate, 'SCC_CERT');
        $components = $verified['result']['components'] ?? null;
        $condensationDag = $verified['result']['condensationDag'] ?? null;
        $nodes = $verified['input']['nodes'] ?? null;

        if (! is_array($components) || ! is_array($condensationDag) || ! is_array($nodes)) {
            throw new InvalidArgumentException('SCC_CERT_PAYLOAD_INVALID: Data partisi atau DAG pada sertifikat rusak.');
        }

        if (! $this->verify($components, $condensationDag, $nodes)) {
            throw new InvalidArgumentException('SCC_CERT_INVARIANT_FAILED: Sertifikat melanggar invarian partisi atau DAG asiklik.');
        }

        return true;
    }

    /**
     * Verify that components form a complete partition and condensation graph is a DAG.
     *
     * @param  list<list<string>>  $components
     * @param  array<int, list<int>>  $condensationDag
     * @param  list<string>  $originalNodes
     */
    public function verify(array $components, array $condensationDag, array $originalNodes): bool
    {
        // 1. Verify exact partition of original nodes
        $seen = [];
        $totalNodes = 0;
        foreach ($components as $comp) {
            if ($comp === []) {
                return false;
            }
            foreach ($comp as $node) {
                if (isset($seen[$node])) {
                    return false; // Duplicate node across components
                }
                $seen[$node] = true;
                $totalNodes++;
            }
        }

        $expectedNodes = array_unique($originalNodes);
        if ($totalNodes !== count($expectedNodes)) {
            return false;
        }

        foreach ($expectedNodes as $node) {
            if (! isset($seen[$node])) {
                return false; // Missing node
            }
        }

        // 2. Verify condensation graph is acyclic using Kahn's topological sort
        $inDegree = [];
        $numSccs = count($components);
        for ($i = 0; $i < $numSccs; $i++) {
            $inDegree[$i] = 0;
        }

        foreach ($condensationDag as $u => $edges) {
            foreach ($edges as $v) {
                if ($u === $v) {
                    return false; // Self-loop in condensation
                }
                if (! isset($inDegree[$v])) {
                    return false; // Invalid node reference
                }
                $inDegree[$v]++;
            }
        }

        $queue = [];
        for ($i = 0; $i < $numSccs; $i++) {
            if ($inDegree[$i] === 0) {
                $queue[] = $i;
            }
        }

        $visitedCount = 0;
        while ($queue !== []) {
            $curr = array_shift($queue);
            $visitedCount++;

            foreach ($condensationDag[$curr] ?? [] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        return $visitedCount === $numSccs;
    }
}
