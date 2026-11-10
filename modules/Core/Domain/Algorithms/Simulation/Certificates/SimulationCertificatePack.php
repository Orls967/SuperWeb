<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Simulation\Certificates;

/**
 * Paket bundel sertifikat algoritma deterministik penuh (A1 + A2 + A3).
 * Berfungsi sebagai segel kriptografis independen atas kesahihan kernel simulasi.
 */
final readonly class SimulationCertificatePack
{
    /**
     * @param  array{algorithm: string, input_count: int, execution_order: array<int, string>, certificate_hash: string}  $a1Cert
     * @param  array{algorithm: string, node_count: int, topological_order: array<int, string>, certificate_hash: string}  $a2Cert
     * @param  array{algorithm: string, base_seed: int, derived_streams: array<string, int>, final_chain_hash: string, certificate_hash: string}  $a3Cert
     */
    public function __construct(
        public array $a1Cert,
        public array $a2Cert,
        public array $a3Cert,
        public string $bundleSignature,
    ) {}

    /**
     * @param  array<int, array{id: string, second: int, priority: int, seq: int}>  $a1Inputs
     * @param  array<string, array<string>>  $a2Graph
     * @param  array<int, string>  $a3Streams
     * @param  array<int, array<string, mixed>>  $a3Summaries
     */
    public static function compile(
        array $a1Inputs,
        array $a2Graph,
        int $a3Seed,
        array $a3Streams,
        array $a3Summaries,
    ): self {
        $gen1 = new A1SchedulerCertificateGenerator;
        $gen2 = new A2TopologicalCertificateGenerator;
        $gen3 = new A3StreamRngCertificateGenerator;

        $c1 = $gen1->generateCertificate($a1Inputs);
        $c2 = $gen2->generateCertificate($a2Graph);
        $c3 = $gen3->generateCertificate($a3Seed, $a3Streams, $a3Summaries);

        $sig = hash('sha256', "{$c1['certificate_hash']}|{$c2['certificate_hash']}|{$c3['certificate_hash']}");

        return new self($c1, $c2, $c3, $sig);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'bundle_signature' => $this->bundleSignature,
            'a1' => $this->a1Cert,
            'a2' => $this->a2Cert,
            'a3' => $this->a3Cert,
        ];
    }
}
