# Katalog Galat Robotika Gudang (Fase 279)

| Kode / Pesan | Penyebab | Penanganan |
| :--- | :--- | :--- |
| `Baterai robot terlalu rendah (<15%)` | Unit AMR mencoba dispatch di bawah ambang baterai aman | Jalankan `RechargeRobotUnitAction` hingga baterai pulih |
| `Zona ... sedang dikunci oleh robot lain` | Terdeteksi konflik kunci zona eksklusif | Tunggu pelepasan lock atau periksa `TrafficDeadlockDetector` |
| `Zona ... sedang dalam safety interlock manusia` | `human_present = true` pada `wms_robot_zone_locks` | Pastikan zona bebas manusia lalu cabut interlock |
| `Transisi misi robot tidak valid` | Pelanggaran graf transisi 8 status `RobotMissionStateMachine` | Periksa status saat ini sebelum memicu fallback/dispatch |

