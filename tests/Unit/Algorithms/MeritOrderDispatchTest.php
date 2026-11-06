<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Energy\MeritOrderDispatch;
use Modules\Shared\Domain\Algorithms\Energy\MeritOrderDispatchCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Energy\MeritOrderDispatchResult;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

it('matches prompt vector for E2 Energy\MeritOrderDispatch under normal and light load', function (): void {
    $dispatcher = new MeritOrderDispatch;

    // Vektor (biaya ribu Rp/MWh):
    // U1 batubara 400 MW @ 600 (min 200, wajib-jalan),
    // U2 gas 300 @ 900 (min 100),
    // U3 surya tersedia 150 @ 0,
    // U4 peaker 250 @ 1.800 (min 50).
    $units = [
        ['id' => 'U1', 'capacity_mw' => 400, 'min_stable_mw' => 200, 'marginal_cost' => 600, 'is_must_run' => true],
        ['id' => 'U2', 'capacity_mw' => 300, 'min_stable_mw' => 100, 'marginal_cost' => 900, 'is_must_run' => false],
        ['id' => 'U3', 'capacity_mw' => 150, 'available_capacity_mw' => 150, 'min_stable_mw' => 0, 'marginal_cost' => 0, 'is_must_run' => false],
        ['id' => 'U4', 'capacity_mw' => 250, 'min_stable_mw' => 50, 'marginal_cost' => 1800, 'is_must_run' => false],
    ];

    // Kasus 1: Beban 950 → surya 150, U1 400, U2 300, U4 100; biaya 690.000.
    $res950 = $dispatcher->dispatch($units, 950);
    $map950 = [];
    foreach ($res950->allocations as $a) {
        $map950[$a['id']] = $a['dispatched_mw'];
    }

    expect($map950['U3'])->toBe(150)
        ->and($map950['U1'])->toBe(400)
        ->and($map950['U2'])->toBe(300)
        ->and($map950['U4'])->toBe(100)
        ->and($res950->totalCostThousandIdr)->toBe(690000);

    // Kasus 2: Beban 300 → U1 200 (min), surya 100, curtail surya 50; U2, U4 mati.
    $res300 = $dispatcher->dispatch($units, 300);
    $map300 = [];
    foreach ($res300->allocations as $a) {
        $map300[$a['id']] = $a['dispatched_mw'];
    }

    expect($map300['U1'])->toBe(200)
        ->and($map300['U3'])->toBe(100)
        ->and($map300['U2'])->toBe(0)
        ->and($map300['U4'])->toBe(0)
        ->and($res300->totalCurtailedMw)->toBe(50);
});

it('throws InvalidCertificateException when E2 MeritOrderDispatch certificate violates merit order', function (): void {
    $verifier = new MeritOrderDispatchCertificateVerifier;

    $corruptedResult = new MeritOrderDispatchResult(
        loadMw: 300,
        totalDispatchedMw: 300,
        totalCurtailedMw: 0,
        totalCostThousandIdr: 270000,
        allocations: [
            // U3 cheaper (cost 0) has spare 50 MW, but U2 expensive (cost 900) is dispatched 100 MW above min
            ['id' => 'U1', 'capacity_mw' => 400, 'available_capacity_mw' => 400, 'min_stable_mw' => 200, 'marginal_cost' => 600, 'is_must_run' => true, 'dispatched_mw' => 200, 'curtailed_mw' => 0, 'cost_thousand_idr' => 120000],
            ['id' => 'U3', 'capacity_mw' => 150, 'available_capacity_mw' => 150, 'min_stable_mw' => 0, 'marginal_cost' => 0, 'is_must_run' => false, 'dispatched_mw' => 0, 'curtailed_mw' => 0, 'cost_thousand_idr' => 0],
            ['id' => 'U2', 'capacity_mw' => 300, 'available_capacity_mw' => 300, 'min_stable_mw' => 0, 'marginal_cost' => 900, 'is_must_run' => false, 'dispatched_mw' => 100, 'curtailed_mw' => 0, 'cost_thousand_idr' => 90000],
        ],
        certificate: [
            'load_mw' => 300,
            'total_dispatched_mw' => 300,
            'total_curtailed_mw' => 0,
            'total_cost_thousand_idr' => 210000,
            'digest' => 'corrupted',
        ]
    );

    expect(fn () => $verifier->verify($corruptedResult))
        ->toThrow(InvalidCertificateException::class);
});
