# ADR-0070: Standarisasi Master Entitas Legal Grup & Segmen Konsolidasi

## Status
Diterima (Accepted)

## Konteks
Sebelumnya, modul `Intercompany` hanya menyimpan nama entitas sebagai string mentah bebas (`selling_entity`, `buying_entity`) seperti `'HOLDING'` atau `'SUB_A'`. Hal ini menyebabkan beberapa masalah fatal:
1. Tidak ada verifikasi apakah entitas tersebut terdaftar secara hukum, memiliki Nomor Pokok Wajib Pajak (NPWP/Tax ID), atau memiliki mata uang fungsional resmi.
2. Tidak ada hirarki kepemilikan saham yang jelas untuk menentukan apakah transaksi merupakan hubungan istimewa sesuai Pasal 18 UU PPh dan PMK 172/2023.
3. Kode akun ledger perbankan memerlukan segmen entitas terstandarisasi (`ic:ar:{pembeli}:{penjual}:{ccy}`) sesuai arsitektur multi-entitas W8-D61.

## Keputusan
1. Membuat tabel master `ic_entities` yang mencatat seluruh entitas legal dalam konglomerasi dengan kolom:
   - `entity_code`: Kode unik string (mis. `HOLDING`, `SUB_A`, `SUB_B`, `JV_C`).
   - `legal_name`: Nama lengkap badan hukum PT atau Ltd.
   - `country_code`: Kode negara ISO 3166-1 alpha-2 (mis. `ID`, `SG`, `MY`).
   - `functional_currency`: Mata uang fungsional entitas (mis. `IDR`, `USD`, `SGD`).
   - `tax_id`: Nomor Pokok Wajib Pajak (NPWP / TIN).
   - `ownership_percent_permille`: Persentase kepemilikan induk dalam permil integer (mis. `100000` untuk 100%, `75000` untuk 75%).
   - `is_parent`: Boolean penanda apakah entitas merupakan induk holding tertinggi.
   - `is_active`: Status keaktifan operasional.
2. Setiap transaksi intercompany (`ic_transactions`, `ic_loans`, `ic_asset_transfers`) wajib mereferensikan entitas terdaftar melalui `selling_entity_id` dan `buying_entity_id`.
3. Invarian hubungan istimewa: Transaksi intercompany hanya diizinkan bila kedua pihak terafiliasi dengan kepemilikan $\ge 25000$ permil (25%) atau berada di bawah pengendalian umum (*common control*).

## Konsekuensi
- Memastikan integritas data dan mencegah transaksi antar entitas fiktif.
- Segmen kode akun buku besar konsisten dan dapat dipetakan secara deterministik.
- Mendukung pemenuhan audit pajak dan pembuatan master file transfer pricing.

