# Algoritma Bersertifikat: PartnerPointConverter (H12)

## 1. Identifikasi
- **Kode:** H12
- **Nama:** Bilateral Alliance Partner Point Converter & Netting Evaluator
- **Kelas:** Algoritma Konversi Finansial & Kliring Aliansi
- **Modul:** `Hotel` (`Modules\Hotel\Domain\Algorithms\PartnerPointConverter`)

## 2. Deskripsi Matematis & Fungsional
Algoritma ini menghitung pertukaran poin bilateral antara program Travel Pass Hotel dan mitra ekosistem aliansi (seperti Maskapai Penerbangan, Tiket Kereta Api, dan Penyedia Transportasi):

1. **Rasio Pertukaran Dinamis:**
   Menghitung jumlah unit reward mitra yang dihasilkan berdasarkan rasio dasar ($K_{base}$), faktor penyesuaian pasar ($M_t$), dan kuantitas poin hotel yang diajukan.
2. **Kalkulasi Biaya Konversi (Platform Transfer Fee):**
   Memotong biaya administrasi aliansi ($\phi = 3.5\%$) dari nilai nominal transfer yang dialokasikan sebagai pendapatan platform: `htl:transfer_fee_revenue:IDR`.
3. **Penyelesaian Netting Aliansi (Bilateral Netting):**
   Memastikan sisa nilai nominal dialokasikan tepat ke akun utang mitra: `htl:partner_points_payable:IDR`.
4. **Verifikasi Kesetaraan:**
   Menjamin tidak ada sen yang hilang dalam pembulatan integer dengan menetapkan aturan penyesuaian selisih pembulatan (rounding penny adjustment) ke sisi pendapatan fee platform.

## 3. Kompleksitas & Karakteristik
- **Waktu:** $\mathcal{O}(1)$ operasi aritmatika integer.
- **Ruang:** $\mathcal{O}(1)$.
- **Deterministik:** 100% deterministik tanpa pembulatan acak.
- **Sertifikasi:** Mengembalikan `PartnerPointConversionCertificate` yang dapat diaudit oleh sistem mitra untuk penyelesaian klaim bulanan.

