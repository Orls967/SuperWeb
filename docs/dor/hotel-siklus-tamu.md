# Definition of Ready (DoR) — Vertical Slice Siklus Tamu Perhotelan (Hotel Guest Lifecycle)

Dokumen ini mendefinisikan kontrak fungsional, finansial, arsitektur, dan pengujian untuk vertical slice nyata operasional harian hotel dari reservasi, deposit, check-in, pembebanan folio, night audit, housekeeping, check-out/pelunasan (tunai/kartu/corporate city ledger/split), pembatalan, no-show, hingga laporan okupansi/ADR/RevPAR dan audit rekonsiliasi dua-sumber dengan general ledger.

---

## 1. Salinan Keputusan Pemilik (H-1 s.d H-11)

- **H-1 Cakupan Properti & Master Data**: Satu properti dan multi-properti (kolom `property_id` di semua tabel transaksi). Mendukung tipe kamar (`room_types`), kamar fisik (`htl_rooms`), rate plan (BAR, corporate, package breakfast), musim/tanggal khusus pada kalender tarif (`htl_rate_calendar`).
- **H-2 Tanggal Bisnis Hotel (Business Date)**: Terpisah penuh dari jam sistem (system clock); business date berganti HANYA saat night audit berhasil ditutup. Semua pembebanan transaksi, folio, dan pendapatan mengacu pada business date aktif properti bersangkutan. Jam operasional disediakan oleh `ClockInterface`.
- **H-3 Pajak Hotel (PBJT) & Service Charge**: Pajak PBJT 10% dan service charge 10% dapat dikonfigurasi per properti dalam basis poin (integer, 1000 bps = 10%). Urutan perhitungan: service charge dihitung dari tarif dasar kamar/layanan; pajak PBJT dihitung dari (tarif dasar + service charge). Pembulatan per baris folio dilakukan ke rupiah penuh menggunakan metode half-up (`round($val, 0, PHP_ROUND_HALF_UP)`).
- **H-4 Deposit Tamu**: Uang deposit adalah kewajiban (`htl:guest_deposit:IDR` / unearned revenue liability) sampai diterapkan ke folio saat check-out; pengembalian (refund) kelebihan sisa deposit dicatat sebagai transaksi terpisah ber-`Idempotency-Key`.
- **H-5 Pengakuan Pendapatan Kamar**: Pendapatan kamar diakui per malam saat night audit berhasil diproses (bukan saat reservasi dibuat, bukan saat check-out tamu).
- **H-6 Corporate Billing & City Ledger**: City ledger diperlakukan sebagai piutang perusahaan (`htl:city_ledger:IDR` / AR asset); setiap akun korporat memiliki batas kredit (`credit_limit_idr`) yang divalidasi saat pemindahan tagihan; folio split membagi tagihan (misal: kamar ditagihkan ke perusahaan, minibar/restoran ke pribadi).
- **H-7 Kebijakan Pembatalan & No-Show**: Pembatalan gratis berlaku sampai X jam sebelum tanggal kedatangan (dikonfigurasi pada rate plan); setelah melewati batas waktu dikenakan denda satu malam; tamu no-show (tidak check-in hingga penutupan night audit pada tanggal kedatangan) dikenakan denda satu malam dan kamar dilepas kembali ke inventaris.
- **H-8 Overbooking & Walk**: Overbooking diizinkan maksimum N% per tipe kamar (default 0%). Tamu yang terkena walk direlokasi dengan kompensasi yang dicatat penuh sebagai beban operasional hotel seimbang di buku besar.
- **H-9 Housekeeping & Kesiapan Kamar**: Transisi status kamar: `dirty` → `cleaning` → `inspected` → `available`. Check-in HANYA diizinkan ke kamar dengan status `available` atau `inspected`. Status `out_of_order` mengurangi inventaris kamar yang dapat dijual.
- **H-10 Matriks Role & Otorisasi**: `admin`, `hotel_manager`, `front_office`, `reservation_agent`, `housekeeping`, `hotel_cashier`, `night_auditor`, `fnb_outlet` (membebankan ke folio kamar aktif), `corporate_client` (portal korporat: melihat invoice & folio perusahaannya), `guest` (portal tamu: melihat reservasi & folio miliknya), `auditor` (hak baca saja).
- **H-11 Perlindungan Data Pribadi Tamu**: Nomor identitas (KTP/paspor) disimpan terenkripsi (AES-256) disertai blind index (HMAC-SHA256 dengan `BLIND_INDEX_PEPPER`); hanya role `front_office`, `hotel_manager`, dan `admin` yang berhak melihat identitas utuh, sedangkan role lainnya menerima data termasking.

---

## 2. Tujuan Bisnis

