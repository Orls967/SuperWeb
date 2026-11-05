<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Ambang approval (KONSEP §A3) — dalam rupiah penuh
    |--------------------------------------------------------------------------
    */
    'approval_threshold_idr' => (int) env('INSURANCE_APPROVAL_THRESHOLD_IDR', 50_000_000),

    /*
    |--------------------------------------------------------------------------
    | Reasuransi & kapital (Fase 157)
    |--------------------------------------------------------------------------
    | Angka aktuaria/regulasi TIDAK di sini: semuanya parameter berversi di tabel `ins_parameters` (G7-D1).
    */
    'cession' => [
        'entity_code' => env('INSURANCE_ENTITY_CODE', 'INS'),
        'quarters_per_year' => 4,
    ],

    'cat' => [
        'max_simulation_cells' => 10_000_000,
        'reference_return_periods' => [100, 250],
    ],
];
