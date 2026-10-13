# KONSEP.md — Cetak Biru Ekspansi Lini Bisnis Superweb (8 Pilar + 22 Lini)

> **Status dokumen (re-baseline 10 Oktober 2026):** dokumen ini adalah **spesifikasi tujuan** (visi + aturan implementasi), bukan checklist. Status implementasi nyata ada di baris "Status audit" setiap fase pada `docs/PROGRESS.md`; hasil audit lengkapnya di `docs/KNOWLEDGE.md`.
> **Ringkasan status nyata:** fondasi & rantai nilai Fase 0–46 ✅ · Fase 47–63 🟡 · Fase 64–66 ⬜ · seluruh pilar ekspansi (Fase 67–150) dan lini 18–30 (Fase 151–484) 🟠 *kerangka* — kode domain ada, tetapi tanpa rute/UI/command, akun ledger hanya dibuat di test, dan klaim skala/integrasi belum terpenuhi.
> **Posisi terhadap PROGRESS.md:** setiap pilar/lini dipetakan ke fase di PROGRESS (tabel pemetaan di bawah). Sebelum mengerjakan fase mana pun, selesaikan **FASE R (remediasi)** di PROGRESS.
> **Sifat proyek:** sistem *dummy* monolitik terpadu (modular monolith; semua lini terhubung lewat Contract / Domain Event / Ledger / PaymentGateway / Outbox). Angka skala di bagian pilar adalah **visi (tier T3)**; kriteria selesai memakai tier T0–T2 (§A9).
> **Prinsip yang diwarisi:** uang integer minor-unit tanpa float; double-entry Σ=0 **dan** saldo normal per jenis akun (§A2.1); hash-chain append-only; idempotensi key deterministik (§A2.5); test (a)–(e) yang bermakna (§A8); quality gate penuh + `*:audit` dua-sumber = 0 selisih (§A5); komunikasi antar-modul hanya via Contract/Event (§A4).
> **Cara pakai:** baca **Bagian A** (A0–A17: aturan teknis, detektor otomatis, template kode normatif) → bagian pilar/lini yang akan dikerjakan, terutama subbagian **"xF. Spesifikasi implementasi minimum"** beserta **"Jalan pintas terlarang"** dan **"Bukti selesai minimum"** → item fase di PROGRESS beserta baris status & kriteria terima wajibnya. Untuk menjalankan agent, pakai template prompt pelaksana & verifikator di `PROGRESS.md` §P13.

---

## PETA RINGKAS 8 PILAR

| # | Pilar | Modul Inti yang Sudah Ada | Skala Ekspansi yang Dituju |
|---|-------|---------------------------|----------------------------|
| 1 | Otomotif & Pembiayaan Kendaraan | AutoDex, AutoServe, Store, core_vehicles, HODL-to-Drive, Logistics car carrier | 10 juta kendaraan terdaftar, 1 juta sesi bengkel/hari, 500 ribu titik EV |
| 2 | FinTech, Perbankan & Kripto | Core Banking, Payment Hub, Crypto, Trade Finance, Treasury | 5 juta dompet, 100 ribu produk tokenisasi, likuiditas harian triliunan simulasi |
| 3 | Kuliner, Restoran & Waralaba | Resto (HPP, POS, dapur sentral, katering, royalti), Agri, Logistics | 5.000 outlet, 2 juta transaksi/hari, 1 juta unit vending |
| 4 | Properti Komersial & EPC | Mall (leasing, billing, parkir, footfall, facility), EPC (WBS, MC, CIP) | 200 properti, 50 ribu unit sewa, digital twin 10 ribu lantai |
| 5 | Logistik Multimoda, SCM & Gudang | Logistics, WMS, S&OP Control Tower | 200 ribu+ shipment → 5 juta shipment/tahun, 100 ribu armada |
| 6 | Manufaktur, Distribusi & Harga | Manufacturing (BOM, MRP, costing), Distribution, Pricing Engine | 100 pabrik, 1 juta SPK/tahun, harga berdetik untuk 1 juta SKU |
| 7 | Perdagangan Internasional & Pengadaan | Trade Ops, Trade Finance, Procurement, Tender | 10 ribu koridor dagang, 50 ribu L/C/tahun, clearing lintas benua |
| 8 | Tata Kelola, Korporasi & Integrasi | HCM, RBAC, Approval Engine, Party, Contract, Group Consolidation | 500 ribu talenta, tata kelola desentralisasi 1 juta hak suara |

**Status implementasi per pilar (audit 10 Okt 2026):**

| # | Modul inti yang sudah ✅ | Fitur ekspansi pilar (Fase) | Status ekspansi |
|---|---|---|---|
| 1 | AutoServe, AutoDex, Store, Finance, Vehicle Passport | Telematics, EV, Fleet (68–70) | 🟠 |
| 2 | Banking, Payment, Crypto, Finance | RWA, InsurTech, Robo-Advisor (71–73); Treasury 🟡 | 🟠 |
| 3 | Resto | Cloud kitchen, forecast/auto-PO, smart vending (74–75) | 🟠 |
| 4 | Mall, Asset | PropTech, BIM/digital twin, flex-space (76–78); EPC 🟡 | 🟠 |
| 5 | Logistics | Reverse logistics, cold-chain hold, drone (79–80); WMS/Control Tower 🟡 | 🟠 |
| 6 | Manufacturing, Distribution, Pricing, Procurement | Surge pricing, VMI, C2M (81–82) | 🟠 |
| 7 | Procurement | Clearing house, CBAM, AI bidding (83–84); Trade/Trade Finance 🟡 | 🟠 |
| 8 | Party, Contract, RBAC, Approval | Gig economy, NDVI, DAO (85–86); HCM 🟠 (Fase 58 dikerjakan ulang) | 🟠 |

---

## KONSEP PENGEMBANGAN BERSAMA (BERLAKU UNTUK SEMUA PILAR)

Sebelum per pilar, empat konsep lintas pilar ini adalah *enabler* wajib:

1. **Simulation Kernel** — lapisan orkestrasi waktu: `--sim-days=N` menjalankan seluruh modul maju N hari kompresi (event time bukan wall clock), sehingga penyusutan aset, jatuh tempo kontrak, siklus S&OP, dan expiry poin bisa disimulasikan bertahun-tahun dalam hitungan menit.
2. **Universal Event Spine** — satu tulang punggung event domain (memperluas `core_outbox`) dengan topik per pilar, schema registry ber-versi, dan replay dari offset tertentu: setiap pilar bisa "menyaksikan" kejadian pilar lain tanpa coupling.
3. **Digital Twin Bus** — setiap entitas bernilai tinggi (kendaraan, gedung, kontainer, pabrik, petak lahan) memiliki bayangan state yang dapat disimulasikan what-if tanpa menyentuh ledger riil.
4. **Fictional Scale Provisioner** — seeder deterministik per pilar yang menghasilkan dataset raksasa idempoten (memperluas pola `EnterpriseUniverseSeeder`), dengan checkpoint/resume dan benchmark per etape.

Spesifikasi teknis keempat enabler ini (kontrak antarmuka, perilaku, kriteria uji) ada di **§A10**.

Setiap pilar di bawah didefinisikan dengan 6 bagian: **Skala** (visi T3), **Operasional**, **Cakupan**, **Hasil (Output)**, **Ide Pengembangan Lanjutan**, dan **xF. Spesifikasi implementasi minimum** (ditambahkan 10 Okt 2026 — modul & prefiks, status audit, slice MVP, posting ledger, rute & role, command, audit dua-sumber, test kunci, tier skala).

---

# BAGIAN A — SPESIFIKASI TEKNIS LINTAS PILAR (WAJIB DIBACA SEBELUM MENGERJAKAN PILAR MANA PUN)

> Bagian ini ditambahkan pada re-baseline 10 Oktober 2026. Isinya adalah aturan implementasi yang **mengikat semua pilar/lini**. Bagian per pilar (1A–12E) menggambarkan *visi*; subbagian **"xF. Spesifikasi implementasi minimum"** di tiap pilar menurunkan visi itu menjadi potongan kerja yang bisa diverifikasi. Bila ada konflik, **Bagian A menang**.

## A0. Cara membaca & istilah

| Istilah | Definisi |
|---|---|
| **KONSEP / PROGRESS / KNOWLEDGE** | KONSEP = spesifikasi tujuan (apa & bagaimana seharusnya). PROGRESS = rencana eksekusi + status per item + bukti. KNOWLEDGE = hasil audit (apa yang sebenarnya ada). |
| **Vertical slice** | Potongan fitur yang lengkap dari DB sampai layar: migrasi → model → action → rute/controller → view/menu → role → akun ledger + seeder → command (bila periodik) → audit → test HTTP (a)–(e). Hanya vertical slice yang boleh dicentang. |
| **Modul pemilik** | Satu-satunya modul yang boleh membuat & menulis tabel dengan prefiks tertentu (registry §A1). Modul lain membaca lewat Contract/Query milik pemilik. |
| **Simulasi** | Proyek ini mensimulasikan bisnis nyata. Setiap aturan diberi tingkat: **S1** aturan disederhanakan & dinyatakan eksplisit (mis. "PPh 21 = tarif tetap 5% — simulasi S1"); **S2** aturan mengikuti regulasi dengan parameter di config (mis. tarif TER, PTKP); **S3** integrasi pihak luar lewat adapter tiruan berstatus `simulated_*`. Tingkat wajib ditulis di item PROGRESS & komentar kelas. Simulasi **tidak pernah** melonggarkan invarian internal — uang, otorisasi, idempotensi, enkripsi data internal, dan audit tetap harus benar (larangan X22). |
| **Tier skala T0–T3** | Lihat §A9. Angka "jutaan" di bagian pilar = T3 (visi), **bukan** syarat centang. |
| **Status ✅/🟡/🟠/⬜** | Sama dengan legenda di `PROGRESS.md`. |

## A1. Registry modul & prefiks tabel

Aturan: (1) satu prefiks = satu modul pemilik; (2) `Integration` hanya memiliki `intg_` (adapter eksternal: webhook, EDI, API gateway); (3) tabel baru wajib memakai prefiks modul yang menulisnya; (4) registry ini dijadikan file konfigurasi (`config/modules.php`) dan ditegakkan arch test (PROGRESS R4.2).

| Modul | Prefiks resmi | Domain | Status audit | Catatan / perbaikan yang diputuskan |
|---|---|---|---|---|
| Shared | — | kernel: `Money`, `BaseAction`, `MenuRegistry` | ✅ | tidak boleh bergantung ke modul bisnis (sekarang `GlobalSearchQuery` mengimpor Mall → R4.3) |
| Core | `core_`, `sim_` | platform: kendaraan & passport, RBAC, audit trail, outbox/event spine, numbering, document store, approval, sim clock | ✅ (enabler 🟠) | tabel `platform_*` & `roles/permissions/role_*/user_*` dipertahankan; `TwelveLinesCrossEcosystemService` tidak boleh mengimpor Hotel (R4.3) |
| Banking | `bank_` | ledger, dompet, PIN | ✅ | `TransactionType`/`AccountKind`/DTO posting dipindah ke shared kernel ledger agar boleh diimpor semua modul |
| Payment | `pay_` | PaymentGateway, intent, refund | ✅ | tambah `pay_refunds` (R1.6) |
| Inventory | `inv_` | stok & movement | ✅ (perlu R2.4) | menjadi pemilik tunggal saldo stok berdimensi lokasi |
| Store | `store_` | katalog, cart, order, C2C | ✅ | |
| AutoServe / AutoDex | `serve_` (+ tabel lama `bookings`, `spareparts`, `services`) / `dex_` (+ `cars`, `brands`, `garages`, `wishlists`) | bengkel / ensiklopedia | ✅ | |
| Crypto / Finance | `crypto_` / `fin_` | bursa simulasi / HODL-to-Drive | ✅ | `fin_` dipakai 20 tabel buatan Integration → pindahkan |
| Resto | `resto_` | restoran | ✅ | CloudKitchen memakai `resto_` → ganti `ckt_` |
| CloudKitchen | `ckt_` (baru) | cloud kitchen & katering langganan | 🟠 | |
| Vending | `vnd_` (baru) | smart vending | 🟠 | sekarang `ven_` bentrok dengan Venue |
| Mall | `mall_` | properti komersial | ✅ | |
| Proptech | `prp_` | smart building, BIM, flex-space | 🟠 | |
| Epc | `epc_` | konstruksi | 🟡 | |
| Logistics | `lgx_` | logistik multimoda | ✅ | |
| Wms / ControlTower | `wms_` / `sct_` | gudang / S&OP | 🟡 | |
| Party / Contract / Asset | `pty_` / `ctr_` / `ast_` | pihak, kontrak, aset | ✅ | |
| Supplier / Procurement | `sup_` / `prc_` | pemasok / pengadaan | ✅ | `prc_` milik Procurement saja |
| Manufacturing / Distribution | `mfg_` / `dist_` | pabrik / distribusi | ✅ | |
| Pricing | `pric_` | harga, promo, surge | ✅ | 2 tabel `prc_frozen_quotes`, `prc_price_ticks` → `pric_` |
| Agency / Partner | `agy_` / `ptn_` | agensi / mitra | ✅ / 🟡 | |
| Treasury / Trade / TradeFinance | `trs_` / `trd_` / `tf_` | treasury / ekspor-impor / L/C | 🟡 | |
| International / Intercompany / EnterpriseFinance | `intl_` / `ic_` / `ef_` | JV / konsolidasi / anggaran grup | 🟡 | |
| Hcm | `hcm_` | SDM & payroll | 🟠 | `gov_bounties*` → `hcm_gig_*`; 30 tabel `hcm_*` buatan Integration → pindahkan |
| Governance (baru, dari Agri+Hcm) | `gov_` | DAO, voting, proposal | 🟠 | sekarang `gov_` ditulis Agri, Hcm, dan Integration (50) |
| Plm / Esg / B2b / Agri | `plm_` / `esg_` / `b2b_` / `agri_` | R&D / ESG / B2B / hulu pertanian | 🟡 | `esg_` 36 tabel buatan Integration → pindahkan ke Esg |
| Mobility (gabungan Telematics, Ev, Fleet) | `oto_` | telematik, EV, armada sewa | 🟠 | tiga modul kecil memakai prefiks yang sama → gabung menjadi satu modul pemilik `oto_` (catat di DECISIONS) |
| Rwa / Insurance / Wealth | `rwa_` / `ins_` / `wm_` | tokenisasi / asuransi / robo-advisor | 🟠 | `ins_` 13 tabel buatan Integration → pindahkan ke Insurance |
| Hospital / Venue / Hotel / Mining | `hsp_` / `ven_` / `htl_` / `min_` | lini 9–12 | 🟠 | |
| Egy / Tlx / Med / Edu / Ret | `egy_` / `tlx_` / `med_` / `edu_` / `ret_` | lini 13–17 | 🟠 | |
| Integration | `intg_` | adapter eksternal | 🟠 | 857 tabel lain (`int_`, `global_`, `platform_`, `ops_`, `crm_`, `ai_`, …) diklasifikasi & dipindah ke modul pemilik (R4.4) |
| Lini 18–30 | lihat §"Spesifikasi minimum lini 18–30" | | 🟠 | prefiks final diputuskan bersama penyelesaian irisan |

## A2. Ledger & uang

**A2.1 Konvensi tanda tunggal.** Kredit = **+**, debit = **−** (konvensi modul lama yang sudah benar). Gunakan helper `Posting::lines()->debit($akun, $nominal)->credit($akun, $nominal)` — tanda **tidak pernah** ditulis manual.

| `AccountKind` | Sisi normal | Saldo normal | Contoh kode akun |
|---|---|---|---|
| `revenue`, `fee` | kredit | ≥ 0 | `hsp:trial_research_revenue:IDR`, `lgx:freight_revenue` |
| `liability`, `ap`, `deposit`, `escrow`, `wallet`, `points`, `contra_asset` | kredit | ≥ 0 | `ap:supplier:{id}:IDR`, `escrow:payment:IDR`, `wallet:user:{id}:IDR` |
| `collateral` | kredit (titipan peminjam) | ≥ 0 | `escrow:finance:collateral:BTC` |
| `asset`, `cash`, `inventory`, `loan_receivable`, piutang `ar:*` | debit | ≤ 0 | `ast:fixed_assets:IDR`, `ar:hsp:patient:{id}:IDR` |
| `expense` | debit | ≤ 0 | `expense:resto:cogs:IDR` |
| `clearing`, `exchange` | — | bebas; wajib kembali 0 per periode | `clearing:external:IDR` |

Test `LedgerNormalBalanceTest` menegakkan tabel ini setelah seluruh skenario (PROGRESS R1.1).

**A2.2 Penamaan kode akun.** `{prefiks}:{nama_akun}[:{subjek}:{id}]:{ASET}` — huruf kecil, `snake_case`, aset huruf besar. Contoh: `htl:room_revenue:IDR`, `ar:htl:folio:{folio_id}:IDR`, `tf:escrow:{lc_id}:USDT`. Akun per-subjek (per pihak/folio/kontrak) dibuat *on demand* oleh helper modul (`HotelAccounts::folioReceivable($folio)`), bukan oleh test.

**A2.3 Chart of accounts sebagai kode.** Setiap modul yang memposting punya `modules/{M}/Ledger/{M}Accounts.php` (konstanta + definisi kind/aset/allow_negative/sisi normal) dan didaftarkan ke `LedgerAccountsSeeder`. Test memanggil seeder yang sama. Pola acuan yang sudah ada: `AssetService::ensureAccounts()`, `AgencyService::ensureAccounts()`.

**A2.4 Tipe transaksi.** `type` wajib berasal dari registry (`TransactionType` atau enum per modul yang didaftarkan), `snake_case` huruf kecil, ≤ 32 karakter (batas kolom `bank_ledger_transactions.type`), berawalan prefiks modul: `hsp_trial_milestone`, `min_royalty_accrual`.

**A2.5 Idempotency key.** Format `{prefiks}:{aksi}:{id_bisnis}[:{sub}]`, contoh `lgx:revenue:{shipment_id}`, `fleet:amort:{contract_id}:m{bulan}`, `refund:{refund_id}`, `resto:order:cogs:{order_id}:{item_id}`. Dilarang `Str::uuid()`, `Str::random()`, `now()`, `uniqid()` di key. Key sama + payload beda = galat (`IdempotencyConflictException`). State bisnis (kolom `posted_at`/`ledger_tx_id`) ikut dijaga agar pemanggilan ulang tidak menggeser angka di tabel modul.

**A2.6 Tipe data uang & pembulatan.**
- IDR: `bigInteger` rupiah penuh. Persentase/tarif: integer **basis poin** (`ppn_bps = 1100`) atau string desimal yang dikonversi ke BigDecimal — **tidak pernah float**.
- Mata uang lain: integer minor unit (sen) + kurs tersimpan (Treasury: integer skala 1e6).
- Kripto/token: `DECIMAL(36,18)` **hanya** di MySQL/PostgreSQL (eksak), atau integer unit dasar (satoshi/wei). SQLite menyimpan `decimal` sebagai NUMERIC ±15 digit → tidak boleh dipakai untuk membuktikan presisi kripto (PROGRESS R1.9).
- Pembulatan: Brick Math `RoundingMode::HalfUp` di level baris; PPN 11% (config), PB1 Resto 10% dibulatkan ke Rp100; sisa pembulatan diserap baris terakhir (pola refund PaymentGateway).
- `Money`/`PostingEntryDTO` tidak menerima `float`.

**A2.7 Pola uang yang sudah baku (pakai ulang, jangan ditulis ulang):**

| Kebutuhan | Gunakan | Catatan |
|---|---|---|
| Pembayaran langsung dari dompet | `PaymentGateway::charge()` + `Payable::revenueSplits()` | Σsplit wajib = nominal |
| Deposit/jaminan/escrow | `PaymentGateway::hold()` → `capture()` / `release()` | sisa dikembalikan dalam transaksi capture |
| Refund | `PaymentGateway::refund()` dengan id permintaan refund | (R1.6) |
| Piutang/utang & pengakuan pendapatan | `Ledger::post()` dengan akun dari `{M}Accounts` | pola `RecognizeFreightRevenueAction` |
| Escrow multi-pihak B2B/trade | akun `escrow:{prefiks}:{kontrak}:{ASET}` + release bertahap ber-key | bukan kolom angka di tabel modul |

**A2.8 Rekonsiliasi.** Setiap subledger modul (tagihan, AP, AR, escrow, payroll, royalti) punya audit yang membandingkan tabel modul dengan posting ledger (§A5). `bank:reconcile` tetap memeriksa Σ=0 per aset dan cache saldo.

## A3. State machine, approval, dokumen

- **State machine:** enum per entitas (`{Entitas}Status`) dengan `transitions()` dan `assertCanTransitionTo()`; transisi hanya di Action, di dalam transaksi, setelah `lockForUpdate`. State terminal/pembalik (void, cancel, terminate, refund) wajib alasan tertulis dan tercatat di riwayat.
- **Approval:** semua persetujuan lewat `ApprovalEngineInterface` (four-eyes, multi-level, delegasi). Ambang per modul di config (`config/{modul}.php` → `approval_threshold_idr`). Service menerima **id approval**, bukan `bool $approved`.
- **Penomoran dokumen:** dokumen bisnis (invoice, PO, kontrak, sertifikat, folio, tiket, klaim, polis) memakai `DocumentNumberingInterface` (gapless per entitas/tahun). `Str::random()` untuk nomor dokumen dilarang.
- **Hash-chain:** hanya untuk dokumen bernilai bukti (passport kendaraan/pasien, versi kontrak, custody logistik, ECO, sertifikat, weighbridge, tiket). Format `prev_hash`/`hash` SHA-256 atas payload kanonik + command `verify-*` + test manipulasi.
- **Dokumen fisik/lampiran:** `DocumentStoreInterface` (checksum, mime guard, retensi).

## A4. Event & integrasi

**A4.1 Penamaan & payload.** Nama event `{domain}.{entitas}.{kejadian}.v{n}` (mis. `resto.order.paid.v1`); kelas Laravel `{Entitas}{Kejadian}` di `Domain/Events`. Payload minimum: `event_id` (uuid), `occurred_at` (dari `ClockInterface`), `aggregate_type`, `aggregate_id`, `idempotency_key`, `schema_version`, `data`. Event dipancarkan `afterCommit`; untuk konsumen lintas modul yang asinkron, event juga ditulis ke outbox/spine dalam transaksi yang sama.

**A4.2 Katalog event minimum** (setiap baris wajib punya produsen **dan** konsumen nyata sebelum klaim integrasi boleh dicentang):