1. Menghadirkan vertical slice operasional perhotelan end-to-end yang tangguh, mencegah overbooking liar, menjamin keakuratan persediaan kamar per malam, dan menegakkan audit trail finansial yang tidak dapat dimanipulasi.
2. Memastikan seluruh pengakuan pendapatan kamar, biaya F&B, pajak PBJT, dan service charge tercatat secara double-entry seimbang ke modul `Banking` ledger tanpa akun inline atau floating-point rounding errors.
3. Memberikan pemisahan tanggal bisnis yang ketat (Business Date vs System Clock) sehingga operasional malam (night audit) dapat mengunci hari buku secara konsisten dan terhindar dari anomali zona waktu.

---

## 3. Modul Pemilik & Prefiks Tabel

- **Modul Pemilik**: `Hotel` (`modules/Hotel`)
- **Prefiks Tabel Resmi**: `htl_` (terdaftar di `config/modules.php`)
- **Tabel-Tabel Utama**:
  - `htl_properties`: Master properti hotel, basis poin pajak PBJT & service charge, status operasional.
  - `htl_business_dates`: Tanggal bisnis aktif per properti, status audit (`open`, `auditing`, `closed`), audit checkpoint.
  - `htl_room_types`: Master tipe kamar (Deluxe, Suite, Villa, dll.), batas overbooking persen.
  - `htl_rooms`: Master kamar fisik, nomor kamar, status housekeeping, flag out-of-order.
  - `htl_rate_plans`: Paket harga (BAR, Corporate, Package Breakfast), jendela pembatalan gratis (jam), penalti pembatalan.
  - `htl_rate_calendar`: Kalender tarif harian per tipe kamar per rate plan (harga dinamis musiman/weekend).
  - `htl_room_nights`: Inventaris kamar per malam per tipe kamar / per kamar fisik dengan unique constraint anti-overlap.
  - `htl_reservations`: Reservasi tamu, tanggal kedatangan/keberangkatan, status reservasi, rate plan terpilih.
  - `htl_guest_identities`: Identitas tamu (KTP/paspor) terenkripsi AES-256 + blind index HMAC-SHA256.
  - `htl_folios`: Rekening tagihan induk tamu (open, closed, transferred).
  - `htl_folio_lines`: Baris tagihan append-only (kamar, F&B, pajak, service, pembayaran, koreksi reversal_of).
  - `htl_deposits`: Penerimaan deposit tamu, status kewajiban (held, applied, refunded), Idempotency-Key.
  - `htl_city_ledger_accounts`: Akun piutang korporat mitra, batas kredit, saldo berjalan.
  - `htl_city_ledger_transfers`: Mutasi pengalihan tagihan folio ke city ledger korporat.
  - `htl_housekeeping_logs`: Log pergantian status kebersihan kamar & inspeksi.
  - `htl_daily_revenue_snapshots`: Snapshot metrik harian hasil night audit (okupansi, ADR, RevPAR, total revenue).

---

## 4. Entitas & State Machine

### 4.1 Reservasi (`htl_reservations`)
```
[tentatif] ──(Bayar Deposit / Jaminan)──> [confirmed] ──(Check-In)──> [checked_in] ──(Check-Out)──> [completed]
     │                                         │                            │
     ├──(Expired/Batal)──> [cancelled]         ├──(Batal Lewat Batas)       └──(Pindah Kamar / Extend)
     │                                         │    └──> [cancelled_penalty]
     │                                         │
     │                                         └──(Night Audit Kedatangan Lewat)
     │                                              └──> [no_show] (Denda 1 malam + lepas inventaris)
     │
     └──(Overbooking Walk)──> [relocated_walk] (Kompensasi beban dicatat)
```

### 4.2 Kamar & Housekeeping (`htl_rooms`)
```
[available] ──(Check-in)──> [occupied] ──(Check-out)──> [dirty] ──(Mulai Bersih)──> [cleaning]
     ▲                                                                                 │
     │                                                                                 ▼
     └──────────────────────────(Inspeksi Lolos)──────────────────────────────── [inspected]
     │
     ├──(Kerusakan/Perbaikan)──> [out_of_order] (Mengurangi inventaris jual)
     │                                 │
     └──(Selesai Perbaikan)────────────┘
```

### 4.3 Folio Tamu (`htl_folios`) & Baris Tagihan (`htl_folio_lines`)
- Folio dibuka otomatis saat reservasi atau check-in dalam status `open`.
- Baris folio bersifat **append-only**: dilarang mengedit atau menghapus baris folio yang sudah tersimpan. Koreksi dilakukan dengan menerbitkan baris pembalik (*reversal line*) yang mereferensikan `reversal_of_id`.
- Check-out hanya diizinkan bila saldo sisa folio bernilai **0** (seluruh tagihan telah lunas oleh pembayaran tunai/kartu, penerapan deposit, atau dialihkan ke city ledger).
- Setelah saldo 0, folio beralih status menjadi `closed`.

### 4.4 Business Date (`htl_business_dates`)
- Business date terpisah dari jam server (system clock).
- Business date HANYA bergerak maju (misal dari 2026-11-01 ke 2026-11-02) saat seluruh prosedur night audit pada tanggal tersebut selesai secara utuh (checkpoint lolos).

