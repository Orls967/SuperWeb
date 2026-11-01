# Definition of Ready (DoR): Lini 15 — Media & Kreatif (Med MVP)

## 1. Acuan & Dasar Regulasi / Bisnis
- **KONSEP.md**:
  - Baris 263: Urutan dependensi eksekusi Lini 15 Media & Kreatif (`med_`).
  - Baris 1318–1324: Spesifikasi Minimum (MVP) Lini 15 Media & Kreatif (`med_`):
    - Slice: studio & booking (hold/capture, anti-overlap) ➔ registri IP (`med_ip_assets`) ➔ lisensi (Contract) ➔ pendapatan lisensi ➔ *royalty statement* dan waterfall settlement multi-pihak ber-key (baris terakhir menyerap pembulatan).
    - Ledger: DR `ar:med:licensee:{party}:IDR` / CR `med:license_revenue:IDR`; DR `expense:med:royalty:IDR` / CR `ap:med:rightsholder:{party}:IDR`.
    - Rute: `/media` (`studio_manager`, `rights_manager`; pemegang hak melihat statement-nya sendiri; auditor baca).
    - Audit: `med:audit`: Σ royalti = Σ (pendapatan lisensi − fee − recoupment) × porsi; tidak ada lisensi eksklusif tumpang tindih; Σ porsi = 10.000 bps pada setiap tanggal; tidak ada slot studio ganda; laporan kampanye = Σ impresi terverifikasi.
    - Jalan pintas terlarang: royalti tanpa kontrak lisensi; settlement multi-pihak yang pembulatannya tidak diserap baris terakhir; booking studio tanpa kunci slot; lisensi eksklusif tumpang tindih.
  - Baris 1374–1375: Roadmap Fase 133–134 (Fase 133: Media studio & IP economy; Fase 134: Distribution, advertising & sponsorship platform).
- **PROGRESS.md**:
  - Baris 10069–10079: Fase 133 (Studio facility, production lifecycle, talent contract dengan backend %, IP registry & monetization, studio utilization rate, anti-overlap tests).
  - Baris 10081–10090: Fase 134 (Distribution platform, programmatic ad inventory yield management, campaign measurement, sponsorship cross-lini).
- **Prinsip Rekayasa (PR1–PR5)**:
  - PR1: Kebenaran, kesederhanaan, kinerja.
  - PR2: Setiap algoritma optimasi/alokasi (IntervalIndex, Waterfall) disertai sertifikat yang diverifikasi kelas terpisah.
  - PR3: Determinisme simulasi dengan explicit seed (state hash identik 2×).

---

## 2. Entitas & State Machine

### 2.1 MedStudio (`med_studios`)
- **Atribut**: `id` (UUID), `studio_code` (unik), `name` (string), `facility_type` (`SOUND_STAGE`, `PODCAST_ROOM`, `VIRTUAL_PRODUCTION`, `EDITING_SUITE`), `location_city` (string), `hourly_rate_minor` (bigint), `full_day_rate_minor` (bigint), `status` (`AVAILABLE`, `MAINTENANCE`).

### 2.2 MedStudioSlotClaim (`med_studio_slot_claims`)
- **Atribut**: `id` (UUID), `studio_id` (FK `med_studios`), `slot_start` (datetime UTC), `slot_end` (datetime UTC, 30 menit), `booking_id` (FK `med_studio_bookings`).
- **Invarian**:
  - Unique constraint: `(studio_id, slot_start)`.
  - Anti double-booking ditegakkan di level database constraint dan `lockForUpdate`.

### 2.3 MedStudioBooking (`med_studio_bookings`)
- **Atribut**: `id` (UUID), `booking_code` (unik), `studio_id` (FK), `client_entity_id` (string), `start_time` (datetime UTC), `end_time` (datetime UTC), `hold_expires_at` (datetime UTC nullable), `hold_amount_minor` (bigint), `total_price_minor` (bigint), `cancellation_fee_minor` (bigint), `status` (`HELD`, `CONFIRMED`, `CHECKED_IN`, `CANCELLED`, `NO_SHOW`).
- **State Machine**:
  `HELD` ➔ `CONFIRMED` ➔ `CHECKED_IN`
  `HELD` ➔ `CANCELLED`
  `CONFIRMED` ➔ `CANCELLED` (sebelum cut-off) | `NO_SHOW` (setelah cut-off, fee dikenakan).

