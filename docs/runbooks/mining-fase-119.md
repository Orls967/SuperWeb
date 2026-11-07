# Runbook Insiden & Prosedur Tanggap Darurat Operasional (Fase 119)

## 1. Matriks Klasifikasi Keparahan Insiden Operasional

| Level | Klasifikasi | Waktu Respons Maksimal | Otoritas Komando | Contoh Insiden |
| :--- | :--- | :--- | :--- | :--- |
| **SEV-1** | **Bencana Kritis / Fatalitas** | Segera (< 5 Menit) | Kepala Teknik Tambang (KTT) | Kegagalan Lereng Masif (> 15 mm/jam), Akumulasi Metana > 1.0%, Personel di Radius Ledak |
| **SEV-2** | **Bahaya Operasi Utama** | < 15 Menit | Superintendent Departemen | Fan Trip Ventilasi Utama, FoS Lereng < 1.20, Selisih Muatan Tongkang > 3.0% |
| **SEV-3** | **Anomali / Deviasi** | < 1 Jam | Mine Engineer Lapangan | Boulders Muckpile > 20%, Deviasi Tekanan Air Pori Piezometer, Keterlambatan Laytime Sandar |

---

## 2. Runbook RB-119-01: Anomali Gas Beracun / Metana Tambang Bawah Tanah

### 2.1 Indikator Pemicu
- Telemetri sensor stope melaporkan konsentrasi Metana ($CH_4$) $\ge 1.00\%$ atau Karbon Monoksida ($CO$) $\ge 50\text{ ppm}$ atau Oksigen ($O_2$) $< 19.5\%$.
- Model `MiningUndergroundStope` otomatis berubah status menjadi `RESTRICTED_GAS_HAZARD`.

### 2.2 Tindakan Tanggap Darurat Seketika
1. **Pemutusan Daya Listrik Otomatis (Power Interlock)**:
   - Sistem kendali SCADA memutus daya panel gardu bawah tanah menuju peralatan non-intrinsik dalam radius 200 meter dari stope terancam untuk mencegah percikan listrik.
2. **Evakuasi Personil ke Refuge Chamber**:
   - Seluruh pekerja di level terdampak wajib mengenakan alat bantu pernapasan mandiri (*Self-Contained Self-Rescuer* - SCSR).
   - Bergerak menuju ruang penyelamat darurat bawah tanah (*refuge chamber*) terdekat yang dilengkapi pasokan oksigen mandiri bertekanan positif 72 jam.
3. **Penyaluran Ventilasi Tambahan (Ventilation Re-routing)**:
   - Petugas ventilasi mengaktifkan *auxiliary ducting fan* bertekanan tinggi untuk membilas (*purge*) kantong gas menuju *return exhaust airway*.
4. **Verifikasi Ambang Gas Pasca-Purging**:
   - Tim Penyelamat Tambang (*Mine Rescue Team*) melakukan pengetesan gas atmosfer manual menggunakan multi-gas detector terkalibrasi.
   - Status stope hanya boleh dipulihkan menjadi `OPERATIONAL` setelah $O_2 \ge 20.0\%$, $CH_4 < 0.25\%$, dan $CO < 10\text{ ppm}$ selama 2 jam kontinu.

---

## 3. Runbook RB-119-02: Pemadaman Kipas Utama Ventilasi (Main Fan Trip & Airflow Reversal)

### 3.1 Indikator Pemicu
- Kipas ventilasi utama poros *downcast/upcast* mengalami gangguan mekanikal / listrik (*fan trip*), menyebabkan kehilangan tekanan statis ventilasi $> 70\%$.
- Simulasi solver Hardy-Cross mendeteksi pembalikan arah aliran udara alami (*natural ventilation reversal*) di lintasan lereng stope aktif.

### 3.2 Tindakan Penanganan
1. **Transisi ke Mode Daya Darurat**:
   - Sistem transfer saklar otomatis (*Automatic Transfer Switch* - ATS) menghidupkan genset diesel cadangan pembangkit listrik ventilasi tambang dalam waktu $\le 60$ detik.
2. **Pembersihan Udara Berbalik**:
   - Juru ventilasi menutup pintu ventilasi pneumatik (*ventilation airlock doors*) pada stope yang rawan terpapar aliran balik gas buang knalpot diesel.
3. **Pengurangan Beban Emisi**:
   - Hentikan seluruh operasional armada truk *underground haul truck* dan alat muat *LHD (Load-Haul-Dump)* bermesin diesel.
4. **Pemeriksaan Solver Hardy-Cross**:
   - Jalankan command CLI: `php artisan mining:run-ventilation --rpm=80 --headcount=20`.
   - Pastikan aliran udara bersih mencapai debit minimum regulasi sebelum mengizinkan personil melanjutkan pengeboran.