---

## 5. Tabel Posting Ledger per Kejadian (Double-Entry Bookkeeping)

Standar Konvensi Single-Sign (KONSEP.md §A2.1):
Debit = Negatif (`-`), Kredit = Positif (`+`), Total Transaksi = 0.
Asset/Expense: Debit normal (bertambah = `-`, berkurang = `+`).
Liability/Revenue: Kredit normal (bertambah = `+`, berkurang = `-`).

Contoh Parameter:
- Tarif kamar dasar: Rp 1.000.000 / malam
- Service Charge: 10% = Rp 100.000
- Pajak PBJT: 10% dari (Tarif + Service) = 10% × Rp 1.100.000 = Rp 110.000
- Total tagihan kamar semalam = Rp 1.210.000

| Kejadian | Akun Debit (-) | Nominal Debit | Akun Kredit (+) | Nominal Kredit | Keterangan Finansial |
|---|---|---|---|---|---|
| **1. Deposit Tamu** (Rp 500.000) | `bank:cash_vault:IDR` (Asset) | -500.000 | `htl:guest_deposit:IDR` (Liability) | +500.000 | Kas bertambah, kewajiban titipan deposit bertambah |
| **2. Check-In Tamu** | — | 0 | — | 0 | Tidak ada mutasi finansial; status kamar jadi `occupied` |
| **3. Night Audit Kamar** (Malam 1) | `htl:guest_ledger:IDR` (Asset) | -1.210.000 | `htl:room_revenue:IDR` (Revenue)<br>`htl:service_charge_payable:IDR` (Liability)<br>`htl:tax_payable:IDR` (Liability) | +1.000.000<br>+100.000<br>+110.000 | Piutang tamu bertambah Rp 1.210.000, pendapatan kamar diakui Rp 1.000.000, utang service & pajak bertambah |
| **4. Pembebanan Resto ke Folio** (Rp 250.000 dasar + 25.000 service + 27.500 pajak = Rp 302.500) | `htl:guest_ledger:IDR` (Asset) | -302.500 | `htl:fnb_revenue:IDR` (Revenue)<br>`htl:service_charge_payable:IDR`<br>`htl:tax_payable:IDR` | +250.000<br>+25.000<br>+27.500 | F&B room charge dibebankan ke folio tamu |
| **5. Penerapan Deposit saat Check-Out** (Rp 500.000) | `htl:guest_deposit:IDR` (Liability) | -500.000 | `htl:guest_ledger:IDR` (Asset) | +500.000 | Kewajiban deposit berkurang, piutang tamu berkurang |
| **6. Pelunasan Sisa via Kartu** (Misal sisa Rp 1.012.500) | `bank:card_clearing:IDR` (Asset) | -1.012.500 | `htl:guest_ledger:IDR` (Asset) | +1.012.500 | Kas/clearing kartu bertambah, piutang tamu lunas |
| **7. Split Tagihan ke City Ledger Korporat** (Kamar Rp 1.210.000 dialihkan) | `htl:city_ledger:IDR` (Asset) | -1.210.000 | `htl:guest_ledger:IDR` (Asset) | +1.210.000 | Piutang tamu berkurang, piutang korporat bertambah (cek limit kredit) |
| **8. Refund Sisa Deposit Tamu** (Kelebihan Rp 200.000) | `htl:guest_deposit:IDR` (Liability) | -200.000 | `bank:cash_vault:IDR` (Asset) | +200.000 | Pengembalian sisa titipan deposit ke tamu |
| **9. Denda Batal Lewat Batas** (Denda Rp 1.000.000 dari deposit) | `htl:guest_deposit:IDR` (Liability) | -1.000.000 | `htl:cancellation_fee_revenue:IDR` (Revenue) | +1.000.000 | Deposit dipotong sebagai pendapatan denda pembatalan |
| **10. No-Show Malam Kedatangan** (Denda 1 malam Rp 1.000.000) | `htl:guest_deposit:IDR` (Liability) | -1.000.000 | `htl:cancellation_fee_revenue:IDR` (Revenue) | +1.000.000 | Denda no-show diakui, inventaris kamar malam berikutnya dilepas |
| **11. Kompensasi Walk Overbooking** (Relokasi + Kompensasi Rp 1.500.000) | `htl:overbooking_expense:IDR` (Expense) | -1.500.000 | `bank:cash_vault:IDR` / `htl:walk_payable:IDR` | +1.500.000 | Beban kompensasi operasional dicatat seimbang |

---

## 6. Event, Rute, UI & Policy Matriks

