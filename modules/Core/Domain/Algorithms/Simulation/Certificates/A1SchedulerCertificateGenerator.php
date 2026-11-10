<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Simulation\Certificates;

use Modules\Core\Domain\Algorithms\Simulation\DiscreteEventScheduler;
use Modules\Core\Domain\Algorithms\Simulation\ValueObjects\DiscreteEvent;

/**
 * Generator sertifikat matematis deterministik untuk penjadwal diskret A1.
 */
final class A1SchedulerCertificateGenerator
{
    /**
     * @param  array<int, array{id: string, second: int, priority: int, seq: int}>  $eventInputs
     * @return array{
     *     algorithm: string,
     *     input_count: int,
     *     execution_order: array<int, string>,
     *     certificate_hash: string
     * }
     */
    public function generateCertificate(array $eventInputs): array
    {
        $scheduler = new DiscreteEventScheduler;

        foreach ($eventInputs as $in) {
            $scheduler->schedule(new DiscreteEvent(
                id: $in['id'],
                virtualSecond: $in['second'],
                priority: $in['priority'],
                sequence: $in['seq'],
            ));
        }

        $order = [];
        while (! $scheduler->isEmpty()) {
            $ev = $scheduler->popNext();
            if ($ev !== null) {
                $order[] = $ev->id;
            }
        }

        $canonical = json_encode($order, JSON_UNESCAPED_SLASHES);
        $certHash = hash('sha256', "A1-CERT:{$canonical}");

        return [
            'algorithm' => 'A1_DiscreteEventScheduler',
            'input_count' => count($eventInputs),
            'execution_order' => $order,
            'certificate_hash' => $certHash,
        ];
    }
}
