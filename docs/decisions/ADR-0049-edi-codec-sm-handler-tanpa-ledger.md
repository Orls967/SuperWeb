# ADR-0049: Kodek Pertukaran EDI Kanonik, State Machine 8-Status & Larangan Mutasi Ledger (W8-D49 / B3 / U-3)

## Status
Diterima (Accepted)

## Konteks
Pertukaran Data Elektronik (*Electronic Data Interchange* - EDI) merupakan standar wajib dalam perdagangan global B2B, logistik, rantai pasok manufaktur, dan perbankan korporat. Format utama yang digunakan mencakup UN/EDIFACT (ISO 9735) di pasar Asia/Eropa dan ANSI ASC X12 di pasar Amerika Utara.

Masalah utama pada implementasi lama:
1. Tidak memiliki parser kanonik yang mendukung escape characters (`?` pada EDIFACT, segment terminator kustom pada X12).
2. Ketiadaan pelacakan status formal menyebabkan ketidakpastian apakah pesan telah diakui (*acknowledged*) atau gagal di downstream.
3. **Risiko Integritas Akuntansi (`U-3`):** Kegagalan batas modul berpotensi membuat pengurai EDI langsung menerbitkan jurnal akuntansi otomatis pada faktur masuk (`INVOIC` / `810`), yang berisiko menciptakan entri buku besar fiktif tanpa rekonsiliasi tiga pihak (*three-way matching*).

## Keputusan Arsitektur

### 1. Pengurai Kanonik Dwistandar (`EdiInterchangeCodec`)
Algoritma `EdiInterchangeCodec` mendukung dua standar utama secara otomatis:
- **UN/EDIFACT:** Mendukung segmen header `UNA` (definisi delimiter opsional), default delimiter (`+` element, `:` component, `?` release/escape, `'` segment terminator). Memvalidasi pasangan amplop `UNB`/`UNZ`, amplop pesan `UNH`/`UNT`, segmen dokumen `BGM`, dan baris item `LIN`/`QTY`.
- **ANSI ASC X12:** Mendukung amplop `ST`/`SE`, transaksi pesanan pembelian `850` (`BEG`, `PO1`, `CTT`), faktur `810` (`BIG`, `IT1`, `TDS`), dan pemberitahuan pengiriman `856` (`BSN`, `HL`).

### 2. State Machine 8-Status Deterministik
Seluruh pertukaran berkas EDI diatur oleh `EdiInterchangeStateMachine` yang mencakup 8 status berurutan:
1. `received` $\to$ 2. `parsed` $\to$ 3. `validated` $\to$ 4. `mapped` $\to$ 5. `acknowledged` $\to$ 6. `posted` (atau `rejected`), dan diakhiri dengan `archived`.
Setiap perpindahan status mencatat baris fakta ke `core_edi_transitions` yang dilindungi oleh model Eloquent kekal (*immutable*), di mana operasi `UPDATE` dan `DELETE` dilarang keras.

### 3. Pembangkit Pengakuan Fungsional Otomatis
- Untuk EDIFACT: Menghasilkan pesan `CONTRL` dengan segmen `UCI` yang membawa status aksi `7` (diterima) atau `4` (ditolak) beserta kode galat sintaks (`UNT_COUNT_MISMATCH`, `LIN_WITHOUT_QTY`, dsb).
- Untuk ASC X12: Menghasilkan pengakuan fungsional `997` dengan segmen `AK1..AK9` (`AK5*A` dan `AK9*A` untuk sukses, `AK5*R` untuk penolakan).

### 4. Larangan Mutasi Buku Besar Akuntansi (`U-3`)
Sesuai invarian terkunci `U-3`:
- Pemrosesan pesan `INVOIC` (EDIFACT) dan `810` (X12) oleh `ProcurementEdiInvoiceHandler` **HANYA** membentuk entitas draft `SupplierInvoice` berstatus `draft` untuk ditinjau oleh operator pengadaan.
- Dilarang keras memanggil `BankLedgerEntry`, `GeneralLedgerService`, atau membuat jurnal akuntansi apa pun selama penanganan EDI masuk.
- Pengujian acceptance menguji secara tegas: `DB::table('bank_ledger_entries')->count()` sebelum dan sesudah penanganan EDI faktur harus bernilai identik.

## Konsekuensi & Kepatuhan
- **Positif:** Standarisasi B2B global tanpa pihak ketiga berbayar, perlindungan integritas buku besar 100%.
- **Negatif:** Parsing dokumen EDIFACT multi-pesan membutuhkan alokasi memori berbanding lurus dengan ukuran payload.
- **Verifikasi:** Diuji pada `ApiV2LifecycleTest` skenario `test_55_3_webhooks_hmac_rotation_event_delivery_dlq_cb_and_edi_sm` dan `ApiV2AlgorithmsTest`.
