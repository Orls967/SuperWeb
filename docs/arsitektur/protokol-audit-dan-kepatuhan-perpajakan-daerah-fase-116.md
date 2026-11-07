# Protokol Audit & Kepatuhan Perpajakan Daerah (Fase 116)

## 1. Kerangka Kepatuhan Hukum Perpajakan Hiburan Daerah (UU HKPD)
Penyelenggaraan jasa hiburan dan kesenian komersial di Indonesia diatur dalam Undang-Undang Hubungan Keuangan antara Pemerintah Pusat dan Pemerintahan Daerah (UU HKPD No. 1 Tahun 2022). Pasal 50 hingga Pasal 55 mengatur secara tegas pengenaan Pajak Barang dan Jasa Tertentu (PBJT) atas Jasa Kesenian dan Hiburan dengan ketentuan:
1. Subjek Pajak adalah konsumen penikmat jasa hiburan (pembeli tiket).
2. Wajib Pajak adalah orang pribadi atau badan yang menyelenggarakan jasa kesenian dan hiburan.
3. Objek Pajak adalah penyerahan jasa kesenian dan hiburan yang dipungut bayaran.
4. Tarif PBJT Kesenian dan Hiburan ditetapkan paling tinggi sebesar 10% (sepuluh persen).

Dalam konteks pasar sekunder tiket (*fan-to-fan resale*), Superweb Venue bertindak sebagai pemungut dan pemotong pajak resmi (*tax withholding agent*) yang diintegrasikan langsung pada lapisan logika bisnis aplikasi melalui algoritma `SecondaryMarketTaxWaterfallEngine`.

---

## 2. Struktur Pencatatan Log Integritas Pemotongan PBJT 10%
Setiap transaksi yang mengalami perubahan status ke `SETTLED` mencatat rincian audit kepatuhan perpajakan yang mencakup:
- ID Transaksi Resale dan Stempel Waktu ISO-8601,
- Nilai Transaksi Bruto (Dasar Pengenaan Pajak),
- Besaran Pemotongan PBJT 10% (dalam bilangan bulat Rupiah),
- Kode Wilayah Bapenda / Lokasi Geografis Venue,
- Tanda Tangan Kriptografis HMAC-SHA256 Sertifikat Waterfall.

Catatan ini bersifat kekal (*append-only fact*) dan tersimpan pada tabel `ven_secondary_ledger_entries`.

---

## 3. Tata Cara Penyelesaian Sengketa Alokasi Royalti Promotor
Selain pajak daerah, setiap transaksi sekunder mengalokasikan $2\%$ untuk royalti promotor dan seniman. Bila timbul ketidaksepakatan antara asosiasi promotor musik dengan penyelenggara:
1. Auditor independen mengekstrak rekapitulasi ledger menggunakan perintah `php artisan venue:audit-secondary`.
2. Total royalti terpotong dicocokkan dengan laporan penjualan tiket sekunder per judul acara hiburan.
3. Seluruh pencairan diselesaikan melalui transfer perbankan resmi dengan berita acara rekonsiliasi yang sah.

