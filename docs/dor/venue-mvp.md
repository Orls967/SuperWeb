# Definition of Ready (DoR) — Lini 1: Venue Entertainment (MVP Vertical Slice)

#### DoR Lini 1 — Venue Entertainment MVP
- **Disetujui pemilik:** Internal Gate L1 (Pelaksana Pengembangan Vertical Slice)
- **Tujuan bisnis (1–2 kalimat):** Menyediakan operasional komprehensif venue entertainment (Beach Club, Festival, Concert Hall) yang mencakup penjualan tiket kuota GA dan kursi bernomor dengan hold 10 menit anti-double-sell, alokasi kursi otomatis BestAvailableSeatFinder anti-orphan, QR tiket HMAC anti-pemalsuan dan pemindaian gate anti-passback dengan verifikasi usia KYC Party, reservasi meja VIP minimum spend deposit, settlement artis berbasis door share dan PPh 23, penutupan event dengan pengakuan pendapatan unearned, serta rekonsiliasi audit dua-sumber.
- **Modul pemilik & prefiks (KONSEP §A1 baris 65, §10F baris 1079):**
  - Modul: `Venue`
  - Prefiks tabel: `ven_` (terdaftar di `config/modules.php` dan `KONSEP.md` §A1). Tabel Vending dipisahkan ke `vnd_`.
- **Prasyarat & statusnya (harus ✅):**
  - Bagian 0: Pustaka Algoritma Bersama (`CanonicalHashChain`, `LargestRemainder`, `RobustStatistics`, `PointInPolygon`, `HungarianSolver`, `DinicMaxFlow`, `RationalSimplex`, `IntegerBlendRounder`) — Status: ✅ (Lolos G-B0, 66 passed, mutasi >= 75%).
  - Core Modules: `Banking` (Ledger), `Payment`, `Party` (Age Verification), `Core` (ClockInterface).
- **Item yang dikerjakan sesi ini & yang ditunda (→ Register Minus):**
  - Dikerjakan:
    1. Seeder role Venue (`venue_manager`, `venue_staff`, `artist_relations`) via RbacService idempoten (U-5, Z8).
    2. Master data venue, zone, tier, seat layout, dan factory testing.
    3. Bagan akun `VenueAccounts` (`ven:ticket_unearned:IDR`, `ven:vip_escrow:IDR`, dll.) dengan saldo normal dan allow_negative sesuai aturan (U-3).
    4. Skema kuota & kursi: `ven_inventory_counters` (kuota GA per zona) dan `ven_seat_claims` (kursi bernomor) dengan DB unique constraints anti-double-sell.
    5. Hold kuota/kursi 10 menit dengan pelepasan otomatis berbasis `ClockInterface` (V-2).
    6. `BestAvailableSeatFinder` sliding window deterministik anti-orphan.
    7. Penerbitan tiket gapless via `DocumentNumberingInterface` + `CanonicalHashChain` (PR5, B0.1) + HMAC-SHA256 signature QR `{uuid}.{key_id}.{hmac}` (V-4).
    8. Pembelian tiket terintegrasi PaymentGateway charge + posting ledger unearned revenue (V-7).
    9. Pembatalan / refund tiket sebelum event closed dengan pembalikan ledger dan pelepasan kuota.
    10. Scan gerbang online-only anti-passback + verifikasi usia minimum KYC Party (V-5, V-6, Z9) + audit log append-only `ven_gate_scans`.
    11. Reservasi meja VIP minimum spend deposit (hold, capture, release, no-show forfeiture fee).
    12. Settlement artis door share bps integer + pemotongan PPh 23 (200 bps) + posting ledger (U-1).
    13. Penutupan event (`ven:close-events`) mengakui pendapatan dari unearned ke revenue, fee platform, dan kewajiban promotor.
    14. Penguatan command `ven:audit` dengan pemeriksaan dua-sumber (tiket terbit vs scan+refund+sisa, deposit meja, dan saldo ledger unearned vs subledger).
    15. Tampilan scanner turnstile gerbang responsif 375 px pada rute `/venue/gate`.
  - Ditunda ke Register Minus:
    - Pemindaian offline turnstile dengan sync batch cryptographic roll.
    - Pasar sekunder capped resale dengan dynamic royalty waterfall lanjutan.
- **Entitas/tabel + state machine:**
  - `ven_entertainment_venues` (master venue)
  - `ven_entertainment_zones` (master zona dengan kapasitas dan batas keselamatan)
  - `ven_entertainment_events` (master event: OPEN, IN_PROGRESS, CLOSED, CANCELLED)
  - `ven_ticket_tiers` (tier tiket: GA / VIP / SEATED, floor & ceiling price)
  - `ven_inventory_counters` (id, event_id, tier_id, total_quota, held_count, sold_count)
  - `ven_seat_claims` (id, event_id, section, row, seat_number, status: AVAILABLE, HELD, BOOKED, held_until, hold_token, buyer_user_id)
  - `ven_entertainment_tickets` (id, ticket_uuid, ticket_number, event_id, buyer_user_id, price_paid_idr, ticket_hash, prev_hash, status: VALID, USED, REFUNDED)
  - `ven_gate_scans` (id, ticket_id, gate_code, scanned_at, direction: IN, OUT, scan_result: ALLOWED, DENIED, denial_reason)
  - `ven_entertainment_table_bookings` (id, booking_code, event_id, table_number, user_id, minimum_spend_idr, deposit_amount_idr, status: HELD, CONFIRMED, SEATED, COMPLETED, NO_SHOW)
  - `ven_artist_contracts` (id, venue_id, event_id, contract_number, artist_name, advance_amount_idr, door_share_bps, status: ACTIVE, SETTLED)
