<?php

declare(strict_types=1);

namespace Modules\Hospital\Contracts;

interface EncounterBillReader
{
    /**
     * Membaca tagihan dan eligibilitas pasien dari encounter RS untuk proses cashless insurance.
     *
     * @return array{
     *     encounter_ref: string,
     *     patient_party_id: string,
     *     hospital_party_id: string,
     *     admission_date: string,
     *     discharge_date: ?string,
     *     diagnosis_icd10: string,
     *     room_tier: string,
     *     room_daily_cost_idr: int,
     *     treatment_cost_idr: int,
     *     medication_cost_idr: int,
     *     total_billed_idr: int,
     *     is_eligible_for_cashless: bool
     * }
     */
    public function readEncounterBill(string $encounterRef): array;
}

