# Definition of Ready (DoR) — Fase 54 (Kelengkapan Tata Kelola Finansial Grup, Anggaran & Tutup Buku)

**Fase**: Fase 54 — Kelengkapan Finance Grup, Anggaran, Pajak, SoD & Tutup Buku  
**Modul**: `EnterpriseFinance` (prefiks tabel `ef_`)  
**Base Commit (sha-awal)**: `a2d3b634984fa35e5e8c5bf100a57314c78f0b5f`  
**Branch**: `feature/w8-p6-pelengkap-p5-54`  
**Ambang B1 Tambahan**: $\ge 6.000$ baris (Target: $\ge 6.300$ baris, margin $\ge +5\%$)  
**Ambang B3 Dokumentasi**: $\ge 1.200$ baris (Target: $\ge 1.350$ baris)  
**Ambang B2 UI**: 8 halaman view Blade baru terstandarisasi, 0 pelanggaran `ui:lint`  
**Plafon Pengujian**: $\le 5$ Acceptance Tests di `tests/Acceptance/EnterpriseGovernance`  

---

## 1. Latar Belakang & Masalah Bisnis
Verifikasi audit independen pasca merge W8-P5 menemukan bahwa PR #111 (Fase 54) mengalami deviasi implementasi di mana volume kode tercurah pada fitur di luar spesifikasi inti (seperti pinjaman sindikasi, capex, evaluasi goodwill), sementara komponen fundamental tata kelola keuangan konglomerasi belum terwujud di master.

Kekurangan kritis yang ditutup secara definitif pada PR [W8-P5-b.1] ini meliputi:
1. Ketiadaan kontrak antarmuka `BudgetCommitmentPort` untuk mengikat komitmen anggaran pengadaan barang/jasa dari modul Procurement.
2. Belum adanya tabel dan model pencatatan encumbrance `ef_budget_encumbrances`.
3. Belum tersedianya 7 console command terjadwal idempoten: `enterprise:budget-sync`, `enterprise:close-checklist`, `enterprise:compliance-escalate`, `enterprise:control-tests`, `enterprise:data-pack`, `enterprise:sod-scan`, dan `enterprise:tax-recon`.
4. Ketiadaan event spine korporat: `enterprise.budget.threshold_exceeded.v1`, `enterprise.sod.violation_detected.v1`, dan `enterprise.period.closed.v1`.
5. Ketiadaan posting buku besar penutupan periode fiskal (D54-10) untuk akun nominal pendapatan/beban ke `ef:income_summary:{entitas}:IDR` dan transfer laba/rugi bersih ke `ef:retained_earnings:{entitas}:IDR`, serta setoran penyelesaian PPN ke `ef:vat_settlement_clearing:{entitas}:IDR`.
6. Belum adanya implementasi `SubledgerTotalProvider` lintas modul (AR, AP, Fixed Asset, Inventory) untuk rekonsiliasi pra-tutup buku.
7. Ketiadaan matriks Segregation of Duties (SoD) aktif yang mampu mencegah penetapan peran konflik serta mekanisme pengecualian berbatas waktu.
8. Belum adanya otomatisasi pengujian kontrol kepatuhan (Internal Control Over Financial Reporting - ICFR / RCM) yang menugaskan defisiensi kontrol ke Kotak Masuk Tugas (`TaskInboxService::createTask()`).
9. Belum tersedianya generator paket data bukti audit KAP (`enterprise:data-pack`) berformat ZIP lengkap dengan manifest ringkasan SHA-256.

---

## 2. Rencana Anggaran Baris (RAB) — [W8-P5-b.1] Fase 54

