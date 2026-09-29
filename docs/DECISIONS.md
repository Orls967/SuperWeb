# Decisions Log

## 2026-09-29: Database Engine
- **Context:** Spec says MySQL, but project uses SQLite.
- **Decision:** Keep SQLite for development simplicity. Use `DB_CONNECTION=sqlite` in .env and `:memory:` for tests. The spec's MySQL references are treated as "relational DB" generically. SQLite supports all features needed (transactions, foreign keys, indexes). Enum columns will use string columns with validation since SQLite doesn't support native enums well in migrations — we'll use PHP Enum classes for type safety instead.
- **Reason:** Project was initialized with SQLite. Switching to MySQL would require local MySQL setup that may not exist. SQLite is sufficient for all features described.

## 2026-09-29: Pest vs PHPUnit
- **Context:** Spec requires Pest. Project currently has PHPUnit with Breeze tests.
- **Decision:** Install Pest alongside PHPUnit. Migrate existing Breeze tests to Pest syntax. All new tests will use Pest.
- **Reason:** Spec explicitly allows pestphp/pest. Pest provides cleaner syntax and arch testing support.

## 2026-09-29: Money Storage — decimal vs integer
- **Context:** Spec says "IDR stored as integer rupiah" but also says "ledger amounts decimal(36,18)".
- **Decision:** Regular IDR columns (prices, costs, totals) stay as integer (rupiah, no decimals). Ledger entries use decimal(36,18) as specified for multi-asset uniformity. The Money value object handles conversion.
- **Reason:** Follows spec literally. IDR has no subunits in practice; ledger needs decimal for crypto precision.

## 2026-09-29: SQLite enum handling
- **Context:** SQLite doesn't support MySQL ENUM type. Existing migrations use `$table->enum()` which SQLite handles as string with CHECK constraint.
- **Decision:** For new tables, use `$table->string()` columns with PHP Enum validation. This is more portable and aligns with the "use PHP Enum" pattern in the spec.
- **Reason:** Better compatibility and type safety through application-level enforcement.

## 2026-09-30: Genesis Liquidity Seeding via Double-Entry Ledger
- **Context:** System exchange accounts need initial liquidity for simulated exchange and trading.
- **Decision:** Seed initial liquidity via `LedgerService->post()` with `clearing:external:{ASSET}` as the source account (with `allow_negative=true`), rather than directly inserting arbitrary balance numbers into accounts.
- **Reason:** Guarantees that `bank:reconcile` passes with 0 discrepancy: global `SUM(entries) == 0` for all assets and `cached_balance == SUM(entries)` for every account from the very start.

## 2026-09-30: Atomicity & Lock Ordering in LedgerService
- **Context:** Concurrent transactions transferring between multiple accounts can deadlock if locks are acquired in arbitrary order.
- **Decision:** Collect all distinct involved account IDs and lock them via `whereIn('id', $ids)->orderBy('id', 'asc')->lockForUpdate()`. Balance calculations track running balances per account within the atomic block.
- **Reason:** Prevents database deadlocks and race conditions completely.

## 2026-09-30: Payment Gateway Hold-Capture Remainder Return & Invoice Payable
- **Context:** When capturing a payment intent that was held, the final amount may be less than the held amount (e.g. estimate higher than actual cost).
- **Decision:** `PaymentGatewayService::capture` executes an atomic ledger transaction that: (1) moves the final amount from `escrow:payment:IDR` to `revenueSplits`, and (2) immediately credits any remaining difference (`held - final`) back to the payer's wallet account.
- **Reason:** Ensures funds are never stranded in escrow and the ledger remains perfectly balanced and reconciled without requiring manual customer refund requests.

