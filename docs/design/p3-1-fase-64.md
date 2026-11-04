# Technical Design — Fase 64: AI Deterministik & Anti-Fraud (P3.1)

## 1. Skema Database & Model

### 1.1 Tabel `core_ai_models`
Mencatat registry model AI yang disahkan:
- `id` (bigint unsigned auto-increment, primary key)
- `code` (string 64, unique bersama version)
- `version` (string 32)
- `canonical_parameters` (json)
- `parameter_hash` (string 64: sha256 dari canonical JSON)
- `created_at`, `updated_at` (timestamps)

### 1.2 Tabel `core_ai_inferences` (Append-Only)
Mencatat jejak inferensi deterministik yang tidak boleh diubah/dihapus (PR5):
- `id` (bigint unsigned auto-increment, primary key)
- `model_code` (string 64, index)
- `model_version` (string 32)
- `input_snapshot` (json: key terurut secara leksikografis)
- `input_hash` (string 64: sha256 dari JSON input kanonik)
- `seed` (bigint)
- `output` (json: key terurut)
- `output_hash` (string 64: sha256 dari JSON output kanonik)
- `created_at` (timestamp)

### 1.3 Tabel `core_ai_anomaly_quarantines` & `core_ai_stock_recommendations`
Tabel operasional Core AI yang mencatat transaksi terkarantina dan rekomendasi stok dengan relasi ke jejak snapshot inferensi.

## 2. Spesifikasi Algoritma

### 2.1 `ModifiedZScore` (Stats\ModifiedZScore)
- Formula:
  $$z = \frac{0,6745 \times (x - \text{median})}{\text{MAD}}$$
  Konstanta $\frac{6745}{10000}$ disimpan sebagai `BigRational`.
  $\text{median}$ dan $\text{MAD}$ dihitung melalui `Modules\Shared\Domain\Algorithms\Stats\RobustStatistics`.
- Jika $\text{MAD} = 0$:
  $$z = \frac{x - \text{median}}{1,253314 \times \text{MeanAD}}$$
  dengan faktor $\frac{1253314}{1000000}$. Jika $\text{MeanAD} = 0$, maka setiap $x \ne \text{median}$ adalah anomali ($|z| > 3,5$).
- Anomali: $|z| > 3,5$ ($35/10$).
- Sertifikat: `ModifiedZScoreCertificateVerifier` memvalidasi median, MAD/MeanAD, z-score, dan sertifikat kelayakan.

### 2.2 `SimpleExponentialSmoothing` (Forecast\SimpleExponentialSmoothing)
- Formula level:
  $$s_0 = y_0$$
  $$s_t = \alpha \times y_t + (1 - \alpha) \times s_{t-1}$$
  dengan $\alpha = 3.000$ bps ($3000/10000 = 0,3$).
- Faktor musim/libur (misal $12.500$ bps $= 1,25$):
  $$\text{Adjusted} = s_T \times \frac{\text{factor\_bps}}{10000}$$
  Dibulatkan ke atas (`ceil`) menjadi unit/hari.

## 3. Alur Bisnis & Guardrail

### 3.1 Pencegahan Settlement Transaksi Terkarantina
- Layanan transaksi/settlement memanggil `SettlementGuardService::assertAllowed($transactionRef)`.
- Jika transaksi tercatat di `core_ai_anomaly_quarantines` dengan status `QUARANTINED`, operasi ditolak dengan `TransactionQuarantinedException`.
- Rilis karantina membutuhkan otorisasi four-eyes: `released_by` $\ne$ inisiator/pembuat transaksi dan harus memiliki peran `risk_manager`.
- Operasi rilis bersifat idempoten.

### 3.2 Persetujuan Rekomendasi Stok
- Rekomendasi stok dengan estimasi biaya $> \text{Rp } 10.000.000$ mewajibkan persetujuan manusia.
- Eksekusi pembentukan PO dilakukan melalui aksi `ApproveStockRecommendationAction` yang hanya berwenang dijalankan oleh role `planner_manager`.

## 4. Audit Dua Sumber (`ai:audit`)
1. Mengambil sampel inferensi dari `core_ai_inferences`.
2. Mengeksekusi ulang komputasi dengan `seed` dan `input_snapshot` yang sama.
3. Memverifikasi bahwa output hasil replay identik byte-per-byte dengan yang tersimpan.
4. Memvalidasi batasan integritas karantina dan approval stok.
5. Menolak fixture manipulasi output/hash dengan exit code $\ne 0$ dan menyebut ID inferensi terkait.

