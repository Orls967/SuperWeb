# Panduan Pengguna: Executive Chef & Banquet Kitchen (Fase 114)

## 1. Peran & Tanggung Jawab
Executive Chef dan tim Banquet Kitchen bertanggung jawab atas perencanaan bahan baku, persiapan resep porsi massal, pengelolaan varians porsi makanan dan minuman, serta meminimalkan sisa makanan (*food waste*) selama perhelatan acara.

## 2. Alur Pengelolaan Banquet Event Order (BEO)
### 2.1 Penerimaan BEO & Review Lembar Produksi (Production Sheet)
1. Akses menu **Kitchen Management -> Banquet Production Sheets**.
2. Setiap kali BEO disetujui, sistem otomatis membedah resep menu menjadi kebutuhan bahan mentah melalui mesin `BanquetBomExploder`.
3. Lembar produksi menampilkan:
   - Jumlah tamu terjamin (*guaranteed pax*) dan jumlah porsi produksi (*prepared pax* dengan 5% buffer).
   - Rincian gramatur bahan segar (daging sapi, ayam, ikan, sayuran, rempah-rempah) lengkap dengan faktor susut olah (*yield factor*).
   - Waktu penyajian setiap menu (*service timeline*).

### 2.2 Requisition & Pemotongan Batch Stok Resto
1. Klik tombol **Kirim Permintaan Bahan Baku (Requisition)**.
2. Modul Resto dan Gudang Logistik/Procurement akan secara otomatis memverifikasi stok tersedia.
3. Saat persiapan dapur dimulai (*mise en place*), status diubah menjadi `PRODUCTION_COMMENCED`. Sistem secara otomatis memotong stok gudang berdasarkan BOM resep standar.

### 2.3 Kontrol Varians Penuangan Bar & Beverage
1. Untuk acara dengan open bar atau jamuan koktail, supervisor bar memantau lembar varians penuangan (*pour variance tracker*).
2. Botol minuman anggur, sampanye, dan spiritus dicatat volume awal dan sisa botol setelah acara.
3. Tingkat varians yang melebihi toleransi standar (> 5% tumpah/spill) wajib dilaporkan dan ditandatangani oleh Bar Captain.
