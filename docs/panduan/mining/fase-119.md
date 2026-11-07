# Panduan Operasional: Integrasi Penambangan Bawah Tanah, Peledakan, Lereng & Pengapalan Tongkang (Fase 119)

## 1. Ikhtisar Alur Kerja Terintegrasi

Panduan ini ditujukan bagi personil operasional lapangan: Kepala Teknik Tambang (KTT), *Mine Plan Engineer*, *Drill & Blast Engineer*, *Geotechnical Engineer*, *Underground Ventilation Officer*, dan *Marine Terminal Port Captain*.

Sistem penambangan terintegrasi Fase 119 mencakup 4 pilar subsistem:
1. **Subsistem Bawah Tanah (Underground)**: Desain blok stope, monitoring sensor multi-gas ($O_2, CH_4, CO$), simulasi jaringan ventilasi Hardy-Cross, dan optimasi efisiensi VSD kipas utama.
2. **Subsistem Peledakan (Blasting)**: Analisis radius bahaya flyrock, verifikasi geofence telematika GPS pekerja, protokol sirine peringatan 3 tahap, eksekusi peledakan elektronik, dan evaluasi kurva fragmentasi KCO.
3. **Subsistem Geoteknik & Kuari (Geotech & Quarry)**: Pemantauan radar InSAR deformasi lereng, solver faktor keamanan Bishop FoS, alur darurat evakuasi, survei fotogrametri drone, dan perhitungan timbunan mesh TIN.
4. **Subsistem Pengapalan Laut (Marine Barge)**: Draft survey 6 titik tongkang, rekonsiliasi tabel hidrostatik (M9), audit segel palka & Surat Persetujuan Berlayar (SPB) Syahbandar, serta pelunasan freight & demurrage ke buku besar.

---

## 2. Tata Kelola Operasional Tambang Bawah Tanah

### 2.1 Prosedur Pendaftaran Blok Stope Baru
1. Masuk ke menu **Mining > Underground Digital Twin**.
2. Klik tombol **Tambah Blok Stope**.
3. Isi parameter teknis:
   - **Kode Stope**: Format terstandarisasi, misalnya `STP-L350-NORTH-04`.
   - **Nama Level**: Misalnya `Level -350m RL Cross-cut 2`.
   - **Metode Tambang**: Sistem akan merekomendasikan secara otomatis berdasarkan nilai RMR:
     - Jika $\text{RMR} \ge 70$: *Sublevel Open Stoping*.
     - Jika $45 \le \text{RMR} < 70$: *Cut-and-Fill Stoping*.
     - Jika $\text{RMR} < 45$: *Square Set / Top Slicing*.
   - **Dimensi Geometri**: Panjang, Lebar, dan Tinggi dalam meter.
   - **Kadar Rata-rata**: Persentase kadar bijih mineral (misal: $1.85\%\text{ Ni}$).
   - **RMR Bieniawski**: Nilai skor massa batuan (rentang 0 s/d 100).
4. Klik simpan. Sistem akan memvalidasi volume teoritis dan menyematkan rekomendasi sistem penyangga batuan (*ground support*).

### 2.2 Prosedur Menjalankan Solver Hardy-Cross
1. Pada kartu **Hardy-Cross Ventilation Loop Control**, tentukan setpoint persentase putaran kipas (*Fan VSD %*), misalnya 100% pada shift kerja aktif atau 60% pada pergantian shift (idle).
2. Masukkan estimasi *headcount* personil bawah tanah aktif dan total kapasitas mesin diesel tambang (kW).
3. Klik **Jalankan Solver Hardy-Cross**.
4. Sistem mengevaluasi keseimbangan hukum Kirchhoff II di setiap cabang airway.
5. Jika aliran balik (*reversal airflow*) terdeteksi atau debit intake tidak memenuhi standar regulasi (0.033 m³/s per orang + 0.067 m³/s per kW mesin diesel), sistem akan menandai status simulasi sebagai `REQUIRES_RECALIBRATION`.

### 2.3 Prosedur Pemantauan Gas Tambang & Interlock Otomatis
- Sensor terpasang di setiap *heading* stope secara otomatis melaporkan konsentrasi gas.
- **Kondisi Normal**: $O_2 \ge 19.5\%$, $CH_4 < 0.50\%$, $CO < 25\text{ ppm}$.
- **Kondisi Peringatan (Warning)**: $0.50\% \le CH_4 < 1.00\%$ atau $25\text{ ppm} \le CO < 50\text{ ppm}$. Pekerjaan panas dihentikan.
- **Kondisi Evakuasi Kritis (Critical Hazard)**: $O_2 < 19.5\%$ atau $CH_4 \ge 1.00\%$ atau $CO \ge 50\text{ ppm}$. Aliran listrik stope terputus otomatis via interlock dan status stope berubah menjadi `RESTRICTED_GAS_HAZARD`.

