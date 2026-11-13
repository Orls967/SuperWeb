# Spesifikasi State Machine, Saga & Jembatan Tugas Gudang — Fase 279

- **Fase**: 279 (W8-P6.3)
- **Komponen Utama**: `RobotMissionStateMachine`, `RobotMissionTransitionService`, `RoboticsMissionLifecycleSaga`, `WarehouseTaskCompletionBridge`

## 1. Delapan Status `RobotMissionStateMachine`
State machine misi robotika dirancang untuk memodelkan siklus hidup penuh dari antrean hingga penyelesaian atau pengalihan manual:
1. `queued`: Misi baru menunggu alokasi robot (`HungarianSolver`).
2. `assigned`: Robot online telah dipasangkan ke misi, menunggu izin masuk zona (`J1`).
3. `moving`: Robot telah memperoleh `ZoneReservation` aktif dan sedang bergerak menuju bin/rak.
4. `picking`: Robot berada di lokasi rak dan mengambil barang.
5. `delivering`: Robot membawa barang menuju stasiun pack/stage.
6. `completed`: Misi selesai sukses (status terminal).
7. `failed`: Misi mengalami kegagalan teknis, interlock, atau preemption deadlock.
8. `fallback_manual`: Misi gagal melewati batas retry (`>= 2`) atau dialihkan ke operator manusia (status terminal).

## 2. Aturan Transisi & Batas Retry
- `MAX_RETRY_ATTEMPTS = 2`: Transisi `failed -> queued` hanya diizinkan apabila `retry_count < 2`. Setiap retry menaikkan `retry_count` sebesar 1.
- Apabila `retry_count >= 2`, pemanggilan `RetryOrFallbackRobotMissionAction::retry()` melempar `RobotMissionTransitionException` dan sistem mengarahkan ke `fallbackAndReplan()`.
- Setiap transisi sah selalu mencatat satu baris immutable pada `wms_robot_mission_events` (`from_status`, `to_status`, `reason_code`, `actor_user_id`, `recorded_at`).

## 3. Kompensasi Saga (`RoboticsMissionLifecycleSaga`)
`RoboticsMissionLifecycleSaga` mengorkestrasi eksekusi multi-langkah (alokasi -> reservasi zona -> pergerakan -> penyelesaian) dengan jaminan **tidak ada reservasi zona menggantung** apabila terjadi kegagalan di tengah jalan:
1. Jika `ReserveZoneForMissionAction` atau langkah setelahnya gagal setelah `ZoneReservation` berstatus `active` dibuat:
   - Saga menangkap `Throwable` di dalam blok kompensasi.
   - Memanggil `releaseActiveReservationsForMission()` untuk mengubah seluruh reservasi `active` milik misi menjadi `released`.
   - Mentransisikan misi ke `failed` (atau `fallback_manual` sesuai kebijakan) dengan kode alasan kegagalan.
   - Menerbitkan event `robotics.mission.failed.v1` melalui `OutboxBusService::record()` setelah `DB::afterCommit`.

## 4. Integrasi Internal dengan `wms_tasks` (`WarehouseTaskCompletionBridge`)
Agar sistem WMS induk (Fase 41) tetap sinkron dengan eksekusi robot:
- **Saat `completed`**: `CompleteRobotMissionAction` memanggil `WarehouseTaskCompletionBridge::confirmLinkedTask($mission)` untuk menandai `wms_tasks.status = 'confirmed'` dan mengisi `completed_at`.
- **Saat `fallback_manual`**: `RetryOrFallbackRobotMissionAction` memanggil `WarehouseTaskCompletionBridge::createManualFallbackTask($mission, $operatorUserId, $reason)` untuk membuat atau menugaskan ulang baris di `wms_tasks` (`kind = 'pick'`, `priority = 'high'`, `source_ref = 'MAN-'.$mission->mission_code`) dan mengaitkannya ke `assigned_to_user_id`.

## 5. Idempotensi Event Outbox (`EventCatalog`)
- `robotics.mission.completed.v1`: key idempoten `robotics-mission-{id}-completed-{retry_count}`.
- `robotics.mission.failed.v1`: key idempoten `robotics-mission-{id}-failed-{retry_count}` atau `fallback_manual`.
- `robotics.wave.released.v1`: key idempoten `robotics-wave-{batchCode}`.
Seluruh key bersifat deterministik tanpa UUID/random/timestamp (memenuhi Aturan `arch:scan` A4 = 0).
