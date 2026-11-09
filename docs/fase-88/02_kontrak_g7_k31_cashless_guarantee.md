# Spesifikasi Teknis Kontrak G7-K31: CashlessGuaranteeRequester

## 1. Ikhtisar & Landasan Desain Kontrak

Dalam arsitektur *Service-Oriented Architecture* (SOA) rumah sakit, modul `Insurance` bertindak sebagai *gateway* penjaminan eksternal. Kontrak **G7-K31** (`CashlessGuaranteeRequester`) adalah kontrak interoperabilitas standar yang menghubungkan modul penagihan rumah sakit (`Hospital`) dengan pihak asuransi komersial, perusahaan penjamin, atau *Third-Party Administrator* (TPA) seperti AdMedika, Fullerton Health, BCA Life, Prudential, dan Allianz.

Pemisahan tanggung jawab arsitektural:
- Modul `Hospital` hanya mengetahui antarmuka `Modules\Insurance\Contracts\CashlessGuaranteeRequester`.
- Modul `Insurance` menyediakan implementasi konkret `CashlessGuaranteeRequesterService` yang menangani enkripsi mTLS, penandatanganan digital HMAC-SHA256, format ISO-20022/FHIR, serta logika *retry backoff*.

```
+--------------------------+                      +-----------------------------+
|     Modul Hospital       |                      |       Modul Insurance       |
| (Billing & Folio Engine) |                      | (TPA Interoperability API)  |
+--------------------------+                      +-----------------------------+
             |                                                   |
             |-------- 1. requestGuarantee(RequestDTO) --------->|
             |                                                   |---- Hubungi TPA via Gateway
             |                                                   |<--- Respons Plafon & Diskon
             |<------- 2. Kembalikan GuaranteeResponseDTO -------|
             |                                                   |
             |-------- 3. updateCoverage(Ref, Tambahan) -------->|
             |                                                   |---- Request Tambah Limit
             |<------- 4. Interim GL Disetujui ------------------|
             |                                                   |
             |-------- 5. finalizeDischarge(Ref, TagihanAkhir) ->|
             |                                                   |---- Verifikasi Berkas Klaim
             |<------- 6. Final GL Terbit (Approved) ------------|
```

---

## 2. Definisi Antarmuka PHP & Tipe Return

Kontrak ini dideklarasikan pada direktori `modules/Insurance/Contracts/CashlessGuaranteeRequester.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Insurance\Contracts;

use Modules\Insurance\DTOs\GuaranteeRequestDTO;
use Modules\Insurance\DTOs\GuaranteeResponseDTO;

interface CashlessGuaranteeRequester
{
    /**
     * Mengajukan permohonan Surat Jaminan Awal (Initial Guarantee Letter)
     * saat pasien mendaftar di admisi IGD atau Rawat Inap.
     *
     * @param GuaranteeRequestDTO $request Data lengkap polis dan estimasi tindakan medis
     * @return GuaranteeResponseDTO Keputusan penjaminan beserta limit plafon
     */
    public function requestGuarantee(GuaranteeRequestDTO $request): GuaranteeResponseDTO;

    /**
     * Meminta penambahan plafon penjaminan (Interim Guarantee Extension)
     * apabila pasien membutuhkan tindakan bedah darurat tambahan atau perawatan ICU lanjutan.
     *
     * @param string $guaranteeReference Nomor referensi surat jaminan awal (GL number)
     * @param int $additionalAmountIdr Nominal kenaikan limit yang diajukan (IDR)
     * @return GuaranteeResponseDTO Status penjaminan yang telah diperbarui
     */
    public function updateCoverage(string $guaranteeReference, int $additionalAmountIdr): GuaranteeResponseDTO;

    /**
     * Mengajukan konfirmasi penjaminan akhir (Final Discharge Guarantee Letter)
     * sesaat sebelum pasien dipulangkan, mencakup seluruh rekonsiliasi biaya riil.
     *
     * @param string $guaranteeReference Nomor referensi surat jaminan
     * @param int $finalBillAmountIdr Total tagihan akhir yang diakui koder
     * @return GuaranteeResponseDTO Konfirmasi nominal yang ditanggung dan sisa copay pasien
     */
    public function finalizeDischarge(string $guaranteeReference, int $finalBillAmountIdr): GuaranteeResponseDTO;

    /**
     * Membatalkan permohonan surat jaminan karena pasien batal dirawat
     * atau beralih ke jaminan pembayaran umum/tunai.
     *
     * @param string $guaranteeReference Nomor referensi surat jaminan
     * @param string $reason Alasan pembatalan resmi
     * @return bool Status keberhasilan pembatalan
     */
    public function cancelGuarantee(string $guaranteeReference, string $reason): bool;
}
```