| Event | Produsen | Konsumen | Tujuan |
|---|---|---|---|
| `store.order.paid.v1` | Store | Logistics (buat shipment), Manufacturing (HPP), Pricing (price lock) | sudah ada sebagai `OrderPaid` |
| `lgx.shipment.delivered.v1` | Logistics | Logistics (pengakuan pendapatan), Core (passport bila kendaraan), Insurance (penutupan polis kargo) | sebagian ada |
| `lgx.temperature.breached.v1` | Logistics | Payment (hold bayaran carrier), Insurance (klaim) | Pilar 5 |
| `auto.telematics.anomaly_detected.v1` | Mobility | AutoServe (draf booking), Logistics (grounded unit) | Pilar 1 |
| `auto.ev.session_completed.v1` | Mobility | Core (SoH ke passport), ESG (emisi/kWh) | Pilar 1 |
| `mall.footfall.recorded.v1` | Mall | Resto/CloudKitchen (forecast) | Pilar 3 |
| `prop.meter.read.v1` / `egy.meter.read.v1` | Proptech / Egy | Mall (tagihan utilitas), ESG (Scope 2) | Pilar 4, Lini 13 |
| `epc.project.handed_over.v1` | Epc | Asset (kapitalisasi) | Pilar 4 |
| `hcm.payroll.posted.v1` | Hcm | CloudKitchen (potongan katering), Wealth (alokasi) | Pilar 3, 2 |
| `hsp.episode.discharged.v1` | Hospital | Payment (settlement), Insurance/BPJS (klaim) | Lini 9 |
| `ven.event.closed.v1` | Venue | Venue (pengakuan pendapatan tiket), Media (royalti) | Lini 10 |
| `htl.night.audited.v1` | Hotel | Hotel (posting room revenue), Rwa (dividen unit) | Lini 11 |
| `min.weighbridge.recorded.v1` | Mining | Logistics (custody), Mining (royalti) | Lini 12 |
| `esg.emission.recorded.v1` | Esg | TradeFinance (CBAM), Egy | Pilar 7 |
| `gov.proposal.passed.v1` | Governance | Contract/Epc (draf proyek) | Pilar 8 |

**A4.3 Outbox/spine.** Status per subscriber, retry hanya yang gagal, klaim pesan atomik, handler internal terdaftar (bukan ditandai sukses), allowlist host untuk webhook, dead-letter + replay (PROGRESS R2.5, R8).

**A4.4 Contract antar-modul.** Untuk kebutuhan sinkron (baca data/perintah langsung) gunakan interface di `modules/{Pemilik}/Contracts`. Contract yang sudah ada: `Ledger`, `VerifiesWalletPin`, `PaymentGateway`, `Payable`, `InventoryService`, `ShipmentBooking`, `FleetMaintenanceBooking`, `RateCardOverrideResolver`, `LoyaltyLedger`, `ParkingValidator`, `TenantSalesProvider`, `PriceLocker`, `MrpRequisitionProposer`, `ReferenceCostUpdater`, `PriceFeed`, dan kontrak platform di Core. Kontrak baru wajib didaftarkan di `CODEBASE.md`.

**A4.5 Integrasi eksternal (simulasi S3).** Setiap pihak luar (bank, BPJS, OTA, satelit, bea cukai, bursa karbon, payment network) diakses lewat interface adapter; implementasi tiruan mengembalikan status `simulated_*` dan bisa dipaksa gagal untuk test. Klien HTTP nyata: timeout, retry dengan backoff, idempotency header, allowlist host.

## A5. Audit & observabilitas

- **Nama:** `{prefiks atau nama domain}:audit` (satu per modul pemegang uang/stok/dokumen bernilai; nama yang sudah tertulis di bagian D pilar/lini dipakai apa adanya, mis. `hosp:audit`, `auto:audit`) + `verify-*` untuk hash-chain. Signature command wajib unik.
- **Isi:** membandingkan **dua sumber yang dipelihara terpisah** (contoh: `Σ folio room lines` vs `Σ posting htl:room_revenue`; `Σ weighbridge` vs `Σ stockpile movement` vs `Σ shipment`; `Σ holdings token` vs `supply terbit`). Membaca data dengan `chunkById`; keluaran tabel selisih + exit code ≠ 0 bila ada selisih.
- **Test negatif wajib:** fixture yang merusak satu sumber → audit gagal. Audit tanpa test negatif tidak boleh dicentang.
- **Agregasi:** `chain:audit-all` menjalankan semua audit terdaftar; `super:health-check` hanya menampilkan pilar yang benar-benar diperiksa.
- **Jejak:** setiap mutasi penting memanggil `AuditTrailInterface` dengan `correlation_id`.

## A6. Keamanan, RBAC & privasi

- **Role & permission** dideklarasikan modul (seeder RBAC per modul), nama `snake_case` (mis. `front_office`, `hse_officer`). Setiap grup rute non-publik wajib `role:`/`can:`; kepemilikan data (tenant melihat tagihannya sendiri, pasien melihat rekamnya sendiri) lewat Policy (anti-IDOR).
- **Matriks rute × role** dihasilkan otomatis dari daftar rute dan dites (PROGRESS R3.1).
- **Kelas data:** **P1** (NIK, NPWP, paspor, rekening, data medis, formula rahasia) → `encrypted` cast + blind index HMAC untuk pencarian + log akses; **P2** (nama, email, telepon, alamat) → akses dibatasi role & dicatat untuk ekspor massal; **P3** publik.
- **Rahasia:** secret webhook terenkripsi; API key disimpan sebagai hash dan hanya ditampilkan sekali; tidak ada rahasia di log.
- **Kontrol diturunkan dari data**, bukan parameter (`bool $approved` dilarang — §A3).

## A7. UI & jalan masuk

Setiap fitur yang dicentang punya minimal: halaman daftar + detail + aksi (form/tombol) untuk role berhak, item menu lewat `MenuRegistry`, tampilan layak di lebar 375 px untuk peran lapangan (kasir, driver, operator, front office, scanner tiket). Endpoint API hanya bila tercantum di `docs/API.md` dengan autentikasi Sanctum + `Idempotency-Key` untuk mutasi. Fitur "headless" (murni job/listener) wajib alasan di `DECISIONS.md` dan tetap punya command/halaman monitoring.

## A8. Strategi test

| Kode | Makna wajib | Bentuk minimal |
|---|---|---|
| (a) | Happy path end-to-end | test HTTP sebagai role berhak → efek di DB + ledger |
| (b) | Otorisasi & validasi | role tak berhak → 403; input salah → 422 |
| (c) | Idempotensi/retry | aksi dipanggil 2× → satu efek ledger **dan** satu efek state |
| (d) | Invarian | Σ=0 per aset, saldo normal (§A2.1), stok tidak negatif, audit modul exit 0 |
| (e) | Edge case/konkurensi | satu kasus batas yang relevan; konkurensi nyata hanya di grup `db-portability` (MySQL/PostgreSQL) |

Aturan tambahan: data uji dibuat lewat seeder/factory resmi (bukan `LedgerAccount::create` di test); setiap audit punya test negatif; dilarang `assertTrue(true)`; nama test memuat id item (`test_r2_1_double_calculate_posts_cogs_once`).

## A9. Tingkatan skala & simulasi

| Tier | Volume | DB | Dipakai untuk | Syarat centang? |
|---|---|---|---|---|
| **T0** | ≤ 100 baris per tabel | SQLite in-memory | fixture test | ya |
| **T1** | 1 rb – 50 rb baris; selesai < 2 menit | SQLite/MySQL | `migrate:fresh --seed` demo, UI terlihat hidup | ya |
| **T2** | 100 rb – 10 jt baris | MySQL 8/PostgreSQL 16 | benchmark: waktu seed, p95 halaman kritis, durasi audit | ya, bila item menyebut kinerja — hasil wajib ditempel di `AUDIT.md` |
| **T3** | angka visi di bagian pilar (jutaan/miliaran) | — | arah desain (indeks, partisi, arsip) | **tidak** |

Partisi tabel & retensi hot/warm/cold hanya diimplementasikan di MySQL/PostgreSQL (SQLite tidak mendukung partisi); di SQLite cukup arsip berbasis tabel + job terjadwal. Seeder T2 wajib deterministik (seed), ber-chunk, dan bisa dilanjutkan (checkpoint).

## A10. Spesifikasi enabler lintas pilar

**A10.1 Simulation Kernel.** `ClockInterface` diinjeksi ke semua action/command peka waktu (hapus `now()` langsung di logika domain). `sim:run --days=N [--resume] [--seed=S]`: untuk setiap hari virtual → set jam virtual → jalankan *tick handler* terdaftar (depresiasi aset, cicilan pinjaman, tagihan Mall/Fleet/Hotel, expiry poin/voucher, MRP, D&D logistik, royalti waralaba & tambang, payroll bulanan) secara idempoten → simpan checkpoint (`sim_runs.virtual_now`, `last_completed_day`). Dilarang `Carbon::setTestNow()` di kode produksi. Kriteria: dua run dengan seed sama → hash snapshot saldo ledger identik.

**A10.2 Universal Event Spine.** Topik per domain (`auto.*`, `fintech.*`, `resto.*`, `prop.*`, `lgx.*`, `mfg.*`, `trade.*`, `gov.*`, `hsp.*`, `ven.*`, `htl.*`, `min.*`, `egy.*`, …), schema registry ber-versi (tabel `core_event_schemas`), consumer group dengan offset tersimpan, replay dari offset N idempoten, dead-letter. Event spine **melengkapi**, bukan menggantikan, event Laravel sinkron untuk konsumen di modul yang sama.

**A10.3 Digital Twin Bus.** `core_twin_states(entity_type, entity_id, version, state_json, valid_from, prev_hash, hash)`; hanya untuk analisis what-if & visualisasi — **tidak pernah** menjadi sumber kebenaran uang/stok (arch test sudah ada untuk `DigitalTwinService`). Mode sandbox menulis ke skema/connection terpisah.

**A10.4 Fictional Scale Provisioner.** Kerangka seeder per lini dengan parameter tier (`--tier=T1|T2`), seed deterministik, chunk insert, checkpoint/resume, dan laporan benchmark otomatis ke `storage/app/benchmarks/{tanggal}.json` untuk disalin ke `AUDIT.md`.

## A11. Definition of Ready & Definition of Done

**Ready** (sebelum item dikerjakan): modul pemilik & prefiks jelas (§A1); akun ledger & arah posting tertulis di spesifikasi pilar (subbagian xF); event yang dipancarkan/dikonsumsi ada di katalog (§A4.2); role & rute ditentukan; audit dua-sumber dirumuskan; tingkat simulasi (S1/S2/S3) dan tier skala (T0/T1/T2) dinyatakan; prasyarat berstatus ✅.

**Done:** vertical slice (§A0) + blok `Bukti:` di PROGRESS + gate penuh hijau + dokumen diperbarui. Detail protokol: `PROGRESS.md` §P1–P13 (DoR §P8, Register Minus §P9, verifikasi silang §P7).

## A12. Urutan pengerjaan lintas pilar (dependensi)

```
Platform ✅ (Ledger, Payment, Inventory, Core: RBAC/approval/numbering/outbox)
  └─ Fase R (remediasi) ──────────────────────────────────────────────┐
Party/Contract/Asset ✅ ──┬─ Pilar 2: RWA (Asset+Party+Contract+Crypto) · Insurance (Payment+Party) · Wealth (Hcm payroll+Treasury)
                          ├─ Pilar 4: Proptech (Mall+Egy meter+ESG) · EPC→Asset (kapitalisasi) · Flex-space (Mall+Payment)
                          └─ Pilar 8: Hcm (R6.3) → Gig bounty → Governance/DAO (Rwa token+Party)
Logistics ✅ ─────────────┬─ Pilar 5: reverse logistics (Manufacturing+Pricing+ESG) · cold-chain hold (Payment) · drone
                          ├─ Pilar 3: CloudKitchen (Resto+Hcm deduction) · Vending (Inventory+Payment) · forecast (Mall footfall)
                          └─ Lini 12: Mining (Asset+Trade+ESG+Hcm)
Manufacturing/Pricing ✅ ─ Pilar 6: surge pricing · VMI (Procurement+Wms) · C2M (Plm+Store)
Trade/TF 🟡 ───────────── Pilar 7: clearing house (Crypto+Logistics custody) · CBAM (ESG) · AI bidding (butuh Fase 64)
Core passport ✅ ───────── Pilar 1: Mobility (telematics→AutoServe, EV→Payment, fleet→Contract)
Party KYC + Payment ✅ ─── Lini 9 Rumah Sakit · Lini 10 Venue · Lini 11 Hotel (Resto F&B)
Lini 13–17 ────────────── setelah lini sumber datanya ✅ (Egy butuh Proptech meter; Tlx menjadi registry perangkat IoT tunggal; Ret memakai Inventory, bukan stok sendiri)
```

## A13. Larangan teknis, alasannya, dan pola yang benar

Larangan X1–X25 di `PROGRESS.md` §P3 berlaku sebagai aturan teknis yang mengikat. Tabel ini menjelaskan **kenapa** (temuan audit `KNOWLEDGE.md` §5) dan **pola benarnya** di dokumen ini, khusus untuk larangan yang menyangkut kode.

| Larangan | Kenapa dilarang (bukti audit) | Pola yang benar |
|---|---|---|
| X2 fitur di `Integration` / prefiks modul lain | 857 dari 1.516 tabel jadi milik `Integration`; prefiks `esg_`/`hcm_`/`fin_`/`ins_`/`gov_` bentrok (K-05) | §A1 registry; §A15.6 struktur modul |
| X3 float untuk uang | 302 parameter uang `float`; SQLite menyimpan `decimal` ±15 digit (K-14) | §A2.6; integer minor unit; basis poin |
| X4 key idempotensi acak | 27 lokasi `Str::uuid/random/now()` → retry menggandakan posting (K-12) | §A2.5; §A15.1 langkah 3–4 |
| X5/X23 akun & data hanya di test | 134 dari 174 akun hanya ada di test → alur gagal di DB hasil seed (K-11) | §A2.3; §A15.2 |
| X6 tanda manual / pendapatan negatif | ≥ 12 modul memposting pendapatan negatif; Σ=0 tetap lolos (K-10) | §A2.1; helper `Posting` §A15.3 |
| X7 flag boolean sebagai kontrol | 184 service menerima `$approved`/`$verified`/… dari pemanggil (K-21) | §A3 (id approval), §A15.1 langkah 2 |
| X9 status sukses tanpa aksi | webhook `delivered` tanpa request, EDI `accepted` tanpa parsing (K-31) | §A4.5 (`simulated_*`) |
| X10 "enkripsi" palsu | formula PLM `base64_encode`; NIK `sha256` tanpa kunci; tender plaintext (K-22) | §A6 kelas data P1 |
| X11 rute hanya `auth` | customer bisa auto-debit tenant Mall & bayar supplier Resto (K-20) | §A6; §A15.4 |
| X14 `Carbon::setTestNow()` di produksi | sim kernel menggeser jam seluruh proses (K-09) | §A10.1 `ClockInterface` |
| X15 `back()->errors()` / `assertTrue(true)` | 15 jalur galat → HTTP 500; test tanpa efek (K-27, K-29) | §A15.4, §A15.5 |
| X16 Domain/tabel lintas modul | 337 import Domain lintas modul, 47 `DB::table` lintas prefiks (K-07) | §A4.4 Contract; §A1 |
| X21 menyalin kerangka `Integration` | kerangka memuat X3/X4/X6/X7 sekaligus | tulis ulang dari §A15 |
| X22 "simulasi" melonggarkan invarian | komentar "Simulasi BPJS & PPh21 TER" menutupi tarif datar 5% (`HcmService`); item 59.4 menyebut "AES/base64" sebagai enkripsi | §A0: simulasi hanya untuk aturan eksternal |
| X25 default diam-diam | faktor emisi 1.0 untuk aktivitas tak dikenal (`EsgService::recordEmission`), gaji 5 jt & rekening default (`HcmService::registerEmployee`), suhu oli 90 °C/tegangan 12,6 V (`TelematicsIngestService::ingestTick`) | validasi eksplisit → 422 |

## A14. Spesifikasi detektor otomatis (pagar anti jalan pintas)

Detektor dipasang di Fase R0 (`PROGRESS.md` §P11) dan wajib hijau di setiap gate. Semua detektor yang memeriksa kode lama memakai **baseline ratchet**: `tests/Architecture/baselines/{detektor}.json` menyimpan jumlah pelanggaran per aturan; gate gagal bila angka **naik**; PR yang menurunkan angka wajib memperbarui baseline; menaikkan baseline hanya dengan entri `DECISIONS.md` + persetujuan pemilik.

**A14.1 `php artisan arch:scan`** — memindai `modules/**` (kecuali `tests/` bila tidak disebut), keluaran JSON per aturan + daftar lokasi. Implementasi: `app/Quality/ArchScan` (tokenizer PHP bawaan). Baseline: `tests/Architecture/baselines/arch-scan.json` per aturan → file → tanda tangan pelanggaran (tahan geser baris); gate: `tests/Architecture/ArchScanBaselineTest.php`. Kolom "Baseline terukur" adalah acuan resmi (DECISIONS 2026-10-10); perkiraan audit dipertahankan sebagai sejarah.

| Aturan | Mendeteksi | Cakupan & pengecualian | Perkiraan awal (audit 10 Okt 2026) | Baseline terukur (R0.8) |
|---|---|---|---|---|
| A1 | `use Modules\{B}\Domain\...` di modul A ≠ B | kecuali shared kernel yang tercatat di DECISIONS (mis. enum/DTO ledger setelah dipindah ke `Shared\Ledger`) | 337 import | 428 |
| A2 | `DB::table('{p}_…')`, `Schema::create/table('{p}_…')`, atau properti `$table` model di modul yang bukan pemilik prefiks `{p}` (registry §A1 = `config/modules.php`); prefiks tak terdaftar juga dihitung | migrasi pemindahan yang disetujui; tabel lama tanpa prefiks didaftarkan di `legacy_tables` | 47 akses + ±800 tabel milik Integration berprefiks lain | 5.254 (5.134 di `Integration`) |
| A3 | parameter/properti `float` bernama uang (`*idr*`, `amount`, `price`, `cost`, `fee`, `revenue`, `salary`, `total`, `budget`, `balance`) dan kolom `decimal` bernama `*_idr` | kuantitas fisik (kWh, kg, jam), rasio/tarif persen, dan durasi/latensi tidak dihitung; nama ber-`idr` selalu uang; kata setelah `per` adalah satuan (`costPerDay` = uang) | ±302 parameter + 80 kolom | 352 |
| A4 | `Str::uuid`, `Str::random`, `uniqid`, `random_bytes`, `now()`, `time()` di dalam argumen/variabel idempotency key | termasuk fallback acak (`?? Str::uuid()`) dan satu tingkat variabel perantara | 27 lokasi | 44 |
| A5 | `type:` posting ledger > 32 karakter atau tidak terdaftar di registry tipe | literal yang sama dengan nilai `TransactionType` dianggap terdaftar; ekspresi dinamis dihitung karena tidak bisa diverifikasi | 12 tipe terlalu panjang | 101 |
| A6 | parameter `bool` bernama kontrol (`$approved`, `$verified*`, `$passed`, `$compliant`, `$signed`, `$consent*`, `$evidence*`, `$attested`, `$certified`, `$*Granted`) di `Application/` | parameter filter tampilan dikecualikan lewat atribut `#[NotAControl('alasan ≥ 10 karakter')]` (`Modules\Shared\Domain\Attributes\NotAControl`) | 184 service | 133 |
| A7 | `base64_encode`/`hash()` tanpa kunci yang ditulis ke kolom bernama `*_encrypted`, `*secret*`, `*nik*`, `*npwp*`, `*passport*` | `passport` berarti nomor paspor (`passport_number/no/id`); hash-chain *Vehicle Passport* bukan PII sehingga tidak dihitung; `hash_hmac` dan `encrypt` tidak dihitung | ≥ 3 lokasi | 5 |
| A8 | `Carbon::setTestNow` / `Date::setTestNow` di luar `tests/` | — | 3 baris | 3 |
| A9 | `Modules\Shared` atau `Modules\Core` memakai namespace modul bisnis | — | ≥ 2 file | 34 |
| A10 | `$guarded = []` | — | 162 model | 162 |
| A11 | file PHP tanpa `declare(strict_types=1)` | migrasi & view dikecualikan | 341 file | 146 |
| A12 | kolom `*_id` intra-modul tanpa FK (index saja tidak cukup) | kolom lintas modul dikecualikan bila terdaftar di `cross_module_columns`; pasangan `{x}_id`+`{x}_type` (polimorfik) dikecualikan; FK yang ditambahkan di file migrasi lain belum terlihat (per file) | ±560 kolom (1.222 kolom `*_id` vs 661 deklarasi FK) | 596 |
| A13 | `back()->errors()` | — | 15 lokasi | 15 |

**A14.2 `ProgressIntegrityTest`** — mem-parse `docs/PROGRESS.md`; memvalidasi blok `Bukti:` sesuai `PROGRESS.md` §P2 (commit ada & menyentuh file yang disebut; file/test ada; test lulus di laporan gate; rute ada dengan middleware role yang sesuai; command/listener terdaftar; item fitur wajib punya `akses` + test HTTP/command). Juga gagal bila: status ✅ diberikan pada fase yang tidak punya bagian **Verifikasi** di `docs/gates/fase-N.md`; ada minus P0/P1 terbuka pada fase ✅; teks item berubah tanpa penanda `⬇️ diturunkan` (dibandingkan dengan snapshot item di baseline).

**A14.3 `RouteAuthorizationMatrixTest`** — `tests/Architecture/route-roles.php` memetakan `nama rute => [role yang boleh]` (atau `'public'`); untuk setiap rute: tidak terdaftar → gagal; role berhak → bukan 403; satu role tak berhak acak → 403. Rute dengan parameter model memakai factory/seeder T0.

**A14.4 `AuditCommandContractTest`** — setiap command `*:audit`, `verify-*`, `*:reconcile` wajib punya kelas fixture `{Command}CorruptionFixture` yang merusak satu sumber data; test: seed bersih → exit 0; fixture diterapkan → exit ≠ 0 dan keluaran menyebut entitas yang rusak.

**A14.5 `LedgerAccountRegistryTest` & `LedgerNormalBalanceTest`** — (1) semua kode akun yang dipakai kode produksi (literal `forCode`, konstanta `{M}Accounts`) terdefinisi & terseed dengan `kind` yang cocok; (2) setelah seed + skenario golden, saldo setiap akun sesuai sisi normal §A2.1 (pengecualian `clearing`/`exchange`).

**A14.6 `IntegrationFreezeTest`** — daftar file & tabel `modules/Integration` dibekukan di baseline; file baru hanya boleh di `Adapters/`, `Webhooks/`, `Edi/`, `Http/Controllers/Api/`; tabel baru hanya berprefiks `intg_`.

**A14.7 `TestHygieneTest`** — T1 `assertTrue(true)`/`expect(true)->toBeTrue()`; T2 `LedgerAccount::create|firstOrCreate|updateOrCreate` di test; T3 `markTestSkipped`/`->skip()` tanpa rujukan BLOCKERS; T4 modul yang punya rute tetapi tidak satu pun test-nya melakukan request HTTP.

