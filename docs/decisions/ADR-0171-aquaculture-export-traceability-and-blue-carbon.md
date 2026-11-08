# Architectural Decision Record (ADR-0171)
## Fase 171 — Ekspor Akuakultur, Ketelusuran Kriptografis Seafood & Blue ESG

- **Status:** Diterima & Diimplementasikan (Accepted)
- **Tanggal:** 2026-11-08
- **Pelaksana:** Pelaksana [G7-P7] — Aviasi & Maritim
- **Kode Keputusan:** `G7-D46` (Standar Merkle Traceability), `G7-D47` (Standar Blue Carbon Verra VM0033), `G7-K28` (VerifiedHarvestReader Interface)

---

## 1. Konteks & Masalah Arsitektur (Context & Problem Statement)

Industri akuakultur dan marikultur Indonesia menghadapi disrupsi persyaratan akses pasar ekspor global:
1. **Regulasi US FDA FSMA Rule 204:** Mewajibkan pencatatan *Critical Tracking Events (CTEs)* dan *Key Data Elements (KDEs)* untuk seluruh produk perikanan budidaya dari tambak hingga pelabuhan masuk Amerika Serikat.
2. **Uni Eropa EUDR (Peraturan Anti-Deforestasi):** Melarang impor komoditas yang terkait dengan konversi atau degradasi hutan mangrove setelah batas waktu 31 Desember 2020. Eksportir wajib melampirkan *Due Diligence Statement (DDS)* dan poligon koordinat batas tambak.
3. **Ketidakpastian Standar Residu Obat (MRL):** Terdapat perbedaan ambang batas ketat antara pasar US FDA, EU RASFF, dan Jepang untuk residu antibiotik (Chloramphenicol, Nitrofurans, Tetracycline) dan histamin.
4. **Kelemahan Kode Lama (`modules/Integration`):** Kelas legacy `AquacultureExportService` dan `OceanFleetService` di modul `Integration` merupakan template satu-file tanpa pemisahan domain, tanpa state machine, dan tanpa pembukuan double-entry ledger.

---

## 2. Pilihan Desain yang Dipertimbangkan (Decision Drivers & Options)

### Opsi A: Memperluas Kode Lama di Modul Integration
- *Kelemahan:* Melanggar aturan monorepo W8 (X2, X21); tidak memenuhi arsitektur vertical slice V1–V12; modul Integration dijadwalkan dipensiunkan.

### Opsi B: Memisahkan Akuakultur ke Modul Khusus Baru (`Aquaculture`)
- *Kelemahan:* Menambah jumlah modul di luar 46 modul kanonikal terdaftar; memecah relasi alami armada kapal nelayan dan tambak pesisir yang sudah bernaung di modul `Marine` (`mar_`).

### Opsi C (Dipilih): Vertical Slice Penuh di Modul `Marine` (`mar_`) dengan Algoritma Bersertifikat di `Shared`
- *Keunggulan:*
  - Seluruh domain tambak, hatchery, panen, lot ekspor, dan karbon biru terkonsolidasi di bawah prefiks resmi `mar_` (20 tabel baru).
  - Algoritma deterministik murni (A15–A19) ditempatkan di kernel `Modules\Shared\Domain\Algorithms\Marine` dan di-wrap via class inheritance di modul Marine untuk mematuhi zero code duplication (M-4 = 0).
  - Mengimplementasikan kontrak baru `G7-K28` (`VerifiedHarvestReader`) untuk integrasi bersih dengan modul lain.
  - Mempertahankan integritas audit command `mar:audit` dan fixture sabotase `MarineAuditCorruptionFixture`.

---

## 3. Keputusan Arsitektur yang Ditetapkan (Decisions)

### 3.1. Penomoran Kontrak & Keputusan (G7-K28 & G7-D46..D47)
- **Kontrak Baru G7-K28:** Didefinisikan interface `Modules\Marine\Contracts\VerifiedHarvestReader`. Menghubungkan pembacaan data lot panen terverifikasi mutu laboratorium dan neraca massa ke modul logistik pengapalan ekspor, ESG, dan escrow.
- **Keputusan Desain G7-D46:** Standar Pohon Merkle 8-daun kanonikal untuk ketelusuran lot ekspor dari benur SPF hingga kontainer pendingin.
- **Keputusan Desain G7-D47:** Metodologi estimasi serapan karbon biru mangrove silvofishery berbasis Verra VM0033.

### 3.2. Larangan Penggunaan Float untuk Parameter Uang (Aturan ArchScan A3)
Seluruh kolom dan parameter transaksi keuangan (nilai kontrak pesanan, dana escrow petani, biaya pakan, premi asuransi, harga kredit karbon) wajib menggunakan tipe integer unit terkecil (`int $amountMinorCents`), mata uang USD minor units (cents) atau IDR minor units (rupiah). Parameter bertipe float hanya diizinkan untuk besaran fisik murni (biomassa kg, suhu °C, konsentrasi DO mg/L, DBH cm, luas hektar) atau menggunakan akhiran nama khusus seperti `Rate` atau `Units`.

### 3.3. Dua State Machine Independen
1. `AquacultureBatchStateMachine`: Mengawal 8 status siklus budidaya kolam (`HATCHERY_STOCKED` s.d. `POND_STERILIZED`).
2. `ExportShipmentStateMachine`: Mengawal 8 status logistik pengapalan ekspor (`DRAFT_BOOKED` s.d. `SETTLED` / `REJECTED_QUARANTINE`).

