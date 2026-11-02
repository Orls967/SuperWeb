# Definition of Ready (DoR) — LINI 5: Kuliner (CloudKitchen & Smart Vending MVP)

## 1. Rujukan Dokumen & Kutipan Baris Konsep

- **docs/KONSEP.md §A1 (baris 3-45):** Kepemilikan tabel dan modul otonom. Modul CloudKitchen memiliki prefiks canonical `ckt_*` (menggantikan prefiks transisi `resto_ck_*`). Modul Vending memiliki prefiks canonical `vnd_*` (menggantikan prefiks usang `ven_*` yang bentrok dengan Venue).
- **docs/KONSEP.md §A2 (seluruhnya, baris 48-124):** Bagan Akun Standar (CoA) double-entry ledger. Akun per-subjek dibuat idempoten. Strict invariant: `allow_negative = false` untuk akun pendapatan dan aset non-kliring.
- **docs/KONSEP.md §A3 (baris 126-170):** Prinsip kebenaran, kesederhanaan, dan pengujian. Tidak ada pembulatan mengambang (zero floats); seluruh perhitungan rupiah memakai integer, persen dalam basis points (bps).
- **docs/KONSEP.md §A4 (baris 172-210):** Kontrak antar-modul asinkron dan domain event. Komunikasi via DTO dan Event, bukan cross-module direct table write.
- **docs/KONSEP.md §A5 (baris 212-255):** Append-only facts table untuk mutasi bahan, order katering, dan transaksi vending.
- **docs/KONSEP.md §A6 (baris 257-295):** Idempotency key unik pada seluruh mutasi stok dan pembayaran.
- **docs/KONSEP.md §A7 (baris 297-330):** RBAC granular: peran `kitchen`, `outlet_manager`, `catering_admin` untuk CloudKitchen; `vending_operator` untuk Vending.
- **docs/KONSEP.md §A8 (baris 332-370):** Two-source audit commands (`ck:audit` dan `vnd:audit`) dengan pendeteksian korupsi buatan (corruption fixtures).
- **docs/KONSEP.md §A10 (baris 372-405):** Penggunaan SimClockInterface untuk seluruh determinisme waktu simulasi.
- **docs/KONSEP.md §A13 (baris 407-440):** Penanganan galat tanpa HTTP 500 (`withErrors` bukan `back()->errors()`).
- **docs/KONSEP.md §A15 (baris 442-500):** Higienitas pengujian, isolasi test, dan larangan bypass aturan arsitektur.
- **docs/KONSEP.md §3F (baris 649-673):** Spesifikasi implementasi minimum (MVP vertical slice) untuk CloudKitchen dan Vending:
  - Baris 651: Prefiks tabel `ckt_*` dan `vnd_*`.
  - Baris 653: Slice 1 — Cloud kitchen & order produksi memotong bahan via InventoryService; delivery via ShipmentBooking.
  - Baris 654: Slice 2 — Katering karyawan potong gaji; langganan dengan kuota harian; potongan dibuat saat `hcm.payroll.posted.v1` sebagai baris payroll resmi.
  - Baris 655: Slice 3 — Holt-Winters demand forecast per outlet (B0′.5) + footfall Mall; usulan auto-PR ke Procurement via ApprovalEngine; pencatatan MAPE harian.
  - Baris 656: Slice 4 — Smart vending unit sebagai lokasi stok Inventory; transaksi via PaymentGateway; restock route optimization (B0′.6) saat stok < ambang kritis.
  - Baris 659-665: Aturan debit/kredit double-entry ledger lengkap.
  - Baris 666: Rute dan role: `/cloud-kitchen` dan `/vending`.
  - Baris 667-668: Command audit dua-sumber `ck:audit` dan `vnd:audit`.
- **docs/PROGRESS.md P2, P3 (X1–X25), P8, P9:** Baseline ratchet zero-regression, integritas proof blocks, dan tata kelola commit atomic.

---

## 2. Kriteria Kesiapan (Ready Criteria) per Komponen

