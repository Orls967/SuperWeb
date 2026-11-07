# Runbook Operasional — Prosedur Penagihan & Mitigasi Risiko Kredit City Ledger

## 1. Maksud & Ruang Lingkup
Runbook ini menjadi pegangan kerja bagi Manajer Kredit (*Credit Manager*), Staf Penagihan Piutang (*Accounts Receivable Accountant*), dan *Director of Finance* dalam menangani siklus penagihan faktur korporat, eskalasi keterlambatan pembayaran, dan mitigasi risiko gagal bayar piutang City Ledger.

---

## 2. Alur Penagihan Berdasarkan Umur Piutang (Dunning Workflow)

```
+-------------------------------------------------------------------------------+
|               DUNNING WORKFLOW MATRIX & COLLECTION PROTOCOL                   |
+---------------+------------------------+--------------------------------------+
| Bucket Hari   | Status Kolektibilitas  | Aksi Komunikasi & Tindakan Hukum     |
+---------------+------------------------+--------------------------------------+
| 1 - 25 Hari   | Periode Lancar         | Pengiriman e-Faktur Pajak & Kwitansi |
| 26 - 30 Hari  | Jatuh Tempo Mendekat   | Reminder Email Ramah dari Bagian AR  |
| 31 - 45 Hari  | Keterlambatan Ringan   | Telepon Konfirmasi Jadwal Kliring    |
| 46 - 60 Hari  | Keterlambatan Serius   | Surat Peringatan 1 (SP-1) Resmi      |
| 61 - 90 Hari  | Macet (Doubtful)       | Surat Peringatan 2 & Suspend Kredit  |
| > 90 Hari     | Wanprestasi Total      | Somasi Kuasa Hukum & Blacklist B2B   |
+---------------+------------------------+--------------------------------------+
```

---

## 3. Langkah Demi Langkah Penanganan Kasus Keterlambatan

### 3.1. Penanganan Hari ke-31 s/d 45 (Follow-Up Awal)
1. Periksa apakah dokumen kelengkapan tagihan (Salinan voucher reservasi kamar yang ditandatangani tamu, billing folio terinci, kwitansi asli bermaterai) telah diterima oleh bagian keuangan korporat klien.
2. Hubungi PIC Penagihan korporat untuk memverifikasi apakah faktur telah dijadwalkan dalam antrean pembayaran minggu berikutnya.
3. Catat hasil komunikasi ke dalam log catatan penagihan pada portal City Ledger.

### 3.2. Penanganan Hari ke-46 s/d 60 (Eskalasi Peringatan SP-1)
1. Bila tidak ada komitmen pembayaran pasti, terbitkan Surat Peringatan Pertama (SP-1) yang ditandatangani oleh Credit Manager dan Financial Controller.
2. Lampirkan rincian denda keterlambatan sebesar 1.5% per bulan atas saldo tertunggak (sesuai klausul kontrak penjaminan biaya).
3. Turunkan plafon kredit berjalan sebesar 50% untuk membatasi eksposur risiko tambahan.

### 3.3. Penanganan Hari ke-61 s/d 90 (Pemblokiran Kredit & SP-2)
1. Ubah status akun korporat pada sistem menjadi `CREDIT_SUSPENDED`.
2. Sistem Central Reservation secara otomatis menolak pembebanan kamar baru (*Direct Billing Blocked*).
3. Terbitkan Surat Peringatan Kedua (SP-2) dengan tenggat waktu pelunasan maksimal 7 hari kerja.
4. Hubungi direksi korporat atau pejabat pembuat komitmen instansi terkait.

### 3.4. Penanganan Lebih Dari 90 Hari (Eskalasi Hukum & Write-Off)
1. Limpahkan berkas penagihan kepada Bagian Legal dan Kuasa Hukum Korporasi Hotel untuk penerbitan Somasi Terakhir.
2. Bentuk cadangan kerugian piutang tak tertagih sebesar 50% dari nilai sisa piutang pada jurnal akuntansi akhir kuartal.
3. Masukkan entitas korporat dan para penjaminnya ke dalam *Blacklist Database Mitra Bisnis Superweb*.
