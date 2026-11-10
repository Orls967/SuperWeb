# Spesifikasi Teknis: Sinkronisasi Waktu Virtual & Frekuensi Eksekusi (Fase 67)

Dokumen ini mendefinisikan spesifikasi arsitektur sinkronisasi waktu virtual, penjadwalan multi-frekuensi (harian, mingguan, bulanan, tahunan), kalkulasi offset epoch, dan pencegahan drift pada layanan **Virtual Clock Synchronizer** platform Superweb.

---

## 1. Latar Belakang & Kebutuhan Bisnis

Ekosistem bisnis enterprise tidak beroperasi pada satu siklus frekuensi tunggal:
- **Transaksi Operasional Kasir & Kamar:** Berjalan setiap hari (*daily frequency*).
- **Rekonsiliasi Bank & Pembagian Royalti:** Berjalan setiap minggu (*weekly frequency*).
- **Akrual Bunga Pinjaman & Depresiasi Aset:** Berjalan setiap bulan (*monthly frequency*, siklus 30 hari).
- **Tutup Buku Laporan Keuangan Tahunan:** Berjalan setiap akhir tahun (*annual frequency*, siklus 365 hari).

Jika seluruh proses dipaksa berjalan setiap hari, beban I/O basis data akan meningkat drastis secara tidak perlu. Di sisi lain, jika proses mingguan/bulanan dilewatkan, laporan keuangan tidak akan akurat.

`VirtualClockSynchronizerService` menyediakan penjadwalan deterministik berbasis offset hari virtual dari titik awal (*genesis epoch*).

---

## 2. Model Penjadwalan Berbasis Frekuensi

Setiap modul dapat mendaftarkan jadwal tugas berkala dengan spesifikasi interval hari:

```php
$syncService->registerSchedule(
    scheduleKey: 'monthly_depreciation',
    frequency: 'monthly',
    intervalDays: 30
);
```

### Evaluasi Jatuh Tempo (`getDueSchedules`):
Pada setiap pergantian hari virtual $T$:
1. Sistem mengiterasi seluruh jadwal terdaftar.
2. Memeriksa selisih hari terhadap eksekusi terakhir:
   $$\Delta T = T_{\text{current}} - T_{\text{last\_run}}$$
3. Jika $\Delta T \ge \text{interval\_days}$, atau jika $T_{\text{current}} = 1$ dan tugas belum pernah berjalan, jadwal tersebut dimasukkan ke dalam daftar tugas jatuh tempo (*due schedules*).
4. Setelah seluruh handler dalam tugas selesai dieksekusi, sistem menandai $T_{\text{last\_run}} = T_{\text{current}}$.

---

## 3. Pemetaan Kalender Virtual dari Titik Genesis

Meskipun kernel simulasi mengoperasikan hari virtual dalam bilangan bulat ($1, 2, \dots, N$), modul-modul pelaporan membutuhkan representasi tanggal kalender riil (YYYY-MM-DD) untuk pembuatan faktur, kwitansi, dan laporan pajak.

### Rumus Konversi Penanggalan:
Diberikan tanggal awal genesis $D_0 = \text{"2026-01-01"}$ dan hari virtual saat ini $T$:
$$\text{Timestamp Virtual} = \text{strtotime}(D_0) + ((T - 1) \times 86400)$$
$$\text{Tanggal Virtual} = \text{date}(\text{"Y-m-d"}, \text{Timestamp Virtual})$$

Dengan formula deterministik ini:
- Hari 1 = `2026-01-01`
- Hari 31 = `2026-01-31`
- Hari 32 = `2026-02-01`
- Hari 365 = `2026-12-31`

Penanggalan virtual ini konsisten tanpa bergantung pada zona waktu server lokal (*timezone independent* berkat UTC epoch offset).

---

## 4. Jitter Buffer & Penanganan Kompresi Drift

Dalam pengujian skenario konkurensi tinggi, event-event bisnis dapat mengalami variasi jeda kedatangan mikrodetik. Kelas `VirtualTimeJitterService` menambahkan variasi latensi terukur:
$$\text{Jitter}_{\text{ms}} = \text{LCG}(\text{seed}_{\text{jitter}}, T) \pmod{\text{maxJitter}}$$
Jitter ini sepenuhnya deterministik berdasarkan seed, sehingga tidak merusak reprodusibilitas rantai hash akhir.

