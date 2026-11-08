# Manual Operasional: Audit Liabilitas Poin & Rekonsiliasi Keuangan Hotel — Fase 112

## 1. Pendahuluan & Ruang Lingkup Audit
Manual ini menjadi panduan kerja resmi bagi Internal Auditor, Chief Financial Officer (CFO), dan Tim Akuntansi Pusat dalam memverifikasi integritas finansial program loyalitas Global Travel Pass. Tujuan utama audit adalah memastikan bahwa nilai moneter liabilitas poin yang tercatat pada buku besar akuntansi (`htl:loyalty_points_liability:IDR`) selalu seimbang secara eksak terhadap total saldo poin yang aktif pada basis data anggota, tanpa ada selisih satu rupiah pun.

---

## 2. Struktur Akun Buku Besar Terkait Loyalitas
| Kode Akun Buku Besar | Nama Akun | Kategori Akun | Saldo Normal | Penjelasan Fungsi Akuntansi |
|---|---|---|---|---|
| `htl:loyalty_points_liability:IDR` | Kewajiban Poin Loyalitas Beredar | Kewajiban (Liability) | Kredit | Menampung nilai moneter poin aktif milik seluruh anggota |
| `htl:loyalty_program_expense:IDR` | Beban Pemasaran Program Loyalitas | Beban (Expense) | Debit | Biaya promosi yang diakui saat poin baru diterbitkan ke tamu |
| `htl:loyalty_breakage_revenue:IDR` | Pendapatan Poin Hangus (Breakage) | Pendapatan (Revenue) | Kredit | Pendapatan resmi saat poin kedaluwarsa setelah 730 hari |
| `htl:partner_points_payable:IDR` | Utang Poin Mitra Aliansi | Kewajiban (Liability) | Kredit | Kewajiban pembayaran kas kepada maskapai mitra atas transfer |
| `htl:transfer_fee_revenue:IDR` | Pendapatan Biaya Administrasi Aliansi| Pendapatan (Revenue) | Kredit | Pendapatan komisi 3,5% dari transfer poin ke mitra aliansi |
| `htl:loyalty_redemption_settlement:IDR`| Kliring Penebusan Hadiah Internal | Kliring (Clearing) | Netral | Akun penengah saat kamar gratis award night ditebus |
| `clearing:external:IDR` | Kliring Kas & Rekening Bank Mitra | Kliring (Clearing) | Netral | Perantara penyelesaian dana keluar/masuk antar-bank |

---

## 3. Prosedur Uji Petik & Audit Berkala

### 3.1 Langkah 1: Ekstraksi Data Saldo Poin Aktif
Jalankan skrip ekstraksi basis data untuk menghitung total poin aktif:
$$\text{Total Points Active} = \sum_{j \in \text{Pass}} \text{pts\_balance}_j$$
Hitung nilai rupiah liabilitas teoritis:
$$\text{Theoretical Liability} = \text{Total Points Active} \times \text{Rp } 100$$

### 3.2 Langkah 2: Pengecekan Saldo Buku Besar
Ambil saldo akhir akun buku besar melalui antarmuka akuntansi atau query ledger:
$$\text{Ledger Liability} = \text{Balance of } (\text{htl:loyalty\_points\_liability:IDR})$$

### 3.3 Langkah 3: Evaluasi Selisih (Discrepancy Formula)
$$\text{Delta} = |\text{Theoretical Liability} - \text{Ledger Liability}|$$
- **Bila $\text{Delta} = 0$:** Laporan audit berstatus **HIJAU / CLEAN PASS**.
- **Bila $\text{Delta} > 0$:** Sistem membunyikan alarm audit dan membekukan sementara aksi penerbitan poin massal hingga akar masalah diidentifikasi.

---

## 4. Prosedur Investigasi Anomali & Rekonsiliasi Batch
Jika terjadi selisih, ikuti langkah investigasi berikut:
1. **Periksa Batch Kedaluwarsa yang Tertunda (Stale Batches):**
   Cari batch yang memiliki tanggal kedaluwarsa lampau namun statusnya masih `ACTIVE` atau `PARTIALLY_REDEEMED`:
   ```sql
   SELECT id, travel_pass_id, remaining_pts, expires_at 
   FROM htl_travel_pass_point_batches 
   WHERE state IN ('ACTIVE', 'PARTIALLY_REDEEMED') 
     AND expires_at < NOW();
   ```
   Bila ditemukan, jalankan aksi `RecognizeLoyaltyBreakageRevenueAction` untuk memproses penutupan batch dan posting jurnal pengakuan breakage.

2. **Periksa Transaksi Transfer Aliansi yang Menggantung (Pending Sagas):**
   Cari rekaman transaksi transfer mitra yang berstatus `PROCESSING` melebihi 15 menit:
   ```sql
   SELECT id, partner_code, points_debited, status, created_at 
   FROM htl_alliance_transfer_logs 
   WHERE status = 'PROCESSING' 
     AND created_at < NOW() - INTERVAL '15 MINUTE';
   ```
   Picu kompensasi saga otomatis untuk membatalkan transaksi dan mengembalikan saldo poin tamu.

---

## 5. Tata Kelola Kepatuhan & Tanda Tangan Auditor
Setiap lembar laporan rekonsiliasi akhir bulan wajib dibubuhi tanda tangan digital kriptografis (HMAC-SHA256) oleh Lead Auditor dan Direktur Keuangan. Berkas audit disimpan dalam repositori dokumen yang tidak dapat diubah (immutable storage) selama minimal 10 tahun sesuai undang-undang perpajakan dan tata kelola perusahaan terbuka.

