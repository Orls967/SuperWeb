# Playbook Penanganan Insiden — Robotika Gudang (Fase 279)

- **Fase**: 279 (W8-P6.3)
- **Audiens**: `hub_operator`, `admin`, `auditor`, On-Call SRE

## 1. Playbook INS-01: Zona Terblokir Staf (`STAFF_ZONE_BLOCKED`)
### Gejala
- Misi berstatus `assigned` gagal berpindah ke `moving` dengan `RobotMissionTransitionException`.
- Log `wms_robot_mission_events` mencatat `reason_code = STAFF_ZONE_BLOCKED`.

### Langkah Triage & Pemulihan
1. Buka `/wms/robotics/zones` dan periksa kolom `staff_blocked`, `block_start_min`, `block_end_min`, serta `block_reason` pada zona terkait.
2. Konfirmasi secara fisik di lantai gudang apakah personel pemeliharaan atau picker masih berada di dalam lorong.
3. **Jangan pernah** memaksa memindahkan robot sebelum lorong benar-benar kosong.
4. Setelah personel keluar:
   - Nonaktifkan `staff_blocked = false` melalui antarmuka Control Tower.
   - Jalankan `php artisan robotics:allocate <warehouse_id>` untuk menugaskan ulang antrean tertunda.
5. Verifikasi di `/wms/robotics/missions` bahwa misi kembali berstatus `moving`.

## 2. Playbook INS-02: Kebuntuan Lalu Lintas (`DEADLOCK_VICTIM_PREEMPTED`)
### Gejala
- Dua atau lebih robot saling menunggu di lorong berkapasitas `capacity_k = 1`.
- Throughput picking menurun dan utilisasi zona mencapai `>= 800‰` (`HIGH`).

### Langkah Triage & Pemulihan
1. Jalankan detektor deadlock:
   ```bash
   php artisan robotics:deadlock-scan <warehouse_id>
   ```
2. `TrafficDeadlockDetector` memetakan graf tunggu dan memilih satu misi berprioritas terendah sebagai victim.
3. Pastikan misi victim berpindah ke `failed` dan seluruh `ZoneReservation` aktif miliknya dilepas (`released`).
4. Jika victim masih memiliki `retry_count < 2`, biarkan `RetryOrFallbackRobotMissionAction::retry()` mengantrekan ulang (`queued`).
5. Jika kebuntuan berulang di zona yang sama, jalankan `php artisan robotics:simulate traffic_density` untuk mengevaluasi perlunya penyesuaian kapasitas atau pembatasan wave.

## 3. Playbook INS-03: Baterai Kritis & Degradasi Pak (`CRITICAL_LOW_BATTERY`)
### Gejala
- `IngestSimulatedTelemetryBatchAction` membuka `RobotSafetyIncident` dengan `incident_type = telemetry_threshold_breach`.
- `RobotHealthScorer` mengembalikan `anomaly_code = CRITICAL_LOW_BATTERY` (`battery_pct <= 15%`).

### Langkah Triage & Pemulihan
1. Pastikan robot ditolak oleh `AllocateRobotMissionsAction` (`MIN_BATTERY_PCT = 20%`).
2. Jalankan penjadwal pengisian daya:
   ```bash
   php artisan robotics:charge-plan <warehouse_id>
   ```
3. Periksa rencana bay di `/wms/robotics/charging` (`ChargingBayContentionResolver` mengalokasikan `BAY-01..BAY-04`).
4. Tinjau metrik degradasi pak baterai dari `RobotBatteryDegradationTracker`: jika `health_permil <= 750‰`, masukkan unit ke daftar `RobotMaintenanceWindowPlanner` (maksimal `15%` armada dalam maintenance bersamaan).
5. Tinjau dan tutup insiden di `/wms/robotics/incidents` setelah unit mencapai target pengisian `90%`.

## 4. Playbook INS-04: Eskalasi Fallback Manual & Konservasi Order (`fallback_manual`)
### Gejala
- Misi gagal `>= 2` kali (`retry_count >= 2`) dan dialihkan ke `fallback_manual`.
- `TaskInbox` menerbitkan tugas baru bertajuk `Fallback Manual Misi Robot <mission_code>`.

### Langkah Triage & Pemulihan
1. Operator yang tertaut (`assigned_operator_user_id`) membuka tugas manual `MAN-<mission_code>` di `wms_tasks`.
2. Pastikan tidak ada order yang hilang dari wave pengiriman kurir:
   - `PlanRoboticsWaveAction` telah melakukan replanning otomatis via `WaveBinPacker` (`J2`).
   - Verifikasi via `RoboticsWaveReconciliationService::reconcileBatch()` bahwa `missing_order_ids` dan `duplicate_order_ids` kosong (`conserved = true`).
3. Setelah picker manusia menyelesaikan pengambilan barang, konfirmasi penyelesaian tugas di WMS (Fase 41).

## 5. Playbook INS-05: Kegagalan Audit Dua Sumber (`robotics:audit` Exit Non-0)
### Gejala
- Command `php artisan robotics:audit` keluar dengan kode `1` dan mencetak baris dari `WmsRoboticsAuditCliReportFormatter`.

### Langkah Triage & Pemulihan
1. Identifikasi kode entitas bermasalah pada output CLI (contoh: `MIS-CORRUPT-01` untuk misi yatim tanpa referensi robot/zona).
2. Jangan menghapus log append-only (`wms_robot_mission_events`, `wms_robot_telemetries`, `wms_robot_safety_incidents`).
3. Perbaiki referensi master atau lepaskan reservasi usang melalui `php artisan robotics:reservation-sweep`.
4. Jalankan ulang `php artisan robotics:audit` hingga keluar kode `0` (`issues=0`).

## 6. Playbook INS-06: Pelanggaran Sandbox Simulasi (`RuntimeException`)
### Gejala
- `php artisan robotics:simulate` melempar `Pelanggaran sandbox simulasi: hash tabel produksi berubah.`

### Langkah Triage & Pemulihan
1. Segera hentikan eksekusi simulasi terjadwal.
2. Bandingkan `computeProductionStateHash()` dan periksa apakah ada koneksi database non-sandbox yang tertulis selama eksekusi.
3. Verifikasi `RoboticsSimulationScenarioRunner::runScenario()` kembali menghasilkan `hash_identical = true` sebelum mengaktifkan kembali simulasi pra-shift.