**A14.8 Mutation testing** — mutation testing Pest untuk kelas Action/Service yang diubah PR (ditandai di test dengan `covers()`/`mutates()` sesuai versi Pest terpasang); skor minimal 60%; dijalankan di CI dengan driver coverage (PCOV/Xdebug).

## A15. Template kode normatif

Template ini adalah bentuk minimum yang **harus** diikuti; menyimpang dari template perlu alasan di DECISIONS. Nama kelas contoh memakai lini Rumah Sakit.

**A15.1 Action keuangan** (gabungan pola baik `RecognizeFreightRevenueAction`, `PaymentGatewayService`, `VerifyPinAction`):

```php
final class BillTrialMilestoneAction
{
    public function __construct(private readonly Ledger $ledger, private readonly ClockInterface $clock) {}

    public function execute(int $milestoneId, User $actor): TrialMilestone
    {
        return DB::transaction(function () use ($milestoneId, $actor): TrialMilestone {
            // 1) kunci baris sumber kebenaran
            $milestone = TrialMilestone::whereKey($milestoneId)->lockForUpdate()->firstOrFail();

            // 2) otorisasi di domain (selain middleware rute)
            Gate::forUser($actor)->authorize('bill', $milestone);

            // 3) idempoten di level STATE
            if ($milestone->billed_at !== null) {
                return $milestone;
            }
            $milestone->status->assertCanTransitionTo(MilestoneStatus::Billed);

            // 4) key deterministik + tanda lewat helper
            $transaction = $this->ledger->post(new PostingDTO(
                type: HospitalTransactionType::TrialMilestone->value,
                description: "Tagihan milestone {$milestone->number}",
                idempotencyKey: "hsp:trial-milestone:{$milestone->id}",
                entries: Posting::lines()
                    ->debit(HospitalAccounts::TRIAL_SPONSOR_RECEIVABLE, $milestone->amount_idr)
                    ->credit(HospitalAccounts::TRIAL_RESEARCH_REVENUE, $milestone->amount_idr)
                    ->toEntries('IDR'),
                referenceType: TrialMilestone::class,
                referenceId: $milestone->id,
                createdBy: $actor->id,
            ));

            // 5) state + tautan ke transaksi ledger
            $milestone->forceFill([
                'status' => MilestoneStatus::Billed,
                'billed_at' => $this->clock->now(),
                'ledger_transaction_id' => $transaction->id,
            ])->save();

            // 6) event setelah commit
            DB::afterCommit(fn () => event(new TrialMilestoneBilled($milestone->id)));

            return $milestone;
        });
    }
}
```

**A15.2 Chart of accounts modul:**

```php
final class HospitalAccounts implements ProvidesLedgerAccounts
{
    public const TRIAL_SPONSOR_RECEIVABLE = 'hsp:trial_sponsor_receivable:IDR';
    public const TRIAL_RESEARCH_REVENUE = 'hsp:trial_research_revenue:IDR';

    /** @return list<LedgerAccountDefinition> */
    public static function definitions(): array
    {
        return [
            new LedgerAccountDefinition(self::TRIAL_SPONSOR_RECEIVABLE, AccountKind::ASSET, 'IDR', allowNegative: true),
            new LedgerAccountDefinition(self::TRIAL_RESEARCH_REVENUE, AccountKind::REVENUE, 'IDR', allowNegative: false),
        ];
    }

    /** Akun per-subjek dibuat on demand oleh kode produksi, bukan oleh test. */
    public static function patientReceivable(int $episodeId): string
    {
        return "ar:hsp:episode:{$episodeId}:IDR";
    }
}
```

**A15.3 Helper posting** (tanda tidak pernah ditulis manual):

```php
$entries = Posting::lines()
    ->debit(HotelAccounts::folioReceivable($folio->id), $roomChargeIdr)   // → −amount (sisi debit)
    ->credit(HotelAccounts::ROOM_REVENUE, $roomChargeIdr - $taxIdr)        // → +amount (sisi kredit)
    ->credit(HotelAccounts::TAX_PAYABLE, $taxIdr)
    ->toEntries('IDR');                                                    // gagal bila Σ ≠ 0
```

**A15.4 Rute, role, controller tipis, jalur galat:**

```php
Route::middleware(['web', 'auth', 'role:rs_admin,cashier_rs'])
    ->prefix('hospital/billing')->name('hospital.billing.')
    ->group(function (): void {
        Route::post('trials/milestones/{milestone}/bill', [TrialBillingController::class, 'bill'])->name('trials.milestones.bill');
    });

public function bill(Request $request, TrialMilestone $milestone, BillTrialMilestoneAction $action): RedirectResponse
{
    try {
        $action->execute($milestone->id, $request->user());
    } catch (InvalidStateTransition $exception) {
        return back()->withErrors(['milestone' => $exception->getMessage()])->withInput();
    }

    return back()->with('success', "Milestone {$milestone->number} ditagihkan.");
}
// + entri 'hospital.billing.trials.milestones.bill' => ['rs_admin', 'cashier_rs'] di tests/Architecture/route-roles.php
```

**A15.5 Test (a)–(e)** (Pest; sesuaikan nama factory/state dengan yang ada di repo):

```php
beforeEach(fn () => $this->seed([LedgerAccountsSeeder::class, HospitalDemoSeeder::class]));

it('menagih milestone sekali walau tombol ditekan dua kali (a, c, d)', function (): void {
    $admin = User::factory()->create(['role' => 'rs_admin']);
    $milestone = TrialMilestone::factory()->pending()->create(['amount_idr' => 500_000_000]);

    $this->actingAs($admin)->post(route('hospital.billing.trials.milestones.bill', $milestone))->assertRedirect();
    $this->actingAs($admin)->post(route('hospital.billing.trials.milestones.bill', $milestone))->assertRedirect();

    expect(LedgerTransaction::where('idempotency_key', "hsp:trial-milestone:{$milestone->id}")->count())->toBe(1)
        ->and($milestone->fresh()->billed_at)->not->toBeNull();
    $this->artisan('hosp:audit')->assertExitCode(0);
});

it('menolak role yang tidak berhak (b)', function (): void {
    $nurse = User::factory()->create(['role' => 'nurse']);
    $milestone = TrialMilestone::factory()->pending()->create();

    $this->actingAs($nurse)->post(route('hospital.billing.trials.milestones.bill', $milestone))->assertForbidden();
});

it('hosp:audit gagal bila tagihan tidak cocok dengan ledger (audit negatif)', function (): void {
    $milestone = TrialMilestone::factory()->billed()->create(['amount_idr' => 500_000_000]);
    $milestone->forceFill(['amount_idr' => 400_000_000])->saveQuietly();   // rusak satu sumber

    $this->artisan('hosp:audit')->assertExitCode(1);
});
```

**A15.6 Audit dua-sumber:** baca sumber A (tabel modul) per `chunkById`, cocokkan dengan sumber B (posting ledger ber-key / movement / hash-chain), cetak tabel selisih, exit ≠ 0 bila ada selisih, dan sediakan `{Command}CorruptionFixture` (§A14.4).

## A16. Aturan fase tema & keputusan modul baru

- **Fase tema** (Fase 185–500, mis. "Governance Wave", "Platform Wave", "Final: Enterprise …") wajib diterjemahkan di DoR (`PROGRESS.md` §P8) menjadi perubahan nyata pada modul domain yang ada — rute, aksi, audit, test yang bisa ditunjuk — **atau** dinyatakan ditunda/tidak berlaku lewat aturan lingkup (§P10). Dilarang membuat service "skor/flag/maturity" yang hanya menyimpan angka masukan pemanggil.
- **Tema rekayasa** (release train, SLO, DX tooling, observability) umumnya diwujudkan sebagai artefak engineering (workflow CI, konfigurasi, dashboard, runbook yang diuji), **bukan** tabel aplikasi.
- **Modul baru** (mis. `Syariah`, `Crm`, `Grc`, `Marine`, `Forest`) hanya dibuat bila: domainnya tidak bisa menjadi sub-domain modul yang sudah ✅; prefiks didaftarkan di §A1; keputusan dicatat di `DECISIONS.md`; pemilik menyetujui (CLAUDE.md melarang folder dasar baru tanpa izin).

## A17. Status, verifikasi, dan Register Minus

Alur status (`⬜/🟠/🟡 → 🔨 → 🔵 → ✅/🔁`), checklist verifikator C1–C14, template DoR, kriteria vertical slice V1–V12, dan Register Minus didefinisikan di `PROGRESS.md` §P7–P9; template prompt pelaksana & verifikator di §P13. Prinsip yang mengikat seluruh KONSEP: **yang menulis kode tidak memverifikasi dirinya sendiri, dan setiap kekurangan harus tertulis** — fase dengan minus P0/P1 terbuka tidak pernah berstatus ✅.

---

# PILAR 1 — OTOMOTIF & PEMBIAYAAN KENDARAAN

## 1A. Skala
- **Master kendaraan:** 10 juta unit `core_vehicles` multi-owner (roda 4, roda 2, armada komersial, alat berat) — tiap unit punya Vehicle Passport hash-chain dengan riwayat peristiwa tak terbatas (50+ event per unit: beli, servis, insiden, ganti pemilik, recall, baterai EV).
- **Telematics stream:** 10 juta kendaraan memancarkan **500 juta titik data OBD2/hari** (GPS, RPM, suhu oli, level baterai, kode DTC) → disimulasikan sebagai tick batch idempoten, retensi hot 30 hari / warm 1 tahun / cold arsip.
- **Bengkel:** 1.000 outlet AutoServe × 1 juta booking servis/hari puncak (10 slot/jam × 10 bay × 24 jam × 1.000 outlet), 50 juta estimasi & invoice/tahun.
- **EV Charging:** 500.000 titik SPKLU, 20 juta sesi pengisian/hari, 100 juta meteran kWh tercatat.
- **Pembiayaan:** 2 juta kontrak leasing/kredit aktif, masing-masing 60–240 termin → 1 miliar baris jadwal angsuran ter-partisi.
- **Fleet B2B:** 50.000 perusahaan penyewa, 500.000 unit armada dalam kontrak sewa, telematikanya mengalir 24/7.

## 1B. Operasional
- **Prediktif Maintenance:** telematik OBD2 → deteksi anomali (mis. suhu oli naik 15% di atas baseline 7 hari) → event `VehicleAnomalyDetected` → AutoServe otomatis membuat **draf booking** + estimasi biaya + slot terdekat; pelanggan konfirmasi sekali klik. Anomali kritis (DTC engine) otomatis menahan unit dari dispatch armada.
- **Opsi di draf booking:** bayar tunai, bayar wallet+PIN, atau **ajukan pembiayaan** — jaring ke modul Fin (HODL-to-Drive / leasing), dengan keputusan kredit 60 detik.
- **EV Charging ops:** booking slot dari garasi AutoDex → tiket reservasi time-lock → sesi charge tercatat meteran kWh → tagih otomatis via Payment Hub ke wallet/aset kripto → **SoH (State of Health) baterai** dihitung dari siklus dan ditulis ke Vehicle Passport secara hash-chained; degradasi SoH < 70% memicu event tukar-tambah.
- **Fleet B2B:** kontrak sewa → SLA (downtime maksimum, jadwal servis wajib) terhubung ke modul Contract; Logistics memantau rute harian armada sewaan; Finance menjalankan amortisasi nilai sewa (PSAK 73 simulasi) + rekonsiliasi utilisasi vs tagihan.
- **Peran baru:** `fleet_manager`, `ev_operator`, `finance_officer_oto` — semua lewat RBAC granular + approval four-eyes untuk kontrak > ambang nilai.

## 1C. Cakupan
- **Lini:** AutoDex (dagang) + AutoServe (servis) + Store (suku cadang) + HODL-to-Drive (kredit) + Logistics (pengiriman & fleet) + Asset (aset armada) + Contract (kontrak sewa) + ESG (emisi unit).
- **Fleets:** kendaraan pribadi, armada logistik sewa, armada restoran/katering, armada EPC, armada mall (security/facility).
- **Teknologi:** OBD2/IoT simulasi, edge gateway, GPS trace, charger API, baterai digital twin.
- **Geografi:** seluruh Indonesia (plat DA s.d. B), 34 provinsi jaringan bengkel & SPKLU.

## 1D. Hasil (Output)
- **Vehicle Passport 360°** lengkap: kepemilikan, servis, kilometer, insiden, SoH baterai, recall, riwayat pembiayaan — diverifikasi `core:verify-passports`.
- **Dashboard Predictive Maintenance:** antrian draf booking, estimasi pendapatan, MAPE akurasi prediksi kerusakan.
- **Energy & Fleet Console:** peta SPKLU (occupancy, antrian), kWh terjual, margin per sesi, SoH fleet heatmap.
- **Contract Lease Ledger:** amortisasi, SLA breach, utilisasi per unit, biaya total kepemilikan (TCO sudah ada di Fase 31.7 diperluas per unit fleet).
- **Audit:** `auto:audit` (booking terbayar = ledger, escrow servis = hold aktif, sesi EV tertagih = kWh meteran, kontrak sewa = amortisasi subledger) = 0 selisih.

## 1E. Ide Pengembangan Lanjutan
- **InsurTech tersemat** (juga masuk Pilar 2): smart-contract mencairkan klaim otomatis — GPS armada telat > 4 jam, atau unit masuk AutoServe akibat kecelakaan (kode DTC collision) → klaim tanpa formulir.
- **Resale Value Oracle:** harga pasar unit bekas dihitung dari Passport + market comps → jadi nilai likuidasi minimum kredit.
- **Congestion & Route-Based Telematics:** data armada menyuplai Pilar 5 untuk perhitungan ongkir dinamis.
- **Recall Campaign Engine:** deteksi batch berdasarkan DTC agregat → kampanye recall otomatis, kuota suku cadang dipesan via Procurement, unit dijadwalkan ke AutoServe.


## 1F. Spesifikasi implementasi minimum (MVP vertical slice)

- **Modul & prefiks:** Core (`core_vehicles`, `core_vehicle_events`), AutoServe (`serve_`), AutoDex (`dex_`), Store (`store_`), Finance (`fin_`), **Mobility** (`oto_`, gabungan Telematics + Ev + Fleet — lihat §A1).
- **Status audit:** AutoServe/AutoDex/Store/Finance/passport ✅ · Telematics/Ev/Fleet 🟠 (tanpa rute/UI, baseline konstan, akun ledger hanya di test, key acak di kompensasi SLA, `amortizeMonthly()` tidak idempoten).
- **Slice 1 — Telematik → draf booking:** `oto_telematics_devices` (terikat kendaraan) · `oto_telematics_ticks` (unik `device_id+sequence`) · `oto_telematics_baselines` (dihitung ulang harian dari 7 hari tick nyata) · daftar DTC kritis di config · event `auto.telematics.anomaly_detected.v1` → AutoServe membuat draf booking sekali per kendaraan per jenis anomali per hari · unit `grounded` ditolak dispatch Logistik.
- **Slice 2 — Sesi EV:** `oto_ev_stations/chargers/sessions` · state `reserved → charging → completed | cancelled` · mulai sesi = `PaymentGateway::hold()` estimasi; selesai = `capture(kWh × tarif)` dan sisa kembali · SoH baterai ditulis ke passport (event `auto.ev.session_completed.v1`).
- **Slice 3 — Sewa armada B2B:** kontrak di modul Contract (`ctr_`) + unit `oto_fleet_contract_units` · tagihan bulanan ber-key `oto:fleet:bill:{contract}:{periode}` · kompensasi SLA ber-key `oto:fleet:sla:{breach_id}`.
- **Posting ledger:**

| Kejadian | Debit (−) | Kredit (+) |
|---|---|---|
| Hold sesi EV | `wallet:user:{id}:IDR` | `escrow:payment:IDR` |
| Capture sesi EV | `escrow:payment:IDR` | `oto:ev_charging_revenue:IDR` (+ sisa ke dompet) |
| Tagihan sewa bulanan | `ar:oto:fleet:{party}:IDR` | `oto:fleet_lease_revenue:IDR` |
| Kredit SLA ke penyewa | `expense:oto:fleet_sla_credit:IDR` | `ar:oto:fleet:{party}:IDR` |

- **Rute & role:** `/oto/telematics` (`fleet_manager`; `mekanik` baca draf booking) · `/oto/ev` (`ev_operator`; `customer` melihat sesinya sendiri) · `/oto/fleet` (`fleet_manager`, `finance_officer_oto`).
- **Command:** `oto:recompute-baselines` (harian) · `oto:bill-fleet` (bulanan) · `auto:audit` (nama sesuai 1D).
- **Audit dua sumber (`auto:audit`):** Σ(kWh × tarif) sesi `completed` = Σ capture `oto:ev_charging_revenue`; tagihan sewa = posting AR; jumlah event SoH di passport = jumlah sesi selesai.
- **Test kunci:** tick duplikat idempoten; anomali sama dalam satu hari → satu draf booking; unit grounded ditolak dispatch; stop sesi 2× → satu capture; tagihan bulan sama 2× → satu invoice & akumulasi tidak bergeser.
- **Skala:** T1 = 200 kendaraan, 50 perangkat, 10 rb tick; T2 = 1 jt tick (benchmark ingest & query baseline); T3 = visi 1A.
- **Di luar MVP:** InsurTech tersemat (Pilar 2), resale value oracle, recall engine.

- **Jalan pintas terlarang** (pola yang ditemukan audit atau paling mungkin muncul di pilar ini): baseline telematik berupa konstanta yang tidak pernah dihitung ulang; semua DTC dianggap kritis; akumulasi amortisasi sewa diubah sebelum posting ber-key; `Str::random` di key kompensasi SLA; akun `oto:*` dibuat di test; nilai sensor default diam-diam (90 °C, 12,6 V, BBM 100%).
- **Bukti selesai minimum** (selain V1–V12 di `PROGRESS.md` §P8): rute `/oto/*` dengan role; test HTTP draf booking tepat sekali per anomali per hari; test stop sesi EV ganda → satu capture; test tagihan sewa bulan sama 2× → satu invoice & akumulasi tetap; `auto:audit` + fixture korupsi; seeder T1 Mobility.

---

# PILAR 2 — FINTECH, PERBANKAN & KRIPTO

## 2A. Skala
- **Nasabah:** 5 juta dompet aktif (multi-aset: IDR, PTS, BTC/ETH/SOL/BNB/USDT, kredit karbon, tokenisasi) dengan buku besar double-entry yang tetap Σ=0 per aset.
- **Throughput:** 50 juta mutasi/hari puncak, diposting via ledger dengan lock per-akun + idempotency key deterministik; mutasi diarsipkan ke tabel partisi bulanan.
- **Tokenisasi RWA:** 100.000 aset riil yang dipecah (unit mall, truk, kontainer, mesin pabrik, petak lahan, hak sewa) → 100 juta baris kepemilikan token; 1 juta holder.
- **Investasi:** 500 ribu portofolio Robo-Advisor, 1 juta order/bulan (reksadana, emas digital, kripto), 10 ribu instrumen harga tick.
- **InsurTech:** 3 juta polis mikro tersemat aktif, 500 ribu klaim otomatis/tahun.

## 2B. Operasional
- **RWA Tokenization:** aset fisik diverifikasi (dokumen 26.8 + appraisal) → diterbitkan token pada `crypto_assets` (penomoran via 26.8) → orderbook internal (memperluas PriceFeed) → settlement lewat ledger; **dividen harian** dihitung dari omzet sumber aset (mis. omzet logistik tercatat di Core Banking) → distribusi pro-rata per holder via batch posting idempoten.
- **InsurTech Micro-Insurance:** premi dipotong otomatis dari saldo saat event trigger (logistik telat, kendaraan masuk bengkel karena kecelakaan, suhu rantai dingin breach) → smart-contract simulasi memvalidasi bukti hash-chain → **klaim cair langsung ke dompet** dalam hitungan detik; reserve & akuntansi premi/klaim ke ledger.
- **Robo-Advisor:** membaca pola gaji (HCM) dan pengeluaran (mutasi wallet) → menetapkan alokasi bulanan (mis. 30% surplus) → reksadana/emas/kripto sesuai profil risiko → **otomatis menyuntik likuiditas Treasury** (kas mengendap di akun treasury earning yield simulasi); guardrail likuiditas minimum 2 bulan pengeluaran tidak boleh diinvestasikan.
- **Settlement & compliance:** screening AML ringan (skor anomali dari Fase 64), freeze akun via `FreezeAccount`, four-eyes untuk penarikan besar.

## 2C. Cakupan
- **Lini:** Core Banking, Payment Hub, Crypto, Trade Finance, Treasury, Loan/HODL-to-Drive, Marketplace escrow, Loyalty (PTS), Carbon credits (ESG), Payroll (HCM).
- **Produk:** tabungan, deposito token, pinjaman terkolateral, kredit barang (Store/B2B), escrow, stablecoin internal, token aset riil, polis mikro, reksadana simulasi.
- **Risiko:** LTV (sudah ada margin call), duration, konsentrasi holder, reserve klaim, kewajiban merchant settle.

## 2D. Hasil (Output)
- **Unified Balance Console:** semua aset (fiat/kripto/token/PTS/karbon) dalam satu papan nilai riil.
- **RWA Marketplace:** katalog aset riil, orderbook, kepemilikan pro-rata, jadwal dividen, laporan distribusi.
- **Claims Autopilot:** klaim otomatis dieksekusi + rasio loss ratio, days-to-pay = detik.
- **Wealth Dashboard:** kinerja portofolio vs benchmark, rekomendasi bulanan, dampak ke Treasury yield.
- **Audit:** `bank:reconcile` (asli) + `fintech:audit` (token terbit = kepemilikan holder; reserve asuransi >= kewajiban; saldo escrow = komitmen; 0 selisih).

## 2E. Ide Pengembangan Lanjutan
- **Stablecoin internal grup** untuk settlement intercompany seketika (terhubung Pilar 7 clearing house).
- **Credit scoring lintas pilar:** rekam jejak bayar sewa mall + royalti resto + omzet distributor → skor kredit 360° untuk plafon baru.
- **Yield saldo idle:** saldo mengendap PTS/IDR menghasilkan yield harian kecil (liabilitas dibukukan) yang mengalir ke Treasury.
- **Kartu kredit korporat grup:** limit gabungan dengan jaringan vendor, cashback otomatis ke dompet entitas.


## 2F. Spesifikasi implementasi minimum (MVP vertical slice)

