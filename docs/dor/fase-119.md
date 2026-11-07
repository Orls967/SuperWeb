# Definition of Ready (DoR) — Fase 119: Underground & Quarry Digital Twin, Geotech & Blast Management

**Penyusun:** Tim Arsitektur & Rekayasa Antigravity (Pelaksana G7-P4)  
**Tanggal:** 2026-11-07  
**Fase:** 119 — Pertambangan  
**Target Rilis:** PR #84 (Fase 119)  
**Status:** 🔵 SIAP DIKERJAKAN (L.1)

---

## 1. Ringkasan Ruang Lingkup Bisnis & Konseptual

Fase 119 memperluas modul Pertambangan (`Mining`, tabel prefiks `min_`) ke ranah operasi tambang bawah tanah (*underground mining*), pemodelan kembaran digital (*digital twin*) jaringan ventilasi stope/decline, keselamatan peledakan terpadu (*blast clearance & KCO fragmentation*), pemantauan stabilitas lereng geoteknik (*geotechnical slope failure early-warning*), survei volumetrik fotogrametri drone, dan pengoperasian kapal tongkang muatan curah (*barge marine ops*).

Sesuai mandat ketat W8, fase ini menolak keras implementasi tiruan/kerangka dangkal dan mewajibkan implementasi logika bisnis penuh dengan kepatuhan perhitungan fisika/metalurgi bersertifikat.

---

## 2. Rencana Anggaran Biaya Baris (RAB) per Lapisan Logika (Target B1 $\ge 6.000$)

| Lapisan Kode / Komponen | Target Estimasi Baris | Target Aktual Minimum | Deskripsi Tanggung Jawab Komponen |
| :--- | :--- | :--- | :--- |
| **Algoritma Bersertifikat & Value Object** | 800–1.200 | **900–1.100** | M7 `HardyCrossVentilationNetworkSolver`, M8 `KcoBlastingFragmentationModel`, M9 `HydrostaticDraftSurveyReconciler`, serta Value Object immutable pendukung (`AirFlowMetric`, `BlastRockProperty`, `DraftSurveyReading`, `BishopSafetyFactor`). |
| **State Machine & Enums** | 600–900 | **650–850** | `BlastClearanceRunStateMachine` (8 status), `GeotechIncidentStateMachine` (8 status), `BargeShipmentStateMachine` (8 status), tabel matriks transisi sah, guard validasi, exception transisi, dan pencatat jejak audit pergeseran state. |
| **Saga & Kontrak Integrasi Lintas Modul** | 450–700 | **500–700** | `BlastExecutionLifecycleSaga` dengan 3 cabang kompensasi pembatalan dan pembebasan perimeter, adaptor integrasi telematika pekerja/alat berat (`G7-K13`), adaptor logistik tongkang (`G7-K14`), dan jembatan ke `TwinBus`. |
| **Layanan Posting Buku Besar (Ledger)** | 300–500 | **320–450** | `MiningFase119LedgerPostingService` mengawal akrual bahan peledak ANFO, biaya sewa tongkang muatan curah, depresiasi peralatan survei drone/fotogrametri, dan provisi mitigasi lereng geoteknik. |
| **Aksi Domain & Layanan Aplikasi** | 3.000–4.000 | **3.200–3.600** | 20+ action terstruktur mengawal simulasi ventilasi stope, verifikasi radius aman peledakan, penanganan insiden geoteknik, evaluasi fragmentasi, kalkulasi survei topografi mesh 3D, dan rantai kustodi tongkang. |
| **Model Eloquent & Migrasi Database** | 500–800 | **550–700** | Model Eloquent dengan relasi, uuid, casting, dan scope query untuk stope ventilasi, log mikroseismik, pembacaan draft tongkang, dan jadwal peledakan. |
| **HTTP Layer (Controller, FormRequest, View)** | 400–600 | **450–600** | Controller tipis RESTful/Web, FormRequest dengan validasi ketat, ViewModel, dan Blade view responsif mobile (375px) untuk 4 dashboard operasi. |
| **Artisan Command & Laporan CSV** | 300–500 | **350–500** | Perintah CLI pemindaian berkala stabilitas lereng, simulasi ventilasi terjadwal, dan ekspor laporan neraca material tongkang curah ke CSV. |
| **Total Logika PHP Baru (B1)** | **$\ge 6.000$** | **$\ge 6.000$** | **MEMENUHI AMBANG KERAS W8 TANPA ISIAN** |

---

## 3. Spesifikasi Tiga Algoritma Bersertifikat Baru

