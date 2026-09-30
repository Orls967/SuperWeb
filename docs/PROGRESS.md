# Progress Tracker — Superwebsite

## FASE 0 — FONDASI & REFACTOR
- [x] 0.1 Audit codebase → docs/AUDIT.md
- [x] 0.2 Setup Pest + tests/Architecture
- [x] 0.3 Characterization tests (AutoServe + AutoDex)
- [x] 0.4 Modul Shared + Core
- [x] 0.5 Pindahkan AutoServe & AutoDex ke modules/
- [x] 0.6 Entity Vehicle (core_vehicles) + migrasi garage
- [x] 0.7 AutoServe: vehicle_id di bookings
- [x] 0.8 BookingStatus Enum + state machine
- [x] 0.9 Arch tests batas modul
- [x] 0.10 Quality gate Fase 0

## FASE 1 — CORE BANKING (DOUBLE-ENTRY LEDGER)
- [x] 1.1 Migrations (ledger_accounts, ledger_transactions, ledger_entries, wallet_pins)
- [x] 1.2 LedgerService (double-entry posting, idempotency, locking)
- [x] 1.3 Akun sistem via seeder
- [x] 1.4 HasLedgerAccounts trait + auto wallet creation
- [x] 1.5 Actions: SetPin, VerifyPin, TopUp, Transfer, FreezeAccount, ManualAdjustment
- [x] 1.6 StatementQuery + CSV export
- [x] 1.7 bank:reconcile command
- [x] 1.8 UI: Wallet, Transfer, Mutasi, Admin Ledger
- [x] 1.9 Tests: posting, insufficient funds, idempotency, concurrent, PIN lockout, reconcile
- [x] 1.10 Quality gate Fase 1

## FASE 2 — PAYMENT HUB + INTEGRASI AUTOSERVE
- [x] 2.1 Payable contract
- [x] 2.2 pay_payment_intents table
- [x] 2.3 PaymentGateway: charge, hold, capture, release, refund
- [x] 2.4 AutoServe Invoice implements Payable
- [x] 2.5 Flow bayar invoice (saldo, PIN, badge LUNAS)
- [x] 2.6 Tests: bayar, saldo kurang, idempotent, hold/capture/release, refund, reconcile
- [x] 2.7 Quality gate Fase 2

## FASE 3 — CATALOG, INVENTORY & STORE
- [x] 3.1 Inventory: stock_movements, InventoryService
- [x] 3.2 store_products + store_items + store_categories, migrasi sparepart
- [x] 3.3 Refactor AutoServe stok → InventoryService
- [x] 3.4 Mobil sebagai produk (dex_car → store_product)
- [x] 3.5 Cart & Checkout, Orders, auto-cancel
- [x] 3.6 UI Store: katalog, produk, cart, checkout, riwayat order
- [x] 3.7 Tests: checkout, stok kurang, saldo kurang, beli mobil → Vehicle, refund
- [x] 3.8 Quality gate Fase 3

## FASE 4 — CRYPTO TRACKER (SIMULASI)
- [x] 4.1 crypto_assets + crypto_price_ticks + seeder
- [x] 4.2 PriceEngine (crypto:tick) + PriceFeed contract + quotes
- [x] 4.3 crypto_trades + buy/sell flow
- [x] 4.4 Portfolio: holdings, P/L, charts, alerts
- [x] 4.5 Tests: buy/sell balance, fee, quote expired, holding limit, reconcile
- [x] 4.6 Quality gate Fase 4

## FASE 5 — FITUR LINTAS MODUL
### 5A. Vehicle Passport
- [x] 5A.1 core_vehicle_events (hash-chain, append-only)
- [x] 5A.2 Listeners: VehicleAcquired, BookingCompleted
- [x] 5A.3 VerifyPassportAction + halaman publik + QR
- [x] 5A.4 Jual mobil bekas C2C (escrow)
- [x] 5A.5 Tests: rantai valid, manipulasi, C2C, reconcile

### 5B. Smart Repair Escrow
- [x] 5B.1 serve_estimates table
- [x] 5B.2 Flow: estimasi → hold → complete → capture/split
- [x] 5B.3 Tests: final < hold, final > hold, reject, waiting_parts, cancel

