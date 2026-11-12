# Runbook Operasional — Tata Kelola Intercompany & Konsolidasi Grup (Fase 52)

- **ID Layanan**: `SVC-INTERCOMPANY-CONSOLIDATION`
- **Klasifikasi Kekritisan**: TINGKAT 1 (Mission Critical Corporate Financial System)
- **Pemilik Layanan**: Tim Platform Keuangan & Pelaksana W8-P6
- **Tanggal Diperbarui**: 2026-11-12

---

## 1. Inventori Komponen & Arsitektur Layanan

Modul `Modules\Intercompany` beroperasi sebagai tulang punggung pelaporan keuangan grup konglomerasi:
- **Layanan Inti**:
  - `GroupEntityRegistry`: Pengelola hirarki entitas, pohon konsolidasi DAG, dan metode konsolidasi (Full/Equity/Cost).
  - `IntercompanyMismatchSweepService`: Deteksi pos gantung dan asimetri saldo timbal balik.
  - `MultivariateBenchmarkComparator`: Analisis komparabilitas transfer pricing PMK 172/2023.
  - `BilateralNettedSettlementMatrixCalculator`: Mesin netting multilateral dan eliminasi utang sirkular.
  - `ConsolidationRollupTreeOptimizer`: Eksekusi roll-up konsolidasi PSAK 65 / IFRS 10 dan translasi PSAK 10.
  - `ConsolidationAdjustmentJournalGenerator`: Amortisasi PPA step-up, PSAK 15 metode ekuitas, dan sewa PSAK 73.
  - `TransferPricingLocalFileGenerator`: Penyusunan Local File & Master File.
  - `IntercompanyCashPoolSweeper`: Sweeping kas saldo nol (ZBA) terpusat.
  - `ConsolidationEliminationRollupOrchestrator`: Penguncian periode konsolidasi resmi.
- **Tabel Basis Data**:
  - `ic_entities`: Master data entitas legal grup.
  - `ic_ownership_links`: Relasi kepemilikan saham dan persentase basis points.
  - `ic_transactions`: Transaksi cermin penjualan dan pembelian intra-grup.
  - `ic_loans`: Pinjaman intra-grup, jadwal bunga, dan amortisasi pokok.
  - `ic_elimination_entries`: Jurnal eliminasi konsolidasi terposting.
  - `ic_inter_entity_transfers`: Mutasi pengalihan aset tetap antar-entitas.
- **Domain Events Resmi**:
  - `intercompany.mismatch.detected.v1`
  - `intercompany.mirror.posted.v1`
  - `intercompany.consolidation.locked.v1`

---

## 2. Prosedur Operasional Standar (SOP) Berkala

### 2.1 Siklus Harian (Daily Routine)
1. **Verifikasi Transaksi Cermin**:
   Pastikan setiap transaksi penjualan intra-grup otomatis membentuk tagihan pada entitas pembeli:
   ```bash
   php artisan group:mismatch-sweep --threshold=0
   ```
2. **Penyapuan Likuiditas Kas Terpusat (Cash Pool Sweep)**:
   Dijalankan setiap pukul 17:00 WIB untuk mengonsolidasikan surplus kas:
   ```bash
   php artisan group:cash-pool-sweep --pool=CPOOL-MAIN
   ```

### 2.2 Siklus Akhir Bulan (Month-End Closing Routine)
1. **Akrual Bunga Pinjaman Intra-Grup**:
   Dijalankan pada hari kerja terakhir bulan bersangkutan:
   ```bash
   php artisan group:ic-interest --period=YYYY-MM
   ```
2. **Rekonsiliasi Matriks Netting Bilateral**:
   ```bash
   php artisan group:matrix-reconcile
   ```
3. **Audit Menyeluruh Pra-Konsolidasi**:
   ```bash
   php artisan group:audit
   ```
   Wajib menghasilkan status kode `0` sebelum melanjutkan ke penguncian.
4. **Eksekusi Roll-Up & Penguncian Periode**:
   ```bash
   php artisan group:consolidation-lock --period=YYYY-MM --holding=HOLDING_CORP
   ```

### 2.3 Siklus Akhir Tahun (Year-End Transfer Pricing Routine)
1. **Ekspor Dossier Audit Kepatuhan Transfer Pricing**:
   ```bash
   php artisan group:tp-audit-pack --entity=ENT_MANUF_01 --year=YYYY --rule=TP-MFG-001
   ```
2. **Penyesuaian TP True-Up**:
   Pastikan jurnal true-up ke titik median telah dibukukan sebelum penutupan buku tahunan.

---

## 3. Penanganan Insiden Operasional (Incident Response)

