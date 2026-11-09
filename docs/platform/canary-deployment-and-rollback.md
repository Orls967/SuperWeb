# Panduan Canary Deployment dan Prosedur Auto-Rollback

Dokumen ini menjelaskan mekanisme penerjunan bertahap (canary rollout), evaluasi metrik kesalahan menggunakan Algoritma A6, dan orkestrasi pembalikan otomatis (auto-rollback) pada modul Platform.

---

## 1. Latar Belakang dan Tujuan Canary Deployment

Canary Deployment adalah strategi penerapan perangkat lunak di mana versi aplikasi baru diekspos secara bertahap kepada sebagian kecil lalu lintas pengguna nyata sebelum diterapkan ke seluruh armada peladen:
- **Reduksi Blast Radius**: Jika terjadi bug regresi atau kebocoran memori, dampaknya hanya dirasakan oleh sebagian kecil pengguna.
- **Deteksi Cepat**: Anomali performa dan lonjakan tingkat kesalahan dapat dideteksi dalam hitungan menit pertama.
- **Pembalikan Instan Tanpa Intervensi Manusia**: Sistem secara otonom membatalkan penerapan dan mengembalikan lalu lintas ke versi stabil saat metrik melampaui ambang batas toleransi.

---

## 2. Profil Pembagian Lalu Lintas (Traffic Split Progression)

Pengalihan lalu lintas diatur oleh `CanaryTrafficSplitController` dan `CanaryTrafficShapingPlanner` mengikuti kurva eksponensial:

| Langkah | Persentase Lalu Lintas | Durasi Soak Minimum | Metrik Pengamatan Utama |
|---|---|---|---|
| Tahap 1 | 1% - 5% | 15 Menit | Deteksi fatal crash, uncaught exceptions, dan status HTTP 500 |
| Tahap 2 | 20% - 25% | 30 Menit | Analisis distribusi latensi p95 & p99, penggunaan CPU & memori |
| Tahap 3 | 50% | 30 Menit | Pemantauan tingkat kesalahan transaksi bisnis dan antrean antrian |
| Tahap 4 | 100% | 60 Menit | Verifikasi final stabilitas armada penuh sebelum penutupan tiket |

Setiap tahap memerlukan konfirmasi kelulusan gerbang otomatis sebelum melangkah ke tahap berikutnya.

---

## 3. Algoritma Evaluasi Kesehatan Canary (Algoritma A6)

Komponen `CanaryErrorEvaluator` mengevaluasi sampel telemetri berkala dengan membandingkan baseline stabil terhadap kelompok uji (canary cohort):

### Metrik Kunci yang Dievaluasi:
1. **Delta Tingkat Kesalahan (Error Rate Delta)**:
   Perbedaan persentase kesalahan antara kelompok canary dan kelompok kontrol.
   ```
   delta = (canary_errors / canary_total) - (baseline_errors / baseline_total)
   ```
2. **Ambang Batas Pelanggaran (Breach Threshold)**:
   - Jika delta tingkat kesalahan $\ge 0.05$ (5%), evaluasi langsung memberikan rekomendasi `ROLLBACK`.
   - Jika delta tingkat kesalahan $\le 0.01$ (1%), evaluasi memberikan rekomendasi `PROMOTE`.
   - Di antara rentang tersebut, sistem memberikan rekomendasi `HOLD` untuk memperpanjang durasi observasi.

### Uji Signifikansi Statistik (Wilson Score & Normal Z-Test):
Untuk menghindari keputusan keliru akibat fluktuasi statistik pada sampel kecil:
- `WilsonScoreConfidenceCalculator`: Menghitung interval kepercayaan 95% untuk proporsi kesalahan.
- `CanaryStatisticalHypothesisRunner`: Melakukan pengujian hipotesis dua proporsi (Z-test) dengan tingkat signifikansi $\alpha = 0.05$.

---

## 4. Orkestrasi Auto-Rollback Otonom

Jika algoritma A6 menetapkan rekomendasi `ROLLBACK`, alur pembalikan dipicu secara otomatis oleh `CanaryRollbackOrchestrator`:

```
   [ Deteksi Lonjakan Error ]
              │
              ▼
   [ Rekomendasi A6: ROLLBACK ]
              │
              ▼
   [ CanaryRollbackOrchestrator::executeRollback ]
              │
              ├─► 1. Alihkan Lalu Lintas: Reset Traffic Split ke 0% (Kembali ke Stable)
              │
              ├─► 2. Perbarui Database: Set Status Deployment = 'rolled_back'
              │
              ├─► 3. Transisi Status CR: Set Status ChangeRequest = 'rolled_back'
              │
              ├─► 4. Audit & Notifikasi: Catat ke Audit Ledger dan Siarkan Alert
              │
              ▼
   [ Sistem Kembali Stabil ]
```

### Karakteristik Eksekusi Rollback:
- **Idempotensi Penuh**: Eksekusi pembalikan dapat dipanggil berulang kali tanpa merusak integritas data.
- **Zero Human Latency**: Waktu dari deteksi breach hingga pemulihan lalu lintas diselesaikan dalam waktu kurang dari 5 detik.
- **Sertifikasi Kepatuhan**: Hasil pembalikan disegel dalam `RollbackExecutionResult` lengkap dengan timestamp presisi mikrodetik.

---

## 5. Prosedur Investigasi Pasca-Rollback (Post-Mortem)

Setelah insiden rollback otomatis terjadi:
1. `DeploymentFailureForensicsEngine` mengumpulkan rekaman telemetri dan log kesalahan terbaru.
2. Insinyur yang bertugas memeriksa ringkasan forensik melalui antarmuka web `/platform/change-management/{id}` atau perintah CLI:
   ```bash
   php artisan platform:canary-evaluate --deployment-code="DEP-12345"
   ```
3. Tiket perbaikan (hotfix) dibuat dan diuji kembali sebelum pengajuan siklus rilis berikutnya.