### 5C. HODL-to-Drive (Crypto-Backed Financing)
- [x] 5C.1 fin_loans + fin_installments
- [x] 5C.2 Loan flow: kolateral → pinjaman → bayar → alur Store
- [x] 5C.3 Scheduler cicilan + overdue + denda
- [x] 5C.4 Risk monitor (LTV) + margin call + likuidasi
- [x] 5C.5 UI: dashboard pinjaman
- [x] 5C.6 Tests: open loan, cicilan, overdue, margin call, likuidasi, pelunasan, reconcile
- [x] 5.7 Quality gate Fase 5

## FASE 6 — PLATFORM SERVICES
- [x] 6.1 Notification module + bell icon + unread counter
- [x] 6.2 Activity feed
- [x] 6.3 Dashboard Customer terpadu
- [x] 6.4 Dashboard Admin terpadu
- [x] 6.5 Dashboard Mekanik terpadu
- [x] 6.6 Seeder demo lengkap — **DITUTUP 2026-09-30 (Fase 19)**: diimplementasikan komprehensif via `DemoCustomerSeeder` (20 customer ber-PIN & bersaldo, 20 kendaraan berpaspor, 40+ booking terbayar lewat ledger, 3 pinjaman HODL-to-Drive aktif LTV < 70%, 6 produk mobil Store, reconcile 0) dan diverifikasi oleh `DemoCustomerSeederTest`.
- [x] 6.7 Quality gate Fase 6

## DEFINITION OF DONE (FASE 0–6)
- [x] Semua task tercentang
- [x] migrate:fresh --seed, test, build, pint → lolos
- [x] Arch tests hijau
- [x] bank:reconcile + core:verify-passports bersih
- [x] Characterization tests hijau
- [x] Tidak ada TODO/stub/placeholder
- [x] README.md diperbarui
- [x] docs/ARCHITECTURE.md selesai
- [x] docs/DECISIONS.md lengkap

## FASE 7 — MODUL RESTO: FONDASI, MENU, RESEP, HPP
- [x] 7.1 Modul Resto + provider + menu sidebar + route group + tabel resto_outlets, resto_ingredients, resto_unit_conversions, resto_ingredient_costs
- [x] 7.2 Menu & kategori khas Padang (resto_menu_categories, resto_menu_items, resto_menu_item_outlet) + Seeder menu realistis min 40 item
- [x] 7.3 Resep berlapis (BOM multi-level) + RecipeCycleDetected exception + RecipeCostCalculator (HPP per porsi, moving average cost, waste_percent)
- [x] 7.4 Halaman admin: CRUD menu (builder resep dinamis Alpine, HPP per porsi real-time via JSON), CRUD bahan + konversi satuan, outlet list + margin badge
- [x] 7.5 Tests: konversi satuan, HPP resep berlapis presisi 6 desimal, resep sirkular ditolak, harga per outlet override, menu nonaktif tersembunyi
- [x] 7.6 Quality gate Fase 7

## FASE 8 — RESTO: DAPUR, BATCH PRODUKSI & SIKLUS ETALASE HIDANG
- [x] 8.1 Tabel resto_production_batches & resto_batch_consumptions
- [x] 8.2 CookBatchAction: stok potong via InventoryService, cost_total aktual, posting ledger, ShortageException, scale-down resep, varians produksi
- [x] 8.3 Siklus etalase: resto_display_trays, aturan recirculate (max 3x, max 6 jam), waste expense, piring disentuh dihitung terjual, command resto:expire-display
- [x] 8.4 UI Dapur (role kitchen): papan produksi harian, tombol "Masak Batch", monitor etalase countdown warna, tombol buang, rekap waste
- [x] 8.5 Tests: potong bahan, kekurangan stok ditolak, scale-down produksi, cost_per_portion, tray expired jadi waste, recirculate ke-4 ditolak, reconcile bersih
- [x] 8.6 Quality gate Fase 8

## FASE 9 — RESTO: POS HIDANG, SESI MEJA, SHIFT KASIR & TUTUP HARIAN
- [x] 9.1 Tabel resto_tables, resto_table_sessions, resto_order_items, resto_orders
- [x] 9.2 Flow hidang: sesi meja, piring hidang presented, hitung hidangan (consumed vs returned), nasi/minuman pesan, tambuah cepat, PB1 10%, pembulatan Rp100, bayar tunai/wallet/voucher/split, void/refund
- [x] 9.3 Shift kasir & kas: resto_shifts, OpenShiftAction, CloseShiftAction, cash variance posting, SettleCashAction
- [x] 9.4 POS UI: tablet/mobile friendly, grid menu, panel meja & etalase, layar hitung hidangan, keypad, idempotency offline-tolerant
- [x] 9.5 Tutup harian: command resto:close-day, resto_daily_summaries, flag --check vs ledger
- [x] 9.6 Tests: alur hidang lengkap, disentuh sebagian dihitung penuh, PB1 & pembulatan, tunai & wallet, submit ganda idempoten, konkurensi meja, shift variance, void/refund, close-day --check cocok ledger, reconcile bersih
- [x] 9.7 Quality gate Fase 9

