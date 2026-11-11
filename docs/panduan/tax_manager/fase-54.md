# Panduan Operasional: Tax Manager — Simulator Perpajakan Nasional & Rekonsiliasi Fiskal (Fase 54)

## 1. Peran & Tanggung Jawab
Tax Manager bertanggung jawab atas kepatuhan seluruh kewajiban perpajakan entitas grup dan subholding, yang mencakup:
1. **Rekonsiliasi PPN Masukan vs PPN Keluaran**: Memastikan perlakuan PPN 11% sesuai UU Harmonisasi Peraturan Perpajakan (HPP).
2. **Manajemen Faktur Pajak Elektronik Gapless**: Memantau penerbitan Nomor Seri Faktur Pajak (NSFP) agar berurutan secara ketat tanpa celah penomoran (*gapless sequence*).
3. **Pemotongan & Pemungutan PPh (Withholding Tax)**: Mengelola rekapitulasi PPh Pasal 21 (karyawan/imbalan jasa), PPh 22 (pengadaan), PPh 23 (jasa/sewa peralatan), dan PPh 4 ayat 2 (sewa properti/tanah).
4. **Penyusunan Draf SPT Masa & Penyetoran**: Menerbitkan draf SPT Masa PPN 1111 dan SPT Masa PPh, serta memvalidasi ekspor berkas data CSV/XML untuk simulator DJP.

---

## 2. Prosedur Rekonsiliasi PPN & Eksekusi Kompensasi/Setoran

### 2.1 Alur Rekonsiliasi Masa Pajak
1. Masuk ke menu **Pajak & Fiskal** (`/enterprise-finance/tax`).
2. Pilih entitas (misal: `CORP-HOLDING`) dan masa pajak (format `YYYY-MM`).
3. Sistem secara otomatis menampilkan:
   - **Total PPN Keluaran (Output Tax)**: Akumulasi PPN yang dipungut dari faktur penjualan modul Mall, Logistics, Store, dan Resto pada akun `ef:vat_output_payable:{entitas}:IDR`.
   - **Total PPN Masukan (Input Tax)**: Akumulasi PPN yang dibayar kepada vendor atas faktur pembelian modul Procurement pada akun `ef:vat_input_receivable:{entitas}:IDR`.
   - **Status Kurang / Lebih Bayar**: Selisih bersih antara PPN Keluaran dengan PPN Masukan.
4. Apabila status menghasilkan **Kurang Bayar**:
   - Tax Manager memverifikasi nomor draf SPT Masa PPN yang diterbitkan secara gapless.
   - Klik tombol **Setor PPN Kurang Bayar**.
   - Sistem memposting jurnal pemindahbukuan ke kas negara via akun kliring `ef:vat_settlement_clearing:{entitas}:IDR`.
   - Status perpajakan berubah menjadi `SETTLED`.

### 2.2 Uji Ketiadaan Celah Penomoran Faktur (Gapless Validation)
Sistem menolak pelaporan SPT jika ditemukan adanya nomor faktur pajak yang terlewati:
- Pengujian dijalankan otomatis via `php artisan enterprise:tax-recon {period}`.
- Generator nomor seri menggunakan mekanisme atomik database sequencing: `010.001-YY.XXXXXXXX`.
- Jika terdapat faktur yang dibatalkan oleh pembeli, faktur tersebut tidak dihapus melainkan ditandai sebagai `CANCELLED` agar urutan penomoran tetap utuh (*gapless*).

---

## 3. Ekspor Data Perpajakan Siap Impor e-Faktur & e-Bupot

Platform Enterprise Finance mendukung ekspor berkas standar CSV/XML yang kompatibel dengan simulator DJP:
1. Klik tombol **Ekspor CSV e-Faktur**.
2. Berkas CSV memuat kolom standar:
   - `KD_JENIS_TRANSAKSI` (01 untuk penyerahan umum)
   - `FG_PENGGANTI` (0 untuk normal, 1 untuk pengganti)
   - `NOMOR_FAKTUR` (16 digit nomor seri)
   - `NPWP_LAWAN_TRANSAKSI` (15/16 digit NPWP pembeli)
   - `NAMA_LAWAN_TRANSAKSI`
   - `JUMLAH_DPP` (Dasar Pengenaan Pajak dalam minor satuan penuh)
   - `JUMLAH_PPN` (11% dari DPP)
   - `STATUS_FAKTUR` (TERBIT / DIBATALKAN)

Tax Manager dapat langsung mengimpor berkas tersebut ke dalam aplikasi klien e-Faktur Desktop atau portal web DJP.

