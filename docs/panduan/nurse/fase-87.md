# Panduan Peran: Perawat Bangsal & IGD (Fase 87)

Panduan operasional lapangan untuk peran `nurse` di modul Rumah Sakit (Hospital). Dirancang khusus agar optimal digunakan pada perangkat genggam (layar 375 px).

## 1. Rute & Akses
- `/hospital/nursing`: Papan tugas perawat (Nurse Board) 375 px: daftar tugas harian, input vital signs, daftar obat terjadwal, dan peringatan keterlambatan order.
- `/hospital/er`: Papan triase dan antrean IGD.
- `/hospital/critical-care`: Monitor status pasien kritis dan alarm Code Blue.

## 2. Prosedur Tugas Lapangan

### 2.1 Pencatatan Tanda Vital & Evaluasi NEWS2
1. Pada papan bangsal `/hospital/nursing`, pilih nama pasien yang sedang dirawat.
2. Masukkan parameter tanda vital: Laju Napas, SpO2, Oksigen Tambahan (Ya/Tidak), Tekanan Sistolik, Nadi, Kesadaran (Alert / CVPU), dan Suhu Tubuh (°C).
3. Tekan **Simpan Vital Sign**. Sistem seketika menghitung skor NEWS2:
   - Skor 0–4: Risiko Rendah (pemantauan per shift).
   - Skor 3 tunggal / 5–6: Risiko Sedang (laporkan dokter ruangan).
   - Skor >= 7: Risiko Tinggi (persiapkan peralatan resusitasi darurat).

### 2.2 Merespons Alarm Keterlambatan Clinical Pathway
1. Jika order dokter (misal: pengambilan darah lab cito) tertunda lebih dari 15 menit, kartu tugas pada papan perawat akan berubah warna kuning dengan badge `TERLAMBAT`.
2. Segera tindak lanjuti order tersebut ke unit laboratorium atau diagnostik terkait.
3. Setelah tindakan diambil, tandai order sebagai `In Progress`.

### 2.3 Tanggap Pertama Code Blue (Menit 0–2)
1. Apabila monitor telemetri atau tombol darurat memicu Code Blue, banner merah berkedip muncul di bagian atas layar perawat.
2. Tekan tombol **Acknowledge Code Blue** dalam waktu < 2 menit untuk mencegah eskalasi liar ke seluruh rumah sakit.
3. Lakukan bantuan hidup dasar (BHD) hingga dokter tiba di lokasi.


---

## 3. Protokol Serah Terima Tugas Jaga Perawat (SBAR Handover)
Setiap pergantian giliran jaga (shift pagi $\rightarrow$ sore $\rightarrow$ malam), perawat pelaksana wajib mendokumentasikan serah terima pasien menggunakan metode SBAR terstruktur:
1. **S (Situation)**: Identitas pasien, nomor kamar/ranjang, diagnosa medis terkini, dan keluhan utama pada saat operan jaga.
2. **B (Background)**: Riwayat alergi berat, riwayat pembedahan, alat invasif yang terpasang (infus line, kateter urin, selang NGT), dan tanggal pemasangan alat.
3. **A (Assessment)**: Skor NEWS2 terakhir, tren hemodinamik per 4 jam, hasil laboratorium abnormal cito yang telah keluar, dan diet gizi yang sedang dijalankan.
4. **R (Recommendation)**: Tindakan keperawatan yang belum selesai, rencana pemeriksaan diagnostik lanjutan shift berikutnya, dan tanda bahaya khusus yang wajib dipantau perawat jaga baru.
5. **Konfirmasi Digital**: Perawat yang menyerahkan dan perawat yang menerima menekan tombol **Sahkan Handover Shift** di papan perawat `/hospital/nursing`.

## 4. Manajemen Diet Pasien Rawat Inap
1. Periksa label diet pada kartu ranjang elektronik (contoh: *Rendah Garam 2000 kkal*, *Diet Diabetes Melitus 1700 kkal*, *Makanan Cair Saring*).
2. Cocokkan identitas pada barcode baki makanan yang diantar instalasi gizi sebelum diberikan kepada pasien (*Three-Point Check*: Nama, Tanggal Lahir, Rekam Medis).
3. Catat persentase porsi makan yang dihabiskan pasien pada menu nutrisi (0%, 25%, 50%, 75%, 100%). Apabila konsumsi $< 50\%$ selama 2 hari beruntun, sistem secara otomatis menerbitkan notifikasi konsultasi ahli gizi klinik.

## 5. Kewaspadaan Standar & Isolasi Infeksi (PPI)
1. **Kewaspadaan Kontak**: Gunakan gaun pelindung dan sarung tangan sebelum masuk kamar pasien berlabel *Contact Precaution* (misal: MRSA, ESBL).
2. **Kewaspadaan Droplet**: Gunakan masker bedah 3-lapis dalam radius 1 meter dari pasien batuk infeksius (misal: Influenza, Pertusis).
3. **Kewaspadaan Airborne**: Gunakan respirator N95/FFP3 dengan uji kerapatan (*fit check*) sebelum memasuki ruang isolasi bertekanan negatif (misal: Tuberkulosis paru aktif, Varicella).
