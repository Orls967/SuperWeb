# Panduan Operasional Front Desk: Validasi Travel Pass & Check-In Anggota — Fase 112

## 1. Prosedur Check-in Tamu Anggota Travel Pass
1. Minta kartu identitas fisik dan kode QR / nomor Travel Pass tamu saat tiba di konter front desk.
2. Pindai QR Pass melalui antarmuka PMS atau masukkan nomor pass (format `TP-XXXX-XXXX`).
3. Sistem secara otomatis menampilkan:
   - Status Tier (Silver / Gold / Platinum / Black).
   - Hak istimewa yang berlaku (free upgrade, late check-out, complimentary breakfast).
   - Saldo poin PTS aktif.
4. Terapkan benefit sesuai tier tamu tanpa memungut biaya tambahan.

## 2. Penanganan Tamu Award Night (Kamar Hasil Tukar Poin)
- Tamu yang menginap dengan award night telah melunasi tarif kamar melalui pemotongan poin pusat.
- Front desk **HANYA** memungut deposit insidental standar sesuai kebijakan hotel (misal Rp 500.000 per malam).
- Tagihan kamar pada folio kamar secara otomatis bernilai Rp 0 dan diselesaikan oleh akun kliring loyalitas pusat (`htl:loyalty_redemption_settlement:IDR`).
- Biaya konsumsi tambahan (minibar, F&B room service, spa) tetap dibebankan ke folio tamu dan diselesaikan saat check-out.

## 3. Resolusi Masalah Saldo & Downgrade
- Jika tamu mengklaim poin menginap sebelumnya belum masuk, periksa tab mutasi poin di layar PMS. Poin biasanya terkredit otomatis saat proses Night Audit.
- Bila ada perbedaan persepsi tier, jelaskan dengan santun bahwa sistem menerapkan evaluasi rolling 365 hari dengan masa tenggang grace period 90 hari.