### 6.1 Matriks Otorisasi Rute & Policy
| Rute & Metode | Fungsi Alur | Role Berhak | Role Terlarang (403) |
|---|---|---|---|
| `GET /hotel` | Dashboard Utama Hotel | `admin`, `hotel_manager`, `front_office` | `guest`, `fnb_outlet`, `housekeeping` |
| `GET /hotel/reservations` | Kalender & Reservasi | `admin`, `hotel_manager`, `front_office`, `reservation_agent` | `housekeeping`, `fnb_outlet` |
| `POST /hotel/reservations` | Pembuatan Reservasi Baru | `admin`, `hotel_manager`, `front_office`, `reservation_agent` | `housekeeping`, `auditor` |
| `POST /hotel/reservations/{id}/deposit` | Penerimaan Deposit | `admin`, `hotel_manager`, `front_office`, `hotel_cashier` | `guest`, `housekeeping`, `auditor` |
| `POST /hotel/reservations/{id}/check-in` | Check-in Tamu | `admin`, `hotel_manager`, `front_office` | `hotel_cashier`, `housekeeping`, `guest` |
| `GET /hotel/folios/{id}` | Detail Rekening Folio | `admin`, `hotel_manager`, `front_office`, `hotel_cashier` | `housekeeping`, `reservation_agent` |
| `POST /hotel/folios/{id}/charge-outlet` | Pembebanan F&B/Outlet ke Folio | `admin`, `hotel_manager`, `front_office`, `fnb_outlet` | `housekeeping`, `auditor`, `guest` |
| `POST /hotel/folios/{id}/settle` | Pelunasan & Check-out | `admin`, `hotel_manager`, `front_office`, `hotel_cashier` | `fnb_outlet`, `housekeeping`, `guest` |
| `POST /hotel/folios/{id}/split-city-ledger` | Pengalihan ke City Ledger | `admin`, `hotel_manager`, `hotel_cashier` | `front_office`, `fnb_outlet`, `guest` |
| `GET /hotel/housekeeping` | Papan Status Kamar & Housekeeping | `admin`, `hotel_manager`, `housekeeping`, `front_office` | `guest`, `corporate_client` |
| `POST /hotel/housekeeping/{room}/status` | Update Status Bersih/Inspeksi | `admin`, `hotel_manager`, `housekeeping` | `front_office`, `fnb_outlet`, `guest` |
| `GET /hotel/night-audit` | Kontrol & Monitor Night Audit | `admin`, `hotel_manager`, `night_auditor` | `front_office`, `hotel_cashier`, `housekeeping` |
| `POST /hotel/night-audit/run` | Eksekusi Tutup Hari Night Audit | `admin`, `hotel_manager`, `night_auditor` | `front_office`, `hotel_cashier`, `auditor` |
| `GET /hotel/reports/daily` | Laporan Okupansi, ADR & RevPAR | `admin`, `hotel_manager`, `night_auditor`, `auditor` | `housekeeping`, `fnb_outlet`, `guest` |
| `GET /hotel/portal/guest` | Portal Tamu (Reservasi & Folio Sendiri) | `guest` | Role lain dibatasi ke data miliknya |
| `GET /hotel/portal/corporate` | Portal Korporat (Folio & Invoice Perusahaan) | `corporate_client` | Perusahaan lain dilarang (403/404) |

---

## 7. Command & Audit Dua-Sumber

1. `php artisan hotel:night-audit {property_id}`: Menjalankan eksekusi night audit terjadwal atau manual. Idempoten: bila sudah ditutup pada tanggal tersebut, mengembalikan pesan sukses tanpa mengeksekusi ulang.
2. `php artisan hotel:folio-audit`: Audit rekonsiliasi antara total baris `htl_folio_lines` dengan saldo `bank_ledger_entries` akun piutang tamu. Menghasilkan exit code 0 saat seimbang; exit non-zero bila terdapat baris tagihan termanipulasi di basis data.
3. `php artisan hotel:availability-audit`: Membandingkan inventaris kamar `htl_room_nights` dengan jumlah reservasi aktif dan kamar out-of-order. Menghasilkan exit code 0 bila cocok; exit non-zero bila ada oversell melebihi batas atau reservasi orphan.

---

## 8. Nama Acceptance Test (Minimal 70 Test)

### Kelompok 1: Ketersediaan & Reservasi (12 Test)
1. `stok_ketersediaan_kamar_per_tipe_terhitung_akurat_per_malam`
2. `reservasi_multi_malam_mengunci_seluruh_malam_atau_batal_semua`
3. `dua_reservasi_bersaing_pada_kamar_terakhir_hanya_satu_yang_lolos`
4. `overbooking_sesuai_batas_persen_diterima_dengan_flag_overbooked`
5. `overbooking_melebihi_batas_persen_maksimal_ditolak_dengan_422`
6. `kamar_status_out_of_order_otomatis_mengurangi_stok_jual`
7. `rate_plan_dan_rate_calendar_menghasilkan_tarif_berbeda_pada_high_season`
8. `reservasi_rate_non_refundable_wajib_disertai_deposit_penuh`
9. `pembuatan_reservasi_dengan_idempotency_key_sama_menghasilkan_record_identik`
10. `pembuatan_reservasi_tanpa_idempotency_key_ditolak_dengan_status_422`
11. `nomor_reservasi_diterbitkan_secara_sekuensial_via_document_numbering_interface`
12. `query_ketersediaan_kamar_mencegah_tumpang_tindih_di_tengah_periode_menginap`