## 2026-09-30: Inventory Reservation & Checkout Morph Resolution
- **Context:** Store checkout required simultaneous inventory reservation, wallet balance verification with PIN, payment gateway charging, order fulfillment (including auto-acquiring cars to user's garage), and rollback on failure.
- **Decision:** Reservation deducts cached stock with `RESERVATION` reason movement. If payment succeeds, reservation is committed to `SALE` reason. If checkout fails, reservations are immediately released via `RESERVATION_RELEASE`. In `PaymentGatewayService`, `payable->getMorphClass()` is used so morph maps resolve properly to alias strings (e.g. `store_order`).
- **Reason:** Keeps inventory counts strictly truthful without race conditions, guarantees zero stock leakage, and integrates seamlessly with double-entry revenue splits and car ownership transfer.

## 2026-09-30: 5-Entry Double-Entry Crypto Trading & 15-Second Locked Quotes
- **Context:** Crypto trading required market order simulation with 15-second price lock, bi-directional conversions, 0.2% exchange fees, and multi-asset ledger integrity across fiat (IDR) and coins (BTC, ETH, SOL, BNB, USDT).
- **Decision:** Trades execute as an atomic 5-entry ledger transaction: (1) user IDR debit/credit, (2) system exchange IDR credit/debit, (3) exchange fee credit to `fee:banking:IDR`, (4) exchange crypto debit/credit, and (5) user crypto wallet credit/debit. Quotes enforce a strict 15-second expiration timestamp.
- **Reason:** Guarantees that both IDR and crypto assets balance individually to zero sum on every transaction (`SUM(IDR) = 0`, `SUM(ASSET) = 0`), ensuring `bank:reconcile` remains pristine while preventing slippage through locked quotes.

## 2026-09-30: C2C Used-Car Sale Uses Dedicated Buy-Now Flow, Not the Cart
- **Context:** Fase 5A.4 requires buying a used car from another user with escrow (hold → handover → confirm → capture), while the regular cart charges the wallet immediately.
- **Decision:** C2C listings (`productable_type = 'core_vehicle'`, `seller_id` set) are excluded from the normal catalog and cart (`CartService::addItem` throws). They are bought through a dedicated route `/store/mobil-bekas/{product:slug}/beli` handled by `PurchaseC2cVehicleAction`, which creates a single-item order and calls `PaymentGateway::hold`.
- **Reason:** Mixing escrow-held and immediately-charged items in one cart would make a single order need two conflicting payment modes. A dedicated flow keeps both paths simple and each order has exactly one payment intent.

## 2026-09-30: C2C Escrow Statuses, Platform Fee and Shipping
- **Context:** The existing `OrderStatus` enum only modelled platform-fulfilled orders (paid → processing → shipped → completed).
- **Decision:** Added `awaiting_handover`, `awaiting_confirmation` and `disputed`. Revenue split for a C2C order is `wallet:user:{seller}:IDR` for 99% and `revenue:store:IDR` for the 1% platform fee, computed with `intdiv` so the two integers always sum exactly to `grand_total`. C2C orders carry `shipping_fee = 0` because the car is handed over directly between the two users.
- **Reason:** The fee must not introduce rounding drift, otherwise `bank:reconcile` would report a discrepancy. A zero shipping fee keeps the captured amount identical to the listed price, so the seller and buyer both see the exact number they agreed on.

## 2026-09-30: C2C Escrow Expiry Handled at Order Level, Not Payment Intent
- **Context:** `payment:release-expired-holds` automatically releases any held intent whose `expires_at` has passed — which would refund a C2C buyer while the seller waits to hand over the car.
- **Decision:** C2C holds are created with `expires_at = null`. The 3-day confirmation window lives on the order (`auto_capture_at`) and is processed by the new `store:auto-capture-c2c` command (hourly), which *captures* to the seller instead of releasing. Opening a dispute clears `auto_capture_at` so funds stay in escrow until an admin decides.
- **Reason:** Auto-release and auto-capture are opposite outcomes; keeping the C2C deadline on the order prevents the generic payment job from resolving the deal the wrong way.

## 2026-09-30: Ownership Transfer Exposed Through a Core Contract
- **Context:** Store must move a `core_vehicles` row to the buyer and append an `ownership_transferred` block to the passport, but the hash-chain logic lives in Core.
- **Decision:** Added `Modules\Core\Contracts\TransfersVehicleOwnership`, implemented by `TransferVehicleOwnershipAction` (locks the vehicle row, reassigns `user_id`, clears the buyer's wishlist entry, records the passport block). Store resolves it from the container, mirroring the existing `AcquiresVehicle` contract.
- **Reason:** Keeps the append-only chain logic in one place and lets Finance (Fase 5C) reuse the same transfer path without duplicating hash-chain code.

## 2026-09-30: View Modul Duplikat Dihapus — Namespace `serve::` / `dex::` Jadi Sumber Tunggal
- **Context:** Setelah refactor Fase 0.5, view AutoServe/AutoDex ada dua salinan: `resources/views/{bookings,services,spareparts,autodex}` dan di dalam modul. Controller memanggil `view('bookings.show')` sehingga salinan app-level yang dirender, dan salinan modul menjadi kode mati. Akibatnya fitur yang hanya ditambahkan di salinan modul (tautan Paspor Digital di My Garage 5A.3 dan tombol "Beli di Store" 3.6) tidak pernah tampil di aplikasi.
- **Decision:** Salinan app-level dihapus; controller memakai view bernamespace (`serve::bookings.show`, `dex::garage`, dst). Salinan modul adalah superset dari salinan app sehingga tidak ada konten yang hilang. Satu assertion pada characterization test diperbarui dari `assertViewIs('bookings.invoice')` menjadi `assertViewIs('serve::bookings.invoice')` — assertion perilaku (status 200 dan perubahan status booking) tetap utuh.
- **Reason:** Duplikasi diam-diam ini membuat setiap perubahan UI modul berisiko tidak berefek. Satu sumber kebenaran mencegah bug senyap berulang, sekaligus menegakkan konvensi `view('{modul}::...')` dari spesifikasi.

## 2026-09-30: Estimate Sebagai Payable dengan Nilai Posting Tersimpan
- **Context:** Satu estimasi bisa menghasilkan dua posting ledger berbeda: capture escrow (sebesar dana ditahan) dan tagihan selisih bila biaya aktual melebihi estimasi. `PaymentGatewayService` membaca ulang payable dari database saat capture, sehingga split tidak bisa dititipkan lewat properti sementara.
- **Decision:** `serve_estimates` menyimpan `final_service_total` dan `final_parts_total` yang selalu berisi pembagian untuk posting berikutnya; `revenueSplits()` dan `payableAmount()` membacanya (fallback ke nilai estimasi bila belum diisi). Bagian escrow dihitung proporsional terhadap biaya akhir (`intdiv`), sisanya menjadi tagihan selisih, sehingga total kedua posting persis sama dengan biaya aktual.
- **Reason:** Menjaga invarian `payableAmount() == Σ revenueSplits()` yang dibutuhkan `charge()`, tanpa membuat model Payable kedua, dan menjamin `bank:reconcile` tetap nol.

## 2026-09-30: Backorder Sparepart Dibayar Akun Beban Bengkel
- **Context:** Saat estimasi disetujui tetapi stok sparepart kurang, spesifikasi meminta order internal yang dibayar akun sistem bengkel, bukan dompet customer.
- **Decision:** Ditambahkan akun sistem `expense:autoserve:parts:IDR` (kind baru `expense`, `allow_negative = true`). Pembelian backorder diposting sebagai `expense:autoserve:parts:IDR −biaya` dan `clearing:external:IDR +biaya`, dengan `store_order` internal berstatus `processing` atas nama akun admin bengkel. Saat barang diterima, stok masuk lewat movement `purchase` dan booking kembali dari `waiting_parts` ke `in_progress`.
- **Reason:** Arus kas ke pemasok tetap tercatat double-entry (reconcile nol), terpisah dari escrow customer, dan stok bengkel/toko tetap satu angka yang sama.
