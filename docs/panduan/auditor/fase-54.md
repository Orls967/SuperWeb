# Panduan Pemeriksaan Independen: Auditor — Tata Kelola Finansial Grup & Audit Trail (Fase 54)

## 1. Landasan Pemeriksaan & Ruang Lingkup Audit
Panduan ini ditujukan bagi Auditor Internal (Satuan Pengawasan Intern - SPI) dan Auditor Eksternal (Kantor Akuntan Publik - KAP / Big-4) dalam menjalankan perikatan audit umum maupun kepatuhan atas laporan keuangan entitas konsolidasi platform Enterprise Finance.

Pemeriksaan mencakup 5 area pengujian utama:
1. **Verifikasi Integritas Buku Besar & Bagan Akun Standar**: Memastikan prinsip *double-entry bookkeeping* ditaati, saldo debit sama dengan kredit pada seluruh posting, dan tidak ada transaksi dengan nominal negatif atau bernilai nol.
2. **Kepatuhan Penutupan Buku & Pencegahan *Backdating***: Memvalidasi ketiadaan pencatatan mutasi susulan pada periode akuntansi yang telah berstatus *locked* (`ef_financial_period_locks`).
3. **Pemeriksaan Efektivitas Titik Kendali (Risk Control Matrix - RCM)**: Menguji efektivitas pengujian kontrol operasional otomatis terhadap bukti transaksi pengadaan, persediaan, dan margin penjualan.
4. **Pemeriksaan Pemisahan Tugas (SoD) & Validitas Pengecualian**: Meneliti riwayat pelanggaran benturan wewenang dan memastikan seluruh dispensasi yang diterbitkan memiliki otorisasi sah serta tidak kedaluwarsa.
5. **Verifikasi Integritas Data Pack Audit (SHA-256 Hash Manifest)**: Memeriksa rantai segel kriptografis atas arsip data transaksi yang diserahkan kepada tim pemeriksa eksternal.

---

## 2. Pengujian Pengendalian Otomatis (Testing of Controls)

### 2.1 Eksekusi Command Audit Dua Sumber (`enterprise:audit`)
Pemeriksaan awal kesehatan sistem wajib dilakukan dengan mengeksekusi perintah CLI:
```bash
php artisan enterprise:audit
```
Kriteria Keberhasilan Audit:
- **Status Keluaran**: Wajib mengembalikan pesan `Status: OK` dan kode keluar `0` (*exit 0*).
- **Diskrepansi**: Angka `Discrepancies` wajib tepat bernilai `0`.
- **Validasi Rekonsiliasi**:
  - Saldo buku besar AR = saldo total faktur piutang pelanggan.
  - Saldo buku besar AP = saldo total tagihan pemasok.
  - Saldo buku besar Aset = nilai buku register aset tetap terdepresiasi.
  - Saldo buku besar Persediaan = total nilai kuantitas bin stock gudang dikalikan biaya standar.
  - Saldo laba rugi bersih pada tabel kunci periode (`ef_financial_period_locks`) = ringkasan nominal penutupan periode fiskal.

### 2.2 Uji Titik Kendali Internal (RCM Execution)
Auditor dapat memicu pengujian populasi kontrol otomatis secara independen dengan ukuran sampel yang dapat disesuaikan:
```bash
php artisan enterprise:control-tests --sample-size=100
```
Titik kendali yang dievaluasi mencakup:
1. `CP-01-FOUR-EYES`: Validasi prinsip Maker-Checker pada siklus penganggaran dan jurnal penyesuaian memorial.
2. `CP-02-THREE-WAY-MATCH`: Kecocokan tiga arah antara Purchase Order, Goods Receipt Note (GRN), dan Vendor Invoice sebelum otorisasi pembayaran.
3. `CP-03-WEIGHING-TOLERANCE`: Kepatuhan batas toleransi deviasi timbangan logistik muatan truk/kargo ($\le 1.5\%$).
4. `CP-04-MARGIN-FLOOR`: Perlindungan margin harga jual minimum distributor/agen terhadap penetapan harga tebus dasar.

Hasil uji disimpan pada tabel `ef_control_test_runs` dan wajib berstatus `EFFECTIVE`. Jika terdapat temuan penyimpangan (`deviation_count > 0`), sistem secara otomatis menciptakan tiket tugas korektif di antarmuka tata kelola.

---

## 3. Prosedur Audit Substantif Saldo & Transaksi