| Lapisan Kode | Estimasi Baris | Target Aktual | Komponen Utama |
|---|---|---|---|
| **State Machine & Guards** | 450–700 | ~600 | `EnterpriseBudgetCycleStateMachine` (8 status, maker-checker, guard hierarki sum, lock period check, versioning saat REVISED) |
| **Posting Ledger** | 500–800 | ~650 | `EnterpriseFinanceLedgerPoster` (penutupan akun pendapatan/beban, laba/rugi ditahan, settlement PPN, deviden subholding, validasi zero-sum single-sign) |
| **Aplikasi (Action & Services)** | 2.500–3.200 | ~2.850 | `BudgetCommitmentService`, `FinancialPeriodClosingService`, `CorporateTaxReconciliationService`, `SodGovernanceService`, `InternalControlTestingService`, `ComplianceEscalationService`, `AuditDataPackGeneratorService`, `GroupFinanceHealthService` |
| **Integrasi & Adaptor** | 700–1.000 | ~850 | 4 Adaptor `SubledgerTotalProvider` (AR, AP, Fixed Assets, Inventory), 3 Sumber `ComplianceDeadlineSource` (Pajak, Kontrak, Lisensi), Listener Komitmen Procurement |
| **HTTP (Controller, Requests, ViewModels)** | 800–1.200 | ~950 | 8 Controller tipis, FormRequest validasi ketat, ViewModel tabular |
| **Console Commands & Laporan** | 900–1.300 | ~1.100 | 7 Command terjadwal idempoten, 1 Command audit dua sumber `enterprise:audit` diperluas, generator ekspor CSV |
| **Domain Models & Enums** | 350–550 | ~420 | `BudgetEncumbrance`, `FinancialPeriodLock`, `SodException`, `ControlPoint`, `ControlTestRun`, `TaxReconSummary`, Enums status |
| **Total Logika B1 Tambahan** | **6.200–8.750** | **~7.420** | **Ambang Keras $\ge 6.000$ Terpenuhi** |
| **Dokumentasi B3 Tulis-Tangan** | **1.200–1.600** | **~1.380** | **Ambang Keras $\ge 1.200$ Terpenuhi** |

---

## 3. Spesifikasi Skema Database Baru (Migrasi Ekstensi Fase 54)

### A. Tabel `ef_budget_encumbrances`
Tabel pencatatan komitmen anggaran (*budget encumbrance / reservation*) yang mengunci pagu sebelum realisasi belanja fisik:
- `id` (uuid, primary key)
- `budget_id` (foreign key ke `ef_enterprise_budgets.id`, cascade on delete)
- `commitment_code` (varchar(64), unique index)
- `source_module` (varchar(32), misal: `Procurement`, `Contract`, `Travel`)
- `reference_number` (varchar(64), misal: `PO-2026-11-001`)
- `amount_minor` (bigint unsigned, nominal komitmen dalam minor unit IDR)
- `liquidated_amount_minor` (bigint unsigned, default 0, nominal yang telah terealisasi ke faktur/jurnal)
- `status` (enum: `RESERVED`, `LIQUIDATED`, `RELEASED`)
- `reserved_at` (timestamp, waktu komitmen dicadangkan)
- `liquidated_at` (timestamp, nullable, waktu likuidasi terjadi)
- `released_at` (timestamp, nullable, waktu pelepasan komitmen terbuka)
- `reason` (text, nullable, catatan pelepasan atau penyesuaian)
- `payload` (json, nullable, metadata baris PO/kontrak)
- `created_at`, `updated_at` (timestamps)

### B. Tabel `ef_financial_period_locks`
Tabel pengunci periode buku besar per entitas hukum pasca checklist tutup buku sukses:
- `id` (uuid, primary key)
- `entity_code` (varchar(32))
- `period` (varchar(7), format `YYYY-MM`)
- `is_locked` (boolean, default false)
- `locked_at` (timestamp, nullable)
- `locked_by` (unsignedBigInteger, nullable, operator penutup buku)
- `closing_voucher_id` (varchar(64), nullable, tautan voucher jurnal penutupan ledger)
- `subledger_recon_summary` (json, ringkasan saldo subledger vs ledger saat penutupan)
- `net_income_loss_minor` (bigint, saldo laba bersih positif atau rugi negatif dalam minor IDR)
- `created_at`, `updated_at` (timestamps)
- Index unik: `unique(entity_code, period)`

