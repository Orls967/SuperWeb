# Definition of Ready (DoR) — Fase R8: Universal Event Spine, Idempotent Consumer Inbox, Outbox Migration, DLQ & Dual-Source Audit

**Gelombang**: W8-P3.2 (Paket 3, Fase 2 dari 2)  
**ID Laporan**: `LP_ID=sw-w8-p3`  
**Base Commit**: `0ef8d6b99912776c5b96495be240a5e8e3f225ad`  
**Ambang B1**: $\ge 7.500$ baris (Target RAB: 8.350 baris, buffer $+11.3\%$)  
**Ambang B3**: $\ge 1.500$ baris (Target RAB: 1.750 baris, buffer $+16.7\%$)  
**Plafon Acceptance Test**: $\le 5$ acceptance test terpadu di `tests/Acceptance/EventSpine/`  

---

## 1. Lingkup & Batasan Arsitektural (Katalog Kontrak & Izin)

1. **IZIN-1 (Modul Baru)**: TIDAK ADA. Semua kode inti berada di modul `modules/Core` dengan tabel berprefiks `core_` dan `sim_`. Adaptor listener lama diletakkan di modul Core atau domain tanpa merusak listener asli.
2. **IZIN-2 (Dependensi Pihak Ketiga)**: TIDAK ADA (tanpa Kafka, RabbitMQ, Redis Streams, atau pustaka eksternal baru). Semua fungsionalitas Event Spine dibangun di atas SQLite/MySQL transaksi bawaan.
3. **IZIN-3 (Penghapusan Kode Lama)**: TIDAK DIBERIKAN. Seluruh jalur event lama dipertahankan lewat `LegacyListenerAdapter` generik; paritas diuji secara deterministik.
4. **IZIN-4 (Waktu Terinjeksi)**: Penegakan ketat `ClockInterface` / `SimClockInterface` tanpa pemanggilan `Carbon::setTestNow()` di produksi.

---

## 2. Rencana Anggaran Biaya (RAB) Baris Logika B1

| Lapisan Arsitektur | Target Baris (Min - Max) | Rencana File & Tanggung Jawab |
| :--- | :---: | :--- |
| **Domain: Algoritma Bersertifikat (A4, A5, A6)** | 1.100 – 1.400 | - `AggregateSequenceGapDetector.php` (A4): $O(n \log n)$ gap & duplicate detection.<br>- `DeterministicBackoff.php` (A5): Integer exponential backoff berbasis MINSTD tanpa `sleep()`.<br>- `SchemaAdditiveCompatibilityChecker.php` (A6): JSON Schema backward-compatibility checker.<br>- `NearestRankLagCalculator.php`: P95 lag rank calculator.<br>- `ProjectionHashCalculator.php`: Kanonikal JSON state SHA-256 hash calculator. |
| **Domain: State Machine (EventDelivery)** | 650 – 850 | - `EventDeliveryStateMachine.php`: 7 status (`pending`, `dispatched`, `delivered`, `retrying`, `dead_lettered`, `replayed`, `discarded`), guards, transitions, immutable history audit. |
| **Domain: Models & Events** | 500 – 700 | - `CoreConsumerInbox.php`: Persisten deduplikasi inbox.<br>- `CoreSpineGap.php`: Deteksi celah urutan aggregate.<br>- `CorePoisonMessage.php`: Karantina poison message.<br>- `CoreEventDelivery.php`: Log pengiriman pesan per consumer group.<br>- Events: `SpineDeadLetteredEvent`, `SpineGapDetectedEvent`, `PoisonMessageQuarantinedEvent`. |
| **Aplikasi: Services** | 2.100 – 2.500 | - `EventSpineService.php` (Deepening): Validasi skema saat publish, transactional emit.<br>- `EventSchemaRegistry.php`: Registrasi skema berversi, penolakan non-additive.<br>- `DeadLetterQueueService.php`: Filter, replay batch 2-langkah, discard with reason.<br>- `PoisonMessageQuarantineService.php`: Isolasi pesan tanpa memblokir offset.<br>- `SpineLagMonitorService.php`: P95 consumer lag tracking.<br>- `SpineReplayService.php`: Replay stream deterministik dengan hash proyeksi kanonikal.<br>- `LegacyListenerAdapterService.php`: Generic bridge untuk 29 listener lama. |
| **Aplikasi: Actions** | 1.100 – 1.400 | - `ConsumeSpineBatchAction.php`<br>- `ClaimConsumerInboxAction.php`<br>- `ReplaySpineStreamAction.php`<br>- `ReplayDeadLetterBatchAction.php`<br>- `DiscardDeadLetterAction.php` (Approval Engine bridge bila > 20)<br>- `ScanAggregateGapsAction.php`<br>- `QuarantinePoisonMessageAction.php`<br>- `RegisterEventSchemaAction.php` |
| **Console & Scheduled Commands** | 900 – 1.200 | - `ConsumeSpineCommand.php` (`core:consume-spine` deepening)<br>- `ProcessOutboxCommand.php` (`core:process-outbox` deepening)<br>- `SpineGapScanCommand.php` (`spine:gap-scan`, cron 15 menit)<br>- `SpineReplayCommand.php` (`spine:replay`)<br>- `SpineDlqReplayCommand.php` (`spine:dlq-replay`)<br>- `SpineLagReportCommand.php` (`spine:lag-report`)<br>- `AuditSpineCommand.php` (`spine:audit`, dual-source audit 6 pemeriksaan) |
| **HTTP: Controllers, Requests, Policies, ViewModels** | 800 – 1.100 | - `SpineDashboardController.php`, `SpineDlqController.php`, `SpineConsumerController.php`, `SpineGapController.php`, `SpineSchemaController.php`<br>- Policies: `EventSpinePolicy`, `SpineDlqPolicy`, `EventSchemaPolicy`<br>- ViewModels & FormRequests for DLQ discard/replay and gap resolution. |
| **Integrasi & Adaptor Domain** | 500 – 700 | - `TaskInboxService` bridge (menugaskan DLQ item ke `spine_operator`)<br>- `ApprovalEngine` bridge (persetujuan batch discard > 20 pesan)<br>- Adaptor listener lama teruji paritas. |
| **Database Migrations** | 400 – 600 | - `create_core_consumer_inbox_table.php`<br>- `create_core_spine_gaps_table.php`<br>- `create_core_poison_messages_table.php`<br>- `create_core_event_deliveries_table.php`<br>- `alter_core_outbox_add_sequence_and_delivery.php` |
| **Total Estimasi B1** | **8.050 – 9.450** | **Target Minimal Lolos: $\ge 7.500$ baris (Buffer $+11\%$ aman)** |

