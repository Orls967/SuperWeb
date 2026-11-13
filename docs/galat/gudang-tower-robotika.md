# Katalog Galat & Penanganan — Robotika Gudang (Fase 279)

| Kode / Exception | Lokasi Sumber | Penyebab | Tindakan Pemulihan |
|---|---|---|---|
| `STAFF_ZONE_BLOCKED` (`RobotMissionTransitionException`) | `ReserveZoneForMissionAction` / `RoboticsZoneInterlockEvaluator` | Misi mencoba masuk ke zona yang berstatus `staff_blocked = true` pada interval bertumpuk | Tunggu hingga blokir staf selesai, atau jadwalkan ulang ke zona alternatif |
| `ZONE_CAPACITY_FULL` (`RobotMissionTransitionException`) | `RoboticsZoneInterlockEvaluator` | Jumlah reservasi aktif `[start, end)` sudah mencapai `capacity_k` | Gunakan `ZoneReservationScheduler` untuk mencari slot waktu berikutnya |
| `LOW_BATTERY_INTERLOCK` (`RobotMissionTransitionException`) | `AllocateRobotMissionsAction` / `RoboticsZoneInterlockEvaluator` | Baterai robot di bawah ambang minimum (`< 20%` saat alokasi, `< 15%` saat masuk zona) | Jalankan `robotics:charge-plan` agar robot masuk slot charging |
| `MAX_RETRY_EXCEEDED` (`RobotMissionTransitionException`) | `RobotMissionStateMachine` / `RetryOrFallbackRobotMissionAction` | Percobaan retry melebihi `MAX_RETRY_ATTEMPTS = 2` | Alihkan misi ke `fallback_manual`; operator mengeksekusi tugas di `wms_tasks` |
| `DEADLOCK_VICTIM_PREEMPTED` | `ResolveTrafficDeadlockAction` / `TrafficDeadlockDetector` | Siklus graf tunggu antar-misi terdeteksi di zona berkapasitas sempit | Victim berprioritas terendah diputus sementara dan diantrekan ulang |
| `InvalidArgumentException` (SoD ROI) | `EvaluateRoboticsRoiProposalAction::approveProposal()` | `created_by_user_id === approverUserId` (pembuat menyetujui sendiri) | Minta `admin` lain yang berbeda untuk melakukan persetujuan |
| `InvalidArgumentException` (NPV Negatif) | `EvaluateRoboticsRoiProposalAction::approveProposal()` | Proposal ROI dengan `npv_minor < 0` disetujui tanpa `justification` | Isi alasan bisnis strategis pada form persetujuan ROI |
| `InvalidArgumentException` (Sertifikat Rusak) | `*Certificate::verifyOrFail()` / `RobotEnergyCertificate::verify()` | Payload hasil algoritma tidak cocok dengan hash SHA-256 atau melanggar invarian sertifikat | Jangan simpan hasil; periksa kemungkinan mutasi data tengah proses |
| `RuntimeException` (Sandbox Mutated) | `RoboticsSimulateCommand` / `RoboticsSimulationScenarioRunner` | Hash tabel produksi berubah setelah simulasi mode sandbox | Segera hentikan job simulasi dan audit mutasi database |
| `LogicException` (Append-Only Violation) | `RobotMissionEvent`, `RobotTelemetry`, `RobotSafetyIncident` | Percobaan `update()` atau `delete()` pada tabel log immutable | Gunakan entri kompensasi/koreksi baru; jangan ubah rekaman historis |
| `HTTP 403 Forbidden` (IDOR / RBAC) | `RobotMissionPolicy` / Middleware `role` | Operator mencoba fallback misi milik operator lain atau role tidak berhak mengakses rute | Gunakan akun operator tertaut atau eskalasi ke `admin` gudang |
