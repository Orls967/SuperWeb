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


---

## 3. Tata Kelola Peningkatan Kelas Rawat (Upgrade Policy)
1. **Regulasi Kepatuhan Permenkes & BPJS**:
   - Pasien penerima bantuan iuran (PBI) atau peserta BPJS Kesehatan berhak memperoleh kamar sesuai haknya tanpa diskriminasi.
   - Apabila seluruh ranjang Kelas 3 penuh, rumah sakit berkewajiban menempatkan pasien di Kelas 2 atau Kelas 1 dengan tarif yang ditagihkan tetap setara tarif Kelas 3.
2. **Prosedur pada Sistem ERP**:
   - `BedAssignmentOptimizer` menandai properti `is_upgrade_subsidized = true` dan mencatat `original_entitled_class = CLASS_3`.
   - Modul billing secara otomatis mengarahkan selisih tarif sewa kamar ke pos biaya beban subsidi kamar rawat inap rumah sakit (`hsp:bed_upgrade_subsidy_expense:IDR`).
   - Bagian penagihan dilarang meminta surat pernyataan kesediaan bayar selisih kepada pasien jika penempatan di kelas lebih tinggi disebabkan oleh kepenuhan kamar kelas hak dasar.

## 4. Pengawasan SLA Sanitasi & Housekeeping
1. **Standar Waktu Pembersihan Kamar**:
   - Kamar rawat inap umum: Maksimal 60 menit sejak pasien keluar (*discharge*).
   - Kamar isolasi infeksius / ICU: Maksimal 90 menit (termasuk siklus fumigasi / sterilisasi UV-C selama minimal 30 menit).
2. **Eskalasi Otomatis (Bed-Sweep)**:
   - Setiap 30 menit, perintah CLI `php artisan hospital:bed-sweep` memindai seluruh ranjang berstatus `CLEANING`.
   - Ranjang yang melampaui durasi 120 menit tanpa konfirmasi selesai sanitasi akan otomatis berubah warna menjadi merah berkedip di monitor `/hospital/beds` dan mengirimkan tiket peringatan ke koordinator kebersihan rumah sakit.
