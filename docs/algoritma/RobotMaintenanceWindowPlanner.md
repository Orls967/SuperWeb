# Algoritma — `RobotMaintenanceWindowPlanner`

- **Kelas**: `Modules\Wms\Domain\Algorithms\RobotMaintenanceWindowPlanner`
- **Uji Vektor**: `tests/Acceptance/WarehouseRobotics/RoboticsAlgorithmsVectorTest.php`

## 1. Batas Konkurensi Pemeliharaan Armada
Agar operasi gudang tidak lumpuh akibat terlalu banyak robot ditarik untuk pemeliharaan preventif secara bersamaan, `RobotMaintenanceWindowPlanner` membatasi jumlah robot yang sedang dalam jendela maintenance pada titik waktu manapun maksimal `MAX_CONCURRENT_MAINTENANCE_PCT = 15%` dari `totalFleetSize` (`max(1, intdiv(totalFleetSize * 15, 100))`).

## 2. Prioritas Kesehatan Terendah
1. Urutkan kandidat berdasarkan `health_score` menaik (unit paling sakit ditangani lebih dulu), lalu `robot_id` menaik.
2. Periksa overlap `IntervalWindow` terhadap kandidat yang sudah terjadwal.
3. Jika jumlah overlap `< maxConcurrent`, jadwalkan unit tersebut; jika sudah mencapai batas, masukkan ke `deferred_robot_ids`.

## 3. Vektor Uji
Armada 10 robot (`maxConcurrent = 1`), 2 kandidat bertumpuk (`health_score` `500` dan `650`) -> robot `500` terjadwal, robot `650` ditunda.