### Algoritma M7: `HardyCrossVentilationNetworkSolver`
- **Konsep:** Jaringan ventilasi bawah tanah dimodelkan sebagai graf berarah dengan $N$ cabang (*branches*) dan $M$ simpal tertutup (*independent loops*).
- **Hukum Fisika:**
  1. Hukum Kirchhoff I: $\sum Q_{\text{in}} = \sum Q_{\text{out}}$ pada setiap simpul.
  2. Hukum Kirchhoff II: $\sum \Delta H = \sum (\pm R \cdot Q^2 - H_{\text{fan}}) = 0$ pada setiap loop tertutup.
- **Formulasi Koreksi Iteratif:**
  $$\Delta Q_k = -\frac{\sum_{i \in \text{loop}_k} (\pm R_i Q_i^2 - H_{\text{fan}, i})}{2 \sum_{i \in \text{loop}_k} R_i |Q_i|}$$
- **Keluaran:** Distribusi debit udara ($Q$, $\text{m}^3/\text{s}$), rugi tekanan total ($\text{Pa}$), daya kipas ventilasi ($\text{kW}$), status konvergensi ($<0.001\text{ m}^3/\text{s}$), dan sertifikat verifikasi digital HMAC-SHA256.

### Algoritma M8: `KcoBlastingFragmentationModel`
- **Konsep:** Model fragmentasi Kuz-Ram yang disempurnakan (Kuznetsov-Cunningham-Ouchterlony) untuk memprediksi kurva distribusi ukuran butir batuan pasca-peledakan.
- **Formulasi:**
  1. Ukuran Butir Rata-Rata ($x_{50}$, $\text{cm}$):
     $$x_{50} = A \cdot q^{-0.8} \cdot Q_e^{1/6} \cdot \left(\frac{115}{E}\right)^{19/30}$$
     di mana $A$ adalah faktor batuan (RMD), $q$ adalah faktor muatan (*powder factor*, $\text{kg}/\text{m}^3$), $Q_e$ adalah massa muatan per lubang ledak ($\text{kg}$), dan $E$ adalah kekuatan energi relatif ANFO ($=100$).
  2. Indeks Keseragaman Cunningham ($n$):
     $$n = \left(2.2 - 14 \frac{B}{d}\right) \sqrt{\frac{1 + S/B}{2}} \left(1 - \frac{W}{B}\right) \left(\frac{L_b}{L_{\text{tot}}}\right)$$
  3. Prediksi Fraksi Butir (Fungsi Rosin-Rammler / Swebrec):
     $$R(x) = \exp\left[-\left(\frac{x}{x_c}\right)^n\right]$$
     Menghitung persentase bongkahan *oversize* ($>1000\text{ mm}$) dan *fines* ($<25\text{ mm}$).
- **Keluaran:** $x_{50}$ ($\text{mm}$), indeks $n$, persentase *oversize*, persentase *fines*, rekomendasi koreksi *burden/spacing*, dan sertifikat verifikasi digital HMAC-SHA256.

### Algoritma M9: `HydrostaticDraftSurveyReconciler`
- **Konsep:** Pengukuran draf 6-titik kapal tongkang (Depan, Tengah, Belakang pada sisi Lambung Kiri/Port dan Lambung Kanan/Starboard) untuk menghitung perpindahan air (*displacement*) sebelum dan sesudah pemuatan material tambang.
- **Formulasi:**
  1. Draf Rata-Rata Koreksi Defleksi (*Quarter-Mean Draft*):
     $$d_M = \frac{d_{FP} + d_{FS} + 6(d_{MP} + d_{MS}) + d_{AP} + d_{AS}}{8}$$
  2. Koreksi Trim dan Kemiringan (*List*):
     $$t = \frac{d_{AP} + d_{AS}}{2} - \frac{d_{FP} + d_{FS}}{2}$$
  3. Koreksi Kerapatan Air (*Density Correction*):
     $$\Delta_{\text{koreksi}} = \Delta_{\text{tabel}} \times \left(\frac{\rho_{\text{aktual}}}{1.025}\right)$$
  4. Tonase Bersih Muatan:
     $$\text{Tonase Muat} = \Delta_{\text{akhir}} - \Delta_{\text{awal}} - \Delta_{\text{ballast/consumables}}$$
- **Keluaran:** Tonase hasil survei draf, perbandingan terhadap tiket jembatan timbang (persentase varians), status rekonsiliasi (*Lolos Toleransi* / *Investigasi Selisih*), dan sertifikat verifikasi digital HMAC-SHA256.

---

## 4. Dua State Machine Bersertifikat

