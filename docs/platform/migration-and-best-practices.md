# Panduan Migrasi dan Praktik Terbaik Platform & DX

## 1. Panduan Migrasi dari Layanan Integration Lama

Dengan diperkenalkannya modul `Platform` (`plt_`) pada Fase 237, dua layanan lama pada modul `Integration` telah ditandai sebagai `@deprecated`:
1. `Modules\Integration\Application\Services\DeveloperExperienceQualityService`
2. `Modules\Integration\Application\Services\ReleaseTrainDeploymentSafetyService`

### Pemetaan Komponen Baru

Pengembang diimbau untuk beralih ke komponen modul Platform berikut:
- **Sandbox Environment Lifecycle**: Gunakan `Modules\Platform\Domain\Actions\CreateSandboxAction`, `ProvisionSandboxAction`, dan `DestroySandboxAction`.
- **Privasi Data & Masking PII**: Gunakan `Modules\Platform\Domain\Actions\MaskPiiAction` dan `Modules\Platform\Domain\Services\PiiVaultAnonymizer`.
- **Evaluasi SLO & Error Budget**: Gunakan `Modules\Platform\Domain\Actions\CalculateErrorBudgetAction` dan `Modules\Platform\Domain\Services\SreAlertRouter`.
- **Tracing & Korelasi ID**: Gunakan `Modules\Platform\Domain\Actions\TraceCorrelationChainAction` bersama `CorrelationContextHolder`.
- **Metrik Kualitas Basis Kode**: Gunakan artisan command `php artisan quality:phase-metrics` yang didukung oleh suite algoritma bersertifikat di namespace `App\Quality\Algorithms\*`.

---

## 2. Praktik Terbaik Penulisan Kode Domain

1. **Anti-Padding Rule**:
   - Dilarang membuat DTO anemik tanpa metode atau logika domain hanya demi menambah baris kode.
   - Setiap struktur data transfer harus memiliki validasi internal, representasi string/array terstruktur, atau immutability contract.
2. **Kerapatan Keputusan Terukur**:
   - Hindari metode raksasa dengan puluhan percabangan `if`/`switch`. Gunakan pola strategy, state machine, atau early-return pattern.
3. **Deterministik Idempotency Key**:
   - Selalu buat kunci idempotensi berbasis konteks bisnis (contoh: `{prefix}:{aksi}:{entitas_id}:{epoch}`), jangan menggunakan UUID acak pada titik assign idempotensi yang dipantau oleh `ArchScanBaselineTest`.
4. **Isolasi Database Prefix**:
   - Seluruh tabel domain platform wajib menggunakan prefiks `plt_`. Jangan mencampuradukkan tabel platform dengan tabel modul core atau domain bisnis lain tanpa relasi foreign key yang jelas.
5. **Penanganan PII Ketat**:
   - Kolom yang memuat identitas personal (nama lengkap, NIK, alamat rumah, nomor kartu kredit) harus dilindungi oleh aturan masking pada `plt_mask_rules` dan diverifikasi dengan `php artisan platform:mask-verify`.
