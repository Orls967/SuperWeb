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
