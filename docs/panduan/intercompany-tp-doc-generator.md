# Panduan Generator Dokumentasi Transfer Pricing (Master File & Local File)

**Standar Kepatuhan**: PMK 172/2023 Bab III, OECD Action 13 (BEPS Transfer Pricing Documentation)  
**Tujuan Modul**: Menghasilkan draf dokumen kepatuhan formal Transfer Pricing Documentation (TP Doc) secara deterministik dari transaksi riil dan benchmark industri.

---

## 1. Tiga Pilar Dokumentasi Transfer Pricing (Three-Tiered TP Documentation)
Sesuai standar perpajakan internasional, grup usaha wajib menyelenggarakan tiga jenis dokumen:
1. **Master File (Dokumen Induk)**: Berisi informasi mengenai grup usaha secara menyeluruh (struktur organisasi, kegiatan usaha, aset tidak berwujud, aktivitas keuangan intra-grup, dan posisi keuangan konsolidasi).
2. **Local File (Dokumen Lokal)**: Berisi informasi spesifik mengenai entitas lokal di Indonesia (struktur manajemen, transaksi afiliasi rinci, analisis fungsi aset risiko / FAR, pemilihan metode transfer pricing, dan analisis kesebandingan benchmark).
3. **Country-by-Country Report (CbCR / Laporan per Negara)**: Berisi alokasi penghasilan, pajak yang dibayar, dan aktivitas ekonomi grup per yurisdiksi perpajakan.

---

## 2. Struktur Data Draf Dokumen Lokal (`ic_tp_documents`)
Generator TP Doc mengagregasi data transaksi dari `ic_transactions` dan `ic_transfer_pricing_rules` untuk menyusun draf Dokumen Lokal per tahun pajak:
- **Bagian I: Identitas Entitas yang Diuji**:
  - Nama Wajib Pajak, NPWP, alamat terdaftar, dan bagan organisasi.
- **Bagian II: Rincian Transaksi Afiliasi**:
  - Daftar seluruh transaksi mirror dengan pihak terafiliasi beserta nilai transaksi bruto.
  - Skema pembayaran, persyaratan kredit, dan mata uang transaksi.
- **Bagian III: Analisis Fungsi, Aset, dan Risiko (FAR)**:
  - Matriks penilaian risiko pasar, risiko kredit, dan risiko inventori.
  - Karakterisasi entitas: Manufaktur Berisiko Penuh (*Full-Fledged Manufacturer*) vs Manufaktur Kontrak (*Contract Manufacturer*).
- **Bagian IV: Pemilihan & Penerapan Metode Transfer Pricing**:
  - Penjelasan alasan pemilihan metode (CUP, CPM, RPM, atau TNMM).
  - Ringkasan statistik rentang interkuartil ($Q_1$, Median, $Q_3$).
  - Bukti kepatuhan margin transaksi terhadap rentang arm's length.

---

## 3. Perintah Ekspor Draf Dokumen TP
Untuk menghasilkan draf TP Doc dalam format JSON/CSV auditan:
```bash
php artisan ic:generate-tp-doc --entity=SUB_A --fiscal-year=2026 --type=LOCAL_FILE
```
Dokumen yang dihasilkan disimpan dalam tabel `ic_tp_documents` dengan nomor referensi hash unik untuk dilampirkan pada pelaporan SPT Tahunan Badan.
