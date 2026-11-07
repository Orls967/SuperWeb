# Spesifikasi Arsitektur Data — Kepemilikan Timeshare & Paket Wisata Terpadu

## 1. Desain Skema Database & Relasi Entitas
Dokumen ini mendokumentasikan spesifikasi struktur relasional tabel basis data pada modul Perhotelan Fase 92 untuk mendukung fungsionalitas kepemilikan vila fraksional (*Timeshare*) dan pemesanan paket destinasi multi-vendor (*Destination Package*).

```
+---------------------------------------------------------------------------------+
|                       DIAGRAM RELASI ENTITAS FASE 92                            |
+---------------------------------------------------------------------------------+
|  htl_corporate_accounts (1) <---> (N) htl_sub_folios (N) <---> (1) htl_folios   |
|  htl_timeshare_units    (1) <---> (N) htl_timeshare_schedules                   |
|  htl_timeshare_units    (1) <---> (N) htl_timeshare_investors                   |
|  htl_destination_packages (1) <-> (N) htl_destination_package_bookings          |
|  htl_destination_package_bookings (1) <-> (N) htl_destination_package_allocations|
+---------------------------------------------------------------------------------+
```

---

## 2. Definisi Struktur Kolom & Tipe Data

### 2.1. Tabel `htl_corporate_accounts`
Menyimpan data master perusahaan rekanan penerima fasilitas penagihan langsung (City Ledger):
- `id` (UUID, Primary Key): Pengenal unik entitas korporat.
- `company_name` (VARCHAR(120), Not Null): Nama resmi badan hukum perusahaan.
- `tax_id_npwp` (VARCHAR(40), Nullable): Nomor Pokok Wajib Pajak untuk keperluan penerbitan e-Faktur.
- `billing_email` (VARCHAR(100), Not Null): Alamat email resmi departemen keuangan rekanan.
- `credit_limit_idr` (BIGINT, Default 10000000): Plafon batas penjaminan biaya dalam satuan Rupiah.
- `current_outstanding_idr` (BIGINT, Default 0): Akumulasi saldo tagihan yang belum dilunasi.
- `status` (VARCHAR(30), Default 'ACTIVE'): Status operasional ('ACTIVE', 'SUSPENDED', 'TERMINATED').

### 2.2. Tabel `htl_sub_folios`
Menyimpan partisi pos penagihan multi-pembayar di bawah satu induk folio reservasi:
- `id` (UUID, Primary Key): Pengenal unik sub-folio.
- `folio_id` (UUID, Foreign Key ke `htl_folios`): Referensi induk folio kamar.
- `payer_type` (VARCHAR(30), Not Null): Klasifikasi penjamin biaya ('PERSONAL', 'CORPORATE').
- `corporate_id` (UUID, Nullable, Foreign Key ke `htl_corporate_accounts`): ID perusahaan penjamin.
- `total_charges_idr` (BIGINT, Default 0): Akumulasi pos debet tagihan pada sub-folio ini.
- `total_payments_idr` (BIGINT, Default 0): Akumulasi pembayaran atau transfer kredit yang diterima.
- `balance_idr` (BIGINT, Default 0): Saldo bersih terutang (`total_charges_idr - total_payments_idr`).
- `status` (VARCHAR(30), Default 'OPEN'): Status penagihan ('OPEN', 'INVOICED', 'SETTLED', 'DISPUTED_HOLD').

### 2.3. Tabel `htl_timeshare_schedules`
Menyimpan alokasi giliran pemanfaatan minggu tahunan vila fraksional:
- `id` (UUID, Primary Key): Pengenal unik jadwal.
- `timeshare_unit_id` (UUID, Foreign Key ke `htl_timeshare_units`): Referensi unit vila.
- `owner_id` (VARCHAR(64), Not Null): Pengenal akun pemilik atau investor token.
- `week_number` (INT, Not Null): Nomor urut minggu kalender tahunan (1 hingga 52).
- `season` (VARCHAR(30), Not Null): Klasifikasi musim kunjungan ('PEAK', 'SHOULDER', 'REGULAR').
- `status` (VARCHAR(30), Default 'OWNER_OCCUPIED'): Status pemanfaatan ('OWNER_OCCUPIED', 'RENTAL_POOL', 'EXCHANGED').
- `rental_income_gross_idr` (BIGINT, Default 0): Pendapatan sewa kotor yang dihasilkan jika masuk pool.
- `dividend_net_idr` (BIGINT, Default 0): Dividen bersih yang disetorkan ke pemilik pasca fee resort 15%.

### 2.4. Tabel `htl_destination_package_allocations`
Menyimpan rincian pemecahan escrow nilai paket wisata multi-operator:
- `id` (UUID, Primary Key): Pengenal unik alokasi.
- `package_booking_id` (UUID, Foreign Key ke `htl_destination_package_bookings`): Referensi reservasi paket.
- `vendor_type` (VARCHAR(40), Not Null): Klasifikasi entitas operator ('HOTEL', 'VENUE', 'LOGISTICS', 'RESTO').
- `vendor_reference` (VARCHAR(64), Not Null): Kode identifikasi komponen layanan vendor terkait.
- `ssp_amount_idr` (BIGINT, Not Null): Nilai alokasi hak pendapatan bersih hasil kalkulasi algoritma SSP.
- `release_status` (VARCHAR(30), Default 'PENDING'): Status pencairan dana escrow ('PENDING', 'RELEASED', 'REFUNDED').
- `released_at` (TIMESTAMP, Nullable): Waktu pencairan dana saat milestone terpenuhi.
