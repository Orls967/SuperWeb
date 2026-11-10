# Definition of Ready (DoR) — Fase 67: Simulation Kernel, Twin Bus & Scale Provisioner

- **ID Fase:** 67 (P3.1 Gelombang 8)
- **Modul Pemilik:** `Core` (tabel berprefiks `core_` dan `sim_`)
- **Prasyarat:** R2.5, R4, W8-P1, W8-P2 ✅
- **Acuan Utama:** KONSEP.md §A10, rencana-gelombang-8-25.md §W8-P3
- **Tingkat Simulasi:** S2 (Simulasi Berbantuan Deterministik & Sandbox Transaksional)

---

## 1. Rencana Anggaran Baris (RAB) — Target B1 ≥ 7.500

| Lapisan | Rencana B1 | Deskripsi & Komponen Kunci |
|---|---|---|
| **Algoritma & Value Object** | 1.300 | A1 `DiscreteEventScheduler` + cert + verifier<br>A2 `SubscriberTopologicalOrder` + cert + verifier<br>A3 `StreamSeedAndFingerprint` + cert + verifier<br>VO: `DiscreteEvent`, `StreamSeed`, `DaySummaryFingerprint`, `SimulationCertificate` |
| **State Machine** | 850 | `SimulationRunStatus` enum (8 status: draft, provisioned, running, paused, checkpointed, completed, failed, archived)<br>`SimulationRunStateMachine` (guard transisi, payload validasi)<br>`SimRunTransition` model append-only & riwayat |
| **Aplikasi (Action & Service)** | 2.600 | `SimulationRunnerService`, `SimStreamRegistryService`<br>`RunSimulationAction`, `ResumeSimulationAction`, `PauseSimulationAction`<br>`CheckpointSimulationAction`, `PruneCheckpointsAction`<br>`DiffSimulationRunsAction`, `ScaleTierPlanService`<br>`DigitalTwinSnapshotService`, `ExecuteDayAction` |
| **Integrasi (Kontrak, Adaptor, Listener)** | 800 | Kontrak publik `SimulationRunner`, `SimStreamRegistry`<br>3 Tick Handler domain: `HospitalDischargeTickHandler`, `HotelNightAuditTickHandler`, `LogisticsDemurrageTickHandler`<br>Event dispatch outbox: `core.simulation.run_state_changed.v1`, `core.simulation.checkpoint_written.v1` |
| **Posting Ledger** | 0 | 0 dialihkan ke Aplikasi/Integrasi: Kernel mengeksekusi Action domain yang memposting ke ledger; dalam mode sandbox (D35) seluruh run di-rollback dengan probe saldo Σ debit = Σ kredit. |
| **HTTP (Controller, FormRequest, Policy, View)** | 1.000 | `SimulationRunController`, `SimulationDiffController`, `SimulationTwinController`<br>FormRequest & Policy Anti-IDOR (`SimulationRunPolicy`, `SimTwinStatePolicy`)<br>CSV Exporters (`RunCsvExporter`, `TimelineCsvExporter`, `DiffCsvExporter`, `TwinCsvExporter`) |
| **Command & Laporan CSV** | 1.200 | `RunSimulationCommand` (`sim:run`), `AuditSimulationKernelCommand` (`sim:audit`)<br>`SimDiffCommand` (`sim:diff`), `SimCheckpointPruneCommand` (`sim:checkpoint-prune`)<br>`ScaleProvisionCommand` (`scale:provision`) |
| **Model & Repositori** | 450 | `SimRun` (perluasan fillable & casts)<br>`SimCheckpoint` (model checkpoint terisolasi)<br>`SimRunTransition` (model riwayat status append-only)<br>`SimTwinSnapshot` (model snapshot state twin per run) |
| **TOTAL ESTIMASI B1** | **8.200** | **Margin aman: ~9% di atas ambang minimum 7.500** |

---

## 2. Skema Tabel dan Relasi Basis Data

### A. Perluasan Tabel `sim_runs`
- Penambahan kolom:
  - `mode` (`varchar(16)`, default `'live'`; nilai: `'live'`, `'sandbox'`)
  - `current_day` (`integer`, default `0`)
  - `total_days` (`integer`, default `1`)
  - `chain_hash` (`varchar(64)`, hash rantai SHA-256 berjalan)
  - `completed_at` (`timestamp`, nullable)

