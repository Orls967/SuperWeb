# Manual Akuntansi & Prosedur Audit — Folio Split & City Ledger AR

## 1. Landasan Prinsip & Standar Pelaporan
Manual akuntansi ini menetapkan perlakuan jurnal buku besar ganda (*double-entry bookkeeping*) untuk siklus pemisahan tagihan multi-pembayar (*Multi-Payer Split Folio*), piutang usaha korporat (*Corporate City Ledger Accounts Receivable*), dan penutupan folio bersaldo nol (*Zero-Balance Checkout Principle*).

---

## 2. Struktur Bagan Akun Buku Besar (Chart of Accounts)
Modul Perhotelan menggunakan bagan akun khusus yang terintegrasi langsung dengan Ledger Inti Superweb:

```
+------------------------------------+----------------------------------------+
| Kode Akun Buku Besar               | Klasifikasi & Deskripsi                |
+------------------------------------+----------------------------------------+
| htl:guest_folio:{folio_id}:IDR     | Liabilitas Penampungan Saldo Tamu      |
| htl:city_ledger:IDR                | Aset Lancar Piutang Usaha Korporat     |
| htl:revenue:room:IDR               | Pendapatan Operasional Sewa Kamar      |
| htl:revenue:fb:IDR                 | Pendapatan Makanan & Minuman Restoran  |
| htl:revenue:spa:IDR                | Pendapatan Layanan Kebugaran Spa       |
| htl:tax_payable:pbjt:IDR           | Hutang Pajak Barang & Jasa Tertentu    |
| htl:escrow:destination_package:IDR | Liabilitas Rekening Escrow Titipan     |
| investor:dividend:{uuid}:IDR       | Liabilitas Dividen Pemilik Timeshare   |
+------------------------------------+----------------------------------------+
```

---

## 3. Alur Jurnal Pemisahan Tagihan (Folio Split Accounting)

### 3.1. Pengakuan Beban Kamar & Insidental
Saat malam audit dijalankan (*Night Audit Run*), sistem membukukan pendapatan kamar dan pajak PBJT:
- **Debit:** `htl:guest_folio:{folio_id}:IDR` (Total Biaya Kamar + Pajak)
- **Kredit:** `htl:revenue:room:IDR` (Tarif Kamar Bersih)
- **Kredit:** `htl:tax_payable:pbjt:IDR` (Pajak Hotel 10%)

### 3.2. Pemindahan ke Piutang Korporat (Transfer to City Ledger)
Saat pemisahan sub-folio korporat dieksekusi:
- **Debit:** `htl:city_ledger:IDR` (Porsi Tagihan Dijamin Perusahaan)
- **Kredit:** `htl:guest_folio:{folio_id}:IDR` (Porsi Tagihan Dijamin Perusahaan)

*Catatan Audit:* Jurnal ini menyeimbangkan saldo kamar pada folio tamu menjadi IDR 0, sehingga tamu hanya berkewajiban menyelesaikan sisa tagihan insidental pribadi.

### 3.3. Pelunasan Sub-Folio Personal Tamu
Saat tamu melunasi tagihan minibar & spa melalui kartu debit/kredit di front desk:
- **Debit:** `bank:settlement:card:IDR` (Penerimaan Kas/Bank)
- **Kredit:** `htl:guest_folio:{folio_id}:IDR` (Pelunasan Saldo Personal)

---

## 4. Matriks Jadwal Umur Piutang (Aging AR Schedule) & Provisi Kerugian
Setiap akhir periode akuntansi bulanan, seluruh saldo piutang di `htl:city_ledger:IDR` diklasifikasikan ke dalam bucket umur piutang:

```
+------------------+---------------------+-------------------+---------------------+
| Kategori Bucket  | Rentang Waktu       | Provisi Kerugian  | Tindakan Penagihan  |
+------------------+---------------------+-------------------+---------------------+
| Lancar (Current) | 0 - 30 Hari         | 0.5% Cadangan     | Rekonsiliasi Faktur |
| Perhatian Khusus | 31 - 60 Hari        | 2.0% Cadangan     | Pengingat Tertulis  |
| Kurang Lancar    | 61 - 90 Hari        | 10.0% Cadangan    | Somasi & Hold Pagu  |
| Macet (Doubtful) | > 90 Hari           | 50.0% Cadangan    | Terminasi Kontrak   |
+------------------+---------------------+-------------------+---------------------+
```

### 4.1. Pemblokiran Fasilitas Kredit Otomatis
Akun korporat yang memiliki tagihan tertunggak melampaui **60 hari kalender** secara otomatis ditandai berstatus `CREDIT_SUSPENDED`. Pemesanan kamar baru yang diajukan oleh staf perusahaan bersangkutan akan ditolak oleh Central Reservation System hingga seluruh kewajiban tertunggak diselesaikan.
