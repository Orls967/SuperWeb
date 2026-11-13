# Panduan Pengguna (`hub_operator`) — Fase 279: Control Tower Robotika Gudang

## 1. Peran & Ruang Lingkup Akses
Sebagai **Hub Operator**, Anda bertanggung jawab memantau pergerakan armada AMR/AGV di lantai gudang, mengelola blokir zona saat staf memasuki lorong robot, menindaklanjuti misi gagal (`fallback_manual`), serta meninjau jadwal pengisian daya dan insiden keselamatan.

Rute yang dapat Anda akses:
- `/wms/robotics/fleet` — Status armada, baterai, dan skor kesehatan robot.
- `/wms/robotics/missions` — Papan misi (`queued` s.d. `completed`, `failed`, `fallback_manual`).
- `/wms/robotics/zones` — Peta okupansi zona lalu lintas & status `staff_blocked`.
- `/wms/robotics/waves` — Perencanaan wave pemenuhan order (`POST /wms/robotics/waves/plan`).
- `/wms/robotics/charging` — Jadwal slot charger armada.
- `/wms/robotics/incidents` — Tindak lanjut insiden keselamatan (`POST /wms/robotics/incidents/{id}/review`).

*Catatan*: Halaman `/wms/robotics/roi` (persetujuan CAPEX) dan `/wms/robotics/simulations` (sandbox P3) hanya untuk peran `admin` / `auditor` / `procurement`.

## 2. Prosedur Harian Operator Gudang
### A. Memantau Alokasi & Antrean Misi
1. Buka `/wms/robotics/missions` untuk melihat distribusi 8 status misi.
2. Misi dengan status `failed` dan `retry_count < 2` dapat dijadwalkan ulang ke `queued`.
3. Jika robot mengalami kerusakan berulang (`retry_count >= 2`), sistem otomatis mengalihkan misi ke `fallback_manual` dan menerbitkan tugas picking manual di `TaskInbox` Anda (`MAN-<mission_code>`).
4. **Aturan Anti-IDOR**: Anda hanya dapat mengeksekusi fallback pada misi yang ditugaskan kepada Anda atau misi tanpa operator tertaut.

### B. Interlock Zona Staf (Keselamatan Kerja)
1. Sebelum staf masuk ke lorong kapasitas 1 (`capacity_k = 1`), pastikan zona tersebut bertanda `staff_blocked = true`.
2. Sistem otomatis menolak alokasi/reservasi baru ke zona yang diblokir staf (`STAFF_ZONE_BLOCKED`).
3. Jika zona berbagi ruang (`capacity_k >= 2`), sistem membatasi kecepatan rekomendasi maksimum `800 mm/s` (`MAX_COEXISTENCE_SPEED_MM_S`).

### C. Menangani Wave & Fallback Order
1. Saat menyusun wave baru di `/wms/robotics/waves`, masukkan `warehouse_id`, `capacity`, dan `batch_code`.
2. Sistem mengelompokkan order secara otomatis berdasarkan `cutoff` kurir agar tidak ada order yang tertinggal saat fallback manual terjadi.
