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


---

## 3. Interpretasi Grafik Segi Empat Barber-Johnson
Grafik Barber-Johnson menyajikan perpotongan empat parameter efisiensi tempat tidur dalam satu diagram koordinat Kartesius:
1. **Daerah Efisiensi (Daerah Ideal)**:
   - Dibatasi oleh perpotongan garis:
     - Garis $\text{BOR} = 75\%$ dan $\text{BOR} = 85\%$
     - Garis $\text{TOI} = 1$ hari dan $\text{TOI} = 3$ hari
     - Garis $\text{ALOS} = 3$ hari dan $\text{ALOS} = 9$ hari
     - Nilai $\text{BTO} \ge 30$ kali per tempat tidur per tahun.
2. **Diagnosis Masalah Manajemen Bangsal**:
   - Titik jatuh di **kiri daerah ideal** ($\text{TOI} < 1$ hari, $\text{BOR} > 85\%$): Beban kerja perawat berlebih, waktu pembersihan kamar tergesa-gesa, risiko infeksi nosokomial (*cross-contamination*) tinggi. Solusi: percepat pelepasan pasien sembuh (*discharge planning* dini) dan tambah kuota perawat.
   - Titik jatuh di **kanan daerah ideal** ($\text{TOI} > 3$ hari, $\text{BOR} < 60\%$): Fasilitas ranjang menganggur (*under-utilized*), inefisiensi biaya operasional tetap. Solusi: rampingkan bangsal non-produktif atau buka kerjasama rujukan faskes tingkat pertama.

## 4. Prosedur Audit Dua Sumber (`hsp:audit`)
Audit integritas dua sumber dijalankan harian untuk menjamin konsistensi mutlak antara data operasional medis dengan pencatatan akuntansi:
```bash
php artisan hosp:audit
```
1. **Parameter yang Diverifikasi**:
   - **Rekonsiliasi Hari Rawat (Bed-Day)**: Jumlah hari rawat aktif pasien terdaftar dikalikan tarif per kelas wajib sama persis dengan mutasi debet akun piutang pasien `hsp:patient_receivable:IDR` dan kredit pendapatan rumah sakit `hsp:hospital_revenue:IDR`.
   - **Integritas Rantai Paspor Kriptografis**: Rantai hash SHA-256 seluruh rekam medis pasien tidak boleh memiliki celah pemutusan (*tampering*).
   - **Ketiadaan Konflik Ranjang**: Tidak ada satupun ranjang yang menampung lebih dari 1 pasien aktif secara bersamaan.
   - **Keseimbangan Buku Besar**: Total debit wajib sama persis dengan total kredit pada modul perbankan/ledger tanpa selisih 1 Rupiah pun.
2. **Kriteria Kelulusan**:
   - Jika bersih 100%: Perintah mencetak `[AUDIT BERSIH] Seluruh data operasional dan buku besar rumah sakit konsisten.` dan keluar dengan status kode `0`.
   - Jika ditemukan anomali: Perintah mencetak daftar ketidaksesuaian secara rinci dan keluar dengan status kode non-zero (`1`).