### Kelompok 2: Deposit & Pembayaran (10 Test)
13. `penerimaan_deposit_mencatat_kewajiban_unearned_revenue_di_ledger`
14. `deposit_diterapkan_penuh_ke_folio_saat_proses_check_out`
15. `deposit_yang_melebihi_total_tagihan_dapat_di_refund_ke_tamu`
16. `refund_deposit_wajib_memiliki_idempotency_key_tersendiri`
17. `transaksi_pembayaran_kasir_tanpa_idempotency_key_ditolak_422`
18. `pembayaran_dengan_idempotency_key_sama_dua_kali_hanya_menghasilkan_satu_posting`
19. `dua_pembayaran_sah_berbeda_dengan_nominal_sama_menghasilkan_dua_posting`
20. `check_out_ditolak_jika_pembayaran_kurang_dan_saldo_folio_masih_ada`
21. `kelebihan_bayar_tunai_tercatat_sebagai_kembalian_atau_deposit`
22. `pembayaran_split_tunai_dan_kartu_kredit_terekam_seimbang_di_buku_besar`

### Kelompok 3: Check-in, Check-out & Housekeeping (10 Test)
23. `check_in_hanya_dapat_dilakukan_pada_kamar_berstatus_inspected_atau_available`
24. `check_in_ke_kamar_dirty_atau_cleaning_ditolak_dengan_galat_validasi`
25. `nomor_ktp_atau_paspor_tamu_tersimpan_terenkripsi_aes_256_di_database`
26. `pencarian_tamu_berdasarkan_identitas_berfungsi_menggunakan_blind_index`
27. `pindah_kamar_tamu_in_house_memindahkan_sisa_malam_dan_mengubah_status_kamar_lama_menjadi_dirty`
28. `perpanjangan_menginap_memverifikasi_ketersediaan_kamar_pada_malam_tambahan`
29. `proses_check_out_otomatis_mengubah_status_kamar_menjadi_dirty`
30. `check_out_wajib_memastikan_saldo_akhir_folio_adalah_nol`
31. `early_check_out_melepas_sisa_malam_yang_tidak_digunakan_kembali_ke_inventaris`
32. `transisi_housekeeping_mengikuti_alur_dirty_cleaning_inspected_available`

### Kelompok 4: Folio & Outlet F&B (8 Test)
33. `pembebanan_outlet_fnb_ke_folio_hanya_diizinkan_untuk_tamu_in_house`
34. `pembebanan_outlet_ke_reservasi_yang_belum_check_in_atau_sudah_check_out_ditolak`
35. `baris_folio_append_only_dilarang_diedit_atau_dihapus_pada_tingkat_model_dan_database`
36. `koreksi_kesalahan_pembebanan_folio_dilakukan_melalui_baris_reversal`
37. `split_folio_antara_kamar_dan_outlet_menghasilkan_total_yang_persis_sama_dengan_tagihan_asli`
38. `pengalihan_kamar_ke_city_ledger_perusahaan_menghormati_batas_kredit_maksimal`
39. `pengalihan_city_ledger_ditolak_jika_total_tagihan_melampaui_sisa_limit_kredit`
40. `service_charge_dan_pajak_fnb_dihitung_berjenjang_sesuai_aturan_properti`

### Kelompok 5: Night Audit (12 Test)
41. `pendapatan_kamar_diakui_dan_diposting_per_malam_secara_akurat`
42. `night_audit_yang_dijalankan_dua_kali_pada_hari_yang_sama_tidak_menduplikasi_posting`
43. `night_audit_sukses_memajukan_business_date_properti_satu_hari`
44. `reservasi_yang_tidak_check_in_hingga_night_audit_diproses_sebagai_no_show`
45. `denda_no_show_satu_malam_dikenakan_dan_sisa_malam_dilepaskan_ke_inventaris`
46. `pajak_hotel_pbjt_10_persen_dihitung_dari_tarif_kamar_ditambah_service_charge`
47. `service_charge_10_persen_dihitung_dari_tarif_dasar_kamar`
48. `pembulatan_rupiah_per_baris_folio_menggunakan_metode_half_up_secara_persis`
49. `kegagalan_di_tengah_proses_night_audit_dapat_dipulihkan_melalui_mekanisme_checkpoint`
50. `snapshot_harian_hasil_audit_memiliki_total_pendapatan_yang_identik_dengan_mutasi_ledger`
51. `pembebanan_transaksi_setelah_night_audit_menggunakan_business_date_baru`
52. `night_audit_tidak_membebankan_biaya_kamar_pada_reservasi_status_tentatif_atau_batal`