- **Modul & prefiks:** Banking (`bank_`), Payment (`pay_`), Crypto (`crypto_`), Finance (`fin_`), Treasury (`trs_`), Rwa (`rwa_`), Insurance (`ins_`), Wealth (`wm_`).
- **Status audit:** Banking/Payment/Crypto/Finance ✅ (bug refund & guard ledger → PROGRESS R1) · Treasury 🟡 · Rwa/Insurance/Wealth 🟠.
- **Slice 1 — Tokenisasi RWA:** aset sumber wajib terdaftar di Asset (`ast_assets`) + pemilik di Party (KYB) + kontrak penerbitan di Contract · aset ledger baru `RWA-{id}` (unit integer) · state `draft → approved (four-eyes) → issued → paused | retired` · dividen dari pendapatan sumber yang **sudah terposting** (mis. sewa Mall, folio Hotel) pro-rata kepemilikan pada tanggal cut-off, baris terakhir menyerap pembulatan.
- **Slice 2 — Micro-insurance:** produk (`ins_products`) → polis (`ins_policies`, nomor gapless) → premi → pemicu klaim dari event (`lgx.temperature.breached.v1`, `lgx.shipment.delivered.v1` terlambat, booking servis kategori tabrakan) → klaim (`ins_claims`, approval di atas ambang) → pembayaran ke dompet.
- **Slice 3 — Robo-advisor:** profil risiko (`wm_profiles`) → rencana alokasi (`wm_plans`) → eksekusi bulanan setelah `hcm.payroll.posted.v1` dengan guardrail likuiditas 2 bulan pengeluaran → kepemilikan (`wm_holdings`) bernilai NAV harian (simulasi S1).
- **Posting ledger:**

| Kejadian | Debit (−) | Kredit (+) |
|---|---|---|
| Penerbitan token | `rwa:issued_supply:RWA-{id}` | `rwa:unsold:RWA-{id}` |
| Pembelian token (IDR) | `wallet:user:{id}:IDR` | `rwa:sale_proceeds:{asset}:IDR` |
| Serah token | `rwa:unsold:RWA-{id}` | `wallet:user:{id}:RWA-{id}` |
| Deklarasi dividen | `rwa:distributable_income:{asset}:IDR` | `rwa:dividend_payable:{asset}:IDR` |
| Bayar dividen | `rwa:dividend_payable:{asset}:IDR` | `wallet:user:{holder}:IDR` |
| Premi dibayar | `wallet:user:{id}:IDR` | `ins:premium_unearned:IDR` |
| Premi diakui (harian/bulanan) | `ins:premium_unearned:IDR` | `ins:premium_revenue:IDR` |
| Klaim disetujui / dibayar | `expense:ins:claims:IDR` → `ins:claims_payable:IDR` | `ins:claims_payable:IDR` → `wallet:user:{id}:IDR` |
| Alokasi robo-advisor | `wallet:user:{id}:IDR` | `wm:client_funds:{id}:IDR` |

- **Rute & role:** `/rwa` (`rwa_issuer`, `investor` = customer terverifikasi KYC) · `/insurance` (`underwriter`, `claims_officer`; customer melihat polisnya) · `/wealth` (`wealth_advisor`; customer).
- **Command:** `rwa:distribute-dividends` · `ins:earn-premiums` · `wm:rebalance` · `fintech:audit` (nama sesuai 2D; menjalankan pemeriksaan RWA, asuransi, dan wealth).
- **Audit dua sumber:** Σ saldo holder per token = supply terbit − unsold; dividen terbayar = dividen dideklarasikan; cadangan klaim ≥ klaim terbuka; Σ `wm_holdings × NAV` = saldo `wm:client_funds`.
- **Test kunci:** pembelian token tanpa KYC → 403; dividen dua kali untuk periode sama → sekali; pembulatan dividen Σ = deklarasi; klaim dobel untuk event sama → satu klaim; alokasi melanggar guardrail likuiditas → ditolak.
- **Skala:** T1 = 20 aset RWA, 500 holder, 1 rb polis, 200 portofolio; T3 = visi 2A.
- **Di luar MVP:** orderbook sekunder RWA, stablecoin internal (Pilar 7), credit scoring lintas pilar.

- **Jalan pintas terlarang** (pola yang ditemukan audit atau paling mungkin muncul di pilar ini): dividen dihitung dari angka masukan, bukan pendapatan terposting; kepemilikan token tanpa ledger aset; klaim dicairkan dengan flag `$approved`; premi diakui penuh saat dibayar; NAV/alokasi memakai float.
- **Bukti selesai minimum** (selain V1–V12 di `PROGRESS.md` §P8): rute `/rwa`, `/insurance`, `/wealth` dengan role; test pembelian token tanpa KYC → 403; test dividen periode sama 2× → sekali & Σ pembulatan = deklarasi; test klaim ganda → satu; `fintech:audit` + fixture korupsi.

---

# PILAR 3 — KULINER, RESTORAN & WARALABA

## 3A. Skala
- **Jaringan:** 5.000 outlet (300 milik sendiri, 4.700 waralaba) di 100 kota, 5 dapur sentral, 200 cloud kitchen satelit.
- **Transaksi:** 2 juta order/hari (POS hidang, takeaway, delivery, katering, vending) → 730 juta order/tahun; tabel order ter-partisi bulanan.
- **Inventori:** 50.000 SKU bahan + 10.000 produk jadi kemasan, 1 juta mutasi stok/hari.
- **Vending:** 1 juta unit smart vending & kiosk tanpa awak, 20 juta transaksi/hari.
- **Katering:** 100 ribu order katering aktif, 5 juta pax/bulan.

## 3B. Operasional
- **Cloud Kitchen & Delivery Aggregator Internal:** dapur sentral + cloud kitchen sebagai origin, jaringan Logistics sendiri sebagai armada pengantar; langganan katering karyawan/tenant mall dibebankan otomatis (**Payroll Deduction** di HCM — dipotong dari gaji bulanan via ledger, dengan kuota harian dan rotasi menu).
- **AI Demand & Waste Forecasting:** prediksi pengunjung per outlet besok dari footfall mall (Pilar 4), kalender event, cuaca, tren lalu lintas (Pilar 5) → menghasilkan **Purchase Order bahan segar otomatis ke Agri/Supplier** tanpa intervensi manusia (melewati approval engine sebagai auto-PR); deviasi forecast tercatat untuk MAPE dan perbaikan model.
- **Smart Vending & Unmanned Kiosks:** setiap unit adalah node inventori mini terhubung WMS → rute restock dinamis (armada berhenti hanya saat level kritis) → pembayaran via Payment Hub (QR / face-recognition simulasi → token biometrik) → stok terpotong via InventoryService dan direkonsiliasi per unit per hari.
- **Waste & HPP real-time:** AI membandingkan HPP per porsi (sudah ada Fase 7.3) terhadap harga jual aktual per outlet → rekomendasi reprice/menu engineering otomatis; waste etalase yang berlebih memicu alert dan mengubah rencana produksi besok.
- **Kualifikasi waralaba:** onboarding franchisee baru melewati KYB Party + kontrak waralaba Contract + royalti harian (sudah Fase 11.3) + audit penjualan tenant.

## 3C. Cakupan
- **Lini:** Resto (POS, dapur, HPP, katering, delivery, royalti) + Agri (bahan baku) + Logistics (pengiriman bahan & makanan) + WMS (gudang sentral) + HCM (payroll deduction) + Mall (outlet tenant & event) + Store (peralatan) + ESG (food waste → kompos/energi).
- **Kanal:** dine-in, takeaway, delivery internal, katering B2B, vending, cloud kitchen, marketplace eksternal (simulasi adapter).
- **Geografi:** 100 kota, 5 dapur sentral multi-region, koridor cold-chain utama.

## 3D. Hasil (Output)
- **Network Demand Console:** forecast per outlet 7 hari, PO otomatis, MAPE, food waste rate turun.
- **Vending Ops Map:** status 1 juta unit (stock level, kesehatan mesin, omzet per jam), rute restock optimal.
- **Payroll Deduction Ledger:** potongan katering karyawan terekonsiliasi dengan payroll HCM.
- **Franchise Performance:** omzet per franchisee, royalti terkumpul, kualifikasi outlet.
- **Audit:** `resto:audit` (batch produksi = stok terpotong, vending sales = stok terpotong, katering deduction = payroll ledger; 0 selisih).

## 3E. Ide Pengembangan Lanjutan
- **Kitchen Robotics Ops Dashboard:** simulasi parameter mesin (fryer, grill) → OEE dapur ala pabrik (meminjam Fase 40).
- **Personalized Menu Engine:** menu berubah per outlet per jam berdasarkan profil tamu (loyalty PTS) dan sisa bahan (gunakan dulu yang mendekati expired — FEFO dapur).
- **Ghost Brand Incubator:** uji merek baru di 1 cloud kitchen 30 hari → keputusan scale/kill otomatis berdasarkan unit economics.


## 3F. Spesifikasi implementasi minimum (MVP vertical slice)

- **Modul & prefiks:** Resto (`resto_`), CloudKitchen (`ckt_` — ganti dari `resto_`), Vending (`vnd_` — ganti dari `ven_`), Logistics, Hcm, Procurement, Inventory.
- **Status audit:** Resto ✅ (bug hitung tagihan ganda, rute tanpa role → PROGRESS R2.1, R3.1) · CloudKitchen/Vending 🟠.
- **Slice 1 — Cloud kitchen & delivery internal:** dapur (`ckt_kitchens`) → order (`ckt_orders`) → produksi memotong bahan via `InventoryService` → pengiriman via Contract `ShipmentBooking` → status pesanan mengikuti event pengiriman.
- **Slice 2 — Katering karyawan dengan potong gaji:** langganan (`ckt_catering_subscriptions`) dengan kuota harian → saat `hcm.payroll.posted.v1`, potongan dibuat sebagai baris payroll (bukan posting terpisah tanpa payroll) → rekonsiliasi potongan vs konsumsi.
- **Slice 3 — Forecast → auto-PR:** forecast per outlet dari histori penjualan + footfall Mall (`mall.footfall.recorded.v1`) → usulan PR ke Procurement lewat `ApprovalEngine` (auto-approve di bawah ambang) → MAPE dicatat per hari.
- **Slice 4 — Smart vending:** unit (`vnd_units`) sebagai lokasi stok di Inventory → transaksi (`vnd_transactions`) via PaymentGateway → tugas restock saat stok < ambang.
- **Posting ledger:**

| Kejadian | Debit (−) | Kredit (+) |
|---|---|---|
| Penjualan (dompet) | `wallet:user:{id}:IDR` | `resto:sales_revenue:{outlet}:IDR` + `resto:pb1_payable:IDR` |
| HPP | `expense:resto:cogs:IDR` | `inventory:resto:{outlet}:IDR` |
| Potongan katering di payroll | `hcm:salary_payable:{employee}:IDR` | `ckt:catering_revenue:IDR` |
| Penjualan vending | `wallet:user:{id}:IDR` | `vnd:sales_revenue:IDR` (+ HPP seperti di atas) |

- **Rute & role:** `/cloud-kitchen` (`kitchen`, `outlet_manager`, `catering_admin`) · `/vending` (`vending_operator`) · rute Resto lama wajib diberi role (`outlet_manager`, `kitchen`, `cashier`, `procurement`).
- **Command:** `ckt:generate-daily-orders` · `resto:forecast` · `vnd:plan-restock` · `resto:audit`.
- **Audit dua sumber (`resto:audit`):** konsumsi bahan per batch = movement bahan; penjualan vending = stok keluar unit; Σ potongan katering = Σ baris payroll potongan.
- **Test kunci:** hitung tagihan 2× → HPP sekali; potongan melebihi kuota → ditolak; forecast deterministik (seed); restock tidak membuat stok negatif.
- **Skala:** T1 = 10 outlet, 2 dapur sentral, 20 vending, 5 rb order; T3 = visi 3A.

- **Jalan pintas terlarang** (pola yang ditemukan audit atau paling mungkin muncul di pilar ini): CloudKitchen menulis tabel `resto_*`; potongan katering di luar baris payroll; forecast acak tanpa seed; stok vending di luar Inventory; hitung tagihan hidang yang memotong HPP lagi saat dipanggil ulang (bug lama, R2.1).
- **Bukti selesai minimum** (selain V1–V12 di `PROGRESS.md` §P8): rute `/cloud-kitchen`, `/vending` dengan role; test Σ potongan katering = konsumsi = baris payroll; test hitung tagihan 2× → HPP sekali; test forecast deterministik (seed); `resto:audit` + fixture korupsi.

---

# PILAR 4 — PROPERTI KOMERSIAL & EPC

## 4A. Skala
- **Portofolio:** 200 properti komersial (mall, ruko, gudang, kantor, kawasan industri) dengan 50 ribu unit sewa aktif, 5 juta lembar invoice penagihan/tahun, 100 juta meteran utilitas/bulan.
- **Digital Twin:** 10 ribu lantai dimodelkan sebagai graph node (ruang → HVAC → sensor → meter) dengan 100 juta titik pembacaan sensor/bulan (suhu, kelembaban, arus, CO2, okupansi).
- **EPC:** 5.000 proyek konstruksi aktif (mall, pabrik, central kitchen, SPKLU), 50 juta baris WBS/aktivitas, 1 juta sertifikat prestasi (MC) terbit.
- **Parkir & footfall:** 5 juta sesi parkir/hari di 200 properti, 500 juta titik footfall/bulan.
- **Flex-space:** 50 ribu ruang on-demand (meeting room, booth, co-working desk), 1 juta booking/bulan.

## 4B. Operasional
- **PropTech & Smart Building:** sensor IoT gedung → okupansi real-time dari grid CCTV/footfall → HVAC & pencahayaan menyesuaikan otomatis → **tagihan listrik tenant dihitung dari pembacaan aktual per zona** (bukan estimasi) dan penurunan emisi GRK dihitung real-time (faktor grid sudah ada di Fase 60.1) → masuk laporan ESG per gedung.
- **Digital Twin & BIM:** saat EPC membangun, sistem menyimpan model BIM ber-versi (synchronized dengan EBOM/MBOM Fase 59); setelah operasi, model menjadi twin hidup — mekanik facility melihat letak pipa/kabel sebelum membongkar, work order (Fase 15.4) menandai komponen terdampak di twin, dan simulasi aliran udara/bencana (kebakaran, banjir) dijalankan di sandbox.
- **Flex-Space Booking:** area kosong mall/site EPC dikomersialisasi → booking per jam via portal, pintu terbuka dengan **pemindaian Paspor Kriptografis** (QR vehicle/person identity dari Core) → tagihan otomatis ke wallet; okupansi flex-space menaikkan revenue per m² properti.
- **EPC digital:** WBS + kurva-S (sudah Fase 63) diperluas dengan **BIM-linked progress**: setiap aktivitas WBS terikat pada komponen BIM → progres fisik diverifikasi dari komponen selesai → memicu MC, CIP, dan kapitalisasi aset (Fase 63.4) otomatis.

## 4C. Cakupan
- **Lini:** Mall (leasing, billing, parkir, facility, event, loyalty) + EPC (proyek konstruksi) + Asset (aset gedung) + Contract (lease & kontrak proyek) + Logistics (loading dock, material proyek) + ESG (energi & emisi) + Store/Resto (tenant) + Core (identitas & paspor).
- **Tipe properti:** mall, ruko, gudang, kantor, kawasan industri, co-working, lahan parkir.
- **Lifecycle:** akuisisi → sewa/bangun → operasi → pemeliharaan → revaluasi/disposal (Fase 31).

## 4D. Hasil (Output)
- **Smart Building Console:** denah lantai + okupansi live, HVAC/energi per zona, tagihan utilitas per tenant, ESG per gedung.
- **Digital Twin Viewer:** model 3D/2.5D per properti, overlay work order & sensor, simulasi what-if.
- **Flex-Space Revenue:** okupansi, rate per jam, pendapatan incremental per properti.
- **EPC Dashboard:** kurva-S terikat BIM, progres per komponen, status MC & kapitalisasi.
- **Audit:** `mall:audit-billing` + `epc:audit` (yang sudah ada) + `proptech:audit` (pembacaan sensor = tagihan utilitas; progres BIM = progres WBS; 0 selisih).

## 4E. Ide Pengembangan Lanjutan
- **Predictive Facility Maintenance:** pola getaran/arus sensor → prediksi kegagalan chiller/lift → work order otomatis sebelum rusak (memperluas Fase 40.2 ke gedung).
- **Grid-Interactive Building:** simulasi demand response — gedung menurunkan beban saat tarif listrik puncak, selisihnya dihitung sebagai penghematan & kredit ESG.
- **Land Bank & Joint Development:** portal lahan milik grup → penawaran ke mitra (Pilar 8 partner) → skema bagi hasil terhubung modul Partner (Fase 47.4).
- **Carbon-Positive Retrofit Advisor:** usulan renovasi gedung dengan payback emisi & finansial.


## 4F. Spesifikasi implementasi minimum (MVP vertical slice)

- **Modul & prefiks:** Mall (`mall_`), Proptech (`prp_`), Epc (`epc_`), Asset (`ast_`), Egy (`egy_` untuk meter), Esg (`esg_`).
- **Status audit:** Mall ✅ (rute billing/auto-debit tanpa role → R3.1) · Epc 🟡 (tanpa ledger & tanpa integrasi Asset) · Proptech 🟠.
- **Slice 1 — Tagihan utilitas dari bacaan aktual:** zona (`prp_building_zones`) → sensor/meter (`prp_building_sensors`) → bacaan per interval → event `prop.meter.read.v1` → Mall menambah baris utilitas di invoice tenant (tarif dari config/kontrak) → Esg mencatat Scope 2.
- **Slice 2 — Flex-space:** ruang (`prp_flex_spaces`) → booking per jam anti-overlap (`lockForUpdate` pada slot) → `hold` saat booking, `capture` saat check-in, `release`/no-show fee sesuai aturan.
- **Slice 3 — EPC → kapitalisasi:** MC tersertifikasi memposting CIP & utang kontraktor (dengan retensi) → BAST final memancarkan `epc.project.handed_over.v1` → Asset membuat aset tetap & mereklasifikasi CIP lewat Contract milik Asset (bukan tulis tabel `ast_` dari Epc).
- **Slice 4 — BIM terikat WBS:** komponen BIM (`prp_twin_components`) ditautkan ke node WBS; progres fisik = komponen selesai / total bobot.
- **Posting ledger:**

| Kejadian | Debit (−) | Kredit (+) |
|---|---|---|
| Baris utilitas tenant | `ar:mall:tenant:{id}:IDR` | `mall:utility_revenue:IDR` |
| MC tersertifikasi | `ast:cip:{project}:IDR` | `ap:epc:contractor:{party}:IDR` + `epc:retention_payable:{project}:IDR` |
| Kapitalisasi (BAST final) | `ast:fixed_assets:IDR` | `ast:cip:{project}:IDR` |
| Flex-space (capture) | `escrow:payment:IDR` | `prp:flex_revenue:IDR` |

- **Rute & role:** `/proptech` (`facility_manager`; `tenant` melihat konsumsi & tagihannya) · `/epc` (`epc_manager`, `site_supervisor`; `asset_manager` untuk kapitalisasi) · `/flex` (customer, `facility_manager`).
- **Command:** `prp:ingest-readings` (simulasi) · `mall:generate-invoices` memakai bacaan · `epc:audit`, `proptech:audit` (nama sesuai 4D).
- **Audit dua sumber:** Σ(konsumsi × tarif) = baris utilitas invoice; Σ MC net + retensi = posting AP + retensi; saldo CIP = Σ MC − Σ kapitalisasi; progres BIM = progres WBS.
- **Test kunci:** bacaan duplikat tidak menggandakan tagihan; booking flex overlap ditolak; kapitalisasi sebelum BAST final → ditolak; progres > 100% → ditolak.
- **Skala:** T1 = 2 properti, 200 unit, 50 sensor × 7 hari per jam (±8 rb bacaan); T2 = 1 jt bacaan; T3 = visi 4A.

- **Jalan pintas terlarang** (pola yang ditemukan audit atau paling mungkin muncul di pilar ini): tagihan utilitas dari estimasi, bukan bacaan; Proptech/Epc menulis tabel modul lain (`mall_*`, `ast_*`); CIP/escrow sebagai kolom angka tanpa ledger; booking flex-space tanpa kunci slot.
- **Bukti selesai minimum** (selain V1–V12 di `PROGRESS.md` §P8): rute `/proptech`, `/epc`, `/flex` dengan role; test bacaan duplikat tidak menggandakan tagihan; test kapitalisasi sebelum BAST final ditolak; test overlap booking ditolak; `proptech:audit` & `epc:audit` + fixture korupsi.

---

# PILAR 5 — LOGISTIK MULTIMODA, SCM & GUDANG

## 5A. Skala
- **Volume:** dari basis 200 ribu shipment (Fase 25) → **5 juta shipment/tahun**, 100 juta tracking event/tahun (hash-chain), 100 ribu unit armada (truk, kapal, pesawat, drone), 50 ribu kontainer, 1.000 hub & CFS.
- **Telematik:** 100 ribu unit memancarkan posisi/sensor tiap 30 detik → 288 juta titik GPS/hari; telemetri suhu reefer 10 juta pembacaan/hari.
- **WMS:** 500 gudang, 10 juta bin, 1 miliar baris stok-blok/tahun, 50 juta tugas pick/hari.
- **Reverse logistics:** 5 juta pengiriman balik/tahun (retur, daur ulang, limbah).

## 5B. Operasional
- **Reverse Logistics & Circular Economy:** armada Logistics mengangkut barang retur, oli bekas AutoServe, jelantah Resto, scrap pabrik → masuk sebagai **bahan baku** ke Manufacturing (biodiesel, remanufaktur) dengan price tag dari Pricing Engine → manfaat ESG terhitung otomatis (pengurangan emisi pembuangan, skor sirkularitas) → kredit karbon/ESG menaik.
- **Cold-Chain Blockchain Automation:** sensor IoT suhu (Agri/farmasi/wagyu) → jika breach > 10 menit, **Payment Gateway otomatis menahan (hold)** pembayaran subkontraktor pengangkut sampai dispute selesai; pembacaan suhu masuk hash-chain shipment → bukti kepatuhan untuk klaim asuransi & sertifikasi.
- **Autonomous Drone & Last-Mile Robotics:** dispatcher dapat menugaskan leg terakhir ke drone/robot dari Hub (radius ≤ 15 km, berat ≤ 5 kg) → routing mempertimbangkan berat/baterai/no-fly zone simulasi → POD drone berupa foto + geo-hash → masuk chain of custody.
- **Dynamic network:** rate card & kapasitas berubah real-time (memperluas lgx_rate_cards) mengikuti permintaan musiman, harga BBM, dan okupansi armada.
- **S&OP terhubung:** Control Tower (Fase 53) memasok forecast ke seluruh pilar.

## 5C. Cakupan
- **Lini:** Logistics (multimoda, chain of custody) + WMS (gudang) + Agri (cold chain) + Resto (replenishment) + Store/Distribusi (outbound) + Trade (leg internasional) + EPC (logistik proyek) + ESG (emisi transportasi) + AutoServe (limbah oli).
- **Mode:** jalan, laut, udara, kereta (simulasi), multimoda, drone/robot last-mile.
- **Peran:** shipper, carrier subkontrak, hub operator, dispatcher, driver, drone pilot (simulasi).

