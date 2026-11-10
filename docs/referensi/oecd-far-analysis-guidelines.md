# Panduan Teknis: Analisis Fungsi, Aset, dan Risiko (FAR Analysis) OECD

**Standar Acuan**: OECD Transfer Pricing Guidelines for Multinational Enterprises and Tax Administrations (Chapter I §D & Chapter VI)  
**Tujuan Dokumen**: Kerangka acuan perancangan analisis FAR dalam generator draf dokumentasi transfer pricing (`ic_tp_documents`).

---

## 1. Esensi Analisis FAR dalam Penentuan Harga Transfer
Analisis FAR (*Functions, Assets, and Risks*) merupakan fondasi utama dalam menentukan karakterisasi entitas dan memilih metode transfer pricing yang paling tepat (*The Most Appropriate Method*). Transaksi afiliasi tidak dapat dinilai semata-mata dari label kontrak formal, melainkan dari substansi ekonomi riil di mana nilai ekonomis (*economic value*) diciptakan:
1. **Fungsi yang Dijalankan (Functions Performed)**: Aktivitas operasional, manajerial, riset, pengadaan, logistik, dan penjualan yang dilakukan oleh masing-masing pihak.
2. **Aset yang Digunakan (Assets Employed)**: Sumber daya berwujud (mesin, pabrik, gudang, kas) dan aset tidak berwujud / HKI (paten teknologi, merek dagang, basis data pelanggan).
3. **Risiko yang Ditanggung (Risks Assumed)**: Potensi kerugian finansial atau operasional yang dipikul (risiko pasar, risiko persediaan, risiko kredit macet, risiko nilai tukar, dan risiko garansi produk).

---

## 2. Karakterisasi Profil Entitas Manufaktur & Distribusi

### A. Entitas Manufaktur
1. **Manufaktur Berisiko Penuh (*Full-Fledged Manufacturer*)**:
   - Menjalankan fungsi riset, pengembangan formula, pembelian bahan baku mandiri, produksi, dan pengendalian mutu.
   - Menggunakan aset pabrik sendiri dan memegang hak kekayaan intelektual (HKI).
   - Menanggung risiko pasar, risiko persediaan usang, dan risiko kegagalan produk.
   - Ekspektasi Margin: Imbal hasil tinggi sebanding dengan risiko yang ditanggung (metode CUP atau TNMM).
2. **Manufaktur Kontrak / Tol (*Contract / Toll Manufacturer*)**:
   - Hanya memproduksi barang berdasarkan spesifikasi dan pesanan yang diberikan oleh entitas prinsipal/induk.
   - Bahan baku dipasok atau dijamin oleh prinsipal; tidak memiliki aset tidak berwujud.
   - Tidak menanggung risiko pasar atau risiko fluktuasi permintaan konsumen akhir.
   - Ekspektasi Margin: Imbal hasil stabil dan terukur berbasis biaya (*Cost Plus Method* / CPM) dengan markup rutin wajar ($5\% - 10\%$).

### B. Entitas Distribusi
1. **Distributor Berisiko Penuh (*Full-Fledged Distributor*)**:
   - Membeli barang atas nama sendiri, mengelola jaringan pergudangan dan promosi pemasaran lokal, menanggung risiko piutang tak tertagih dan risiko depresiasi persediaan.
   - Ekspektasi Margin: Margin laba kotor yang memadai (*Resale Price Method* / RPM) atau margin operasi bersih (*TNMM*).
2. **Distributor Berisiko Terbatas (*Limited Risk Distributor* - LRD)**:
   - Bertindak sebagai agen perantara penjualan dengan komitmen pembelian kembali (*buy-back guarantee*) dari produsen afiliasi bila barang tidak terjual.
   - Risiko kredit dan persediaan diminimalkan.
   - Ekspektasi Margin: Margin operasional tetap yang rendah namun stabil ($2\% - 4\%$).

---

## 3. Matriks Penilaian Skor Risiko Digital
Sistem `ic_tp_documents` mengonversi profil kualitatif menjadi skor kuantitatif terstandarisasi (skala 1 s/d 5) untuk mendukung penetapan rentang margin wajar:
- Skor $< 2,0 \implies$ Profil Risiko Rendah (*Contract Entity* / LRD).
- Skor $2,0 - 3,5 \implies$ Profil Risiko Sedang (*Hybrid Entity*).
- Skor $> 3,5 \implies$ Profil Risiko Penuh (*Principal / Full-Fledged Entity*).