### Kelompok 6: Pembatalan & Walk Overbooking (5 Test)
53. `pembatalan_sebelum_batas_waktu_bebas_denda_dan_deposit_dikembalikan_penuh`
54. `pembatalan_setelah_lewat_batas_waktu_dikenakan_denda_sebesar_satu_malam`
55. `denda_pembatalan_dipotong_dari_deposit_dan_diakui_sebagai_pendapatan_denda`
56. `walk_tamu_overbooking_mencatat_kompensasi_sebagai_beban_operasional_seimbang`
57. `relokasi_tamu_walk_ke_properti_mitra_memperbarui_status_reservasi_relocated`

### Kelompok 7: Otorisasi & Akses (8 Test)
58. `tamu_a_dilarang_melihat_reservasi_dan_folio_milik_tamu_b`
59. `klien_korporat_x_dilarang_melihat_tagihan_dan_invoice_korporat_y`
60. `housekeeping_dilarang_mengakses_data_folio_dan_keuangan_tamu`
61. `fnb_outlet_dilarang_mengubah_status_kamar_atau_menjalankan_night_audit`
62. `auditor_memiliki_akses_baca_namun_ditolak_saat_melakukan_aksi_post_mutasi_finansial`
63. `nomor_identitas_ktp_atau_paspor_tampil_termasking_untuk_role_non_front_office`
64. `role_front_office_dan_hotel_manager_dapat_melihat_nomor_identitas_lengkap`
65. `pengguna_belum_login_dialihkan_ke_layar_autentikasi_302`

### Kelompok 8: Ledger & Rekonsiliasi Audit (5 Test)
66. `setiap_transaksi_keuangan_hotel_menghasilkan_jurnal_ledger_seimbang_debit_kredit`
67. `seluruh_saldo_akun_hotel_berada_pada_sisi_saldo_normal_sesuai_account_kind`
68. `artisan_command_hotel_folio_audit_lolos_bersih_exit_0_pada_data_valid`
69. `artisan_command_hotel_folio_audit_gagal_exit_non_zero_saat_terdapat_baris_folio_korup`
70. `artisan_command_hotel_availability_audit_gagal_bila_ada_ketidakcocokan_inventaris`

### Kelompok 9: Laporan Okupansi, ADR & RevPAR (4 Test)
71. `perhitungan_tingkat_okupansi_harian_akurat_berdasarkan_kamar_terjual_dan_tersedia`
72. `perhitungan_average_daily_rate_adr_sesuai_dengan_realisasi_pendapatan_kamar_dibagi_kamar_terjual`
73. `perhitungan_revpar_sesuai_dengan_total_pendapatan_kamar_dibagi_total_kamar_tersedia`
74. `angka_laporan_pendapatan_bersumber_dari_agregasi_ledger_bukan_konstanta_statis`

### Kelompok 10: Antarmuka UI Blade & Penanganan Error (6 Test)
75. `halaman_dashboard_dan_reservasi_merespons_200_untuk_role_front_office_dan_manager`
76. `halaman_papan_housekeeping_merespons_200_untuk_role_housekeeping`
77. `halaman_night_audit_merespons_200_untuk_night_auditor_dan_403_untuk_role_lain`
78. `halaman_portal_tamu_merespons_200_dan_menampilkan_rincian_folio_sendiri`
79. `halaman_portal_korporat_merespons_200_dan_menampilkan_tagihan_city_ledger_perusahaan`
80. `kegagalan_validasi_input_mengembalikan_redirect_dengan_pesan_galat_bukan_http_500`

---

## 9. Jalan Pintas yang Dilarang Keras
1. Menggunakan nilai waktu/acak (seperti `Str::uuid()`, `time()`) sebagai `Idempotency-Key` default.
2. Menyimpan identitas tamu (KTP/paspor) secara *plaintext*.
3. Menggunakan tipe `float` atau kolom desimal untuk mata uang IDR (wajib `integer` rupiah penuh).
4. Membuat akun buku besar secara *inline* di dalam Action (`LedgerAccount::firstOrCreate`).
5. Mengubah definisi `AccountKind` untuk meloloskan pemeriksaan saldo normal.
6. Mengedit atau menghapus record `htl_folio_lines` (wajib *append-only* dengan *reversal*).
7. Menghitung metrik laporan (Okupansi, ADR, RevPAR) menggunakan konstanta palsu tanpa query data riil.
8. Melakukan check-in ke kamar yang masih berstatus `dirty` atau `cleaning`.
9. Menjalankan night audit tanpa penguncian *business date* dan tanpa penanganan *checkpoint*.

---

## 10. Pertanyaan Terbuka & Asumsi Konservatif
- **Pertanyaan 1**: Apakah service charge 10% diakui sebagai pendapatan hotel atau kewajiban yang didistribusikan kepada karyawan?
  *Asumsi Konservatif Diterapkan*: Service charge dibukukan ke akun kewajiban `htl:service_charge_payable:IDR` (Liability), mencerminkan kewajiban manajemen hotel untuk mendistribusikan dana service charge kepada staf operasional sesuai peraturan ketenagakerjaan perhotelan Indonesia.
