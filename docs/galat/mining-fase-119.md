# Katalog Galat Teknis & Penanganan Error Operasional (Fase 119)

## 1. Daftar Kode Galat Teknis Modul Pertambangan Fase 119

Modul ini mengimplementasikan penanganan eksepsi bertingkat dengan pesan galat berbahasa Indonesia dan kode status HTTP terstandarisasi.

| Kode Galat | Nama Eksepsi | Kode Status HTTP | Deskripsi Penyebab & Solusi Penanganan |
| :--- | :--- | :--- | :--- |
| **ERR-UG-11901** | `InvalidStopeDimensionException` | `422 Unprocessable` | Dimensi stope (panjang, lebar, tinggi) $\le 0$ meter atau RMR $< 0$ / $> 100$. Periksa kembali koordinat survei pemodelan cadangan. |
| **ERR-UG-11902** | `HazardousGasConcentrationException` | `409 Conflict` | Konsentrasi gas metana $\ge 1.0\%$ atau oksigen $< 19.5\%$. Akses stope diblokir oleh interlock keselamatan tambang bawah tanah. |
| **ERR-UG-11903** | `VentilationUnconvergedException` | `500 Server Error` | Solver Hardy-Cross gagal konvergen dalam 100 iterasi akibat loop sirkulasi tertutup singular atau konfigurasi tahanan salah. |
| **ERR-UG-11904** | `AirflowReversalDetectedException` | `422 Unprocessable` | Tekanan ventilasi alami membalik arah aliran di cabang kerja stope. Kalibrasi ulang tekanan kipas penekan (*booster fan*). |
| **ERR-BL-11910** | `BlastPerimeterBreachException` | `403 Forbidden` | Penembakan peledakan ditolak karena terdeteksi personil tambang aktif di dalam radius bahaya eksklusi flyrock. |
| **ERR-BL-11911** | `InvalidBlasterCredentialException` | `401 Unauthorized` | Nomor Kartu Izin Meledakkan (KIM / KJL) juru ledak tidak terdaftar di basis data inspektur tambang Ditjen Minerba ESDM. |
| **ERR-BL-11912** | `BlastSirenSequenceViolationException`| `400 Bad Request` | Detonasi dieksekusi tanpa melalui tahapan sirine peringatan wajib 3 tahap (15 menit, 5 menit, 1 menit). |
| **ERR-GT-11920** | `CriticalSlopeDisplacementException` | `409 Conflict` | Laju deformasi radar lereng $\ge 15\text{ mm/jam}$. Seluruh perintah non-evakuasi ditolak hingga KTT menyetujui perintah tanggap darurat. |
| **ERR-GT-11921** | `BishopFosCalculationDivergence` | `500 Server Error` | Iterasi irisan Bishop tidak konvergen karena sudut kemiringan lereng melebihi sudut geser dalam batuan tanpa kohesi. |
| **ERR-MR-11930** | `InvalidBargeDraftReadingException` | `422 Unprocessable` | Pembacaan draf 6 titik tidak realistis (misal: draf negatif, sarat air melampaui kedalaman palka fisik tongkang, atau densitas air di luar 0.98–1.05 kg/L). |
| **ERR-MR-11931** | `HydrostaticToleranceExceededException`| `422 Unprocessable` | Selisih muatan tonase draf survey vs akumulasi timbangan jembatan timbang dermaga melebihi ambang batas toleransi $\pm 1.5\%$. |
| **ERR-MR-11932** | `MissingCustomsPortClearanceException` | `403 Forbidden` | Pengapalan tongkang belum mengantongi Surat Persetujuan Berlayar (SPB) KSOP atau nomor segel palka resmi. |

---

## 2. Rincian Penanganan & Pemulihan Eksepsi Kritis

### 2.1 Penanganan ERR-BL-11910: BlastPerimeterBreachException
- **Kondisi Galat**:
  ```json
  {
    "message": "Dilarang tembak: 2 personel masih berada di radius bahaya peledakan.",
    "run_code": "BLAST-20261107-A91B",
    "status": "DRAFT",
    "is_perimeter_safe": false,
    "detected_breaches_count": 2,
    "breached_personnel": [
      {
        "worker_id": "EMP-9021",
        "name": "Operator Dozer Pit North",
        "distance_meters": 342.5
      }
    ]
  }
  ```
- **Tindakan Pemulihan**:
  1. Jangan mencoba memotong alur (*bypass*) atau mematikan sistem radar telematika.
  2. Arahkan operator dozer yang berada pada jarak 342.5 meter keluar dari perimeter lingkaran 500 meter.
  3. Kirimkan permintaan verifikasi ulang via HTTP `POST /mining/fase119/blast/verify-clearance`.
  4. Bila jumlah pelanggaran telah mencapai 0, sistem secara otomatis mengizinkan transisi status ke `PERIMETER_CLEARED`.

### 2.2 Penanganan ERR-UG-11903: VentilationUnconvergedException
- **Kondisi Galat**:
  ```json
  {
    "message": "Solver Hardy-Cross gagal mencapai toleransi konvergensi 0.001 m3/s setelah 100 iterasi.",
    "max_residual_error_m3_s": 0.084,
    "is_converged": false,
    "status": "REQUIRES_RECALIBRATION"
  }
  ```