## FASE 10 — RESTO: RANTAI PASOK, DAPUR SENTRAL & MULTI-OUTLET
- [x] 10.1 Tabel resto_suppliers, resto_purchase_orders, resto_purchase_order_lines, resto_goods_receipts
- [x] 10.2 ReceiveGoodsAction: stok bahan masuk via InventoryService, moving avg cost BigDecimal, varians harga, posting AP supplier & inventory, PaySupplierAction, aging payable
- [x] 10.3 Dapur sentral & transfer antar outlet: resto_stock_transfers, in-transit account, selisih terima ke waste
- [x] 10.4 Stock opname: resto_stock_counts, adjustment via InventoryService, posting selisih
- [x] 10.5 Peringatan otomatis: stok di bawah min_stock notifikasi + draft PO otomatis, perishable mendekati kedaluwarsa notifikasi dapur
- [x] 10.6 Tests: PO terima parsial & penuh, moving avg cost 3 harga, transfer in-transit, opname minus, aging payable, reconcile bersih
- [x] 10.7 Quality gate Fase 10

## FASE 11 — RESTO: KANAL PENJUALAN, KATERING, FRANCHISE & ANALITIK
- [x] 11.1 Bungkus & delivery: harga takeaway, potong bahan kemasan, resto_deliveries ongkir bertingkat, pesan online wallet, refund parsial
- [x] 11.2 Katering & nasi bungkus massal: resto_catering_orders, flow quote -> customer approve -> HOLD deposit 30% -> produksi -> deliver -> capture + charge sisa, batal < 3 hari potongan deposit, validasi kapasitas pax
- [x] 11.3 Franchise royalty: resto_outlet_contracts, command resto:post-royalty harian dari daily summary ke revenue:group:royalty
- [x] 11.4 Analitik: Menu engineering (Star/Plowhorse/Puzzle/Dog scatter chart), waste report, heatmap sales mix per jam, P&L per outlet dari ledger, peramalan rata-rata bergerak 4 minggu
- [x] 11.5 Tests: takeaway pakai harga bungkus, kemasan potong stok, delivery gagal refund, katering hold -> capture, batal potongan deposit, kapasitas penuh ditolak, royalty posting, klasifikasi menu engineering, reconcile bersih
- [x] 11.6 Quality gate Fase 11

## FASE 12 — MODUL MALL: FONDASI, UNIT & LEASING
- [x] 12.1 Modul Mall + provider + menu. Tabel: mall_properties, mall_zones, mall_units, mall_tenants
- [x] 12.2 mall_leases: fit_out_days, rent_model (fixed|revenue_share|greater_of), eskalasi tahunan, billing_day, grace_days, denda harian
- [x] 12.3 Actions: CreateLeaseAction (anti-overlap), ActivateLeaseAction (tarik deposit via Payment Hub ke deposit:tenant), TerminateLeaseAction (potong tunggakan dari deposit), RenewLeaseAction
- [x] 12.4 UI: site plan per lantai (Tailwind grid/SVG interaktif), occupancy & GLA, daftar lease & expiring soon (<90 hari), direktori tenant publik
- [x] 12.5 Tests: lease ganda ditolak, deposit liability, terminate potong deposit, eskalasi tahun ke-2, occupancy rate, reconcile bersih
- [x] 12.6 Quality gate Fase 12

