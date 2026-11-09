# Spesifikasi Metrik DORA & Rekayasa Produktivitas Pengiriman

Dokumen ini mendefinisikan secara matematis dan prosedural implementasi empat metrik DORA (DevOps Research and Assessment) pada modul Platform.

---

## 1. Landasan Konseptual DORA

Penelitian DORA selama bertahun-tahun membuktikan bahwa kemampuan pengiriman perangkat lunak dengan frekuensi tinggi dan stabilitas tinggi tidak saling bertentangan, melainkan saling memperkuat. Organisasi berkinerja tinggi (Elite Performers) mampu melakukan deployment puluhan kali per hari sekaligus mempertahankan angka kegagalan perubahan yang sangat rendah.

Platform menyediakan mesin kalkulator bawaan `DoraMetricsCalculator` yang mengagregasi data dari:
- `plt_deployments`: Mencatat setiap aktivitas penerapan ke staging dan produksi.
- `plt_change_requests`: Mencatat riwayat persetujuan, risiko, dan status penutupan.
- `plt_hotfixes`: Mencatat perbaikan di luar jadwal normal yang dipicu oleh insiden.
- `plt_canary_observations`: Mencatat metrik anomali tingkat kesalahan selama fase canary.

---

## 2. Definisi Matematis Empat Metrik Kunci

### 1. Frekuensi Penerapan (Deployment Frequency - DF)
Frekuensi keberhasilan rilis kode ke lingkungan produksi dalam satu jendela waktu analisis ($T$ hari):

$$DF = \frac{N_{\text{prod\_deployments}}}{T}$$

Klasifikasi Kategori:
- **Elite**: $DF \ge 1.0$ (minimal 1 kali per hari atau on-demand berkali-kali).
- **High**: $0.14 \le DF < 1.0$ (sekali per minggu hingga sekali per hari).
- **Medium**: $0.033 \le DF < 0.14$ (sekali per bulan hingga sekali per minggu).
- **Low**: $DF < 0.033$ (kurang dari sekali per bulan).

### 2. Waktu Tunggu Perubahan (Lead Time for Changes - LT)
Durasi dari saat commit kode pertama dibuat (atau tiket CR disetujui) hingga kode tersebut aktif berjalan dan melayani trafik di produksi:

$$LT = t_{\text{deployed}} - t_{\text{created\_or\_committed}}$$

Metrik ini dihitung menggunakan agregasi persentil (p50, p90, dan p95) untuk mengeliminasi skew akibat outlier:
- **Elite**: $LT < 1\text{ jam}$
- **High**: $1\text{ jam} \le LT < 1\text{ minggu}$
- **Medium**: $1\text{ minggu} \le LT < 1\text{ bulan}$
- **Low**: $LT \ge 1\text{ bulan}$

### 3. Tingkat Kegagalan Perubahan (Change Failure Rate - CFR)
Rasio persentase penerapan produksi yang memerlukan tindakan pemulihan (rollback, patch darurat, atau degradasi layanan):

$$CFR = \left( \frac{N_{\text{failed\_or\_rolled\_back}} + N_{\text{hotfixes}}}{N_{\text{total\_deployments}}} \right) \times 100\%$$

Klasifikasi Kategori:
- **Elite**: $0\% \le CFR \le 5\%$
- **High**: $5\% < CFR \le 15\%$
- **Medium**: $15\% < CFR \le 30\%$
- **Low**: $CFR > 30\%$

### 4. Waktu Pemulihan Rata-rata (Mean Time to Restore - MTTR)
Durasi rata-rata yang diperlukan untuk memulihkan layanan produksi setelah terjadi insiden kegagalan deployment:

$$MTTR = \frac{1}{M} \sum_{i=1}^{M} (t_{\text{restored}, i} - t_{\text{incident\_started}, i})$$

Klasifikasi Kategori:
- **Elite**: $MTTR < 1\text{ jam}$
- **High**: $1\text{ jam} \le MTTR < 1\text{ hari}$
- **Medium**: $1\text{ hari} \le MTTR < 1\text{ minggu}$
- **Low**: $MTTR \ge 1\text{ minggu}$

---

## 3. Skema Data Telemetri Metrik DORA

Setiap catatan deployment di `plt_deployments` memuat atribut terstruktur:

| Kolom | Tipe | Deskripsi |
|---|---|---|
| `id` | BIGINT UNSIGNED | Kunci utama inkremental |
| `deployment_code` | VARCHAR(64) | Pengenal unik deployment (format: `DEP-YYYYMMDD-XXXX`) |
| `change_request_id` | BIGINT UNSIGNED | Referensi ke Change Request terkait |
| `release_train_id` | BIGINT UNSIGNED | Referensi ke Release Train (opsional) |
| `environment` | VARCHAR(32) | Lingkungan target (`production`, `staging`, `sandbox`) |
| `status` | VARCHAR(32) | Status deployment (`pending`, `deploying`, `canary_evaluating`, `promoted`, `rolled_back`) |
| `started_at` | TIMESTAMP | Waktu inisiasi penerapan dimulai |
| `completed_at` | TIMESTAMP | Waktu penerapan selesai atau pembalikan tuntas |
| `traffic_percent` | INT | Persentase lalu lintas canary aktif saat ini |
| `metadata` | JSON | Detail telemetri eksekusi, hash commit, catatan pemeriksa |

---

## 4. Perintah CLI Penghasil Laporan Metrik DORA

Laporan performa pengiriman dapat dipanggil kapan saja melalui antarmuka konsol:

```bash
php artisan platform:dora-metrics --window-days=30
```

Contoh keluaran laporan konsol:
```text
+-----------------------+--------------------+---------------+
| Metric                | Value              | Performance   |
+-----------------------+--------------------+---------------+
| Deployment Frequency  | 2.4 deploys/day    | Elite         |
| Lead Time for Changes | 42 minutes (p50)   | Elite         |
| Change Failure Rate   | 2.8%               | Elite         |
| Mean Time to Restore  | 18 minutes         | Elite         |
+-----------------------+--------------------+---------------+
```

---

## 5. Strategi Peningkatan Berkelanjutan Berdasarkan DORA

1. **Jika MTTR Tinggi**: Perbaiki otomatisasi skrip rollback dan tingkatkan cakupan health check sintetis pra-rilis.
2. **Jika CFR Tinggi**: Perketat kuorum CAB untuk perubahan berisiko tinggi dan terapkan linter migrasi skema `platform:migration-lint` di gerbang pra-penggabungan PR.
3. **Jika Lead Time Tinggi**: Kurangi ukuran batch rilis dengan mempercepat siklus Release Train dari dua mingguan menjadi mingguan atau harian.
