# Definition of Ready (DoR) — Lini 27: Pelabuhan & Terminal (modul Port prt_, Fase 179, P1.4)

#### DoR Fase 179 — Ports & Marine Terminals Vertical Slice
- **Disetujui pemilik:** Internal Gate G6-P1 (Pelaksana Pengembangan Vertical Slice)
- **Tujuan bisnis (1–2 kalimat):** Mengoperasikan modul terminal pelabuhan laut modern (`Port`, prefiks `prt_`) yang menangani kunjungan kapal (ETA/ATA/ATD), alokasi tambatan optimal (Berth Allocation branch & bound dan heuristik bersertifikat), penataan lapangan kontainer append-only (yard blocks/slots), gate in/out idempoten, dan penagihan terminal (THC, dockage, storage, D&D lewat kontrak K5) yang terhubung ke buku besar.
- **Kutipan Dokumen Rujukan:**
  - `docs/KONSEP.md` Lini 27 (baris 2100–2180): Pelabuhan & Terminal peti kemas, master kapal tetap di Logistics (kontrak K4), D&D via K5.
  - `docs/KONSEP.md` §A1: Registrasi modul Port (`prt_`).
  - `docs/KONSEP.md` §A2: Aturan pembukuan berpasangan pendapatan terminal dan piutang shipping line: `ar:prt:line:{party}:IDR`, `rev:prt:thc:IDR`, `rev:prt:dockage:IDR`, `rev:prt:storage:IDR`, `prt:dnd_revenue:IDR`.
  - `docs/PROGRESS.md` Fase 179 (baris 11627–11638): Deliverable 179.1–179.6.
- **Modul pemilik & prefiks:**
  - Modul Utama: `Port` (`prt_`)
  - Modul Terintegrasi: `Logistics` (`lgx_`) via Kontrak K4 (`VesselRegistry`) dan K5 (`DemurrageDetentionQuote`), `Banking` (Ledger)
- **Prasyarat & status:**
  - Master kapal dan kontainer di Logistics (K4/K5) aktif; modul Port disetujui (G6-K1).
- **Item yang dikerjakan sesi ini:**
  - D27-1: Modul Port `prt_` dengan 6 tabel: `prt_berths`, `prt_vessel_calls`, `prt_yard_blocks`, `prt_yard_slots`, `prt_gate_passes`, `prt_terminal_bills`. Master kapal & kontainer tetap di Logistics via K4. Tabel kapal kedua DILARANG.
  - D27-2: Invarian kontainer per terminal per hari: masuk = keluar + di lapangan. Dihitung append-only dari fakta pergerakan.
  - D27-3: Alokasi tambatan diskrit `Scheduling\BerthAllocation` meminimalkan weighted waiting time dengan tie-break leksikografis terkecil dan sertifikat validitas (vektor prompt 5 kapal pada 2 tambatan menghasilkan objektif 14).
  - D27-4: Tagihan terminal: THC per gerakan, dermaga per GT-jam, penumpukan yard per hari, D&D via K5.
  - D27-5: Audit dua sumber `prt:audit` (reconcile yard harian + tagihan vs ledger).
- **Item yang ditunda (Register Minus):**
  - M-179-1: Integrasi sensor IoT crane otomatis ditunda ke Fase Pelabuhan Cerdas (Port 4.0).
