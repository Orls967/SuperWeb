# Pedoman Penyelesaian Sengketa Kurs Aliansi & Chargeback Antar-Mitra — Fase 112

## Bab 1: Definisi & Kategori Sengketa Transaksi Aliansi
Dalam pertukaran poin bilateral antara program Global Travel Pass dan mitra aliansi transportasi (maskapai penerbangan, operator kereta api cepat), sengketa dapat timbul akibat perbedaan waktu pemrosesan transaksi (settlement lag), fluktuasi nilai tukar konversi antar-mata uang, atau pembatalan sepihak oleh pengguna akhir (chargeback).

### Kategori Sengketa Formal:
1. **Sengketa Perbedaan Nilai Konversi (Exchange Ratio Dispute):**
   Terjadi bila kurs konversi dasar ($K_{base}$) yang diterapkan oleh Travel Pass Hotel pada jam $T$ berbeda dengan tabel kurs yang dicatat oleh gateway mitra pada jam $T + \Delta t$.
2. **Sengketa Transaksi Ganda (Duplicate Billing):**
   Tamu mengeklaim bahwa satu kali pemindahan poin menyebabkan saldo poin hotel terpotong dua kali di basis data atau miles maskapai terbit ganda.
3. **Sengketa Kegagalan Kredit Mitra (Uncredited Partner Transaction):**
   Poin hotel telah berhasil didebit dari saldo tamu dan saldo liabilitas telah berkurang, namun sistem mitra gagal menambahkan saldo miles ke akun penerbangan tamu dalam SLA 24 jam.
4. **Sengketa Pembatalan Penukaran (Redemption Cancellation & Refund):**
   Tamu membatalkan penerbangan yang dibeli menggunakan miles hasil konversi poin dan meminta pengembalian poin kembali ke akun Travel Pass Hotel.

---

## Bab 2: Prosedur Resolusi Sengketa Tiga Tahap
1. **Tahap 1: Pembekuan Rekonsiliasi Otomatis (Automatic Transaction Freeze):**
   Begitu notifikasi sengketa diterima, sistem secara otomatis menandai ID transaksi terkait dengan status `DISPUTED` pada tabel `htl_alliance_transfer_logs`. Hal ini mencegah dana yang disengketakan dimasukkan ke dalam batch settlement netting bulanan.
2. **Tahap 2: Pembuktian Kriptografis (Cryptographic Hash Validation):**
   Kedua belah pihak mempertukarkan sertifikat transaksi yang dihasilkan oleh algoritma `PartnerPointConverter` (H12). Bila hash SHA-256 pada payload request identik dengan hash pada log konfirmasi gateway mitra, transaksi dinyatakan sah secara teknis dan kewajiban pembayaran tetap mengikat.
3. **Tahap 3: Mediasi Komite Aliansi Finansial:**
   Jika selisih nilai sengketa melebihi ambang batas ekuivalen Rp 50.000.000, kasus dieskalasi ke Komite Aliansi Finansial Bersama yang bersidang setiap hari Kamis untuk menerbitkan Berita Acara Rekonsiliasi Final.

---

## Bab 3: Tata Kelola Pengembalian Dana (Refund & Reverse Posting)
Bila sengketa dimenangkan oleh tamu atau mitra menyetujui pembatalan:
- Sistem mengeksekusi aksi kompensasi balik: mengkreditkan kembali poin ke batch asal dengan masa kedaluwarsa yang dipulihkan.
- Jurnal pembalik otomatis:
  - **Debit:** `htl:partner_points_payable:IDR` (Membatalkan utang ke mitra).
  - **Debit:** `htl:transfer_fee_revenue:IDR` (Mengembalikan komisi transfer).
  - **Kredit:** `htl:loyalty_points_liability:IDR` (Memulihkan liabilitas poin aktif).

