# Runbook Operasional — Rekualifikasi Tingkat Loyalitas & Prosedur Night Audit

## 1. Identifikasi Prosedur & Siklus Eksekusi
Runbook ini memandu Tim Sistem Informasi Hotel (*IT Hotel Systems*) dan Manajer Audit Malam (*Night Audit Manager*) dalam menjalankan siklus tutup buku harian (*Daily Night Audit Closure*) dan rekualifikasi tahunan status tingkat keanggotaan tamu (*Loyalty Tier Annual Requalification*).

```
+----------------------------------------------------------------------------------+
|                   ALUR KERJA AUDIT MALAM & REKUALIFIKASI TIER                    |
+----------------------------------------------------------------------------------+
|  [Pukul 02:00 WITA] --> Kunci Transaksi Tanggal Berjalan (HotelBusinessDate)     |
|  [Posting Kamar]    --> Eksekusi Batch Room Charges & Pajak PBJT                 |
|  [Akumulasi Malam]  --> Evaluasi Algoritma H7 (LoyaltyNightTierEngine)           |
|  [Buka Hari Baru]   --> Majukan Business Date & Buka Jalur Transaksi Kasir Baru  |
+----------------------------------------------------------------------------------+
```

---

## 2. Langkah-Langkah Operasional Tutup Buku Harian

### 2.1 Pengecekan Pra-Audit (Pre-Audit Sanity Checks)
1. Buka dasbor Night Audit pada sistem PMS.
2. Pastikan tidak ada tamu dengan status kedatangan yang masih `EXPECTED_ARRIVAL` (seluruh tamu wajib diproses menjadi `CHECKED_IN` atau ditandai sebagai `NO_SHOW`).
3. Pastikan seluruh kasir outlet restoran (K17), bar, dan spa telah melakukan penutupan kasir shift malam (*End of Shift Batch Close*).

### 2.2 Eksekusi Perintah Audit Malam
Jalankan perintah batch night audit melalui CLI:
```bash
php artisan hotel:night-audit --business-date=$(date +%Y-%m-%d)
```
Perintah ini akan:
- Memposting tagihan kamar dan pajak 10% ke seluruh folio kamar aktif.
- Memperbarui akumulasi malam menginap terverifikasi pada profil loyalitas tamu.
- Memvalidasi apakah tamu berhak mengalami peningkatan tier (*Tier Upgrade*) atau mempertahankan status (*Tier Maintained*).

---

## 3. Penanganan Kegagalan & Rollback (Incident Handling)
Bila proses night audit terhenti di tengah jalan karena kegagalan jaringan basis data:
1. Periksa log audit pada file `storage/logs/hotel-audit.log`.
2. Jangan menjalankan posting kamar secara manual untuk menghindari pembebanan ganda (*Double Posting Violation*).
3. Jalankan kembali perintah dengan flag `--resume-interrupted` untuk melanjutkan transaksi dari posisi checkpoint terakhir secara idempoten.