## 5D. Hasil (Output)
- **Control Tower Eksekutif:** OTIF end-to-end, dwell time, margin per lane, biaya CO2/shipment.
- **Circular Economy Ledger:** tonase retur/limbah terangkut & terpakai ulang, nilai penghematan, kredit ESG.
- **Cold-Chain Compliance Center:** riwayat suhu per kontainer/pengiriman, hold pembayaran, dispute.
- **Drone Ops Console:** misi aktif, kepatuhan radius, biaya per pengiriman vs truk.
- **Audit:** `lgx:audit-billing`, `lgx:verify-custody`, `wms:audit` (sudah ada) + `logi:circular-audit` (retur masuk = stok bahan baku/biaya limbah; hold pembayaran = nilai dispute; 0 selisih).

## 5E. Ide Pengembangan Lanjutan
- **Autonomous Truck Corridor:** koridor jalan tol khusus armada otonom (simulasi) dengan telematik penuh.
- **Freight Exchange Marketplace:** lelang muatan dua arah (backhaul kosong → muatan balik) dengan escrow B2B (Fase 61.4).
- **Emission-Aware Routing:** planner rute memilih opsi dengan emisi terendah saat pelanggan memilih "green shipping" (surcharge kecil masuk ESG revenue).


## 5F. Spesifikasi implementasi minimum (MVP vertical slice)

- **Modul & prefiks:** Logistics (`lgx_`), Wms (`wms_`), ControlTower (`sct_`), Manufacturing (`mfg_`), Pricing (`pric_`), Esg (`esg_`), Payment.
- **Status audit:** Logistics ✅ (acuan terbaik) · Wms/ControlTower 🟡 · fitur Fase 79–80 (reverse, cold-chain hold, drone) 🟠.
- **Slice 1 — Reverse logistics → bahan baku:** jenis shipment `reverse` (retur, oli bekas AutoServe, jelantah Resto, scrap) → diterima di pabrik sebagai `mfg_material_lots` dengan harga dari Pricing → manfaat emisi dicatat Esg.
- **Slice 2 — Cold-chain hold:** pelanggaran suhu > 10 menit (dari `TemperatureReading`) → event `lgx.temperature.breached.v1` → utang ke carrier untuk leg itu dipindah ke akun *on hold* sampai sengketa selesai → bukti suhu masuk hash-chain custody.
- **Slice 3 — Drone last-mile:** leg tipe `drone` dengan batasan radius ≤ 15 km, berat ≤ 5 kg, zona larangan terbang (config) → POD foto + geo-hash.
- **Posting ledger:**

| Kejadian | Debit (−) | Kredit (+) |
|---|---|---|
| Akru biaya carrier per leg | `lgx:carrier_cost:IDR` | `ap:carrier:{id}:IDR` |
| Tahan pembayaran (breach) | `ap:carrier:{id}:IDR` | `lgx:carrier_payable_on_hold:IDR` |
| Lepas tahanan | `lgx:carrier_payable_on_hold:IDR` | `ap:carrier:{id}:IDR` |
| Material daur ulang diterima | `inv:materials:IDR` | `mfg:recycled_material_clearing:IDR` |

- **Rute & role:** rute Logistik yang ada + `/logistics/reverse` (`hub_operator`), `/logistics/cold-chain` (`logistics_admin`), portal `carrier` melihat status tahanan.
- **Command:** `lgx:detect-temperature-breach` · `logi:circular-audit` (nama sesuai 5D).
- **Audit dua sumber (`logi:circular-audit`):** shipment reverse diterima = lot material dibuat; saldo `on_hold` = Σ sengketa terbuka.
- **Test kunci:** breach 9 menit → tidak hold; breach 11 menit → hold sekali walau pembacaan berulang; drone melebihi berat → ditolak saat perencanaan rute.
- **Skala:** T1 = 2 rb shipment, 50 armada, 5 rb pembacaan suhu; T2 = 200 rb shipment (sudah pernah ada `LogisticsLargeSeeder`); T3 = visi 5A.

- **Jalan pintas terlarang** (pola yang ditemukan audit atau paling mungkin muncul di pilar ini): tahan pembayaran carrier berupa kolom status tanpa ledger; breach dihitung per pembacaan sehingga ganda; batas drone hanya ditegakkan di test; material daur ulang tanpa lot Manufaktur.
- **Bukti selesai minimum** (selain V1–V12 di `PROGRESS.md` §P8): test breach 9 menit (tanpa hold) vs 11 menit (hold sekali walau pembacaan berulang); test lepas tahanan memulihkan utang; test drone melebihi berat ditolak saat perencanaan; `logi:circular-audit` + fixture korupsi.

---

# PILAR 6 — MANUFAKTUR, DISTRIBUSI & KEBIJAKAN HARGA

## 6A. Skala
- **Pabrik:** 100 fasilitas manufaktur (termasuk 5 dapur sentral), 50.000 work center/mesin, 1 juta SPK/tahun, 100 juta baris konsumsi bahan.
- **SKU & harga:** 1 juta SKU (sparepart, produk jadi, bahan grosir) dengan harga berfluktuasi real-time; 1 miliar baris price tick/tahun (partitioned).
- **Distribusi:** 1.000 distributor, 50.000 outlet, 10 juta order sell-in/tahun, 100 juta laporan sell-out/bulan.
- **VMI:** 5.000 pemasok dengan akses stok rak, 1 juta pengiriman VMI/tahun.
- **C2M:** 500 ribu desain konsumen/tahun dikonversi ke instruksi produksi.

## 6B. Operasional
- **Algorithmic & Surge Pricing:** mesin Pricing (Fase 44) dinaikkan menjadi **engine detik-per-detik**: harga suku cadang Store, ongkir Logistics, dan bahan baku grosir berfluktuasi mengikuti supply-demand global (feed komoditas simulasi), level stok WMS, musim, dan okupansi gudang — dengan guardrail floor price / ceiling (HET simulasi) dan log audit setiap perubahan harga (harga pada dokumen tetap immutable saat order, Fase 44.4).
- **Vendor-Managed Inventory:** pemasok mendapat akses read-only + trigger PO khusus via API Integration (API v2, Fase 55) → mereka memantau stok rak WMS milik kita → saat menyentuh titik pesan ulang, sistem menerbitkan **PO otomatis tanpa staf pengadaan** (dengan plafon per kontrak); penerimaan masuk GRN 3-way match biasa.
- **Made-to-Order (C2M):** pembeli Store B2C mendesain suku cadang modifikasi mobil secara 3D (parametric configurator) → dikonversi menjadi **instruksi routing & BOM khusus** di pabrik (link ke PLM EBOM/MBOM) → masuk MRP sebagai planned order → produksi → pengiriman via Logistics; harga dihitung dari BOM live + complexity factor.
- **S&OP & MRP** (sudah Fase 36) tetap jadi otak perencanaan dengan input forecast dari Pilar 5 & 3.

## 6C. Cakupan
- **Lini:** Manufacturing (BOM, MRP, costing, QMS) + WMS + Distribution + Pricing + Store (B2C) + B2B Marketplace + Supplier + Procurement + Logistics + PLM/R&D + ESG.
- **Barang:** sparepart otomotif, produk agri olahan, barang jadi elektronik/alat, produk resto kemasan, komponen OEM.
- **Saluran:** retail langsung, distributor grosir, agen, marketplace B2B, ekspor.

## 6D. Hasil (Output)
- **Pricing War Room:** pergerakan harga per SKU per detik, margin per transaksi, alarm penyimpangan HET/floor.
- **VMI Control Panel:** stok per rak pemasok, auto-PO terbit, fill rate, denda keterlambatan.
- **C2M Production Board:** desain konsumen → status SPK → biaya aktual vs penawaran.
- **Distributor Performance:** sell-in vs sell-out, rebate, tier, stock cover.
- **Audit:** `mfg:audit-costing`, `dist:audit`, `wms:audit` + `pricing:audit` (tick harga log lengkap & konsisten dengan dokumen order; auto-PO VMI = komitmen anggaran; 0 selisih).

## 6E. Ide Pengembangan Lanjutan
- **Generative Design C2M:** AI menghasilkan varian desain sparepart (massa, kekuatan) lalu diuji simulasi sebelum masuk produksi.
- **Price Arbitrage Bot:** deteksi selisih harga antar wilayah/kanal → rekomendasi alokasi stok untuk menangkap margin (tanpa pelanggaran kontrak harga).
- **Digital Product Passport:** setiap unit barang jadi mendapat passport hash (materi, karbon, daur ulang) untuk kepatuhan pasar ekspor (Pilar 7 & ESG).


## 6F. Spesifikasi implementasi minimum (MVP vertical slice)

- **Modul & prefiks:** Manufacturing (`mfg_`), Distribution (`dist_`), Pricing (`pric_`), Procurement (`prc_`), Wms (`wms_`), Plm (`plm_`), Store (`store_`).
- **Status audit:** Manufacturing/Distribution/Pricing/Procurement ✅ (jalur galat controller → R2.3) · fitur Fase 81–82 (surge pricing, VMI, C2M) 🟠 · tabel `prc_price_ticks`/`prc_frozen_quotes` milik Pricing salah prefiks.
- **Slice 1 — Surge pricing ber-guardrail:** `pric_price_ticks` (append-only) dihitung dari stok WMS, permintaan, musim (aturan S1 tertulis) → floor/ceiling/HET wajib → harga terkunci di dokumen saat order via `PriceLocker` (immutable) → setiap perubahan tercatat audit trail.
- **Slice 2 — VMI auto-PO:** pemasok (role `supplier`) melihat stok rak miliknya → saat stok < titik pesan ulang, sistem membuat PO otomatis dalam plafon kontrak (Contract) → di atas plafon wajib approval → penerimaan lewat GRN 3-way match yang sudah ada.
- **Slice 3 — C2M:** konfigurator parametrik (pilihan terbatas, S1) di Store → membuat BOM & routing turunan dari template PLM → planned order di MRP → harga = biaya BOM live × faktor kompleksitas.
- **Posting ledger:** memakai jurnal Manufacturing/Procurement yang sudah ada (WIP/FG/COGS/AP); tidak ada akun baru kecuali `pric_` tidak memposting.
- **Rute & role:** `/pricing/surge` (`pricing_manager`) · portal `/portal/vmi` (`supplier`) · `/store/c2m` (customer) + `/manufacturing/c2m` (`planner`).
- **Command:** `pricing:tick` (terjadwal) · `vmi:scan-reorder` · `pricing:audit`, `vmi:audit`.
- **Audit dua sumber:** setiap baris order = harga terkunci pada saat order (bukan tick terbaru); tick tidak melanggar floor/ceiling; auto-PO ≤ plafon kontrak.
- **Test kunci:** tick di luar guardrail → ditolak & tercatat; order lama tidak berubah harga saat tick baru; VMI dipanggil ulang → satu PO; desain C2M tidak valid (dimensi di luar batas) → 422.
- **Skala:** T1 = 1 rb SKU, 10 rb tick; T2 = 1 jt tick; T3 = visi 6A.

- **Jalan pintas terlarang** (pola yang ditemukan audit atau paling mungkin muncul di pilar ini): harga order mengikuti tick terbaru (bukan harga terkunci); tabel `prc_*` dibuat di Pricing; auto-PO tanpa plafon kontrak/approval; jalur galat `back()->errors()->add()`.
- **Bukti selesai minimum** (selain V1–V12 di `PROGRESS.md` §P8): test harga order lama tidak berubah setelah tick baru; test tick di luar guardrail ditolak & tercatat; test VMI dipanggil ulang → satu PO; test HTTP jalur galat (redirect + pesan, bukan 500); `pricing:audit` + fixture korupsi.

---

# PILAR 7 — PERDAGANGAN INTERNASIONAL & PENGADAAN

## 7A. Skala
- **Koridor:** 10 ribu koridor dagang (negara/pelabuhan × komoditas), 50 ribu L/C & garansi/tahun, 10 juta dokumen dagang/tahun (invoice, BL/AWB, CoO, PIB/PEB).
- **Volume:** 500 ribu kontainer ekspor-impor/tahun, nilai transaksi triliunan rupiah simulasi, kurs 100+ mata uang (Fase 48).
- **Pengadaan:** 100 ribu lelang pengadaan/tahun, 1 juta penawaran otomatis, 10 ribu pemasok global.
- **Karbon:** 50 ribu sertifikat jejak karbon per kontainer/tahun (CBAM).

## 7B. Operasional
- **Cross-Border Clearing House Berbasis Kripto:** menghindari lambatnya SWIFT — importir men-deposit **stablecoin internal** (terhubung Pilar 2) ke escrow Trade; saat Bill of Lading / POD diunggah dan hash-nya terverifikasi pada chain of custody Logistik, smart-contract simulasi **otomatis melepas (release)** dana ke penjual; settlement real-time 24/7, rekonsiliasi ke ledger multi-currency dengan kurs tersimpan.
- **CBAM Compliance:** modul Trade membaca data emisi dari modul ESG per pabrik per kontainer → **otomatis mencetak dokumen sertifikasi jejak karbon** per kontainer ekspor ke Uni Eropa (faktor emisi, metodologi, nomor gapless) → terhubung ke pelaporan & kredit karbon (Fase 60).
- **AI Contract Bidding:** saat lelang pengadaan dibuka, agen AI (deterministik, dapat diaudit — selaras `ai:audit` Fase 64) merayapi harga komoditas global, riwayat menang/kalah, dan skor risiko → menyusun penawaran harga optimal + draf klausul di modul Contract → **staf manusia menyetujui** (four-eyes) sebelum submit.
- **Trade docs automation:** seluruh dokumen (L/C, inkaso, PEB/PIB, sertifikat) diperiksa otomatis oleh engine yang sudah ada (Fase 50.2) dengan penambahan cross-check ke data emisi & screening sanksi.

## 7C. Cakupan
- **Lini:** Trade Ops (ekspor-impor) + Trade Finance (L/C, garansi, SCF) + Procurement/Tender + Treasury (kurs, hedging) + Logistics (leg internasional) + ESG (CBAM) + Crypto (stablecoin) + Contract + Party (KYC lintas negara).
- **Instrumen:** L/C, inkaso D/P-D/A, open account, garansi bank, stablecoin escrow, barter komoditas (simulasi).
- **Regulasi (simulasi):** Incoterms 2020, UCP 600, CBAM, lartas, P3B, sanksi internasional.

## 7D. Hasil (Output)
- **Global Trade Cockpit:** peta koridor, posisi dana escrow, L/C jatuh tempo, exposure per negara.
- **CBAM Certificate Center:** tonase CO2 per kontainer, dokumen siap unggah ke sistem UE (simulasi), biaya bea karbon terhitung.
- **AI Bid Desk:** penawaran yang diajukan, win rate, margin vs benchmark, jejak persetujuan manusia.
- **Audit:** `trade:audit`, `tf:audit` (sudah ada) + `clearing:audit` (stablecoin escrow = komitmen L/C/shipment; sertifikat karbon = emisi ESG terverifikasi; 0 selisih).

## 7E. Ide Pengembangan Lanjutan
- **Barter & Countertrade Engine:** skema barter komoditas antar negara (gula ↔ minyak sawit) dengan penilaian nilai & pelunasan bertahap.
- **Trade-Linked Insurance Wrap:** asuransi kargo + politik risiko digabung dalam satu premi berbasis risiko koridor (memperluas Fase 50.6).
- **Autonomous Customs Broker:** agen AI menyiapkan dokumen bea cukai lengkap & mengajukan perbaikan diskrepansi otomatis.


## 7F. Spesifikasi implementasi minimum (MVP vertical slice)

- **Modul & prefiks:** Trade (`trd_`), TradeFinance (`tf_`), Procurement (`prc_`), Treasury (`trs_`), Crypto (`crypto_`), Logistics (custody), Esg (`esg_`), Contract.
- **Status audit:** Trade/TradeFinance/Treasury 🟡 · fitur Fase 83–84 (clearing house, CBAM, AI bidding) 🟠 · AI bidding bergantung Fase 64 (⬜).
- **Slice 1 — Clearing house stablecoin:** importir menyetor aset `USDT` (atau stablecoin internal) ke escrow per L/C/kontrak → hash BL/POD diverifikasi terhadap custody chain Logistik (`lgx.custody.bl_verified.v1`) → rilis otomatis ke eksportir → kurs disimpan untuk revaluasi Treasury.
- **Slice 2 — Sertifikat CBAM:** per kontainer ekspor ke UE, emisi diambil dari Esg (data terverifikasi, bukan input bebas) → sertifikat bernomor gapless → dokumen di DocumentStore.
- **Slice 3 — Asisten penawaran tender:** agen deterministik (seed + snapshot input tersimpan) mengusulkan harga & klausul → **wajib** approval manusia (four-eyes) via ApprovalEngine sebelum submit ke tender Procurement.
- **Posting ledger:**

| Kejadian | Debit (−) | Kredit (+) |
|---|---|---|
| Setoran escrow | `wallet:user:{importir}:USDT` | `tf:escrow:{lc}:USDT` |
| Rilis ke eksportir | `tf:escrow:{lc}:USDT` | `wallet:user:{eksportir}:USDT` |
| Revaluasi kurs | `trs:fx_gain_loss:IDR` / akun aset | sebaliknya (sesuai arah selisih) |

- **Rute & role:** `/trade-finance/clearing` (`tf_officer`, importir/eksportir melihat escrow-nya) · `/trade/cbam` (`compliance_officer`) · `/procurement/bid-assistant` (`procurement`; approval `procurement_manager`).
- **Command:** `clearing:audit` · `tf:audit`.
- **Audit dua sumber (`clearing:audit`):** saldo escrow = Σ escrow terbuka; Σ rilis = Σ BL terverifikasi; sertifikat CBAM = emisi Esg terverifikasi.
- **Test kunci:** rilis tanpa hash BL valid → ditolak; rilis dobel → sekali; usulan AI tanpa approval → tidak bisa submit; rekonstruksi usulan dari snapshot = identik.
- **Skala:** T1 = 20 koridor, 100 L/C, 200 kontainer; T3 = visi 7A.

- **Jalan pintas terlarang** (pola yang ditemukan audit atau paling mungkin muncul di pilar ini): rilis escrow tanpa verifikasi hash BL/POD di custody chain; CBAM dari masukan emisi bebas; usulan AI langsung submit tanpa approval; usulan AI tanpa snapshot input (tidak bisa direkonstruksi).
- **Bukti selesai minimum** (selain V1–V12 di `PROGRESS.md` §P8): test rilis tanpa hash valid ditolak & rilis ganda → sekali; test rekonstruksi usulan dari snapshot identik; test submit tanpa approval ditolak; `clearing:audit` + fixture korupsi.

---

# PILAR 8 — TATA KELOLA, KORPORASI & INTEGRASI ENTERPRISE

## 8A. Skala
- **Talenta:** 500 ribu karyawan & mitra terdaftar di HCM (multi-entitas, multi-negara), 50 ribu shift/hari, 100 juta baris absensi/tahun.
- **Tata kelola:** 1 juta pemegang hak suara (karyawan, saham, franchisee, partner), 10 ribu voting/tahun, 100 ribu permohonan approval/bulan.
- **Agregasi:** 20 entitas hukum, 16 domain lini, konsolidasi keuangan bulanan dengan jutaan baris eliminasi.
- **Agri-tech:** 100 ribu petak lahan plasma dipantau citra satelit, 1 juta pembacaan NDVI/bulan.

## 8B. Operasional
- **Internal Gig Economy (Talent Marketplace):** manajer Resto/Logistik/Mall membuat **"Bounty"** (tugas dadakan: bongkar muat, rush hour outlet, event setup) → karyawan lintas unit bisnis yang memenuhi syarat (sertifikasi, lokasi, jam kerja) mengambil shift di luar jam kerja → sistem memvalidasi aturan upah lembur UU 22/2009 & K3 → pembayaran **per jam otomatis via Core Banking** saat shift selesai + POB (proof of work) digital → biaya masuk ke pusat biaya unit pemesan; KPI internal mobility & utilisasi tenaga kerja.
- **Precision Agri-Tech:** API citra satelit (simulasi NDVI) memantau kebun plasma → **cicilan modal pembiayaan ke petani hanya dicairkan bila indeks kehijauan (NDVI) memenuhi standar** (ratchet kontrak Fase 62.2) → gagal panen → restrukturisasi otomatis via approval engine → kualitas panen grade A/B/C (sudah Fase 62.3) menentukan harga beli.
- **DAO Corporate Governance:** karyawan, pemegang saham (token RWA Pilar 2), mitra waralaba, & partner memiliki **hak suara desentralisasi** berbobot Paspor Digital/token → voting topik strategis (buka cabang Resto di kota B, akuisisi pabrik, ekspansi negara) → kuorum, quorum-weighted tally, masa kampanye, hasil voting memicu pembuatan **proyek Contract/EPC/Investasi otomatis** bila disetujui (jejak hash-chain, emulating blockchain vote ledger).
- **Enterprise integration:** seluruh 16+ lini terhubung via Universal Event Spine (konsep bersama di atas) + Group Dashboard P&L + RBAC 32+ role + Approval Engine + Audit generik.

## 8C. Cakupan
- **Lini:** HCM (talenta, payroll, shift) + Party/PartyRole + RBAC & Approval + Contract + Governance/DAO + Agri (petani) + Core Banking (pembayaran bounty) + Group Finance (konsolidasi) + seluruh lini lain sebagai konsumen integrasi.
- **Stakeholder:** karyawan, mitra, petani plasma, franchisee, pemegang saham, regulator (simulasi).
- **Wilayah:** multi-negara (mengikuti ekspansi JV Fase 51).

## 8D. Hasil (Output)
- **Talent Mobility Dashboard:** bounty terbuka, tingkat pengisian, biaya tenaga kerja fleksibel, kepuasan karyawan.
- **Agri Satellite Monitor:** peta NDVI per petak, status cicilan terkait prestasi, risiko gagal panen.
- **DAO Voting Portal:** proposal aktif, distribusi bobot suara, hasil & eksekusi otomatis terhadap sistem.
- **Group Command Center** (memperluas Fase 57.4): P&L 16 lini, kesehatan seluruh `*:audit`, status seluruh event spine.
- **Audit:** `hcm:audit` (sudah) + `governance:audit` (Σ bobot suara = paspor/token terbit; bounty terbayar = payroll/ledger; NDVI payout sesuai ratchet; 0 selisih).

## 8E. Ide Pengembangan Lanjutan
- **Skills Ontology & AI Matching:** kecocokan talenta ke bounty/proyek lintas lini berbasis skill graph.
- **Predictive Workforce Planning:** S&OP permintaan operasional → kebutuhan tenaga kerja 4 minggu ke depan → jadwal shift otomatis.
- **Ecosystem Scorecard Lintas Pilar:** satu skor kesehatan gabungan (finansial, mutu, ESG, talenta) per entitas untuk keputusan alokasi modal.


## 8F. Spesifikasi implementasi minimum (MVP vertical slice)

