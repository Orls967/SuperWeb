# Algoritma Rekonsiliasi Neraca Massa Metalurgi Bersertifikat (M5: MassBalanceReconciler)

## 1. Latar Belakang Metalurgi & Masalah Deviasi Neraca Massa
Pada sirkuit pengolahan dan pemurnian mineral (seperti pabrik benefisiasi batubara atau fasilitas reduksi pirometalurgi tanur listrik RKEF nikel), pengukuran fisik massa dan kadar laboratorium tidak pernah sempurna secara analitis. Ketidakpastian muncul dari:
- Fluktuasi kalibrasi *weightometer* ban berjalan (*belt scale drift*).
- Variasi kelembapan (*free moisture variations*).
- Bias pengambilan sampel (*fundamental sampling error* sesuai teori Pierre Gy).
- Deviasi analitika instrumen XRF / ICP-OES di laboratorium uji.

Akibatnya, neraca massa mentah:
$$\text{Massa Feed} \neq \text{Massa Konsentrat} + \text{Massa Tailing}$$
$$\text{Massa Logam Masuk} \neq \text{Massa Logam Terdistribusi}$$

Bila selisih (*unaccounted discrepancy*) ini dibiarkan tanpa rekonsiliasi yang benar secara statistik, perusahaan akan menghadapi kesalahan akuntansi persediaan, kehilangan logam yang tidak terdeteksi (*unaccounted metal loss*), dan perselisihan royalti dengan otoritas pertambangan.

`MassBalanceReconciler` mengimplementasikan teknik rekonsiliasi data kuadrat terkecil berbobot varians (*Weighted Least Squares / WLS Data Reconciliation*) untuk menghasilkan perkiraan massa dan kadar yang konsisten secara stoikiometri dan hukum kekekalan massa (*Law of Conservation of Mass*).

---

## 2. Formulasi Matematis Rekonsiliasi Kuadrat Terkecil (WLS)

### 2.1 Definisi Variabel
Misalkan dalam satu siklus pengolahan terdapat $N$ aliran material ($i = 1, \dots, N$):
- $F$: Aliran umpan masuk pabrik (*Feed stream*).
- $C$: Aliran produk konsentrat / logam (*Concentrate stream*).
- $T$: Aliran ampas sisa pengolahan (*Tailings stream*).

Untuk setiap aliran $i$:
- $M_i^{\text{meas}}$: Pengukuran massa kotor terukur (kg).
- $\hat{M}_i$: Nilai massa hasil rekonsiliasi optimal (kg).
- $c_i^{\text{meas}}$: Kadar analit terukur (ppm atau persentase).
- $\hat{c}_i$: Nilai kadar hasil rekonsiliasi optimal.
- $\sigma_{M,i}^2$: Varians ketidakpastian pengukuran massa aliran $i$.
- $\sigma_{c,i}^2$: Varians ketidakpastian analisis kimia aliran $i$.

### 2.2 Fungsi Objektif Optimasi
Fungsi penalti deviasi kuadrat terbobot varians:

$$J(\hat{\mathbf{M}}, \hat{\mathbf{c}}) = \sum_{i=1}^N \left( \frac{(M_i^{\text{meas}} - \hat{M}_i)^2}{\sigma_{M,i}^2} + \frac{(c_i^{\text{meas}} - \hat{c}_i)^2}{\sigma_{c,i}^2} \right)$$

### 2.3 Persamaan Kendala Kekekalan (Conservation Constraints)
1. **Kendala Kekekalan Massa Total**:
   $$\sum_{i \in \text{Inputs}} \hat{M}_i - \sum_{j \in \text{Outputs}} \hat{M}_j = 0$$
   Untuk sistem 1-masuk 2-keluar:
   $$\hat{M}_F - (\hat{M}_C + \hat{M}_T) = 0$$

2. **Kendala Kekekalan Komponen / Logam Berharga**:
   $$\sum_{i \in \text{Inputs}} (\hat{M}_i \cdot \hat{c}_i) - \sum_{j \in \text{Outputs}} (\hat{M}_j \cdot \hat{c}_j) = 0$$
   $$\hat{M}_F \hat{c}_F - (\hat{M}_C \hat{c}_C + \hat{M}_T \hat{c}_T) = 0$$

### 2.4 Penyelesaian dengan Pengali Lagrange (Lagrange Multipliers)
Bentuk Lagrangian:

$$\mathcal{L}(\hat{\mathbf{M}}, \hat{\mathbf{c}}, \lambda_1, \lambda_2) = J(\hat{\mathbf{M}}, \hat{\mathbf{c}}) + \lambda_1 \Big( \hat{M}_F - \hat{M}_C - \hat{M}_T \Big) + \lambda_2 \Big( \hat{M}_F \hat{c}_F - \hat{M}_C \hat{c}_C - \hat{M}_T \hat{c}_T \Big)$$

Dengan menetapkan turunan parsial terhadap seluruh parameter sama dengan nol:

