# Pembuktian Matematis, Teorema & Analisis Batas Vektor Algoritma (Fase 91: H1-H5)

Dokumen ini berisi pembuktian analitis, penurunan rumus, kondisi batas konvergensi, dan analisis kompleksitas komputasi untuk lima algoritma inti perhotelan Fase 91.

---

## 1. Algoritma H1: Overbooking Limit Optimizer

### 1.1 Formulasi Masalah & Teorema Nilai Ekspektasi Bersih
Diberikan kapasitas fisik kamar C dalam bilangan asli, tarif sewa kamar harian R > 0, dan biaya penalti relokasi walk W > 0 dengan kondisi W > R. Misalkan S adalah variabel acak diskrit yang menyatakan jumlah tamu yang tidak hadir (no-show) dengan fungsi peluang massa P(S = s) untuk s dalam {0, 1, ..., N}.

Jika hotel menerima pemesanan sebanyak C + k dengan k bilangan cacah sebagai batas kelebihan jual (overbooking limit), maka:
1. Jumlah tamu yang hadir secara fisik adalah H(k) = C + k - S.
2. Jumlah kamar yang berhasil dihuni dan menghasilkan pendapatan adalah:
   O(k) = min(C, C + k - S) = C - max(0, S - k)
3. Jumlah tamu yang terpaksa dipindahkan ke hotel lain (walked guests) adalah:
   V(k) = max(0, H(k) - C) = max(0, k - S)

Fungsi ekspektasi nilai bersih pendapatan hotel Pi(k) dinyatakan sebagai:
   Pi(k) = R * E[O(k)] - W * E[V(k)]

### 1.2 Pembuktian Monotonisitas & Syarat Optimalitas Marjinal
Perubahan marjinal ekspektasi pendapatan terhadap penambahan 1 unit k adalah:
   Delta Pi(k) = Pi(k + 1) - Pi(k) = R * P(S >= k + 1) - W * P(S <= k)

Karena fungsi distribusi kumulatif F(k) = P(S <= k) merupakan fungsi tidak turun monoton, maka Delta Pi(k) adalah fungsi turun monoton terhadap k. Oleh karena itu, fungsi objektif Pi(k) bersifat konkaf diskrit (strictly concave), sehingga nilai optimal k* terdefinisi secara unik pada titik di mana:
   F(k*) >= R / (R + W) > F(k* - 1)

### 1.3 Verifikasi Vektor Uji U-14
- Parameter: C = 100, R = 1.000.000, W = 2.500.000.
- Distribusi probabilitas no-show s dalam {0, 1, 2, 3, 4, 5, 6} berturut-turut dalam basis poin:
  [500, 1000, 2000, 2500, 2000, 1500, 500] bps
- Evaluasi nilai ekspektasi Pi(k):
  - Pi(0) = 96.900.000 IDR
  - Pi(1) = 97.725.000 IDR
  - Pi(2) = 98.200.000 IDR (Optimal Maksimal)
  - Pi(3) = 97.975.000 IDR
  - Pi(4) = 96.875.000 IDR
  - Pi(5) = 95.075.000 IDR
  - Pi(6) = 92.750.000 IDR
- Kesimpulan: Batas optimal terpilih adalah k* = 2 dengan nilai bersih Rp 98.200.000. Batas kebijakan risiko hotel (maksimal 3% atau 3 kamar) terpenuhi secara sah karena 2 <= 3.

---

## 2. Algoritma H2: Best Available Rate (BAR) Calculator

### 2.1 Formulasi Multiplier Terpadu
Tarif dasar kamar R0 disesuaikan oleh kombinasi faktor elastisitas okupansi mu_occ, faktor lead time mu_lead, dan faktor lonjakan kegiatan kota mu_event:
   R_raw = R0 * (1 + mu_occ) * (1 + mu_lead) * (1 + mu_event)

### 2.2 Aturan Pembulatan & Penjepitan Batas Aman (Guardrail Clamping)
1. Pembulatan bertahap dilakukan ke bawah (floor) menuju kelipatan Rp 1.000:
   R_rounded = floor(R_raw / 1000) * 1000
2. Penjepitan batas aman:
   R_clamped = max(R_floor, min(R_ceiling, R_rounded))
3. Kekebalan Kontrak Korporat (Immunity Invariant):
   Jika R_contract ada, maka R_final = R_contract tanpa pengecualian.

---

## 3. Algoritma H3: Housekeeping Route Sequencer

### 3.1 Teorema Pembagian Beban Adil (Fair Load Balancing via Greedy LPT)
Pengalokasian tugas kamar dengan kredit bobot kerja w_i kepada M petugas dengan kendala kapasitas beban maksimal K_max menjamin bahwa deviasi beban kerja antar petugas terdistribusi secara seimbang:
   min max sum(w_i)
Urutan prioritas leksikografis:
1. Status Prioritas / VIP (P_i = 1) diurutkan sebelum kamar reguler (P_i = 0).
2. Kredit pembersihan descending (w_i menurun).
3. Nomor kamar ascending (RoomNum_i menaik) untuk determinisme penuh.

---

## 4. Algoritma H4: HVAC Setback Controller

### 4.1 Persamaan Termodinamika & Konservasi Energi
Daya konsumsi pendingin udara P_AC (Watt) dikurangi dengan faktor penghematan delta_setback = 3.000 bps = 0,30.
Penghematan energi listrik total E_saved dalam watt-hour (Wh) untuk durasi T menit adalah:
   E_saved = (T / 60) * P_AC * delta_setback

Untuk T = 165 menit, P_AC = 1.200 W, dan delta = 0,30:
   E_saved = (165 / 60) * 1200 * 0,30 = 2,75 * 360 = 990 Wh
Tervalidasi identik dengan vektor prompt.

---

## 5. Algoritma H5: Service Recovery Compensator

### 5.1 Teorema Fungsi Tangga Kompensasi Berjenjang
Untuk durasi gangguan layanan t jam melebihi batas toleransi SLA:
- Bila 0 < t <= 2: Kompensasi = 0,10 * R_daily
- Bila 2 < t <= 6: Kompensasi = 0,25 * R_daily
- Bila t > 6: Kompensasi = 0,50 * R_daily + FreeNightVoucher
Dibatasi maksimal 1 tarif malam kamar (1,0 * R_daily).
