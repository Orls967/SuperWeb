# Algoritma Bersertifikat: GlobalTierQualifier (H13)

## 1. Identifikasi
- **Kode:** H13
- **Nama:** Global Multi-Vector Loyalty Tier Qualifier & Grace Period Engine
- **Kelas:** Algoritma Status Keanggotaan & Evaluasi Vektor
- **Modul:** `Hotel` (`Modules\Hotel\Domain\Algorithms\GlobalTierQualifier`)

## 2. Deskripsi Matematis & Fungsional
Algoritma ini mengevaluasi status tier anggota Travel Pass global (Silver, Gold, Platinum, Black) secara berkelanjutan:

1. **Jendela Evaluasi Bergerak 365 Hari:**
   Menghitung akumulasi malam menginap yang memenuhi syarat ($N_{qualifying}$) dan pembelanjaan moneter terkonsolidasi ($S_{qualifying}$) dalam 365 hari terakhir.
2. **Kriteria Ambang Batas Ganda:**
   - **Black:** $\ge 75$ malam ATAU $\ge \text{Rp } 250.000.000$ belanja.
   - **Platinum:** $\ge 40$ malam ATAU $\ge \text{Rp } 100.000.000$ belanja.
   - **Gold:** $\ge 20$ malam ATAU $\ge \text{Rp } 35.000.000$ belanja.
   - **Silver:** Status dasar keanggotaan.
3. **Mekanisme Soft-Landing Downgrade:**
   Jika seorang anggota gagal mempertahankan tier saat masa evaluasi berakhir, algoritma tidak langsung menurunkan ke tier terbawah, melainkan memberikan masa tenggang 90 hari (Grace Period) atau penurunan bertahap maksimal 1 level di bawah tier sebelumnya.
4. **Fast-Track Milestone Bonus:**
   Mendeteksi pencapaian ambang percepatan (misal menginap di 5 properti berbeda dalam 30 hari) untuk memberikan lompatan kenaikan tier seketika.

## 3. Kompleksitas & Karakteristik
- **Waktu:** $\mathcal{O}(1)$ evaluasi kondisi deterministik.
- **Ruang:** $\mathcal{O}(1)$.
- **Deterministik:** 100% konsisten terhadap riwayat transaksi.
- **Sertifikasi:** Mengembalikan `GlobalTierQualificationCertificate` yang mencakup riwayat parameter input, tier hasil evaluasi, status grace period, dan hash pengesahan.