- **Modul & prefiks:** Hcm (`hcm_`), Governance (`gov_` — dipisah dari Agri & Hcm), Agri (`agri_`), Party, Contract, Core (RBAC/approval), EnterpriseFinance (`ef_`), Intercompany (`ic_`).
- **Status audit:** Party/Contract/RBAC/Approval ✅ · Hcm 🟠 (fondasi Fase 58 dikerjakan ulang di PROGRESS R6.3) · Agri/EF/IC 🟡 · fitur Fase 85–86 (gig, NDVI, DAO) 🟠.
- **Prasyarat:** HCM inti dulu — karyawan (NIK terenkripsi + blind index), struktur organisasi, shift & absensi, payroll dengan aturan pajak/BPJS yang dinyatakan (S1 tarif tetap tertulis, atau S2 TER berparameter), jurnal payroll.
- **Slice 1 — Gig bounty internal:** manajer unit membuat bounty (biaya ke pusat biaya unit) → karyawan memenuhi syarat (sertifikat Edu, lokasi, batas jam lembur) mengambil → bukti kerja → approval → dibayar lewat payroll berikutnya (bukan transfer di luar payroll).
- **Slice 2 — Pencairan cicilan berbasis NDVI:** pemindaian satelit tersimulasi (`agri_satellite_scans`) per petak → aturan ratchet kontrak (Contract) → cicilan modal dicairkan hanya bila NDVI ≥ ambang → gagal panen memicu approval restrukturisasi.
- **Slice 3 — Voting tata kelola:** proposal (`gov_proposals`) → snapshot bobot suara (token RWA, kepemilikan saham simulasi, karyawan) → voting dengan kuorum → `gov.proposal.passed.v1` → draf proyek di Contract/Epc yang **tetap** butuh approval manusia.
- **Posting ledger:**

| Kejadian | Debit (−) | Kredit (+) |
|---|---|---|
| Payroll diakui | `expense:hcm:salary:{cost_center}:IDR` | `hcm:salary_payable:{employee}:IDR` + `hcm:pph21_payable:IDR` + `hcm:bpjs_payable:IDR` |
| Bounty selesai | `expense:{unit}:gig_labor:IDR` | `hcm:salary_payable:{employee}:IDR` |
| Pembayaran gaji | `hcm:salary_payable:{employee}:IDR` | `wallet:user:{id}:IDR` (atau `clearing:external:IDR` untuk bank luar) |
| Pencairan cicilan petani | `agri:farmer_loan_receivable:{farmer}:IDR` | `wallet:user:{farmer}:IDR` |

- **Rute & role:** `/hcm` (`hcm_manager`; karyawan melihat slipnya sendiri) · `/gig` (`unit_manager`, `employee`) · `/agri/ndvi` (`agri_officer`, `farmer` melihat petaknya) · `/governance` (`governance_admin`, `voter`).
- **Command:** `hcm:run-payroll {periode}` · `agri:scan-ndvi` (simulasi) · `gov:close-voting` · `hcm:audit`, `governance:audit` (nama sesuai 8D).
- **Audit dua sumber:** Σ baris payroll = posting payroll; bounty terbayar = baris payroll bounty; Σ bobot suara = snapshot bobot; pencairan NDVI hanya pada scan ≥ ambang.
- **Test kunci:** payroll periode sama 2× → sekali; bounty diklaim karyawan tanpa sertifikat → ditolak; voting setelah tutup → ditolak; proposal lolos tidak langsung mengeksekusi tanpa approval.
- **Skala:** T1 = 500 karyawan, 3 bulan absensi, 100 petani, 20 proposal; T3 = visi 8A.

- **Jalan pintas terlarang** (pola yang ditemukan audit atau paling mungkin muncul di pilar ini): pajak/BPJS tarif datar tanpa label simulasi & persetujuan (P10); status payroll di-hard-code `approved`; gaji/rekening default diam-diam; bounty dibayar di luar payroll; voting tanpa snapshot bobot; proposal lolos langsung mengeksekusi; halaman gaji tanpa role.
- **Bukti selesai minimum** (selain V1–V12 di `PROGRESS.md` §P8): rute `/hcm` hanya `hcm_manager`/`admin` (karyawan hanya slipnya sendiri); test payroll periode sama 2× → sekali; test bounty tanpa sertifikat ditolak; test voting setelah tutup ditolak; `hcm:audit` & `governance:audit` + fixture korupsi.

---

# 4 LINI BISNIS TAMBAHAN (TETAP DALAM SATU WEBSITE MONOLITH TERPADU)

> Empat lini di bawah ini adalah perluasan dominasi 8 pilar ke sektor baru. Sama seperti pilar lain, semuanya **terhubung penuh** ke ekosistem: Ledger double-entry, Payment Hub, Party/KYC, Contract, RBAC, Approval Engine, Universal Event Spine, dan Group Dashboard. **Skala dikembangkan tanpa batasan** — bersifat dummy/simulasi sehingga angka bisa setinggi mungkin.

---

# PILAR 9 — RUMAH SAKIT & LAYANAN KESEHATAN (HEALTHCARE)

## 9A. Skala (Tanpa Batas)
- **Jaringan:** 50 rumah sakit rujukan + 200 klinik + 1.000 puskesmas mitra + 5.000 dokter rekanan + 50 laboratorium & 100 apotek jaringan → visi akhir: **10 juta pasien terdaftar**, 100 juta kunjungan/tahun.
- **Rekam medis:** 10 miliar baris clinical event (vital sign, order obat, hasil lab, radiologi) — 1 juta vital sign/jam dari monitor ICU IoT.
- **Operasional:** 100.000 tempat tidur, 5 juta rawat inap/tahun, 50 juta transaksi farmasi/tahun, 2 juta unit darah & produk biologis bertanggal, 100 juta laboratorium result/tahun.
- **Perangkat medis:** 1 juta aset medis (infusion pump, ventilator, CT, MRI) dengan telematik 24/7.
- **Talenta:** 500 ribu tenaga kesehatan (dokter, perawat, apoteker, teknisi) terjadwal 24/7.

## 9B. Operasional
- **Patient Identity & Longitudinal Record:** satu identitas paspor kesehatan (memperluas Paspor Kendaraan → **Human Passport**) hash-chain: alergi, diagnosis kronis, riwayat obat, riwayat bedah, imunisasi — dapat dipindai QR di pendaftaran dan berlaku lintas RS se-grup.
- **Admission → Bed → Care → Discharge (ABCD) Engine:** pendaftaran → alokasi tempat tidur real-time (occupancy per kelas kamar, isolasi, VIP) → clinical pathway (CPG simulasi) menjadwalkan order dokter, lab, farmasi → rekonsiliasi semuanya ke **satu tagihan episode** (bed-day, tindakan, obat, alat habis pakai) → discharge dengan settlement BPJS simulasi + insurance copay + self-pay via Payment Hub.
- **EMR Order-to-Cash:** e-prescription → farmasi menyiap → stok obat terpotong via InventoryService → order masuk tagihan pasien → kamar & ICU terhubung ke tarif bertingkat seperti mall utility; bedah besar memakai **escrow deposit** (hold saat masuk, capture saat pulang, sisa direfund).
- **IoT Critical Care:** monitor pasien memancarkan telemetri (SpO2, ECG, suhu) → ambang batas → **code blue alert** ke perawat via Notification + prioritas antrian → kejadian terekam hash-chain sebagai bukti malpractice/review mutu.
- **Supply & Cold Chain:** darah, vaksin, obat sitostatik disimpan di fridge IoT → breach suhu → quarantine lot otomatis + recall internal + hold pembayaran pemasok (meniru cold-chain Logistics Fase 24.4/5C).
- **Medical Waste & Reverse Logistics:** limbah B3 medis diangkut armada Logistics tersendiri dengan rantai kustodi hash → dimusnahkan / diolah (lingkungan) → kredit ESG.
- **Doctor & Perawat Gig Marketplace:** shift kosong (izin sakit, lonjakan pasien) diposting sebagai **Bounty** di Talent Marketplace Pilar 8 → dokter rekanan mengambil → dibayar per jam per encounter via Core Banking.

## 9C. Cakupan
- **Modul baru:** `Hosp` (`hsp_`): patients, encounters, admissions, beds, orders, prescriptions, labs, radiology, clinical pathways, claims, mortality/morbidity review.
- **Terhubung:** Core Banking & Payment Hub (tagihan, klaim), Inventory (farmasi & alat medis), Logistics (darah, limbah, medis supply chain), WMS (gudang farmasi), Asset (alat medis, depresiasi & maintenance), Contract (kerjasama BPJS/insurance/klinik), Party (dokter & pasien sebagai party), HCM (tenaga kesehatan), ESG (limbah medis), Trade (impor alat & obat), Manufacturing (farmasi lokal simulasi), ESG (emisi & limbah), AI (diagnosis pendukung simulasi).

## 9D. Hasil (Output)
- **Clinical Command Center:** okupansi tempat tidur per kelas, LOS rata-rata, DOR (days of revenue occupancy), pasien menunggu IGD.
- **Revenue Cycle Dashboard:** pemungutan per unit (rawat jalan, rawat inap, bedah, lab, farmasi), aging klaim asuransi/BPJS, denial rate.
- **Patient Passport Publik:** QR pasien berisi ringkasan darurat (alergi, golongan darah, kontak darurat) — privasi ter-encrypt.
- **Supply Integrity:** rantai dingin medis, recall lot obat, stok darah per golongan.
- **Audit:** `hosp:audit` (tagihan episode = ledger; klaim terbayar <= tagihan; stok obat terpotong konsisten; rantai paspor pasien valid; 0 selisih).

## 9E. Ide Pengembangan Lanjutan
- **Telemedicine & e-Pharmacy:** konsultasi jarak jauh → e-resep → pengiriman obat via Logistics last-mile.
- **AI Triage & Diagnostic Support:** model skoring gejala IGD (deterministik/teraudit `ai:audit`) → prioritas antrean, rekomendasi order lab.
- **Hospital Command Center Prediction:** proyeksi pasien masuk besok dari data musim/demam/wabah simulasi → jadwal shift & kamar siap.
- **Medical Tourism Package:** paket RS + tiket + hotel (Pilar 11) + airport transfer (Logistics) dalam satu bundle harga.
- **Genomic & Personalized Medicine Vault:** data genom terenkripsi sebagai aset data (tokenisasi anonim untuk riset — Pilar 2 RWA).
- **Clinical Trial Management:** studi, subjek, endpoint, biaya riset → terhubung R&D PLM (Fase 59) dan kontrak.


## 9F. Spesifikasi implementasi minimum (MVP vertical slice)

- **Modul & prefiks:** Hospital (`hsp_`), Party (pasien & dokter sebagai pihak), Payment, Inventory (farmasi), Wms, Hcm, Insurance (`ins_`), Logistics (darah/limbah).
- **Status audit:** 🟠 — 32 tabel & 9 service sudah ada tetapi tanpa rute/UI/command, akun ledger hanya di test, posting pendapatan bertanda terbalik (PROGRESS R1.2), `hosp:audit` tidak ada.
- **Slice 1 — Pendaftaran → rawat inap → pulang:** pasien (Party + `hsp_patients`, data medis P1 terenkripsi) → encounter → admisi & alokasi tempat tidur (`lockForUpdate` pada bed) → order (tindakan, lab, obat) menjadi baris `hsp_billing_episodes` → discharge = settlement: deposit (hold) di-capture, sisa ke penjamin/BPJS.
- **Slice 2 — Farmasi:** e-resep → dispensing memotong stok farmasi via `InventoryService` (lokasi apotek) → baris tagihan.
- **Slice 3 — Klaim BPJS (S1/S3):** batch klaim per periode → adapter tiruan BPJS (`simulated_*`) → klaim dibayar ≤ ditagih.
- **Posting ledger:**

| Kejadian | Debit (−) | Kredit (+) |
|---|---|---|
| Deposit masuk (hold) | `wallet:user:{pasien}:IDR` | `escrow:payment:IDR` |
| Baris tagihan episode | `ar:hsp:episode:{id}:IDR` | `hsp:service_revenue:{departemen}:IDR` |
| Settlement deposit | `escrow:payment:IDR` | `ar:hsp:episode:{id}:IDR` |
| Klaim BPJS diajukan | `ar:hsp:bpjs:IDR` | `ar:hsp:episode:{id}:IDR` |
| HPP obat | `expense:hsp:pharmacy_cogs:IDR` | `inventory:hsp:pharmacy:IDR` |

- **Rute & role:** `/hospital/registration` (`rs_admin`) · `/hospital/wards` (`nurse`, `doctor`) · `/hospital/pharmacy` (`pharmacist`) · `/hospital/billing` (`cashier_rs`) · portal pasien (melihat episodenya sendiri).
- **Command:** `hosp:post-bed-days` (harian) · `hosp:submit-bpjs-batch` · `hosp:audit`.
- **Audit dua sumber (`hosp:audit`):** total episode = Σ baris tagihan = posting AR; klaim terbayar ≤ klaim diajukan; stok obat keluar = resep yang di-dispense; hash-chain paspor pasien valid.
- **Test kunci:** bed yang sama dialokasikan dua pasien bersamaan → satu gagal; discharge 2× → satu settlement; akses rekam medis pasien lain → 403 + tercatat; dispensing melebihi stok → ditolak.
- **Skala:** T1 = 2 RS, 200 bed, 2 rb pasien, 5 rb encounter; T3 = visi 9A.

- **Jalan pintas terlarang** (pola yang ditemukan audit atau paling mungkin muncul di pilar ini): pendapatan diposting negatif; akun `hsp:*` dibuat di test; data medis P1 plaintext; alokasi bed tanpa `lockForUpdate`; modul tanpa rute/UI; "randomisasi blok" berupa paritas `crc32 % 2`.
- **Bukti selesai minimum** (selain V1–V12 di `PROGRESS.md` §P8): rute `/hospital/*` dengan role per peran; test alokasi bed bersamaan → satu gagal; test akses rekam medis pasien lain → 403 + tercatat; test discharge 2× → satu settlement; `hosp:audit` + fixture korupsi.

---

# PILAR 10 — BEACH CLUB, CLUB NASIONAL & INTERNATIONAL (NIGHTLIFE & ENTERTAINMENT)

## 10A. Skala (Tanpa Batas)
- **Jaringan:** 100 venue (beach club pesisir, club dansa kota, lounge, festival ground) di 50 kota Indonesia + **50 cabang internasional** (Bali/Sydney/Dubai/Tokyo/Los Angeles simulasi) → visi akhir 1.000 venue global.
- **Kapasitas:** 1 juta pengunjung/hari pada puncak festival, 50 juta tiket terjual/tahun, 200 juta transaksi F&B/bottle service/tahun.
- **Event:** 100 ribu event/tahun (DJ set, live act, pool party, sunrise session, turnamen), 10 juta booking meja/VIP/tahun.
- **Kepatuhan umur:** 100 juta verifikasi usia/identitas per tahun (umur min 21 untuk club, 18+ tertentu).
- **Artis & talent:** 50 ribu DJ/performer/brand ambassador terdaftar dengan kontrak & komisi.

## 10B. Operasional
- **Ticketing & Access Control:** tiket digital (NFT-like hash, non-transferable sekali / secondary market terkontrol) → scan QR di pintu + **verifikasi identitas usia** via Human Passport/KYC → gate terbuka (integrasi akses pintu seperti flex-space Pilar 4) → anti-fraud: tiket ganda ditolak, replay hash-chain dicek, resale di platform resmi dengan fee.
- **Table/Bottle Service & VIP:** pemesanan meja dengan minimum spend → deposit via escrow Payment Hub (hold saat booking, capture saat hadir, no-show fee otomatis) → konsumsi tercatat POS khusus venue → tagihan akhir dikirim ke dompet.
- **Dynamic Pricing Tiket:** harga tiket real-time mengikuti countdown (early bird → tier naik), demand forecast, cuaca pesisir, dan okupansi — memakai Pricing Engine (Fase 44) + surge pricing (Fase 81) dengan floor & ceiling.
- **In-house POS & Supply:** bar/resto venue memakai modul Resto (HPP, batch, waste) → bahan F&B dikirim via Logistics cold-chain dari dapur sentral → stok bar (spirit, mixer) terkelola WMS mini-warehouse per venue.
- **Artist & Event Finance:** kontrak performa (Contract) dengan skema bayar (advance + backlog + share door) → pembayaran lintas negara via multi-currency Treasury + stablecoin (Pilar 7) → pajak withholding lintas negara simulasi (Fase 51.7).
- **Safety, Compliance & Risk:** izin keramaian (dokumen 26.8), kapasitas maksimum di-enforce (gate menolak saat penuh seperti parkir), deteksi kerumunan dari density sensor, ambulans on-standby, policy narkoba simulasi (screening acak tercatat), asuransi event (escrow medis Pilar 9).
- **Membership & Loyalty:** membership beach club tahunan (Smart Membership) → hak akses prioritas, disc F&B, poin PTS lintas ekosistem (tukar diskon Store, tiket Resto).

## 10C. Cakupan
- **Modul baru:** `Venue` (`ven_`): venues, zones (pool/beach/dance floor/VIP), tables, events, tickets, artist contracts, capacity, compliance permits, crowd density.
- **Terhubung:** Resto (POS & dapur), Logistics (supply & artis transport), Payment Hub/Escrow, Crypto (tiket & membership token), Party/KYC (usia & artis), Contract (artist & sponsorship), Mall (beach club sebagai tenant kawasan), ESG (energi & limbah event), HCM (bartender, security, crew shift), Trade (impor spirits), Agri (bahan F&B organik), Asset (sound system, bar equipment), ESG (emisi festival & offset).

## 10D. Hasil (Output)
- **Venue Live Board:** okupansi per zone, penjualan tiket per tier, bar revenue per jam, density heatmap keamanan.
- **Event P&L:** pendapatan tiket + bar + sponsorship + VIP vs biaya artis & operasi (dari ledger).
- **Artist Statement:** sisa performa terbayar, penjualan merch, komisi agensi (Fase 45).
- **Safety Dashboard:** kapasitas vs aktual, insiden, verifikasi usia sukses/gagal.
- **Audit:** `venue:audit` (tiket terbit = terpakai + tersisa; escrow meja = konsumsi + refund; bar sales = stok terpotong; 0 selisih).

## 10E. Ide Pengembangan Lanjutan
- **Festival-as-a-Platform:** penyelenggaraan festival multi-hari dengan camping/tiket harian, transport shuttle (Logistics), dan hotel bundle (Pilar 11).
- **Global Day-Part Playbook:** playbook operasional berulang (sunset session, brunch, after-hours) dieksekusi otomatis di semua cabang dengan template menu/staffing.
- **Creator & Content Economy:** booking konten kreator, revenue share live-stream simulasi, clip royalties via ledger.
- **Alcohol-Free & Wellness Beach Clubs:** segmen day-club sehat (juice bar, yoga) dengan pricing berbeda dan crowd family-friendly zone terpisah.
- **NFT Membership & VIP Passport:** membership digital berbobot hak suara DAO event (Pilar 8) untuk memilih line-up.


## 10F. Spesifikasi implementasi minimum (MVP vertical slice)

- **Modul & prefiks:** Venue (`ven_`), Resto (POS bar), Party (KYC usia), Payment, Contract (artis), Logistics.
- **Status audit:** 🟠 — 18 tabel & 5 service tanpa rute/UI/command; idempotency key acak di creator economy; posting terbalik; `venue:audit` tidak ada; prefiks `ven_` juga dipakai Vending (dipindah ke `vnd_`).
- **Slice 1 — Tiket & akses:** venue → zona → event → tier tiket (kuota, harga dari Pricing dengan floor/ceiling) → pembelian (`charge`) → tiket bernomor gapless + hash → pemindaian QR di pintu (sekali pakai; verifikasi usia dari KYC Party) → kapasitas zona ditegakkan.
- **Slice 2 — Meja & bottle service:** booking meja dengan minimum spend → `hold` deposit → `capture` saat hadir / no-show fee sesuai aturan → konsumsi bar memakai POS Resto.
- **Slice 3 — Kontrak artis:** kontrak di Contract (advance + door share) → pembayaran setelah `ven.event.closed.v1`.
- **Posting ledger:**

| Kejadian | Debit (−) | Kredit (+) |
|---|---|---|
| Penjualan tiket | `wallet:user:{id}:IDR` | `ven:ticket_unearned:{event}:IDR` |
| Event selesai | `ven:ticket_unearned:{event}:IDR` | `ven:ticket_revenue:IDR` |
| Deposit meja (hold/capture) | `wallet:user:{id}:IDR` → `escrow:payment:IDR` | `escrow:payment:IDR` → `ven:table_revenue:IDR` |
| Fee artis | `expense:ven:artist_fee:IDR` | `ap:artist:{party}:IDR` |

- **Rute & role:** `/venue/events` (`venue_manager`) · `/venue/gate` (`venue_staff` scanner, tampilan 375 px) · `/venue/tables` (`venue_manager`, customer) · `/venue/artists` (`artist_relations`).
- **Command:** `ven:close-events` · `venue:audit`.
- **Audit dua sumber (`venue:audit`):** tiket terbit = dipindai + belum dipindai + direfund; deposit = capture + release; pendapatan tiket hanya diakui setelah event ditutup.
- **Test kunci:** tiket dipindai 2× → kedua ditolak; usia di bawah batas → ditolak; zona penuh → ditolak; no-show diproses 2× → sekali.
- **Skala:** T1 = 3 venue, 20 event, 10 rb tiket; T3 = visi 10A.

- **Jalan pintas terlarang** (pola yang ditemukan audit atau paling mungkin muncul di pilar ini): tiket tanpa nomor gapless/hash; pindai ulang diterima; key acak di creator economy; pendapatan tiket diakui saat jual; prefiks `ven_` dipakai bersama Vending.
- **Bukti selesai minimum** (selain V1–V12 di `PROGRESS.md` §P8): rute `/venue/gate` (layak di 375 px) dengan role `venue_staff`; test pindai 2× → kedua ditolak; test usia di bawah batas ditolak; test no-show diproses 2× → sekali; `venue:audit` + fixture korupsi.

---

# PILAR 11 — PERHOTELAN & HOSPITALITY (HOTELS & RESORTS)

## 11A. Skala (Tanpa Batas)
- **Jaringan:** 500 properti (city hotel, resort pesisir, villa, apartemen serviced, kapsul, glamping) di 100 kota + 100 internasional → visi 5.000 properti, **500.000 kamar**.
- **Transaksi:** 100 juta room-night/tahun, 1 miliar folio item/tahun (kamar, F&B, spa, laundry, minibar), 200 juta booking channel/tahun (OTA simulasi, corporate, walk-in, loyalty).
- **Operasional:** 200 ribu kamar dibersihkan/hari (housekeeping task 1,8 juta/hari), 10 juta maintenance ticket/tahun, 1 juta spa treatment/bulan, 50 juta loyalty night/bulan.
- **Revenue management:** 500 ribu rate change/hari (1 rate per kamar per channel per hari), 100 juta price quote/tahun.

