# Matriks Kode Galat & Pengecualian Domain — Fase 279

- **Fase**: 279 (W8-P6.3)
- **Tujuan**: Referensi cepat seluruh exception dan kode status HTTP pada modul `Wms\Robotics`.

## 1. Daftar Pengecualian Domain & Aplikasi
| Kelas Exception | Kode / Pesan Kunci | Sumber | Arti & Solusi |
|---|---|---|---|
| `RobotMissionTransitionException` | `STAFF_ZONE_BLOCKED` | `ReserveZoneForMissionAction` / `RoboticsZoneInterlockEvaluator` | Zona diblokir staf pada interval `[start, end)`. Tunggu blokir dibuka |
| `RobotMissionTransitionException` | `ZONE_CAPACITY_FULL` | `RoboticsZoneInterlockEvaluator` | Okupansi zona mencapai `capacity_k`. Jadwalkan via `ZoneReservationScheduler` |
| `RobotMissionTransitionException` | `LOW_BATTERY_INTERLOCK` | `RoboticsZoneInterlockEvaluator` | Baterai `< 15%`. Arahkan unit ke `BAY-01..04` via `robotics:charge-plan` |
| `RobotMissionTransitionException` | `MAX_RETRY_EXCEEDED` | `RobotMissionStateMachine` | `retry_count >= 2`. Gunakan `fallbackAndReplan()` ke `fallback_manual` |
| `RobotMissionTransitionException` | Transisi status tidak sah | `RobotMissionStateMachine::assertCanTransition()` | Pasangan `(from, to)` tidak ada di peta transisi SM 8 status |
| `InvalidArgumentException` | SoD ROI dilanggar | `EvaluateRoboticsRoiProposalAction` | `created_by_user_id === approverUserId`. Gunakan `admin` berbeda |
| `InvalidArgumentException` | NPV negatif tanpa alasan | `EvaluateRoboticsRoiProposalAction` | Isi `justification` non-kosong sebelum menyetujui proposal `npv_minor < 0` |
| `InvalidArgumentException` | Sertifikat rusak / hash gagal | `*Certificate::verifyOrFail()` / `verify()` | Payload hasil algoritma dimanipulasi atau tidak konsisten dengan hash SHA-256 |
| `InvalidArgumentException` | Biaya Hungarian tak layak | `HungarianSolver::solve()` | Pastikan dummy pad cost `< HungarianSolver::INF (1_000_000_000)` |
| `LogicException` | Append-only dilanggar | `RobotMissionEvent`, `RobotTelemetry`, `RobotSafetyIncident` | Tabel log immutable menolak `update()` / `delete()` |
| `RuntimeException` | Hash produksi berubah | `RoboticsSimulateCommand` / `RoboticsSimulationScenarioRunner` | Kebocoran mutasi saat mode sandbox; hentikan simulasi dan audit koneksi DB |

## 2. Kode Status HTTP Control Tower (`/wms/robotics/*`)
| HTTP Status | Penyebab Umum | Pemeriksaan |
|---|---|---|
| `200 OK` | Permintaan valid dengan peran berhak | Normal |
| `302 Redirect` | Aksi `POST` (`waves/plan`, `roi/{id}/approve`, `incidents/{id}/review`) sukses atau validasi form gagal | Periksa session flash `status` / `errors` |
| `403 Forbidden` | Peran tidak ada di middleware `role:...` atau ditolak `RobotMissionPolicy` (anti-IDOR) | Verifikasi peran user & `assigned_operator_user_id` |
| `404 Not Found` | ID proposal/insiden atau entity ekspor CSV tidak ditemukan | Pastikan ID valid & entity termasuk `robots|missions|waves|roi|incidents` |

