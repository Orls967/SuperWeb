# Panduan Operasional: Agregasi Neraca Saldo Multi-Entitas & Eliminasi Konsolidasi

## 1. Ikhtisar Alur Kerja Konsolidasi
Proses konsolidasi neraca saldo (Trial Balance) grup dilakukan secara bertahap pada setiap akhir bulan penutupan buku (*month-end closing*):

```mermaid
flowchart TD
    S1["1. Tutup Buku Entitas Anak (Local Closing)"] --> S2["2. Ekspor Neraca Saldo Lokal ke COA Grup"]
    S2 --> S3["3. Agregasi Neraca Saldo via Algoritma D8"]
    S3 --> S4["4. Verifikasi Zero-Sum Balance (Debit = Kredit)"]
    S4 --> S5["5. Tarik Jurnal Eliminasi dari Modul Intercompany"]
    S5 --> S6["6. Hasilkan Neraca Saldo Konsolidasi Grup Final"]
    S6 --> S7["7. Cetak Laporan Keuangan Konsolidasi & Rekonsiliasi Dua Sumber"]
```

---

## 2. Pengecekan Integritas Keseimbangan Jurnal
1. **Pengecekan Pra-Konsolidasi**: Pastikan seluruh entitas telah menyelesaikan transaksi cermin di modul Intercompany dan tidak ada transaksi berstatus gantung (*unmatched*).
2. **Kalkulasi Agregasi Zero-Sum**: Jalankan `php artisan ef:aggregate-trial-balance --period=2026-10`. Algoritma D8 akan menjumlahkan saldo debit dan kredit dari seluruh entitas.
3. **Penyelesaian Diskrepansi**: Apabila $\Delta \neq 0$, sistem menampilkan daftar entitas yang menyebabkan ketidakseimbangan beserta voucher akun terkait.

---

## 3. Ekspor Laporan Resmi
Hasil konsolidasi diekspor menggunakan `ConsolidatedTrialBalanceReportExporter` dengan format CSV ber-checksum SHA-256 yang memuat rincian saldo sebelum eliminasi, jurnal eliminasi, dan saldo akhir konsolidasi.
