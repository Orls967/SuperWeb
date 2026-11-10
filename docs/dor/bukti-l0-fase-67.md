# Bukti Kondisi Awal (L.0) — Fase 67: Simulation Kernel, Twin Bus & Scale Provisioner

- **Tanggal Diperiksa:** 2026-11-10
- **Commit Trunk (Master):** `ab6b9d75bafab3f97e51f0c53c8efc928e576e17`
- **Worktree:** `sw-w8-p3` (`feature/w8-p3-event-spine-simulasi`)

## 1. Verifikasi Prasyarat Lingkungan & Alat Nyata di Master

1. `php artisan list | grep -E "quality:|docs:|ui:lint"`:
   - `quality:phase-metrics {--path=} {--phase=} {--check}`: Terverifikasi ada. Mengukur direktori, tanpa `--from` atau `--ui`. Pengukur resmi B1/B2/B3 tetap `git diff T-1` melalui gerbang `T-1b`.
   - `docs:generate {--source=} {--out=}`: Terverifikasi ada (tanpa `--check`).
   - `docs:check {--path=}`, `docs:drift`: Terverifikasi ada.
   - `ui:lint {--paths=*} {--render} {--responsive} {--json}`: Terverifikasi ada.
2. `ls -1 modules/Shared/resources/views/components/ds/ | wc -l`:
   - 31 komponen `x-ds.*` terverifikasi ada.
3. `ls docs/gates/ambang.json`:
   - Terverifikasi TIDAK ADA. Ambang M-2 s/d M-6 dibaca langsung dari prompt.
4. `grep -rn "interface TaskInboxProvider" modules app`:
   - Terverifikasi TIDAK ADA. Yang aktif di master adalah `Modules\Core\Application\Actions\TaskInbox\TaskInboxService::createTask(array $data)`.
5. Semua PR W8-P1 (#103, #104) dan W8-P2 (#105, #106) terverifikasi closed & merged di master. Seluruh workflow run di commit master `ab6b9d75` berstatus `success`.

## 2. Tabel Kondisi Eksisting Komponen Fase 67

| Komponen | Status | Kondisi Nyata di Master | Keputusan Arsitektural |
|---|---|---|---|
| `Modules\Core\Contracts\SimClockInterface` | Ada / Benar | Interface jam virtual simulasi mewarisi `ClockInterface`. | Pakai ulang & pertahankan signature. |
| `Modules\Core\Contracts\SimTickHandlerInterface` | Ada / Benar | Kontrak tick harian (`name`, `shouldRun`, `handle`). | Pakai ulang & pertahankan signature. |
| `Modules\Core\Application\Services\SimClockService` | Ada / Benar | Layanan jam virtual berbasis tabel `sim_runs`. | Pakai ulang & perdalam sinkronisasi run aktif. |
| `Modules\Core\Application\Services\SimTickRegistry` | Ada / Perlu Diperdalam | Berisi registrasi 7 default handler, belum mengurutkan via topological dependency (A2) dan belum menyuntikkan aliran acak terisolasi (A3). | Perdalam dengan urutan Kahn A2 & SimStreamRegistry. |
| 7 Default TickHandlers (`Billing`, `Demurrage`, `Depreciation`, `FranchiseRoyalty`, `Installment`, `LoyaltyExpiry`, `Mrp`) | Ada / Perlu Diperdalam | Berjalan deterministik tetapi belum menerima aliran acak terisolasi dari `SimStreamRegistry`. | Kunci ke SimStreamRegistry/ClockInterface. |
| `Modules\Core\Console\Commands\RunSimulationCommand` (`sim:run`) | Ada / Salah | Memakai `mt_srand`/`srand`, json tidak kanonik, tanpa state machine 8 status, tanpa rollback sandbox D35, tanpa antrean diskrit A1. | Tulis ulang secara mendalam sesuai W8-D31 s/d D35. |
| `Modules\Core\Console\Commands\AuditSimulationKernelCommand` (`sim:audit`) | Ada / Salah | Hanya memeriksa tabel lama secara parsial, belum memverifikasi rantai hash harian A3, kesinambungan checkpoint, riwayat transisi append-only, ledger Σ=0, atau deteksi siklus Kahn A2. | Perdalam menjadi audit dua sumber komprehensif. |
| `Modules\Core\Application\Services\DigitalTwinService` & `sim_twin_states` | Ada / Perlu Diperdalam | Menyimpan status twin, tetapi belum memiliki snapshot per run `sim_twin_snapshots`, belum ada hash prev CanonicalHashChain yang ketat, dan belum mendukung sandbox what-if D35. | Perdalam dengan snapshot dan hash chain prev. |
| Scale Provisioner (`OutboxScaleProvisioner`, `PassportScaleProvisioner`, `scale:provision`) | Ada / Perlu Diperdalam | Kerangka seeder skala dasar dengan tabel `sim_seeder_checkpoints`. Belum ada `ScaleTierPlan` T1/T2 formal dan benchmark per etape. | Perdalam kelas produksi ScaleTierPlan T1/T2. |
| State Machine `SimulationRun` (8 status) | Tidak ada | Status di `sim_runs` masih string bebas (`idle`, `running`, `paused`, `completed`), tanpa riwayat transisi append-only dan tanpa guard. | Buat baru di `Modules\Core\Domain\StateMachines\SimulationRunStateMachine`. |
| Tabel `sim_checkpoints` & `sim_run_transitions` & `sim_twin_snapshots` | Tidak ada | Checkpoint run harian belum dipersistensikan di tabel mandiri; riwayat transisi belum append-only. | Buat migrasi baru. |
| Kontrak `SimulationRunner` & `SimStreamRegistry` | Tidak ada | Kontrak belum ada di `Modules\Core\Contracts\`. | Buat baru sebagai kontrak publik W8. |
| Algoritma A1 (`DiscreteEventScheduler`), A2 (`SubscriberTopologicalOrder`), A3 (`StreamSeedAndFingerprint`) | Tidak ada | Scheduler diskrit, topological Kahn, dan MINSTD stream & fingerprint belum ada. | Implementasikan di `Modules\Core\Domain\Algorithms\Simulation\`. |
| UI Operator Simulation (`/admin/simulation`, `/admin/simulation/{run}`, `/admin/simulation/diff`, `/admin/simulation/twins`) | Tidak ada | Hanya ada view tata kelola lama `/admin/simulation-governance`. | Bangun antarmuka operator responsif (375px & 1280px) berbasis komponen `x-ds.*`. |
| Command `sim:diff` & `sim:checkpoint-prune` | Tidak ada | Belum ada pembanding run deterministik dan pemangkasan checkpoint. | Buat baru di `Modules\Core\Console\Commands\`. |

## 3. Pemilihan Modul Domain untuk Pilar Nyata (D67-3)
Dipilih 3 modul domain yang memiliki Action peka waktu, idempoten per hari, dan relevan dengan siklus bisnis:
1. **Modules\Hospital**: `DischargeStaleEpisodesTickHandler` — memeriksa dan menyelesaikan billing/discharge pasien rawat inap yang telah melewati tanggal rencana pulang.
2. **Modules\Hotel**: `PostDailyRoomAccrualTickHandler` — memproses night audit akrual kamar hotel per malam menginap tamu aktif.
3. **Modules\Logistics**: `AccrueDemurragePenaltyTickHandler` — mengakrualkan demurrage kontainer/armada yang melewati batas free time di pelabuhan/depo.

Ketiga handler ini ditempatkan di modul masing-masing (zona aditif) tanpa Core mengimpor modul bisnis (Zero Dependency A9 terjaga).

