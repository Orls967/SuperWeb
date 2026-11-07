# Spesifikasi Integrasi API RESTful: Smelter & Metals Trading Desk (Fase 121)

## 1. Ikhtisar Arsitektur API

Modul Pertambangan Fase 121 mengekspos 29 rute antarmuka pemrograman aplikasi (RESTful API) yang dirancang mengikuti konvensi HTTP modern, otorisasi berbasis peran (*Role-Based Access Control* - RBAC), penanganan validasi ketat FormRequest, serta struktur respons JSON standar.

Base URL: `https://app.example.com/fase121`
Autentikasi: `Bearer Token (Laravel Sanctum / Session Web)`
Hak Akses Diizinkan: `admin`, `dispatcher_mine`, `hse_officer`, `mine_manager`, `site_manager`.

---

## 2. Katalog Endpoint API Smelter & Hilirisasi

### A. Penjadwalan & Pengendalian Batch Tanur
- `GET /fase121/smelter`
  - Deskripsi: Mengembalikan tampilan dashboard antarmuka SCADA dan daftar batch tanur aktif.
  - Roles: `admin, dispatcher_mine, hse_officer, mine_manager, site_manager`
- `POST /fase121/smelter/runs`
  - Deskripsi: Menjadwalkan siklus batch peleburan tanur baru.
  - Request Body: `CreateSmelterFurnaceRunRequest` (`furnace_id`, `ore_feed_wmt`, `ore_moisture_pct`, `ore_ni_grade_pct`, `ore_fe_grade_pct`, `coal_reductant_tons`, `silica_flux_tons`).
  - Status: `201 Created`
- `POST /fase121/smelter/balance`
  - Deskripsi: Mengeksekusi solver neraca massa dan energi pirometalurgi M10.
  - Request Body: `ExecuteSmelterBalanceRequest` (`run_id`, `power_consumption_mwh`).
  - Status: `200 OK`
- `POST /fase121/smelter/tapping`
  - Deskripsi: Mencatat keluaran logam NPI dan terak cair, serta mengkapitalisasi persediaan ke buku besar.
  - Request Body: `run_id`, `metal_output_tons`, `slag_output_tons`, `tapping_temperature_c`.
  - Status: `200 OK`
- `POST /fase121/smelter/abort`
  - Deskripsi: Membatalkan siklus batch tanur secara darurat (*emergency trip*).
  - Request Body: `run_id`, `reason`.
  - Status: `200 OK`
- `POST /fase121/smelter/hilirisasi-pipeline`
  - Deskripsi: Menjalankan saga orkestrasi hilirisasi mineral terpadu (`MetalsHilirisasiLifecycleSaga`).
  - Request Body: `run_id`, `contract_id` (opsional), `bank_partner` (opsional).
  - Status: `200 OK` atau `422 Unprocessable` jika terjadi kegagalan dengan kompensasi otomatis.

### B. Analisis Mutu Laboratorium Spektrometri & Emisi GRK
- `POST /fase121/smelter/assay`
  - Deskripsi: Menerbitkan sertifikasi uji laboratorium mutu spektrometri XRF / ICP-OES.
  - Request Body: `run_id`, `primary_metal_grade_pct`, `iron_fe_pct`, `carbon_c_pct`, `silicon_si_pct`, `phosphorus_p_pct`, `sulfur_s_pct`.
  - Status: `200 OK`
- `POST /fase121/smelter/emissions`
  - Deskripsi: Mengevaluasi intensitas emisi GRK Scope 1 dan Scope 2 per ton logam.
  - Request Body: `run_id`.
  - Status: `200 OK`
- `POST /fase121/smelter/carbon-tax`
  - Deskripsi: Menghitung provisi liabilitas pajak karbon jika intensitas $> 2.50\text{ tCO}_2\text{e/t}$.
  - Request Body: `run_id`.
  - Status: `200 OK`
- `POST /fase121/smelter/slag-basicity`
  - Deskripsi: Menghitung indeks rasio basicity terak $(\text{MgO} + \text{CaO}) / \text{SiO}_2$.
  - Request Body: `sio2_pct`, `mgo_pct`, `cao_pct`.
  - Status: `200 OK`
- `POST /fase121/smelter/reconcile`
  - Deskripsi: Melakukan rekonsiliasi neraca massa umpan versus produk leburan aktual.
  - Request Body: `run_id`.
  - Status: `200 OK`
- `POST /fase121/smelter/audit-chain`
  - Deskripsi: Mengaudit integritas rantai kustodi tanda tangan digital HMAC dari umpan hingga produk.
  - Request Body: `run_id`.
  - Status: `200 OK`