### 2.4 MedIpAsset (`med_ip_assets`)
- **Atribut**: `id` (UUID), `ip_code` (unik), `title` (string), `ip_type` (`TRADEMARK`, `SONG`, `SHOW_FORMAT`, `CHARACTER`), `owner_entity_id` (string), `status` (`ACTIVE`, `RETIRED`).

### 2.5 MedIpRightsShare (`med_ip_rights_shares`) — Append-Only
- **Atribut**: `id` (UUID), `ip_id` (FK `med_ip_assets`), `rights_type` (`MASTER`, `PUBLISHING`, `SYNC`, `MERCHANDISE`), `rightsholder_party_id` (string), `share_bps` (int, 1..10000), `effective_date` (date YYYY-MM-DD), `created_at` (timestamp).
- **Invarian**:
  - Pada tanggal efektif mana pun, `Σ share_bps` untuk suatu `(ip_id, rights_type)` WAJIB tepat 10.000 bps (100.00%).
  - Perubahan pembagian hak adalah append-only dengan tanggal berlaku baru.

### 2.6 MedIpLicense (`med_ip_licenses`)
- **Atribut**: `id` (UUID), `license_code` (unik), `contract_number` (string nullable), `ip_id` (FK), `licensee_entity_id` (string), `rights_type` (string), `channel` (string), `territory` (string ISO), `start_date` (date), `end_date` (date), `is_exclusive` (bool), `minimum_guarantee_minor` (bigint), `royalty_rate_pct` (float), `advance_payment_minor` (bigint), `status` (`ACTIVE`, `EXPIRED`, `TERMINATED`).
- **Invarian Tumpang Tindih (`IntervalIndex`)**:
  - Lisensi eksklusif tidak boleh tumpang tindih dengan lisensi apa pun pada `(ip_id, rights_type, territory)` yang sama dalam rentang `[start_date, end_date)`.
  - Lisensi non-eksklusif tidak boleh tumpang tindih dengan lisensi eksklusif.

### 2.7 MedLicenseRevenue (`med_license_revenues`)
- **Atribut**: `id` (UUID), `license_id` (FK), `billing_period` (YYYY-MM), `gross_revenue_minor` (bigint), `distribution_fee_bps` (int), `distribution_fee_minor` (bigint), `recouped_advance_minor` (bigint), `net_distributable_minor` (bigint), `status` (`RECORDED`, `SETTLED`).

### 2.8 MedRoyaltyStatement & MedRoyaltyStatementLine
- **Atribut Statement**: `id` (UUID), `statement_number` (unik), `license_id` (FK), `billing_period` (YYYY-MM), `gross_revenue_minor` (bigint), `total_distribution_fee_minor` (bigint), `total_recouped_minor` (bigint), `total_royalty_distributed_minor` (bigint), `status` (`ISSUED`, `PAID`).
- **Atribut Line**: `id` (UUID), `statement_id` (FK), `rightsholder_party_id` (string), `rights_type` (string), `share_bps` (int), `gross_share_minor` (bigint), `recoupment_deducted_minor` (bigint), `net_payable_minor` (bigint).
- **Invarian Waterfall (B0″.7)**:
  - Pembulatan integer: baris penerima terakhir WAJIB menyerap sisa rupiah (penny absorption) sehingga `Σ net_payable_minor` sama persis dengan `net_distributable_minor`.

### 2.9 MedIpAdvanceBalance (`med_ip_advance_balances`)
- **Atribut**: `id` (UUID), `license_id` (FK), `rightsholder_party_id` (string), `initial_advance_minor` (bigint), `recouped_amount_minor` (bigint), `remaining_balance_minor` (bigint).

### 2.10 MedAdPlacement & MedAdCampaign
- **Placement**: `id`, `channel_id`, `placement_code` (unik), `floor_cpm_minor` (bigint), `is_active` (bool).
- **Campaign**: `id`, `campaign_code` (unik), `advertiser_entity_id`, `placement_id`, `actual_cpm_minor` (wajib `>= floor_cpm_minor`), `target_impressions`, `verified_impressions`, `status` (`SCHEDULED`, `RUNNING`, `COMPLETED`).

