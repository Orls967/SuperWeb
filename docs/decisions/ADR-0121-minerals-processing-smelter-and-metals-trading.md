# ADR-0121: Arsitektur Hilirisasi Mineral, Solver Smelter RKEF, Sistem Resi Gudang (SRG) & Metals Trading Desk

## Status
Diterima (*Accepted*) — 2026-11-07

## Konteks & Latar Belakang Masalah
Sesuai mandat Undang-Undang No. 3 Tahun 2020 tentang Pertambangan Mineral dan Batubara (UU Minerba), pelaku usaha pertambangan pemegang IUP OP dilarang melakukan ekspor bijih mentah (*raw ore*) dan diwajibkan melakukan hilirisasi pemurnian mineral di dalam negeri. Pada Modul Pertambangan (*Mining*), operasi pengolahan dan pemurnian logam melibatkan rantai nilai kompleks yang mencakup:
1. Reaksi pirometalurgi tanur busur listrik terendam RKEF pada temperatur $> 1.500^\circ\text{C}$ dengan konsumsi energi listrik dan reduktor intensif.
2. Kewajiban pelaporan dan audit emisi gas rumah kaca (GRK) Scope 1 dan Scope 2 berdasarkan Perpres No. 98 Tahun 2021 dan UU HPP No. 7 Tahun 2021.
3. Kebutuhan modal kerja likuid berbasis penatausahaan Sistem Resi Gudang (SRG) digital yang terdaftar resmi pada Pusat Registrasi ISWARE (PT Kliring Berjangka Indonesia / Bappebti Kementerian Perdagangan) untuk diagunkan pada fasilitas *Supply Chain Financing* (SCF) perbankan.
4. Volatilitas harga komoditas logam global pada bursa *London Metal Exchange* (LME) yang mewajibkan mekanisme lindung nilai (*hedging*) dengan valuasi harian *Mark-to-Market* (MTM) dan panggilan margin variasi.
5. Pemanfaatan produk sampingan terak nikel (*nickel slag*) sebagai agregat semen ramah lingkungan berizin KLHK SIMPEL.

## Keputusan Arsitektur (Decisions)

### 1. D121-1: Solver Termodinamika Neraca Massa & Energi Pyrometallurgical RKEF (Algoritma M10)
Diputuskan untuk membangun solver deterministik `SmelterPyrometallurgicalEnergyBalanceSolver` di `Modules\Shared\Domain\Algorithms\Smelter\` untuk menghitung neraca massa umpan bijih kering, perolehan logam leburan (NPI/FeNi), pembentukan terak cair, dan kebutuhan energi listrik spesifik (MWh/t metal). Efisiensi termal tanur divalidasi pada rentang $[40\%, 96\%]$ dengan sertifikasi segel hash SHA-256 oleh `SmelterPyrometallurgicalEnergyBalanceVerifier`.

### 2. D121-2: Kuantifikasi Emisi GRK Scope 1 & 2 dan Penilaian Pajak Karbon
Diputuskan untuk menghitung emisi Scope 1 secara langsung dari stoikiometri pembakaran reduktor batubara ($2.4200 \text{ tCO}_2\text{e/ton}$) dan emisi Scope 2 dari konsumsi daya listrik jaringan interkoneksi transmisi ($0.7900 \text{ tCO}_2\text{e/MWh}$). Bila intensitas emisi melebihi ambang batas $2.50 \text{ tCO}_2\text{e/ton logam}$, sistem secara otomatis mengalkulasi provisi liabilitas pajak karbon sebesar Rp 30.000 per ton kelebihan emisi.

### 3. D121-3: Model Pemotongan Nilai Risiko Kolateral Resi Gudang SCF (Algoritma M12)
Diputuskan untuk mengimplementasikan `WarehouseReceiptDigitalCollateralHaircutModel` di `Modules\Shared\Domain\Algorithms\MetalsTrading\` yang menentukan potongan nilai agunan dinamis berdasarkan volatilitas harga historis LME 30 hari, durasi usia simpan gudang, dan toleransi variansi uji laboratorium mutu spektrometri. Batas kredit pembiayaan bank SCF dibatasi pada rasio $\text{LTV} \le 80\%$.

### 4. D121-4: Integrasi Gateway Pusat Registrasi Resi Gudang ISWARE Bappebti (G7-K16)
Diputuskan untuk menyediakan kontrak antarmuka `WarehouseReceiptRegistryGateway` dan implementasi `WarehouseReceiptRegistryGatewayService` untuk mencatatkan resi gudang elektronik, memverifikasi hak fidusia agunan kredit perbankan, dan menerbitkan pelepasan hak tanggungan pasca-pelunasan pinjaman SCF.

### 5. D121-5: Valuasi Mark-to-Market & Manajemen Margin Call Bursa LME (Algoritma M11)
Diputuskan untuk mengimplementasikan `LmeMetalMarkToMarketValuator` di `Modules\Shared\Domain\Algorithms\MetalsTrading\` untuk menilai kembali posisi portofolio lindung nilai secara harian terhadap harga settlement resmi LME Cash dan 3-Month Forward. Jika kerugian yang belum direalisasi melampaui buffer margin pemeliharaan ($8\%$), sistem mengaktifkan status `MARGIN_CALLED` dan menghitung setoran margin variasi wajib.

### 6. D121-6: Orkestrasi Saga Hilirisasi dengan Kompensasi Otomatis
Diputuskan untuk membangun saga lintas domain `MetalsHilirisasiLifecycleSaga` yang mengawal transisi state machine tanur, pengujian spektrometri lab, registrasi resi gudang, penjaminan kredit bank SCF, hingga monetisasi produk sampingan slag. Bila terjadi anomali mutu atau penolakan kliring, sistem mengeksekusi kompensasi rollback otomatis.

### 7. D121-7: Pembukuan Finansial Double-Entry Ledger Posting
Diputuskan untuk memperluas `MiningAccounts.php` dan `MiningTransactionTypes.php` serta mengimplementasikan `MiningFase121LedgerPostingService` untuk mencatat beban listrik tanur, kapitalisasi persediaan logam, realisasi MTM LME, pencairan/pelunasan pinjaman bank SCF, dan penjualan produk sampingan terak slag dengan seluruh nilai moneter IDR bertipe integer.

### 8. D121-8: Manifest Lingkungan Digital SIMPEL KLHK & Ekonomi Sirkular (G7-K17)
Diputuskan untuk menghubungkan pencatatan pengeluaran terak nikel slag dengan gateway manifest lingkungan `ByProductResidueOfftakeGateway` guna menjamin kepatuhan pengangkutan limbah non-B3 terdaftar ke pabrik semen atau proyek infrastruktur jalan.

## Konsekuensi Arsitektur
- **Positif**: Terjaminnya transparansi penuh rantai nilai hilirisasi dari bijih mentah hingga produk akhir, kepatuhan mutlak terhadap regulasi Minerba dan KLHK, otomatisasi pembukuan buku besar akrual yang akurat, serta perlindungan risiko likuiditas pada transaksi lindung nilai pasar komoditas global.
- **Tantangan**: Memerlukan sinkronisasi jaringan yang stabil ke gateway eksternal Bappebti ISWARE dan kuotasi bursa LME. Masalah ini dimitigasi dengan penggunaan gateway adapter yang memiliki mekanisme fallback and local caching.
