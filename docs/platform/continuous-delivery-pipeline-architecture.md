# Arsitektur Pipeline Pengiriman Berkelanjutan (Continuous Delivery)

Dokumen ini mendokumentasikan topologi pipeline pengiriman berkelanjutan, gerbang promosi otomatis (automated stage gates), dan verifikasi integritas artefak pada modul Platform.

---

## 1. Topologi Lingkungan Pengiriman (Environment Topology)

Modul Platform mengorganisasi jalur promosi artefak melalui empat lingkungan berjenjang:

```
[ Lingkungan Lokal / CI Build ]
              │
              ▼
   [ Lingkungan Sandbox ] ──► Pengujian Eksploratif & Verifikasi API Spesifikasi
              │
              ▼
   [ Lingkungan Staging ] ──► Pengujian Regresi Lengkap & Uji Beban Sintetis
              │
              ▼
  [ Lingkungan Pre-Prod ] ──► Validasi Kloning Data Sanitasi & Linter Migrasi
              │
              ▼
  [ Lingkungan Produksi ] ──► Penerapan Bertahap (Canary) & Pemantauan SLO
```

Setiap lingkungan memiliki profil konfigurasi dan pembatasan isolasi jaringan yang terpisah untuk mencegah kebocoran data antar lingkungan.

---

## 2. Gerbang Promosi Otomatis (Stage Gate Evaluation)

Promosi artefak dari satu lingkungan ke lingkungan berikutnya dikendalikan oleh komponen `StageGateEvaluator`:

| Gerbang Promosi | Kriteria Wajib Kelulusan | Komponen Verifikator |
|---|---|---|
| **Sandbox -> Staging** | Seluruh test suite lolos (100%), linting skema bersih | `MigrationSafetyLinter` & PHPUnit |
| **Staging -> Pre-Prod** | Uji integrasi end-to-end sukses, tidak ada kebocoran memori | `DeploymentStageHealthProbe` |
| **Pre-Prod -> Production** | Persetujuan CAB lengkap, sertifikat risiko ditandatangani, rencana rollback siap | `ChangeAuditComplianceVerifier` & `DeploymentRolloutAuditor` |

Jika salah satu kriteria gerbang gagal terpenuhi, pipeline promosi dihentikan seketika dan tim pemilik perubahan menerima notifikasi diagnostik kegagalan.

---

## 3. Verifikasi Integritas Artefak Kriptografis (Artifact Integrity)

Untuk mencegah serangan rantai pasok perangkat lunak (software supply chain attacks):
1. **Penyusunan Digest Hash**: Setiap paket rilis yang dihasilkan oleh build pipeline diikat dengan hash SHA-256 (`artifact_digest`).
2. **Pengecekan di Titik Penerapan**: Sebelum artefak dieksekusi di server target, peladen memvalidasi hash artefak terhadap tanda tangan digital yang tersimpan di manifest Release Train.
3. **Penyimpanan Artefak Imutabel**: Artefak yang telah dirilis ke lingkungan produksi dikunci secara permanen dan tidak dapat diubah (immutable).

---

## 4. Orkestrasi Pipeline Multi-Subsystem

Ketika perubahan melibatkan koordinasi antar subsistem:
- `DeploymentPipelineOrchestrator` memetakan urutan ketergantungan deployment menggunakan topologi topological sort.
- Subsistem database dan migrasi selalu diterapkan dan diverifikasi terlebih dahulu sebelum subsistem backend aplikasi dan antarmuka web diperbarui.
- Jika terjadi kegagalan pada salah satu subsistem di tengah jalan, orchestrator secara berurutan memicu pembalikan berantai terbalik (cascading rollback) untuk memulihkan ke konsistensi awal.
