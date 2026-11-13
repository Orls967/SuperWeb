# Algoritma — `RobotHealthScorer`

- **Kelas**: `Modules\Wms\Domain\Algorithms\RobotHealthScorer`
- **Dipanggil oleh**: `RobotTelemetryIngestor`, `IngestSimulatedTelemetryBatchAction`

## 1. Tujuan & Formulasi Skoring
`RobotHealthScorer` menghitung skor kesehatan deterministik `[0..1000]` untuk setiap unit robot berdasarkan tiga parameter telemetri diskret:
1. `batteryPct` (integer `0..100`): penalti `(100 - batteryClamped) * 4` (maksimum 400 poin).
2. `errorCount` (integer `>= 0`): penalti `min(400, errorCount * 80)` (maksimum 400 poin).
3. `downtimeMinutes` (integer `>= 0`): penalti `min(300, downtimeMinutes * 5)` (maksimum 300 poin).

Skor akhir:
`health_score = max(0, 1000 - batteryPenalty - errorPenalty - downtimePenalty)`

## 2. Klasifikasi Anomali & Ambang Tindakan
| Kondisi | Kode Anomali (`anomaly_code`) | Tindakan Otomatis |
|---|---|---|
| `batteryClamped <= 15` | `CRITICAL_LOW_BATTERY` | Buka `RobotSafetyIncident` & tugas `TaskInbox` prioritas tinggi |
| `errorCount >= 3` | `HIGH_ERROR_BURST` | Eskalasi pemeriksaan sensor ke operator gudang |
| `downtimeMinutes >= 30` | `EXCESSIVE_DOWNTIME` | Kandidat jendela pemeliharaan (`RobotMaintenanceWindowPlanner`) |
| `health_score < 600` (`ANOMALY_THRESHOLD_SCORE`) | `DEGRADED_FLEET_HEALTH` | Tandai unit terdegradasi pada Control Tower |

## 3. Invarian Determinisme
Seluruh perhitungan menggunakan integer penuh tanpa `float`. Dua pemanggilan dengan masukan telemetri identik selalu menghasilkan `health_score`, `is_anomaly`, dan `anomaly_code` yang persis sama.