### C. Tabel `ef_sod_exceptions`
Pencatatan persetujuan dispensasi/pengecualian pemisahan tugas dengan batas kedaluwarsa yang diverifikasi sistem:
- `id` (uuid, primary key)
- `sod_rule_id` (varchar(64), kode aturan konflik SoD)
- `user_id` (unsignedBigInteger, operator yang menerima dispensasi)
- `granted_by` (unsignedBigInteger, pejabat berwenang pemberi dispensasi)
- `justification` (text, alasan bisnis valid)
- `expires_at` (timestamp, batas waktu berlakunya pengecualian)
- `is_active` (boolean, default true)
- `created_at`, `updated_at` (timestamps)
- Index: `index(user_id, is_active)`

### D. Tabel `ef_control_points` & `ef_control_test_runs`
Katalog matriks kontrol risiko pelaporan keuangan (Risk & Control Matrix - RCM) dan riwayat uji kepatuhan otomatis:
- `ef_control_points`:
  - `id` (varchar(32), primary key, misal: `CP-01-FOUR-EYES`)
  - `name` (varchar(128))
  - `category` (enum: `AUTHORIZATION`, `RECONCILIATION`, `DATA_INTEGRITY`, `SEPARATION_OF_DUTIES`)
  - `automated` (boolean, default true)
  - `description` (text)
  - `created_at`, `updated_at` (timestamps)
- `ef_control_test_runs`:
  - `id` (uuid, primary key)
  - `control_id` (foreign key ke `ef_control_points.id`)
  - `tested_at` (timestamp)
  - `status` (enum: `EFFECTIVE`, `DEFICIENT`, `FAILED`)
  - `sample_count` (int unsigned, jumlah populasi/sampel yang diuji)
  - `deviation_count` (int unsigned, jumlah anomali/pelanggaran)
  - `details` (json, payload detail anomali)
  - `task_id` (varchar(64), nullable, ID CoreTask bila eskalasi defisiensi diterbitkan)
  - `created_at`, `updated_at` (timestamps)

### E. Tabel `ef_tax_recon_summaries`
Pencatatan hasil rekonsiliasi PPN dan pajak penghasilan masa per entitas:
- `id` (uuid, primary key)
- `entity_code` (varchar(32))
- `period` (varchar(7), format `YYYY-MM`)
- `vat_output_minor` (bigint unsigned, PPN Keluaran / Pajak Terutang)
- `vat_input_minor` (bigint unsigned, PPN Masukan / Pajak Dapat Dikreditkan)
- `net_payable_minor` (bigint, selisih kurang bayar positif atau lebih bayar negatif)
- `settlement_status` (enum: `DRAFT`, `RECONCILED`, `SETTLED`)
- `spt_draft_number` (varchar(64), nomor SPT Masa simulasi gapless)
- `created_at`, `updated_at` (timestamps)
- Index unik: `unique(entity_code, period)`

---

## 4. Bagan Akun Buku Besar (D54-10)

Bagan akun modul didaftarkan pada `EnterpriseFinanceAccounts` dengan konvensi segmen entitas:

```
ef:income_summary:{entitas}:IDR       -> EKUITAS / IKTISAR LABA RUGI (Normal Kredit, allow_negative: true)
ef:retained_earnings:{entitas}:IDR     -> EKUITAS / SALDO LABA DITAHAN (Normal Kredit, allow_negative: true)
ef:vat_output_payable:{entitas}:IDR   -> LIABILITAS / UTANG PPN KELUARAN (Normal Kredit, allow_negative: false)
ef:vat_input_receivable:{entitas}:IDR -> ASET / PIUTANG PPN MASUKAN (Normal Debit, allow_negative: false)
ef:vat_settlement_clearing:{entitas}:IDR -> ASET / KLIRING SETORAN KAS NEGARA (Normal Debit, allow_negative: true)
ef:dividend_declared:{entitas}:IDR    -> EKUITAS / DEVIDEN DIUMUMKAN (Normal Debit, allow_negative: false)
ef:dividend_payable:{entitas}:IDR     -> LIABILITAS / UTANG DEVIDEN KEPADA INDUK (Normal Kredit, allow_negative: false)
```