- **Tindakan Pemulihan**:
  1. Periksa nilai tahanan Atkinson ($R$) pada setiap cabang. Pastikan tidak ada cabang dengan resistansi bernilai 0 ($R = 0$) atau nilai tahanan yang melompat lebih dari $10^4$ kali cabang di sebelahnya.
  2. Periksa definisi lintasan arah putaran loop (`directions`): arah loop harus membentuk sirkuit tertutup berkesinambungan.
  3. Berikan tebakan debit awal ($Q_0$) yang mendekati estimasi hukum Kirchhoff I (jumlah debit masuk junction $\approx$ debit keluar).

### 2.3 Penanganan ERR-MR-11931: HydrostaticToleranceExceededException
- **Kondisi Galat**:
  ```json
  {
    "message": "Deviasi muatan bersih draf survey (7,850 WMT) terhadap timbangan jetty (7,520 WMT) adalah 4.38% (Batas toleransi max 1.50%).",
    "status": "DISCREPANCY_FLAGGED",
    "variance_percent": 4.38
  }
  ```
- **Tindakan Pemulihan**:
  1. Periksa tabel koreksi hidrostatik kapal tongkang yang digunakan. Pastikan nomor lambung tongkang (*hull ID*) sesuai dengan sertifikat ukur kapal yang diterbitkan biro klasifikasi (BKI / Lloyd's Register).
  2. Verifikasi ulang pembacaan hidrometer perairan: kadar salinitas air muara sungai yang bercampur air laut dapat berfluktuasi saat air pasang/surut.
  3. Periksa selisih *hogging/sagging*: defleksi lambung yang melebihi 0.15 meter membutuhkan koreksi formula draf rata-rata kuartil secara ketat.


---

## 3. Matriks Galat Lanjutan Modul Pertambangan Fase 119

| Kode Galat | Nama Eksepsi | Modul Terkait | Dampak & Tindakan Pencegahan |
| :--- | :--- | :--- | :--- |
| **ERR-GT-11922** | `PiezometerThresholdExceededException` | Geoteknik | Tekanan air pori melebihi ambang batas hidrostatis kritis lereng ($u > 250\text{ kPa}$). Jalankan pemboran sumur horizontal drain segera. |
| **ERR-GT-11923** | `DroneGcpAccuracyDeviationException` | Kuari Drone | Kesalahan penutup Ground Control Points (GCP) $> 10\text{ cm}$. Lakukan kalibrasi ulang koordinat baseline RTK/PPK GNSS. |
| **ERR-GT-11924** | `TinVolumetricMeshIntersectionError` | Kuari Drone | Permukaan atas timbunan memotong dasar topografi secara tidak valid. Periksa batas poligon kontur stockpile. |
| **ERR-BL-11913** | `FlyrockSafetyRadiusUnderestimated` | Peledakan | Radius peledakan yang diajukan $< 500\text{ m}$. Sistem menolak nilai di bawah standar minimum KTT. |
| **ERR-BL-11914** | `DetonatorBoxMiscommunicationError` | Peledakan | Gagal menjalin jabat tangan (*handshake*) terenkripsi dengan mesin blasting digital. Periksa kabel pemicu dan resistansi sirkuit. |
| **ERR-BL-11915** | `MuckpileFragmentationNonSieveable` | Peledakan | Kurva Rosin-Rammler menghasilkan nilai negatif akibat parameter batuan tidak valid. Masukkan nilai rock factor $A \ge 1.0$. |
| **ERR-UG-11905** | `AuxiliaryFanStaticPressureDeficit` | Bawah Tanah | Kipas bantu tidak mampu mengatasi gesekan saluran pipa ventilasi elastis (*flexible duct*). Naikkan daya fan VSD. |
| **ERR-UG-11906** | `RockMassRatingOutOfPhysicalRange` | Bawah Tanah | Nilai RMR Bieniawski di luar 0–100. Evaluasi ulang 6 parameter dasar geomekanika batuan. |
| **ERR-MR-11933** | `BargeHeelExcessiveTiltException` | Pengapalan | Kemiringan melintang (*heel/list*) tongkang $> 2.5^\circ$. Distribusikan ulang muatan curah di palka untuk menstabilkan titik metasentris. |
| **ERR-MR-11934** | `BargeSaggingStructuralStressRisk` | Pengapalan | Defleksi lambung kapal (*sagging*) $> 0.25\text{ m}$. Hentikan pemuatan konsentrat di bagian tengah palka; muat terlebih dahulu ruang haluan dan buritan. |
| **ERR-MR-11935** | `WaterDensityHydrometerOutOfBound` | Pengapalan | Densitas air yang dicatat surveyor $< 0.98\text{ kg/L}$ atau $> 1.05\text{ kg/L}$. Kalibrasi ulang tabung hidrometer di laboratorium bersertifikat. |
| **ERR-MR-11936** | `FreightLaytimeCalculationDispute` | Pengapalan | Jam laytime aktual bernilai negatif. Periksa waktu pencatatan Notice of Readiness (NOR) dan Surat Perintah Bongkar Muat. |
| **ERR-MR-11937** | `LedgerJournalPostingFailedException` | Ledger Mining | Akun beban atau kas operasional tidak ditemukan di buku besar. Sinkronkan chart of accounts `MiningAccounts.php`. |