---

## 3. Katalog Endpoint API Metals Trading Desk & LME Hedging

- `GET /fase121/trading`
  - Deskripsi: Mengembalikan tampilan antarmuka trading desk dan daftar kontrak aktif.
- `POST /fase121/trading/contracts`
  - Deskripsi: Membuat kontrak perdagangan penjualan fisik atau lindung nilai berjangka.
  - Request Body: `CreateMetalsContractRequest` (`counterparty_name`, `metal_symbol`, `contract_type`, `contract_quantity_metric_tons`, `fixed_price_usd_cents_per_ton`).
  - Status: `201 Created`
- `POST /fase121/trading/lock-price`
  - Deskripsi: Mengunci harga acuan LME Cash pada kontrak.
  - Request Body: `contract_id`, `price_cents` (opsional).
  - Status: `200 OK`
- `POST /fase121/trading/mtm`
  - Deskripsi: Mengeksekusi valuasi harian Mark-to-Market posisi derivatif (Algoritma M11).
  - Request Body: `contract_id`, `position_type`, `market_price_usd_cents`.
  - Status: `200 OK`
- `POST /fase121/trading/margin-call`
  - Deskripsi: Memeriksa dan memicu peringatan setoran margin variasi jika ekuitas turun.
  - Request Body: `contract_id`.
  - Status: `200 OK`
- `POST /fase121/trading/settle`
  - Deskripsi: Menyelesaikan kontrak perdagangan secara fisik atau finansial.
  - Request Body: `contract_id`, `is_physical_delivery`.
  - Status: `200 OK`
- `POST /fase121/trading/lme-contango`
  - Deskripsi: Menganalisis struktur spread kurva forward LME 3-Bulan vs Cash (Contango/Backwardation).
  - Request Body: `metal_symbol`.
  - Status: `200 OK`

---

## 4. Katalog Endpoint API Resi Gudang (SRG) & Produk Sampingan

- `GET /fase121/warehouse-receipt`
  - Deskripsi: Mengembalikan antarmuka daftar resi gudang terdaftar dan brankas penyimpanan.
- `POST /fase121/warehouse-receipt/issue`
  - Deskripsi: Menerbitkan resi gudang digital dan menghitung haircut agunan dinamis (Algoritma M12).
  - Request Body: `IssueWarehouseReceiptRequest` (`assay_id`, `certified_weight_metric_tons`, `unit_market_price_idr_per_ton`, `warehouse_code`).
  - Status: `201 Created`
- `POST /fase121/warehouse-receipt/register-bappebti`
  - Deskripsi: Mendaftarkan resi gudang secara resmi ke sistem ISWARE Bappebti.
  - Request Body: `receipt_id`.
  - Status: `200 OK`
- `POST /fase121/warehouse-receipt/pledge-scf`
  - Deskripsi: Mengikat hak fidusia penjaminan ke bank mitra SCF dan mencairkan pinjaman ke buku besar.
  - Request Body: `receipt_id`, `bank_name`, `loan_amount_idr`.
  - Status: `200 OK`
- `POST /fase121/warehouse-receipt/release`
  - Deskripsi: Melepaskan hak agunan resi gudang pasca-pelunasan pinjaman bank SCF.
  - Request Body: `receipt_id`, `is_full_release`, `repayment_amount_idr`.
  - Status: `200 OK`
- `POST /fase121/warehouse-receipt/validate-integrity`
  - Deskripsi: Memvalidasi segel digital SHA-256 dan tanda tangan HMAC resi gudang.
  - Request Body: `receipt_id`.
  - Status: `200 OK`
- `GET /fase121/byproduct`
  - Deskripsi: Mengembalikan antarmuka pengelolaan produk sampingan terak nikel slag.
- `POST /fase121/byproduct/dispatch`
  - Deskripsi: Mencatat pengiriman terak slag, menerbitkan manifest SIMPEL KLHK, dan memposting pendapatan.
  - Request Body: `DispatchByproductSlagRequest` (`run_id`, `dispatch_tons`, `buyer_party_code`, `unit_price_idr_per_ton`).
  - Status: `200 OK`
- `POST /fase121/byproduct/recycle`
  - Deskripsi: Mencatat pemanfaatan daur ulang internal terak slag untuk agregat jalan tambang.
  - Request Body: `byproduct_id`, `recycle_tons`, `circular_application`.
  - Status: `200 OK`
- `GET /fase121/export-csv`
  - Deskripsi: Mengekspor laporan operasional hilirisasi lengkap dalam format CSV.
  - Status: `200 OK (Content-Type: text/csv)`
