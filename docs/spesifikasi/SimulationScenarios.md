# Spesifikasi Teknis: Katalog Skenario Simulasi Bisnis & Stres Pasar (Fase 67 & R8)

Dokumen ini mendefinisikan spesifikasi katalog skenario bisnis standar, parameterisasi variabel ekonomi makro, toleransi metrik evaluasi, dan arsitektur pengujian stres (*stress-testing engine*) pada platform Superweb.

---

## 1. Konsep & Arsitektur Skenario

Skenario simulasi bisnis adalah representasi terstruktur dari kondisi operasional khusus (baik kondisi normal, fluktuasi musiman, maupun krisis pasar ekstrem) yang diterapkan pada satu atau lebih modul domain dalam rentang hari virtual tertentu.

Setiap skenario direpresentasikan oleh objek nilai `SimulationScenario` yang membawa:
- **`code`:** Identifier alfanumerik unik (misal: `hospital_surge`, `hotel_holiday_peak`).
- **`title` & `description`:** Penjelasan kontekstual bagi operator dan pengambil keputusan.
- **`duration_days`:** Jangka waktu simulasi virtual yang dibutuhkan (14 hingga 365 hari).
- **`default_seed`:** Seed MINSTD baku untuk memastikan reprodusibilitas hasil pengujian.
- **`parameters`:** Pasangan kunci-nilai parameter spesifik skenario.
- **`active_modules`:** Daftar modul domain yang diikutsertakan dalam simulasi.

---

## 2. Katalog Tiga Skenario Bawaan (Built-in Scenarios)

### 2.1. Skenario 1: Lonjakan Pasien Pasca Bencana (`hospital_surge`)
- **Durasi:** 30 Hari Virtual.
- **Seed Baku:** `1001`.
- **Modul Aktif:** `Hospital`, `Logistics`, `Finance`.
- **Parameter Kunci:**
  - `patient_arrival_multiplier = 2.5`: Tingkat kedatangan pasien rawat inap meningkat 250% dibanding hari biasa.
  - `discharge_rush = true`: Kebijakan percepatan pemulangan pasien dengan masa rawat $\ge 3$ hari untuk membebaskan tempat tidur.
- **Tujuan Pengujian:** Menguji ketahanan kapasitas bangsal rawat inap (100 tempat tidur) dan mendeteksi apakah tingkat okupansi melampaui batas kritis 90%.

### 2.2. Skenario 2: Puncak Musim Liburan Hotel & Resort (`hotel_holiday_peak`)
- **Durasi:** 14 Hari Virtual.
- **Seed Baku:** `2002`.
- **Modul Aktif:** `Hotel`, `Finance`.
- **Parameter Kunci:**
  - `occupancy_rate = 0.98`: Okupansi kamar dipertahankan pada 98% selama dua minggu penuh.
  - `overbooking_buffer = 0.05`: Alokasi overbooking toleransi 5% di atas inventaris 50 kamar.
- **Tujuan Pengujian:** Memvalidasi akrual tarif kamar harian deterministik, rekonsiliasi pajak hotel 10%, dan perhitungan Average Daily Rate (ADR) serta RevPAR.

### 2.3. Skenario 3: Kemacetan Pelabuhan Logistik & Lonjakan Demurrage (`port_congestion_demurrage`)
- **Durasi:** 45 Hari Virtual.
- **Seed Baku:** `3003`.
- **Modul Aktif:** `Logistics`, `Finance`.
- **Parameter Kunci:**
  - `delay_days_average = 8`: Rata-rata keterlambatan pengambilan peti kemas melampaui batas masa bebas (5 hari).
  - `penalty_escalation = true`: Penerapan denda bertingkat (Tier 1 Rp 250.000/hari, Tier 2 Rp 500.000/hari).
- **Tujuan Pengujian:** Membuktikan perhitungan denda bertingkat yang akurat, akumulasi denda demurrage tanpa selisih pembulatan, dan posting pendapatan denda pelabuhan ke buku besar.

---

## 3. Simulator Guncangan Makroekonomi (`VirtualMarketStressSimulator`)

Selain skenario operasional modul mikro, kernel menyediakan simulator faktor guncangan ekonomi makro:
- **Profil `standard`:** Fluktuasi normal ($\pm 0.5\%$).
- **Profil `mild_stress`:** Fluktuasi suku bunga dan inflasi skala ringan (faktor 1.2x).
- **Profil `moderate_volatility`:** Volatilitas nilai tukar dan indeks VIX meningkat (faktor 1.5x).
- **Profil `severe_shock`:** Guncangan pasar parah (faktor 2.5x) dengan lonjakan tajam suku bunga acuan dan pelemahan mata uang.

Setiap hari virtual menghasilkan tuple kondisi makro:
$$\text{Kondisi}(t) = (\text{BaseRate}, \text{Inflation}, \text{FxRateUSD}, \text{VIX})$$
yang dapat diserap oleh modul Treasury, Keuangan, dan Pengadaan untuk menyesuaikan kebijakan harga harian.

---

## 4. Evaluasi & Rekonsiliasi Hasil Skenario (`SimulationScenarioReconcilerService`)

Setelah skenario selesai dieksekusi, hasilnya direkonsiliasi terhadap batas ambang toleransi yang diharapkan (*benchmark criteria*):
1. **Aturan Batas Minimum (`min`):** Memastikan metrik tidak jatuh di bawah batas aman (misal: rasio likuiditas minimum $\ge 1.2$).
2. **Aturan Batas Maksimum (`max`):** Memastikan metrik tidak melampaui batas risiko (misal: tingkat okupansi tempat tidur $\le 95\%$).
3. **Status Evaluasi:**
   - `OK`: Sesuai batas toleransi.
   - `FAILED_MIN`: Nilai berada di bawah batas minimum.
   - `FAILED_MAX`: Nilai melampaui batas risiko.
   - `MISSING`: Metrik wajib tidak dilaporkan oleh modul.

Hasil rekonsiliasi menghasilkan laporan komprehensif yang memandu operator apakah sistem lulus sertifikasi skenario atau memerlukan penyesuaian parameter operasional.

