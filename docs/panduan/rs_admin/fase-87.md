# Panduan Peran: Administrator Rumah Sakit (RS Admin) (Fase 87)

Panduan operasional manajerial dan eksekutif untuk peran `rs_admin`.

## 1. Rute & Akses
- `/hospital/efficiency`: Dashboard efisiensi tempat tidur Barber-Johnson (BOR, ALOS, TOI, BTO), analisis kuadran kinerja, dan ekspor CSV.
- `/hospital/beds`: Konfigurasi kuota bangsal, pengalihan status isolasi darurat, dan audit ranjang.

## 2. Prosedur Manajerial & Kepekaan Eksternal

### 2.1 Analisis Efisiensi Tempat Tidur (Barber-Johnson)
1. Buka `/hospital/efficiency`.
2. Tentukan periode analisis (bulanan / triwulanan).
3. Evaluasi metrik:
   - BOR (Bed Occupancy Rate): Ideal 7.500–8.500 bps (75%–85%). Jika < 6.000 bps, kapasitas berlebih; jika > 8.500 bps, risiko keselamatan pasien meningkat.
   - ALOS (Average Length of Stay): Ideal 3.000–9.000 mili (3–9 hari).
   - TOI (Turn Over Interval): Ideal 1.000–3.000 mili (1–3 hari).
   - BTO (Bed Turn Over): Ideal >= 30 kali putaran/tahun.
4. Klik **Ekspor Laporan CSV** untuk keperluan pelaporan resmi ke dinas kesehatan atau dewan direksi.

### 2.2 Respons Kepekaan Eksternal (DBD, ISPA, Banjir)
1. Periksa peringatan otomatis indeks faktor eksternal dari `HospitalExternalFactors`.
2. Jika terdeteksi indeks KLB DBD >= 150 atau ISPA PM2.5 > 150 ug/m3:
   - Sistem akan menyajikan usulan alih fungsi ranjang (`Emergency Capacity Plan`).
   - Tinjau jumlah ranjang reguler yang akan dialihkan menjadi ranjang isolasi atau HDU.
   - Klik **Setujui Penyesuaian Kapasitas Darurat**. Sistem akan mencatat riwayat persetujuan di tabel `hsp_bed_transitions`.
3. Jika terjadi bencana banjir yang memutus jalan akses RS > 2 jam:
   - Aktifkan status siaga darurat (Flood Standby Mode).
   - Seluruh rujukan keluar non-kritis ditangguhkan otomatis dan ambulans dikoordinasikan via helipad.
