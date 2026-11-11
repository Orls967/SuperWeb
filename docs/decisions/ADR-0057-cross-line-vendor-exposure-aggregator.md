# ADR-0057: Agregator Eksposur Kredit Grup Vendor Lintas Lini Berbasis Integer

**Status:** ACCEPTED  
**Tanggal:** 2026-11-12  
**Pengambil Keputusan:** Chief Financial Officer, Platform Architect, Pelaksana W8-P4  
**Konteks Gelombang:** Gelombang 8, Fase 188 (W8-P4.3 / W8-D57)

---

## Konteks & Masalah

Sebuah grup vendor raksasa dapat memasok barang atau jasa ke 5 atau lebih divisi bisnis SuperWeb yang berbeda (Procurement ritel, suku cadang AutoServe, armada Logistics, dan peralatan CloudKitchen). Jika masing-masing modul mengelola batas kredit vendor secara terisolasi tanpa visibilitas konsolidasi, perusahaan terpapar risiko gagal bayar masif (*concentration risk*).

Diperlukan mesin agregasi yang dapat menghitung total eksposur liabilitas grup vendor di seluruh lini bisnis dengan presisi mutlak bebas pembulatan floating-point IEEE 754.

---

## Keputusan Arsitektur

1. **Agregator Finansial Terpadu (`VendorExposureAggregator` - C3):**
   - Mengelompokkan anak-anak usaha vendor ke dalam satu `vendor_cluster_id`.
   - Mengagregasi seluruh tranche liabilitas berjalan:
     - Tranche 1: Nilai PO terbuka (*unfulfilled commitments*) di modul Procurement.
     - Tranche 2: Penerimaan barang (*Goods Receipts un-invoiced*) di modul Logistics/Wms.
     - Tranche 3: Saldo titipan jaminan fasilitas di modul CloudKitchen/Mall.
     - Tranche 4: Nilai klaim perbaikan garansi belum ditagih di modul AutoServe/Distribution.
2. **Aritmetika Integer Murni (Minor Units):**
   - Seluruh nominal dihitung dalam sen/minor units integer 64-bit IDR.
   - Dilarang keras menggunakan tipe data `float` atau `double`.
3. **Penyimpanan Snapshot & Verifikasi SHA-256:**
   - Hasil kalkulasi disimpan ke dalam tabel `core_vendor_exposure_snapshots` lengkap dengan `integrity_sha256`.
   - Jika eksposur melebihi batas limit konsolidasi grup (`group_credit_limit`), sistem memblokir penerbitan PO baru di modul manapun dengan peringatan risiko kredit terpusat.

---

## Konsekuensi

- **Positif:** Visibilitas risiko kredit vendor 360 derajat, pencegahan over-exposure grup vendor terafiliasi.
- **Negatif:** Kebutuhan mengintegrasikan penyedia data eksposur dari modul-modul bisnis secara decoupled.
- **Kepatuhan:** Diuji di `AccessAlgorithmsTest` (C3) dan `AccessLifecycleTest`.

