<?php

declare(strict_types=1);

use App\Quality\Freeze\IntegrationFreeze;

/*
| PROGRESS R0.11 (K-05, KONSEP §A14.6): modules/Integration is frozen. A new domain
| service or table there fails this test; new code belongs in its owner module.
*/

it('keeps modules/Integration frozen except for external adapters and intg_ tables', function (): void {
    assertSetBaseline(
        'tests/Architecture/baselines/integration-freeze.json',
        IntegrationFreeze::currentEntries(dirname(__DIR__, 2)),
        'Pembekuan modules/Integration (PROGRESS R0.11, KONSEP §A14.6). File baru hanya di Adapters/, Webhooks/, Edi/, Http/Controllers/Api/, tests/; tabel baru hanya intg_. Daftar ini hanya boleh menyusut (R4.4 memindahkan isinya ke modul pemilik).',
    );
});
