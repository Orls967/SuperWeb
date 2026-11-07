# Runbook Operasional & Penanganan Insiden Pertambangan Fase 94

## 1. Ikhtisar & Matriks Eskalasi Insiden
Runbook ini memuat prosedur mitigasi teknis dan tanggap darurat operasional untuk insiden-insiden kritis pada sistem operasi pertambangan Fase 94. Seluruh insiden diklasifikasikan berdasarkan tingkat keparahan (*Severity Level*):

| Tingkat | Definisi | Waktu Tanggap (SLA) | Penanggung Jawab Komando |
|---|---|---|---|
| **SEV-1 (Kritis)** | Bahaya keselamatan jiwa, keruntuhan geoteknik lereng, luapan bendungan tailing, atau ledakan gas pit. | Segera ($< 5\text{ menit}$) | Kepala Teknik Tambang (KTT) & General Manager |
| **SEV-2 (Mayor)** | Manipulasi timbangan terdeteksi, anomali penagihan offtake berskala besar, atau penolakan LC bank. | $< 30\text{ menit}$ | Manajer Operasi & Manajer Keuangan |
| **SEV-3 (Moderat)** | Deviasi draft survey tongkang, varians neraca massa pabrik melebihi toleransi wajar. | $< 2\text{ jam}$ | Ahli Metalurgi & Superintendent Pelabuhan |
| **SEV-4 (Minor)** | Kesalahan format data sensor atau kegagalan sinkronisasi laporan berkala. | $< 8\text{ jam}$ | Operator IT Tambang |

---

## 2. RB-MIN-94-01: Deteksi Manipulasi / Kerusakan Rantai Timbang Digital (Weighbridge Hash-Chain Tampering)

### 2.1 Gejala & Deteksi Otomatis
- Peringatan pada log aplikasi: `CRITICAL: Hash chain verification mismatch on weighbridge WB-COAL-01`.
- Perintah audit `mining:audit-weighbridge-hash-chain` menghasilkan status galat non-nol: `BROKEN_CHAIN`.
- Tiket baru ditolak oleh `RecordHashChainWeighbridgeTicketAction` dengan pengecualian `InvalidArgumentException: Tanda tangan hash rantai sebelumnya tidak cocok`.

### 2.2 Prosedur Penanganan Cepat
1. **Isolasi Jembatan Timbang**:
   - Alihkan palang pintu otomatis jembatan timbang ke mode manual.
   - Pindahkan arus truk ke jembatan timbang cadangan (*weighbridge bay 2*).
2. **Investigasi Integritas Data**:
   - Jalankan audit basis data untuk mencari tiket terakhir yang valid:
     ```bash
     php artisan mining:audit-weighbridge-hash-chain --scale-id="WB-COAL-01" --detail
     ```
   - Bandingkan nomor tiket, stempel waktu, dan rekaman kamera CCTV pos timbang pada saat tiket bermasalah diterbitkan.
3. **Penyelidikan Forensik**:
   - Periksa apakah terjadi manipulasi manual langsung (*direct SQL UPDATE*) pada tabel `min_weighbridge_tickets`.
   - Periksa log otentikasi operator pos timbang untuk mendeteksi penggunaan akun tidak sah.
4. **Pemulihan Rantai (Re-anchoring Chain)**:
   - Apabila kerusakan diakibatkan kegagalan sinkronisasi jaringan lokal pos timbang (*offline queue conflict*), supervisor jembatan timbang bersama admin IT berwenang melakukan kalibrasi ulang dan menerbitkan *anchor ticket* baru bertanda tangan ganda (*dual supervisor authorization*).

---

## 3. RB-MIN-94-02: Anomali Pemantauan Keamanan Bendungan Tailing TSF (GISTM Piezometer Alarm)

### 3.1 Gejala & Deteksi Otomatis
- Aksi `AuditTailingsDamSafetyAction` menghasilkan status bahaya:
  - `is_piezometric_safe === false` (rasio garis freatik $> 0.45$).
  - `is_seepage_safe === false` (debit rembesan kaki bendungan $> 1.5\times$ garis dasar historis).
  - `is_liquefaction_safe === false` ($CSR / CRR \ge 1.0$).
- Notifikasi darurat terkirim ke kanal KTT dan Kepala Seksi Geoteknik.

### 3.2 Prosedur Tanggap Darurat Lapangan
1. **Penghentian Segera Pembuangan Slurry Tailing**:
   - Hentikan pompa pipa transfer tailing dari pabrik pengolahan/smelter menuju kolam pengendapan bersangkutan.
   - Buka katup pengalihan menuju kolam darurat penampungan sementar (*emergency impoundment cell*).
