# Runbook Operasional — Prosedur Penukaran Hak Guna Vila di Pasar Sekunder (Swap)

## 1. Maksud & Ruang Lingkup
Runbook ini memandu Tim Hubungan Pemilik Vila (*Owner Relations Coordinator*) dalam memverifikasi permohonan pertukaran minggu hak guna vila (*Timeshare Week Swap*) antar pemilik unit fraksional di pasar sekunder.

---

## 2. Alur Operasional Pertukaran Minggu (Swap Lifecycle)

```
+----------------------------------------------------------------------------------+
|                   ALUR PROSEDUR PENUKARAN JADWAL (SWAP)                          |
+----------------------------------------------------------------------------------+
|  [Pemilik A Mengajukan Swap] --> Memilih Minggu Target Milik Pemilik B           |
|  [Pemeriksaan Musim]         --> Evaluasi Kategori (Peak / Shoulder / Regular)   |
|  [Kalkulasi Balancing Fee]   --> Dikenakan Biaya Penyeimbang Bila Musim Berbeda  |
|  [Persetujuan Kedua Pihak]   --> Eksekusi Pertukaran & Penerbitan Sertifikat H8  |
+----------------------------------------------------------------------------------+
```

---

## 3. Langkah Demi Langkah Verifikasi & Otorisasi
1. Verifikasi bahwa kedua jadwal berstatus `OWNER_OCCUPIED` dan belum pernah didaftarkan ke dalam *Rental Pool Program* untuk tahun berjalan.
2. Periksa apakah pemilik pemohon memiliki tunggakan iuran pemeliharaan tahunan (*Maintenance Fee Arrears*). Jika terdapat tunggakan, permohonan swap ditahan (*Swap Suspended*) sampai tunggakan dilunasi.
3. Jalankan aksi eksekusi sistem melalui modul pertukaran sekunder:
   ```php
   $result = $exchangePool->executeScheduleSwap($requesterScheduleId, $targetScheduleId);
   ```
4. Sistem secara atomik menukar data `owner_id` pada tabel `htl_timeshare_schedules`, mendebit *balancing fee* jika berlaku, dan mengirimkan email konfirmasi kalender baru kepada kedua pemilik.

---

## 4. Daftar Periksa (Checklist) Pasca Swap
- [ ] Konfirmasi kalender kamar terbarui pada CRS
- [ ] Jurnal penyeimbang balancing fee tercatat di buku besar
- [ ] Notifikasi SMS dan email konfirmasi terkirim ke kedua pemilik vila
- [ ] Status audit kepemilikan terverifikasi 100% konsisten