### CK-1: Cloud Kitchen Operations & BOM Recipe Traversal (B0′.4)
- [x] Resep masakan dimodelkan sebagai Directed Acyclic Graph (DAG) bertingkat (misal: Bento Box -> Nasi Gurih + Ayam Goreng Lengkuas + Sambal).
- [x] Setiap node bahan memiliki rasio kuantitas integer (skala gram/ml) dan komponen biaya.
- [x] Siklus dependensi terdeteksi dan menghasilkan CycleDetectedException dengan lintasan siklus lengkap.
- [x] Traversal kedua independen menerbitkan BOMVerificationCertificate di produksi.
- [x] Eksekusi produksi dapur memotong stok bahan baku mentah melalui `StockMovement` (U-4 idempotency).

### CK-2: Corporate Catering Subscription & Payroll Deduction Event (Z9)
- [x] Karyawan mendaftar langganan katering dengan kuota harian (1-3 porsi/hari) dan tarif bulanan integer rupiah.
- [x] Pengiriman harian memvalidasi sisa kuota hari berjalan; pemesanan melebihi kuota harian ditolak seketika (422/InvalidArgumentException).
- [x] Pemotongan biaya katering terintegrasi dengan event `hcm.payroll.posted.v1`, memposting baris payroll resmi:
  - Debit: `hcm:salary_payable:{employee}:IDR`
  - Kredit: `ckt:catering_revenue:IDR`
- [x] Pembatalan pesanan sebelum jam cut-off memulihkan kuota dan menerbitkan entri pembalik refund.

### VND-1: Smart Vending Machine as Inventory Stock Location (U-4)
- [x] Setiap mesin vending (`vnd_units`) terdaftar sebagai lokasi penyimpanan multi-outlet resmi di modul Inventory.
- [x] Transaksi penjualan (`vnd_transactions`) mencatat audit log append-only:
  - Debit: `wallet:user:{id}:IDR` (atau clearing)
  - Kredit: `vnd:sales_revenue:IDR`
  - HPP: Debit `expense:resto:cogs:IDR`, Kredit `inventory:resto:{outlet}:IDR`
- [x] Dispense item menjamin saldo stok tidak pernah negatif (`current_stock >= requested_qty`).

### VND-2: Holt-Winters 7-Day Demand & Waste Forecasting (B0′.5)
- [x] Perhitungan peramalan permintaan 7 hari ke depan menggunakan Holt-Winters double/triple smoothing deterministik (seed tetap 42).
- [x] Peramalan limbah (waste projection) berbasis rasio pembusukan kategori produk.
- [x] Pencatatan MAPE aktual vs forecast harian; saran auto-scaledown aktif bila limbah aktual melampaui forecast sebesar >20%.
- [x] Usulan replenishment purchase order terikat batas plafon harian dan disalurkan ke ApprovalEngine.

### VND-3: Restocking Route Optimization (B0′.6)
- [x] Pengelompokan mesin-mesin vending yang membutuhkan pengisian ulang (stok <= ambang kritis).
- [x] Penentuan urutan rute kunjungan teknisi restock menggunakan TSP eksak ($n \le 12$) atau heuristik 2-opt dengan batas bawah Minimum Spanning Tree (MST).
- [x] Rute diverifikasi dengan RouteOptimizationCertificate independen sebelum tugas restock diterbitkan.

### SEC & AUDIT: Two-Source Audit Commands (Z7, Z8)
- [x] `ck:audit` merekonsiliasi 2 sumber:
  1. Konsumsi bahan per batch produksi dapur = akumulasi movement stok bahan baku di Inventory.
  2. Akumulasi potongan katering karyawan = total potongan katering pada baris event payroll HCM.
- [x] `vnd:audit` merekonsiliasi 2 sumber:
  1. Akumulasi rupiah penjualan unit vending = total transaksi `vnd_transactions` berstatus COMPLETED.
  2. Total fisik stok berkurang pada unit = total item dispensed.
- [x] Disediakan fixture korupsi untuk membuktikan bahwa manipulasi data secara sengaja menyebabkan `ck:audit` dan `vnd:audit` keluar dengan exit code 1.
