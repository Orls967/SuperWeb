<?php

declare(strict_types=1);

use App\Quality\TestHygiene\TestHygieneScan;

/*
| PROGRESS R0.11 (K-27, KONSEP §A14.7): test hygiene T1–T4 with a ratchet baseline —
| trivially true assertions, ledger accounts created by tests, skips without a
| BLOCKERS reference, and modules whose routes are never requested in a test.
*/

it('keeps tests within the hygiene ratchet baseline', function (): void {
    assertFingerprintBaseline(
        'tests/Architecture/baselines/test-hygiene.json',
        TestHygieneScan::scanProject(dirname(__DIR__, 2)),
        'Baseline ratchet higiene test T1–T4 (PROGRESS R0.11, KONSEP §A14.7). Hanya boleh turun; target 0 di R5.5 (T2 di R1.3).',
    );
});
