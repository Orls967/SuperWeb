# Panduan Troubleshooting CLI & Diagnostik — Robotika Gudang (Fase 279)

- **Fase**: 279 (W8-P6.3)
- **Komponen**: `modules/Wms/Console/Commands/Robotics*.php`, `WmsRoboticsAuditCliReportFormatter`

## 1. Membaca Output `robotics:audit` (`WmsRoboticsAuditCliReportFormatter`)
Ketika `php artisan robotics:audit` mendeteksi inkonsistensi dua sumber, output diformat sebagai berikut:
```text
robotics:audit [GAGAL] issues=1 state_hash=<16-hex>
  [1] <detail_isu_dan_kode_entitas>
```
### Arti Isu Umum
- `Misi aktif ... tertaut ke robot offline`: Ada misi `assigned`/`moving`/`picking`/`delivering` pada robot dengan `is_online = false`. Solusi: alihkan misi ke `failed` atau `fallback_manual`.
- `Misi ... merujuk robot/zona tidak valid` (misal `MIS-CORRUPT-01`): Referensi FK/logis terputus. Solusi: bersihkan data yatim pada tabel misi atau perbaiki master zona/robot.
- `Zona ... melebihi kapasitas k`: Dua atau lebih `ZoneReservation` `active` bertumpuk melebihi `capacity_k`. Solusi: jalankan `php artisan robotics:reservation-sweep`.
- `Proposal ROI ... disetujui oleh pembuatnya sendiri` / `NPV negatif tanpa alasan`: Pelanggaran tata kelola `J3`. Solusi: batalkan status persetujuan dan ulangi persetujuan oleh `admin` berbeda dengan alasan tertulis.

## 2. Diagnostik Koneksi & Migrasi SQLite/PostgreSQL
- Jika muncul `SQLSTATE[HY000]: no such table: wms_robots` saat menjalankan command CLI di lingkungan lokal:
  ```bash
  php artisan migrate --force
  php artisan db:seed --class="Modules\\Wms\\database\\seeders\\RoboticsT1Seeder"
  ```
- Jika muncul `FOREIGN KEY constraint failed` saat pengujian `RobotUnit` atau `Robot`: pastikan `Warehouse` dibuat terlebih dahulu dan atribut yang diisi adalah `'kind'` (bukan `'type'`).

## 3. Diagnostik `OutboxBusInterface` & `TaskInboxService`
- `OutboxBusService` menggunakan metode `record($eventType, $payload, $idempotencyKey)`, bukan `publish()`.
- `TaskInboxService::createTask()` mewajibkan kunci `source_entity_type` dan `source_entity_id` (beserta `assignee_role` agar muncul di kotak masuk peran terkait).

## 4. Diagnostik `HungarianSolver`
- Nilai `HungarianSolver::INF` adalah `1_000_000_000`.
- Sel padding dummy pada matriks persegi `dim x dim` wajib bernilai strictly `< INF` (pada `AllocateRobotMissionsAction` dan `RobotFleetDispatchOptimizer` digunakan `500_000_000`). Jika sel dummy bernilai `>= INF`, solver akan melempar `InvalidArgumentException: Masalah penugasan tidak layak`.

