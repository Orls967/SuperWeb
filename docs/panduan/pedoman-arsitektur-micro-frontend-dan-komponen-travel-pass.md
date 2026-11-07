# Pedoman Arsitektur Komponen Antarmuka & Micro-Frontend Program Loyalitas — Fase 112

## Bab 1: Prinsip Desain Modular & Reusabilitas Blade
Antarmuka program Global Travel Pass dibangun menggunakan pendekatan komponen modular berbasis Laravel Blade Component yang dirancang agar dapat disematkan secara mulus di berbagai modul (Hotel PMS, Venue Ticket Portal, Guest Mobile WebView, dan Resto POS).

### Standar Komponen UI:
1. **Pemisahan State & Presentasi:** Logika bisnis dan pemanggilan API backend diisolasi pada Controller dan Action; komponen Blade hanya menerima data props terstruktur (DTO atau array representasional).
2. **Alpine.js untuk Interaktivitas Lokal:** Komponen yang membutuhkan interaksi pengguna langsung (seperti kalkulator pertukaran poin atau pembukaan modal rincian batch) menggunakan state reaktif Alpine.js tanpa memerlukan ketergantungan JavaScript pihak ketiga yang berat.
3. **Desain Adaptif Multi-Perangkat:** Komponen otomatis menyesuaikan ukuran layar dari tampilan desktop monitor resolusi tinggi di kantor front desk hingga layar ponsel pintar tamu.

---

## Bab 2: Struktur Komponen Kunci

### 2.1 Komponen Radar Progres Tier (`tier_progress_radar_gauge`)
Menampilkan diagram lingkaran SVG interaktif yang mengilustrasikan jarak pencapaian anggota menuju tier berikutnya:
- Menghitung persentase sisa malam menginap ($\frac{N_{current}}{N_{target}} \times 100\%$).
- Menghitung persentase sisa pembelanjaan moneter ($\frac{S_{current}}{S_{target}} \times 100\%$).
- Menampilkan indikator visual berkilau saat anggota telah mendekati ambang kenaikan tingkat (di atas 80%).

### 2.2 Komponen Kalender Poin Dinamis (`award_calendar_heatmap`)
Menyajikan matriks kalender bulanan dengan gradasi warna berbasis okupansi hotel:
- Warna Hijau Muda: Tarif poin terendah (musim sepi / off-peak).
- Warna Kuning / Emas: Tarif poin standar (musim normal).
- Warna Jingga / Merah Muda: Tarif poin tinggi (musim puncak / peak season), tetap terlindungi oleh batas bawah 115%.

### 2.3 Komponen Garis Waktu Penuaan FIFO (`points_expiry_fifo_timeline`)
Menampilkan visualisasi kartu tumpukan (stacked card timeline) untuk setiap batch poin aktif:
- Menampilkan tanggal perolehan dan jumlah poin awal.
- Menampilkan sisa poin yang belum terpakai.
- Menampilkan hitung mundur hari menuju kedaluwarsa dengan penanda bahaya merah jika jatuh tempo dalam waktu kurang dari 30 hari.

---

## Bab 3: Tata Kelola Aksesibilitas (WCAG AA Compliance)
- Seluruh elemen interaktif memiliki atribut `aria-label`, `aria-expanded`, dan `role` yang sesuai.
- Rasio kontras teks terhadap latar belakang dipertahankan minimal 4.5:1 untuk teks normal dan 3:1 untuk teks tebal/besar.
- Navigasi penuh didukung melalui tombol `Tab`, `Enter`, dan `Escape` untuk memastikan kemudahan akses bagi pengguna difabel.

