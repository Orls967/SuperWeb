# G7-D57: Maritime Cold-Chain Reefer Monitoring & Container Telemetry Standard

## 1. Konteks Pengangkutan Kargo Rantai Dingin di Laut

Pengangkutan komoditas bernilai tinggi yang sensitif terhadap temperatur (seperti ekspor hasil laut beku, daging, produk susu, buah-buahan tropis, dan vaksin farmasi) mengandalkan kontainer berpendingin (*Reefer Containers* sesuai standar ISO 1496-2). Fluktuasi suhu melebihi batas toleransi selama pelayaran lintas samudera (*temperature abuse*) dapat merusak kargo secara total dan memicu klaim asuransi kargo bernilai jutaan dolar.

Keputusan arsitektur **G7-D57** menetapkan integrasi pemantauan telemetri reefer berkelanjutan di atas kapal dan terminal pelabuhan, pencatatan log suhu digital append-only, dan alarm otomatis deviasi suhu.

---

## 2. Parameter Pengendalian Suhu & Komoditas Standar

| Kategori Komoditas Kargo | Suhu Target Setpoint | Rentang Toleransi | Kebutuhan Ventilasi Udara Segar |
|---|---|---|---|
| **Hasil Laut Beku (Tuna / Udang)** | **$-25.0^\circ\text{C}$ s.d. $-20.0^\circ\text{C}$** | $\pm 1.0^\circ\text{C}$ | Ventilasi ditutup rapat (*Vent Closed $0\,\text{m}^3/\text{h}$*). |
| **Daging Sapi Beku (Frozen Beef)** | **$-18.0^\circ\text{C}$** | $\pm 1.5^\circ\text{C}$ | Ventilasi ditutup rapat (*Vent Closed*). |
| **Buah Pisang & Tropis (Chilled Fruit)** | **$+13.5^\circ\text{C}$** | $\pm 0.5^\circ\text{C}$ | Ventilasi aktif ($15-25\,\text{m}^3/\text{h}$) untuk membuang gas etilen. |
| **Vaksin & Farmasi (Pharma Grade)** | **$+2.0^\circ\text{C}$ s.d. $+8.0^\circ\text{C}$** | $\pm 0.3^\circ\text{C}$ | Pengawasan ketat dengan sensor kalibrasi ganda (*dual probe logging*). |

---

## 3. Arsitektur Telemetri IoT & Pemantauan di Kapal Niaga

```
+---------------------------------------------------------------+
|       KONTAINER REEFER (ISO 1496-2) DI PALKA / DEK KAPAL      |
+-------------------------------+-------------------------------+
                                |
                                v
+---------------------------------------------------------------+
| Sensor Suhu Multi-Titik (Supply Air, Return Air, USDA Probes) |
+-------------------------------+-------------------------------+
                                |
                                v
+---------------------------------------------------------------+
| Modem Powerline Carrier (PLC) / Wireless Zigbee / LoRaWAN     |
+-------------------------------+-------------------------------+
                                |
                                v
+---------------------------------------------------------------+
| Terminal Pemantauan Anjungan Kapal & Satelit Telemetri VSAT  |
+-------------------------------+-------------------------------+
                                |
                                v
+---------------------------------------------------------------+
| Server Modul Port (Modules\Port\Application\Services)         |
| - Verifikasi setpoint suhu harian                             |
| - Deteksi dini trip kompresor atau pemadaman listrik darat    |
+---------------------------------------------------------------+
```

---

## 4. Protokol Tanggap Darurat Deviasi Suhu (Cold-Chain Breach Protocol)

Jika terjadi anomali di mana suhu ruang pendingin (*return air temperature*) melampaui batas toleransi selama lebih dari 60 menit berturut-turut:
1. **Alarm Kritis Anjungan & Kamar Mesin**: Sistem memicu sinyal visual dan audio kepada perwira jaga dan teknisi listrik kapal (*Electro-Technical Officer - ETO*).
2. **Pemeriksaan Fisik Lapangan**: Teknisi wajib memeriksa suplai daya listrik reefer plug-in, sirkuit pendingin freon, motor kipas evaporator, dan siklus defrost otomatis.
3. **Pencatatan Berita Acara Insiden**: Catatan deviasi suhu disimpan secara otomatis ke dalam riwayat perjalanan konosemen (*B/L Chain of Custody*) sebagai bukti objektif klaim asuransi bila terjadi sengketa mutu saat kargo tiba di pelabuhan tujuan.