2. **Inspeksi Visual & Pengukuran Fisik**:
   - Tim geoteknik segera diterjunkan ke mercu bendungan (*crest*) dan hilir tanggul untuk memeriksa rekahan (*tension cracks*), penurunan tanah (*settlement*), atau rembesan keruh pembawa butiran tanah (*piping erosion*).
   - Verifikasi pembacaan piezometer vibrating wire manual di sumur pantau.
3. **Penurunan Muka Air Kolam (Emergency Water Depletion)**:
   - Hidupkan pompa apung darurat (*pontoon emergency pumps*) untuk mengalirkan kelebihan air permukaan kolam menuju instalasi pengolahan air limbah (*acid mine drainage plant*).
   - Pastikan tinggi jagaan (*freeboard*) kembali minimal $1.5\text{ meter}$.
4. **Pelaporan Regulator**:
   - Laporkan kejadian secara resmi ke Ditjen Minerba ESDM dan Inspektur Tambang dalam tempo $< 24\text{ jam}$ sesuai mandat GISTM.

---

## 4. RB-MIN-94-03: Konsentrasi Gas Berbahaya Pit Tambang Melebihi Batas Ambang K3

### 4.1 Gejala & Deteksi
- Sensor IoT stasioner atau instrumen multi-gas genggam pengawas mencatat:
  - Metana ($\text{CH}_4$) $> 500\text{ ppm}$ (atau $> 0.25\%$ LEL di udara).
  - Karbon Monoksida ($\text{CO}$) $> 25\text{ ppm}$.
  - Oksigen ($\text{O}_2$) $< 19.5\%$.
- Aksi `IngestMineGasReadingAction` menerbitkan status `DANGER_EVACUATE` dan memicu sirine evakuasi otomatis.

### 4.2 Prosedur Evakuasi & Pengendalian
1. **Evakuasi Sektor Pit**:
   - Seluruh personil dan operator alat berat di sektor terdampak wajib mematikan mesin, memasang rem parkir, dan segera berjalan menuju titik kumpul (*muster point*) di dataran tinggi berlawanan arah angin (*upwind direction*).
2. **Isolasi Sumber Nyala & Peledakan**:
   - Hentikan seluruh pekerjaan panas (*Hot Work*) dan tunda setiap rangkaian rencana peledakan di radius $500\text{ meter}$.
3. **Penyelidikan Ventilasi Alami & Buatan**:
   - Jika gas berasal dari singkapan batubara terbakar spontan (*spontaneous combustion*), kirimkan unit *Water Truck* pemadam dengan larutan busa penekan oksigen (*fire-retardant foam*).
4. **Izin Masuk Kembali (Re-entry Permit)**:
   - Area pit dilarang dimasuki hingga pengukuran ulang oleh petugas K3 membuktikan $\text{CH}_4 = 0\text{ ppm}$, $\text{CO} < 10\text{ ppm}$, dan $\text{O}_2 \ge 20.9\%$ selama minimal 2 jam berturut-turut.

---

## 5. RB-MIN-94-04: Selisih Rekonsiliasi Sarat Tongkang (Barge Draft Discrepancy > 2.0%)

### 5.1 Gejala & Deteksi
- Aksi `ReconcileBargeDraftSurveyAction` menandai status `DISCREPANCY_EXCEEDS_TOLERANCE`.
- Selisih antara akumulasi berat timbangan dermaga (*conveyor weightometer*) dan tonase survei sarat air tongkang melampaui toleransi $2.0\%$.

### 5.2 Prosedur Investigasi Bersama
1. **Pemeriksaan Salinitas & Densitas Air**:
   - Ukur ulang densitas air di pelabuhan muat menggunakan hidrometer terkalibrasi pada tiga kedalaman berbeda (permukaan, tengah, dasar).
2. **Pemeriksaan Ballast & Kebocoran Palka**:
   - Periksa tangki ballast tongkang untuk memastikan tidak ada air ballast yang belum dikosongkan secara tuntas (*residual ballast*).
   - Periksa kekeringan ruang palka muatan.
3. **Survei Ulang Bersama Surveyor Independen**:
   - Surveyor independen yang ditunjuk pembeli dan penjual (misalnya Sucofindo atau Carsurin) melakukan pembacaan sarat enam titik secara bersama (*joint re-reading*: Fwd Port/Stbd, Mid Port/Stbd, Aft Port/Stbd).
4. **Penyelesaian Administratif**:
   - Bila perbedaan tetap terjadi akibat faktor kelembapan curah hujan saat pemuatan, jalankan aksi `ReconcileMoistureLossAction` untuk menghitung ekuivalen berat kering (*Dry Metric Ton*).