---

## 3. Rencana Anggaran Biaya (RAB) Baris Dokumentasi B3

| Berkas Dokumentasi | Target Baris | Fokus & Isi |
| :--- | :---: | :--- |
| `docs/dor/fase-r8.md` | 180 – 220 | DoR, batasan arsitektur, RAB detail, inventarisasi. |
| `docs/dor/bukti-l0-fase-r8.md` | 100 – 140 | Bukti baseline L.0 kondisi awal master `0ef8d6b9`. |
| `docs/design/fase-r8.md` | 400 – 500 | Arsitektur detail, flowchart Mermaid alur event spine, SM 7 status, DLQ & replay. |
| `docs/decisions/ADR-0036..0040.md` | 600 – 750 | 5 ADR lengkap (SM Backoff, Inbox Idempotency, Schema Additive, Replay Hash, DLQ Poison). |
| `docs/diagrams/event-spine.md` | 200 – 250 | Peta topik, produsen, konsumen, schema registry mapping. |
| `docs/galat/event-spine-error-catalog.md` | 150 – 200 | Kode error, penyebab, solusi pemulihan operasional. |
| **Total Estimasi B3** | **1.630 – 2.060** | **Target Minimal Lolos: $\ge 1.500$ baris (Buffer $+15\%$ aman)** |

---

## 4. Acceptance Criteria & Verifikasi

1. **AC-1 (State Machine EventDelivery)**: 7 status didukung penuh dengan transisi legal dan append-only audit history.
2. **AC-2 (Algoritma A4, A5, A6)**: Vektor prompt diuji dan lulus 100% tanpa komputasi apung (`float`) dan tanpa `sleep()`.
3. **AC-3 (Persistent Consumer Inbox)**: `claim()` atomik mencegah eksekusi ganda meskipun proses dimatikan/di-restart.
4. **AC-4 (Dead-Letter Queue & Poison Isolation)**: Pesan gagal di-quarantine ke `core_poison_messages` tanpa memblokir pemrosesan pesan sehat lain dalam stream.
5. **AC-5 (Deterministic Replay)**: Dua kali eksekusi replay stream offset menghasilkan kanonikal projection hash yang identik ($h_1 == h_2$).
6. **AC-6 (Dual-Source Audit)**: `spine:audit` memvalidasi 6 pemeriksaan silang dan exit non-zero dengan pesan detail ketika diinjeksi fixture korupsi.
7. **AC-7 (Paritas Listener Lama)**: Semua 29 listener lama berfungsi identik melalui adaptor tanpa modifikasi file listener.

