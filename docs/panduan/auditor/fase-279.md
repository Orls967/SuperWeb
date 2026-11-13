# Panduan Pengguna (`auditor`) — Fase 279: Audit Robotika, Simulasi & ROI

## 1. Peran & Ruang Lingkup Akses Auditor
Sebagai **Auditor**, Anda memiliki akses baca-saja terhadap seluruh jejak audit robotika, proposal ROI otomasi, hasil simulasi sandbox, dan ekspor CSV untuk pemeriksaan kepatuhan:
- `/wms/robotics/fleet` & `/wms/robotics/missions` — Memantau rekam jejak armada dan transisi state machine (`wms_robot_mission_events` bersifat append-only).
- `/wms/robotics/roi` — Memeriksa perhitungan `NPV`, `IRR (bps)`, dan alasan tertulis untuk proposal ber-NPV negatif.
- `/wms/robotics/simulations` — Memeriksa laporan simulasi T1, kepadatan zona (`ZoneCongestionHeatmapCalculator`), dan bukti `hash_identical = true`.
- `/wms/robotics/incidents` — Mengaudit log insiden keselamatan (`wms_robot_safety_incidents`).
- `/wms/robotics/export/{entity}` — Mengunduh data mentah CSV (`robots`, `missions`, `waves`, `roi`, `incidents`).

*Catatan*: Auditor tidak diperkenankan mengubah data operasional zona (`/wms/robotics/zones`) maupun menyetujui proposal ROI.

## 2. Verifikasi Audit Dua Sumber (`robotics:audit`)
1. Jalankan command:
   ```bash
   php artisan robotics:audit
   ```
2. Command ini membandingkan konsistensi dua sumber secara deterministik:
   - Misi berstatus aktif/selesai tidak boleh kehilangan referensi robot/zona yang valid.
   - Tidak boleh ada dua reservasi zona yang melebihi `capacity_k` pada interval yang bertumpukan.
   - Tidak boleh ada reservasi aktif di zona yang bertanda `staff_blocked = true`.
   - Tidak boleh ada proposal ROI `approved` dengan `created_by_user_id == approved_by_user_id` atau NPV negatif tanpa `justification`.
3. Apabila seluruh pemeriksaan bersih, command keluar dengan kode `0` dan mencetak `state_hash` SHA-256. Jika ada anomali, command keluar dengan kode non-0 dan merinci entitas bermasalah.

## 3. Verifikasi Kelayakan ROI & Pemisahan Tugas (SoD)
- Periksa pada `/wms/robotics/roi` bahwa setiap proposal berstatus `approved` memiliki `approved_by_user_id != created_by_user_id`.
- Untuk setiap proposal dengan `npv_minor < 0`, pastikan kolom `justification` terisi jelas dan tercatat di `ApprovalEngine` (`approval_requests`).