$$\frac{\partial \mathcal{L}}{\partial \hat{M}_i} = 0, \quad \frac{\partial \mathcal{L}}{\partial \hat{c}_i} = 0, \quad \frac{\partial \mathcal{L}}{\partial \lambda_1} = 0, \quad \frac{\partial \mathcal{L}}{\partial \lambda_2} = 0$$

Sistem persamaan non-linear diselesaikan menggunakan metode aproksimasi Gauss-Newton terkonvergensi ($\epsilon < 10^{-6}$), menghasilkan vektor massa terkoreksi $[\hat{M}_F, \hat{M}_C, \hat{M}_T]$ dan kadar terkoreksi $[\hat{c}_F, \hat{c}_C, \hat{c}_T]$ yang patuh 100% pada hukum kekekalan materi.

---

## 3. Metrik Efisiensi Metalurgi Turunan

Setelah massa dan kadar terekonsiliasi diperoleh, sistem menghitung metrik performa pabrik:

### 3.1 Perolehan Metalurgi (Metallurgical Recovery)
Persentase logam berharga pada umpan yang berhasil ditangkap ke dalam konsentrat:

$$R_{\text{metal}} = \frac{\hat{M}_C \cdot \hat{c}_C}{\hat{M}_F \cdot \hat{c}_F} \times 100\%$$

### 3.2 Rasio Konsentrasi (Enrichment Ratio / Concentration Ratio)
Faktor pelipatan kadar produk terhadap kadar umpan:

$$E = \frac{\hat{c}_C}{\hat{c}_F}$$

$$\text{Rasio Pengayaan} = \frac{\hat{M}_F}{\hat{M}_C}$$

---

## 4. Evaluasi Chi-Kuadrat ($\chi^2$) untuk Deteksi Kerusakan Sensor

Algoritma mengevaluasi apakah penyesuaian yang dilakukan masih berada dalam batas statistik normal atau mengindikasikan kerusakan instrumen fisik (*gross error detection*):

$$\chi_{\text{calc}}^2 = \sum_{i=1}^N \frac{(M_i^{\text{meas}} - \hat{M}_i)^2}{\sigma_{M,i}^2}$$

Dengan derajat bebas $\nu = (\text{Jumlah Aliran Terukur}) - (\text{Jumlah Aliran Tak Bebas})$:
- Bila $\chi_{\text{calc}}^2 \le \chi_{\text{crit}}^2(0.05, \nu)$: Penyesuaian berada dalam batas kesalahan acak wajar (*Random Error Only*). Rekonsiliasi disetujui otomatis.
- Bila $\chi_{\text{calc}}^2 > \chi_{\text{crit}}^2(0.05, \nu)$: Terdeteksi galat sistematis kasar (*Gross Error / Sensor Bias*). Sistem menandai status `ANOMALY_DETECTED` dan menerbitkan rekomendasi kalibrasi timbangan belt scale atau pemeriksaan tabung XRF.

---

## 5. Sertifikat Kriptografis Rekonsiliasi (M5 Certificate)

Setiap proses rekonsiliasi menghasilkan struktur keluaran `MassBalanceReconcilerResult` berstempel waktu dan ber-hash HMAC-SHA256:

```json
{
  "algorithm_id": "MASS_BALANCE_WLS_V1",
  "reconciliation_run_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "reconciled_feed_kg": 1000000,
  "reconciled_concentrate_kg": 182400,
  "reconciled_tailings_kg": 817600,
  "reconciled_feed_grade_pct": 1.82,
  "reconciled_concentrate_grade_pct": 8.95,
  "reconciled_tailings_grade_pct": 0.23,
  "metallurgical_recovery_pct": 89.68,
  "chi_square_statistic": 1.84,
  "is_statistically_sound": true,
  "certificate_hash": "a4f5b2c8d9e1029384756abcdeffedcba1234567890abcdef1234567890abcde"
}
```

---

## 6. Vektor Uji Algoritma (Test Vectors)

Diuji dalam `tests/Unit/Algorithms/MassBalanceReconcilerTest.php`:

| ID Kasus | Komoditas | Feed Ton | Konsentrat Ton | Tailing Ton | Feed % | Conc % | Tail % | $\chi^2$ | Status |
|---|---|---|---|---|---|---|---|---|---|
| `VEC-M5-01` | Nikel RKEF | $1.000$ | $180$ | $820$ | $1.80\%$ | $9.00\%$ | $0.22\%$ | $0.42$ | ACCEPTED |
| `VEC-M5-02` | Nikel HPAL | $5.000$ | $450$ | $4.550$ | $1.35\%$ | $14.20\%$ | $0.08\%$ | $1.15$ | ACCEPTED |
| `VEC-M5-03` | Batubara CWP | $10.000$ | $7.200$ | $2.800$ | $18.5\%\text{ Ash}$ | $7.2\%\text{ Ash}$ | $47.5\%\text{ Ash}$ | $0.88$ | ACCEPTED |
| `VEC-M5-04` | Anomali Sensor | $1.000$ | $100$ | $700$ | $1.80\%$ | $9.00\%$ | $0.20\%$ | $48.50$ | REJECTED (Gross Error) |