---

## 4. Runbook RB-119-03: Pelanggaran Perimeter Sterilisasi Peledakan (Blast Breach)

### 4.1 Indikator Pemicu
- Eksekusi action `VerifyBlastPerimeterClearanceAction` melaporkan `all_personnel_evacuated = false` atau `detected_breaches_count > 0`.
- Terdapat pekerja atau unit kendaraan tambang yang terdeteksi berada di dalam radius eksklusi bahaya flyrock (lingkaran 500 meter).

### 4.2 Tindakan Penanganan
1. **Pencegahan Penembakan Otomatis (Hard Interlock Abort)**:
   - Sistem secara deterministik menonaktifkan sirkuit pengisian muatan mesin peledak elektronik (*blasting machine disarm*). Tombol tembak di UI diblokir.
2. **Pencegatan Personel**:
   - Koordinator sterilisasi (*Blasting Roadblock Warden*) mengontak pekerja via radio dua arah frekuensi darurat channel VHF 16.
   - Mengirimkan mobil patroli HSE untuk mengevakuasi pekerja keluar melintasi batas pita barikade merah.
3. **Verifikasi Ulang GPS Radar**:
   - Setelah personil keluar dari zona bahaya, jalankan kembali fungsi pemindaian radar perimeter.
   - Bila hasil pemindaian menyatakan 0 pelanggaran, juru ledak bersertifikat KIM dapat melanjutkan peniupan sirine tahap akhir.

---

## 5. Runbook RB-119-04: Alarm Percepatan Deformasi Lereng Radar (Slope Acceleration)

### 5.1 Indikator Pemicu
- Radar pemantau lereng (SSR / InSAR) mendeteksi laju pergerakan dinding pit $\ge 5.0\text{ mm/jam}$ atau regangan kumulatif melonjak tajam dalam jendela 3 jam.
- Perhitungan numerik irisan Bishop menghasilkan Faktor Keamanan lereng $\text{FoS} < 1.10$.

### 5.2 Tindakan Evakuasi & Penanganan
1. **Deklarasi Darurat KTT**:
   - Kepala Teknik Tambang segera membunyikan alarm sirene bahaya longsor di pit dan mengaktifkan action `TriggerGeotechEvacuationAlarmAction`.
   - Status sektor lereng ditetapkan menjadi `CRITICAL_EVACUATION`.
2. **Penghentian Operasi & Penutupan Jalur Hauling**:
   - Hentikan seluruh aktivitas penggalian ekskavator dan truk di jenjang (*bench*) bawah yang berada di dalam zona lintasan potensi luncuran material longsoran (*runout zone*).
   - Pengawas lapangan menutup jalan akses tambang (*mine haul road*) dengan pembatas tanggul keselamatan (*bund wall*).
3. **Investigasi Rekayasa Geoteknik**:
   - Drone pemetaan udara diterbangkan untuk mengambil model elevasi digital (DEM) dan melacak perambatan rekahan tarik di mahkota lereng (*tension cracks*).
   - Geoteknik engineer melakukan *back-analysis* kohesi ($c$) dan sudut geser dalam ($\phi$) batuan.
4. **Pemasangan Penyangga & Pemulihan**:
   - Lakukan penimbunan *counterweight buttress* pada kaki lereng dan pemboran pipa pelepas tekanan air tanah.
   - Pelaksanaan pekerjaan dicatat ke buku besar melalui action `RemediateSlopeStabilityAction`.
   - Penutupan insiden hanya dapat disetujui jika laju pergerakan telah melambat di bawah $0.5\text{ mm/jam}$ selama minimal 48 jam.

---

## 6. Runbook RB-119-05: Selisih Draf Survey Tongkang Melebihi Batas Toleransi (> 1.5%)

### 6.1 Indikator Pemicu
- Hasil rekonsiliasi draf survey hidrostatik tongkang (M9) mendeteksi deviasi tonase muatan bersih terhadap akumulasi timbangan jembatan timbang dermaga $\Delta > 1.5\%$.
- Model `MiningBargeDraftSurvey` berubah status menjadi `DISCREPANCY_FLAGGED`.

### 6.2 Tindakan Investigasi
1. **Pemeriksaan Ulang Densitas Air Perairan**:
   - Ambil sampel air pada tiga kedalaman (permukaan, tengah draf, dan dasar lunas kapal) di sekitar lambung tongkang.
   - Ukur kembali densitas air menggunakan hidrometer bersertifikat kalibrasi laboratorium uji terakreditasi KAN.
2. **Pemeriksaan Kompartemen Void & Tangki Balas**:
   - Masuki kompartemen palka kosong (*void spaces*) dan tangki dasar ganda (*double bottom ballast tanks*) tongkang.
   - Pastikan tidak ada kebocoran air perairan yang masuk ke palka muatan atau akumulasi air balas tersembunyi yang belum terpompa keluar.