---

## 3. Tata Kelola Peledakan Terkendali & Analisis KCO

### 3.1 Siklus Peledakan (Blast Clearance Lifecycle)
Alur status peledakan dikendalikan oleh *Finite State Machine* dengan tahapan:
$$\text{DRAFT} \longrightarrow \text{PERIMETER\_CLEARED} \longrightarrow \text{SIREN\_ACTIVATED} \longrightarrow \text{DETONATED} \longrightarrow \text{ALL\_CLEAR}$$

### 3.2 Langkah Eksekusi Peledakan Aman
1. Buat rencana peledakan (*Blast Run*) baru dengan memasukkan koordinat pusat, radius bahaya (minimum 500 meter), jumlah lubang ledak, dan total muatan bahan peledak (kg ANFO / emulsi).
2. Jalankan perintah **Scan Radar Perimeter**:
   - Sistem memanggil `PersonnelTelematicsGeofenceGateway`.
   - Jika ada pekerja yang GPS-nya berada di dalam lingkaran radius bahaya, sterilisasi dinyatakan **GAGAL** (`detected_breaches_count > 0`).
   - Petugas keselamatan (*Blast Safety Officer*) harus mengarahkan personil menuju *Muster Point* aman di luar perimeter.
3. Setelah perimeter bersih 100% (`breaches = 0`), status berubah menjadi `PERIMETER_CLEARED`.
4. Bunyikan sirine peringatan via tombol **Sirine**:
   - Tahap 1: 15 menit sebelum peledakan (bunyi kontinu bernada rendah).
   - Tahap 2: 5 menit sebelum peledakan (bunyi terputus-putus).
   - Tahap 3: 1 menit sebelum peledakan (bunyi sirine melengking tinggi).
   - Masukkan nomor Kartu Izin Meledakkan (KIM/KJL) juru ledak yang bertugas. Status berubah menjadi `SIREN_ACTIVATED`.
5. Tekan tombol **TEMBAK**:
   - Masukkan nomor serial exploder / *electronic blasting machine*.
   - Saga `BlastExecutionLifecycleSaga` memvalidasi seluruh kondisi pra-syarat.
   - Peledakan dieksekusi, status menjadi `DETONATED`.
   - Jurnal pemakaian bahan peledak otomatis dibukukan ke buku besar `min:expense:explosives_detonators` oleh `MiningFase119LedgerPostingService`.

### 3.3 Evaluasi Fragmentasi Muckpile Batuan
1. Unggah foto fotogrametri muckpile batuan hasil peledakan.
2. Masukkan parameter evaluasi model KCO: *rock factor* $A$, *burden*, *spacing*, diameter lubang, dan *powder factor*.
3. Sistem menghitung ukuran $d_{50}$, $d_{80}$, persentase bongkah (*boulder* $>800\text{ mm}$), dan persentase *fines* ($<25\text{ mm}$).
4. Jika persentase boulder $> 15\%$, sistem memberikan rekomendasi koreksi pola pengeboran berikutnya: pengurangan burden 10%, penambahan powder factor 12%, dan pengurangan stemming.

---

## 4. Tata Kelola Kestabilan Lereng & Survei Kuari

### 4.1 Pemantauan Radar Deformasi Lereng (SSR / InSAR)
- Radar lereng mengukur laju deformasi dinding tambang setiap 15 menit.
- Ambang batas laju pergerakan dinding:
  - $< 1.0\text{ mm/jam}$: Operasi normal.
  - $1.0 - 4.9\text{ mm/jam}$: Pengawasan intensif geoteknik.
  - $\ge 5.0\text{ mm/jam}$: Ambang kritis pemicu alarm peringatan.
  - $\ge 15.0\text{ mm/jam}$: Percepatan regangan tersier (*tertiary creep*), keruntuhan lereng tak terhindarkan.

