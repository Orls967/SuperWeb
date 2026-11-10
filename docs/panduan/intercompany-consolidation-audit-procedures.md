# Prosedur Audit & Penutupan Buku Konsolidasi Grup (SOP-IC-04)

**Pemilik Prosedur**: Tim Konsolidasi Keuangan & Audit Internal Grup  
**Frekuensi**: Bulanan (Setiap tanggal 1-3 bulan berikutnya)  

---

## 1. Tahapan Pra-Penutupan (*Pre-Closing Reconciliation*)
Sebelum jurnal eliminasi dijalankan:
1. **Verifikasi Cut-Off Transaksi**:
   - Pastikan seluruh anak perusahaan telah menyelesaikan input mutasi transaksi penjualan/pembelian intercompany paling lambat pukul 18.00 tanggal akhir bulan.
   - Periksa bahwa tidak ada transaksi berstatus `DRAFT` atau `SUBMITTED` yang menggantung.
2. **Pengecekan Akrual Bunga**:
   - Jalankan `php artisan ic:accrue-interest` untuk memastikan seluruh beban dan pendapatan bunga pinjaman intercompany bulan berjalan telah terakrual beserta pemotongan PPh 23/26.
3. **Pengecekan Alokasi Jasa Manajemen**:
   - Jalankan `php artisan ic:allocate-costs` untuk memastikan alokasi biaya bersama (headcount/revenue ratio) telah terbit dan diterima pembeli.

---

## 2. Eksekusi Eliminasi & Rekonsiliasi Otomatis
1. **Jalankan Command Eliminasi Konsolidasi**:
   ```bash
   php artisan ic:eliminate-period --period=YYYY-MM
   ```
2. **Review Matriks Eliminasi**:
   - Unduh laporan matriks eliminasi bilateral:
     ```bash
     php artisan ic:export-matrix --period=YYYY-MM --out=storage/reports/matrix-YYYY-MM.csv
     ```
   - Verifikasi bahwa total piutang yang dieliminasi sama persis dengan total hutang yang dieliminasi.
3. **Penyelesaian Transaksi Transit**:
   - Bila terdapat selisih, pastikan pos transit telah disetujui oleh controller kedua entitas dan didukung oleh dokumen pengiriman fisik.

---

## 3. Pengujian Integritas Audit Dua Sumber (`group:audit`)
1. Eksekusi perintah audit konsolidasi:
   ```bash
   php artisan group:audit
   ```
2. Pastikan hasil pengujian menyatakan `0 discrepancy` dan status `OK`.
3. Simpan ringkasan keluaran audit sebagai berkas pendukung (*audit working paper*) sebelum laporan konsolidasi diserahkan kepada Direksi dan Auditor Eksternal.

