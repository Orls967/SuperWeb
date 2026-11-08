# Runbook Operasional: Rekonsiliasi & Netting Penyelesaian Poin Mitra Aliansi

## 1. Tujuan
Menyelenggarakan proses pencocokan data transaksi pertukaran poin bilateral antara platform Travel Pass Hotel dan mitra eksternal (maskapai penerbangan, operator kereta api) untuk memastikan tidak ada tagihan ganda atau selisih piutang/utang.

## 2. Parameter Rekonsiliasi
- **Akun Buku Besar:** `htl:partner_points_payable:IDR` (Kewajiban pembayaran ke mitra) dan `htl:transfer_fee_revenue:IDR` (Pendapatan komisi konversi).
- **Periode Netting:** Tanggal 1 hingga tanggal 28 setiap bulan kalender.
- **Toleransi Selisih:** Rp 0 (Zero Tolerance).

## 3. Alur Rekonsiliasi
1. Unduh rekap file transaksi penyelesaian dari gateway mitra (format JSON/CSV).
2. Jalankan perintah verifikasi silang:
   ```bash
   php artisan hotel:reconcile-partner-points --partner="GARUDA_AIR" --month="2026-11"
   ```
3. Sistem akan mencocokkan setiap `idempotency_key` dan memverifikasi sertifikat kriptografis algoritma `PartnerPointConverter` (H12).
4. Jika status `MATCHED`: terbitkan bukti potong settlement netting dan instruksi transfer pembayaran ke mitra.
5. Jika status `DISCREPANCY`: transfer pembayaran ditahan otomatis (freeze) dan tiket sengketa diterbitkan ke tim aliansi bisnis.

