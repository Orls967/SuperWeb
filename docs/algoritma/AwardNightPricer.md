# Algoritma Bersertifikat: AwardNightPricer (H11)

## 1. Identifikasi
- **Kode:** H11
- **Nama:** Dynamic Award Night Room Pricing Engine with Floor Guarantee
- **Kelas:** Algoritma Dynamic Yield & Revenue Management
- **Modul:** `Hotel` (`Modules\Hotel\Domain\Algorithms\AwardNightPricer`)

## 2. Deskripsi Matematis & Fungsional
Algoritma ini menetapkan biaya poin yang diperlukan seorang anggota untuk menebus kamar gratis (award room night). Alih-alih menggunakan bagan award tetap (fixed award chart) yang kaku, algoritma ini menyeimbangkan antara keterjangkauan tamu dan profitabilitas pemilik properti:

1. **Kurva Okupansi Sigmoid:**
   Ketika okupansi properti rendah ($< 50\%$), tarif poin mendekati baseline biaya standar untuk merangsang penukaran kamar dan menjaga tingkat keterisian.
   Ketika okupansi meningkat di atas $70\%$, tarif poin naik secara eksponensial halus mengikuti kurva sigmoid untuk membatasi kanibalisasi tamu berbayar tunai.
2. **Pengali Musim & Event Terdekat:**
   Memperhitungkan faktor pengali musim liburan atau konser venue terdekat ($S \in [1.0, 1.8]$).
3. **Diskon Tingkat Keanggotaan (Tier Benefit):**
   - Silver: diskon 0%
   - Gold: diskon 5%
   - Platinum: diskon 10%
   - Black: diskon 15%
4. **Proteksi Batas Bawah Finansial (115% Floor Rule):**
   Tarif poin akhir setelah dikonversi ke nilai tukar moneter tidak pernah boleh kurang dari 115% dari biaya pokok marjinal kamar ($C_{marginal}$). Nilai ini menjamin properti selalu mendapatkan margin laba minimal 15% dari program reward pusat.

## 3. Kompleksitas & Karakteristik
- **Waktu:** $\mathcal{O}(1)$ kalkulasi matematis tertutup.
- **Ruang:** $\mathcal{O}(1)$.
- **Deterministik:** 100% deterministik dengan pembulatan integer ke atas (ceil) untuk menghindari fraksi pecahan moneter.
- **Sertifikasi:** Mengembalikan `AwardNightPriceCertificate` yang memuat input parameter, perhitungan formula, floor verification boolean, dan hash integritas.

