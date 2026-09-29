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
- [ ] 0.9 Arch tests batas modul
- [ ] 0.10 Quality gate Fase 0

## FASE 1 — CORE BANKING (DOUBLE-ENTRY LEDGER)
- [ ] 1.1 Migrations (ledger_accounts, ledger_transactions, ledger_entries, wallet_pins)
- [ ] 1.2 LedgerService (double-entry posting, idempotency, locking)
- [ ] 1.3 Akun sistem via seeder
- [ ] 1.4 HasLedgerAccounts trait + auto wallet creation
- [ ] 1.5 Actions: SetPin, VerifyPin, TopUp, Transfer, FreezeAccount, ManualAdjustment
- [ ] 1.6 StatementQuery + CSV export
- [ ] 1.7 bank:reconcile command
- [ ] 1.8 UI: Wallet, Transfer, Mutasi, Admin Ledger
- [ ] 1.9 Tests: posting, insufficient funds, idempotency, concurrent, PIN lockout, reconcile
- [ ] 1.10 Quality gate Fase 1

## FASE 2 — PAYMENT HUB + INTEGRASI AUTOSERVE
- [ ] 2.1 Payable contract
- [ ] 2.2 pay_payment_intents table
- [ ] 2.3 PaymentGateway: charge, hold, capture, release, refund
- [ ] 2.4 AutoServe Invoice implements Payable
- [ ] 2.5 Flow bayar invoice (saldo, PIN, badge LUNAS)
- [ ] 2.6 Tests: bayar, saldo kurang, idempotent, hold/capture/release, refund, reconcile
- [ ] 2.7 Quality gate Fase 2

## FASE 3 — CATALOG, INVENTORY & STORE
- [ ] 3.1 Inventory: stock_movements, InventoryService
- [ ] 3.2 store_products + store_items + store_categories, migrasi sparepart
- [ ] 3.3 Refactor AutoServe stok → InventoryService
- [ ] 3.4 Mobil sebagai produk (dex_car → store_product)
- [ ] 3.5 Cart & Checkout, Orders, auto-cancel
- [ ] 3.6 UI Store: katalog, produk, cart, checkout, riwayat order
- [ ] 3.7 Tests: checkout, stok kurang, saldo kurang, beli mobil → Vehicle, refund
- [ ] 3.8 Quality gate Fase 3

## FASE 4 — CRYPTO TRACKER (SIMULASI)
- [ ] 4.1 crypto_assets + crypto_price_ticks + seeder
- [ ] 4.2 PriceEngine (crypto:tick) + PriceFeed contract + quotes
- [ ] 4.3 crypto_trades + buy/sell flow
- [ ] 4.4 Portfolio: holdings, P/L, charts, alerts
- [ ] 4.5 Tests: buy/sell balance, fee, quote expired, holding limit, reconcile
- [ ] 4.6 Quality gate Fase 4

## FASE 5 — FITUR LINTAS MODUL
### 5A. Vehicle Passport
- [ ] 5A.1 core_vehicle_events (hash-chain, append-only)
- [ ] 5A.2 Listeners: VehicleAcquired, BookingCompleted
- [ ] 5A.3 VerifyPassportAction + halaman publik + QR
- [ ] 5A.4 Jual mobil bekas C2C (escrow)
- [ ] 5A.5 Tests: rantai valid, manipulasi, C2C, reconcile

### 5B. Smart Repair Escrow
- [ ] 5B.1 serve_estimates table
- [ ] 5B.2 Flow: estimasi → hold → complete → capture/split
- [ ] 5B.3 Tests: final < hold, final > hold, reject, waiting_parts, cancel

### 5C. HODL-to-Drive (Crypto-Backed Financing)
- [ ] 5C.1 fin_loans + fin_installments
- [ ] 5C.2 Loan flow: kolateral → pinjaman → bayar → alur Store
- [ ] 5C.3 Scheduler cicilan + overdue + denda
- [ ] 5C.4 Risk monitor (LTV) + margin call + likuidasi
- [ ] 5C.5 UI: dashboard pinjaman
- [ ] 5C.6 Tests: open loan, cicilan, overdue, margin call, likuidasi, pelunasan, reconcile
- [ ] 5.7 Quality gate Fase 5

## FASE 6 — PLATFORM SERVICES
- [ ] 6.1 Notification module + bell icon + unread counter
- [ ] 6.2 Activity feed
- [ ] 6.3 Dashboard Customer terpadu
- [ ] 6.4 Dashboard Admin terpadu
- [ ] 6.5 Dashboard Mekanik terpadu
- [ ] 6.6 Seeder demo lengkap
- [ ] 6.7 Quality gate Fase 6

## DEFINITION OF DONE
- [ ] Semua task tercentang
- [ ] migrate:fresh --seed, test, build, pint → lolos
- [ ] Arch tests hijau
- [ ] bank:reconcile + core:verify-passports bersih
- [ ] Characterization tests hijau
- [ ] Tidak ada TODO/stub/placeholder
- [ ] README.md diperbarui
- [ ] docs/ARCHITECTURE.md selesai
- [ ] docs/DECISIONS.md lengkap