- **Pertanyaan 2**: Apakah denda pembatalan dan denda no-show dikenakan pajak PBJT dan service charge?
  *Asumsi Konservatif Diterapkan*: Denda pembatalan dan no-show diperlakukan sebagai ganti rugi operasional/penalti (*indemnity/cancellation fee revenue*) yang tidak menikmati layanan fisik hotel secara langsung, sehingga dibukukan 100% ke akun `htl:cancellation_fee_revenue:IDR` tanpa pemungutan service charge & PBJT, kecuali diatur khusus oleh peraturan daerah.

---

## 11. Usulan DECISIONS (M2)

*(Catatan: Mengikuti aturan M2, usulan keputusan ini dicatat di sini untuk diadopsi ke `docs/DECISIONS.md` pada sesi tata kelola arsitektur)*:

1. **DECISION-HTL-01 (Formula Pajak PBJT & Service Charge)**:
   - Rumus: `Service = round(BaseRate * ServiceBps / 10000, 0, PHP_ROUND_HALF_UP)`
   - `PBJT = round((BaseRate + Service) * TaxBps / 10000, 0, PHP_ROUND_HALF_UP)`
   - Seluruh perhitungan moneter diselesaikan dalam minor unit IDR integer tanpa pecahan sen.
2. **DECISION-HTL-02 (Append-Only Folio & Reversal Lines)**:
   - Tabel `htl_folio_lines` tidak memiliki operasi `UPDATE` atau `DELETE` pada level aplikasi. Pembatalan tagihan menggunakan baris baru dengan nominal negatif bertipe `reversal` yang merujuk pada `reversal_of_id`.
3. **DECISION-HTL-03 (Pemisahan Business Date)**:
   - Setiap transaksi finansial operasional hotel mencatat `business_date` yang bersumber dari tabel `htl_business_dates` milik properti bersangkutan, menjamin konsistensi laporan harian terlepas dari zona waktu server.
4. **DECISION-HTL-04 (Foreign Key Kolom Legacy M6 & Pembersihan Config)**:
   - Empat kolom relasi intra-modul pada tabel lama (`htl_folio_items.folio_id`, `htl_reservations.property_id`, `htl_reservations.room_id`, `htl_timeshare_investors.timeshare_unit_id`) telah memiliki constraint Foreign Key nyata melalui migrasi `2026_10_29_195404_add_foreign_keys_to_legacy_htl_tables.php` (0 orphan di seeder dan test). Entri pengecualian keempat kolom tersebut pada `config/modules.php` dapat dihapus pada sesi pemulihan.
   - Dua kolom lainnya (`htl_folios.reservation_id`, `htl_timeshare_units.property_id`) memiliki 0 orphan pada seeder, tetapi 3 file test legacy lama (`HotelEnterpriseDeepeningTest`, `HotelFolioEnterpriseDeepeningTest`, `HotelFolioTimeshareAndPackageTest`) membuat fixture `Str::uuid()` tanpa record induk, sehingga FK kedua kolom tersebut ditangguhkan sesuai titik henti M6 sampai test legacy tersebut diperbarui pada sesi pemulihan.

---

## 12. Bukti untuk Dipindahkan ke PROGRESS (M1)

*(Bagian ini akan disalin ke `docs/PROGRESS.md` oleh sesi verifikasi/integrasi)*:

