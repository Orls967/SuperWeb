# Desain Arsitektur & Teknis — Lini 17: Ritel & E-Commerce (Ret) MVP

Dokumen ini menguraikan arsitektur teknis, desain domain, model data, skema akuntansi ledger ganda, dan strategi integrasi modul `Ret` sesuai cetak biru KONSEP.md.

---

## 1. Ikhtisar Arsitektur Lini 17

Lini 17 berfungsi sebagai mesin omnichannel e-commerce, marketplace multi-vendor, dan ekosistem super-app ritel.

```mermaid
flowchart TD
    Customer([Pelanggan / Super App]) -->|Checkout Cart| OrderAction[CheckoutOmnichannelOrderAction]
    OrderAction -->|1. Validasi Harga vs MAP| MapLookup[MapPriceLookupInterface]
    OrderAction -->|2. Reservasi Stok| InvService[InventoryService (inv_)]
    OrderAction -->|3. Capture Pembayaran| PayGateway[PaymentGateway (pay_)]
    OrderAction -->|4. Post Escrow Ledger| Ledger[LedgerService (bank_)]
    
    OrderAction -->|Terbitkan Order & Split| Planner[FulfillmentPlanner (B0″.9)]
    Planner -->|Fulfillment Split Plan| Splits[ret_fulfillment_splits]
    
    Splits -->|Pengiriman Selesai / Event| SettleAction[SettleRetailSellerEscrowAction]
    SettleAction -->|Release Escrow ke AP Seller + Fee Komisi| Ledger
    
    Customer -->|Program Loyalitas| CashbackService[SuperAppCashbackService]
    CashbackService -->|Terbitkan Cashback / Liabilitas| Ledger
    CashbackService -->|Kedaluwarsa Cashback / Breakage Revenue| Ledger
```

---

## 2. Skema Database & Tabel (`ret_`)

1. **`ret_channels`**:
   - `id` (string uuid), `channel_code` (unique string), `channel_name` (string), `channel_type` (`WEB`, `STORE`, `MARKETPLACE_3P`), `default_commission_bps` (unsigned int bps, e.g. 500 = 5%), `status` (`ACTIVE`, `INACTIVE`), timestamps.
2. **`ret_sellers`**:
   - `id` (string uuid), `party_id` (string, relasi ke Party), `seller_name` (string), `kyb_status` (`VERIFIED`, `PENDING`), `commission_override_bps` (nullable int), `payout_account_id` (string), timestamps.
3. **`ret_listings`**:
   - `id` (string uuid), `seller_id` (string FK), `channel_id` (string FK), `sku` (string), `title` (string), `price_minor` (bigInteger IDR), `map_price_minor` (bigInteger IDR, dari Pricing), `is_active` (boolean), timestamps.
4. **`ret_orders`**:
   - `id` (string uuid), `order_number` (string unique gapless), `customer_id` (string), `channel_id` (string FK), `seller_id` (nullable string FK), `total_amount_minor` (bigInteger IDR), `commission_amount_minor` (bigInteger IDR), `packaging_deposit_minor` (bigInteger IDR), `status` (`PENDING`, `RESERVED`, `PAID`, `FULFILLED`, `CANCELLED`, `REFUNDED`), `idempotency_key` (string unique), timestamps.
5. **`ret_order_items`**:
   - `id` (string uuid), `order_id` (string FK), `sku` (string), `quantity` (int), `unit_price_minor` (bigInteger), `subtotal_minor` (bigInteger), timestamps.
6. **`ret_fulfillment_splits`**:
   - `id` (string uuid), `order_id` (string FK), `source_location_id` (string), `source_type` (`DARK_STORE`, `CENTRAL_WAREHOUSE`, `STORE_FRONT`), `allocated_quantity` (int), `status` (`PLANNED`, `PICKING`, `SHIPPED`, `DELIVERED`), timestamps.
7. **`ret_cashback_transactions`**:
   - `id` (string uuid), `customer_id` (string), `order_id` (nullable string FK), `amount_minor` (bigInteger), `type` (`EARNED`, `REDEEMED`, `EXPIRED`), `expires_at` (datetime), `idempotency_key` (string unique), timestamps.
8. **`ret_packaging_deposits`**:
   - `id` (string uuid), `order_id` (string FK), `customer_id` (string), `package_code` (string), `deposit_amount_minor` (bigInteger), `status` (`HELD`, `REFUNDED`, `FORFEITED`), timestamps.

