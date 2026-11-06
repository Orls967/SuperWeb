<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Utility\BlackStartSynchronizationCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Utility\BlackStartSynchronizationSequencer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BlackStartSynchronizationSequencerTest extends TestCase
{
    public function test_black_start_sequence_planning_and_verification(): void
    {
        $sequencer = new BlackStartSynchronizationSequencer;
        $verifier = new BlackStartSynchronizationCertificateVerifier;

        $components = [
            [
                'code' => 'BUS_PRIMARY',
                'name' => 'Busbar Primer Tegangan Menengah 20kV',
                'dependencies' => ['CRANK_GENSET'],
                'step_duration_seconds' => 60,
                'inrush_current_amp' => 120,
            ],
            [
                'code' => 'CRANK_GENSET',
                'name' => 'Diesel Cranking Generator Mandiri',
                'dependencies' => [],
                'step_duration_seconds' => 180,
                'inrush_current_amp' => 300,
            ],
            [
                'code' => 'FEEDER_HOSPITAL',
                'name' => 'Penyulang Beban Kritis RS',
                'dependencies' => ['TRANSFORMER_TX1'],
                'step_duration_seconds' => 45,
                'inrush_current_amp' => 150,
            ],
            [
                'code' => 'TRANSFORMER_TX1',
                'name' => 'Transformator Daya Step-Down 20kV/400V',
                'dependencies' => ['BUS_PRIMARY'],
                'step_duration_seconds' => 30,
                'inrush_current_amp' => 200,
            ],
            [
                'code' => 'GRID_SYNC_BREAKER',
                'name' => 'Pemutus Sinkronisasi Grid Nasional PLN',
                'dependencies' => ['FEEDER_HOSPITAL'],
                'step_duration_seconds' => 90,
                'inrush_current_amp' => 50,
            ],
        ];

        $result = $sequencer->planSequence('MG-CIKARANG-01', $components);

        $this->assertSame('MG-CIKARANG-01', $result->microgridCode);
        $this->assertSame(5, $result->totalComponentsCount);
        $this->assertSame(405, $result->totalEstimatedSeconds); // 180 + 60 + 30 + 45 + 90 = 405 detik

        // Urutan langkah harus Kahn topological order:
        // 1. CRANK_GENSET -> 2. BUS_PRIMARY -> 3. TRANSFORMER_TX1 -> 4. FEEDER_HOSPITAL -> 5. GRID_SYNC_BREAKER
        $sequenceCodes = array_column($result->sequence, 'component_code');
        $this->assertSame([
            'CRANK_GENSET',
            'BUS_PRIMARY',
            'TRANSFORMER_TX1',
            'FEEDER_HOSPITAL',
            'GRID_SYNC_BREAKER',
        ], $sequenceCodes);

        $this->assertTrue($verifier->verify($result));
    }

    public function test_cyclic_dependency_in_black_start_throws_exception(): void
    {
        $sequencer = new BlackStartSynchronizationSequencer;

        $cyclicalComponents = [
            ['code' => 'A', 'name' => 'Comp A', 'dependencies' => ['B'], 'step_duration_seconds' => 10, 'inrush_current_amp' => 10],
            ['code' => 'B', 'name' => 'Comp B', 'dependencies' => ['A'], 'step_duration_seconds' => 10, 'inrush_current_amp' => 10],
        ];

        $this->expectException(RuntimeException::class);
        $sequencer->planSequence('MG-CYCLIC-TEST', $cyclicalComponents);
    }
}
