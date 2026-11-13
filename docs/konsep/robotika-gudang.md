# Konsep Robotika Gudang (`Wms\Robotics` — Fase 279)

Fase 279 memindahkan kerangka robotika dari modul `Integration` menjadi vertical slice penuh di dalam modul `Wms`:
- **Orkestrasi Armada**: Manajemen unit AMR/conveyor (`wms_robot_units`), misi 8-status (`wms_robot_missions`), dan kunci zona eksklusif (`wms_robot_zone_locks`).
- **Safety Interlock**: Saat manusia terdeteksi di zona aktif, batas kecepatan diturunkan ke `300 mm/s` dan misi aktif dialihkan ke status `interlocked`.
- **Fallback Manual**: Ketika robot mengalami gangguan atau deadlock, misi dapat langsung dialihkan ke operator manusia (`failed_fallback`).
- **Non-Ledger**: Tidak ada kejadian perpindahan uang pada siklus misi robotik; nilai persediaan tetap dicatat melalui transaksi stok WMS (Fase 41).

