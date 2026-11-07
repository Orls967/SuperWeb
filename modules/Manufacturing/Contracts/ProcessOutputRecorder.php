<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Contracts;

/**
 * Kontrak Integrasi Lintas Modul: Mining Smelter -> Manufacturing (G7-K12).
 * Mencatat output pemrosesan peleburan/smelter bijih mineral menjadi produk manufaktur.
 */
interface ProcessOutputRecorder
{
    /**
     * Catat hasil keluaran batch proses peleburan (ferronickel, nickel matte, katoda tembaga).
     *
     * @param array{
     *     batch_code: string,
     *     input_commodity: string,
     *     input_tonnage_kg: int,
     *     output_product_code: string,
     *     output_tonnage_kg: int,
     *     metal_recovery_bps: int,
     *     idempotency_key: string
     * } $data
     * @return array{output_batch_id: string, status: string}
     */
    public function recordProcessOutput(array $data): array;
}
