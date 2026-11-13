# Spesifikasi Keselamatan & SOP Koeksistensi Robotika Gudang (ISO 3691-4)

- **Fase**: 279 (W8-P6.3)
- **Komponen Utama**: `RoboticsZoneInterlockEvaluator`, `ReserveZoneForMissionAction`, `EnforceZoneSafetyInterlockAction`, `SafetyIncidentLog`

## 1. Prinsip Dasar Keselamatan Manusia–Robot
Dalam operasi gudang hibrida di mana pekerja manusia (`hub_operator`, picker, teknisi pemeliharaan) berbagi area kerja dengan armada AMR/AGV/ASRS, keselamatan manusia memiliki prioritas mutlak di atas throughput mesin:
1. **Pemisahan Spasial Keras (`capacity_k = 1`)**: Lorong sempit berkapasitas satu unit wajib dikunci penuh (`staff_blocked = true`) sebelum pekerja masuk. Selama `staff_blocked = true` aktif pada jendela `[block_start_min, block_end_min)`, tidak ada misi robot yang boleh dialokasikan atau memulai pergerakan ke zona tersebut.
2. **Pembatasan Kecepatan Koeksistensi (`capacity_k >= 2`)**: Apabila dua unit atau lebih (atau unit dengan zona penyangga) beroperasi dalam zona berkapasitas lebih dari 1, `RoboticsZoneInterlockEvaluator` membatasi kecepatan rekomendasi maksimum menjadi `MAX_COEXISTENCE_SPEED_MM_S = 800 mm/s` (dibandingkan kecepatan lorong bebas `STANDARD_SPEED_MM_S = 1800 mm/s`).
3. **Cadangan Energi Kritis**: Robot tidak diizinkan memasuki zona kerja aktif apabila `battery_pct < 15%` (`LOW_BATTERY_INTERLOCK`), dan tidak diizinkan menerima penugasan misi baru apabila `battery_pct < 20%` (`MIN_BATTERY_PCT`), guna mencegah robot mati mendadak dan memblokir lorong utama.

## 2. Siklus Interlock Saat Eksekusi Misi
1. **Pra-Alokasi (`AllocateRobotMissionsAction` & `RobotFleetDispatchOptimizer`)**:
   - Menyaring hanya robot dengan `is_online = true` dan `battery_pct >= 20%`.
   - Memberikan penalti biaya `STAFF_BLOCKED_PENALTY = 100_000_000` pada sel matriks Hungarian untuk misi yang menarget zona terblokir staf.
   - Menyaring ulang hasil solver terhadap `feasibilityGrid` sehingga misi berisiko tetap berada di `unassigned_mission_ids`.
2. **Pra-Pergerakan (`ReserveZoneForMissionAction::reserveAndStartMoving`)**:
   - Mengunci baris `TrafficZone` dan `RobotMission` menggunakan `lockForUpdate()` di dalam `DB::transaction()`.
   - Memeriksa `staff_blocked` dan jendela `[block_start_min, block_end_min)`. Jika bertumpuk, melempar `RobotMissionTransitionException` (`STAFF_ZONE_BLOCKED`).
   - Memanggil `ZoneReservationScheduler::schedule()` untuk memperoleh slot `[start_min, end_min)` paling awal yang memenuhi `okupansi < capacity_k`.
   - Memastikan tidak ada deadlock sebelum mengubah status misi dari `assigned` ke `moving`.
3. **Saat Terjadi Pelanggaran atau Anomali Telemetri**:
   - `IngestSimulatedTelemetryBatchAction` memantau setiap tick telemetri; jika `health_score < 400` atau `battery_pct < 15%`, sistem otomatis membuka `RobotSafetyIncident` dengan tingkat keparahan `high`/`medium`.
   - `SafetyIncidentLog::record()` bersifat idempoten berdasarkan `incident_code` dan append-only (`RobotSafetyIncident` menolak `update`/`delete` selain transisi review terotorisasi).

## 3. Matriks Kode Interlock & Tindakan Operator
| Kode Interlock | Kondisi Pemicu | Efek pada Misi | Tindakan Operator |
|---|---|---|---|
| `STAFF_ZONE_BLOCKED` | `staff_blocked = true` dan interval bertumpuk | Transisi ke `moving` ditolak; misi tetap `assigned` atau `failed` | Selesaikan pekerjaan staf, lalu buka blokir zona di `/wms/robotics/zones` |
| `ZONE_CAPACITY_FULL` | Okupansi interval mencapai `capacity_k` | Slot digeser ke waktu bebas berikutnya oleh `J1` | Pantau peta kepadatan di `/wms/robotics/simulations` |
| `LOW_BATTERY_INTERLOCK` | `battery_pct < 15%` saat akan memasuki zona | Penolakan masuk zona; pembukaan insiden telemetri | Jalankan `robotics:charge-plan` & arahkan unit ke `BAY-01..04` |
| `DEADLOCK_VICTIM_PREEMPTED` | Siklus tunggu terdeteksi oleh `TrafficDeadlockDetector` | Misi prioritas terendah diputus ke `failed`, reservasi dilepas | Biarkan retry otomatis (maks 2) atau tangani via `fallback_manual` |

## 4. Prosedur Penghentian Darurat & Fallback Manual
1. Jika staf harus segera memasuki lorong robot tanpa menunggu siklus selesai:
   - Aktifkan `staff_blocked = true` pada `TrafficZone` terkait melalui `/wms/robotics/zones`.
   - Jalankan `php artisan robotics:reservation-sweep` untuk membersihkan reservasi kedaluwarsa.
2. Untuk misi yang terhenti atau gagal melebihi `MAX_RETRY_ATTEMPTS = 2`:
   - `RetryOrFallbackRobotMissionAction::fallbackAndReplan()` memindahkan misi ke `fallback_manual`.
   - `WarehouseTaskCompletionBridge::createManualFallbackTask()` menerbitkan tugas picking manual `MAN-<mission_code>` di `wms_tasks` dan mengirim notifikasi ke `TaskInbox` untuk peran `hub_operator`.
   - `PlanRoboticsWaveAction` menyusun ulang wave sisa agar seluruh order pelanggan tetap terjaga 100% (`RoboticsWaveReconciliationService::reconcileBatch()`).

## 5. Jejak Audit Keselamatan (Append-Only)
Seluruh rekaman peristiwa misi (`wms_robot_mission_events`), telemetri (`wms_robot_telemetries`), dan insiden keselamatan (`wms_robot_safety_incidents`) dilindungi oleh guard model Eloquent yang menolak mutasi atau penghapusan historis. Auditor dapat mengekspor seluruh insiden melalui `/wms/robotics/export/incidents` dan memverifikasi integritasnya melalui `php artisan robotics:audit`.
