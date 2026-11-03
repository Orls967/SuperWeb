# Definition of Ready (DoR) — Fase 80: Cold-Chain Blockchain Autonomous, Drone & Last-Mile Robotics (P1.3)

#### DoR Fase 80 — Cold-Chain & Drone Robotics Vertical Slice
- **Disetujui pemilik:** Internal Gate G6-P1 (Pelaksana Pengembangan Vertical Slice)
- **Tujuan bisnis (1–2 kalimat):** Mengoperasikan penegakan rantai dingin (cold chain) autonomous dengan telemetri suhu reefer, penahanan otomatis pembayaran utang carrier (hold) saat breach > 10 menit ke ledger `lgx:carrier_payable_on_hold:IDR`, serta dispatch drone last-mile otonom berbasis visibility graph Dijkstra yang menghindari no-fly zone bersertifikat dan geohash POD digital.
- **Kutipan Dokumen Rujukan:**
  - `docs/KONSEP.md` §5F Slice 2–3: Rantai dingin autonomous, telemetri IoT reefer, event breach suhu, hold carrier payable ke ledger, rilis tahanan setelah investigasi, drone last-mile routing berbasis visibility graph, koordinat integer micro-degree, sertifikat POD geohash.
  - `docs/KONSEP.md` §A1: Registrasi modul Logistics (`lgx_`) dan Banking/Ledger (`led_`).
  - `docs/KONSEP.md` §A2: Aturan pembukuan berpasangan double-entry: `ap:carrier:{id}:IDR` <-> `lgx:carrier_payable_on_hold:IDR`.
  - `docs/KONSEP.md` §A3: Presisi integer eksak (suhu 0,1 °C integer, berat gram integer, jarak meter integer, koordinat micro-degree).
  - `docs/KONSEP.md` §A4: Idempotensi hold per episode dan rilis tahanan.
  - `docs/PROGRESS.md` Fase 80 (baris 8550–8645): Deliverable 80.1–80.8.
- **Modul pemilik & prefiks:**
  - Modul Utama: `Logistics` (`lgx_`)
  - Modul Aditif: `Shared` (`Modules\Shared\Domain\Algorithms\Geo`), `Banking` (akun utang on hold)
- **Prasyarat & status:**
  - Trunk master hijau; PR P1.1 berjalan di CI; modul Logistics dasar beroperasi.
- **Item yang dikerjakan sesi ini:**
  - D80-1: Profil suhu per komoditas reefer (min/max 0,1 °C integer); durasi breach = t_akhir − t_mulai; breach = durasi > 10 menit (ketat, 10 menit pas = tidak hold).
  - D80-2: Satu hold per (leg, episode); idempoten; custody hash-chain pada jendela pembacaan.
  - D80-3: Posting akuntansi debit `ap:carrier:{id}:IDR` kredit `lgx:carrier_payable_on_hold:IDR` saat breach, dan kebalikannya saat rilis penuh.
  - D80-4: Drone dispatch: berat ≤ 5.000 g, radius ≤ 15.000 m, jangkauan baterai memadai, visibility graph router + Dijkstra yang menghindari interior no-fly zone (panjang 10.246 m pada benchmark), dan geohash POD presisi 9 (`qqguygv1t`).
  - D80-5: Audit dua sumber `lgx:audit-coldchain` dan fixture korupsi data.
- **Item yang ditunda (Register Minus):**
  - M-80-1: Hasil sengketa parsial/pemotongan denda selain rilis penuh ditunda ke fase konsiliasi klaim asuransi terpadu.