Aturan Posting Buku Besar (Semua transaksi terikat kunci Idempotensi `IDEM:EF:{AKSI}:{ID}`):
1. **Penutupan Akun Pendapatan**: Debit akun Pendapatan Operasional, Kredit `ef:income_summary:{entitas}:IDR`.
2. **Penutupan Akun Beban**: Debit `ef:income_summary:{entitas}:IDR`, Kredit akun Beban Operasional.
3. **Penutupan Laba Bersih ke Laba Ditahan**: Debit `ef:income_summary:{entitas}:IDR`, Kredit `ef:retained_earnings:{entitas}:IDR`.
4. **Penutupan Rugi Bersih ke Laba Ditahan**: Debit `ef:retained_earnings:{entitas}:IDR`, Kredit `ef:income_summary:{entitas}:IDR`.
5. **Setoran Selisih PPN Kurang Bayar**: Debit `ef:vat_output_payable:{entitas}:IDR`, Kredit `ef:vat_input_receivable:{entitas}:IDR` (sebesar masukan yang dikreditkan) dan Kredit `ef:vat_settlement_clearing:{entitas}:IDR` (sebesar kas yang disetor).
6. **Dividen Sub-holding Diumumkan**: Debit `ef:dividend_declared:{entitas}:IDR`, Kredit `ef:dividend_payable:{entitas}:IDR`.

---

## 5. Matriks Segregation of Duties (SoD) — 12 Aturan Baku

Matriks konflik kepentingan didefinisikan sebagai data terstruktur untuk mendeteksi dan mencegah penetapan hak akses ganda berbahaya:
1. `SOD-01`: Pembuat Purchase Order (`procurement_buyer`) vs Penyetuju Purchase Order (`procurement_manager`).
2. `SOD-02`: Penginput Faktur Vendor (`ap_clerk`) vs Eksekutor Pembayaran Kas/Bank (`treasury_disburser`).
3. `SOD-03`: Pembuat Pengeluaran Kas (`cashier`) vs Rekonsiliator Mutasi Bank (`bank_reconciler`).
4. `SOD-04`: Pengelola Master Data Vendor (`master_data_admin`) vs Penginput Pembayaran Tagihan (`ap_officer`).
5. `SOD-05`: Kasir POS (`pos_cashier`) vs Pemroses Pembatalan/Void Transaksi tanpa Supervisor (`pos_void_admin`).
6. `SOD-06`: Pembuat Pagu Anggaran (`budget_planner`) vs Penyetuju Anggaran/Revisi (`budget_approver`).
7. `SOD-07`: Operator Gudang / Penerima Fisik (`warehouse_receiver`) vs Petugas Penyesuaian Cycle Count (`inventory_controller`).
8. `SOD-08`: Penginput Penggajian Karyawan (`payroll_clerk`) vs Otorisator Transfer Gaji (`hr_finance_director`).
9. `SOD-09`: Pengaju Proposal Investasi CAPEX (`project_sponsor`) vs Komite Penilai Kelayakan Finansial (`capex_evaluator`).
10. `SOD-10`: Operator Penagihan Piutang (`ar_collector`) vs Petugas Write-off Piutang Tak Tertagih (`credit_risk_manager`).
11. `SOD-11`: Petugas Perhitungan Pajak (`tax_preparer`) vs Penyetuju Pelaporan SPT & Transfer Fiskal (`tax_approver`).
12. `SOD-12`: Auditor Internal (`internal_auditor`) vs Administrator Hak Akses Operasional (`system_security_admin`).

Mekanisme pencegahan: Saat sistem mendeteksi user memiliki 2 role yang berkonflik, sistem melempar `SodViolationException` kecuali terdapat entri aktif pada tabel `ef_sod_exceptions` yang masa berlakunya belum kedaluwarsa.

---

## 6. Protokol Uji Kepatuhan Kontrol Internal (RCM) & Otomatisasi