## FASE 13 — MALL: LAPORAN PENJUALAN TENANT, TAGIHAN BULANAN & TUNGGAKAN
- [x] 13.1 mall_tenant_sales_reports, portal tenant lapor penjualan, TenantSalesProvider contract untuk tenant terintegrasi
- [x] 13.2 Utilitas: mall_utility_readings, mall_utility_tariffs bertingkat, mall_overtime_requests AC overtime
- [x] 13.3 Tagihan bulanan: mall_invoices, mall_invoice_lines, command mall:generate-invoices idempoten, MallAutoDebitAction tanpa PIN, command mall:apply-penalties denda 0,1%/hari bertingkat
- [x] 13.4 Pembayaran sebagian via portal (wallet + PIN) alokasi urut (denda -> utilitas -> service charge -> sewa), status partially_paid
- [x] 13.5 Command mall:audit-billing: audit kesesuaian invoice vs ledger, masukkan ke quality gate
- [x] 13.6 UI: dashboard billing, aging receivable, detail tagihan, input meteran batch, portal tenant
- [x] 13.7 Tests: generate-invoices idempoten, revenue_share_topup bila % > base rent, tarif utilitas bertingkat, bayar cicil urut, denda harian, suspend H+30, isolasi tenant IDOR, audit-billing & reconcile bersih
- [x] 13.8 Quality gate Fase 13

## FASE 14 — MALL: PARKIR, AKSES & FOOTFALL
- [x] 14.1 Tabel mall_parking_zones, mall_parking_tariffs, mall_parking_sessions, mall_parking_members
- [x] 14.2 Tarif progresif BigDecimal: grace 15 menit, pembulatan jam, batas harian, tiket hilang, validasi parkir oleh tenant jadi piutang tenant
- [x] 14.3 Gate simulasi UI: gate masuk (tiket/plat member) & gate keluar (scan tiket, bayar, buka gate), tolak jika penuh, real-time occupancy polling
- [x] 14.4 Footfall: mall_footfall_counts, command mall:simulate-footfall, dashboard footfall & konversi tenant
- [x] 14.5 Tests: tarif grace, 61 menit, 8 jam batas harian, tiket hilang, member aktif gratis, member kedaluwarsa bayar, validasi tenant, kapasitas penuh ditolak, tiket ganda ditolak, query budget, reconcile bersih
- [x] 14.6 Quality gate Fase 14

## FASE 15 — MALL: LOYALTY, VOUCHER, EVENT & FACILITY MANAGEMENT
- [x] 15.1 Loyalty Duta Points (aset ledger PTS): points:user:{id}:PTS & liability:mall:points:PTS, earn dari belanja / struk klaim unik, redeem voucher & tier membership, FIFO expiry mall:expire-points
- [x] 15.2 Voucher mall: mall_vouchers, liability:mall:voucher, settlement mingguan mall:settle-vouchers ke wallet tenant, voucher expired balik ke breakage/penalty
- [x] 15.3 Event & atrium: mall_event_spaces, mall_event_bookings, deteksi bentrok jadwal ConflictException, bazaar booth, kalender bulanan
- [x] 15.4 Facility management: mall_assets, mall_work_orders, SLA priority, command mall:generate-pm-orders, biaya perbaikan masuk tagihan tenant, Kanban board
- [x] 15.5 Tests: earn poin belanja, klaim struk dobel ditolak, redeem voucher, voucher dipakai lalu settle, voucher expired, event bentrok ditolak, PM order terjadwal, SLA breach, biaya perbaikan ke invoice, reconcile IDR & PTS bersih
- [x] 15.6 Quality gate Fase 15

## FASE 16 — INTEGRASI LINTAS LINI
- [x] 16.1 Resto & AutoServe sebagai tenant Duta Mall via TenantSalesProvider (omzet terintegrasi otomatis masuk revenue_share_topup tanpa input manual)
- [x] 16.2 Validasi parkir dari POS Resto via ParkingValidator contract, potong tarif dan masuk piutang tenant
- [x] 16.3 Poin & Voucher lintas modul: LoyaltyLedger contract, order Resto dapat poin, voucher mall bisa dipakai di Resto & Store, poin tukar diskon Store
- [x] 16.4 Kendaraan & parkir: member parkir disinkronkan ke core_vehicles, transfer kepemilikan nonaktifkan parkir member
- [x] 16.5 Dashboard Grup konsolidasi (role admin): P&L per lini bisnis dari ledger, grafik 30 hari, query budget <= 30 query
- [x] 16.6 Navigasi terpadu: sidebar dikelompokkan per lini (Otomotif, Keuangan, Kuliner, Properti), global search Ctrl+K lintas modul
- [x] 16.7 Test integrasi end-to-end satu hari penuh (parkir -> makan hidang -> bayar wallet -> dapat poin -> validasi parkir -> keluar gate -> akhir bulan tagih sewa revenue share -> tenant bayar -> reconcile & audit-billing bersih)
- [x] 16.8 Quality gate Fase 16