### B. Tabel Baru `sim_checkpoints`
- `id` (`bigint unsigned auto_increment primary key`)
- `run_id` (`uuid`, FK ke `sim_runs.id` on delete cascade)
- `day_number` (`integer`)
- `chain_hash` (`varchar(64)`)
- `handler_hashes` (`json`)
- `state_summary` (`json`)
- `created_at`, `updated_at` (`timestamp`)
- Unique key: `['run_id', 'day_number']`

### C. Tabel Baru `sim_run_transitions`
- `id` (`bigint unsigned auto_increment primary key`)
- `run_id` (`uuid`, FK ke `sim_runs.id` on delete cascade)
- `from_status` (`varchar(32)`)
- `to_status` (`varchar(32)`)
- `reason` (`varchar(255)`)
- `actor_id` (`varchar(64)`)
- `actor_role` (`varchar(32)`)
- `metadata` (`json`, nullable)
- `created_at` (`timestamp`)

### D. Tabel Baru `sim_twin_snapshots`
- `id` (`bigint unsigned auto_increment primary key`)
- `run_id` (`uuid`, FK ke `sim_runs.id` on delete cascade)
- `day_number` (`integer`)
- `entity_type` (`varchar(64)`)
- `entity_id` (`varchar(64)`)
- `state_hash` (`varchar(64)`)
- `state_payload` (`json`)
- `created_at` (`timestamp`)
- Unique key: `['run_id', 'day_number', 'entity_type', 'entity_id']`

---

## 3. Rute, Role, dan Hak Akses RBAC

| Metode | URI | Controller / Action | Peran Berhak |
|---|---|---|---|
| `GET` | `/admin/simulation` | `SimulationRunController@index` | `sim_operator`, `admin` |
| `POST` | `/admin/simulation` | `SimulationRunController@store` | `sim_operator`, `admin` |
| `GET` | `/admin/simulation/{run}` | `SimulationRunController@show` | `sim_operator`, `admin` |
| `POST` | `/admin/simulation/{run}/pause` | `SimulationRunController@pause` | `sim_operator`, `admin` |
| `POST` | `/admin/simulation/{run}/resume` | `SimulationRunController@resume` | `sim_operator`, `admin` |
| `GET` | `/admin/simulation/diff` | `SimulationDiffController@index` | `sim_operator`, `admin` |
| `GET` | `/admin/simulation/twins` | `SimulationTwinController@index` | `sim_operator`, `admin` |
| `GET` | `/admin/simulation/twins/{type}/{id}` | `SimulationTwinController@show` | `sim_operator`, `admin` |
| `GET` | `/admin/simulation/export/{type}` | `SimulationExportController@export` | `sim_operator`, `admin` |

---

## 4. Keputusan Arsitektural (ADR W8-D31 s/d D35)
1. **W8-D31 (Waktu Virtual):** Menggunakan `ClockInterface` / `SimClockInterface`. Jam virtual dalam detik bulat maju harian. Dilarang menggunakan `Carbon::setTestNow()` di runtime produksi.
2. **W8-D32 (Discrete Event Scheduler A1):** Urutan eksekusi berdasar `(virtual_second, priority ASC, seq ASC)`. Menjadwalkan ke waktu lampau melempar `PastEventScheduledException`. Urutan handler harian diatur oleh algoritma Kahn A2.
3. **W8-D33 (MINSTD RNG Stream A3):** Multiplicative congruential generator ($a = 48271, m = 2^{31}-1$). Sub-stream dibuat via `split(seed, stream_name)`. Dilarang menggunakan generator non-deterministik.
4. **W8-D34 (Fingerprint & Checkpoint):** Rantai kriptografis harian: $h_0 = \text{SHA256}(\text{"sim-run\|seed="}\cdot \text{seed})$, $h_i = \text{SHA256}(h_{i-1} \cdot \text{"\|"} \cdot \text{day} \cdot \text{"\|"} \cdot \text{canonicalJson}(\text{summary}))$. Checkpoint menyimpan hash rantai dan ringkasan keadaan per handler.
5. **W8-D35 (Sandbox Rollback):** Mode `--mode=sandbox` membungkus seluruh eksekusi dalam database transaction yang selalu di-rollback setelah verifikasi probe keseimbangan ledger $\sum \text{debit} = \sum \text{credit}$. Catatan run dan fingerprint dipersistensikan setelah rollback koneksi baru/terpisah.

