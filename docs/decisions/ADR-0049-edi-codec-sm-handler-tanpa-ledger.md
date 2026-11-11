# ADR-0049: Subsistem EDI Simulasi (B3 Codec, State Machine 8 Status & Handler Pemilik) (W8-D49)

## Status
Diterima (Accepted)

## Konteks
Layanan EDI terdahulu di modul Integration hanya memeriksa `str_contains('UNH+')` atau `str_contains('ST*')` dan langsung menulis status `accepted` tanpa mengurai struktur segmen, tanpa menghitung ulang kontrol `UNH↔UNT` / `UNB↔UNZ`, tanpa menghasilkan pengakuan `CONTRL` / `997`, dan tanpa menyalurkan dokumen bisnis ke modul pemiliknya.

## Keputusan
1. **Codec Simulasi Deterministik `EdiInterchangeCodec` (B3)**:
   - Mendukung subset simulasi **UN/EDIFACT** (`ORDERS D96A`, `INVOIC`, `DESADV`; pemisah default `' + : ?` dan escape/unescape karakter pelepas `?`) serta **ASC X12** (`850` & `997`; pemisah `~` dan `*`). Memvalidasi nomor kontrol berpasangan (`UNH↔UNT`, `UNB↔UNZ`, `ST↔SE`), hitungan segmen inklusif, serta segmen wajib `BGM` dan minimal satu grup `LIN` + `QTY`.
   - Menjamin pembuatan ulang pesan yang **byte-identik** atas dokumen kanonik valid dan memvalidasi hasilnya dengan `EdiInterchangeCertificateVerifier`.
   - Tidak mengklaim kepatuhan standar EDI produksi penuh maupun transport AS2/VAN (dicatat eksplisit di Register Minus).
2. **State Machine 8 Status & Riwayat Append-Only**:
   - `EdiInterchangeStateMachine` mengelola transisi `received -> parsed -> validated -> mapped -> acknowledged -> posted -> rejected -> archived`.
   - Setiap interchange **wajib** menghasilkan pengakuan `CONTRL` (aksi `7` = diterima, `4` = ditolak beserta kode galat terurut) atau `997` (`AK1`/`AK2`/`AK5`/`AK9`).
   - Transisi `acknowledged -> posted` hanya sah jika `EdiDocumentHandler::handle()` modul pemilik sukses mengembalikan `EdiHandleResult` beserta kunci idempoten yang tersimpan.
3. **Nol Posting Ledger & Kontrak Modul Pemilik**:
   - EDI **dilarang** memposting jurnal ledger (`U-3`). `Distribution` menangani `ORDERS`/`850` ke draft `DistOrder`; `Procurement` menangani `INVOIC` ke `SupplierInvoice` status `pending` (draft); `Wms` menangani `DESADV` ke `DockAppointment` inbound.
   - Interchange yang ditolak menerbitkan `core.edi.interchange_rejected.v1` ke Outbox.

## Konsekuensi
- Pengakuan `CONTRL`/`997` dan generasi byte-identik diaudit silang pada `api:audit`.
