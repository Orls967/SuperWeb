<?php

declare(strict_types=1);

namespace Modules\Hospital\Contracts;

use Modules\Hospital\Domain\Models\BpjsReferralBridge;
use Modules\Hospital\Domain\Models\EPharmacyOrder;
use Modules\Hospital\Domain\Models\MedicineCourierDelivery;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\TeleConsult;

/**
 * Kontrak G7-K30: Patient Referral & E-Pharmacy Interoperability Booker.
 */
interface PatientReferralBooker
{
    /**
     * Memesan sesi konsultasi jarak jauh / telemedicine dengan triase klinis awal.
     *
     * @param array{
     *     patient_id: int,
     *     doctor_id: int,
     *     channel_type?: string,
     *     triage_category?: string,
     *     chief_complaint: string,
     *     clinical_notes?: string|null
     * } $data
     */
    public function bookTeleconsultation(array $data): TeleConsult;

    /**
     * Menerbitkan e-resep digital tervalidasi keselamatan klinis dan bertanda tangan digital dokter.
     *
     * @param array{
     *     tele_consult_id: int,
     *     patient_id: int,
     *     pharmacy_branch_id: int,
     *     drug_code: string,
     *     drug_name: string,
     *     quantity: int,
     *     total_price_idr: int,
     *     drug_classification?: string,
     *     doctor_sip_number: string,
     *     is_chronic_subscription?: bool
     * } $data
     */
    public function issueElectronicPrescription(array $data): EPharmacyOrder;

    /**
     * Menemukan dan merutekan resep ke cabang apotek optimal berdasarkan geospasial dan stok FEFO.
     */
    public function routePrescriptionToPharmacy(
        string $drugCode,
        int $quantity,
        float $patientLatitude,
        float $patientLongitude,
        float $maxRadiusKm = 25.0
    ): PharmacyBranch;

    /**
     * Memberangkatkan kurir pengantaran obat dengan rantai dingin (cold-chain) dan audit e-POD.
     *
     * @param array{
     *     order_id: int,
     *     courier_service?: string,
     *     courier_name?: string,
     *     courier_phone?: string,
     *     recipient_name: string,
     *     recipient_phone: string,
     *     destination_address: string,
     *     destination_latitude?: float|null,
     *     destination_longitude?: float|null,
     *     requires_cold_chain?: bool,
     *     min_temp_c?: float,
     *     max_temp_c?: float,
     *     initial_temp_c?: float
     * } $data
     */
    public function dispatchMedicineDelivery(array $data): MedicineCourierDelivery;

    /**
     * Menjembatani rujukan berjenjang pasien ke sistem SISRUTE / V-Claim BPJS.
     *
     * @param array{
     *     patient_id: int,
     *     tele_consult_id?: int|null,
     *     encounter_id?: int|null,
     *     referral_direction?: string,
     *     referral_type?: string,
     *     origin_faskes_code: string,
     *     origin_faskes_name: string,
     *     destination_faskes_code: string,
     *     destination_faskes_name: string,
     *     diagnosis_icd10: string,
     *     clinical_summary: string,
     *     transportation_type?: string,
     *     target_polyclinic_or_ward: string
     * } $data
     */
    public function bridgeBpjsReferral(array $data): BpjsReferralBridge;

    /**
     * Memesan kamar rawat inap / bed reservation terintegrasi hasil eskalasi telemedis.
     *
     * @return array{
     *     booking_reference: string,
     *     patient_id: int,
     *     bed_id: int,
     *     bed_code: string,
     *     ward_name: string,
     *     encounter_id: int,
     *     required_escrow_deposit_idr: int,
     *     status: string
     * }
     */
    public function bookInpatientFromReferral(
        int $teleConsultId,
        string $targetWardClass,
        string $primaryDiagnosisIcd10
    ): array;
}
