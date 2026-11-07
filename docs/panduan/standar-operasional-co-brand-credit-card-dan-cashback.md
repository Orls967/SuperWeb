# Standar Operasional: Kartu Kredit Co-Brand & Cashback Loyalitas Global — Fase 112

## Bab 1: Kerjasama Bank Penerbit & Kartu Co-Brand
Program Global Travel Pass bekerja sama dengan bank mitra perbankan terkemuka untuk menerbitkan Kartu Kredit Co-Brand Travel Pass (Tingkat Platinum dan Infinite). Pemegang kartu kredit co-brand menikmati jalur percepatan perolehan poin dan fasilitas finansial premium saat bertransaksi di dalam maupun di luar ekosistem perhotelan.

### Mekanisme Perolehan Poin Kartu Co-Brand:
- **Transaksi di Dalam Ekosistem Hotel & Venue:** 3x Lipat Poin PTS untuk setiap pembelanjaan Rp 10.000.
- **Transaksi Perjalanan Internasional (Airlines, Mata Uang Asing):** 2x Lipat Poin PTS untuk setiap pembelanjaan Rp 10.000.
- **Transaksi Ritel Harian Umum:** 1x Poin PTS untuk setiap pembelanjaan Rp 10.000.

---

## Bab 2: Mekanisme Cashback Langsung ke Dompet Digital
Selain penukaran kamar dan tiket pesawat, poin PTS dapat dikonversi menjadi saldo kas belanja (cashback) yang langsung masuk ke dompet digital anggota di modul Perbankan/Fintech:
1. **Nilai Tukar Cashback:**
   1 PTS = Rp 80 Saldo Dompet Tunai (terdapat selisih 20% dibandingkan nilai penebusan kamar untuk mendorong penggunaan poin di dalam fasilitas hotel).
2. **Posting Buku Besar Pembayaran Cashback:**
   - **Debit:** `htl:loyalty_points_liability:IDR` (Nilai nominal penuh liabilitas poin yang dipotong, misal Rp 100 per poin).
   - **Kredit:** `clearing:external:IDR` (Dana tunai yang ditransfer ke dompet digital tamu, misal Rp 80 per poin).
   - **Kredit:** `htl:transfer_fee_revenue:IDR` (Margin konversi kas yang diperoleh grup hotel, misal Rp 20 per poin).
3. **Batas Likuiditas & Anti-Pencucian Uang (AML):**
   Maksimum konversi cashback harian dibatasi sebesar Rp 5.000.000 per anggota dan wajib melalui verifikasi keamanan biometrik atau PIN dua langkah (2FA).

