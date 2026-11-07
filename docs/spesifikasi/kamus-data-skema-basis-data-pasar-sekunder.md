# Kamus Data Skema Basis Data Pasar Sekunder (Fase 116)

## 1. Pendahuluan
Kamus data ini mendokumentasikan spesifikasi teknis tabel, kolom, tipe data, indeks, dan batasan integritas relasional (*foreign key constraints*) yang dibuat melalui migrasi `2026_11_07_116200_create_ven_secondary_market_and_dynamic_bundling_tables.php` pada modul Superweb Venue.

Seluruh kolom finansial wajib bertipe integer Rupiah (menegakkan Aturan A3 tanpa desimal float), dan seluruh kunci asing wajib memiliki constraint eksplisit (menegakkan Aturan A12).

---

## 2. Rincian Tabel Basis Data

### 2.1. Tabel `ven_ticket_resales` (Order Penjualan Sekunder)
Merekam setiap peredaran penawaran tiket di pasar sekunder.
- `id` (bigint unsigned, primary key, auto increment).
- `ticket_id` (bigint unsigned, foreign key ke `entertainment_tickets.id`, cascade on delete).
- `seller_user_id` (bigint unsigned, foreign key ke `users.id`, cascade on delete).
- `buyer_user_id` (bigint unsigned, nullable, foreign key ke `users.id`, null on delete).
- `resale_price_idr` (bigint unsigned): Nilai penawaran harga penjualan kembali dalam Rupiah bulat.
- `face_value_idr` (bigint unsigned): Nilai nominal harga asli tiket saat penerbitan perdana.
- `max_allowed_price_idr` (bigint unsigned): Plafon legal maksimum ($120\%$ dari face value).
- `pbjt_tax_idr` (bigint unsigned, default 0): Pemotongan pajak daerah PBJT 10%.
- `platform_fee_idr` (bigint unsigned, default 0): Pemotongan komisi layanan escrow 8%.
- `promoter_royalty_idr` (bigint unsigned, default 0): Pemotongan hak cipta promotor 2%.
- `seller_payout_idr` (bigint unsigned, default 0): Nilai pencairan bersih yang diterima penjual.
- `transfer_count` (unsigned smallint, default 0): Frekuensi perpindahan tiket (maksimal 1 kali).
- `new_ticket_hash` (string 64, nullable): Hash token barcode berputar baru untuk pembeli sah.
- `status` (string 32, default 'LISTED'): Nilai enum state machine (`LISTED`, `ESCROW_HOLD`, `SETTLED`, `CANCELLED`, `EXPIRED`).
- `listed_at` (timestamp, nullable): Waktu penawaran pertama kali ditayangkan.
- `settled_at` (timestamp, nullable): Waktu transaksi sukses diselesaikan dan dana dicairkan.

### 2.2. Tabel `ven_ticket_waitlists` (Antrean Daftar Tunggu)
Merekam pendaftar antrean saat kategori tiket habis terjual.
- `id` (bigint unsigned, primary key, auto increment).
- `event_id` (bigint unsigned, foreign key ke `entertainment_events.id`, cascade on delete).
- `user_id` (bigint unsigned, foreign key ke `users.id`, cascade on delete).
- `tier_category` (string 64): Kategori tempat duduk tiket yang diinginkan.
- `loyalty_points` (integer, default 0): Akumulasi poin loyalitas akun pengguna.
- `registration_seconds_ago` (integer, default 0): Selisih durasi waktu pendaftaran untuk anti-starvation.
- `is_fan_club_member` (boolean, default false): Penanda keanggotaan fan club resmi.
- `past_attendance_count` (integer, default 0): Riwayat kehadiran pada acara promotor sebelumnya.
- `priority_score` (integer, default 0): Skor bobot komposit total ($0 - 1000$).
- `status` (string 32, default 'WAITING'): Status antrean (`WAITING`, `OFFERED`, `CLAIMED`, `EXPIRED`, `CANCELLED`).
- `offered_ticket_id` (bigint unsigned, nullable, foreign key ke `entertainment_tickets.id`, null on delete).
- `reservation_token` (string 64, nullable): Token acak pengunci alokasi eksklusif.
- `offer_expires_at` (timestamp, nullable): Batas waktu klaim (tepat 15 menit).

### 2.3. Tabel `ven_dynamic_bundle_packages` (Paket Bundling Dinamis)
Merekam kurasi paket gabungan tiket, hotel, dan transportasi logistik.
- `id` (bigint unsigned, primary key, auto increment).
- `event_id` (bigint unsigned, foreign key ke `entertainment_events.id`, cascade on delete).
- `bundle_name` (string 255): Nama komersial paket bundling.
- `bundle_slug` (string 255, unique): Penanda URL kanonikal paket.
- `status` (string 32, default 'DRAFT'): Daur hidup paket (`DRAFT`, `PUBLISHED`, `SUSPENDED`, `ARCHIVED`).
- `total_base_price_idr` (bigint unsigned): Akumulasi harga eceran dasar komponen.
- `discount_bps` (unsigned smallint, default 0): Persentase diskon promosi dalam basis points.
- `bundle_price_idr` (bigint unsigned): Harga jual paket ke konsumen setelah diskon.
- `floor_price_idr` (bigint unsigned): Batas bawah pengaman modal mitra gabungan.
- `hotel_package_id` (bigint unsigned, nullable): Kunci relasi paket perhotelan mitra (G7-K20).
- `shuttle_route_id` (bigint unsigned, nullable): Kunci relasi rute transportasi shuttle bus (G7-K21).
- `description` (text, nullable): Ringkasan informasi fasilitas dan manfaat paket.

### 2.4. Tabel `ven_secondary_ledger_entries` (Buku Besar Sekunder)
Merekam pembukuan akuntansi ganda aliran dana pasar sekunder.
- `id` (bigint unsigned, primary key, auto increment).
- `resale_id` (bigint unsigned, nullable, foreign key ke `ven_ticket_resales.id`, null on delete).
- `entry_type` (string 32): Tipe transaksi (`SECONDARY_SETTLEMENT`, `ESCROW_HOLD`, `ESCROW_REFUND`).
- `gross_amount_idr` (bigint unsigned): Nilai bruto mutasi moneter.
- `pbjt_tax_idr` (bigint unsigned, default 0): Alokasi pajak daerah PBJT.
- `platform_fee_idr` (bigint unsigned, default 0): Alokasi komisi layanan platform.
- `promoter_royalty_idr` (bigint unsigned, default 0): Alokasi royalti promotor musik.
- `seller_net_idr` (bigint unsigned, default 0): Alokasi pencairan bersih pemilik tiket.
- `description` (string 255): Keterangan deskriptif audit transaksi.