## FASE 17 — SKALA, HARDENING & OPERASIONAL
- [x] 17.1 Seeder demo skala besar DemoLargeSeeder (3 outlet resto, 1 central kitchen, 60 tenant, 12 bulan billing, 150.000 parkir, batch inserts)
- [x] 17.2 Performa: tests/Performance/QueryBudgetTest.php, eliminasi N+1, index database yang tepat, cursor pagination, tabel ringkasan
- [x] 17.3 Smoke test semua route: tests/Feature/RouteSmokeTest.php assert per role (200/302/403)
- [x] 17.4 Keamanan: tests/Feature/SecurityTest.php (IDOR, mass assignment, rate limit, brute force PIN, signed URL, replay key, XSS)
- [x] 17.5 Observability & ops: core_audit_logs, halaman admin Kesehatan Sistem, command super:health-check
- [x] 17.6 Arch tests diperluas: batas modul Resto & Mall, tidak ada DB facade di controller, VerifiesWalletPin contract
- [x] 17.7 Quality gate Fase 17 + super:health-check bersih

## FASE 18 — DOKUMENTASI & PENUTUP
- [x] 18.1 README.md: ringkasan platform 5 lini bisnis, cara menjalankan, daftar command, tabel akun demo lengkap per role
- [x] 18.2 docs/ARCHITECTURE.md: ERD Mermaid, diagram integrasi, contracts & events, konvensi ledger (IDR/PTS/kripto), sequence diagram Mermaid
- [x] 18.3 docs/RUNBOOK.md: panduan troubleshooting operasional
- [x] 18.4 docs/DECISIONS.md final: seluruh keputusan teknis Fase 7-18 tercatat
- [x] 18.5 Pembersihan kode: tidak ada TODO/FIXME/stub/dd()/dump()
- [x] 18.6 Quality gate final & laporan penutup di docs/PROGRESS.md

## DEFINITION OF DONE (FASE 7–18)
- [x] Semua task 7.1–18.6 tercentang di docs/PROGRESS.md
- [x] migrate:fresh --seed, php artisan test, npm run build, pint → semua lolos
- [x] bank:reconcile bersih untuk SEMUA aset (IDR, PTS, BTC/ETH/SOL/BNB/USDT)
- [x] core:verify-passports bersih; resto:close-day --check bersih; mall:audit-billing bersih; super:health-check bersih
- [x] Arch tests batas modul hijau: tidak ada import Domain/Application lintas modul; Resto dan Mall hanya berkomunikasi lewat Contracts/Events
- [x] Characterization tests AutoServe & AutoDex dari Fase 0 dan seluruh test Fase 1–6 tetap hijau; jumlah test akhir > jumlah test baseline
- [x] RouteSmokeTest, AuthorizationMatrixTest, SecurityTest, QueryBudgetTest hijau
- [x] Test integrasi lintas lini (16.7) hijau
- [x] Setiap fitur baru punya jalur navigasi yang bisa diklik untuk role yang berhak
- [x] Tidak ada TODO/FIXME/stub/dd()/dump() di modules/
- [x] README.md, docs/ARCHITECTURE.md, docs/RUNBOOK.md, docs/DECISIONS.md, docs/AUDIT.md diperbarui dan konsisten dengan kode

---

## 🏆 LAPORAN PENUTUP & SERAH TERIMA PROYEK (FINAL SIGN-OFF)

Pada tanggal **30 September 2026**, seluruh tahapan ekspansi arsitektur **Superwebsite** (Fase 0 hingga Fase 18) telah diselesaikan secara tuntas dan mandiri dengan standar rekayasa perangkat lunak enterprise:

### 1. Metrik Kualitas & Kesiapan Produksi
- **Test Suite**: **297 Tests, 1317 Assertions (100% Passed, 0 Failures)**.
- **Integritas Moneter Double-Entry**: `php artisan bank:reconcile` menghasilkan **0 diskrepansi saldo** di seluruh 64 akun aktif dan 7 jenis aset (`IDR`, `PTS`, `BTC`, `ETH`, `SOL`, `BNB`, `USDT`).
- **Kriptografi Rantai Paspor**: `php artisan core:verify-passports` memvalidasi seluruh rantai hash SHA-256 paspor kendaraan valid tanpa kerusakan.
- **Audit Penagihan & Utilitas Mall**: `php artisan mall:audit-billing` memverifikasi keselarasan 165 invoice penagihan terhadap buku besar dengan **0 selisih**.
- **Observabilitas 7 Pilar**: `php artisan super:health-check` memverifikasi seluruh komponen platform dalam status **HEALTHY**.
- **Uji Beban Data (Stress Test)**: `DemoLargeSeeder` sukses menginisialisasi 3 outlet resto, 60 tenant/unit mall, 12 bulan billing, dan **150.000 sesi parkir** dalam **3,43 detik**.
- **Anggaran Query SQL**: 9 rute utama beroperasi jauh di bawah ambang batas (Group Dashboard konsolidasi P&L hanya **2 query SQL**).
- **Standar Kode & Build**: `vendor/bin/pint --test` lolos 100%, `npm run build` sukses tanpa error, dan 0 artefak debug (`dd()`, `dump()`, `TODO`, `FIXME`).

---

## FASE 19 — PENUTUPAN UTANG FASE 18
- [x] 19.1 Baseline & commit Fase 18 (AUDIT.md baseline dicatat, docs phase 18 committed)
- [x] 19.2 Stabilkan DemoCustomerSeeder (deterministik, quote kripto, saldo cukup, ListCarProductAction stok tersedia, buffer kolateral 1.6x)
- [x] 19.3 Test seeder: tests/Feature/Seed/DemoCustomerSeederTest.php (20 customer ber-PIN dan saldo > 0, >=20 kendaraan berpaspor valid, >=40 booking terbayar lewat ledger, tepat 3 pinjaman HODL-to-Drive aktif LTV < 70%, 6 produk mobil Store, reconcile 0)
- [x] 19.4 Perbaiki test yang terdampak data seed baru (SecurityTest, RouteSmokeTest, QueryBudgetTest, dll.) tanpa melemahkan assertion/anggaran
- [x] 19.5 Gate harian tidak boleh lolos karena kosong: default seed memuat >= 1 hari usaha Resto ditutup dengan >= 5 order terbayar, dan >= 1 bulan tagihan Mall terbit sebagian dibayar, audit-billing dan resto:close-day melaporkan nilai non-nol
- [x] 19.6 Hapus kolom users.pin: migrasi drop kolom, hapus referensi, verifikasi PIN tetap via contract Banking
- [x] 19.7 Kompatibilitas DemoLargeSeeder: jalankan ulang di atas DatabaseSeeder baru tanpa bentrok unique, ukur ulang benchmark (3.24s, 0 collision)
- [x] 19.8 Rapikan dokumen: centang 6.6 dengan catatan benar, perbaiki DoD 0-6, catat DECISIONS seeder, update akun demo di README
- [x] 19.9 Quality gate Fase 19 (305 passed / 1569 assertions, Pint clean, build clean, 89 ledger accounts, 30 passports valid, resto/mall/health gates pass)

## FASE 20 — FONDASI LOGISTIK: MODUL, JARINGAN, ARMADA
- [x] 20.1 Kerangka modul modules/Logistics, provider, MenuRegistry "Logistik", roles (logistics_admin, dispatcher, hub_operator, driver, shipper), policies, arch tests batas modul
- [x] 20.2 Lokasi & jaringan: lgx_locations, lgx_lanes, seed jaringan berpusat di Banjarmasin (pelabuhan UN/LOCODE, bandara IATA, hub darat, CFS, depot), CRUD + SVG skematik
- [x] 20.3 Armada: lgx_trucks (terhubung Vehicle Passport core_vehicles, DA plate), lgx_trailers, lgx_vessels (IMO check digit), lgx_aircraft, lgx_containers (ISO 6346 check digit), lgx_ulds, unit tests check digit
- [x] 20.4 Pengemudi & kru: lgx_drivers (SIM class, expiry, hub asal), batas jam mengemudi UU 22/2009 maks 8 jam/hari & istirahat 30 menit per 4 jam
- [x] 20.5 Seeder LogisticsSeeder (30 truk berpaspor, 8 trailer, 4 kapal, 2 pesawat, 300 kontainer ISO 6346, 12 driver, 3 dispatcher, 4 hub operator, 5 shipper, 1 admin), akun demo di README
- [x] 20.6 Quality gate Fase 20

