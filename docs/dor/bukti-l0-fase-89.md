# Bukti Kondisi Lama (L.0) — Fase 89: Ticketing, Access Control, Usia & Venue Operations

**Domain:** Venue & Entertainment (`modules/Venue/`, prefiks DB `ven_`)  
**Basis Git:** master (`d40d6571`)  
**Waktu Eksekusi:** 2026-11-07  

---

## 1. Tabel Analisis Komponen Lama: "Ada / Benar / Salah / Tidak Ada"

| Komponen | Status | Penjelasan & Rencana Tindakan |
|---|---|---|
| **Tabel `ven_entertainment_venues`** | Ada / Benar sebagian | Tabel ada dengan kolom master venue, namun belum memiliki relasi izin keramaian gapless dan koordinat zona. Dipertahankan dan ditambah relasi/kolom bila perlu. |
| **Tabel `ven_entertainment_zones`** | Ada / Benar | Tabel zona (Pool Deck, Dance Floor, VIP, Beachfront) dengan `capacity_limit` dan `current_occupancy`. Perlu dipastikan dihitung dari fakta pindaian. |
| **Tabel `ven_entertainment_events`** | Ada / Benar sebagian | Tabel event ada dengan batasan harga, namun status masih sederhana (OPEN), belum memenuhi State Machine 8 status: `draft`, `permit_pending`, `on_sale`, `sold_out`, `live`, `closed`, `settled`, `cancelled`. |
| **Tabel `ven_entertainment_tickets`** | Ada / Benar sebagian | Kolom hash dan nomor tiket ada, namun belum menerapkan hash-chain `CanonicalHashChain` gapless per event dan belum memiliki 8 status SM (`reserved`, `issued`, `transferred`, `scanned_in`, `scanned_out`, `used`, `refunded`, `void`). |
| **Tabel `ven_entertainment_table_bookings`** | Ada / Benar sebagian | Tabel reservasi meja ada dengan status HELD/SEATED/COMPLETED/NO_SHOW, namun belum memiliki 7 status SM `TableBooking` dan belum terintegrasi dengan saga `PaymentIntent` dan `OutletSaleRecorder` (G7-K17). |
| **Tabel `ven_permits`** | Tidak ada | Izin keramaian bernomor gapless (DocumentNumbering) dan kapasitas legal belum memiliki tabel khusus. Wajib dibuat migrasi baru. |
| **Tabel `ven_sagas`** | Tidak ada | Saga kompensasi terdistribusi (deposit meja, pembatalan event) belum memiliki tabel pencatatan langkah saga. Wajib dibuat migrasi baru. |
| **Tabel `ven_external_factors`** | Tidak ada | Penyimpan parameter cuaca, izin keramaian, tarif PBJT, dan faktor eksternal berversi belum ada. Wajib dibuat migrasi baru. |
| **Tabel `ven_ticket_tiers` & `ven_inventory_counters`** | Ada / Benar | Dibuat pada migrasi MVP 120100, siap dipakai untuk alokasi tier tiket. |
| **Tabel `ven_gate_scans`** | Ada / Benar sebagian | Tabel riwayat pindaian gerbang ada, namun belum menyimpan nonce pindaian dan arah IN/OUT secara append-only untuk anti-passback. |
| **Service `VenueOperationsService`** | Ada / Salah | Mengakui pendapatan tiket langsung ke revenue (`ven:ticket_revenue:IDR`) saat penjualan (melanggar G7-D33 unearned revenue); mengambil umur dari argumen mentah bukan KYC; nomor tiket acak `bin2hex` (bukan gapless). Pensiun/refactor ke Actions terpisah. |
| **Action `PurchaseVenueTicketAction`** | Ada / Benar sebagian | Action MVP ada, perlu diperluas agar mematuhi G7-D33 (unearned + PBJT payable), DocumentNumbering gapless, dan CanonicalHashChain. |
| **Action `ScanTicketGateWithAntiPassbackAndAgeCheckAction`** | Ada / Benar sebagian | Ada tapi belum memakai algoritma bersertifikat `AntiPassbackGate` (V2) dan verifikasi umur `PartyAgeVerificationInterface`. |
| **Action `CloseEntertainmentEventAction`** | Ada / Benar sebagian | Ada tapi belum membalik unearned revenue ke revenue final secara akrual penuh dan idempoten. |
| **Algoritma V1 `TierCountdownPricer`** | Tidak ada | Algoritma dynamic pricing countdown tier + cuaca + permintaan belum ada. Wajib diimplementasikan bersertifikat. |
| **Algoritma V2 `AntiPassbackGate`** | Tidak ada | Algoritma pencegah passback masuk/keluar bergantian dengan batas re-entry belum ada. Wajib diimplementasikan bersertifikat di Shared. |
| **Algoritma V3 `ZoneCapacityGovernor`** | Tidak ada | Algoritma admisi zona dengan token bucket laju masuk dan safety margin belum ada. Wajib diimplementasikan bersertifikat. |
| **Algoritma V4 `MinimumSpendSettlement`** | Tidak ada | Algoritma perhitungan shortfall dan no-show forfeiture meja VIP belum ada. Wajib diimplementasikan bersertifikat. |
| **Command `ven:audit` (`AuditVenueCommand`)** | Ada / Benar sebagian | Command ada, namun pengecekan audit dua sumber belum lengkap (unearned vs issued, hash chain, nomor gapless, okupansi fakta vs kapasitas). Perlu diperluas. |
| **Command `venue:close-event`** | Ada / Benar sebagian | Sudah ada `CloseEventsCommand`, perlu diselaraskan dengan parameter `{event}` idempoten. |
| **Command `venue:permit-expiry-scan` & `venue:weather-check`** | Tidak ada | Command terjadwal kepatuhan izin dan pemantauan cuaca belum ada. Wajib dibuat. |
| **Rute & UI Lapangan (375 px)** | Tidak ada | Halaman gerbang tiket 375 px, peta zona SVG, kalender izin, dan dashboard operasional belum ada. Wajib dibuat. |
| **Acceptance Tests (`tests/Acceptance/VenueOps`)** | Tidak ada | Suite acceptance test terpadu ≤ 5 test belum ada. Wajib dibuat di L.2. |

---

## 2. Output Asli Perintah Pemeriksaan

### a. Status Git Awal
```
$ git status
On branch feature/g7-p5-venue
Your branch is up to date with 'origin/master'.
nothing to commit, working tree clean
```

### b. Pengujian Modul Venue Lama
```
$ vendor/bin/pest modules/Venue/tests/Feature/BeachClubAndVenueOperationsTest.php
   PASS  Modules\Venue\tests\Feature\BeachClubAndVenueOperationsTest
  ✓ 89 3 ticket purchase and anti replay gate scan                       6.52s  
  ✓ 89 4 zone at max capacity blocks further entry                       0.13s  
  ✓ 89 5 vip table escrow deposit and no show forfeiture                 0.12s  

  Tests:    3 passed (14 assertions)
  Duration: 6.87s
```

### c. Pemeriksaan Audit Command & Fixture
```
$ php artisan ven:audit
Starting Venue Operations & Crowd Safety Audit (ven:audit)...
+---------------------+-------+
| Metric              | Value |
+---------------------+-------+
| Status              | OK    |
| Total Events        | 0     |
| Total Tickets       | 0     |
| Table Bookings      | 0     |
| Entertainment Zones | 0     |
| Discrepancies       | 0     |
+---------------------+-------+
Venue audit PASSED with 0 discrepancy.
```

Kondisi awal telah terpetakan secara lengkap. Siap melanjutkan ke L.1 (DoR & Desain Arsitektur).
