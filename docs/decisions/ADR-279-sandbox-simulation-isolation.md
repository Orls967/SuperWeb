# ADR-279-C: Isolasi Sandbox P3 & Hash Integritas Produksi pada Simulasi Robotika

- **Status**: Diterima (2026-11-13)
- **Komponen**: `RoboticsSimulationTickHandler`, `RoboticsSimulationScenarioRunner`, `RoboticsSimulateCommand`, `RoboticsTwoSourceAuditService::computeProductionStateHash()`

## 1. Konteks
Control Tower Robotika membutuhkan kemampuan menjalankan simulasi skenario kepadatan lalu lintas dan skenario kejutan (`two_robots_offline_one_zone_blocked`) sebelum shift dimulai. Risiko terbesar dari simulasi di dalam codebase produksi yang sama adalah kebocoran mutasi (misalnya mengubah `battery_pct`, membuat `ZoneReservation` palsu, atau memicu `OutboxMessage` nyata ke produksi).

## 2. Penggerak Keputusan
1. **Zero Production Side-Effects**: Simulasi tidak boleh meninggalkan satu pun baris baru, perubahan kolom, atau penghapusan pada tabel operasional `wms_robots`, `wms_traffic_zones`, `wms_zone_reservations`, `wms_robot_missions`, `wms_robot_waves`, maupun `wms_robotics_roi_proposals`.
2. **Verifikasi Kriptografis Otomatis**: Bukti isolasi tidak boleh hanya berupa klaim; sistem harus membuktikannya secara terprogram pada setiap eksekusi.
3. **Determinisme Seed**: Simulasi dengan `--seed` yang sama harus menghasilkan `fingerprint` identik di `SimulationRunnerService`.

## 3. Keputusan Arsitektur
1. **Integrasi `SimulationRunnerService` Mode `'sandbox'`**:
   - `RoboticsSimulateCommand` memulai sesi lewat `$runner->start("robotics-{$scenario}", $days, $seed, 'sandbox')`.
   - `RoboticsSimulationTickHandler` mengimplementasikan `SimTickHandlerInterface` dan melakukan perhitungan alokasi/slot murni di memori tanpa menulis ke tabel `wms_*`.
2. **Penjagaan Hash Pra- & Pasca-Simulasi**:
   - Sebelum simulasi berjalan, `RoboticsTwoSourceAuditService::computeProductionStateHash()` menghitung SHA-256 agregat atas seluruh tabel inti robotika.
   - Setelah simulasi selesai, hash dihitung ulang; jika `$hashBefore !== $hashAfter`, sistem melempar `RuntimeException` dan menggagalkan command/test.

## 4. Konsekuensi
- **Positif**: Operator dan auditor dapat menguji skenario kejutan kapan saja di staging maupun produksi dengan jaminan `hash_identik=true`.
- **Negatif**: Penambahan tabel inti baru di masa depan mengharuskan pembaruan daftar tabel di `computeProductionStateHash()` agar cakupan hash tetap menyeluruh.