## FASE 21 — SHIPMENT, TARIF, BOOKING & PORTAL SHIPPER
- [x] 21.1 Model shipment: lgx_shipments (SRX + 10 digit + check digit Luhn), state machine canTransitionTo(), lgx_packages (berat, dimensi, hs_code, dg_un_number, temp reefer)
- [x] 21.2 Berat tertagih (chargeable weight) BigDecimal: max(aktual, volumetrik), pembagi volumetrik per mode, pembulatan
- [x] 21.3 Rate card & surcharge: lgx_rate_cards, lgx_rate_brackets, lgx_surcharges, validasi non-overlapping, PPN simulasi 11%
- [x] 21.4 Quote: QuoteShipmentAction -> lgx_quotes timelock 15 menit + hash anti-manipulasi
- [x] 21.5 Booking prabayar: BookShipmentAction, PIN wajib via VerifiesWalletPin, PaymentGateway::charge ke lgx:unearned_freight
- [x] 21.6 Akun postpaid B2B: lgx_shipper_accounts, credit limit, lgx:invoice-shippers bulanan idempotent, bayar invoice via wallet + PIN
- [x] 21.7 Pembatalan: refund via gateway dikurangi cancellation fee -> lgx:freight_revenue (pre-pickup), tolak post-pickup
- [x] 21.8 Portal shipper (mobile-responsive): form buat shipment, upload massal CSV s/d 5.000 baris queued job, cetak label QR
- [x] 21.9 Pelacakan publik: /track/{tracking_number} tanpa login, throttle 30/menit, penyamaran PII data consignee, timeline event
- [x] 21.10 Quality gate Fase 21

## FASE 22 — OPERASI JARINGAN MULTIMODA: KAPASITAS, RUTE, KONSOLIDASI, HUB, LAST-MILE
- [x] 22.1 Jadwal: lgx_schedules (truk, kapal voyage multi-port, pesawat freighter), kapasitas multi-dimensi
- [x] 22.2 Reservasi kapasitas: ReserveCapacityAction lockForUpdate, ReleaseCapacityAction, lgx:capacity-check
- [x] 22.3 Perencana rute: RoutePlanner pure domain service (Dijkstra/k-shortest paths graf terisolasi), filter mode & DG & reefer, lgx_shipment_legs
- [x] 22.4 Konsolidasi & load planning: lgx_loads + lgx_load_items (FCL, LCL first-fit-decreasing CFS, VGM SOLAS sebelum muat kapal)
- [x] 22.5 Chain of custody: lgx_tracking_events append-only hash chain per shipment, immutable, lgx:verify-custody chunkById
- [ ] 22.6 Operasi hub (hub_operator, mobile): scan inbound, sortir, outbound, deteksi otomatis exception missort
- [ ] 22.7 Papan dispatch (dispatcher): assign armada + driver dengan validasi SIM, jam kerja, status maintenance, paspor kendaraan
- [ ] 22.8 Aplikasi driver (mobile): daftar stop, scan pickup, Proof of Delivery (nama penerima, OTP 6 digit hash, foto bukti, tanda tangan canvas), 3x gagal -> ReturnToSender
- [ ] 22.9 Exception & SLA: tipe exception terstruktur, SLA monitoring, lgx:detect-late idempotent
- [ ] 22.10 Quality gate Fase 22 (termasuk lgx:verify-custody dan lgx:capacity-check)

## FASE 23 — UANG LOGISTIK: PENDAPATAN, COD, CARRIER, KLAIM, D&D, BEA CUKAI
- [ ] 23.1 Pengakuan pendapatan (Alur 2 & 4): ShipmentDelivered event -> debit unearned_freight / kredit freight_revenue
- [ ] 23.2 COD (Alur 6-8): driver collect cash -> setor di hub -> lgx:settle-cod D+N ke shipper + cod_fee_revenue, dashboard COD
- [ ] 23.3 Carrier subkontrak (Alur 9-10): lgx_carriers, akrual biaya leg, lgx:pay-carriers mingguan, laporan margin shipment
- [ ] 23.4 Klaim (Alur 11): workflow klaim asuransi/non-asuransi, aturan 4 mata (penyetuju != pengaju != pembuat), anti bayar ganda
- [ ] 23.5 Demurrage & Detention (Alur 12): lgx_dd_tariffs, free time, lgx:accrue-dd harian zona waktu lokasi, invoice D&D
- [ ] 23.6 Bea cukai (Alur 13): lgx_customs_declarations PIB/PEB, lgx_hs_tariffs, simulasi bea masuk/PPN/PPh 22, CustomsHold
- [ ] 23.7 Bahan bakar & biaya truk: lgx_fuel_logs (liter x 1000 integer), konsumsi km/l, flag anomali > 30%
- [ ] 23.8 lgx:audit-billing command: invoice freight, D&D, bea cukai vs ledger, pendapatan Delivered vs unearned, non-nol pada seed default
- [ ] 23.9 Quality gate Fase 23 (termasuk lgx:audit-billing)