1. **`BlastClearanceRunStateMachine` (8 status):**
   - `DRAFT`: Parameter peledakan dirancang oleh juru ledak.
   - `RADIUS_CALCULATED`: Radius aman bahaya peledakan dihitung otomatis berbasis muatan bahan peledak.
   - `PERMITS_VERIFIED`: Izin kerja panas/peledakan dan kelaikan peralatan telah divalidasi.
   - `SIREN_ACTIVE`: Sirene peringatan bahaya diaktifkan, hitung mundur evakuasi dimulai.
   - `ALL_CLEAR`: Telematika memastikan radius perimeter 100% steril dari personel dan aset.
   - `DETONATED`: Peledakan berhasil dieksekusi.
   - `MUCKPILE_INSPECTED`: Timbunan hasil ledakan (*muckpile*) diinspeksi untuk gas beracun dan fragmentasi.
   - `RESOLVED`: Area dinyatakan aman dan dibuka kembali untuk pemuatan ekskavator.
   - *(Cabang Exception: `BLOCKED` bila personel terdeteksi di perimeter, `ABORTED` bila cuaca buruk/petir).*

2. **`GeotechIncidentStateMachine` (8 status):**
   - `MONITORING_NORMAL`: Seluruh sensor displacement dan kecepatan lereng berada dalam batas hijau.
   - `THRESHOLD_EXCEEDED`: Salah satu sensor melewati batas deviasi awal ($>2\text{ mm/hari}$).
   - `PRE_WARNING`: Kecepatan pergerakan meningkat ($>5\text{ mm/hari}$), status siaga level kuning.
   - `ALARM_TRIGGERED`: Kecepatan longsor kritis terdeteksi ($>10\text{ mm/hari}$), sirene evakuasi berbunyi.
   - `AREA_EVACUATED`: Seluruh operator dan alat berat dievakuasi keluar dari sektor pit terdampak.
   - `INSPECTED_BY_GEOTECH`: Ahli geoteknik melakukan investigasi retakan dan bidang gelincir.
   - `REMEDIATED`: Dilakukan pelandaian lereng (*buttressing/unloading*).
   - `ALL_CLEAR`: Faktor keamanan lereng terverifikasi stabil ($FoS \ge 1.30$), izin operasi dipulihkan.

---

## 5. Saga Berkompensasi: `BlastExecutionLifecycleSaga`

- **Tahap 1 (Reserve Area & Sirene):** Mengunci area pit, mengaktifkan sirene bahaya, dan menerbitkan notifikasi evakuasi.
- **Tahap 2 (Verifikasi Geofence Telematika):** Memindai koordinat real-time seluruh pekerja dan truk di radius ledak.
  - *Cabang Kompensasi A:* Jika pekerja terdeteksi, batalkan hitung mundur peledakan, ubah status ke `BLOCKED`, nonaktifkan sirene, dan kirim teguran keselamatan ke supervisor.
- **Tahap 3 (Alokasi Bahan Peledak):** Mencatat kuota bahan peledak ANFO dan detonator dari gudang amunisi.
  - *Cabang Kompensasi B:* Jika pasokan bahan peledak tidak sinkron dengan kuota izin, batalkan alokasi dan kembalikan status ke `DRAFT`.
- **Tahap 4 (Detonasi & Ledger):** Eksekusi peledakan, catat konsumsi bahan peledak di buku besar via `MiningFase119LedgerPostingService`.
- **Tahap 5 (Inspeksi & Unfreeze):** Inspeksi pasca-ledak selesai $\rightarrow$ buka kembali kunci area tambang.

---

## 6. Kriteria Penerimaan Wajib (Acceptance Criteria)

1. **AC-119.1 (Ventilasi Bawah Tanah):** Solver Hardy Cross mensimulasikan jaringan ventilasi $\ge 10$ cabang dan mengonvergenkan debit udara dalam $\le 100$ iterasi dengan residu $<0.001\text{ m}^3/\text{s}$.
2. **AC-119.2 (Interlock Peledakan):** Upaya detonasi peledakan seketika DITOLAK sistem bila sensor telematika GPS mendeteksi pekerja di dalam radius aman perimeter ($<R_{\text{safety}}$).
3. **AC-119.3 (Model KCO & Koreksi Pemboran):** Bila fragmentasi *oversize* $>15\%$, sistem secara otomatis menerbitkan rekomendasi penyesuaian pola pemboran (*burden* dipersempit, *powder factor* dinaikkan) untuk siklus peledakan berikutnya.
4. **AC-119.4 (Early-Warning Geoteknik):** Kecepatan pergerakan dinding lereng $\ge 10\text{ mm/hari}$ memicu alarm darurat evakuasi, mengunci area pit, dan mencatat insiden geoteknik.
5. **AC-119.5 (Rekonsiliasi Tongkang & Ledger):** Hasil survei draf hidrostatik 6-titik tongkang merekonsiliasi tonase dengan tiket jembatan timbang ($\le 2\%$ toleransi), dan menerbitkan jurnal buku besar sewa tongkang (*marine freight*).
6. **AC-119.6 (Sabotase & Idempotensi):** Seeder T1 dengan 15 skenario bernama dapat dijalankan dua kali berturut-turut tanpa error duplikasi atau tabrakan saldo buku besar.