### 4.2 Prosedur Evakuasi Darurat KTT
1. Jika radar lereng mendeteksi pergerakan kritis atau faktor keamanan Bishop FoS $< 1.10$, Kepala Teknik Tambang mengaktifkan tombol **Evakuasi Darurat KTT**.
2. Masukkan sektor pit yang terancam dan tentukan titik kumpul aman (*Safe Muster Point*).
3. Status insiden berubah menjadi `CRITICAL_EVACUATION`. Seluruh armada tambang dan personil diarahkan meninggalkan area lereng.
4. Gerbang akses pit dikunci secara elektronik.

### 4.3 Tindakan Remediasi Rekayasa
- Tim geoteknik merancang tindakan perbaikan kestabilan:
  - *Unloading Crest*: Mengupas material di puncak lereng untuk mengurangi beban geser.
  - *Toe Buttress*: Menimbun tanggul penahan di kaki lereng.
  - *Horizontal Drains*: Pengeboran lubang pipa drainase untuk menurunkan tekanan air pori ($u$).
  - *Cable Bolting*: Pemasangan tendon jangkar baja panjang bertegangan tinggi.
- Biaya pelaksanaan pekerjaan remediasi dibukukan ke akun buku besar `min:expense:geotech_ground_control`.
- Setelah pekerjaan selesai dan radar memantau lereng stabil (laju deformasi $< 0.5\text{ mm/jam}$) selama 48 jam berturut-turut, insiden ditutup dengan status `RESOLVED`.

---

## 5. Tata Kelola Pengapalan Tongkang & Rekonsiliasi Draft Survey

### 5.1 Tahapan Draft Survey Tongkang
Proses draft survey dilaksanakan dua kali untuk setiap tongkang:
1. **Initial Draft Survey (Kondisi Kosong / Terang)**:
   - Dilakukan sesaat setelah tongkang sandar di dermaga tambang (*jetty*).
   - Surveyor independen mencatat 6 titik sarat air kapal: $DFP, DFS, DMP, DMS, DAP, DAS$.
   - Mengukur densitas air perairan dengan hidrometer terkalibrasi.
   - Mencatat volume air balas dan cairan lain yang dapat dideduksi.
2. **Final Draft Survey (Kondisi Muat Penuh)**:
   - Dilakukan setelah proses pemuatan mineral curah oleh *shiploader* selesai.
   - Mencatat kembali 6 titik sarat air dan densitas air aktual.

### 5.2 Rekonsiliasi Hidrostatik (M9)
1. Buka menu **Mining > Marine Barge Logistics**.
2. Pilih nomor tongkang dan klik **Rekonsiliasi Muatan**.
3. Sistem menginterpolasi bobot perpindahan (*displacement*) dari tabel hidrostatik kapal, mengaplikasikan koreksi trim dan massa jenis air payau/laut.
4. Tonase muatan bersih (*net cargo tonnage WMT*) dihitung dan diperbandingkan dengan akumulasi tiket timbangan *weighbridge* tambang.
5. Jika selisih tonase $\le 1.5\%$, status muatan berubah menjadi `RECONCILED`. Jika melebihi $1.5\%$, status ditandai `DISCREPANCY_FLAGGED` untuk investigasi *surveyor*.

### 5.3 Audit Rantai Kendali (Chain of Custody) & Surat Persetujuan Berlayar (SPB)
1. Petugas terminal memeriksa segel fisik palka tongkang dan memasukkan nomor segel *tamper-evident*.
2. Memasukkan nomor sertifikat analisis kadar (*Certificate of Analysis* - COA) dari laboratorium resmi.
3. Memasukkan nomor Surat Persetujuan Berlayar (SPB) resmi yang diterbitkan oleh Syahbandar Kantor Kesyahbandaran dan Otoritas Pelabuhan (KSOP).
4. Klik **Audit SPB & Segel**. Tongkang bertransisi ke status `DEPARTED`.

### 5.4 Pelunasan Ongkos Angkut Tongkang (Freight Settlement)
1. Setelah tongkang tiba di pelabuhan tujuan (misalnya pelabuhan pabrik smelter), petugas memasukkan total durasi sandar aktual (*actual laytime*).
2. Jika waktu bongkar muat melebihi batas perjanjian sewa (*laytime exceeded*), sistem mengenakan denda keterlambatan (*demurrage*).
3. Jika waktu bongkar muat lebih cepat, sistem memberikan potongan insentif percepatan (*despatch*).
4. Sistem menghitung ongkos angkut bersih:
   $$\text{Net Payable} = (\text{Cargo WMT} \times \text{Tarif Ton}) + \text{Demurrage} - \text{Despatch} + \text{Stevedoring}$$
