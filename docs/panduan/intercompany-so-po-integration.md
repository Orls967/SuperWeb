# Panduan Integrasi: Transaksi Cermin Sales Order & Purchase Order Intercompany

## 1. Siklus Hidup Transaksi Dagang Intra-Kelompok Usaha
Ketika satu anak perusahaan memasok barang dagangan kepada anak perusahaan lain, proses pengadaan harus berjalan mulus tanpa duplikasi input data operasional:
1. **Penerbitan Sales Order (SO) di Entitas Penjual**:
   - Departemen penjualan entitas penjual menerima pesanan pengadaan dari afiliasi.
   - Sistem mencatat referensi `SO-IC-XXXX`.
2. **Penerbitan Purchase Order (PO) Otomatis di Entitas Pembeli**:
   - Tanpa perlu staf pembeli mengetik ulang, modul Intercompany menerbitkan Purchase Order cermin (`PO-IC-XXXX`) dengan rincian barang, kuantitas, harga transfer tervalidasi, dan syarat penyerahan (Incoterms: FOB atau CIF).
3. **Pengiriman Barang & Surat Jalan (Delivery Order / GRN)**:
   - Sisi Penjual mengeluarkan barang dari gudang dengan Surat Jalan.
   - Sisi Pembeli menerbitkan bukti penerimaan barang (*Goods Receipt Note* / GRN) setelah verifikasi kuantitas fisik.
4. **Penerbitan Faktur (Sales Invoice & Purchase Bill)**:
   - Penjual menerbitkan Sales Invoice (`INV-IC-XXXX`).
   - Pembeli secara atomik menerima Purchase Bill (`BILL-IC-XXXX`).
   - Keduanya langsung membukukan ayat jurnal ke buku besar masing-masing.

---

## 2. Penyelesaian Pembayaran Melalui Rekening Bank Kliring
Pembayaran hutang piutang intercompany dilakukan secara terjadwal:
1. **Netting Multilateral (Bilateral Netting)**:
   - Jika SUB_A memiliki piutang ke SUB_B senilai Rp 10.000.000 dan pada saat bersamaan memiliki hutang ke SUB_B senilai Rp 4.000.000, sistem menyarankan penyelesaian kas neto (*net cash settlement*) senilai Rp 6.000.000.
2. **Eksekusi Kliring Bank**:
   - Pihak pembayar (SUB_B) mentransfer Rp 6.000.000 ke rekening bank pihak penerima (SUB_A).
   - Kedua entitas mencatat kas keluar dan kas masuk dengan referensi kliring unik.
   - Sisa saldo piutang dan hutang saling mengimbangi hingga mencapai saldo nol.