## FASE 24 — INTEGRASI LINTAS LINI
- [ ] 24.1 Store -> Logistik: event order dibayar -> shipment otomatis via ShipmentBooking contract, ongkir Store -> unearned_freight, resi di order detail
- [ ] 24.2 Pengiriman mobil (Store/AutoDex/HODL-to-Drive): shipment FTL car carrier, event delivered_by_carrier masuk Vehicle Passport
- [ ] 24.3 Perawatan armada -> AutoServe: trip odometer trigger service_interval_m -> FleetServiceDue event -> booking bengkel via contract, status Maintenance
- [ ] 24.4 Resto -> Logistik (cold-chain): replenishment dapur pusat CK-01 -> shipment reefer, lgx_temperature_readings, excursion alert, terima stok via Inventory
- [ ] 24.5 Mall -> Logistik (loading dock): lgx_dock_appointments di Duta Mall, slot time-lock tanpa overlap, portal tenant booking dock, satpam check-in/out
- [ ] 24.6 Finance & observabilitas: pendapatan Logistik masuk Group Dashboard P&L tanpa menaikkan query budget, pilar Logistik di super:health-check
- [ ] 24.7 Quality gate Fase 24

## FASE 25 — SKALA, API, HARDENING, CONTROL TOWER
- [ ] 25.1 LogisticsLargeSeeder: >= 200.000 shipment, >= 2.000.000 tracking event, >= 5.000 kontainer, >= 20 kapal, >= 300 truk, 12 bulan riwayat, B2B postpaid ledger, benchmark waktu
- [ ] 25.2 Anggaran kinerja (QueryBudgetTest): lookup resi <= 3 query p95 < 50ms, dispatcher <= 10 query, control tower <= 12 query, lgx:accrue-dd < 30s, EXPLAIN docs
- [ ] 25.3 API v1 (Sanctum): quotes, shipments (Idempotency-Key header), tracking, token abilities, rate limit, docs/API.md
- [ ] 25.4 Webhook outbox: lgx_webhook_endpoints + deliveries, HMAC-SHA256, exponential backoff max 8 retry, dead-letter, manual replay
- [ ] 25.5 Queue & scheduler: queued jobs idempotent ShouldBeUnique, seluruh command di routes/console.php, docs/RUNBOOK.md update
- [ ] 25.6 Matriks otorisasi: SecurityTest & RouteSmokeTest mencakup seluruh rute dan role logistik
- [ ] 25.7 Control Tower (logistics_admin): KPI OTIF, status chart, armada, dwell time, COD, margin per lane
- [ ] 25.8 Dokumentasi final: ARCHITECTURE, RUNBOOK, README, DECISIONS lengkap
- [ ] 25.9 Quality gate final: seluruh test & large seeder lolos, semua gate hijau

## DEFINITION OF DONE (FASE 19–25)
- [ ] Semua task 19.1–25.9 tercentang, masing-masing di commit-nya sendiri.
- [ ] Jumlah test >= baseline 19.1 (297 test, 1317 assertions) dan naik di setiap fase; tidak ada test yang di-skip/dilemahkan.
- [ ] Semua quality gate lulus pada commit terakhir; bank:reconcile = 0 untuk semua aset.
- [ ] Setiap alur uang di tabel Fase 23 punya test (a)–(e).
- [ ] Setiap fitur baru bisa dicapai lewat klik oleh role yang berhak (dibuktikan oleh RouteSmokeTest + entri menu).
- [ ] Portal shipper, aplikasi driver, operasi hub, dan pelacakan publik responsif di lebar 375 px.
- [ ] ARCHITECTURE, DECISIONS, BLOCKERS, RUNBOOK, README, API mutakhir.
- [ ] Working tree bersih (git status kosong).




