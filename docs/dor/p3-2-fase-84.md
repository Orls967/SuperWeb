# Definition of Ready (DoR) — Fase 84: Asisten Penawaran Tender (P3.2)

#### DoR Fase 84 (Item 84.1–84.8) — AI Contract Bidding Agent & Tender Assistant Vertical Slice
- **Disetujui pemilik:** Internal Gate G6-P3 (Pelaksana Perdagangan & Keuangan)
- **Tujuan bisnis (1–2 kalimat):** Mengoperasikan asisten penawaran lelang tender deterministik yang mengoptimalkan harga penawaran menggunakan algoritma `ExpectedProfitBidOptimizer` berbasis riwayat lelang kompetitor dan batas margin entitas, menyusun rekomendasi draf klausul komersial dinamis dari modul Contract via kontrak K13 `ClauseTemplateReader`, mewajibkan persetujuan manusia four-eyes (`procurement_manager` $\ne$ pengusul) sebelum penawaran dapat diajukan ke lelang pengadaan, mencatat komitmen anggaran akrual pasca-menang, serta diaudit dengan 0 selisih oleh `bidding:audit`.
- **Kutipan Dokumen Rujukan:**
  - `docs/KONSEP.md` §7B (baris 862): Agen AI deterministik merayapi data biaya dan histori lelang untuk menyusun harga optimal + klausul kontrak dengan approval staf manusia sebelum submit.
  - `docs/KONSEP.md` §7D (baris 873): AI Bid Desk, ringkasan penawaran diajukan, win rate, margin vs benchmark, dan audit trail persetujuan manusia.
  - `docs/KONSEP.md` §7F Slice 3 (baris 888, 897): Asisten penawaran tender, rute `/procurement/bid-assistant` (`procurement`; approval `procurement_manager`), command `bidding:audit`. Usulan AI tanpa approval dilarang submit.
  - `docs/KONSEP.md` §A1 (baris 20–105): Prefiks modul Trade (`trd_`), Procurement (`prc_`), Contract (`ctr_`), dan Shared.
  - `docs/KONSEP.md` §A2 & §A3 (baris 108–165): Larangan float kuantitas & uang; perhitungan diskrit integer IDR dan pecahan eksak `BigRational` dengan rounding HalfUp.
  - `docs/KONSEP.md` §A4 (baris 181–210): Idempotency key deterministik berformat `{prefiks}:{aksi}:{id_bisnis}` tanpa fungsi acak (`uniqid`, `Str::random`, `now()`).
  - `docs/KONSEP.md` §A10 (Katalog Kontrak G6): K13 `ClauseTemplateReader` (P3 pemilik) di modul Contract untuk draf klausul AI tender.
  - `docs/PROGRESS.md` Fase 84 item 84.1–84.8 (baris 8870–8940).
- **Modul pemilik & prefiks:**
  - Modul Utama: `Trade` (`trd_`), `Procurement` (`prc_`).
  - Modul Aditif: `Contract` (`ctr_`) untuk K13 `ClauseTemplateReader`, `Shared` untuk `ExpectedProfitBidOptimizer`.
- **Prasyarat & status:**
  - PR [G6-P3.1] (#58) siap landing.
  - Algoritma `ExpectedProfitBidOptimizer` lolos uji deterministik `ExpectedProfitBidOptimizerTest`.
- **Item yang dikerjakan sesi ini:**
  - D84-1: Optimasi harga penawaran lelang deterministik dengan algoritma `ExpectedProfitBidOptimizer` (grid search diskrit step IDR, probabilitas empirical win rate, penegakan ambang peluang menang $\ge 30\%$, batas harga bawah/atas agen).
  - D84-2: Integrasi kontrak K13 `ClauseTemplateReader` di modul Contract untuk merangkai draf klausul komersial standar lelang (termin pembayaran, incoterm, sanksi keterlambatan, dispute counter) yang siap direview.
  - D84-3: Penegakan kontrol four-eyes mandatori: submit penawaran diblokir sistem tanpa persetujuan approver (`procurement_manager` atau `admin` yang berbeda dari user pembuat run).
  - D84-4: Audit dua sumber `bidding:audit` yang memvalidasi integritas replay evaluasi tender dari snapshot, ketiadaan penawaran tanpa four-eyes, kepatuhan batas harga, dan pencatatan komitmen anggaran kontrak.
- **Rute & role:**
  - `/procurement/bid-assistant` (`procurement`, `admin`, `auditor`, `treasury`, `procurement_manager`)
  - `/trade/bidding` (`admin`, `auditor`, `procurement`, `treasury`)
  - `/procurement/bid-assistant/submissions/{submission}/approve` (`procurement_manager`, `admin`)
- **Item yang ditunda (Register Minus):**
  - Nihil untuk deliverable inti P3.2.