5. Klik **Pelunasan Freight Ledger**. Biaya pengapalan otomatis dibukukan ke akun buku besar `min:expense:marine_freight_barging` oleh `MiningFase119LedgerPostingService`.


---

## 6. Spesifikasi Payload REST API & Contoh Interaksi Sistem

Untuk integrasi dengan sistem SCADA tambang, berikut adalah spesifikasi endpoint dan contoh payload JSON terverifikasi:

### 6.1 Pendaftaran Blok Stope Bawah Tanah
- **Metode**: `POST /mining/fase119/stopes`
- **Payload Request**:
  ```json
  {
    "stope_code": "STP-L400-EAST-01",
    "level_name": "Level -400m Sublevel 3",
    "mining_method": "SUBLEVEL_OPEN_STOPING",
    "planned_tonnage": 45000.0,
    "ore_grade": 2.15,
    "rock_mass_rating": 72,
    "length_meters": 60.0,
    "width_meters": 20.0,
    "height_meters": 35.0,
    "rock_density_t_m3": 2.75,
    "support_system": "SYSTEMATIC_BOLTS_2M_SPACING_WITH_MESH"
  }
  ```
- **Respons Sukses (201 Created)**:
  ```json
  {
    "message": "Stope bawah tanah berhasil didaftarkan.",
    "stope": {
      "id": "01943012-789a-7b33-8c4d-119000000001",
      "stope_code": "STP-L400-EAST-01",
      "volume_m3": 42000.0,
      "planned_tonnage": 45000.0,
      "status": "DEVELOPMENT"
    }
  }
  ```

### 6.2 Eksekusi Solver Hardy-Cross Ventilasi
- **Metode**: `POST /mining/fase119/ventilation/simulate`
- **Payload Request**:
  ```json
  {
    "simulation_code": "SIM-HARDY-CROSS-SHIFT-A",
    "fan_rpm_percent": 100.0,
    "air_density_kg_m3": 1.20,
    "headcount_underground": 25,
    "diesel_fleet_kw": 400.0,
    "branches": [
      {
        "id": "BR-IN-01",
        "name": "Intake Downcast Shaft",
        "resistance_r": 0.038,
        "initial_flow_q": 150.0,
        "fan_pressure_pf": 1450.0
      },
      {
        "id": "BR-WORK-01",
        "name": "Stope Heading Drift L400",
        "resistance_r": 0.095,
        "initial_flow_q": 75.0
      },
      {
        "id": "BR-RET-01",
        "name": "Exhaust Return Shaft",
        "resistance_r": 0.042,
        "initial_flow_q": 150.0
      }
    ],
    "loops": [
      {
        "loop_id": "LP-01",
        "branch_ids": ["BR-IN-01", "BR-WORK-01", "BR-RET-01"],
        "directions": [1, 1, -1]
      }
    ]
  }
  ```

### 6.3 Verifikasi Sterilisasi Geofence Peledakan
- **Metode**: `POST /mining/fase119/blast/verify-clearance`
- **Payload Request**:
  ```json
  {
    "blast_clearance_run_id": "01943012-789a-7b33-8c4d-119000000002"
  }
  ```
- **Respons Steril**:
  ```json
  {
    "run_code": "BLAST-20261107-B01",
    "status": "PERIMETER_CLEARED",
    "is_perimeter_safe": true,
    "detected_breaches_count": 0,
    "breached_personnel": [],
    "rejection_reason": null
  }
  ```

### 6.4 Rekonsiliasi Draf Survey Tongkang (M9)
- **Metode**: `POST /mining/fase119/barge/reconcile`
- **Payload Request**:
  ```json
  {
    "barge_code": "BG-CITRA-3001",
    "initial_survey_id": "01943012-789a-7b33-8c4d-119000000003",
    "final_survey_id": "01943012-789a-7b33-8c4d-119000000004",
    "weighbridge_loaded_tonnage_mt": 7480.0
  }
  ```
- **Respons Rekonsiliasi**:
  ```json
  {
    "barge_code": "BG-CITRA-3001",
    "light_displacement_mt": 1250.4,
    "loaded_displacement_mt": 8745.2,
    "net_cargo_tonnage_wmt": 7494.8,
    "weighbridge_tonnage_mt": 7480.0,
    "variance_percent": 0.20,
    "is_within_tolerance": true,
    "status": "RECONCILED"
  }
  ```
