# Panduan Operasional Tata Graha & Pemeliharaan Kamar (Housekeeping & Engineering)

Panduan ini ditujukan bagi Floor Supervisor, Room Attendant, dan Teknisi Engineering dalam menjaga kebersihan fisik dan keandalan fasilitas kamar hotel.

---

## 1. Alur Kerja Harian Room Attendant (Mobile View 375px)

Aplikasi mobile tata graha dirancang responsif satu tangan (*one-handed mobile friendly*) untuk digunakan langsung oleh staf di koridor kamar.

### 1.1 Memulai Shift & Menerima Tugas Rute (Algoritma H3)
1. Buka aplikasi di ponsel dan login dengan akun peran `housekeeping`.
2. Akses halaman `/hotel/housekeeping`.
3. Sistem secara otomatis menyajikan daftar kamar yang telah diurutkan berdasarkan algoritma H3:
   - Kamar tamu VIP dengan permintaan kedatangan lebih awal diposisikan pada urutan teratas.
   - Kamar-kamar checkout kotor di lantai yang sama dikelompokkan bersama guna meminimalkan pergerakan naik-turun lift service.
4. Klik tombol **Mulai Pembersihan** pada kamar pertama. Status kamar seketika bertransisi ke `cleaning`.

### 1.2 Standar Pembersihan Kamar (SOP Sanitasi)
- Ganti seluruh sprei kasur, sarung bantal, dan duvet dengan linen bersih dari pantry lantai.
- Bersihkan dan sanitasi area kamar mandi, ganti handuk mandi, dan lengkapi amenitas gigi/sabun.
- Periksa kelengkapan isi minibar; laporkan konsumsi tamu yang checkout ke front desk.
- Setelah selesai, semprotkan pengharum ruangan standar hotel dan klik tombol **Selesai Pembersihan**. Status kamar berubah menjadi `done`.

### 1.3 Inspeksi oleh Floor Supervisor
1. Floor Supervisor memeriksa kamar berstatus `done` menggunakan checklist inspeksi kualitas.
2. Jika memenuhi standar kebersihan bintang lima: Klik **Setujui Inspeksi**. Status kamar bertransisi ke `inspected` dan siap dijual.
3. Jika ditemukan kekurangan: Klik **Tolak Inspeksi** dan berikan catatan perbaikan. Tugas pembersihan otomatis ditugaskan ulang ke attendant terkait.

---

## 2. Pelaporan Kerusakan Fasilitas ke Tim Rekayasa (Engineering)

Jika attendant menemukan kerusakan fasilitas di dalam kamar (misal: AC bocor, lampu mati, kunci pintu rusak):
1. Buka form **Lapor Kerusakan Kamar** di aplikasi mobile.
2. Pilih kategori fasilitas yang rusak dan tentukan tingkat keparahan (*severity*):
   - **Minor**: Lampu meja mati, remote TV kehabisan baterai. Kamar tetap dapat dihuni.
   - **Critical**: AC mati total, pipa air wastafel pecah, kunci pintu smart lock macet. Kamar seketika dialihkan ke status `out_of_order`.
3. Tiket Work Order otomatis terbit di papan pemeliharaan Engineering (`/hotel/engineering`).
4. Teknisi Engineering menangani perbaikan fisik sesuai SLA (maksimal 2 jam untuk masalah kritikal).
5. Setelah perbaikan selesai, teknisi menutup tiket dan kamar kembali ke status `cleaning` untuk dibersihkan ulang sebelum dirilis.
