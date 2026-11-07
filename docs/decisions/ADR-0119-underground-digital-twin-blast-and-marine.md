# ADR-0119: Arsitektur Digital Twin Bawah Tanah, Simulasi Ventilasi Hardy Cross, Model Peledakan KCO, dan Rekonsiliasi Tongkang Curah

**Status:** DITERIMA  
**Tanggal:** 2026-11-07  
**Penyusun:** Pelaksana G7-P4 (Pertambangan)  
**Pembuat Keputusan:** Tim Arsitektur SuperWeb & Pemilik Repositori

---

## 1. Konteks dan Masalah Bisnis

Operasi pertambangan modern menuntut integrasi yang aman dan presisi tinggi antara fasilitas bawah tanah (*underground mine*), pit terbuka (*open pit*), dan logistik pengapalan curah:
1. **Bahaya Gas & Ventilasi Bawah Tanah:** Kurangnya pemodelan aerodinamika jaringan terowongan, decline, stope, dan shaft dapat memicu asfiksia pekerja atau penumpukan gas beracun ($CO$, $CH_4$, $NO_x$). Diperlukan solver numerik non-linier untuk menghitung kesetimbangan aliran udara dan tekanan kipas ventilasi.
2. **Risiko Peledakan & Fragmentasi Batuan:** Peledakan tambang berisiko fatal jika radius perimeter tidak steril dari pekerja/alat berat. Selain itu, peledakan yang menghasilkan ukuran batuan terlalu besar (*oversize* $>1\text{ meter}$) menyebabkan terhentinya *crusher* dan pembengkakan biaya pemecahan sekunder (*secondary breaking*).
3. **Ketidakstabilan Lereng & Longsor Geoteknik:** Pergerakan dinding tambang yang tidak terdeteksi sejak dini mengancam keselamatan ratusan jiwa. Sistem harus mampu mendeteksi akselerasi kecepatan longsor (*inverse velocity*) dan mengeksekusi evakuasi otomatis.
4. **Kebocoran Tonase Pengapalan Tongkang Curah:** Selisih antara timbangan darat jembatan timbang dan muatan aktual di atas tongkang sering menjadi sumber sengketa komersial dan kecurangan muatan. Diperlukan survei draf 6-titik hidrostatik dengan koreksi densitas air perairan.

---

## 2. Keputusan Arsitektur yang Ditetapkan

### A. Algoritma Simulasi Jaringan Ventilasi Hardy Cross (M7)
- Memilih metode iterasi **Hardy Cross** berbasis Hukum Kirchhoff I (konservasi massa udara pada simpul) dan Hukum Kirchhoff II (konservasi energi/tekanan pada loop tertutup).
- Penurunan tekanan gesekan dimodelkan dengan persamaan Atkinson:
  $$h_f = R \cdot Q^2$$
  di mana $R$ adalah resistansi aerodinamika terowongan ($\text{N}\cdot\text{s}^2/\text{m}^8$) dan $Q$ adalah debit aliran ($\text{m}^3/\text{s}$).
- Untuk setiap simpal tertutup $k$, koreksi debit dihitung dengan:
  $$\Delta Q_k = -\frac{\sum_{i} (\pm R_i Q_i^2 - h_{\text{fan}, i})}{2 \sum_{i} R_i |Q_i|}$$
- Iterasi dihentikan saat $\max|\Delta Q_k| < 10^{-3}\text{ m}^3/\text{s}$ atau mencapai 100 siklus iterasi.
- Hasil perhitungan diverifikasi dan ditandatangani secara kriptografis menggunakan sertifikat HMAC-SHA256 (`HardyCrossVentilationNetworkSolverResult`).

### B. Model Fragmentasi Peledakan Kuznetsov-Cunningham-Ouchterlony / KCO (M8)
- Memilih model gabungan Kuznetsov dan Cunningham (disempurnakan oleh Ouchterlony) untuk memprediksi kurva distribusi ukuran butir pasca-ledak.
- Menghitung ukuran median $x_{50}$ berbasis faktor massa batuan (*Rock Mass Descriptor* / RMD), faktor muatan bahan peledak (*powder factor* $q$), massa muatan per lubang ledak ($Q_e$), dan energi relatif bahan peledak.
- Menghitung indeks keseragaman $n$ berbasis rasio *burden/spacing*, deviasi lubang bor, dan rasio kolom isian (*charge length* vs *bench height*).
- Menghitung fraksi persentase butiran *oversize* ($>1000\text{ mm}$) dan *fines* ($<25\text{ mm}$).
- Jika persentase *oversize* melebihi batas toleransi ($>15\%$), mesin analitik secara deterministik menerbitkan rekomendasi penyesuaian pola pemboran (*drill pattern adjustment*) untuk siklus peledakan berikutnya.

