# Spesifikasi Teknis Kontrak G7-K30: PatientReferralBooker

## 1. Latar Belakang & Peran Kontrak

Kontrak **G7-K30** (`PatientReferralBooker`) adalah kontrak antarmuka interoperabilitas medis standar pada Kelompok Kerja G7 yang bertugas menghubungkan:
1. **Layanan Rawat Jalan & Konsultasi Telemedis** dengan fasilitas jejaring rujukan eksternal (FKTP, Klinik Pratama, Puskesmas, dan Rumah Sakit Mitra).
2. **Mesin Preskripsi Elektronik (E-Pharmacy)** dengan sistem rujukan farmasi terdesentralisasi (*multi-branch pharmacy fulfillment*) dan kurir obat berlisensi.
3. **Gerbang Rujukan Nasional BPJS (SISRUTE & V-Claim)** untuk transfer pasien vertikal dan horizontal antar fasilitas kesehatan tingkat lanjutan.
4. **Alokasi Rawat Inap & Bed Reservation Terintegrasi** (menghubungkan rujukan hasil telemedis langsung ke modul admisi dan penjaminan Fase 87/88).

```
                  +----------------------------------------------+
                  |         KONTRAK G7-K30 INTERFACE             |
                  |           PatientReferralBooker              |
                  +----------------------------------------------+
                                  │             │
                ┌─────────────────┘             └────────────────┐
                ▼                                                ▼
+───────────────────────────────+               +───────────────────────────────+
|      MODUL HOSPITAL (P8)      |               |     JARINGAN EKSTERNAL &      |
|                               |               |     SISTEM BRIDGING           |
| - Telemedicine Triage         |               | - BPJS Kesehatan (SISRUTE)    |
| - CDSE Clinical Safety        |◄─────────────►| - Apotek Mitra Jaringan (Party|
| - FEFO Dispensing Engine      |               | - Ekspedisi Logistik Medis    |
| - Admission & Bed Reservation |               | - RS Jejaring Vertikal/Horiz. |
+───────────────────────────────+               +───────────────────────────────+
```

---

## 2. Definisi Antarmuka Kontrak PHP

Antarmuka didefinisikan secara ketat pada namespace `Modules\Hospital\Contracts\PatientReferralBooker` dengan deklarasi tipe murni (*strict typing*):

```php
<?php

declare(strict_types=1);

namespace Modules\Hospital\Contracts;

use Modules\Hospital\Domain\Models\TeleConsult;
use Modules\Hospital\Domain\Models\ElectronicPrescription;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\MedicineCourierDelivery;
use Modules\Hospital\Domain\Models\BpjsReferralBridge;

interface PatientReferralBooker
{
    /**
     * Memesan sesi konsultasi telemedis dengan triase klinis awal.
     *
     * @param array{
     *     patient_id: int,
     *     doctor_id: int,
     *     scheduled_at: string,
     *     symptoms_summary: string,
     *     triage_urgency: string,
     *     consultation_channel: string
     * } $bookingData
     * @return TeleConsult
     */
    public function bookTeleconsultation(array $bookingData): TeleConsult;

    /**
     * Mengesahkan e-resep digital lengkap dengan tanda tangan kriptografis dokter.
     *
     * @param int $consultationId
     * @param array<int, array{
     *     item_id: int,
     *     item_name: string,
     *     dosage_instructions: string,
     *     quantity: int,
     *     is_controlled_substance: bool
     * }> $items
     * @param string $doctorSipNumber
     * @param string $doctorPrivateKeyPem
     * @return ElectronicPrescription
     */
    public function issueElectronicPrescription(
        int $consultationId,
        array $items,
        string $doctorSipNumber,
        string $doctorPrivateKeyPem
    ): ElectronicPrescription;

    /**
     * Menemukan dan merutekan resep ke cabang apotek terdekat dengan ketersediaan stok FEFO lengkap.
     *
     * @param int $prescriptionId
     * @param float $patientLatitude
     * @param float $patientLongitude
     * @param float $maxRadiusKm
     * @return PharmacyBranch
     */
    public function routePrescriptionToPharmacy(
        int $prescriptionId,
        float $patientLatitude,
        float $patientLongitude,
        float $maxRadiusKm = 25.0
    ): PharmacyBranch;

    /**
     * Memberangkatkan kurir pengiriman obat berantai dingin lengkap dengan nomor segel dan pelacak suhu.
     *
     * @param int $prescriptionId
     * @param int $branchId
     * @param array{
     *     courier_service: string,
     *     recipient_name: string,
     *     recipient_phone: string,
     *     destination_address: string,
     *     requires_cold_chain: bool,
     *     allowed_temp_min_c: float,
     *     allowed_temp_max_c: float
     * } $deliveryData
     * @return MedicineCourierDelivery
     */
    public function dispatchMedicineDelivery(
        int $prescriptionId,
        int $branchId,
        array $deliveryData
    ): MedicineCourierDelivery;

    /**
     * Menjembatani rujukan keluar / masuk ke sistem SISRUTE BPJS Kesehatan.
     *
     * @param array{
     *     patient_id: int,
     *     referral_direction: string, // OUTBOUND, INBOUND
     *     origin_faskes_code: string,
     *     destination_faskes_code: string,
     *     primary_diagnosis_icd10: string,
     *     clinical_summary: string,
     *     referral_reason: string,
     *     target_polyclinic: string
     * } $referralData
     * @return BpjsReferralBridge
     */
    public function bridgeBpjsReferral(array $referralData): BpjsReferralBridge;

    /**
     * Memesan tempat tidur rawat inap (Inpatient Bed Reservation) berdasarkan rujukan klinis telemedis.
     *
     * @param int $consultationId
     * @param string $targetWardClass
     * @param string $primaryDiagnosisIcd10
     * @return array{
     *     booking_reference: string,
     *     bed_id: int,
     *     ward_name: string,
     *     encounter_id: int,
     *     deposit_idr: int
     * }
     */
    public function bookInpatientFromReferral(
        int $consultationId,
        string $targetWardClass,
        string $primaryDiagnosisIcd10
    ): array;
}
```

---

## 3. SLA & Jaminan Kinerja Kontrak

| Operasi Kontrak | Ambang Batas Waktu (SLA) | Toleransi Kesalahan | Mekanisme Fallback |
| :--- | :--- | :--- | :--- |
| `bookTeleconsultation` | < 300 ms | 0% data loss | Antrian reservasi slot dengan *optimistic locking* |
| `issueElectronicPrescription` | < 500 ms (termasuk verifikasi CDS) | 0% bypass keamanan farmasi | Penolakan otomatis bila ada kontraindikasi obat fatal |
| `routePrescriptionToPharmacy` | < 450 ms untuk pencarian 5.000 apotek | 0% kegagalan stok cabang | Fallback sekuensial ke Central Hospital Hub |
| `dispatchMedicineDelivery` | < 600 ms (termasuk alokasi kurir) | Rantai dingin terjaga 2–8°C | Penolakan pengiriman jika kotak pendingin tidak tervalidasi |
| `bridgeBpjsReferral` | < 1.200 ms (bridging HTTP REST) | Simulasi offline mock idempotent | Queue retry berjangka 3 kali dengan *exponential backoff* |
| `bookInpatientFromReferral` | < 350 ms | 0% *overbooking* bed fisik | Lock pesimis baris bed dengan rollback transaksi atomik |

