# ADR-0053: Batasan Zona Fase, Larangan Sentuh Kode Lama, dan Eksekusi P7 (G7-D53)

## Status
Diterima (Accepted) — 2026-11-08

## Konteks
Pekerjaan gelombang G7 melibatkan banyak pilar yang bekerja secara simultan (misalnya Hospital di P8, Hotel di P6, Venue di P5). Diperlukan batas zona kepemilikan yang tegas agar tidak terjadi benturan kode, pelanggaran kontrak, atau regresi pada modul yang telah stabil.

## Keputusan
1. **Fase yang Dikerjakan Pelaksana P7:**
   - Fase 177: Aviation Core (Lini 26, modul baru `avi_`).
   - Fase 178: Airline Network, Yield, Codeshare & IRROPS (Lini 26).
   - Fase 180: Ocean Fleet, Ship Management & Marine Services (Lini 27, tabel `prt_fleet_*`).
   - Fase 171: Aquaculture Export, Seafood Traceability & Blue ESG (Lini 22, modul `Marine`).
2. **Fase yang Dilarang Dikerjakan:**
   - Fase 179 (Operasi Terminal Pelabuhan): sudah mendarat di master via G6-P1. Tabel `prt_berths`, `prt_vessel_calls`, `prt_gate_passes`, dll. tidak boleh diubah atau ditulis ulang.
   - Fase 170 (Budidaya Tambak Hulu): sudah mendarat di master. Tabel `mar_ponds`, `mar_cycles`, `mar_harvests`, dll. hanya diperdalam tanpa merusak perilaku lama atau pembayaran panen petani.
   - Fase 172 (Kehutanan): dikerjakan oleh tim kehutanan G6-P5.2.
3. **Kode Kerangka Lama `Modules\Integration`:**
   - `AviationService`, `AirlineNetworkService`, `OceanFleetService`, dan `AquacultureExportService` diperlakukan sebagai referensi read-only lama. Kode tersebut tidak boleh diedit, dihapus, atau disalin. Seluruh implementasi ditulis ulang sebagai vertical slice mandiri di modul pemilik baru.
4. **Isolasi Zona Lain:**
   - Dilarang menyentuh `modules/Hospital/**` (zona worktree P8).
   - Dilarang mengubah kode produksi modul `Hotel`, `Venue`, `Mining`, `Logistics`, `.github`. Integrasi hanya melalui kontrak antarmuka yang sah.

## Konsekuensi
Menjaga keutuhan arsitektur modular dan mencegah regresi pada pipeline pengujian lintas modul.

