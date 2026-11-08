# Pedoman Pengujian & Verifikasi Otomatis: Ekspor Akuakultur & Blue ESG (Fase 171)

Dokumen ini mendefinisikan strategi pengujian otomatis (*automated testing strategy*), cakupan pengujian (*test coverage*), panduan penulisan tes unit dan penerimaan (*acceptance test*), serta metodologi pengujian sabotase (*sabotage audit fixture*) untuk modul `Marine` pada Fase 171.

---

## 1. Strategi Piramida Pengujian Modul Marine

Pengujian modul `Marine` dirancang berjenjang dengan arsitektur piramida pengujian terotomatisasi:

```
                  ┌────────────────────────┐
                  │    SABOTASE LEDGER     │  (Audit Korupsi Rantai Hash)
                  │       (mar:audit)      │
                  ├────────────────────────┤
                  │    ACCEPTANCE TESTS    │  (Alur End-to-End, HTTP, RBAC)
                  │  (AquacultureExport)   │
                  ├────────────────────────┤
                  │   INTEGRATION TESTS    │  (Saga Ekspor & Arbitrase Lab)
                  │ (TradeFinance/Logistics)│
                  ├────────────────────────┤
                  │       UNIT TESTS       │  (5 Algoritma A15-A19, DTOs,
                  │  (Domain & State Mach) │   State Machines, Queries)
                  └────────────────────────┘
```

---

## 2. Cakupan Pengujian Unit (Unit Test Suite)

Daftar suite pengujian unit yang melindungi invarian domain logika bisnis modul:

| Kelas Pengujian Unit | File Sumber Target | Invarian Kunci yang Diverifikasi |
|---|---|---|
| `AquacultureMassBalanceTest` | `AquacultureMassBalanceVerifier` | Batasan rendemen HLSO $\le 66.5\%$; penolakan rendemen abnormal surplus $> 100\%$; proteksi pembagian dengan nol. |
| `FeedConversionRatioTest` | `FeedConversionRatioOptimizer` | Rasio eFCR dan bFCR matematis; penyesuaian penurunan jatah pakan saat DO drop; penghentian pakan saat hipoksia kritis ($DO < 2.5\text{ mg/L}$). |
| `SeafoodLotMerkleProofTest` | `SeafoodLotMerkleProofGenerator` | Rekonstruksi pohon biner SHA-256 kanonikal 8 daun; pembuktian inklusi daun valid; penolakan daun palsu/tampered. |
| `BlueCarbonCreditEstimatorTest` | `BlueCarbonCreditEstimator` | Persamaan allometrik biomassa tegakan mangrove Rhizophora ($AGB = 0.128 \times DBH^{2.58}$); rasio akar $BGB = 0.49 \times AGB$; potongan buffer non-permanence $15\%$. |
| `ExportAssayToleranceTest` | `ExportAssayToleranceChecker` | Kepatuhan multi-yurisdiksi (FDA, EU, Jepang); zero tolerance kloramfenikol ($< 0.3\text{ ppb}$); toleransi susut defrost $\le 4.5\%$. |
| `AquacultureStateMachineTest` | `AquacultureBatchStateMachine`, `ExportShipmentStateMachine` | Transisi 8 status legal; pencegahan transisi lompat (*illegal transition exception*); transisi pembatalan karantina. |
| `AquacultureExportExecutionSagaTest`| `AquacultureExportExecutionSaga` | Alur transaksi saga multi-tahap; kompensasi pembatalan otomatis saat salah satu tahap gagal (misal assay tercemar). |
| `AssayDisputeArbitrationSagaTest` | `AssayDisputeArbitrationSaga` | Penguncian escrow sengketa; pelepasan dana ke eksportir bila lab independen bersih; refund ke pembeli bila tercemar. |
| `BiosecurityAndCarbonOffsettingTest` | `AquacultureBiosecurityProtocolService`, `SilvofisheryCarbonOffsetLedgerService` | Kesiapan tebar biosekuriti; perhitungan rasio FIFO pakan pelet; insetting pensiun kredit karbon. |
| `BuyerProcurementAndDocumentationTest`| `ExportDocumentationService`, `BuyerProcurementPortalService` | Penerbitan sertifikat karantina SPS, Certificate of Origin, dan verifikasi segel digital paspor lot tamper-evident. |

