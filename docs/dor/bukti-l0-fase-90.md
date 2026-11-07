# Bukti Kondisi Awal (L.0) — Fase 90: Artist Contracts, Supply Bar, Night Economics, Membership & Festival Economy

**Domain:** Venue & Entertainment (`modules/Venue/`, prefiks DB `ven_`)  
**Basis Git:** master  
**Waktu Eksekusi:** 2026-11-07  

---

## 1. Tabel Analisis Komponen: "Ada / Benar / Salah / Tidak Ada"

| Komponen | Status | Penjelasan & Rencana Tindakan |
|---|---|---|
| **Tabel `ven_artist_contracts`** | Ada / Benar sebagian | Tabel kontrak artis ada dari migrasi legacy 890100, namun field advance/guarantee/door share V5, multi-currency, withholding tax PPh 26/21, dan relasi modul Contract belum lengkap. Perlu diperluas migrasi aditif. |
| **State Machine `ArtistEngagementStateMachine`** | Tidak ada | Siklus hidup kontrak artis 7 status (`offered`, `contracted`, `advance_paid`, `performed`, `settled`, `disputed`, `cancelled_force_majeure`) belum diimplementasikan. Wajib dibuat baru. |
| **Algoritma V5 `ArtistDoorSplitCalculator` / `ArtistVersusDealSettlement`** | Tidak ada | Perhitungan `max(guarantee, share% × (door − cost)) − advance` dengan pemotongan withholding tax PPh 26 (20%) / P3B dan konversi valas (USD/SGD ke sen) belum ada. Wajib dibuat bersertifikat. |
| **Algoritma V6 `BundleSspAllocator` (`Modules\Shared\Domain\Algorithms\Settlement`)** | Tidak ada | Alokasi harga bundle festival ke vendor menurut Standalone Selling Price (Largest Remainder, tie-break indeks) dan deduksi platform fee bps belum ada. Wajib dibuat bersertifikat di modul Shared. |
| **Algoritma V7 `MembershipTierEngine`** | Tidak ada | Evaluasi tier bergulir 12 bulan (Infinity: belanja ≥ 75jt & visit ≥ 24; Moon: belanja ≥ 20jt atau visit ≥ 12; Sun: lainnya), anniversary downgrade grace, dan kalkulasi poin PTS via Mall LoyaltyLedger belum ada. Wajib dibuat bersertifikat. |
| **Algoritma V8 `SponsorExposureValuator`** | Tidak ada | Perhitungan weighted impressions (Σ footfall × dwell weight), ekivalensi CPM, dan termin milestone ber-KPI (≥10.000 bps penuh, 7.000–9.999 bps pro-rata, <7.000 bps 0) belum ada. Wajib dibuat bersertifikat. |
| **Kontrak Aditif G7-K16 `RoomBlockReserver` (`Modules\Hotel\Contracts\RoomBlockReserver`)** | Tidak ada | Kontrak aditif di modul Hotel membungkus `ReserveRoomWithInventoryNightLockAction` untuk glamping/kamar festival belum dibuat. Wajib dibuat di Hotel beserta implementasinya. |
| **Kontrak Aditif G7-K17 `OutletSaleRecorder` (`Modules\Resto\Contracts\OutletSaleRecorder`)** | Ada / Benar | Sudah dibuat di Fase 89, siap dipakai untuk supply resto/bar venue dan pelaporan konsumsi botol. |
| **Kontrak Aditif G7-K18 `ShiftBountyPublisher` (`Modules\Hcm\Contracts\ShiftBountyPublisher`)** | Tidak ada | Kontrak aditif di modul Hcm membungkus `InternalGigEconomyService::postBounty` untuk shift kru dadakan event belum ada. Wajib dibuat di Hcm. |
| **Kontrak Aditif G7-K19 `ImportLandedCostQuoter` (`Modules\Trade\Contracts\ImportLandedCostQuoter`)** | Tidak ada | Kontrak aditif di modul Trade membungkus `CalculateIncotermsLandedCostAction` + tarif cukai MMEA berversi (PMK 160/2023) belum ada. Wajib dibuat di Trade beserta migrasi tabel cukai. |
| **Tabel `ven_membership_profiles` & SM `MembershipStateMachine`** | Tidak ada | Profil keanggotaan beach club (Sun, Moon, Infinity), histori belanja 12 bulan, anniversary date, dan 7 status SM (`applied`, `kyc_pending`, `active`, `grace`, `downgraded`, `suspended`, `cancelled`) belum ada. Wajib dibuat. |
| **Tabel `ven_festival_bundles` & SM `FestivalBundleStateMachine`** | Tidak ada | Pembuat bundle multi-day festival, alokasi per komponen vendor, dan 8 status SM (`quoted`, `reserved`, `paid`, `fulfilled_partial`, `fulfilled`, `settled`, `refunded_partial`, `cancelled`) belum ada. Wajib dibuat. |
| **Saga `FestivalBundleEscrowSaga`** | Tidak ada | Saga kompensasi festival bundle: jika kamar hotel gagal → shuttle dibatalkan dan refund pro-rata komponen; jika event dibatalkan → memicu refund penuh. Wajib dibuat. |
| **Tabel `ven_sponsor_deals` & SM `SponsorDealStateMachine`** | Tidak ada | Paket sponsorship (naming rights zona, booth, aktivasi), termin milestone KPI, dan 7 status SM (`proposed`, `signed`, `activated`, `milestone_due`, `invoiced`, `paid`, `closed`) belum ada. Wajib dibuat. |
| **Tabel `ven_bar_spirits_excise_landed_costs` & Stok WMS Mini-Warehouse** | Tidak ada | Pencatatan landed cost spirit impor botol per batch, tarif bea masuk, cukai MMEA, PPN impor, dan integritas cold-chain belum ada. Wajib dibuat. |
| **Tabel `ven_night_pos_hourly_metrics` & Papan RevPAT** | Tidak ada | Agregasi POS per jam (peak 23.00–03.00), mix kategori minuman, revenue per available table (RevPAT), dan pour variance belum ada. Wajib dibuat. |
| **Dashboard P&L Event & Pelaporan Finansial** | Tidak ada | P&L terintegrasi ledger (tiket + bar + VIP + sponsor vs artis + operasi), statement artis (PDF-ready HTML), portal member, preview bundle festival, dan ekspor CSV belum ada. Wajib dibuat. |
| **Command Audit `venue:audit-economy`** | Tidak ada | Verifikasi matematis: payout artis = V5, Σ alokasi bundle = harga (V6), Σ poin terbit = LoyaltyLedger, termin sponsor = KPI, dan zero-sum ledger belum ada. Wajib dibuat. |
| **Commands Operasional: `venue:settle-artist`, `venue:membership-tier-review`, `venue:sponsor-milestone-check`, `venue:settle-bundles`** | Tidak ada | Perintah terjadwal operasional ekonomi venue belum ada. Wajib dibuat. |
| **Acceptance Tests (`tests/Acceptance/VenueEconomy`)** | Tidak ada | Suite acceptance test terpadu ≤ 5 test untuk Fase 90 belum ada. Wajib dibuat di L.2. |

---

## 2. Pemeriksaan Lingkungan & Ruang Kerja

- Worktree: `sw-g7-p5`
- Branch kerja: `feature/g7-p5-venue`
- Database: SQLite in-memory / local `database.sqlite`
- Status CI PR Fase 89 (#86): Dalam pemantauan CI
