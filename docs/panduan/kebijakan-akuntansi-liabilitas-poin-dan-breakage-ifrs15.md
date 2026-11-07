# Kebijakan Akuntansi: Liabilitas Poin Loyalitas & Pengakuan Breakage Berdasarkan IFRS 15 / PSAK 72 — Fase 112

## 1. Landasan Standar Akuntansi
Berdasarkan PSAK 72 / IFRS 15 (Pendapatan dari Kontrak dengan Pelanggan), poin loyalitas yang diberikan kepada tamu dikategorikan sebagai kewajiban pelaksanaan terpisah (material right). Nilai transaksi transaksi awal harus dialokasikan antara pendapatan kamar/jasa yang diserahkan dan nilai wajar poin loyalitas yang diterbitkan.

## 2. Pengakuan Awal Liabilitas Poin
Setiap poin yang diterbitkan dinilai menggunakan nilai penebusan wajar historis (standar sistem: 1 PTS = Rp 100).
Jurnal Penerbitan Poin:
- **Debit:** `htl:loyalty_program_expense:IDR` (Beban Operasional Pemasaran Loyalitas)
- **Kredit:** `htl:loyalty_points_liability:IDR` (Kewajiban Kontrak Poin Beredar)

## 3. Pelunasan Saat Penebusan Hadiah
Ketika tamu menggunakan poin untuk menginap atau ditukar dengan produk mitra:
- **Debit:** `htl:loyalty_points_liability:IDR` (Pelepasan Liabilitas)
- **Kredit:** `htl:room_revenue:IDR` / `htl:loyalty_redemption_settlement:IDR` / `htl:partner_points_payable:IDR`

## 4. Pengakuan Pendapatan Poin Kedaluwarsa (Breakage)
Poin yang tidak ditebus oleh anggota hingga melewati masa kedaluwarsa (730 hari) diakui sebagai pendapatan resmi platform perhotelan:
- **Debit:** `htl:loyalty_points_liability:IDR` (Penghapusan Liabilitas Kedaluwarsa)
- **Kredit:** `htl:loyalty_breakage_revenue:IDR` (Pendapatan Lain-lain Breakage)

Metode alokasi FIFO menjamin bahwa batch poin terlama selalu dihapuskan terlebih dahulu, sehingga liabilitas yang tercatat pada neraca keuangan selalu mencerminkan nilai wajar poin aktif terkini.

