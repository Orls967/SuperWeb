# ADR-0092: Arsitektur Folio Multi-Payer, Timeshare Fractional Pool, dan Alokasi Paket Destinasi Multi-Vendor

- **Status**: Disetujui (Approved)
- **Tanggal**: 2026-11-08
- **Pengambil Keputusan**: Tim Arsitektur Superweb, Pelaksana G7-P6 (Perhotelan)
- **Konsultasi**: Domain Keuangan (Banking), Properti Riil (RWA), Restoran (Resto), Atraksi (Venue)

---

## 1. Konteks Bisnis & Masalah

Pada pengelolaan hotel skala enterprise, transaksi operasional melibatkan skenario penagihan kompleks:
1. Tamu korporat memerlukan pemisahan tagihan resmi kamar dan pajak ke akun City Ledger perusahaan (*Direct Corporate Billing AR*), sedangkan biaya minibar dan spa dibayar langsung oleh individu tamu.
2. Penyelenggaraan perjamuan (banquet) dan resepsi pernikahan berisiko merugi jika perhitungan HPP bahan baku dapur tidak dikontrol ketat terhadap harga jual paket acara.
3. Unit akomodasi resort villa memerlukan monetisasi fleksibel bagi pemilik fraksional (timeshare) dengan opsi pengalihan kuota hak tinggal yang tidak digunakan ke *hotel public rental pool* dengan pembagian dividen transparan.
4. Paket pariwisata terpadu (*destination packages*) melibatkan beberapa entitas legal mitra (Hotel, Beach Club, Katering, Shuttle Transportasi) yang memerlukan penampungan dana aman (*escrow*) dan pelepasan bertahap berbasis pemenuhan layanan.

---

## 2. Pilihan Desain yang Dipertimbangkan

1. **Pendekatan Folio Tunggal Tanpa Partisi**:
   - Seluruh tagihan dicatat dalam satu saldo tunggal; staf kasir front desk memilah secara manual saat checkout.
   - *Kelemahan*: Rawan kekeliruan audit akuntansi, rawan salah tagih corporate vs personal, dan memperlambat waktu antrean checkout di lobby.
2. **Pendekatan Sub-Folio Multi-Payer Terpisah dengan Router Aturan Otomatis (Terpilih)**:
   - Folio induk memiliki sub-folio terpisah (`PERSONAL` dan `CORPORATE`).
   - Algoritma `FolioSplitRouter` (H6) mengelompokkan kategori tagihan secara deterministik.
   - Pemindahan tagihan ke City Ledger menghasilkan entri piutang terverifikasi.
   - Folio hanya dapat ditutup jika saldo akhir seluruh sub-folio bernilai tepat nol.

---

## 3. Keputusan Arsitektural

1. **Folio Split Routing**:
   - Menggunakan `FolioSplitRouter` untuk memetakan pos tagihan `ROOM_CHARGE`, `HOTEL_TAX`, dan `SERVICE_CHARGE` ke Corporate Payer bila reservasi memiliki jaminan korporat (`corporate_billing_enabled = true`). Seluruh pos di luar itu (misal: `MINIBAR`, `SPA`, `ROOM_SERVICE_FNB`) dialokasikan ke Personal Guest Payer.
2. **Gross Margin Guardrail Banquet**:
   - Menetapkan batas marjin kotor minimal $40\%$ untuk persetujuan dokumen Banquet Event Order (BEO). Pemesanan di bawah batas ini ditolak secara otomatis oleh sistem.
3. **Timeshare Fractional Rental Pool**:
   - Hak tinggal mingguan yang tidak diambil hingga $T-14$ hari sebelum tanggal menginap dialihkan ke rental pool umum properti. Pendapatan kotor dipotong management fee $15\%$, dan sisa $85\%$ didistribusikan pro-rata ke akun wallet pemegang token fraksional.
4. **Alokasi Nilai Wajar Paket Multi-Vendor**:
   - Menggunakan pustaka resmi `Shared\Domain\Algorithms\Settlement\BundleSspAllocator` (PSAK 72 / IFRS 15) untuk menghitung proporsi nilai wajar masing-masing vendor dari total harga bundel.
   - Pembayaran tamu ditampung di clearing escrow dan dilepas bertahap saat konfirmasi pemenuhan layanan masing-masing vendor tercatat.

---

## 4. Konsekuensi

- **Positif**:
  - Transparansi penuh audit piutang korporat dan rekonsiliasi kasir front desk.
  - Bebas risiko salah bayar pada penagihan multi-payer.
  - Peningkatan okupansi hotel resort melalui optimalisasi unit villa timeshare yang tidak terpakai.
  - Kepatuhan standar akuntansi pendapatan kontrak multi-komponen internasional.
- **Mitigasi**:
  - Penambahan tabel pendukung `htl_fase92_*` dengan foreign key dan indeks berkinerja tinggi.
  - Penjaminan zero debt arsitektur (A1–A13) dan pemenuhan ambang ketat baris kode.