### 3.4. Dua Saga Transaksional Terkompensasi Otomatis
1. `AquacultureExportExecutionSaga`: Validasi assay $\rightarrow$ Kunci escrow dana petani $\rightarrow$ Alokasi reefer container $\rightarrow$ Pembentukan Merkle Tree $\rightarrow$ Ledger entry $\rightarrow$ Transisi dispatch ekspor. Bila gagal di salah satu langkah, saga melakukan kompensasi mundur otomatis (*compensating transactions*).
2. `AssayDisputeArbitrationSaga`: Pembekuan escrow dispute $\rightarrow$ Uji banding laboratorium rujukan independen (Eurofins/SGS) $\rightarrow$ Settlement akhir atau refund importir penuh.

### 3.5. Integrasi Kontrak Eksternal yang Sudah Ada di Master
- **TradeFinance (G7-K11 `OfftakeLetterOfCreditGateway`):** Digunakan untuk penerbitan Letter of Credit ekspor komoditas offtake.
- **Logistics (`CustodyVerifier` & `ShipmentBooking`):** Digunakan untuk verifikasi hash dokumen Bill of Lading dan pemesanan logistik multimoda transfer kargo dingin.
- **Insurance (`EventCoverBinder`):** Digunakan untuk underwriting polis asuransi mortalitas tambak parametrik.

---

## 4. Konsekuensi Arsitektur (Consequences)

### Positif:
1. **Transparansi Kriptografis Penuh:** Importir dan auditor luar negeri dapat memverifikasi integritas silsilah produk seafood melalui Merkle proof tanpa risiko kebocoran rahasia petak tambak plasma rakyat.
2. **Kepatuhan Pasar Mutlak:** Memenuhi seluruh prasyarat US FDA FSMA 204, ASC CoC, dan regulasi anti-deforestasi EUDR.
3. **Penyelesaian Fair-Trade:** Hak petambak plasma terlindungi secara transparan melalui sistem escrow bersyarat yang terkunci sebelum panen dan cair setelah lolos karantina.
4. **Valorisasi Karbon:** Menghasilkan unit kredit karbon biru tersertifikasi yang dapat dimanfaatkan untuk program insetting netral-karbon.

### Netral / Mitigasi:
1. **Beban Komputasi Merkle Tree:** Hashing berulang 8 daun per lot memerlukan komputasi SHA-256; dioptimalkan dengan struktur array memori ringan ($< 1\text{ ms}$ per pohon).
2. **Ketergantungan Laboratorium Terakreditasi:** Kelolosan ekspor bergantung pada uji laboratorium BUSKIPM / ISO 17025; dimitigasi dengan mekanisme arbitrase sengketa otomatis pada saga kedua.

---

## 5. Matriks Tata Kelola & Daftar Periksa Kepatuhan (Governance Checklist)

Daftar periksa verifikasi kepatuhan arsitektural monorepo W8 untuk Fase 171:

- [x] **V1 Domain Models**: 20 model Eloquent di bawah `Modules\Marine\Domain\Models\` dengan `$fillable` eksplisit dan relasi terdefinisi kuat.
- [x] **V2 Database Migrations**: 20 tabel baru dengan prefiks resmi `mar_`, indeks unik $< 64$ karakter, dan foreign key cascade terstruktur.
- [x] **V3 Certified Algorithms**: 5 algoritma deterministik murni (A15–A19) tersimpan di `Modules\Shared\Domain\Algorithms\Marine\` dan diwariskan ke `Modules\Marine\Domain\Algorithms\`.
- [x] **V4 State Machines**: 2 mesin status finite state machine (`AquacultureBatchStateMachine`, `ExportShipmentStateMachine`) dengan validasi transisi terisolasi.
- [x] **V5 Compensating Sagas**: 2 saga transaksional dengan alur kompensasi rollback otomatis bila terjadi kegagalan pengujian assay atau penolakan slot kontainer.
- [x] **V6 Double-Entry Ledger**: Penjurnalan akrual berpasangan pada `mar_aquaculture_ledger_entries` dengan integritas berantai hash kriptografis SHA-256.
- [x] **V7 Cross-Module Integrations**: Integrasi bersih dengan 5 modul platform (`TradeFinance`, `Logistics`, `Insurance`, `Shared`, `Esg`).
- [x] **V8 Role-Based Access Control**: Seluruh 13 rute web terpetakan peran resmi (`admin`, `farmer`, `officer`, `manager`) pada `route-roles.php`.
- [x] **V9 API Specification**: Spesifikasi OpenAPI 3.1.0 terbit otomatis pada `docs/openapi-v3.json` dan tervalidasi via `php artisan openapi:validate`.
- [x] **V10 Self-Audit Command**: Perintah `php artisan mar:audit` mandiri lolos verifikasi status bersih (exit 0) dan menangkap sabotase buatan (exit 1).
- [x] **V11 Code Hygiene & Strict Types**: Seluruh berkas PHP baru menyertakan `declare(strict_types=1)` dan format lolos standar Laravel Pint PSR-12.
- [x] **V12 Metric Thresholds**: B1 logika $\ge 6.000$ baris, B3 dokumentasi $\ge 1.500$ baris, M-2 $\ge 55\%$, M-3 $\le 20\%$, M-4 $= 0$ temuan duplikasi.


