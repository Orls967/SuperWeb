# Panduan Auditor: Verifikasi Integritas Finansial Grup & Bukti Audit (Fase 54)

## 1. Peran & Aksesibilitas
Auditor Internal maupun Kantor Akuntan Publik (KAP) memiliki akses baca penuh (*read-only audit access*) dan wewenang mengunduh bukti forensik finansial di seluruh entitas konglomerasi.

## 2. Fitur Audit Utama

### A. Ekspor Paket Data Bukti Audit (Auditor Data Pack Generator)
1. Akses menu **Audit & Forensik $\to$ Paket Data Bukti** (`/enterprise-finance/audit-pack`).
2. Masukkan Entitas dan Periode Audit yang dituju (misal: `CORP-HOLDING`, `2026-10`).
3. Klik **Generate Audit Evidence Pack (ZIP)**.
4. Sistem memproses secara deterministik berkas-berkas berikut ke dalam satu arsip ZIP:
   - `general_ledger_entries.csv`: Seluruh mutasi jurnal buku besar terinci.
   - `trial_balance_summary.csv`: Neraca saldo konsolidasi per akun.
   - `bank_reconciliation_statements.csv`: Rekonsiliasi mutasi rekening koran vs buku bank.
   - `fixed_assets_register.csv`: Daftar aset tetap beserta nilai buku dan akumulasi penyusutan.
   - `tax_recon_summary.csv`: Bukti setor PPN dan pemotongan PPh.
   - `sod_violations_log.csv`: Catatan pelanggaran SoD dan dispensasi aktif.
   - `manifest.sha256`: Hash ringkasan SHA-256 tiap berkas untuk menjamin *non-repudiation* dan anti-tampering.

### B. Audit Dua Sumber Otomatis (`enterprise:audit`)
Auditor dapat memicu atau memverifikasi keluaran command audit dua sumber melalui antarmuka atau terminal:
```bash
php artisan enterprise:audit --entity=CORP-HOLDING --period=2026-10
```
Pemeriksaan mencakup:
- Saldo buku besar vs saldo subledger pembantu (selisih wajib 0).
- Saldo komitmen tercatat di `ef_budget_encumbrances` vs Purchase Order terbuka.
- Tidak adanya pagu anggaran negatif tanpa otorisasi soft-stop.
- Keseimbangan neraca konsolidasi ($\Sigma \text{Debit} = \Sigma \text{Kredit}$).
- Keabsahan nomor seri faktur pajak tanpa lompatan (gapless sequence).

### C. Penelaahan Matriks Risiko & Kontrol (RCM)
1. Akses menu **Tata Kelola $\to$ Uji Kontrol Internal (RCM)** (`/enterprise-finance/controls`).
2. Tinjau riwayat pengujian harian otomatis 4 titik kendali:
   - `CP-01-FOUR-EYES`: Validasi Maker $\neq$ Checker.
   - `CP-02-THREE-WAY-MATCH`: Kecocokan PO, GRN, dan Invoice.
   - `CP-03-WEIGHING-TOLERANCE`: Toleransi penerimaan fisik.
   - `CP-04-MARGIN-FLOOR`: Batas bawah harga pokok penjualan.
3. Seluruh defisiensi kontrol (`DEFICIENT` atau `FAILED`) dapat ditelusuri ke nomor tiket mitigasi di Kotak Masuk Tugas (`CoreTask`).

