# Panduan Operasional Robotika Gudang

## Rute Web
- `/wms/robotics/fleet`: Pantau status armada AMR dan kunci zona/interlock aktif.
- `/wms/robotics/missions`: Pantau antrean misi 8-status dan alihkan ke fallback manual.
- `/wms/robotics/roi`: Tinjau rencana ROI dan payback periode automasi per gudang.

## Perintah Artisan
- `php artisan wms:robotics-roi-plan {warehouse_id} {capex_idr} {annual_savings_idr}`
- `php artisan wms:robotics-dispatch {warehouse_id} {robot_unit_id} {zone_code}`
- `php artisan wms:robotics-audit` dan `php artisan wms:audit`

