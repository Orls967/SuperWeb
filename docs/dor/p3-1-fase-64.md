# Definition of Ready (DoR) — Fase 64: AI Deterministik & Anti-Fraud (P3.1)

#### DoR Fase 64 (Item 64.2–64.4) — Deterministic AI & Anomaly Quarantine Vertical Slice
- **Disetujui pemilik:** Internal Gate G6-P3 (Pelaksana Perdagangan & Keuangan)
- **Tujuan bisnis (1–2 kalimat):** Mengoperasikan mesin inferensi AI deterministik yang 100% dapat direproduksi byte-per-byte via snapshot input kanonik dan seed tetap, mendeteksi anomali transaksi menggunakan skor-z termodifikasi (`ModifiedZScore`) serta aturan split-bill/BBM/agen untuk menahan settlement perbankan sebelum settlement terjadi dengan rilis four-eyes (`risk_manager` $\ne$ pengaju), memberikan usulan preskriptif safety stock & ROP berbasis `SimpleExponentialSmoothing` berfaktor kalender/libur melalui approval `planner_manager`, serta diaudit tanpa selisih oleh `ai:audit`.
- **Kutipan Dokumen Rujukan:**
  - `docs/KONSEP.md` §7F (baris 882–888): Dependensi AI bidding pada infrastruktur inferensi deterministik Fase 64.
  - `docs/KONSEP.md` §A1 (baris 20–105): Registrasi modul Core (`core_`) dan Shared domain.
  - `docs/KONSEP.md` §A2 & §A3 (baris 108–165): Larangan float untuk kuantitas dan uang, penggunaan `BigRational` eksak dan HalfUp rounding.
  - `docs/KONSEP.md` §A4 (baris 181–210): Idempotency key berformat `{prefiks}:{aksi}:{id_bisnis}` tanpa nilai acak/waktu.
  - `docs/KONSEP.md` §A7: Penggunaan `ClockInterface` untuk waktu bisnis.
  - `docs/PROGRESS.md` Fase 64 item 64.2, 64.3, 64.4 (baris 7330–7360).
- **Modul pemilik & prefiks:**
  - Modul Utama: `Core` (`core_`) — HANYA namespace AI (`Application/Services/Ai/**`, `Domain/Models/Ai*/**`, `Http/Controllers/Ai/**`, `Console/Commands/AuditAiCommand.php`, migrasi `core_ai_*`).
  - Modul Aditif: `Shared` (`modules/Shared/Domain/Algorithms/Stats/ModifiedZScore.php`, `modules/Shared/Domain/Algorithms/Forecast/SimpleExponentialSmoothing.php`).
- **Prasyarat & status:**
  - Master run `d319bacf` hijau.
  - Baseline arch:scan bersih (0 baru, 0 basi).
- **Item yang dikerjakan sesi ini:**
  - D64-1: Registry model AI (`core_ai_models`) dan jejak inferensi append-only (`core_ai_inferences`) dengan snapshot input kanonik (JSON terurut + sha256), seed, versi model, output kanonik, dan replay identik byte-per-byte.
  - D64-2: Algoritma deteksi anomali `ModifiedZScore` ($z = 0,6745 \times (x - \text{median}) / \text{MAD}$; jika $\text{MAD} = 0$, pakai $\text{MeanAD} \times 1,253314$; jika keduanya 0, setiap $x \ne \text{median}$ adalah anomali; ambang $|z| > 3,5$) dilengkapi aturan multi-fitur (split bill terminal dalam jendela waktu, deviasi BBM, pola agen). Transaksi anomali berstatus `QUARANTINED` menahan settlement, dan rilis memerlukan approval four-eyes (`risk_manager` $\ne$ pengaju) secara idempoten.
  - D64-3: Rekomendasi stok preskriptif menggunakan `SimpleExponentialSmoothing` ($\alpha = 3.000$ bps) dikali faktor musim/libur (bps berversi) menghasilkan usulan safety stock dan ROP integer, dengan persetujuan manusia oleh `planner_manager`.
  - D64-4: Command `ai:audit` memverifikasi replay inferensi deterministik dan konsistensi karantina/rekomendasi, gagal pada fixture korupsi dengan menyebut ID rekaman yang rusak.
- **Rute & role:**
  - `/ai/anomalies` (`risk_officer`, `admin`)
  - `/ai/quarantine` (`risk_officer`, `admin`, `risk_manager`)
  - `/ai/quarantine/{quarantine}/release` (`risk_manager`, `admin`)
  - `/ai/stock-recommendations` (`planner`, `admin`)
  - `/ai/stock-recommendations/{recommendation}/approve` (`planner_manager`, `admin`)
- **Item yang ditunda (Register Minus):**
  - Nihil untuk deliverable inti P3.1.

