# Panduan Peran: Manajer Tempat Tidur (Bed Manager) (Fase 87)

Panduan operasional alokasi ranjang untuk peran `bed_manager`.

## 1. Rute & Akses
- `/hospital/beds`: Papan kontrol ranjang real-time (Bed Management Board): denah bangsal, daftar ketersediaan per kelas (VIP, K1, K2, K3, Isolasi, ICU/HDU), antrean admisi gawat darurat dan elektif, tombol alokasi otomatis (B1).

## 2. Prosedur Alokasi & Mutasi Ranjang

### 2.1 Menjalankan Alokasi Optimal Berjenjang (B1)
1. Periksa daftar pasien dalam antrean admisi yang telah mengonfirmasi kebutuhan rawat inap.
2. Klik tombol **Jalankan Optimasi Alokasi (B1)**.
3. Algoritma akan memprioritaskan pasien gawat darurat (prioritas 0) dan waktu tunggu terlama, mencocokkan ranjang bebas terkecil nomor kodenya.
4. Periksa rekomendasi: jika kelas hak penuh, sistem menandai ranjang kelas lebih tinggi sebagai `Upgrade Disetujui RS`.
5. Klik **Konfirmasi Alokasi**. Sistem akan melakukan penguncian transaksi DB (`lockForUpdate`) untuk mencegah bentrok alokasi paralel ganda.

### 2.2 Penanganan Pasien Discharge & Alur Kebersihan
1. Pasien yang telah menyelesaikan resume medis dan pelunasan kasir akan di-discharge.
2. Ranjang otomatis berpindah dari status `occupied` ke `cleaning`.
3. Informasikan kepada staf kebersihan (housekeeping).
4. Setelah sanitasi selesai, klik **Tandai Sanitasi Selesai** untuk mengembalikan status ranjang menjadi `available`.
