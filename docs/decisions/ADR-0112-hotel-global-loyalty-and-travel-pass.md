# ADR-0112: Hotel Global Loyalty Program, Travel Pass & Cross-Ecosystem Points Clearing

## Status
Diterima (Accepted)

## Konteks
Sebagai bagian dari ekspansi global grup perhotelan dan hiburan terpadu (KONSEP 11F), platform membutuhkan ekosistem loyalitas terpadu yang melayani ribuan properti, restoran, klub malam, arena festival (modul Venue), maskapai mitra, dan moda transportasi. Program loyalitas konvensional sering mengalami tantangan:
1. **Liabilitas Poin Menggelembung:** Poin yang tidak pernah kedaluwarsa atau tidak terdistribusi secara akurat menimbulkan beban liabilitas jangka panjang tanpa kepastian pengakuan pendapatan breakage.
2. **Kekakuan Penukaran Kamar Gratis (Award Night):** Nilai kamar tetap saat okupansi 99% merugikan properti, sedangkan nilai tetap saat okupansi 20% membuat tamu enggan menukarkan poin.
3. **Kompleksitas Settlement Aliansi Mitra:** Pertukaran poin antar korporasi (misal transfer poin ke maskapai penerbangan) rentan terhadap inkonsistensi saldo bila salah satu pihak mengalami kegagalan transmisi jaringan.
4. **Kesenjangan Data Personalisasi:** Rekomendasi tujuan liburan sering kali mengabaikan histori belanja lintas lini dan melanggar privasi tamu.

## Keputusan Arsitektur
1. **Algoritma PointsLiabilityFifo (H10):**
   - Menggunakan model batch penerbitan berbasis tanggal (FIFO - First-In First-Out) dengan masa berlaku deterministik (misalnya 730 hari).
   - Pengakuan liabilitas dicatat per batch pada akun `htl:loyalty_points_liability:IDR`.
   - Ketika poin kedaluwarsa, algoritma menghitung tingkat breakage historis dan membalik liabilitas menjadi pendapatan resmi `htl:loyalty_breakage_revenue:IDR` disertai sertifikat SHA-256 yang dapat diverifikasi oleh auditor eksternal.
2. **Algoritma Dynamic AwardNightPricer (H11):**
   - Tarif poin kamar berfluktuasi secara dinamis mengikuti fungsi kurva sigmoid terhadap okupansi kamar hotel hari itu.
   - Diterapkan guardrail ketat batas bawah (floor price): nilai ganti rugi poin kepada pemilik properti tidak boleh lebih rendah dari 115% dari baseline biaya marjinal kamar untuk menjamin kelayakan ekonomi properti.
3. **Algoritma PartnerPointConverter (H12) & Saga Kompensasi:**
   - Pertukaran poin bilateral antara Travel Pass dan mitra aliansi (seperti maskapai mitra) diproses melalui perhitungan kurs tukar dinamis yang memperhitungkan nilai tukar mata uang, spread kemitraan, dan conversion fee transaksional.
   - Seluruh alur transfer dijalankan oleh `HotelPartnerPointTransferSaga` dengan status terorkestrasi: jika mitra menolak atau transaksi timeout, poin dikembalikan ke saldo tamu secara instan dan pemotongan jurnal dibatalkan.
4. **Algoritma GlobalTierQualifier (H13):**
   - Tingkatan keanggotaan (Silver, Gold, Platinum, Black) dihitung secara deterministik menggunakan evaluasi rolling window 365 hari.
   - Mempertimbangkan kombinasi bobot malam menginap (nights weight) dan total belanja teragregasi di seluruh lini ekosistem dengan kebijakan pelindung soft-landing penurunan tier.
5. **Kepatuhan Privasi Data:**
   - Seluruh data preferensi dan rekomendasi personalisasi mematuhi regulasi PDP/GDPR dengan enkripsi data sensitif dan kepatuhan penuh terhadap preferensi opt-out / unsubscribe kampanye.

## Konsekuensi Positif
- Transparansi penuh pada neraca keuangan: liabilitas loyalitas selalu seimbang dengan mutasi buku besar.
- Pendapatan breakage diakui secara sah dan dapat dipertanggungjawabkan di hadapan auditor keuangan.
- Integrasi antar mitra aliansi memiliki resiliensi tinggi berkat mekanisme saga berkompensasi.
- Properti hotel terlindungi dari kerugian penukaran poin di musim puncak (peak season) melalui penetapan harga award dinamis.