Command `enterprise:control-tests` mengeksekusi pengujian harian atas 4 titik kendali utama:
1. **Persetujuan Ganda (*Four-Eyes Principle*)**: Memverifikasi bahwa seluruh anggaran berstatus `APPROVED` dan transaksi nominal $\ge$ ambang memiliki pembuat $\neq$ penyetuju.
2. **Pencocokan Tiga Pihak (*3-Way Match Verification*)**: Memastikan seluruh pelunasan faktur vendor memiliki referensi Purchase Order dan Goods Receipt Note yang sah.
3. **Batas Toleransi Timbangan & Penerimaan**: Memeriksa seluruh selisih kuantitas penerimaan barang tidak melebihi parameter toleransi yang ditetapkan.
4. **Pencegahan Margin Negatif**: Memastikan transaksi penjualan tidak dilakukan di bawah harga pokok penjualan standar tanpa otorisasi diskon khusus.

Bila terdeteksi defisiensi (`status = DEFICIENT` atau `FAILED`), sistem secara otomatis memanggil `TaskInboxService::createTask()` untuk menerbitkan tiket remediasi kepada manajer tata kelola.

---

## 7. Rute & Otorisasi Hak Akses (Route-Roles)

| URL Endpoint | Metode | Role Berhak | Deskripsi Fungsi |
|---|---|---|---|
| `/enterprise-finance/budgets` | GET | `budget_owner`, `finance_controller`, `auditor`, `admin` | Halaman hierarki anggaran, sisa pagu, encumbrance |
| `/enterprise-finance/budgets/{id}/commitments` | GET | `budget_owner`, `finance_controller`, `auditor`, `admin` | Detail komitmen terbuka per anggaran |
| `/enterprise-finance/statements` | GET | `finance_controller`, `auditor`, `admin` | Laporan neraca, laba rugi, arus kas langsung & tidak langsung |
| `/enterprise-finance/close` | GET, POST | `finance_controller`, `admin` | Checklist tutup buku, rekonsiliasi subledger, penutupan akun |
| `/enterprise-finance/tax` | GET, POST | `finance_controller`, `auditor`, `admin` | Rekonsiliasi PPN, ringkasan PPh, ekspor e-Faktur/e-Bupot |
| `/enterprise-finance/sod` | GET, POST | `finance_controller`, `auditor`, `admin` | Matriks SoD, deteksi konflik, permohonan dispensasi |
| `/enterprise-finance/controls` | GET, POST | `finance_controller`, `auditor`, `admin` | Ringkasan RCM, eksekusi uji kontrol harian, antrean defisiensi |
| `/enterprise-finance/compliance` | GET | `finance_controller`, `legal`, `auditor`, `admin` | Kalender tenggat waktu regulasi & eskalasi |
| `/enterprise-finance/audit-pack` | GET, POST | `auditor`, `finance_controller`, `admin` | Generator paket bukti audit KAP (ZIP + SHA-256) |

---

## 8. Persyaratan Audit Dua Sumber (`enterprise:audit`)

Audit dua sumber `enterprise:audit` diperluas untuk menguji 10 dimensi integritas data:
1. Rekonsiliasi saldo buku besar vs agregasi subledger (AR, AP, Fixed Asset, Inventory, Pajak).
2. Kesesuaian saldo komitmen di `ef_budget_encumbrances` vs total pemesanan terbuka di Procurement.
3. Integritas pagu: Memastikan tidak ada sisa pagu anggaran negatif tanpa otorisasi soft-stop.
4. Keseimbangan neraca konsolidasi: $\Sigma \text{Aset} = \Sigma \text{Liabilitas} + \Sigma \text{Ekuitas}$.
5. Integritas tutup buku: Memastikan periode terkunci tidak menerima mutasi baru.
6. Pemeriksaan nomor draf faktur pajak gapless: Memastikan penomoran faktur tidak mengalami loncatan urutan.
7. Pemeriksaan pelanggaran SoD aktif tanpa dokumen dispensasi sah di `ef_sod_exceptions`.
8. Deteksi kewajiban kepatuhan regulasi yang jatuh tempo tanpa penerbitan eskalasi.
9. Verifikasi paket data audit: Memastikan manifest checksum SHA-256 cocok dengan isi berkas arsip.
10. Validasi zero-sum ledger posting: Memastikan setiap transaksi `ef:*` memiliki $\Sigma \text{Debit} = \Sigma \text{Kredit}$.
