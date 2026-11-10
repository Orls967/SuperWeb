<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Simulation\Certificates;

use Modules\Core\Domain\Algorithms\Simulation\SubscriberTopologicalOrder;

/**
 * Generator sertifikat urutan topologis Kahn A2.
 */
final class A2TopologicalCertificateGenerator
{
    /**
     * @param  array<string, array<string>>  $dependencyGraph
     * @return array{
     *     algorithm: string,
     *     node_count: int,
     *     topological_order: array<int, string>,
     *     certificate_hash: string
     * }
     */
    public function generateCertificate(array $dependencyGraph): array
    {
        $sorter = new SubscriberTopologicalOrder;
        $order = $sorter->sort($dependencyGraph);

        $canonical = json_encode($order, JSON_UNESCAPED_SLASHES);
        $certHash = hash('sha256', "A2-CERT:{$canonical}");

        return [
            'algorithm' => 'A2_SubscriberTopologicalOrder',
            'node_count' => count($order),
            'topological_order' => $order,
            'certificate_hash' => $certHash,
        ];
    }
}