```markdown
- [ ] R7.HTL.1 **Vertical Slice Siklus Tamu Perhotelan**: Reservasi multi-malam (anti-overlap inventory lock per malam), deposit kewajiban, check-in aman identitas terenkripsi (KTP/paspor + blind index), housekeeping state machine (dirty->cleaning->inspected->available), pembebanan folio append-only & F&B outlet, night audit idempoten (pengakuan pendapatan harian, PBJT 10% & service 10%, no-show penalty, snapshot harian, pemajuan business date), check-out pelunasan (tunai/kartu/city ledger korporat/deposit), split folio, refund deposit, pembatalan & walk compensation seimbang di ledger, portal tamu & korporat, laporan okupansi/ADR/RevPAR cocok ledger, dan artisan command hotel:night-audit, hotel:folio-audit, hotel:availability-audit dua-sumber.
  Bukti:
    - jenis: fitur
    - commit: 3bdf8772, e4e202aa, 8dc7d7e1, 9a343549, bbbeef20, d9c6b61a, feb129a1, 6f988fb7, cb1f1f4c, a3296d53, cb8ef129, e9e29a48, 5944befc, f0ed7934, 571422df, a74f1b58, b429a039, 3232a1a4, fef909ce, 912845c1, 21ce0bb0, 98b849f0
    - file: modules/Hotel/Application/Actions/ReserveRoomAction.php
    - file: modules/Hotel/Application/Actions/ProcessGuestDepositAction.php
    - file: modules/Hotel/Application/Actions/RefundGuestDepositAction.php
    - file: modules/Hotel/Application/Actions/CheckInGuestAction.php
    - file: modules/Hotel/Application/Actions/ChargeFolioOutletAction.php
    - file: modules/Hotel/Application/Actions/TransferFolioToCityLedgerAction.php
    - file: modules/Hotel/Application/Actions/ExecuteHotelNightAuditAction.php
    - file: modules/Hotel/Application/Actions/CheckOutAndSettleFolioAction.php
    - file: modules/Hotel/Application/Actions/CancelReservationAction.php
    - file: modules/Hotel/Application/Actions/ExecuteWalkCompensationAction.php
    - file: modules/Hotel/Application/Services/HotelBusinessDateService.php
    - file: modules/Hotel/Application/Services/HotelInventoryService.php
    - file: modules/Hotel/Application/Services/HotelReportService.php
    - file: modules/Hotel/Console/Commands/HotelNightAuditCommand.php
    - file: modules/Hotel/Console/Commands/HotelFolioAuditCommand.php
    - file: modules/Hotel/Console/Commands/HotelAvailabilityAuditCommand.php
    - test: tests/Acceptance/Hotel/Eksplorasi/HotelExplorationTest.php (5 passed)
    - test: tests/Acceptance/Hotel/HotelReservationAndInventoryTest.php (12 passed)
    - test: tests/Acceptance/Hotel/HotelDepositAndPaymentTest.php (10 passed)
    - test: tests/Acceptance/Hotel/HotelCheckInOutAndHousekeepingTest.php (10 passed)
    - test: tests/Acceptance/Hotel/HotelFolioAndOutletTest.php (8 passed)
    - test: tests/Acceptance/Hotel/HotelNightAuditTest.php (12 passed)
    - test: tests/Acceptance/Hotel/HotelCancellationAndWalkTest.php (5 passed)
    - test: tests/Acceptance/Hotel/HotelAccessControlTest.php (8 passed)
    - test: tests/Acceptance/Hotel/HotelLedgerAndReconciliationAuditTest.php (5 passed)
    - test: tests/Acceptance/Hotel/HotelOccupancyAdrRevparReportTest.php (4 passed)
    - test: tests/Acceptance/Hotel/HotelUiAndValidationTest.php (6 passed)
    - test: tests/Acceptance/Hotel/HotelSevenDaySimulationTest.php (1 passed, 63 assertions)
    - test: modules/Hotel/tests/Feature/ReserveRoomAntiOverlapRegressionTest.php (3 passed)
    - mutasi: ReserveRoomWithInventoryNightLockAction 73.58%, HotelInventoryService 60.00%, ExecuteHotelNightAuditAction 62.22%, RefundGuestDepositAction 70.59%, TransferFolioToCityLedgerAction 90.24%, ChargeFolioOutletAction 70.79%
    - akses: route GET /hotel [role: admin, hotel_manager, front_office]
    - akses: route GET /hotel/reservations [role: admin, hotel_manager, front_office, reservation_agent]
    - akses: route GET /hotel/housekeeping [role: admin, hotel_manager, housekeeping, front_office]
    - akses: route GET /hotel/night-audit [role: admin, hotel_manager, night_auditor]
    - akses: route GET /hotel/reports/daily [role: admin, hotel_manager, night_auditor, auditor]
    - akses: route GET /hotel/portal/guest [role: guest]
    - akses: route GET /hotel/portal/corporate [role: corporate_client]
    - akses: command hotel:night-audit
    - akses: command hotel:folio-audit
    - akses: command hotel:availability-audit
```

---

## 13. Register Minus Usulan (6 Kolom)

| ID | Item | Minus | Dampak | Prioritas | Rencana Penyelesaian |
|---|---|---|---|---|---|
| RM-HTL-01 | Channel Manager OTA Bridge | Sinkronisasi ketersediaan dan harga ke OTA global (Agoda/Booking.com) menggunakan simulator channel lokal, belum terhubung API XML 2-arah eksternal | Reservasi OTA masuk melalui endpoint simulasi terstandar; belum otomatis tersinkron ke agregator global | P3 | Implementasi modul OTA Channel Manager Adapter dengan protokol OTA 2014.B saat integrasi eksternal aktif |
| RM-HTL-02 | Integrasi IoT Door Lock Fisik | Pembuatan smart-lock key menggunakan token kriptografis berbatas waktu HMAC-SHA256 lokal, belum mengirim sinyal ke gateway hardware kunci RFID/Zigbee fisik | Tamu menerima kode QR/PIN digital terverifikasi; instalasi hardware membutuhkan integrasi driver vendor kunci | P3 | Pengembangan driver IoT gateway (Assa Abloy / VingCard) pada fase ekspansi fisik |
| RM-HTL-03 | Multi-Currency Dynamic Currency Conversion (DCC) | Seluruh pencatatan tarif, transaksi, dan pelunasan menggunakan mata uang dasar IDR rupiah penuh; belum mendukung konversi kurs valas real-time untuk kartu internasional | Tamu asing ditagihkan dalam IDR ekuivalen; settlement perbankan domestik IDR bersih tanpa risiko kurs | P3 | Penambahan fitur konversi valuta asing terhubung modul Treasury pada roadmap internasional |
