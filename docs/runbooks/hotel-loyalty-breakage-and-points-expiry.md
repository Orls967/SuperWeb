# Runbook Operasional: Audit Poin Kedaluwarsa & Pengakuan Pendapatan Breakage (IFRS 15)

## 1. Tujuan
Memberikan panduan standar kepada tim finance dan auditor akuntansi hotel dalam menjalankan prosedur pengakuan pendapatan atas poin loyalitas yang tidak ditebus oleh anggota (Breakage Revenue Recognition) serta penutupan batch poin kedaluwarsa.

## 2. Jadwal & Frekuensi
- **Audit Rutin Mingguan:** Setiap hari Minggu pukul 23:59 WITA dijalankan otomatis via cron scheduler.
- **Audit Penutupan Buku Akhir Bulan:** Tanggal terakhir setiap bulan kalender sebelum laporan keuangan dipublikasikan.

## 3. Prosedur Eksekusi CLI
Jalankan perintah Artisan berikut di server produksi atau container terjadwal:
```bash
php artisan hotel:audit-points-breakage --dry-run
```
Output simulasi akan menampilkan:
- Total batch poin yang telah melewati masa berlaku (730 hari).
- Total kuantitas poin yang hangus.
- Nilai rupiah liabilitas yang akan dibebaskan (`htl:loyalty_points_liability:IDR`).
- Nilai rupiah pendapatan breakage yang akan diakui (`htl:loyalty_breakage_revenue:IDR`).

Bila ringkasan selaras, jalankan eksekusi final:
```bash
php artisan hotel:audit-points-breakage --commit --authorized-by="FINANCE_CHIEF"
```

## 4. Penanganan Anomali & Resolusi Selisih
- **Kasus Selisih Buku Besar:** Bila total liabilitas poin di database tidak sama dengan saldo akun `htl:loyalty_points_liability:IDR`, sistem akan memicu alert darurat `PTS_LIABILITY_DISCREPANCY` ke kanal Slack Finance.
- **Investigasi Batch:** Jalankan query audit `SELECT * FROM htl_travel_pass_point_batches WHERE state = 'EXPIRED' AND breakage_recognized_at IS NULL` untuk mengidentifikasi batch yatim (orphaned batch).

