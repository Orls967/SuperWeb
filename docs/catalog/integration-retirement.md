# Katalog Pensiun Modul Integration (Integration Retirement Log)

Dokumen ini mencatat artefak dan layanan pada modul `Integration` yang telah digantikan oleh implementasi modular resmi pilar bisnis, sesuai dengan rencana migrasi §12.3.1 dan mandat IZIN-3 (W8-P1).

Kelas-kelas di bawah ini telah ditandai dengan anotasi PHPDoc `@deprecated` dan dipertahankan tanpa perubahan fungsional hingga jadwal pembersihan resmi yang disetujui pemilik (W13-P8 dan W17-P7).

| Kelas Lama (Modul Integration) | Pengganti Resmi | Fase Pengganti | Tanggal Deprecate | Status |
|---|---|---|---|---|
| `Modules\Integration\Application\Services\DeveloperExperienceQualityService` | `Modules\Platform` & `app/Quality` | Fase 237 (W8-P1.1) | 2026-11-09 | @deprecated |
| `Modules\Integration\Application\Services\ReleaseTrainDeploymentSafetyService` | `Modules\Platform` & `app/Quality` | Fase 238 (W8-P1.2) | 2026-11-09 | @deprecated |

---

### Catatan Kepatuhan:
- Dilarang memperluas, menyalin, atau menghapus kelas di atas sebelum izin tertulis pemilik (W13-P8 dan W17-P7).
- Seluruh kode baru di Gelombang 8 dan seterusnya wajib menggunakan `Modules\Platform` dan namespace `App\Quality`.

