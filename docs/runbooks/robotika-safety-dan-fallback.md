# Runbook: Safety Interlock & Fallback Manual Robotika

1. **Deteksi Manusia di Zona Aktif**:
   - Panggil `POST /wms/robotics/interlock` dengan `human_present=1`.
   - Sistem menurunkan `speed_limit_mm_s` menjadi `300` dan memindahkan misi aktif ke `interlocked`.
2. **Pemulihan atau Fallback Manual**:
   - Jika robot gagal pulih, operator menjalankan `POST /wms/robotics/missions/{mission}/fallback`.
   - Kunci zona dilepas (`lock_status=released`), robot ditandai `faulted`, dan misi ditugaskan ke operator.
3. **Verifikasi Pasca-Insiden**:
   - Jalankan `php artisan wms:robotics-audit` dan `php artisan wms:audit` untuk memastikan 0 dual active lock.