- **Posting ledger (kejadian → debit/kredit, akun di VenueAccounts):**
  - Penjualan tiket GA/Kursi:
    - Debit: `clearing:payment:IDR` (atau wallet user)
    - Kredit: `ven:ticket_unearned:IDR`
  - Penutupan Event (`ven:close-events`):
    - Debit: `ven:ticket_unearned:IDR`
    - Kredit: `ven:ticket_revenue:IDR` (pendapatan bersih event)
    - Kredit: `ven:platform_fee_revenue:IDR` (komisi platform)
    - Kredit: `ven:tax_ticket_payable:IDR` (pajak hiburan / PPN)
    - Kredit: `ven:organizer_payable:IDR` (kewajiban promotor)
  - Refund Tiket (sebelum closed):
    - Debit: `ven:ticket_unearned:IDR`
    - Kredit: `clearing:payment:IDR`
  - Deposit Meja VIP:
    - Hold deposit: `hold` di PaymentGateway / `ven:vip_escrow:IDR`
    - Capture check-in: `ven:vip_escrow:IDR` → `ven:table_revenue:IDR`
    - No-show penalty: `ven:vip_escrow:IDR` → `ven:vip_noshow_rev:IDR`
  - Settlement Artis:
    - Debit: `expense:venue_artist_fee:IDR` (gross door share)
    - Kredit: `tax:wht23_payable:IDR` (PPh 23 200 bps)
    - Kredit: `artist:payable:default:IDR` (net payout setelah advance)
- **Event (produce/consume; katalog KONSEP §A4.2):**
  - Produce: `ven.ticket.issued.v1`, `ven.ticket.refunded.v1`, `ven.gate.admitted.v1`, `ven.table.reserved.v1`, `ven.event.closed.v1`, `ven.artist.settled.v1`
  - Consume: `party.identity.verified.v1`, `payment.captured.v1`
- **Rute/UI/menu + role & policy:**
  - Rute:
    - `/venue` (`venue_manager`, `venue_staff`, `admin`)
    - `/venue/events` (`venue_manager`)
    - `/venue/gate` (`venue_staff` — UI turnstile scanner optimal 375 px)
    - `/venue/tables` (`venue_manager`, `venue_staff`)
    - `/venue/artists` (`artist_relations`)
  - Middleware: `['web', 'auth', 'role:...']`
- **Command/jadwal/tick handler:**
  - `ven:close-events` (penutupan event dan pengakuan pendapatan)
  - `ven:release-expired-holds` (pelepasan hold kedaluwarsa berbasis ClockInterface)
  - `ven:audit` (audit dua-sumber operasional dan integritas data)
- **Audit dua-sumber + fixture korupsi (KONSEP §A15.6 baris 469, §10F baris 1095):**
  - Sumber A: `ven_entertainment_tickets` agregat status (VALID, USED, REFUNDED)
  - Sumber B: Rekam transaksi ledger pada `ven:ticket_unearned:IDR` + log `ven_gate_scans`
  - Invarian: Σ Tiket Terbit = Σ Tiket Aktif (Belum Scan) + Σ Tiket Terpakai (Scan Gate) + Σ Tiket Direfund.
  - Invarian Finansial: Saldo akun `ven:ticket_unearned:IDR` tepat sama dengan total nominal tiket status VALID pada event yang belum CLOSED.
  - Fixture korupsi: `tests/Acceptance/Venue/Corruptions/VenueAuditCorruptionFixture.php`
- **Nama test (a)–(e) yang akan ditulis (KONSEP §A8 baris 216):**
  - (a) Test alur happy path: pembelian tiket GA & kursi, scan gate, reservasi meja VIP, settlement artis, close event.
  - (b) Test boundary & error: kuota habis, kursi sudah terambil, hold kedaluwarsa 10 menit, batas beli per akun 6 tiket (anti-calo), usia < minimum ditolak.
  - (c) Test idempotensi: retry pembelian dengan Idempotency-Key sama mengembalikan response identik tanpa double selling atau duplicate ledger entry.
  - (d) Test invarian ledger: double-entry balance Σ=0 pada seluruh transaksi, saldo normal ditaati, zero leakage.
  - (e) Test keamanan & otorisasi: gate turnstile 403 bila diakses tanpa role `venue_staff`, tampering QR HMAC ditolak, anti-passback (scan 2x ditolak).
- **Tingkat simulasi (S1/S2/S3) & tier skala (T0/T1/T2):**
  - Tingkat simulasi: S2 (State-machine deterministik + event outbox nyata + mock gateway deterministik)
  - Tier skala: T1 (3 venue, 12 event, 3.000 transaksi tiket, 40 meja VIP, 100% deterministik)
- **Jalan pintas yang harus dihindari (dari KONSEP §10F baris 1099 & baris "Kriteria terima wajib"):**
  - Dilarang membuat tiket tanpa nomor gapless / tanpa hash-chain.
  - Dilarang menerima pemindaian ulang tiket yang sudah USED (anti-passback).
  - Dilarang mengakui pendapatan tiket pada saat penjualan awal (harus mengendap di unearned sampai event closed).
  - Dilarang menggunakan float untuk pembagian door share artis atau tarif pajak.
  - Dilarang menggunakan idempotency key berbasis random string atau timestamp `now()`.
  - Dilarang mencampur prefiks tabel dengan modul lain.