3. **Pemeriksaan Kalibrasi Jembatan Timbang (Weighbridge)**:
   - Periksa sertifikat tera metrologi legal pada timbangan ban berjalan (*belt scale*) atau jembatan timbang truk dermaga.
4. **Penyelesaian Selisih (Dispute Resolution)**:
   - Jika investigasi membuktikan kesalahan pembacaan draf akibat gelombang ombak tinggi, lakukan pembacaan ulang draf saat air perairan tenang di dermaga terlindung (*sheltered dock*).
   - Jika terbukti terdapat selisih tonase riil, sesuaikan faktur muatan berdasarkan angka draf survey independen sebelum izin berlayar diterbitkan.


---

## 7. Runbook RB-119-06: Infiltrasi Air Asam Tambang Bawah Tanah (Acid Mine Inflow)

### 7.1 Indikator Pemicu
- Sensor kualitas air limbah bawah tanah mendeteksi penurunan nilai keasaman $\text{pH} < 4.0$ atau lonjakan konsentrasi ion logam terlarut ($Fe > 7\text{ mg/L}, Mn > 4\text{ mg/L}$).
- Piezometer merekam lonjakan tekanan hidraulik air tanah secara tiba-tiba pada dinding stope.

### 7.2 Tindakan Pengendalian
1. **Penyekatan Daerah Rembesan (Grouting & Bulkhead Sealing)**:
   - Pasang bendungan penyekat kedap air (*concrete bulkhead*) pada terowongan menuju stope tergenang.
   - Lakukan injeksi semen bertekanan tinggi (*microfine cement grouting*) atau resin poliuretan ke dalam rekahan pembawa air.
2. **Penyedotan & Pengapuran (Dewatering & Lime Neutralization)**:
   - Pompa lumpur *submersible slurry pump* mengalirkan air asam tambang ke kolam pengolahan netralisasi permukaan.
   - Lakukan pengapuran menggunakan kapur tohor ($CaO$) atau hidrasida kalsium ($Ca(OH)_2$) hingga pH mencapai $6.0 - 9.0$ baku mutu Kepmen LH No. 113/2003.

---

## 8. Runbook RB-119-07: Kegagalan Mesin Tugboat Saat Towing Tongkang di Selat Sempit

### 8.1 Indikator Pemicu
- Kapal tunda (*tugboat*) penarik tongkang batubara/nikel mengalami kerusakan mesin utama (*main engine blackout*) saat memandu tongkang melintasi alur pelayaran sempit atau mendekati dermaga jeti.
- Telemetri AIS dari `BulkMarineVesselLogisticsGateway` mendeteksi kecepatan hanyut (*drift velocity*) $> 2.5\text{ knot}$ ke arah karang dangkal.

### 8.2 Tindakan Penanganan
1. **Lego Jangkar Darurat (Emergency Anchoring)**:
   - Nakhoda tugboat memerintahkan pelepasan jangkar darurat (*emergency drop anchor*) pada tongkang untuk menahan laju hanyut.
2. **Pengerahan Tugboat Bantuan (Assist Tugboat Dispatch)**:
   - Port Captain dermaga khusus segera mengirimkan kapal tunda pembantu (*standby assist tugboat*) berdaya minimum $2 \times 1200\text{ HP}$.
3. **Pemberitahuan Navigasi (Securite Broadcast)**:
   - Syahbandar memancarkan siaran peringatan bahaya navigasi (*Securite*) pada radio VHF Marine Channel 16 agar kapal lain menjaga jarak aman minimal 1 mil laut.

---

## 9. Runbook RB-119-08: Runtuhan Batuan Lokal di Stope Bawah Tanah (Rockfall Incident)

### 9.1 Indikator Pemicu
- Sensor geofon seismik mikro (*microseismic acoustic emission*) mendeteksi pelepasan energi tegangan tinggi (> 10 kJ).
- Terjadi runtuhan bongkah batuan dari atap stope (*roof collapse*).

### 9.2 Tindakan Tanggap Darurat & Penyelamatan
1. **Isolasi Zona Bahaya**:
   - Tarik garis batas merah dan pasang rambu larangan melintas pada seluruh persimpangan jalan menuju stope terdampak.
2. **Penilaian Kestabilan Sekunder (Secondary Stability Assessment)**:
   - Geoteknik tambang melakukan pemindaian laser scanner 3D untuk memetakan volume batuan yang runtuh.
3. **Pemasangan Penyangga Tambahan**:
   - Pasang baut batuan gesekan (*friction rock bolts / split sets*) dan jaring kawat baja berdaya regang tinggi sebelum operasi pembersihan material runtuhan diizinkan.
