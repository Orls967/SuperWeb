# Bukti Kondisi Awal Fase R8 (L.0)

**Fase**: R8 — Universal Event Spine, Idempotent Consumer Inbox, Outbox Migration, DLQ & Dual-Source Audit  
**Waktu Pengambilan**: 2026-11-11T01:27:00+08:00  
**Base Commit**: `0ef8d6b99912776c5b96495be240a5e8e3f225ad` (Merge PR #107 Fase 67 ke master)  
**Branch**: `feature/w8-p3-event-spine-r8`

---

## 1. Verifikasi Status Alat W8-P1 & W8-P2 di Master

```bash
$ php artisan list | grep -E "quality:|docs:|ui:lint"
  docs:check                      Memeriksa kesegaran dokumen desain, broken links, dan integritas diagram
  docs:drift                      Mendeteksi pergeseran (drift) antara spesifikasi dokumen dengan implementasi skema database
  docs:export                     Mengekspor seluruh bundle dokumentasi arsitektur dan skema
  docs:generate                   Menghasilkan artefak diagram SVG secara deterministik dari blok Mermaid murni PHP (IZIN-2)
  docs:inventory                  Generate bagian faktual docs/CODEBASE.md (modul, prefiks, migrasi, rute, command & jadwal, akun ledger) (K-03, PROGRESS R5.1)
  quality:coverage-gap            Mengevaluasi selisih gap cakupan test suite terhadap target
  quality:phase-metrics           Mengukur metrik fase B1-B3, cyclomatic density, serta mendeteksi manipulasi kode M-2 s/d M-5
  quality:pipeline-status         Menampilkan status integrasi pipeline CI/CD dan trunk git
  ui:lint                         Linter aksesibilitas, tokenisasi warna, responsivitas, dan kepatuhan Design System untuk template Blade
```

- `docs/gates/ambang.json`: **TIDAK ADA**. Sesuai T-3, ambang M-2..M-5 dibaca langsung dari prompt.
- `interface TaskInboxProvider`: **TIDAK ADA**. Yang ada adalah `Modules\Core\Application\Actions\TaskInbox\TaskInboxService::createTask(array $data)`.
- Komponen DS: 31 komponen `x-ds.*` aktif di `modules/Shared/resources/views/components/ds/`.
- Pengukur resmi: `/tmp/gerbang.sh <sha-awal-r8> 7500` berbasis git diff tiga partisi (B1, B2, B3).

---

## 2. Inventarisasi Kode & Skema Eksisting Terkait Event Spine & Outbox

### A. Tabel Database Eksisting
1. `core_outbox`: Tabel outbox transaksional berstatus `pending`, `dispatched`, `failed`, `dead_letter` dengan kolom `attempts`, `next_retry_at`, `payload`, `headers`, `idempotency_key`.
2. `core_outbox_subscriptions` & `core_outbox_dispatches`: Dispatch log webhook/queue/listener.
3. `sim_event_spine`: Append-only event log (`event_id`, `topic`, `event_name`, `version`, `payload`, `idempotency_key`, `occurred_at`).
4. `sim_event_consumers`: Offset tracker per `(consumer_group, topic)`.
5. `core_event_schemas`: Registry skema event (`event_name`, `version`, `producer_module`, `consumer_modules`, `payload_schema`, `description`).

### B. Cacat Teridentifikasi & Sasaran Perbaikan
1. **Idempotensi In-Memory**: `SpineConsumerRegistry::$processedEvents` menyimpan cache di array statis memori PHP, yang hilang ketika proses worker/command restart. Sasaran: Digantikan oleh tabel database persisten `core_consumer_inbox` (`claim()` atomik).
2. **Ketiadaan Dead-Letter Queue (DLQ) & Karantina Poison Khusus**: Ketika pesan gagal berkali-kali atau gagal skema, seluruh offset konsumen dapat tertahan. Sasaran: Status SM 7 `EventDelivery` dan tabel `core_poison_messages`.
3. **Celah Urutan (Sequence Gap)**: Belum ada pendeteksi celah urutan aggregate pada outbox/spine. Sasaran: Algoritma A4 `AggregateSequenceGapDetector` + tabel `core_spine_gaps` + command `spine:gap-scan`.
4. **Jeda Retry Tidak Deterministik**: Menggunakan random atau linear backoff. Sasaran: Algoritma A5 `DeterministicBackoff` berbasis MINSTD tanpa `sleep()`.
5. **Evolusi Skema Tanpa Validasi Aditif**: Skema event dapat diubah sembarangan tanpa deteksi breaking change. Sasaran: Algoritma A6 `SchemaAdditiveCompatibilityChecker`.

---

## 3. Inventarisasi Listener / Consumer Eksisting (DR8-6)
Sebanyak 29 listener aktif di lintas modul diidentifikasi:
- `Modules\Core\Application\Listeners\SearchIndexEventSubscriber`
- `Modules\Core\Application\Listeners\RecordVehicleAcquiredPassportEvent`
- `Modules\Hotel\Domain\Listeners\NotifyBanquetKitchenOnBeoFinalizedListener`
- `Modules\Hotel\Domain\Listeners\SyncAllotmentToCrsInventoryListener`
- `Modules\Hotel\Domain\Listeners\TriggerAttritionPenaltyCheckListener`
- `Modules\Hotel\Domain\Listeners\UpdateWeddingMilestoneStatusListener`
- `Modules\Hotel\Domain\Listeners\RecordMiceLedgerJournalEntryListener`
- `Modules\Payment\Application\Listeners\NotifyPaymentCaptured`
- `Modules\Payment\Application\Listeners\NotifyPaymentRefunded`
- `Modules\Platform\Domain\Listeners\PlatformReleaseAuditSubscriber`
- `Modules\Manufacturing\Application\Listeners\PostSaleCogsListener`
- `Modules\AutoServe\Application\Listeners\CreatePredictiveMaintenanceBooking`
- `Modules\AutoServe\Application\Listeners\NotifyBookingCompleted`
- `Modules\AutoServe\Application\Listeners\RecordBookingCompletedPassportEvent`
- `Modules\Hospital\Application\Listeners\NotifyRapidResponseTeamListener`
- `Modules\Hospital\Application\Listeners\RecordPassportAuditLogListener`
- `Modules\Hospital\Application\Listeners\UpdateBedCapacityMetricListener`
- `Modules\Banking\Listeners\CreateUserWalletListener`
- `Modules\Esg\Application\Listeners\RecordScopeTwoElectricityEmissionsListener`
- `Modules\Finance\Listeners\MonitorLoanRisk`
- `Modules\Mall\Listeners\CancelParkingMembershipOnVehicleTransfer`
- `Modules\Venue\Application\Listeners\HandleScalperBlacklistAuditListener`
- `Modules\Venue\Application\Listeners\NotifyResaleSellerSettlementListener`
- `Modules\Venue\Application\Listeners\NotifyNextWaitlistUserListener`
- `Modules\Venue\Application\Listeners\RecordSecondaryLedgerListener`
- `Modules\Pricing\Application\Listeners\PostSalePriceLockListener`
- `Modules\Logistics\Application\Listeners\RecognizeFreightRevenueOnDelivery`
- `Modules\Logistics\Application\Listeners\CreateShipmentOnOrderPaid`
- `Modules\Logistics\Application\Listeners\HandleFleetServiceDue`

Semua listener ini dipertahankan utuh dan didukung paritasnya melalui `LegacyListenerAdapter` generik.