---

## 3. Spesifikasi Payload JSON & Skema Komunikasi

### Permohonan Awal Surat Jaminan (`GuaranteeRequestDTO`)
```json
{
  "request_header": {
    "transmission_id": "TX-20261109-0099812",
    "timestamp": "2026-11-09T08:15:30Z",
    "hospital_code": "RS-INT-001",
    "hospital_branch_name": "RS Siloam Kebon Jeruk"
  },
  "policy_data": {
    "insurer_code": "PRUDENTIAL_ID",
    "policy_number": "POL-PRU-98821734",
    "member_id": "MBR-88192-01",
    "patient_national_id": "3171092801900003",
    "patient_full_name": "Budi Santoso",
    "patient_date_of_birth": "1990-01-28",
    "gender": "MALE"
  },
  "admission_details": {
    "admission_type": "EMERGENCY_INPATIENT",
    "admission_timestamp": "2026-11-09T07:45:00Z",
    "primary_icd10": "I21.0",
    "primary_diagnosis_description": "Acute transmural myocardial infarction of anterior wall",
    "intended_ward_class": "CLASS_1",
    "estimated_cost_idr": 45000000,
    "attending_physician": "dr. Hartono Sp.JP(K)"
  }
}
```

### Jawaban Surat Jaminan Awal (`GuaranteeResponseDTO`)
```json
{
  "response_header": {
    "transmission_id": "TX-20261109-0099812",
    "tpa_response_id": "RESP-TPA-9988112",
    "processed_at": "2026-11-09T08:16:05Z"
  },
  "guarantee_letter": {
    "guarantee_reference": "GL-PRU-202611-000491",
    "status": "APPROVED",
    "approved_limit_idr": 45000000,
    "deductible_remaining_idr": 0,
    "coinsurance_patient_pct": 10.0,
    "pre_existing_condition_clause": "WAIVED",
    "excluded_benefits": [
      "SPECIAL_ORDER_COSMETIC_SURGERY",
      "NON_FORMULARY_VITAMIN_SUPPLEMENTS"
    ],
    "valid_until": "2026-11-19T23:59:59Z",
    "authorization_barcode_hash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
  }
}
```

---

## 4. Penanganan Kegagalan Jaringan & Mekanisme Idempotensi

1. **Jaminan Idempotensi Transaksi**:
   - Setiap transmisi surat jaminan wajib mencantumkan `transmission_id` unik berbasis UUIDv4.
   - Apabila TPA menerima permohonan dengan `transmission_id` yang sama dalam kurun 24 jam, TPA wajib mengembalikan respons identik dari *cache* tanpa memotong limit polis berulang kali.
2. **Exponential Backoff Retry Strategy**:
   - Jika koneksi HTTP ke TPA mengalami *Read Timeout* ($> 15 \text{ detik}$) atau *HTTP 503 Service Unavailable*:
     - Upaya 1: Tunggu 2 detik.
     - Upaya 2: Tunggu 8 detik.
     - Upaya 3: Tunggu 32 detik.
   - Jika ketiga percobaan gagal, episode tagihan dialihkan ke antrian audit rekonsiliasi manual (*Fallback Offline Protocol*).

---

## 5. Resolusi Aturan Q4f pada Service Provider

Untuk mencegah regresi terhadap modul `Insurance` yang telah diperluas oleh kelompok kerja sebelumnya (Fase 177 / P7), berkas `InsuranceServiceProvider.php` wajib mempertahankan kedua *binding* kontrak sebagai *union*:

```php
// File: modules/Insurance/Providers/InsuranceServiceProvider.php

namespace Modules\Insurance\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Insurance\Contracts\CashlessGuaranteeRequester;
use Modules\Insurance\Contracts\ParametricTriggerRecorder;
use Modules\Insurance\Application\Services\CashlessGuaranteeRequesterService;
use Modules\Insurance\Application\Services\ParametricTriggerRecorderService;

class InsuranceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 1. Kontrak G7-K29: Asuransi Parametrik Cuaca/Aviasi (P7)
        $this->app->singleton(
            ParametricTriggerRecorder::class,
            ParametricTriggerRecorderService::class
        );

        // 2. Kontrak G7-K31: Penjaminan Cashless Rumah Sakit (P8)
        $this->app->singleton(
            CashlessGuaranteeRequester::class,
            CashlessGuaranteeRequesterService::class
        );
    }
}
```
Integritas aturan Q4f ini memastikan bahwa pengujian unit modul asuransi pada fase terdahulu tetap berjalan hijau (*100% backward compatible*).
