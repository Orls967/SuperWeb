# Runbook Operasional Modul Platform (plt_)

## 1. Penyediaan Sandbox Pengembang (Provisioning)

### Pembuatan Melalui CLI
Untuk membuat sandbox baru melalui command-line interface:
```bash
php artisan platform:synth-data --name="sandbox-feature-x" --owner=1
```

### Pembersihan Sandbox Kedaluwarsa (Garbage Collection)
Pembersihan sandbox yang masa aktifnya telah habis dapat dijalankan secara terjadwal setiap 15 menit menggunakan scheduler atau dipicu secara manual:
```bash
php artisan platform:sandbox-expire --force
```

Tindakan yang dilakukan oleh perintah ini:
1. Memindai tabel `plt_sandboxes` untuk record dengan `status` in (`ready`, `expiring`) dan `expires_at < now()`.
2. Mentransisikan status menjadi `expired` melalui `SandboxEnvironmentStateMachine`.
3. Menjalankan isolasi pembersihan sumber daya (teardown database schema atau cache namespace).
4. Mentransisikan status akhir menjadi `destroyed`.

---

## 2. Manajemen Service Level Objective (SLO) & Error Budget

### Pengecekan Error Budget Konsumsi
Untuk memeriksa konsumsi error budget seluruh layanan platform:
```bash
php artisan platform:error-budget --evaluate
```

### Prosedur Penanganan Pelanggaran Burn Rate (Burn Rate Incident)
Ketika jendela observasi 1 jam melampaui $14.4 \times$ normal (artinya 2% error budget habis dalam 1 jam):
1. **Identifikasi Layanan**: Periksa kolom `service_name` pada tabel `plt_service_slos` yang mengalami `burn_rate > threshold`.
2. **Korelasikan Log**: Gunakan korelasi ID (`x-correlation-id`) yang tercatat pada HTTP headers atau log untuk melacak rantai pemanggilan.
3. **Pembekuan Deploy (Freeze)**: Jika error budget habis ($balance \le 0$), sistem secara otomatis memicu mode *deployment hold*.
4. **Catat Postmortem**: Dokumentasikan insiden pada tabel `plt_postmortems` menggunakan form request atau perintah:
   ```bash
   php artisan platform:error-budget --record-incident --service="api-gateway" --ttr=45 --root-cause="Database connection pool exhausted"
   ```

---

## 3. Verifikasi Privasi Data dan Masking PII

### Menjalankan Masking Dataset
Sebelum mengekspor data pengujian atau snapshot database staging:
```bash
php artisan platform:anonymize --table=users --dataset-id=1
```

### Audit Validitas Masking (Zero-Leakage Test)
Untuk memastikan tidak ada data pribadi mentah yang lolos:
```bash
php artisan platform:mask-verify --dataset-id=1
```
Jika ditemukan satu record saja yang melanggar aturan regex identitas (misal format email asli non-anonymized atau NIK 16-digit valid), perintah akan keluar dengan exit code 1 dan mencatat pelanggaran ke `plt_audit_runs`.

---

## 4. Evaluasi Kualitas Basis Kode & Gate PR

### Menghasilkan Snapshot Metrik Fase
```bash
php artisan quality:phase-metrics --path=modules/Platform --check
```

### Memeriksa Kesenjangan Cakupan Pengujian (Coverage Gap)
```bash
php artisan quality:coverage-gap --threshold=80
```

### Verifikasi Dokumentasi dan Diagram
```bash
php artisan docs:check
php artisan docs:generate --format=svg
```
Mermaid diagram dalam berkas markdown akan dikonversi menjadi berkas SVG native di folder `docs/generated/svg/`.