### 3.1 Prosedur Rekonsiliasi Bank & Kas
Auditor wajib membandingkan rekening koran perbankan (*bank statement*) dengan saldo akun kas/bank di buku besar:
1. Unduh rekapitulasi mutasi via berkas `bank_reconciliation_statements.csv` dari paket data audit.
2. Pastikan tidak ada transaksi kas keluar tanpa didukung voucher penutupan kas (*payment disbursement voucher*) bernomor seri gapless.
3. Selidiki setiap pos rekonsiliasi yang berumur $> 30$ hari (outstanding checks atau deposits in transit).

### 3.2 Audit Komitmen Anggaran (Budget Encumbrance & Liquidation)
Auditor memeriksa kepatuhan reservasi dana belanja:
1. Akses tabel `ef_budget_encumbrances`.
2. Verifikasi siklus hidup komitmen:
   $$\text{Status: RESERVED} \xrightarrow{\text{Penerimaan Faktur}} \text{Status: LIQUIDATED} \xrightarrow{\text{Pembatalan PO}} \text{Status: RELEASED}$$
3. Buktikan bahwa nilai sisa pagu bebas (`available_amount`) pada saat transaksi diajukan tidak pernah berada di bawah angka 0.

### 3.3 Verifikasi Kepatuhan Pemisahan Tugas (SoD Matrix)
Jalankan audit pemisahan wewenang dengan perintah:
```bash
php artisan enterprise:sod-scan --strict
```
Auditor wajib memastikan:
- Tidak ada pengguna yang memegang pasangan peran terlarang (misal: penginput tagihan merangkap kasir transfer kas).
- Setiap akun yang terdaftar dalam dispensasi darurat memiliki surat tugas resmi yang terarsip di berkas `ef_sod_exceptions` dan tidak melampaui tanggal `expires_at`.

---

## 4. Verifikasi Integritas Digital Audit Data Pack

Platform menyediakan fasilitas ekspor mandiri paket bukti perikatan audit KAP dalam berkas terkompresi ZIP melalui antarmuka web `/enterprise-finance/audit-pack` atau baris perintah:
```bash
php artisan enterprise:data-pack 2026-10 --entity=CORP-HOLDING
```

### 4.1 Struktur Berkas Paket Data Audit
Paket data audit yang dihasilkan memiliki struktur berkas standar:
```
audit_pack_CORP-HOLDING_202610.zip
├── manifest.json
├── general_ledger_entries.csv
├── trial_balance_summary.csv
├── bank_reconciliation_statements.csv
├── fixed_assets_register.csv
├── tax_recon_summary.csv
└── sod_violations_log.csv
```

### 4.2 Prosedur Validasi Segel Kriptografis SHA-256
Auditor wajib menguji keaslian dan ketiadaan perubahan data pasca penutupan periode menggunakan verifikasi checksum:
1. Ekstrak berkas `manifest.json`.
2. Hitung nilai hash SHA-256 atas masing-masing berkas CSV secara lokal, misalnya menggunakan utilitas sistem:
   ```bash
   shasum -a 256 general_ledger_entries.csv
   ```
3. Cocokkan nilai hash kalkulasi lokal dengan nilai yang tercatat pada node `files[...].sha256` di `manifest.json`.
4. Jika ditemukan perbedaan satu karakter sekalipun, data pack dinyatakan telah mengalami manipulasi data (*tampered evidence*) dan perikatan audit wajib menerbitkan laporan pengecualian.

---

## 5. Ringkasan Kuesioner Audit Internal (Audit Checklist)

| No | Fokus Pemeriksaan | Pertanyaan Audit | Referensi Kepatuhan |
| :--- | :--- | :--- | :--- |
| 1 | Penguncian Buku | Apakah periode fiskal sebelumnya telah dikunci di `ef_financial_period_locks` dan tidak terdapat jurnal susulan? | Standar Akuntansi Keuangan (SAK) & SOX 404 |
| 2 | Keseimbangan Jurnal | Apakah seluruh entri pada `bank_ledger_entries` berpasangan debit-kredit dengan selisih mutlak 0? | Prinsip Double-Entry Konsep §A2.1 |
| 3 | Pengendalian Pagu | Apakah sistem menolak reservasi belanja jika saldo pagu bebas tidak mencukupi (Hard-Stop)? | Tata Kelola Belanja RKAP Korporat |
| 4 | Pemisahan Wewenang | Apakah penugasan peran operator bebas dari benturan wewenang pada matriks 12 aturan SoD? | COSO Internal Control Framework |
| 5 | Bukti Kriptografis | Apakah berkas manifest SHA-256 cocok 100% dengan rekapitulasi buku besar dan subledger? | Standar Bukti Audit Digital ISA 500 |