## 11B. Operasional
- **Central Reservation & PMS (Property Management System):** satu engine reservasi lintas channel (web, app, OTA, corporate, walk-in) dengan **inventory real-time anti-oversell** (lock kamar seperti capacity Fase 22) → check-in sekali klik dengan identitas (Human Passport/KYC) → kamar diberi smart-lock (QR/biometrik) → folio terbuka → check-out settlement (kartu/wallet/escrow corporate) → posting ke ledger.
- **Rate & Revenue Management (AI):** pricing per kamar per hari mengikuti demand, event kota (mall event, festival Pilar 10, konvensi EPC), kompetitor simulasi, lead time, dan okupansi saat ini — **dynamic rate** real-time dengan guardrail floor per contract rate & HET; penawaran korporat terikat kontrak (Fase 44.4 immutable).
- **Housekeeping & Maintenance IoT:** occupancy sensor + Smart TV status → kamar "make-up on request" → tugas housekeeping terdistribusi (mobile, urutan rute terpendek ala pick WMS) → laporan inspect; kerusakan (AC, shower) → **work order otomatis** ke teknisi + vendor (Asset Fase 31.5) → SLA durasi perbaikan → gangguan > jam menimbulkan kompensasi tamu otomatis (voucher).
- **F&B & Banquet:** restoran hotel memakai modul Resto penuh (HPP, batch, shift) + banquet/catering multi-event (Pilar 3) → kitchen terhubung cold-chain Logistics; room service masuk folio.
- **Integrated Guest Journey:** tamu hotel = party dengan **stay passport** (riwayat menginap, preferensi, alergi, loyalitas) → personalisasi room setup, upsell spa/parkir mobil (Pilar 1), early check-in berbayar.
- **Loyalty & Membership:** poin per room-night (PTS lintas ekosistem), tier member (Silver/Gold/Platinum) dengan benefit upgrade malam gratis, dan **night-rental RWA**: pemilik vila/kamar menyewakan ke platform (Pilar 2 tokenisasi unit hotel → dividen per okupansi).

## 11C. Cakupan
- **Modul baru:** `Hotel` (`htl_`): properties, rooms, rate plans, reservations, folios, housekeeping tasks, maintenance, spa, banquet, loyalty nights.
- **Terhubung:** Resto (F&B), Venue (paket liburan event), Hospital (medical tourism), Mall (shopping package), Logistics (laundry linen, supply), Asset (properti & depresiasi), Contract (OTA & corporate rate), Trade (impor linen/amenities), Payment Hub (deposit & no-show), Crypto (NFT stay & timeshare), HCM (front office, housekeeping, chef), ESG (water/energy per occupied room), Parkir/auto (valet via Pilar 1).

## 11D. Hasil (Output)
- **Revenue Command Center:** ADR, RevPAR, occupancy per properti/channel, pace vs tahun lalu.
- **Guest 360°:** stay history, spend per tamu, satisfaction score (NPS), churn risk.
- **Room Operations:** status kamar real-time (clean/dirty/inspect/OOO), backlog maintenance, tugas housekeeping selesai.
- **Folio Audit:** seluruh item folio = ledger; deposit & no-show fee terjelaskan.
- **Audit:** `hotel:audit` (room-night terjual = ledger revenue; rate plan konsisten; folio = line items; 0 selisih).

## 11E. Ide Pengembangan Lanjutan
- **Contactless & Smart Room Digital Twin:** simulasi suhu/lampu/TV per kamar terhubung smart building (Pilar 4) → energi dimatikan saat kosong → ESG per occupied-room-night.
- **Timeshare & Fractional Ownership:** kepemilikan fractional villa → token (Pilar 2) → jadwal penggunaan & bagi hasil sewa.
- **Destination Package Engine:** bundling hotel + tiket club/festival + restoran + transport + spa → satu harga, satu pembayaran, satu invoice multi-vendor (settlement otomatis ke tiap pihak via escrow).
- **MICE & Wedding Sales:** pipeline B2B konvensi dengan proposal harga berjenjang, deposit milestone, dan koordinasi venue (mall atrium Pilar 4 / beach club Pilar 10).
- **Predictive Maintenance & Guest Complaint Prediction:** pola keluhan → perbaikan proaktif sebelum review negatif.


## 11F. Spesifikasi implementasi minimum (MVP vertical slice)

- **Modul & prefiks:** Hotel (`htl_`), Payment, Party, Resto (F&B), Rwa (timeshare), Proptech (smart room).
- **Status audit:** 🟠 — 22 tabel & 7 service tanpa rute/UI/command; key acak di settlement paket; posting terbalik; `hotel:audit` tidak ada.
- **Slice 1 — Reservasi → menginap → check-out:** properti → tipe kamar → rate plan (harga harian, floor dari kontrak korporat) → reservasi dengan kunci inventori kamar per malam (anti-oversell, `lockForUpdate` pada baris inventori tanggal) → check-in (KYC) → folio → *night audit* harian memposting room charge → check-out settlement.
- **Slice 2 — F&B & paket:** pesanan restoran hotel (Resto) masuk folio; paket destinasi = beberapa vendor dengan escrow & settlement multi-vendor ber-key deterministik.
- **Slice 3 — Loyalty nights:** poin per room-night (akun `points`), redeem malam gratis sebagai diskon tercatat.
- **Posting ledger:**

| Kejadian | Debit (−) | Kredit (+) |
|---|---|---|
| Deposit reservasi (hold) | `wallet:user:{tamu}:IDR` | `escrow:payment:IDR` |
| Night audit (room charge) | `ar:htl:folio:{folio}:IDR` | `htl:room_revenue:IDR` + `htl:tax_payable:IDR` |
| Posting F&B ke folio | `ar:htl:folio:{folio}:IDR` | `resto:sales_revenue:{outlet}:IDR` |
| Check-out (bayar) | `wallet:user:{tamu}:IDR` / `escrow:payment:IDR` | `ar:htl:folio:{folio}:IDR` |

- **Rute & role:** `/hotel/front-office` (`front_office`) · `/hotel/housekeeping` (`housekeeping`, 375 px) · `/hotel/revenue` (`revenue_mgr`) · `/hotel/admin` (`hotel_gm`) · portal tamu (reservasinya sendiri).
- **Command:** `htl:night-audit` (harian, tick handler simulasi) · `hotel:audit`.
- **Audit dua sumber (`hotel:audit`):** room-night terjual = baris room charge folio = posting `htl:room_revenue`; tidak ada tanggal dengan kamar terjual > inventori; folio ditutup bersaldo 0.
- **Test kunci:** dua reservasi bersamaan untuk kamar terakhir → satu gagal; night audit 2× untuk tanggal sama → sekali; check-out folio bersaldo ≠ 0 → ditolak.
- **Skala:** T1 = 3 properti, 300 kamar, 90 hari reservasi; T3 = visi 11A.

- **Jalan pintas terlarang** (pola yang ditemukan audit atau paling mungkin muncul di pilar ini): oversell kamar tanpa kunci inventori per malam; night audit tanpa idempotensi; key acak di settlement paket; folio ditutup bersaldo ≠ 0.
- **Bukti selesai minimum** (selain V1–V12 di `PROGRESS.md` §P8): rute `/hotel/*` dengan role per peran; test dua reservasi bersamaan untuk kamar terakhir → satu gagal; test night audit tanggal sama 2× → sekali; `hotel:audit` + fixture korupsi.

---

# PILAR 12 — PERTAMBANGAN (MINING & RESOURCES)

## 12A. Skala (Tanpa Batas)
- **Operasi:** 100 tambang (batubara, nikel, emas, tembaga, batu kapur, pasir, andesit) di 30 wilayah + 50 kawasan pengolahan (smelter, crushing plant, quarry) → visi: 500 pit & 1.000 stockpile.
- **Armada raksasa:** 50.000 unit alat berat (haul truck 400 ton, excavator, bulldozer, drill, conveyor, dredger), 1 juta perjalanan angkut/hari (hauling), 100 juta ton material/bulan.
- **Telematik:** 50.000 unit memancarkan 500 juta titik telemetri/hari (GPS, fuel rate, payload, grade, vibration) + 10 juta sensor lingkungan/bulan (debu, air, kebisingan, tremor).
- **Tenaga kerja & kontraktor:** 500 ribu pekerja (karyawan + vendor), 1 juta shift/K3 permit/bulan, 100 ribu izin kerja panas/ketinggian/konfined space per bulan.
- **Rantai pasok:** 1 juta pengiriman ore/hasil tambang/tahun (truk, kereta, conveyor, tongkang, kapal curah), 50 juta ton ekspor/tahun.

## 12B. Operasional
- **Mine Planning & Fleet Dispatch:** rencana bulanan cut & fill, grade target, produksi harian → **dispatch system** menugaskan haul truck ke shovels & stockpile secara optimal (algoritma assignment deterministik) → telematik memantau payload aktual vs target → selisih dihitung sebagai *payload variance* & efisiensi → dispatcher bisa override dengan alasan tercatat.
- **Fleet & Maintenance Telematics:** jam kerja mesin → **prediktif maintenance** (engine hours, oil analysis, vibration) → work order otomatis ke workshop + suku cadang dipesan via Procurement (MRP equipment) → downtime mengurangi forecast produksi → terhubung S&OP (Fase 53) dan AutoServe sebagai adapter bengkel alat berat (Asset Fase 31.5).
- **Fuel Management & Anti-theft:** konsumsi BBM per 100 ton-km dibandingkan baseline → anomali > ambang → alarm + verifikasi telematik + hold bayaran kontraktor (meniru cold-chain hold) → selisih masuk cost variance.
- **Stockpile & Grade Control:** timbangan digital (Weighbridge) tercatat hash-chain per truck load → stockpile model 3D (digital twin) → sampling grade lab → **reconciliation ore vs concentrate vs shipment** (yang masuk smelter/ekspor = yang dicatat) → selisih > toleransi → investigasi otomatis.
- **HSE (K3) & Environment:** izin kerja berisiko dengan approval & masa berlaku (sudah Fase 40.6 diperluas) → incident & near-miss → tremor/dust/noise monitoring dari IoT → ambang batas → shutdown area + notifikasi; reklamasi & pascatambang dijadwalkan sebagai proyek EPC (Pilar 4) dengan biaya capitalisasi & provisi liabilitas pascatambang (simulasi PSAK).
- **Royalty, Pajak & Compliance (Simulasi):** produksi per bulan × tarif royalti komoditas → jurnal kewajiban; PPN/PPh final; laporan ke pemerintah (dokumen gapless); IUP/IUPK masa berlaku → pengingat & perpanjangan via approval.
- **Sustainability & Carbon:** emisi Scope 1 (BBM alat berat, peledakan/blasting) & Scope 2 (listrik plant) → kredit karbon/ESG (Fase 60) → rencana elektrifikasi fleet & solar plant di tambang → laporan ESG tambang per konsesi.
- **Offtake & Trading:** kontrak penjualan ore/coal ke smelter/mitra (Contract) dengan formula harga (index komoditas + kalori/grade adjustment) → settlement bertingkat + assay final → impas ke Trade Finance/LC (Pilar 7).

## 12C. Cakupan
- **Modul baru:** `Mining` (`min_`): sites/pits, stockpiles, equipment, dispatch runs, weighbridge tickets, assays, grades, hse_permits, incidents, royalty_returns, environmental readings.
- **Terhubung:** Logistics (haul, kereta, tongkang, ekspor), Asset (alat berat & depresiasi), Manufacturing (smelter/crushing), Trade (ekspor komoditas & LC), Treasury (hedging harga komoditas), Procurement (sparepart & explosives simulasi), HCM (pekerja, shift, K3), Contract (offtake, kontraktor), ESG (emisi & reklamasi), Payment/hold (bayaran kontraktor), AI (dispatch & predictive maintenance), EPC (pembangunan plant & reklamasi), B2B marketplace (jual overburden/side product).

## 12D. Hasil (Output)
- **Mine Command Center:** produksi vs target per pit & shift, okupansi fleet, payload variance, fuel KPI, stockpile 3D.
- **HSE Dashboard:** jam tanpa kecelakaan, insiden, permit aktif, ambang lingkungan (debu/noise/tremor), status reklamasi.
- **Grade & Reconciliation:** ore mined vs terkirim vs terjual, assay bias, recovery %, selisih dijelaskan.
- **Compliance Center:** royalti & pajak terhitung, izin (IUP, AMDAL simulasi) mendekati kedaluwarsa.
- **Audit:** `mining:audit` (weighbridge total = stockpile movement = shipment; royalti = produksi × tarif; fuel terbayar = konsumsi terukur ± toleransi; 0 selisih).

## 12E. Ide Pengembangan Lanjutan
- **Autonomous Fleet Simulation:** haul truck otonom (simulasi) dengan koridor khusus & remote control center.
- **Mine-to-Mill Optimization:** optimasi blending stockpile agar feed smelter stabil → recovery naik → margin naik (AI teraudit).
- **Critical Minerals for EV:** jalur nikel/hijau → baterai EV (terhubung Pilar 1 SPKLU) dengan sertifikasi rantai pasok battery-grade traceability (Digital Product Passport).
- **Underground Digital Twin:** model 3D terowongan & ventilasi untuk simulasi keamanan tambang bawah tanah.
- **Community Development Ledger:** dana CSR/DMSP per desa tercatat sebagai liabilitas & realisasi proyek (transparansi).


## 12F. Spesifikasi implementasi minimum (MVP vertical slice)

- **Modul & prefiks:** Mining (`min_`), Logistics (haul/tongkang), Asset (alat berat), Trade (ekspor), Esg, Hcm (K3), Contract (offtake, kontraktor).
- **Status audit:** 🟠 — 30 tabel & 9 service tanpa rute/UI/command; idempotency key acak di penalti kontraktor & lingkungan; posting pendapatan terbalik; `mining:audit` tidak ada.
- **Slice 1 — Dispatch & weighbridge:** situs → pit → stockpile → alat (Asset) → dispatch run (penugasan truk ke shovel, algoritma deterministik S1) → tiket timbang hash-chain per muatan (`min.weighbridge.recorded.v1`) → mutasi stockpile.
- **Slice 2 — Grade & rekonsiliasi:** sampel assay per lot → rekonsiliasi tambang → stockpile → pengapalan (Logistics) dalam toleransi; selisih di atas toleransi membuka investigasi.
- **Slice 3 — Royalti & K3:** royalti = produksi terverifikasi × tarif (config) per bulan; izin kerja berisiko lewat ApprovalEngine dengan masa berlaku; insiden memicu penghentian area.
- **Posting ledger:**

| Kejadian | Debit (−) | Kredit (+) |
|---|---|---|
| Akru royalti bulanan | `expense:min:royalty:IDR` | `min:royalty_payable:IDR` |
| Penalti kontraktor | `ar:min:contractor:{party}:IDR` | `min:penalty_income:IDR` |
| Biaya BBM | `expense:min:fuel:IDR` | `ap:fuel_supplier:{party}:IDR` |
| Penjualan offtake | `ar:min:offtaker:{party}:IDR` | `min:sales_revenue:IDR` |

- **Rute & role:** `/mining/dispatch` (`dispatcher_mine`) · `/mining/weighbridge` (`weighbridge_operator`, 375 px) · `/mining/hse` (`hse_officer`) · `/mining/planning` (`mine_planner`) · `/mining/admin` (`mine_manager`).
- **Command:** `min:accrue-royalty` (bulanan) · `min:reconcile-grade` · `mining:audit`.
- **Audit dua sumber (`mining:audit`):** Σ weighbridge = Σ mutasi stockpile = Σ pengapalan (± toleransi); royalti = produksi × tarif; BBM terbayar = konsumsi terukur ± toleransi; hash-chain weighbridge valid.
- **Test kunci:** tiket timbang duplikat → ditolak; penalti untuk evaluasi yang sama 2× → sekali; izin kerja kedaluwarsa → aktivitas ditolak; royalti bulan sama 2× → sekali.
- **Skala:** T1 = 2 situs, 5 pit, 50 alat, 5 rb tiket timbang; T2 = 500 rb tiket; T3 = visi 12A.

- **Jalan pintas terlarang** (pola yang ditemukan audit atau paling mungkin muncul di pilar ini): key acak di penalti kontraktor & lingkungan; tiket timbang tanpa hash-chain; royalti dari angka masukan, bukan produksi terverifikasi; pendapatan bertanda terbalik.
- **Bukti selesai minimum** (selain V1–V12 di `PROGRESS.md` §P8): rute `/mining/weighbridge` (layak di 375 px) dengan role `weighbridge_operator`; test tiket timbang duplikat ditolak; test royalti bulan sama 2× → sekali; test izin kerja kedaluwarsa menolak aktivitas; `mining:audit` + fixture korupsi.

---

# INTEGRASI 12 LINI DALAM SATU MONOLITH

| Dari | Ke | Melalui |
|------|-----|---------|
| Rumah Sakit | Logistics | pengiriman darah/obat/limbah B3 (cold-chain + hash custody) |
| Rumah Sakit | Hotel | medical tourism package |
| Rumah Sakit | Resto | diet meals terkontrol HPP |
| Beach Club | Resto/Logistics | POS venue + supply cold-chain |
| Beach Club | Hotel | staycation bundle |
| Beach Club | DAO | voting line-up & festival |
| Hotel | Resto/Banquet | F&B folio |
| Hotel | EPC/Asset | smart building twin + maintenance |
| Hotel | Crypto | timeshare token & NFT stay |
| Pertambangan | Logistics | ore/coal multimoda + weighbridge custody |
| Pertambangan | Trade | offtake ekspor + LC |
| Pertambangan | ESG | emisi & kredit karbon |
| Pertambangan | Manufacturing | smelter & mineral hilirisasi |
| Semua lini | Group | Universal Event Spine + Ledger + P&L konsolidasi |

---

# PEMETAAN KONSEP → FASE DI PROGRESS.md (FASE 67–103)

> **Status audit 10 Okt 2026:** seluruh fase di tabel ini berstatus 🟠 KERANGKA (lihat baris status per fase di PROGRESS.md dan subbagian xF di atas).

| KONSEP | Fase di PROGRESS.md |
|--------|---------------------|
| Konsep bersama (Simulation Kernel, Universal Event Spine, Digital Twin Bus, Scale Provisioner) | Fase 67 |
| Pilar 1 — Telematics & Predictive Maintenance | Fase 68 |
| Pilar 1 — EV Charging Network & Battery Passport | Fase 69 |
| Pilar 1 — B2B Fleet & Corporate Leasing | Fase 70 |
| Pilar 2 — Tokenisasi Aset Riil (RWA) & Dividen | Fase 71 |
| Pilar 2 — InsurTech Micro-Insurance & Claims Autopilot | Fase 72 |
| Pilar 2 — Robo-Advisor & Treasury Yield | Fase 73 |
| Pilar 3 — Cloud Kitchen, Delivery Internal & Payroll Deduction | Fase 74 |
| Pilar 3 — AI Demand/Waste Forecasting, Auto-PO & Smart Vending | Fase 75 |
| Pilar 4 — PropTech & Smart Building (IoT + ESG real-time) | Fase 76 |
| Pilar 4 — Digital Twin & BIM Lifecycle | Fase 77 |
| Pilar 4 — Flex-Space & Co-Working Booking | Fase 78 |
| Pilar 5 — Reverse Logistics & Circular Economy | Fase 79 |
| Pilar 5 — Cold-Chain Blockchain, Drone & Last-Mile Robotics | Fase 80 |
| Pilar 6 — Algorithmic & Surge Pricing (detik-per-detik) | Fase 81 |
| Pilar 6 — VMI & C2M (Consumer-to-Manufacturer) | Fase 82 |
| Pilar 7 — Cross-Border Clearing House Kripto & CBAM | Fase 83 |
| Pilar 7 — AI Contract Bidding Agent | Fase 84 |
| Pilar 8 — Internal Gig Economy (Talent Marketplace) | Fase 85 |
| Pilar 8 — Precision Agri-Tech NDVI & DAO Governance | Fase 86 |
| Lini 9 — Rumah Sakit I (EMR, Bed, Clinical Pathway) | Fase 87 |
| Lini 9 — Rumah Sakit II (Order-to-Cash, Farmasi, Klaim) | Fase 88 |
| Lini 10 — Beach Club & Clubs I (Ticketing, Access, Usia) | Fase 89 |
| Lini 10 — Beach Club & Clubs II (Artis, Supply, Festival) | Fase 90 |
| Lini 11 — Perhotelan I (PMS, Reservation, Rate, Smart Room) | Fase 91 |
| Lini 11 — Perhotelan II (Folio, Loyalty, Timeshare, Paket) | Fase 92 |
| Lini 12 — Pertambangan I (Mine Planning, Dispatch, Fuel) | Fase 93 |
| Lini 12 — Pertambangan II (Weighbridge, Royalty, HSE, Offtake) | Fase 94 |
| Integrasi 12 lini A (Otomotif, EV, Logistik, Hotel, Venue, RS) | Fase 95 |
| Integrasi 12 lini B (Fintech, RWA, InsurTech, Pembiayaan) | Fase 96 |
| Integrasi 12 lini C (Talent, ESG, Event Spine, Command Center) | Fase 97 |
| Skala Ultra (Seeder 12 lini, Query Budget, Stress Test) | Fase 98 |
| AI & Analitik Prediktif Terpadu | Fase 99 |
| Keamanan, RBAC 60+ Role, Kepatuhan & Observabilitas | Fase 100 |
| Skenario Emas 12 Lini & Disaster Recovery | Fase 101 |
| API V3, Webhook & Portal Mitra | Fase 102 |
| Dokumentasi Final, Playbook & Serah Terima | Fase 103 |

---

# GELOMBANG 2 — PENDALAMAN, 5 LINI BARU & EKSPANSI SAMPAI FASE 150

> Ekspansi lanjutan: pendalaman 4 lini (Kesehatan, Hospitality & Entertainment, Sumber Daya & Energi) + pembukaan **5 lini baru** — Energi & Utilitas (13), Telekomunikasi & Data Center (14), Media & Kreatif (15), Pendidikan & Talent (16), Ritel & E-Commerce (17) — sehingga total **17 lini bisnis dalam satu monolith**, diakhiri integrasi, skala, AI, keamanan, resilience, dan serah terima final Fase 150.

## 5 LINI BARU (GELOMBANG 2) — SKALA TANPA BATAS

