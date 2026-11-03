# Definition of Ready (DoR) — Fase 81 & Item 64.1: Dynamic Surge Pricing ber-Guardrail (P2.1)

#### DoR Fase 81 & Item 64.1 — Dynamic Surge Pricing Vertical Slice
- **Disetujui pemilik:** Internal Gate G6-P2 (Pelaksana Pengembangan Vertical Slice)
- **Tujuan bisnis (1–2 kalimat):** Mengoperasikan mesin penetapan harga dinamis deterministik berbasis aturan S1 (stok cover, rasio permintaan, umur simpan produk perishable, musim, dan okupansi) yang dilindungi batas langkah (step limit ±1.000 bps) dan guardrail keras [floor, min(ceiling, HET)], di mana harga yang melanggar batas ditolak dan dicatat di `pric_tick_rejections`, harga order dikunci secara immutable di dokumen via `PriceLocker` (kedaluwarsa 15 menit), proyeksi elastisitas permintaan (item 64.1) dilaporkan, dan diaudit tanpa selisih oleh `pricing:audit`.
- **Kutipan Dokumen Rujukan:**
  - `docs/KONSEP.md` §6A–§6F (baris 832–870): Spesifikasi implementasi minimum Slice 1 (Surge pricing ber-guardrail), rute `/pricing/surge`, `/pricing/rejections`, peran `pricing_manager`, command `pricing:tick`, `pricing:audit`, audit dua sumber, skala T1 (1.000 SKU, 10.000 tick).
  - `docs/KONSEP.md` §A1 (baris 100–105): Registrasi modul Pricing (`pric_`), Procurement (`prc_`), Store (`store_`).
  - `docs/KONSEP.md` §A2 (baris 108–165): Aturan buku besar, larangan float untuk harga (integer rupiah), audit dua sumber.
  - `docs/KONSEP.md` §A3 (baris 151–158): Satuan integer eksak (rupiah integer, basis poin integer, pembulatan HalfUp).
  - `docs/KONSEP.md` §A4 (baris 181–210): Idempotency key berformat `{prefiks}:{aksi}:{id_bisnis}` tanpa nilai acak/waktu.
  - `docs/KONSEP.md` §A7: Penggunaan `ClockInterface` untuk waktu bisnis.
  - `docs/PROGRESS.md` Fase 81 (baris 8643–8730) & Fase 64 item 64.1 (baris 7320–7325).
- **Modul pemilik & prefiks:**
  - Modul Utama: `Pricing` (`pric_`)
  - Modul Terkait: `Store` (`store_`), `Core` (`core_`)
- **Prasyarat & status:**
  - Master run `fbbac91d` hijau.
  - Baseline arch:scan bersih (0 baru, 0 basi).
- **Item yang dikerjakan sesi ini:**
  - D81-1: Verifikasi tabel prefiks `pric_` (`pric_price_ticks`, `pric_frozen_quotes`, `pric_price_tick_aggregates`), penambahan tabel `pric_tick_rejections` dan parameter SKU.
  - D81-2: Mesin kalkulasi tick S1 deterministik dengan faktor bps (stok, permintaan, umur simpan, musim, okupansi), pembulatan HalfUp rupiah dan HalfUp kelipatan langkah harga (Rp 50), batas langkah ±1.000 bps (`dibatasi`), guardrail keras [floor, min(ceiling, HET)] (penolakan dicatat di `pric_tick_rejections`), serta vektor uji 1–5.
  - D81-3: Proyeksi dampak elastisitas harga $q' = q \times (10.000 - e \times \Delta p_{bps} / 10.000) / 10.000$ untuk memenuhi item 64.1 tanpa mengubah tick produksi.
  - D81-4: `PriceLocker` mengunci harga dokumen secara immutable saat order; order lama tidak terpengaruh tick baru; quote kedaluwarsa (> 15 menit) saat checkout menghasilkan HTTP 409 + harga baru.
  - D81-5: Command `pricing:tick` terjadwal idempoten per (SKU, slot 5 menit); command `pricing:audit` menguji order line = harga terkunci, tick mematuhi guardrail, dan setiap penolakan tercatat; rute `/pricing/surge` dan `/pricing/rejections` dengan role `pricing_manager`.
- **Item yang ditunda (Register Minus):**
  - Nihil untuk deliverable inti P2.1.

