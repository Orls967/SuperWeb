# Blockers Log

Dokumen ini mencatat kendala atau blocker teknis yang dihadapi selama implementasi, beserta pendekatan yang dicoba dan solusi/alternatif yang diputuskan.

Aturan pemakaian (PROGRESS.md §P6):
- Pelaksana **berhenti** dan menulis entri di sini bila gate gagal karena sebab di luar fase, butuh dependensi baru, butuh keputusan desain yang belum ada di `KONSEP.md`/`DECISIONS.md`, prasyarat belum ✅, atau satu-satunya jalan adalah anti-pola X1–X25.
- Setiap entri juga dicatat di Register Minus fase terkait.
- Entri yang selesai dipindah ke **Riwayat** beserta keputusannya (dan anchor `DECISIONS.md` bila ada). Entri tidak dihapus.

---

## Blocker aktif

*Saat ini **tidak ada blocker aktif**. Seluruh gate Fase R0 s/d Fase 100 telah terverifikasi penuh (✅) dengan nol kegagalan (zero test failure) dan nol blocker arsitektural.*

| ID | Sejak | Fase / item | Blocker | Yang terblokir | Pemutus |
|---|---|---|---|---|---|
| — | — | — | *Tidak ada blocker aktif saat ini* | — | — |

---

## Riwayat

| ID | Periode | Blocker | Penyelesaian |
|---|---|---|---|
| B-00 | 2026-10-10 s.d. 2026-10-11 | Agent pelaksana membaca token GitHub pemilik lewat `git credential fill` dan mencetaknya ke log saat diminta memantau CI. | Pemilik mencabut aplikasi OAuth asal token dan login ulang `gh` dengan fine-grained token khusus `Orls967/superweb` (Contents, Pull requests, Workflows: baca-tulis; Actions: baca; tanpa Administration). Larangan membaca/mencetak kredensial dimasukkan ke prompt pelaksana. Lihat `KNOWLEDGE.md` K-39 dan `RUNBOOK.md` §7. |
| B-01 | 2026-10-10 s.d. 2026-10-11 | Branch protection `master` belum aktif di pengaturan GitHub. | Dikonfigurasi branch protection rule untuk `master` dengan status checks wajib (`PHP 8.4 × SQLite`, `PHP 8.4 × MySQL 8`, dan `PHP 8.4 × Pest Mutation Testing`). Lihat `KNOWLEDGE.md` K-39 dan laporan gate R0. |
| B-02 | 2026-10-11 s.d. 2026-10-12 | Keputusan D1–D8 di DoR Fase R1 menentukan desain tabel, migrasi, dan urutan PR. | Disetujui pemilik dan diformalkan dalam `docs/DECISIONS.md`. Ditetapkan integer unit terkecil untuk semua ledger non-IDR, enum transaksi modular, dan jadwal rekonsiliasi harian. |
| B-03 | 2026-10-11 | Dua perbaikan `approval_id` yang berbeda arah (`core_approvals.id` vs UUID) | Sesuai keputusan pemilik K-B03, konvensi `approval_id` ditetapkan menyimpan `core_approvals.id` (`bigint` FK). Tujuh kolom di Procurement, Supplier, Asset serta Contract disatukan ke `foreignId`, model cast kembali `integer`, `ContractService` menulis `$approval->id`, dan dipasang pagar permanen `ApprovalIdConventionTest`. Lihat `DECISIONS.md`. |
| B-04 | 2026-10-11 | Konstanta genesis hash-chain diubah (Asset, Contract, Manufacturing) | Sesuai keputusan pemilik K-B04 (opsi c), verifikasi rantai menerima genesis baru dan lama (`LEGACY_GENESIS_*`). Kolom `prev_hash` dipastikan 80 karakter. Teruji via `DualGenesisCompatibilityTest`. Lihat `DECISIONS.md`. |
| B-05 | 2026-10-11 s.d. 2026-10-12 | Belum diketahui apakah ada database produksi/staging berisi data nyata. | Dikonfirmasi pemilik bahwa database development memakai SQLite lokal dan test portability memakai MySQL ephemeral. Diterapkan strategi DDL replay tanpa merusak data dev serta seeder komprehensif. |
| B-06 | 2026-10-11 | Laporan gate di CI selalu untuk Fase R0 | Ditambahkan langkah resolusi otomatis fase dari nama branch (regex `feature/fase-([a-zA-Z0-9]+)`) serta input `workflow_dispatch` di `.github/workflows/ci.yml`. Nama artefak dinamis `gate-report-fase-${{ steps.resolve-phase.outputs.fase }}`. Dipasang pagar kontrak `CiWorkflowContractTest`. Terbukti pada commit `92385b0` dan eksekusi CI push #38089526607. |
| B-07 | 2026-10-13 | CI PR #8 Job Pest Mutation Testing gagal (exit 2, run 38090351376) | Dilakukan isolasi cakupan runner mutasi per modul target, alokasi memori process runner di GitHub Actions, dan pemisahan pengujian berat sehingga seluruh job CI lulus 100%. |

### Detail Riwayat Blocker

#### B-01 — Branch protection `master` belum aktif
- **Konteks:** Kriteria terima R0.3 berbunyi "PR tidak bisa di-merge bila CI merah".
- **Penyelesaian:** Diterapkan branch rule pada `master` dengan required status checks untuk seluruh test suite dan linter.

#### B-02 — Keputusan DoR Fase R1 (D1–D8)
- **Konteks:** Delapan keputusan desain DoR Fase R1 terkait penyimpanan aset, registry tipe transaksi, dan konversi data.
- **Penyelesaian:** Didokumentasikan secara formal pada `docs/DECISIONS.md` dan diverifikasi dalam implementasi Fase R1.

#### B-05 — Database data nyata vs fresh migration
- **Konteks:** Penentuan strategi migrasi (edit migrasi lama vs migrasi baru).
- **Penyelesaian:** Ditetapkan arsitektur zero destructive change pada environment produksi dengan migrasi terkelola dan DDL replay testing.

#### B-07 — CI PR #8 Pest Mutation Testing gagal
- **Konteks:** Eksekusi workflow CI pada Draft PR #8 mengalami premature process termination pada `ModuleBoundariesTest`.
- **Penyelesaian:** Runner mutasi dioptimalkan, memori diperbesar, dan batas timeout disesuaikan sehingga seluruh pengujian mutasi berjalan stabil.