### Lini 13 — Energi & Utilitas (modul `egy_`)
- **Skala:** 5 juta smart meter (mall, pabrik, RS, hotel, venue), reading tiap 15 menit (200 juta bacaan/hari), 100 unit pembangkit (solar farm, genset, battery storage), district cooling 50 kawasan.
- **Operasional:** grid dispatch unit-commitment sederhana, tarif time-of-use, net metering & PPA antar-entitas (intercompany billing), EV charging dijadwalkan off-peak (demand response), microgrid dengan islanding prioritas beban (RS > pabrik > mall), arbitrage battery, ESCO performance contract (bayar dari penghematan).
- **Hasil:** tagihan utilitas terkonsolidasi per properti, PUE & renewable %, resilience scorecard per site, revenue energi hijau.
- **Lanjutan:** carbon trading internal + REC marketplace, ESG-linked pricing (green lease), water & waste utility, waste-to-energy.
- **Spesifikasi minimum (MVP):** modul `Egy` (`egy_`) · status 🟠 (17 tabel tanpa rute/UI, posting pendapatan terbalik, `egy:audit` tidak ada). Slice: meter pintar (`egy_smart_meters`) → bacaan 15 menit (T1: 50 meter × 7 hari) → tarif time-of-use (config) → tagihan utilitas terkonsolidasi per properti (DR `ar:egy:property:{id}:IDR` / CR `egy:utility_revenue:IDR`) → PPA antar-entitas lewat Intercompany (eliminasi saat konsolidasi). Event `egy.meter.read.v1` → Mall & Esg. Rute `/energy` (`energy_manager`; tenant melihat tagihannya). Audit `egy:audit`: Σ(kWh × tarif TOU) = baris tagihan = posting AR. Test: bacaan duplikat, tarif lintas periode TOU, PPA tereliminasi di konsolidasi. **Jalan pintas terlarang:** tagihan tanpa bacaan meter nyata; PPA antar-entitas tanpa eliminasi Intercompany; pendapatan bertanda terbalik; akun `egy:*` dibuat di test.

### Lini 14 — Telekomunikasi & Data Center (modul `tlx_`)
- **Skala:** 10.000 site jaringan, 50.000 link, 10 data center (PUE terukur), 1 juta subscriber SIM, 100 ribu ISP rumah, 100 juta perangkat IoT lintas 17 lini.
- **Operasional:** IoT backbone terpusat untuk seluruh telematik/sensor platform, billing konektivitas antar-entitas, colocation & cloud chargeback, NOC alarm → ticket → MTTR, smart city services (parkir pintar, CCTV), churn & upsell analytics.
- **Hasil:** NOC dashboard, DC utilization & PUE, IoT device registry tunggal, revenue B2B/B2C connectivity.
- **Lanjutan:** edge computing untuk venue & site tambang, white-label ISP.
- **Spesifikasi minimum (MVP):** modul `Tlx` (`tlx_`) · status 🟠. Peran kunci: **registry perangkat IoT tunggal** (`tlx_iot_devices`) yang dipakai Mobility, Proptech, Agri, Mining, Hospital — modul lain menyimpan `device_id` dan membaca lewat Contract `IotDeviceRegistry`. Slice: aktivasi perangkat & SIM → pemakaian data bulanan → tagihan konektivitas antar-entitas (Intercompany) → kontrak colocation DC (Contract) dengan tagihan bulanan. Rute `/telecom` (`noc_operator`, `telecom_admin`). Audit `tlx:audit`: perangkat aktif tertagih = perangkat aktif di registry; tagihan colocation = kontrak aktif. **Jalan pintas terlarang:** registry perangkat ganda per modul; tagihan konektivitas untuk perangkat yang tidak aktif di registry; `tlx:audit` yang hanya menghitung baris.

### Lini 15 — Media & Kreatif (modul `med_`)
- **Skala:** 500 studio (sound stage, podcast, virtual production), 5.000 proyek produksi/tahun, 100 ribu aset IP (merek, lagu, format), 1 miliar impressions iklan/bulan (OOH mall/venue/hotel + digital).
- **Operasional:** production lifecycle (brief → shoot → post → delivery, kapitalisasi biaya kreatif), talent contract dengan backend %, IP registry → lisensi otomatis multi-kanal, sponsorship lintas lini, yield management inventaris iklan (dynamic price + floor), campaign measurement terverifikasi.
- **Hasil:** IP portfolio & royalty statement per karya, studio utilization, campaign ROI per advertiser, split settlement multi-pihak.
- **Lanjutan:** creator economy bridge ke venue (Fase 115), distribution revenue per view.
- **Spesifikasi minimum (MVP):** modul `Med` (`med_`) · status 🟠. Slice: studio & booking (hold/capture, anti-overlap) → registri IP (`med_ip_assets`) → lisensi (Contract) → pendapatan lisensi → *royalty statement* dan settlement multi-pihak ber-key (baris terakhir menyerap pembulatan). Ledger: DR `ar:med:licensee:{party}:IDR` / CR `med:license_revenue:IDR`; DR `expense:med:royalty:IDR` / CR `ap:med:rightsholder:{party}:IDR`. Rute `/media` (`studio_manager`, `rights_manager`; pemegang hak melihat statement-nya). Audit `med:audit`: Σ royalti = Σ pendapatan lisensi × porsi kontrak. **Jalan pintas terlarang:** royalti tanpa kontrak lisensi; settlement multi-pihak yang pembulatannya tidak diserap baris terakhir; booking studio tanpa kunci slot.

### Lini 16 — Pendidikan & Talent (modul `edu_`)
- **Skala:** 10.000 program, 500 ribu enrollment/tahun, 5 juta sertifikat hash-chain, 1 juta talent pool, 100 ribu lowongan lintas lini/tahun.
- **Operasional:** kurikulum berlapis + prerequisite graph (anti-siklus), assessment → sertifikat hash terverifikasi QR + masa berlaku (prasyarat role kritis: dokter, operator K3, mekanik), corporate L&D B2B (kuota karyawan tenant/pabrik/RS/tambang), talent matching engine deterministik, headhunter fee hold sampai garansi kerja, contingent workforce timesheet.
- **Hasil:** kompetensi terverifikasi per karyawan, pipeline talenta 17 lini, laporan kepatuhan sertifikasi, revenue edukasi.
- **Lanjutan:** internal mobility bridge ke gig economy (Fase 85/97), alumni → lowongan otomatis.
- **Spesifikasi minimum (MVP):** modul `Edu` (`edu_`) · status 🟠. Slice: program → cohort → enrollment (kuota korporat B2B) → asesmen → sertifikat hash-chain bermasa berlaku (nomor gapless) → sertifikat menjadi **prasyarat** role/aksi di modul lain (mis. operator K3 Mining, mekanik AutoServe) lewat Contract `CredentialVerifier` → biaya pelatihan korporat ditagih. Rute `/academy` (`academy_admin`, `trainer`; peserta melihat sertifikatnya; publik memverifikasi QR). Audit `edu:audit`: sertifikat terbit = asesmen lulus; tagihan korporat = enrollment kuota. **Jalan pintas terlarang:** sertifikat tanpa nomor gapless/hash; sertifikat tidak dipakai sebagai prasyarat lewat Contract; kuota korporat yang tidak ditagih.

### Lini 17 — Ritel & E-Commerce (modul `ret_`)
- **Skala:** marketplace 3P 1 juta listing, 50 juta order/tahun, 100 dark store q-commerce (30 menit), omnichannel ke seluruh toko 17 lini, 5 juta pengguna super app.
- **Operasional:** OMS unified inventory anti double-sell (lockForUpdate), split fulfillment (ship-as-store/FDC/dropship/drone/crowdshipping), pricing consistency MAP lintas channel, settlement seller T+N + komisi + chargeback, super app wallet & cashback lintas lini (liability terkendali + anti-abuse), bill payment hub, subscription bundle, packaging deposit loop (reverse).
- **Hasil:** GMV terkonsolidasi, seller performance, unit economics per zone q-commerce, cashback liability = ledger.
- **Lanjutan:** embedded finance untuk seller, white-label OMS.
- **Spesifikasi minimum (MVP):** modul `Ret` (`ret_`) · status 🟠. Aturan: Ret **tidak** punya stok sendiri — memakai `InventoryService` (anti double-sell dengan reservasi). Slice: listing multi-channel (harga konsisten dengan Pricing) → order → reservasi stok → fulfillment split → settlement seller T+N lewat escrow (DR `escrow:payment:IDR` / CR `ap:ret:seller:{party}:IDR` + `ret:commission_revenue:IDR`) → cashback sebagai liabilitas (`ret:cashback_liability:IDR`, kredit) dengan batas anti-abuse. Rute `/retail` (`seller` portal, `marketplace_admin`, customer). Audit `ret:audit`: utang seller = Σ order − komisi − refund; liabilitas cashback = Σ cashback terbit − terpakai − kedaluwarsa. **Jalan pintas terlarang:** stok ritel terpisah dari Inventory (double-sell); cashback tanpa liabilitas ledger; settlement seller tanpa escrow; harga lintas kanal tidak konsisten dengan Pricing.

## PEMETAAN GELOMBANG 2 → FASE (104–150)

> **Status audit 10 Okt 2026:** seluruh fase di tabel ini berstatus 🟠 KERANGKA; fase 103 & 150 hanya berisi commit dokumen.

| KONSEP | Fase |
|--------|------|
| Kesehatan: telemedicine & e-pharmacy | 104 |
| Kesehatan: lab & imaging | 105 |
| Kesehatan: clinical trial & data vault | 106 |
| Kesehatan: JKN/BPJS & health command center | 107 |
| Kesehatan: medical tourism & membership | 108 |
| Kesehatan: blood bank, waste, regulasi | 109 |
| Kesehatan: analytics & portofolio RS grup | 110 |
| Hospitality: brand standard & franchise hotel | 111 |
| Hospitality: global loyalty & travel pass | 112 |
| Hospitality: travel & itinerary platform | 113 |
| Hospitality: MICE & wedding engine | 114 |
| Entertainment: content, creator & IP economy | 115 |
| Entertainment: secondary ticket & dynamic bundling | 116 |
| Entertainment: biometric entry & crowd safety AI | 117 |
| Hospitality/Entertainment: revenue command & portfolio | 118 |
| Sumber Daya: underground twin, blasting, geotech | 119 |
| Sumber Daya: HSE leading indicator & contractor safety | 120 |
| Sumber Daya: smelter & metals trading desk | 121 |
| Sumber Daya: coal export logistics & demurrage | 122 |
| Sumber Daya: renewable mining & carbon project | 123 |
| Sumber Daya: reclamation, water & biodiversity | 124 |
| Sumber Daya: resource command center | 125 |
| **Lini 13** Energi: grid & smart metering | 126 |
| Lini 13 Energi: water, waste & district utilities | 127 |
| Lini 13 Energi: carbon trading & REC | 128 |
| Lini 13 Energi: microgrid & resilience | 129 |
| **Lini 14** Telko: network & IoT backbone | 130 |
| Lini 14 Telko: data center & cloud | 131 |
| Lini 14 Telko: ISP, SIM & smart city | 132 |
| **Lini 15** Media: studio & IP economy | 133 |
| Lini 15 Media: distribution, ads & sponsorship | 134 |
| **Lini 16** Edu: academy & sertifikasi | 135 |
| Lini 16 Edu: talent pipeline & workforce marketplace | 136 |
| **Lini 17** Ritel: omnichannel marketplace | 137 |
| Lini 17 Ritel: super app & cashback economy | 138 |
| Lini 17 Ritel: fulfillment & quick commerce | 139 |
| Integrasi gelombang 2 (energi, telko, media, edu, ritel) | 140 |
| Integrasi: holding, capital allocation & M&A | 141 |
| Skala gelombang 2 (seeder 17 lini & performance) | 142 |
| AI cross-lini & autonomous operations ladder | 143 |
| Keamanan gelombang 2 (zero trust & privacy vault) | 144 |
| Resilience gelombang 2 (active-active & BCP) | 145 |
| Data platform (lakehouse & MDM 17 lini) | 146 |
| Platform economy (open API & white-label) | 147 |
| Skenario konglomerasi 12 bulan & mega-scenario | 148 |
| Dokumentasi & playbook gelombang 2 | 149 |
| Final gelombang 2 | 150 |

---

# GELOMBANG 3–14 — FASE 151–500: 30 LINI BISNIS & MATURITY

> `PROGRESS.md` mengembangkan roadmap ini sampai **Fase 500** dengan pembukaan 13 lini baru (18–30) dan gelombang integrasi, skala, AI/data, risiko, keuangan, operasi, pelanggan, SDM, keberlanjutan, tata kelola, platform, hingga enterprise maturity.

## DAFTAR 30 LINI BISNIS (SATU MONOLITH)

**Gelombang awal (Fase 0–66):**
1. Otomotif (AutoDex, AutoServe) · 2. FinTek/Perbankan/Kripto · 3. Kuliner/Resto · 4. Properti Komersial/Mall · 5. Logistik Multimoda · 6. Manufaktur/Distribusi · 7. Perdagangan Internasional · 8. Tata Kelola/Enterprise

**Gelombang 1 (Fase 67–103):**
9. Rumah Sakit · 10. Beach Club & Clubs · 11. Perhotelan · 12. Pertambangan

**Gelombang 2 (Fase 104–150):**
13. Energi & Utilitas (`egy_`) · 14. Telekomunikasi & Data Center (`tlx_`) · 15. Media & Kreatif (`med_`) · 16. Pendidikan & Talent (`edu_`) · 17. Ritel & E-Commerce (`ret_`)

**Gelombang 3 (Fase 151–300):**
18. Asuransi & Reasuransi (`ins_`) · 19. Keuangan Syariah (`syb_`) · 20. Pendidikan Formal/Campus (`camp_`) · 21. Agri-Processing & Food (`food_`) · 22. Perikanan & Aquaculture (`mar_`) · 23. Kehutanan & Forest (`for_`) · 24. Waste & Recycling/Circular (`cir_`) · 25. Professional Services & Legal (`psv_`) · 26. Aviasi & Air Cargo (`avi_`) · 27. Pelabuhan & Marine Fleet (`prt_`/`marinefleet_`) · 28. Fashion & Textile (`fsh_`) · 29. Digital Identity & Telecom Media Services (`identity_`) · 30. City Operations & Smart District (`dst_`)

## SPESIFIKASI MINIMUM LINI 18–30 & PENYELESAIAN IRISAN

> Status audit seluruh lini 18–30: 🟠 — kode yang ada berupa service di modul `Integration` (Fase 151–184) tanpa jalan masuk. Sebelum dikerjakan ulang, setiap lini wajib memutuskan **modul pemilik** dan **irisan** dengan modul yang sudah ada (catat di `DECISIONS.md`). Prinsip: jangan membuat modul baru bila domainnya adalah sub-domain modul yang sudah ✅.

| Lini | Keputusan modul & prefiks | Irisan yang harus diselesaikan | Slice MVP pertama | Audit dua sumber |
|---|---|---|---|---|
| 18 Asuransi & Reasuransi | **Insurance** (`ins_`) — satu modul untuk micro-insurance Pilar 2 + lini penuh | 13 tabel `ins_*` buatan Integration dipindah | produk → underwriting (aturan S1 tertulis) → polis → premi → klaim → treaty reasuransi proporsional | premi = ledger; cadangan ≥ klaim terbuka; porsi reasuradur = treaty × klaim |
| 19 Keuangan Syariah | modul baru **Syariah** (`syb_`) di atas Ledger | tidak boleh memakai akun bunga; akun margin/bagi hasil terpisah | akad murabahah: barang dibeli → dijual dengan margin tetap → angsuran tanpa bunga | jadwal angsuran = posting; tidak ada akun `interest` di transaksi syariah |
| 20 Pendidikan Formal/Kampus | **sub-domain Edu** (`edu_`), bukan modul `camp_` terpisah | kurikulum/enrollment sama dengan Lini 16 | semester → mata kuliah → KRS → nilai → transkrip hash-chain | SKS terambil = SKS dinilai; tagihan UKT = ledger |
| 21 Agri-processing & Food | pengolahan di **Manufacturing**; hulu di **Agri**; modul **Food** (`food_`) hanya untuk merek/private label & program gizi | jangan duplikasi BOM/MRP | lot bahan Agri → batch olahan Manufacturing → grade ekspor | lot masuk = lot terpakai + sisa; grade sesuai assay |
| 22 Perikanan & Aquaculture | modul **Marine** (`mar_`) | kontrak pembudidaya mengikuti pola Agri (kontrak, uang muka, grading) — pakai ulang, jangan salin | kolam/keramba → siklus budidaya → panen → grading → payout | panen = penerimaan; payout = ledger |
| 23 Kehutanan | modul **Forest** (`for_`) | kredit karbon memakai Esg | petak → inventarisasi tegakan → izin tebang (approval) → kayu bernomor (hash-chain) → restorasi | volume tebang ≤ izin; kayu terjual = kayu bernomor |
| 24 Waste & Recycling | modul **Circular** (`cir_`) | angkutan = Logistics reverse (Pilar 5); manfaat emisi = Esg | marketplace material sekunder: listing → pembelian → pengiriman reverse → penerimaan sebagai bahan baku | tonase dijual = tonase dikirim = tonase diterima |
| 25 Professional Services & Legal | modul **ProServices** (`psv_`); sengketa/legal ops tetap di **Contract** | jangan duplikasi kontrak | proyek → timesheet → tagihan T&M/fixed fee → pengakuan pendapatan | jam tertagih = jam disetujui; tagihan = ledger |
| 26 Aviasi & Air Cargo | air cargo tetap di **Logistics** (mode udara sudah ada); modul **Aviation** (`avi_`) hanya untuk layanan bandara & jaringan maskapai | jangan buat shipment kedua | layanan ground handling per penerbangan → tagihan ke maskapai | layanan tercatat = tagihan |
| 27 Pelabuhan & Marine Fleet | master kapal/kontainer tetap di **Logistics**; modul **Port** (`prt_`) untuk operasi terminal | satu tabel kapal saja | kunjungan kapal → bongkar/muat → storage & D&D (pakai kalkulator D&D Logistics) | kontainer masuk = keluar + di lapangan |
| 28 Fashion & Textile | **Manufacturing + Store** dengan atribut varian; modul **Fashion** (`fsh_`) hanya untuk sourcing musiman & koleksi | varian produk = master produk Inventory/Store | koleksi → sampel → PO produksi → varian ukuran/warna di Store | stok varian = movement; PO = penerimaan |
| 29 Digital Identity & Telco Media | identitas di **Party/Core**, layanan di **Tlx** — tidak ada modul `identity_` terpisah | jangan duplikasi KYC | identitas digital terverifikasi (Party KYC) dipakai SSO lintas lini | setiap kredensial aktif tertaut ke pihak terverifikasi |
| 30 City Operations & Smart District | modul **District** (`dst_`) sebagai orkestrator | sensor = Tlx registry; energi = Egy; gedung = Proptech | layanan kawasan (parkir pintar, CCTV, sampah) → tiket layanan → SLA | tiket ditutup dalam SLA = laporan; tagihan layanan = ledger |

**Jalan pintas terlarang untuk lini 18–30:** (1) menambah service/tabel di `Integration` — kode Fase 151–184 di sana hanya referensi (X2, X21); (2) membuat modul baru tanpa keputusan irisan di `DECISIONS.md` dan persetujuan pemilik (§A16); (3) menyalin pola modul lain (mis. Agri → Marine) alih-alih memakai ulang lewat Contract; (4) akun bunga pada transaksi Syariah; (5) master data ganda (kapal, produk/varian, identitas, kontrak); (6) klaim kepatuhan/regulasi tanpa label tingkat simulasi.

**Bukti selesai minimum per lini:** modul pemilik terdaftar di §A1 · rute & role · chart of accounts + seeder T1 · audit dua-sumber + fixture korupsi · test HTTP (a)–(e) · verifikasi silang ✅.

## STRUKTUR FASE 151–500

> **Status audit 10 Okt 2026:** fase 151–484 berstatus 🟠 KERANGKA (1 service + 1 migrasi + 1 test per fase di modul `Integration`); 485–500 ⬜ dibekukan.

| Blok | Fase | Isi |
|------|------|-----|
| Pembukaan lini 18–30 | 151–165 | Global ops, Asuransi penuh, Syariah, Campus, Food, dst. |
| Pendalaman 13 lini baru | 166–185 | Lini 20–30 mendalam + domain model 30 lini |
| Integrasi & skala 30 lini | 186–200 | Value chain, treasury, identity, data, seeder ultra |
| AI & risiko | 201–210 | Forecast federation, ERM, cyber, continuity, tax |
| Keuangan & operasi | 211–220 | Capital, profitability, QMS, maintenance, supply, projects |
| Pelanggan, SDM, ESG | 221–230 | Subscription, marketing, workforce, ESG fabric, climate |
| Tata kelola & inovasi | 231–240 | Board, DAO, ethics, venture, DX, design system |
| Data & komersial | 241–255 | Data governance, real-time, pricing, sales, Q2C, golden scenario |
| Platform & ekosistem | 256–270 | SLO, CQRS, knowledge graph, partner, autonomous ladder |
| Keuangan & operasi lanjut | 271–285 | Digital securities, crypto, tax, planning, field service, wellness |
| ESG, governance, platform | 286–300 | Assurance, climate, policy engine, final 30-line release (**Fase 300**) |
| **Gelombang Kematangan** | 301–400 | Supply/finance/people intelligence, data & AI platform, global platform, stress wave |
| **Gelombang Maturity** | 401–500 | Governance/ops/customer/people/platform/finance/sustainability waves, scenario mega, enterprise acceptance (**Fase 500**) |

> Detail tiap fase (skala, operasional, cakupan, hasil, test, quality gate) tercantum di `PROGRESS.md`. Seluruh fase bersifat **belum dikerjakan** (checkbox `[ ]`) kecuali yang sudah ditandai selesai.

---

# MATURITY ROADMAP — FASE 501–1000

> ⬜ **DIBEKUKAN** sampai Fase R di PROGRESS selesai dan 30 lini rancangan memiliki MVP ✅.

> Setelah Fase 500, roadmap dilanjutkan ke **Fase 1000** dengan prinsip yang berbeda: **tidak ada lini bisnis baru** (tetap 30 lini rancangan) dan **setiap fase dipadatkan** menjadi satu hasil terukur + tes/invarian + quality gate. Fase 501–1000 murni pendalaman, integrasi, pengujian, dan pematangan — bukan penambahan ide.

| Gelombang | Fase | Hasil |
|-----------|------|-------|
| A | 501–550 | Domain & service maturity 30 lini (katalog, KPI, master data, contract/event hygiene) |
| B | 551–600 | Keamanan, privasi, DR & recovery |
| C | 601–650 | Performa, skala, partition & FinOps |
| D | 651–700 | Operasi harian + audit tiap lini (termasuk overlap Edu/Campus & Port/Logistics diverifikasi) |
| E | 701–750 | Pengukuran maturity & integritas metrik (anti-gaming) |
| F | 751–800 | Business outcomes & value realization lintas lini |
| G | 801–850 | Digital trust, inovasi & ekosistem mitra |
| H | 851–900 | Platform operating model & sustainability |
| I | 901–950 | Simulasi deterministik 365 hari + assurance independen |
| J | 951–1000 | Acceptance, rollout bertahap, post-release review, tag `v1000-30-lines-enterprise-maturity` |

> **Catatan integritas roadmap:** daftar 30 lini adalah jumlah *domain/modul rancangan* — ada irisan yang harus dinyatakan (Pendidikan Formal/Campus bisa menjadi sub-domain Pendidikan & Talent; Pelabuhan/Marine beririsan dengan Logistik). Fase tidak boleh dicentang tanpa bukti; checkbox tetap `[ ]` sampai dikerjakan. File `PROGRESS.md` hasil salinan manual perlu diverifikasi ulang terhadap file asli karena proses penyalinan manual berisiko tidak 100% identik.