### Insiden 1: Transaksi Asimetris Terdeteksi (`intercompany.mismatch.detected.v1`)
- **Gejala**: Peringatan log deteksi diskrepansi bilateral atau kegagalan perintah `group:mismatch-sweep` (exit 1).
- **Penyebab**: Entitas penjual telah menerbitkan faktur dan memosting transaksi berstatus `posted_seller`, namun entitas pembeli belum menerima barang atau belum menginput nomor `purchase_bill_ref`.
- **Langkah Mitigasi**:
  1. Identifikasi kode transaksi dari laporan sweep:
     ```bash
     php artisan group:mismatch-sweep
     ```
  2. Hubungi bagian akuntansi entitas pembeli untuk mengonfirmasi penerimaan barang/jasa.
  3. Lengkapi referensi tagihan pembelian melalui antarmuka web konsolidasi atau buat transaksi cermin penyeimbang.
  4. Jalankan ulang `group:mismatch-sweep` dan pastikan status kembali hijau (exit 0).

### Insiden 2: Deteksi Siklus Kepemilikan Sirkular (Circular Ownership Loop)
- **Gejala**: Error `InvalidArgumentException: Siklus kepemilikan sirkular terdeteksi melibatkan entitas X`.
- **Penyebab**: Terdapat pendaftaran relasi kepemilikan saham di tabel `ic_ownership_links` yang membentuk loop tertutup ($A \to B \to C \to A$).
- **Langkah Mitigasi**:
  1. Jalankan kueri visualisasi relasi kepemilikan:
     ```bash
     php artisan tinker --execute="dump(app(\Modules\Intercompany\Application\Services\GroupEntityRegistry::class)->buildConsolidationTree('HOLDING_CORP'));"
     ```
  2. Identifikasi entitas yang memicu loop dan periksa akta perubahan modal perseroan.
  3. Nonaktifkan relasi sirkular yang salah dengan mengubah kolom `is_active = false` pada tabel `ic_ownership_links`.

### Insiden 3: Neraca Konsolidasian Tidak Seimbang ($Assets \neq Liabilities + Equity$)
- **Gejala**: Indikator `is_balance_sheet_balanced = false` pada hasil penguncian konsolidasi.
- **Penyebab**: Terdapat jurnal eliminasi manual yang tidak berpasangan seimbang (debit $\neq$ kredit), atau kurs translasi mata uang asing salah input.
- **Langkah Mitigasi**:
  1. Periksa daftar jurnal eliminasi periode berjalan:
     ```bash
     php artisan group:audit
     ```
  2. Periksa pos selisih kurs translasi (*FCTR*) pada entitas anak luar negeri. Pastikan pos aset menggunakan kurs penutup dan pos pendapatan menggunakan kurs rata-rata.
  3. Jika terdapat entri korup, lakukan perbaikan neraca lajur entitas terkait dan jalankan ulang konsolidasi.

### Insiden 4: Prosedur Darurat Pembatalan Kunci Periode (Emergency Period Unlock)
- **Kondisi Khusus**: Penguncian periode konsolidasi telah dilakukan, namun auditor eksternal menemukan kesalahan material yang mewajibkan penerbitan jurnal penyesuaian retrospektif.
- **Otoritas Diperlukan**: Persetujuan tertulis dari Holding CFO dan Kepala Auditor Internal.
- **Langkah Prosedur**:
  1. Dokumentasikan nomor tiket perubahan darurat dan alasan pembatalan kunci.
  2. Eksekusi skrip pembatalan penguncian:
     ```bash
     php artisan tinker --execute="Modules\Intercompany\Domain\Models\EliminationEntry::where('period', 'YYYY-MM')->delete();"
     ```
  3. Lakukan penyesuaian neraca lajur yang diperlukan pada entitas anak terkait.
  4. Jalankan kembali seluruh pengujian invarian:
     ```bash
     php artisan group:audit
     php artisan group:mismatch-sweep
     ```
  5. Kunci kembali periode konsolidasi dan terbitkan hash audit baru:
     ```bash
     php artisan group:consolidation-lock --period=YYYY-MM --holding=HOLDING_CORP
     ```

---

## 4. Perintah Diagnostik & Pengujian Kesehatan Layanan (Health Check)

```bash
# 1. Verifikasi spesifikasi fase 52 (8 token wajib)
/tmp/spek.sh 52

# 2. Uji kepatuhan seluruh test suite penerimaan Intercompany
php artisan test tests/Acceptance/Intercompany/IntercompanyPhase52Test.php

# 3. Uji slice operasional fitur intercompany
php artisan test modules/Intercompany/tests/Feature/IntercompanyOperationsSliceTest.php

# 4. Uji audit dua sumber integritas data
php artisan group:audit

# 5. Uji pemindaian selisih transaksi intra-grup
php artisan group:mismatch-sweep
```
