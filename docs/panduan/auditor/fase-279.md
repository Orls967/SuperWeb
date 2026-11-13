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


## 4. Referensi Kolom Ekspor CSV (`GET /wms/robotics/export/{entity}`)
| Entity (`{entity}`) | Sumber Tabel | Kolom Utama yang Diekspor | Kegunaan Audit |
|---|---|---|---|
| `robots` | `wms_robots` | `id`, `warehouse_id`, `robot_code`, `is_online`, `battery_pct`, `health_score`, `position_x`, `position_y` | Verifikasi rasio armada online & kesehatan unit |
| `missions` | `wms_robot_missions` | `id`, `mission_code`, `warehouse_id`, `robot_id`, `zone_code`, `status`, `priority`, `retry_count`, `fallback_manual` | Rekonsiliasi distribusi 8 status & batas retry <= 2 |
| `waves` | `wms_robot_waves` | `id`, `warehouse_id`, `batch_code`, `carrier_code`, `cutoff_hhmm`, `capacity`, `total_load`, `certificate_hash` | Verifikasi konservasi order `J2` & sertifikat SHA-256 |
| `roi` | `wms_robotics_roi_proposals` | `id`, `proposal_code`, `status`, `capex_minor`, `npv_minor`, `irr_bps`, `payback_months`, `created_by_user_id`, `approved_by_user_id`, `justification` | Pemeriksaan SoD & alasan NPV negatif `J3` |
| `incidents` | `wms_robot_safety_incidents` | `id`, `incident_code`, `warehouse_id`, `severity`, `incident_type`, `status`, `reviewed_by_user_id`, `recorded_at` | Audit jejak keselamatan append-only |

## 5. Daftar Periksa Kepatuhan Auditor Sebelum Penutupan Periode
1. Jalankan `php artisan robotics:audit` dan simpan `state_hash` SHA-256 sebagai bukti integritas periode.
2. Pastikan seluruh proposal ROI berstatus `approved` memenuhi `created_by_user_id !== approved_by_user_id`.
3. Pastikan setiap proposal ROI dengan `npv_minor < 0` memiliki `justification` yang tercatat di kedua tabel (`wms_robotics_roi_proposals` dan `approval_requests`).
4. Pastikan tidak ada selisih order antara `wms_robot_waves` dan `wms_tasks` untuk misi yang beralih ke `fallback_manual`.
5. Pastikan hasil simulasi `robotics:simulate` mencantumkan `hash_identik=true`.
