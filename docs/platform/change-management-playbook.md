# Change Management Playbook — Platform Release Engineering

Dokumen ini merupakan panduan operasional standar (SOP) untuk manajemen perubahan sistem di lingkungan produksi, staging, dan pengujian pada ekosistem platform.

---

## 1. Filosofi dan Prinsip Dasar

Platform mengadopsi prinsip ITIL v4 yang dimodernisasi untuk lingkungan Continuous Delivery:
1. **Traceability**: Setiap baris kode produksi harus dapat dilacak ke sebuah Change Request (CR) terdaftar.
2. **Automated Risk Scoring**: Klasifikasi risiko dihitung secara deterministik melalui algoritma A4 (`ChangeRiskScorer`) berdasarkan cakupan perubahan, dampak skema, ketersediaan rollback plan, dan subsystem blast radius.
3. **No Unilateral Critical Changes**: Perubahan berkategori Critical wajib melalui persetujuan kuorum Change Advisory Board (CAB).
4. **Instant Reversibility**: Setiap perubahan wajib menyertakan Change Rollback Plan yang telah diverifikasi sebelum jadwal eksekusi disetujui.

---

## 2. Siklus Hidup Change Request (9 Status)

Alur status dikelola secara ketat oleh `ChangeRequestStateMachine`. Transisi status ilegal akan memicu `DomainException`.

```
[ draft ]
   │
   ▼
[ submitted ] ──► [ under_review ] ──► [ cab_review ] (Jika High / Critical)
                          │                     │
                          ▼                     ▼
                  [ approved ] ◄────────────────┘
                          │
                          ▼
                  [ scheduled ]
                          │
                          ▼
                  [ deploying ]
                          │
                          ▼
              [ canary_evaluating ]
                  │             │
        (Sehat)   │             │ (Spike / Error)
                  ▼             ▼
             [ completed ]  [ rolled_back ]
```

### Definisi Status:
- `draft`: Perubahan sedang disusun oleh pengusul. Sertifikat risiko belum dikunci.
- `submitted`: Perubahan diajukan untuk telaah teknis awal.
- `under_review`: Peer review teknis sedang berlangsung.
- `cab_review`: Peninjauan dewan CAB untuk perubahan berkategori High dan Critical.
- `approved`: Semua persetujuan administratif dan teknis telah terpenuhi.
- `scheduled`: Perubahan dialokasikan ke jendela rilis atau Release Train tertentu.
- `deploying`: Artefak sedang diterapkan ke lingkungan target.
- `canary_evaluating`: Evaluasi metrik kesehatan canary sedang berjalan aktif.
- `completed`: Penerapan berhasil 100% dan terverifikasi stabil.
- `rolled_back`: Terjadi anomali sehingga prosedur rollback otomatis atau manual dieksekusi.
- `cancelled`: Pengusul atau manajer rilis membatalkan perubahan sebelum penerapan.

---

## 3. Matriks Penilaian Risiko (Algoritma A4)

Algoritma `ChangeRiskScorer` mengagregasi bobot berikut:

| Parameter | Bobot Rendah | Bobot Sedang | Bobot Tinggi | Bobot Kritis |
|---|---|---|---|---|
| Perubahan Skema DDL | Tanpa DDL (0) | Tambah Kolom Baru (15) | Modifikasi Tipe / Indeks (35) | Hapus Kolom / Drop Table (50) |
| Cakupan Subsystem | Modul Terisolasi (5) | Modul Inti / Shared (20) | Multi-Service Sinkron (35) | Gateway & Database Auth (50) |
| Ketersediaan Rollback Plan | Otomatis 1-Klik (0) | Skrip Manual Teruji (10) | Prosedur Manual Parsial (30) | Tidak Ada Rollback (50) |
| Durasi Jendela Pemeliharaan | < 15 Menit (5) | 15 - 60 Menit (15) | 1 - 4 Jam (30) | > 4 Jam (45) |

### Penentuan Tier Risiko:
- **Low (0 - 25)**: Peer review tunggal sudah mencukupi; disetujui secara otomatis bila seluruh CI checks lulus.
- **Medium (26 - 50)**: Memerlukan persetujuan dari Tech Lead modul terkait.
- **High (51 - 75)**: Memerlukan persetujuan CAB dan jendela penerapan luar jam sibuk (off-peak).
- **Critical (76 - 100)**: Memerlukan kuorum penuh CAB (minimal 3 anggota) dan pengesahan VP of Engineering.

---

## 4. Change Advisory Board (CAB) & Quorum Policy

Prosedur sidang CAB:
1. Rapat CAB diselenggarakan secara terjadwal atau asinkron melalui sistem platform.
2. Setiap pengajuan dinilai berdasarkan:
   - Dampak terhadap ketersediaan layanan (SLA target 99.9%).
   - Kesiapan pengujian regresi dan acceptance tests.
   - Hasil validasi linter skema (`platform:migration-lint`).
3. Kuorum CAB dianggap sah apabila dihadiri oleh perwakilan dari:
   - Platform & Infrastructure Engineering.
   - Quality Assurance / Release Management.
   - Security & Compliance Team.

---

## 5. Prosedur Perubahan Darurat (Emergency Changes)

Ketika terjadi insiden produksi dengan tingkat keparahan P1 / SEV-1:
1. Pemilik insiden dapat membuat Change Request dengan tipe `emergency`.
2. Prosedur persetujuan CAB normal dapat dilewati menggunakan `EmergencyApprovalBypassGuard` dengan syarat:
   - Tautan insiden aktif dicantumkan dalam metadata.
   - Disetujui minimal oleh 1 Incident Commander atau On-Call Principal Engineer.
3. Setelah insiden selesai:
   - Wajib dilakukan audit retrospektif melalui `EmergencyChangeRetrospectiveTracker` dalam kurun waktu 24 jam.
   - Pembaruan post-mortem dicatat ke audit ledger `plt_change_transitions`.

---

## 6. Prosedur Audit dan Kepatuhan

Setiap transisi status mencatat jejak audit kriptografis di `plt_change_transitions`:
- `operator_id`: Pengguna atau sistem yang memicu transisi.
- `from_status` dan `to_status`.
- `reason`: Alasan transisi bisnis atau log keputusan.
- `audit_hash`: Hash SHA-256 yang mengikat urutan riwayat perubahan.

Audit dapat diverifikasi secara berkala menggunakan perintah:
```bash
php artisan platform:change-check --window-days=30
```
Jika ditemukan ketidaksesuaian atau pemalsuan transisi, audit reporter akan memunculkan peringatan fatal dan menghentikan pipeline rilis otomatis.