### 2.11 MedAdImpression (`med_ad_impressions`) — Append-Only
- **Atribut**: `id` (UUID), `campaign_id` (FK), `impression_hash` (string unik), `device_ip_hash` (string), `timestamp_utc` (datetime), `is_verified` (bool), `is_fraud` (bool), `fraud_reason` (string nullable).
- **Invarian**: Deduplikasi ketat (impresi duplikat ditolak) dan batas frekuensi (fraud velocity > 10 impresi/detik dari sumber sama ditolak).

---

## 3. Tabel Posting & Perhitungan Keuangan

### 3.1 Pendapatan Lisensi
Contoh: Lisensi streaming Rp 100.000.000:
- DR `ar:med:licensee:{party}:IDR` Rp 100.000.000
- CR `med:license_revenue:IDR` Rp 100.000.000

### 3.2 Waterfall Royalti
- Gross Revenue = Rp 100.000.000
- Distribution fee 15% (1.500 bps) = Rp 15.000.000
- Recoupment Advance = Rp 30.000.000
- Net Distributable Pool = Rp 100.000.000 − Rp 15.000.000 − Rp 30.000.000 = Rp 55.000.000
- Porsi Rightsholders:
  - Komponis A (5.000 bps = 50%): Rp 27.500.000
  - Penulis Lirik B (3.000 bps = 30%): Rp 16.500.000
  - Produser C (2.000 bps = 20%): Rp 11.000.000
- **Posting**:
  - DR `expense:med:royalty:IDR` Rp 55.000.000
  - CR `ap:med:rightsholder:A:IDR` Rp 27.500.000
  - CR `ap:med:rightsholder:B:IDR` Rp 16.500.000
  - CR `ap:med:rightsholder:C:IDR` Rp 11.000.000

---

## 4. Otorisasi Peran (RBAC) & Anti-IDOR
- `/media`: `admin`, `media_admin`, `studio_manager`, `rights_manager`.
- `/media/studios`: `admin`, `studio_manager`, `media_admin`.
- `/media/licensing`: `admin`, `rights_manager`, `media_admin`.
- `/media/royalties/{id}`: Pemegang hak hanya boleh melihat statement royalti milik mereka sendiri (`rightsholder_party_id === user->party_id` atau `entity_id`). Pengguna entitas lain ditolak 403.
- Auditor: Hak baca (read-only) untuk seluruh rute.

---

## 5. Audit Dua-Sumber (`med:audit`)
Audit command `med:audit` diperkuat untuk memverifikasi integritas faktual:
1. **Royalti vs Lisensi**: `Σ royalti terbit = Σ (pendapatan lisensi − fee − recoupment) × porsi`.
2. **Anti Tumpang Tindih Lisensi**: Nol lisensi eksklusif yang saling tumpang tindih wilayah, jenis hak, dan periode.
3. **Integritas Porsi Hak**: `Σ share_bps = 10.000` pada setiap tanggal berlaku untuk seluruh aset IP aktif.
4. **Impresi Kampanye Iklan**: Laporan kampanye sama persis dengan jumlah impresi terverifikasi (bebas duplikat).
5. **Anti Double-Booking Slot Studio**: Nol slot waktu studio ganda.
6. **Kompabilitas Fixture**: `MedAuditCorruptionFixture` tetap gagal dengan exit code 1.

---

## 6. Usulan DECISIONS & Register Minus
- **Usulan DECISIONS**:
  - Alokasi slot studio menggunakan interval 30 menit diskrit dengan tabel klaim `med_studio_slot_claims` dan `lockForUpdate`.
  - Pembulatan royalti multi-pihak menyerap sisa sen pada baris penerima dengan porsi terbesar/terakhir (penny absorption).
  - Pengecekan tumpang tindih lisensi menggunakan `IntervalIndex` [start, end) setengah terbuka.
- **Register Minus Usulan**:
  - M-MED-01: Integrasi sensor footfall fisik OOH disederhanakan pada event simulasi impresi digital terverifikasi.
  - M-MED-02: Watermark digital steganografi disimulasikan menggunakan hash chain SHA-256 terverifikasi pada metadata aset.
