# Analisis Ekonomi Kesehatan, Farmakoekonomi & Perjanjian Tingkat Layanan (SLA)

Dokumen ini memaparkan model farmakoekonomi, analisis efisiensi operasional rumah sakit, serta Perjanjian Tingkat Layanan (Service Level Agreement / SLA) logistik farmasi digital pada modul Hospital Fase 104.

---

## 1. Analisis Biaya Manfaat Farmakoekonomi (Cost-Benefit Analysis)

Implementasi terpadu Telemedicine dan E-Pharmacy Multi-Cabang memberikan dampak positif terukur pada metrik ekonomi kesehatan rumah sakit dan pasien:

### 1.1 Reduksi Biaya Transaksi & Waktu Tunggu Pasien
- **Waktu Tunggu Farmasi Rawat Jalan Konvensional**: 90 – 180 menit di ruang tunggu apotek sentral RS.
- **Model E-Pharmacy Digital**: 0 menit antrean di RS; obat dikirimkan langsung ke rumah dalam jendela waktu 60 – 120 menit.
- **Penghematan Biaya Transportasi & Parkir Pasien**: Estimasi Rp 50.000 – Rp 150.000 per kunjungan konsultasi.

### 1.2 Efisiensi Manajemen Persediaan FEFO
- **Penurunan Nilai Obat Kedaluwarsa (Write-Off Loss)**: Pengurangan hingga 82% kerugian akibat obat kedaluwarsa berkat alokasi otomatis lot terdekat kedaluwarsa ke cabang-cabang dengan *turnover* tinggi.
- **Optimalisasi Modal Kerja (Working Capital)**: Peramalan prediktif kebutuhan obat kronis bulanan memungkinkan perputaran persediaan (*inventory turnover ratio*) meningkat dari 6x per tahun menjadi 14x per tahun.

---

## 2. Metrik Kinerja Kunci & Target SLA Logistik

Tabel berikut menetapkan target kuantitatif *Service Level Agreement* (SLA) yang dipantau melalui audit berkala:

| Indikator Kinerja Utama (KPI) | Target Ambang Standar | Mekanisme Pemantauan | Konsekuensi Pelanggaran |
|---|---|---|---|
| **Waktu Respon Pemanggilan Telemedis** | $\le 10\text{ menit}$ dari jadwal janji temu | Timestamp audit telekonsultasi | Penalti insentif kehadiran dokter |
| **Kecepatan Validasi E-Resep Farmasi** | $\le 15\text{ menit}$ sejak submit resep | Queue processing timestamp | Eskalasi ke kepala instalasi farmasi |
| **Kepatuhan Rantai Dingin Suhu (2°C - 8°C)**| $100\%$ tanpa toleransi pelanggaran termal $> 5\text{ menit}$ | Telemetri IoT sensor boks kurir | Paket otomatis dikarantina & penggantian gratis oleh unit logistik |
| **Ketepatan Waktu Pengantaran Kurir Instan**| $\le 120\text{ menit}$ dari penyerahan obat | GPS geofencing & e-POD signature | Pembebasan ongkos kirim pasien |
| **Akurasi Penyerahan Pasien (NIK Match)** | $100\%$ validitas NIK 16 digit | Hash verifikasi cryptographic e-POD | Investigasi potensi salah serah obat |
| **Tingkat Kepatuhan Pasien Kronis (MPR)** | $\ge 80\%$ pada populasi terdaftar | Analisis berkala 90 hari | Intervensi edukasi proaktif oleh perawat |

---

## 3. Akuntansi Terpadu & Rekonsiliasi Pendapatan

Setiap transaksi e-pharmacy terintegrasi langsung dengan buku besar keuangan rumah sakit (ERP Hospital Module):
1. **Piutang Pelanggan / Kas Masuk**:
   - Pasien Umum: Debit Kas/Bank, Kredit Pendapatan Penjualan Farmasi Rawat Jalan.
   - Peserta Asuransi/BPJS: Debit Piutang Klaim BPJS Telemedis, Kredit Pendapatan Operasional Pelayanan Kesehatan.
2. **Beban Pokok Penjualan (HPP)**:
   - Debit Beban Pokok Persediaan Obat (HPP), Kredit Akun Persediaan Obat Apotek Cabang Terpilih berdasarkan nilai perolehan lot FEFO aktual.
3. **Kliring Antar-Cabang (Inter-Branch Clearing)**:
   - Bila resep dipenuhi oleh cabang waralaba/rekanan jejaring, sistem membukukan kewajiban kliring antar-unit (`hsp_inter_branch_obligations`) yang diselesaikan secara netting berkala.