---

## 3. Buku Besar Akuntansi (Double-Entry Ledger Chart of Accounts)

| Kode Akun | Jenis (`kind`) | Saldo Normal | Deskripsi |
|---|---|---|---|
| `escrow:payment:IDR` | `escrow` | Kredit | Dana pembayaran pesanan yang ditahan sebelum serah terima |
| `ap:ret:seller:{party_id}:IDR` | `ap` | Kredit | Utang penyelesaian penjualan kepada mitra penjual (3P) |
| `ret:commission_revenue:IDR` | `revenue` | Kredit | Pendapatan komisi marketplace dari nilai transaksi (GMV) |
| `ret:omnichannel_sales_revenue:IDR` | `revenue` | Kredit | Pendapatan penjualan ritel inventaris langsung (1P) |
| `ret:cashback_liability:IDR` | `liability` | Kredit | Liabilitas saldo poin cashback yang beredar dan belum ditukarkan |
| `ret:cashback_promo_expense:IDR` | `expense` | Debit | Beban pemasaran/promosi saat cashback diterbitkan |
| `ret:cashback_breakage_revenue:IDR` | `revenue` | Kredit | Pengakuan pendapatan keuntungan dari poin cashback yang kedaluwarsa |
| `ret:deposit_liability:IDR` | `liability` | Kredit | Liabilitas jaminan wadah/kemasan dapat guna ulang |
| `ret:inventory_shrinkage_expense:IDR`| `expense` | Debit | Beban kerugian selisih susut fisik inventaris toko |
| `ret:inventory_asset:IDR` | `asset` | Debit | Nilai inventaris buku ritel (tereliminasi terhadap stok fisik) |

---

## 4. Pola Anti-Double-Sell (Unified Inventory OMS)

Modul `Ret` **tidak** menyimpan saldo inventaris master sendiri:
1. Ketika pemesanan dibuat (`CheckoutOmnichannelOrderAction`), aksi memanggil:
   ```php
   $reservationId = $this->inventoryService->reserveStock($sku, $qty, $orderNumber);
   ```
2. Saldo stok dikunci dengan `lockForUpdate` di level `InventoryService`.
3. Setelah pembayaran terverifikasi, stok di-commit:
   ```php
   $this->inventoryService->commitStockReservation($reservationId);
   ```
4. Jika pesanan dibatalkan atau kedaluwarsa, reservasi dilepaskan:
   ```php
   $this->inventoryService->releaseStockReservation($reservationId);
   ```
5. Tabel lama `ret_inventory_items` didepresiasi dan diselaraskan untuk kepatuhan kompatibilitas `RetAuditCorruptionFixture`.

---

## 5. Algoritma Numerik Bersama (Shared Numerics B0″)

- **FulfillmentPlanner & FulfillmentPlannerVerifier (B0″.9)**:
  Membagi pesanan multi-item ke beberapa titik gudang/toko terdekat dengan biaya pengiriman minimal dan ketersediaan stok tanpa pecahan barang yang tidak valid.
- **Hamilton Proportional Allocation**:
  Menghitung bagi hasil komisi seller dan diskon promosi secara presisi bulat rupiah tanpa kehilangan 1 rupiah pun ($\sum \text{alokasi} == \text{total}$).

---

## 6. Audit Operasional Dua-Sumber (`ret:audit`)

Audit dua sumber memvalidasi konsistensi data operasional terhadap transaksi ledger:
1. **Rekonsiliasi Utang Penjual (AP Seller)**:
   $$\text{Saldo Ledger AP Seller} = \sum (\text{Order Terkirim} - \text{Komisi Marketplace} - \text{Refund})$$
2. **Rekonsiliasi Liabilitas Cashback**:
   $$\text{Saldo Ledger Cashback Liability} = \sum \text{Cashback Terbit} - \sum \text{Cashback Ditebus} - \sum \text{Cashback Kedaluwarsa}$$
3. **Rekonsiliasi Deposit Kemasan**:
   $$\text{Saldo Ledger Deposit Liability} = \sum \text{Deposit Diterima} - \sum \text{Deposit Dikembalikan}$$
4. **Rekonsiliasi Stok**:
   Memeriksa bahwa tidak ada pesanan dengan status `PAID` yang tidak memiliki reservasi/komit inventaris sah.
