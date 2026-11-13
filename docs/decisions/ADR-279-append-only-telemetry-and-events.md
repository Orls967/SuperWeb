# ADR-279-D: Log Append-Only & Idempotensi Deterministik pada Telemetri dan Event Misi

- **Status**: Diterima (2026-11-13)
- **Komponen**: `RobotMissionEvent`, `RobotTelemetry`, `RobotSafetyIncident`, `RobotTelemetryIngestor`, `OutboxBusService`

## 1. Konteks
Dalam investigasi insiden keselamatan maupun audit kepatuhan SLA gudang, jejak peristiwa (`wms_robot_mission_events`), pembacaan sensor (`wms_robot_telemetries`), dan laporan insiden (`wms_robot_safety_incidents`) merupakan bukti hukum dan operasional utama. Jika baris historis dapat ditimpa atau dihapus, integritas audit hilang. Selain itu, polling telemetri atau retry worker berpotensi mengirim event ganda jika tidak idempoten.

## 2. Keputusan Arsitektur
1. **Guard Append-Only di Level Model Eloquent**:
   - `RobotMissionEvent`, `RobotTelemetry`, dan `RobotSafetyIncident` mendaftarkan listener `static::updating(...)` dan `static::deleting(...)` pada `booted()` untuk melempar `LogicException` apabila ada upaya mutasi atau penghapusan rekaman historis.
   - Untuk `RobotSafetyIncident`, transisi status peninjauan (`reviewed`/`resolved`) hanya dilakukan melalui jalur terotorisasi atau penambahan catatan review yang terpisah dari perubahan isi kejadian awal.
2. **Idempotency Key Deterministik (Aturan `arch:scan` A4 = 0)**:
   - Seluruh key idempotensi dilarang menggunakan `Str::uuid()`, `Str::random()`, `uniqid()`, `now()`, atau `time()`.
   - Format standar yang digunakan:
     - Telemetri: `tel:{robot_id}:{tickKey}`
     - Insiden telemetri: `INC-TEL-{robot_id}-{tickKey}`
     - Event misi: `robotics-mission-{mission_id}-{to_status}-{retry_count}`
     - Event wave: `robotics-wave-{batch_code}`

## 3. Konsekuensi
- **Positif**: Memenuhi aturan `A4 = 0` pada `arch:scan`; pemrosesan ulang batch telemetri dengan `tickKey` yang sama aman dan tidak menggandakan baris maupun tugas `TaskInbox`.
- **Negatif**: Koreksi data historis tidak dapat dilakukan dengan `UPDATE` langsung; segala pembetulan wajib dicatat sebagai entri peristiwa kompensasi baru.

