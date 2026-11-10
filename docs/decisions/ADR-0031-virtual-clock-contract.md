# ADR-0031: Kontrak Waktu Virtual (SimClockInterface) Tanpa Manipulasi Carbon::setTestNow() di Runtime

- **Status:** DITERIMA
- **Tanggal:** 2026-11-10
- **Pengambil Keputusan:** Tim Arsitektur Core Platform & Pelaksana W8-P3
- **Konteks:** Simulasi bisnis jangka panjang memerlukan orkestrasi waktu yang dapat maju secara terkompresi tanpa memengaruhi waktu sistem operasi atau lingkungan produksi.

---

## 1. Konteks & Masalah

Penggunaan fungsi global seperti `Carbon::setTestNow()` untuk memajukan waktu virtual dalam simulasi multi-hari memiliki dampak samping (*side-effects*) yang sangat berbahaya:
1. Mengubah waktu proses global pada level thread web server (PHP-FPM / Octane), merusak timestamp sesi HTTP pengguna lain, audit logs, dan token JWT.
2. Menyebabkan ketidakkonsistenan pada tugas background (*cron jobs* / queued workers) yang sedang berjalan paralel.
3. Rentan terhadap kebocoran waktu simulasi ke kode produksi riil.

## 2. Pilihan yang Dipertimbangkan

1. **Opsi A:** Memakai `Carbon::setTestNow()` dengan pemulihan di blok `finally`. (DITOLAK: Tidak aman pada lingkungan multi-threaded dan konkurensi).
2. **Opsi B:** Menggunakan interface `SimClockInterface` yang terikat pada `ClockInterface` (PSR-20 compliant) yang secara eksplisit di-inject ke seluruh handler simulasi. (DITERIMA).

## 3. Keputusan

Kami memutuskan untuk mengimplementasikan antarmuka formal `SimClockInterface` yang menyediakan kontrak eksplisit:
- `currentDay(): int`: Mendapatkan nomor hari virtual berjalan (1..N).
- `advanceDay(int $days = 1): void`: Memajukan hari virtual secara diskret.
- `now(): CarbonInterface`: Menghasilkan representasi waktu virtual saat ini tanpa menyentuh jam sistem global.
- `reset(): void`: Mengembalikan clock virtual ke hari pertama.

Dilarang keras memanggil `Carbon::setTestNow()` dalam kode kernel simulasi maupun handler modul domain.

## 4. Konsekuensi

- **Positif:** Isolasi waktu virtual terjamin 100%. Lingkungan konkurensi aman dari kontaminasi jam sistem.
- **Negatif:** Setiap tick handler yang membutuhkan waktu virtual harus menerima instance `CarbonInterface $virtualNow` yang disuplai oleh kernel saat eksekusi.

