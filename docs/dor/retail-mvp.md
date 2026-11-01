# Definition of Ready (DoR) — Lini 17: Ritel & E-Commerce (Ret) MVP

Dokumen ini mendefinisikan kesiapan implementasi untuk Lini 17 (Fase 137, 138, 139) berdasarkan KONSEP.md dan PROGRESS.md.

---

## 1. Rujukan dan Sitasi Dokumen Acuan

### 1.1 docs/KONSEP.md
- **§A1 (baris 70–108)**: Registry modul & prefiks tabel resmi `ret_`. Modul `Ret` adalah pemilik tunggal dari tabel berprefiks `ret_`. Modul `Ret` tidak mengelola stok fisik sendiri, melainkan mendelegasikan inventaris kepada `InventoryService` (`inv_`).
- **§A2 (baris 109–150)**: Ledger & Uang:
  - **A2.1 (baris 111–122)**: Konvensi tanda tunggal kredit (+), debit (-). Sisi normal akun pendapatan/kewajiban/escrow (kredit), beban/aset/piutang (debit).
  - **A2.2 (baris 124)**: Format kode akun `{prefiks}:{nama_akun}[:{subjek}:{id}]:{ASET}`, contoh: `escrow:payment:IDR`, `ap:ret:seller:{party_id}:IDR`, `ret:commission_revenue:IDR`, `ret:cashback_liability:IDR`, `ret:deposit_liability:IDR`.
  - **A2.3 (baris 126)**: Chart of Accounts sebagai kode di `Modules/Ret/Ledger/RetAccounts.php` dan seeder idempoten.
  - **A2.4 (baris 128)**: Tipe transaksi terdaftar di `RetTransactionTypes.php` (≤ 32 karakter).
  - **A2.5 (baris 130)**: Format idempotency key deterministik `{prefiks}:{aksi}:{id_bisnis}[:{sub}]` tanpa `Str::uuid()`, `now()`, atau `microtime()`.
  - **A2.6 (baris 132–138)**: Uang IDR dalam integer rupiah (minor unit), komisi dalam integer basis poin (`bps`), pembulatan HalfUp, alokasi proporsional Hamilton.
- **§A3 (baris 151–158)**: State machine pesanan (`PENDING`, `RESERVED`, `PAID`, `FULFILLED`, `CANCELLED`, `REFUNDED`), nomor pesanan gapless deterministik.
- **§A4 (baris 159–188)**: Event domain (`ret.order.paid.v1`, `ret.shipment.delivered.v1`, `ret.cashback.issued.v1`), konsumsi event spine, isolasi modul via kontrak antarmuka (`MapPriceLookupInterface`).
- **§A5 (baris 189–196)**: Audit dua sumber `ret:audit` membandingkan fakta operasional dengan pembukuan ledger:
  - Utang seller = $\sum (\text{order settled} - \text{komisi} - \text{refund})$;
  - Liabilitas cashback = $\sum (\text{cashback terbit} - \text{terpakai} - \text{kedaluwarsa})$;
  - Liabilitas deposit kemasan = $\sum (\text{deposit dipungut} - \text{deposit direfund})$.
- **§A6 (baris 197–204)**: RBAC modul: peran `marketplace_admin`, `store_manager`, `warehouse_staff`, `customer`. Anti-IDOR pada profil dan pesanan pelanggan.
- **§A7 (baris 205–208)**: UI blade mobile-responsive 375px untuk kasir/staf gudang dan checkout pelanggan.
- **§A8 (baris 209–220)**: Matriks acceptance test (a) happy path, (b) auth/validasi 403/422, (c) idempotensi key deterministik, (d) invarian ledger/stok, (e) edge case/konkurensi.
- **§A10 (baris 232–241)**: Determinisme waktu via `ClockInterface`, simulasi deterministik 60 hari dengan hash run 1 == run 2.
- **§A13 (baris 266–323)**: Kepatuhan larangan X1–X25 (tanpa float uang, tanpa idempotency acak, tanpa bypass stok).
- **§A15 (baris 324–474)**: Template implementasi normatif, posting DTO, audit chunkById.
- **GELOMBANG 2 Lini 17 (baris 1332–1337)**: Spesifikasi Minimum MVP:
  - Larangan jalan pintas: stok terpisah dari Inventory (anti double-sell); cashback tanpa liabilitas ledger; settlement seller tanpa escrow; harga tidak selaras MAP.

### 1.2 docs/PROGRESS.md
- **P2 (baris 21–64)**: Protokol vertical slice V1–V12.
- **P3 (baris 65–124)**: Larangan X1–X25 (X3 integer minor, X4 idempotency key deterministik, X16 larangan domain import lintas modul).
- **P8 (baris 240–280)**: Format DoR & persetujuan desain arsitektur.
- **P9 (baris 281–310)**: Register Minus arsitektur & kompensasi kontrol.
- **Fase 137 (baris 10120–10131)**: Omnichannel Marketplace Group (Unified Inventory OMS, 3P Seller, MAP Pricing, Split Fulfillment).
- **Fase 138 (baris 10133–10144)**: Super App, Wallet Cross-Lini & Cashback Economy (Liabilitas Cashback, Anti-Abuse, Expiry Breakage).
- **Fase 139 (baris 10146–10157)**: Fulfillment, Quick Commerce & Last-Mile Grid (Dark Store Pick-Pack, SLA Promise, Kemasan Deposit).

---

## 2. Kriteria Penerimaan DoR (Checklist Kesiapan)

1. **Modul & Prefiks**: Pemilik tunggal `Ret` dengan prefiks tabel `ret_`.
2. **Integrasi Stok Tanpa Double-Sell**: Ret memanggil `InventoryService` untuk reservasi (`reserve`) dan komit (`commit`), bukan memelihara tabel stok independen. Tabel `ret_inventory_items` ditandai deprecated dan diselaraskan.
3. **Integritas Uang & Ledger**:
   - Seluruh mutasi moneter menggunakan integer rupiah minor unit.
   - Settlement seller melewati escrow pembayaran (`escrow:payment:IDR`).
   - Cashback dicatat sebagai kewajiban penuh (`ret:cashback_liability:IDR`).
   - Deposit kemasan dicatat sebagai kewajiban (`ret:deposit_liability:IDR`).
4. **Idempotensi**: Seluruh aksi checkout, settlement, dan mutasi saldo menerima `Idempotency-Key` deterministik.
5. **Algoritma Bersama B0″**:
   - Alokasi pemenuhan split gudang/toko menggunakan `FulfillmentPlanner` (B0″.9) dan `FulfillmentPlannerVerifier`.
   - Pembagian proporsional komisi dan split menggunakan pembagian sisa terbesar (Hamilton).
6. **Simulasi**: Pengujian deterministik 60 hari dengan seed tetap, menghasilkan state hash yang identik pada run ganda.
