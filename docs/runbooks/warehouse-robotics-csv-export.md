# Panduan Ekspor Data CSV Control Tower — Fase 279

- **Endpoint**: `GET /wms/robotics/export/{entity}` (`wms.robotics.export`)
- **Service**: `Modules\Wms\Application\Services\RoboticsCsvExportService`
- **Peran Berhak**: `admin`, `auditor`

## 1. Daftar Entitas Ekspor yang Didukung
1. `robots` — Mengunduh seluruh data armada (`wms_robots`) terurut berdasarkan `robot_code`.
2. `missions` — Mengunduh seluruh misi (`wms_robot_missions`) termasuk status, `retry_count`, dan flag `fallback_manual`.
3. `waves` — Mengunduh seluruh batch wave (`wms_robot_waves`) beserta `carrier_code`, `cutoff_hhmm`, dan `certificate_hash`.
4. `roi` — Mengunduh seluruh proposal investasi (`wms_robotics_roi_proposals`) dalam satuan minor integer (`capex_minor`, `npv_minor`, `irr_bps`).
5. `incidents` — Mengunduh log insiden keselamatan (`wms_robot_safety_incidents`) beserta status review.

## 2. Keamanan & Format Keluaran
- Content-Type: `text/csv; charset=UTF-8` dengan header `Content-Disposition: attachment; filename="robotics-{entity}.csv"`.
- Nilai uang tidak diformat sebagai float, melainkan integer minor unit agar bebas dari kehilangan presisi saat dibuka di alat audit eksternal.
- Permintaan untuk `{entity}` di luar daftar di atas ditolak dengan `404 Not Found`.