### C. Rekonsiliasi Hidrostatik Survei Draf Tongkang 6-Titik (M9)
- Menggunakan pembacaan 6 titik draf lambung tongkang:
  - Haluan kiri (*Forward Port* / $d_{FP}$) dan Haluan kanan (*Forward Starboard* / $d_{FS}$).
  - Tengah kiri (*Midship Port* / $d_{MP}$) dan Tengah kanan (*Midship Starboard* / $d_{MS}$).
  - Buritan kiri (*Aft Port* / $d_{AP}$) dan Buritan kanan (*Aft Starboard* / $d_{AS}$).
- Koreksi defleksi lambung kapal dihitung menggunakan metode kuarter rata-rata (*Quarter-Mean Draft*):
  $$d_M = \frac{d_{FP} + d_{FS} + 6(d_{MP} + d_{MS}) + d_{AP} + d_{AS}}{8}$$
- Koreksi kerapatan air perairan dihitung terhadap standar air laut internasional ($1.025\text{ t/m}^3$).
- Selisih antara tonase hidrostatik kapal tongkang dan tonase jembatan timbang dikomparasikan terhadap ambang batas toleransi statutory $2.0\%$.

### D. Keamanan Peledakan Berpagar State Machine dan Saga Kompensasi
- Mengimplementasikan `BlastClearanceRunStateMachine` (8 status: `DRAFT`, `RADIUS_CALCULATED`, `PERMITS_VERIFIED`, `SIREN_ACTIVE`, `ALL_CLEAR`, `DETONATED`, `MUCKPILE_INSPECTED`, `RESOLVED`).
- Mengimplementasikan `BlastExecutionLifecycleSaga` dengan 3 cabang kompensasi:
  1. *Perimeter Breach Compensation:* Jika telematika mendeteksi pekerja di radius aman peledakan, batalkan hitung mundur, tandai state `BLOCKED`, nonaktifkan sirene, dan buka kembali kunci gerbang darurat.
  2. *Explosives Quota Compensation:* Jika inventaris amunisi/ANFO di gudang bahan peledak tidak memadai, batalkan alokasi izin dan kembalikan ke status perancangan `DRAFT`.
  3. *Adverse Weather Compensation:* Jika terdeteksi sambaran petir atau badai, hentikan prosedur dan tunda jadwal peledakan.

### E. Penanganan Insiden Stabilitas Lereng Geoteknik (State Machine)
- Mengimplementasikan `GeotechIncidentStateMachine` (8 status: `MONITORING_NORMAL`, `THRESHOLD_EXCEEDED`, `PRE_WARNING`, `ALARM_TRIGGERED`, `AREA_EVACUATED`, `INSPECTED_BY_GEOTECH`, `REMEDIATED`, `ALL_CLEAR`).
- Pemicu eskalasi otomatis berbasis kecepatan pergerakan:
  - Kecepatan $<2.0\text{ mm/hari}$: Normal (Hijau).
  - Kecepatan $2.0 - 4.9\text{ mm/hari}$: Threshold Exceeded (Kuning Muda).
  - Kecepatan $5.0 - 9.9\text{ mm/hari}$: Pre-Warning (Kuning Tua).
  - Kecepatan $\ge 10.0\text{ mm/hari}$ atau perpindahan kumulatif $\ge 50\text{ mm}$: Alarm Darurat Evakuasi Aktif (Merah - Site Lockout).

### F. Penjurnalan Buku Besar Keuangan (Double-Entry Ledger)
- Mengimplementasikan `MiningFase119LedgerPostingService` dengan akun-akun bagan akun standar terdaftar di `MiningAccounts`:
  1. *Biaya Bahan Peledak ANFO:* Debit `min:explosives_expense:IDR` | Kredit `min:explosives_inventory:IDR` (atau `min:bank_cash:IDR`).
  2. *Beban Sewa Tongkang / Freight Marine:* Debit `min:marine_freight_expense:IDR` | Kredit `min:barge_freight_payable:IDR`.
  3. *Beban Pemantauan Geoteknik & Survei Drone:* Debit `expense:min:geotech_monitoring:IDR` | Kredit `min:bank_cash:IDR`.
  4. *Provisi Pemulihan Lereng / Buttressing:* Debit `min:slope_remediation_expense:IDR` | Kredit `min:slope_remediation_provision:IDR`.

---

## 3. Konsekuensi Arsitektur

1. **Positif:**
   - Keamanan operasional tambang terjamin secara otomatis melalui integrasi telematika dan interlock peledakan.
   - Peniadaan data statis/palsu: simulasi ventilasi dan fragmentasi peledakan dihitung dengan persamaan fisika riil.
   - Ketahanan audit tinggi: setiap perhitungan dilengkapi sertifikat HMAC dan setiap mutasi status terikat pada mesin state berpagar guard.
   - Kepatuhan buku besar penuh: seluruh transaksi biaya peledakan, sewa kapal, dan mitigasi lereng tercatat seimbang.
2. **Mitigasi Kompleksitas:**
   - Solver Hardy Cross dibatasi maksimal 100 iterasi untuk mencegah kondisi loop tak hingga pada jaringan yang memiliki resistansi singular.
   - Perhitungan draf tongkang mendukung fallback ke tabel hidrometri kapal jika interpolasi displacement kurva polinomial tidak tersedia.