---

## 3. Cakupan Pengujian Penerimaan (Acceptance Test Suite)

### 3.1 `AquacultureExportAndTraceabilityAcceptanceTest`
Menguji siklus hidup lengkap (*complete end-to-end lifecycle*) ekspor komoditas maritim:
1. Penebaran benih udang vaname bersertifikat SPF di petak tambak Delta.
2. Ingesti telemetri kualitas air periodik dari probe sensor IoT (DO, pH, suhu, salinitas).
3. Pemantauan pemberian pakan pelet berkelanjutan berbasis rasio FCR optimal.
4. Pelaksanaan panen biomassa udang dengan pendinginan bubur es (*slurry ice*).
5. Pemrosesan pabrik (HLSO), pembekuan IQF CCP2, dan uji laboratorium residu antibiotik.
6. Pembuatan pohon Merkle silsilah lot dan registrasi paspor publik.
7. Penguncian escrow petambak via kontrak `TradeFinance` (G7-K11).
8. Pemuatan kontainer reefer pendingin dan penerbitan dokumen pabean ekspor.
9. Pelayaran kontainer dan pelepasan escrow petani secara berkeadilan.
10. Pencatatan serapan karbon biru mangrove dan penerbitan sertifikat uji tuntas EUDR.

### 3.2 `AquacultureApiControllerTest`
Memverifikasi integrasi HTTP controller, validasi parameter request, respons kode status, dan otorisasi kontrol peran RBAC:
- `POST /marine/batches/sample`: Sampling tambak & kalkulasi FCR dinamis.
- `POST /marine/export/health-certificate`: Evaluasi uji assay lab & penerbitan sertifikat kesehatan karantina ekspor.
- `POST /marine/export/dispatch`: Validasi pengapalan kontainer reefer & inisiasi saga ekspor.

---

## 4. Pengujian Sabotase Integritas Dua Sumber (`mar:audit`)

Untuk memastikan bahwa sistem audit mendeteksi segala bentuk kecurangan data atau manipulasi basis data di luar aplikasi, pengujian audit mengimplementasikan skenario sabotase (*intentional tampering fixture*):

```php
public function test_passes_marine_audit_on_clean_state_and_detects_sabotage(): void
{
    // 1. Kondisi Bersih: mar:audit harus exit code 0
    $exitCodeClean = Artisan::call('mar:audit');
    $this->assertEquals(0, $exitCodeClean, 'Audit kondisi bersih wajib sukses dengan exit code 0');

    // 2. Sabotase Data: Manipulasi nilai saldo entry ledger secara paksa di DB
    DB::table('mar_aquaculture_ledger_entries')
        ->where('id', 1)
        ->update(['amount_minor_cents' => 9999999999]);

    // 3. Deteksi Sabotase: mar:audit wajib mendeteksi anomali hash chain dan exit code 1
    $exitCodeTampered = Artisan::call('mar:audit');
    $this->assertEquals(1, $exitCodeTampered, 'Audit wajib mendeteksi sabotase rantai hash dan keluar dengan exit code 1');
}
```

---

## 5. Menjalankan Suite Pengujian Mandiri

```bash
# Menjalankan seluruh pengujian unit marikultur
php artisan test tests/Unit/Marine

# Menjalankan pengujian penerimaan ekspor marikultur
php artisan test tests/Acceptance/Marine

# Menjalankan verifikasi kepatuhan arsitektur
php artisan test tests/Architecture/RouteAuthorizationMatrixTest.php
php artisan test tests/Architecture/SchemaIdentifierLengthTest.php
php artisan test tests/Architecture/MysqlSchemaCompatibilityTest.php
php artisan test tests/Architecture/OpenApiContractCompletenessTest.php
php artisan test tests/Architecture/ArchScanBaselineTest.php

# Menjalankan pemindaian arsitektur ketat (A1-A13)
php artisan arch:scan
```
