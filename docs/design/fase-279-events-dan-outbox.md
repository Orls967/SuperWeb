# Desain Event Domain & Kontrak Outbox — Fase 279

- **Fase**: 279 (W8-P6.3)
- **Registry**: `modules/Core/Domain/Events/EventCatalog.php`
- **Publisher**: `RobotMissionTransitionService`, `PlanRoboticsWaveAction` via `OutboxBusInterface::record()`

## 1. Jaminan Pengiriman Transaksional (Transactional Outbox)
Seluruh event domain Fase 279 tidak pernah dikirim langsung ke message broker di tengah transaksi database. Sebaliknya:
1. Perubahan status (`RobotMission` atau `RobotWave`) disimpan bersama log append-only di dalam `DB::transaction()`.
2. Pemanggilan `$this->outboxBus->record(...)` dibungkus di dalam `DB::afterCommit(...)` sehingga event hanya tercatat apabila transaksi bisnis benar-benar commit.
3. Setiap pemanggilan menyertakan `idempotency_key` deterministik (tanpa UUID/waktu acak, mematuhi Aturan `A4 = 0`).

## 2. Skema Event `robotics.mission.completed.v1`
- **Pemicu**: Misi mencapai status terminal `completed` (`CompleteRobotMissionAction`).
- **Idempotency Key**: `robotics-mission-{mission_id}-completed-{retry_count}`
- **Payload**:
  ```json
  {
    "warehouse_id": "019a0000-0000-7000-8000-000000000279",
    "mission_id": 42,
    "mission_code": "MIS-T1-042",
    "from_status": "delivering",
    "to_status": "completed",
    "reason_code": "DELIVERY_CONFIRMED"
  }
  ```

## 3. Skema Event `robotics.mission.failed.v1`
- **Pemicu**: Misi memasuki status `failed` atau `fallback_manual` (`RobotMissionTransitionService`).
- **Idempotency Key**: `robotics-mission-{mission_id}-{to_status}-{retry_count}`
- **Payload**:
  ```json
  {
    "warehouse_id": "019a0000-0000-7000-8000-000000000279",
    "mission_id": 43,
    "mission_code": "MIS-T1-043",
    "from_status": "moving",
    "to_status": "fallback_manual",
    "reason_code": "STAFF_ZONE_BLOCKED"
  }
  ```

## 4. Skema Event `robotics.wave.released.v1`
- **Pemicu**: Batch wave FFD `J2` berhasil disusun dan diverifikasi oleh `WaveBinPackerCertificate` (`PlanRoboticsWaveAction`).
- **Idempotency Key**: `robotics-wave-{batch_code}`
- **Payload**:
  ```json
  {
    "warehouse_id": "019a0000-0000-7000-8000-000000000279",
    "batch_code": "BATCH-01",
    "wave_count": 2,
    "total_orders": 3,
    "certificate_hash": "<sha256>"
  }
  ```

## 5. Panduan Konsumen Event
Konsumen di modul lain (misalnya analitik atau notifikasi) wajib memproses event secara idempoten berdasarkan `(event_type, idempotency_key)` dan tidak boleh mengasumsikan urutan kedatangan antar-batch yang berbeda.
