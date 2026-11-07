# Definition of Ready (DoR) — Fase 89: Ticketing, Access Control, Usia & Venue Operations

**Modul Pemilik:** Venue (`ven_`)  
**Status Eksekusi:** [🔵] Siap Verifikasi  
**Basis Master:** `d40d6571`  
**Standar Rekayasa:** Protokol G7 + Tambahan Ketat W8 (Poles UI 35%, Kedalaman Logika & Audit Dua Sumber)  

---

## 1. Rencana Anggaran Baris (RAB) B1 Sesuai T-2

Target Ambang Keras B1: $\ge 6.000$ baris logika bersih (di luar migrasi, seeder, factory, Blade, test).

| Lapisan Arsitektur | Rencana Baris B1 | Target Realisasi | Keterangan Komponen |
|---|---|---|---|
| Algoritma & Value Object (T-4) | 900–1.400 | 1.150 | V1 TierCountdownPricer, V2 AntiPassbackGate, V3 ZoneCapacityGovernor, V4 MinimumSpendSettlement, sertifikat & verifier |
| State Machine (Enum, Transisi, Guard, Riwayat) | 500–900 | 850 | EventStateMachine (8 status), TicketStateMachine (8 status), TableBookingStateMachine (7 status) |
| Aplikasi (Action & Service Bisnis) | 1.800–2.800 | 2.400 | IssueTicketWithGaplessHashAction, ScanGateWithAntiPassbackAction, ReserveTableWithEscrowDepositAction, SettleTableBillWithMinimumSpendAction, CancelEventWithCompensationSagaAction, AdjustZoneCapacityForWeatherAction, IssueCrowdPermitAction |
| Integrasi (Kontrak, Adaptor, Saga Kompensasi) | 500–900 | 750 | G7-K15 EventCoverBinder (Insurance), G7-K17 OutletSaleRecorder (Resto), PartyAgeVerificationAdapter, VenueCancellationSaga, TableBookingSaga |
| Posting Ledger & Chart of Accounts | 300–500 | 450 | VenueAccounts registration, VenuePostingHelper, TicketLedgerAction, TableEscrowLedgerAction, InsuranceExpenseLedgerAction |
| HTTP (Controller, FormRequest, Policy, ViewModel) | 700–1.100 | 950 | VenueEventController, GateScanController, VenueZoneController, TableBookingController, PermitController, GuestTicketPortalController |
| Command Artisan & Laporan CSV | 500–900 | 650 | AuditVenueCommand (diperluas dua sumber), CloseEventCommand, PermitExpiryScanCommand, WeatherCheckCommand, CsvExportServices |
| Model Eloquent Domain (Append-Only Invariants) | 300–500 | 450 | EntertainmentVenue, EntertainmentZone, EntertainmentEvent, EntertainmentTicket, EntertainmentTableBooking, VenuePermit, VenueExternalFactor, VenueSaga |
| **Total Rencana B1** | **5.000–8.900** | **~7.650** | **Memenuhi Ambang Keras $\ge 6.000$** |

---

## 2. Kamus Data & Skema Tabel Database (`ven_`)

Semua tabel menggunakan integer minor unit untuk uang/kuantitas, FK nyata ke tabel terkait, dan indeks unik pencegah duplikasi:

### 1. `ven_entertainment_venues`
- `id` (bigIncrements, primary key): Pengenal unik venue.
- `venue_code` (string 32, unique): Kode identitas unik venue (contoh: `VEN-BALI-ATLAS-01`).
- `name` (string 128): Nama komersial venue (contoh: `Atlas Beach Club Canggu`).
- `venue_type` (string 32): Kategori tempat (`BEACH_CLUB`, `NIGHTCLUB`, `ROOFTOP_LOUNGE`, `FESTIVAL_GROUNDS`).
- `city` (string 64): Kota/kabupaten lokasi fisik untuk penentuan perda PBJT (contoh: `Badung`, `Denpasar`, `Jakarta Selatan`).
- `max_legal_capacity` (integer): Batas daya tampung maksimum yang diizinkan izin keramaian kepolisian.
- `min_age_requirement` (integer, default 21): Batas usia legal minimal pengunjung tempat hiburan.
- `created_at`, `updated_at`: Waktu pencatatan.

### 2. `ven_entertainment_zones`
- `id` (bigIncrements, primary key): Pengenal unik zona.
- `zone_code` (string 32, unique): Kode zona (contoh: `ZN-CANGGU-POOL`, `ZN-CANGGU-VIP`).
- `venue_id` (foreignId -> `ven_entertainment_venues`): Relasi ke venue induk.
- `name` (string 64): Nama area fisik (`Lagoon Pool Deck`, `VIP Balcony`, `Main Dance Floor`).
- `capacity_limit` (integer): Batas kapasitas legal spesifik zona tersebut.
- `current_occupancy` (integer, default 0): Agregat akumulasi orang saat ini ($\sum IN - \sum OUT$).
- `crowd_lockdown_active` (boolean, default false): Flag darurat penutupan pintu zona saat terjadi kepadatan kritis.
- `is_outdoor` (boolean, default false): Penanda zona terbuka yang rentan terhadap cuaca hujan/angin pesisir.
- `created_at`, `updated_at`: Waktu pencatatan.

### 3. `ven_entertainment_events`
- `id` (bigIncrements, primary key): Pengenal unik acara.
- `event_code` (string 32, unique): Kode unik acara (contoh: `EVT-2026-SUNSET-01`).
- `venue_id` (foreignId -> `ven_entertainment_venues`): Venue penyelenggara.
- `title` (string 128): Judul acara hiburan/festival.
- `doors_open_at` (dateTime): Waktu pembukaan pintu gerbang masuk penonton.
- `doors_closed_at` (dateTime, nullable): Waktu penutupan acara dan gerbang keluar.
- `ticket_floor_price_idr` (bigInteger): Batas harga terendah tiket (floor) yang disepakati kontrak penampil.
- `ticket_ceiling_price_idr` (bigInteger): Batas harga tertinggi tiket (ceiling) dari batas kewajaran pasar.
- `current_ticket_price_idr` (bigInteger): Harga aktif tiket saat ini yang dihasilkan algoritma V1.
- `status` (string 32, default `draft`): Status State Machine (8 status: `draft`, `permit_pending`, `on_sale`, `sold_out`, `live`, `closed`, `settled`, `cancelled`).
- `settled_at` (timestamp, nullable): Waktu rekonsiliasi akuntansi akhir selesai.
- `created_at`, `updated_at`: Waktu pencatatan.

### 4. `ven_entertainment_tickets`
- `id` (bigIncrements, primary key): Pengenal unik tiket.
- `ticket_number` (string 32, unique): Nomor seri tiket gapless per event (contoh: `TCK-EVT-01-000001`).
- `ticket_uuid` (string 36, unique): UUID kanonik pengenal tiket.
- `event_id` (foreignId -> `ven_entertainment_events`): Acara yang bersangkutan.
- `tier_id` (foreignId -> `ven_ticket_tiers`, nullable): Tier tiket (Early Bird, GA, VIP Pass).
- `buyer_user_id` (foreignId -> `users`): Pengguna pemilik tiket terdaftar.
- `price_paid_idr` (bigInteger): Nominal rupiah murni yang dibayar pembeli.
- `tax_paid_idr` (bigInteger): Nominal pajak PBJT yang dipungut saat pembelian tiket.
- `ticket_hash` (string 64): Hash SHA-256 kanonik dari data tiket saat ini.
- `prev_hash` (string 64, nullable): Hash tiket sebelumnya dalam rantai hash acara.
- `status` (string 32, default `reserved`): State Machine 8 status (`reserved`, `issued`, `transferred`, `scanned_in`, `scanned_out`, `used`, `refunded`, `void`).
- `reentry_count` (integer, default 0): Akumulasi pemindaian masuk ulang yang telah dilakukan.
- `last_direction` (string 8, nullable): Arah pemindaian terakhir (`IN` atau `OUT`).
- `scanned_at` (timestamp, nullable): Waktu pemindaian pertama kali.
- `idempotency_key` (string 128, unique): Kunci idempotensi transaksi penerbitan tiket.
- `created_at`, `updated_at`: Waktu pencatatan.

### 5. `ven_entertainment_table_bookings`
- `id` (bigIncrements, primary key): Pengenal unik reservasi meja.
- `booking_code` (string 32, unique): Nomor booking meja gapless (contoh: `BKG-TBL-000001`).
- `event_id` (foreignId -> `ven_entertainment_events`): Acara terkait.
- `table_number` (string 16): Nomor/kode fisik meja atau cabana (contoh: `VIP-CABANA-01`).
- `user_id` (foreignId -> `users`): Tamu pemesan meja.
- `minimum_spend_idr` (bigInteger): Nilai komitmen minimum belanja makanan & minuman (F&B).
- `deposit_amount_idr` (bigInteger): Uang muka deposit yang telah dikunci di akun escrow.
- `actual_spend_idr` (bigInteger, default 0): Total konsumsi nyata F&B dari POS resto.
- `shortfall_idr` (bigInteger, default 0): Nilai denda/kekurangan bila konsumsi di bawah minimum spend.
- `forfeited_deposit_idr` (bigInteger, default 0): Deposit yang hangus akibat tamu tidak hadir (no-show).
- `status` (string 32, default `requested`): State Machine 7 status (`requested`, `deposit_held`, `confirmed`, `seated`, `settled`, `no_show_forfeited`, `cancelled_refunded`).
- `idempotency_key` (string 128, unique): Kunci idempotensi proses booking.
- `created_at`, `updated_at`: Waktu pencatatan.

### 6. `ven_gate_scans`
- `id` (bigIncrements, primary key): Pengenal unik catatan pemindaian.
- `ticket_id` (foreignId -> `ven_entertainment_tickets`): Tiket yang dipindai.
- `zone_id` (foreignId -> `ven_entertainment_zones`, nullable): Zona gerbang yang dilewati.
- `gate_code` (string 32): Kode fisik pintu putar/gerbang (contoh: `GATE-NORTH-TURNSTILE-01`).
- `direction` (string 8): Arah aliran pengunjung (`IN` untuk masuk, `OUT` untuk keluar).
- `scan_result` (string 16): Hasil keputusan pemindaian (`ALLOWED` atau `DENIED`).
- `denial_reason` (string 255, nullable): Penjelasan penyebab penolakan (`UNDERAGE`, `PASSBACK_VIOLATION`, `REPLAY_ATTACK`, `REENTRY_LIMIT_EXCEEDED`, `ZONE_OVERCAPACITY`).
- `nonce` (string 64, unique): Token unik acak sekali pakai dari kode QR dinamis.
- `scanned_by_user_id` (foreignId -> `users`, nullable): Petugas gerbang yang melakukan pemindaian.
- `scanned_at` (timestamp): Waktu pasti pemindaian terjadi.
- `created_at`, `updated_at`: Waktu pencatatan.

### 7. `ven_permits`
- `id` (bigIncrements, primary key): Pengenal unik izin keramaian.
- `permit_number` (string 64, unique): Nomor seri surat izin gapless dari kepolisian (contoh: `SIK-POLDA-2026-0001`).
- `event_id` (foreignId -> `ven_entertainment_events`): Acara yang diizinkan.
- `issuing_authority` (string 128): Instansi penerbit (contoh: `Polda Bali - Ditintelkam`).
- `max_legal_capacity` (integer): Batas kapasitas maksimum sah penonton.
- `valid_from` (timestamp): Waktu awal berlaku izin.
- `valid_until` (timestamp): Batas akhir waktu berlaku izin.
- `status` (string 32): Status izin (`PENDING`, `APPROVED`, `REVOKED`, `EXPIRED`).
- `revocation_reason` (string 255, nullable): Alasan pencabutan izin jika dibatalkan otoritas.
- `created_at`, `updated_at`: Waktu pencatatan.

### 8. `ven_external_factors`
- `id` (bigIncrements, primary key): Pengenal unik rekaman faktor eksternal.
- `factor_key` (string 64): Identitas faktor (contoh: `weather.rain_probability_bps`, `compliance.min_age_years`).
- `version` (integer): Versi parameter untuk audit time-travel.
- `effective_from` (date): Tanggal mulai berlakunya parameter.
- `source` (string 128): Sumber rujukan data (contoh: `BMKG Maritim`, `UU HKPD 1/2022`).
- `label` (string 32): Kategori kepatuhan (`regulasi`, `asumsi_simulasi`, `fixture`).
- `numeric_value_scaled` (bigInteger): Nilai numerik dalam representasi skala integer (mis. bps).
- `unit` (string 32): Satuan parameter (`bps`, `years`, `idr`, `percent`).
- `metadata_json` (json, nullable): Informasi tambahan atau atribut pendukung.
- Unique Index: `['factor_key', 'version']`.
- `created_at`, `updated_at`: Waktu pencatatan.

### 9. `ven_sagas`
- `id` (bigIncrements, primary key): Pengenal unik eksekusi saga.
- `saga_type` (string 64): Jenis transaksi terdistribusi (`EVENT_CANCELLATION_SAGA`, `TABLE_BOOKING_SAGA`).
- `saga_reference` (string 64, unique): Nomor referensi transaksi induk.
- `current_step` (string 64): Langkah aktif yang sedang dieksekusi.
- `status` (string 32): Status akhir saga (`STARTED`, `COMPLETED`, `COMPENSATING`, `COMPENSATED`, `FAILED`).
- `payload_json` (json): Payload data operasional saga.
- `compensation_log_json` (json, nullable): Rekaman langkah kompensasi yang telah dijalankan.
- `completed_at` (timestamp, nullable): Waktu penyelesaian penuh.
- `created_at`, `updated_at`: Waktu pencatatan.

---

## 3. Akun Buku Besar (Ledger Chart of Accounts) & Arah Posting

Sesuai tabel posting D89-7 dan prinsip IFRS 15:
1. **Penjualan Tiket**:
   - Debit: `ven:ticket_clearing:IDR` (Kas/piutang pembayaran tiket masuk dari gateway)
   - Kredit: `ven:ticket_unearned:IDR` (Kewajiban pendapatan tiket diterima di muka)
   - Kredit: `ven:entertainment_tax_payable:IDR` (Kewajiban pajak hiburan PBJT yang dipungut)
2. **Penutupan Acara Definitif (`CloseEntertainmentEventAction`)**:
   - Debit: `ven:ticket_unearned:IDR` (Penghapusan kewajiban unearned)
   - Kredit: `ven:ticket_revenue:IDR` (Pengakuan laba pendapatan tiket riil)
3. **Pembatalan / Pengembalian Tiket (`RefundVenueTicketAction`)**:
   - Debit: `ven:ticket_unearned:IDR` (Pembalikan kewajiban unearned)
   - Debit: `ven:entertainment_tax_payable:IDR` (Pembalikan kewajiban pajak yang belum disetor)
   - Kredit: `ven:ticket_clearing:IDR` (Pengembalian kas kepada pembeli)
4. **Hold Deposit Meja VIP (`ReserveTableWithMinimumSpendDepositAction`)**:
   - Debit: `ven:vip_escrow:IDR` (Dana escrow terkunci dari saldo/kartu pelanggan)
   - Kredit: `ven:vip_unearned_rev:IDR` (Kewajiban penampungan deposit pemesanan meja)
5. **Penyelesaian Tagihan Meja VIP (`SettleTableBillAction`)**:
   - Debit: `ven:vip_unearned_rev:IDR` (Pelepasan kewajiban deposit)
   - Kredit: `ven:table_revenue:IDR` (Pengakuan pendapatan sewa meja & shortfall minimum spend)
6. **No-Show Meja VIP Hangus (`ForfeitTableNoShowDepositAction`)**:
   - Debit: `ven:vip_unearned_rev:IDR` (Pelepasan kewajiban deposit no-show)
   - Kredit: `ven:vip_noshow_rev:IDR` (Pengakuan laba penalti pembatalan sepihak 50%)
7. **Premi Asuransi Kerugian Acara (G7-K15 `EventCoverBinder`)**:
   - Debit: `ven:event_insurance_expense:IDR` (Beban operasional asuransi acara)
   - Kredit: `ven:organizer_payable:IDR` (Hutang kepada mitra pialang asuransi)

---

## 4. Peran Pengguna (RBAC) & Endpoint Rute

| Rute Web | Method | Peran Berhak | Fungsi Bisnis |
|---|---|---|---|
| `/venue/events` | GET, POST | `venue_manager` | Manajemen kalender event, status izin, dan kuota tier |
| `/venue/events/{event}/close` | POST | `venue_manager` | Penutupan event definitif & pengakuan pendapatan unearned |
| `/venue/gate` | GET, POST | `venue_staff` | Pindai gerbang ramah layar mobile 375 px, anti-passback V2, verifikasi usia |
| `/venue/zones` | GET, POST | `crowd_safety` | Heatmap kepadatan zona, override laju admisi V3, lockdown darurat |
| `/venue/tables` | GET, POST | `venue_manager`, `venue_staff` | Denah meja VIP, booking deposit hold, penagihan minimum spend V4 |
| `/venue/permits` | GET, POST | `venue_manager`, `compliance_officer` | Pendaftaran izin keramaian gapless, audit kapasitas legal, asuransi event K15 |
| `/guest/tickets` | GET | `customer` | Portal tamu terisolasi: tiket digital mandiri, QR dinamis, bukti bayar |
| `/venue/reports/export` | GET | `venue_manager` | Ekspor CSV resmi data pindaian gerbang, penjualan tiket, dan meja VIP |

---

## 5. Algoritma Bersertifikat Baru (T-4)

1. **V1 `Modules\Venue\Domain\Algorithms\TierCountdownPricer`**:
   - Menghitung harga tiket dinamis dari harga tier dasar (Early Bird, General Admission, Door) dipengaruhi oleh rasio penjualan (faktor permintaan) dan peluang hujan pesisir (faktor cuaca), dibulatkan ke bawah ke Rp 1.000 dan dijepit di interval $[floor, ceiling]$.
   - Vektor pengujian: floor 280.000, ceiling 500.000:
     - GA 350.000, terjual 8.500 bps, hujan 2.000 bps $\to$ 420.000
     - EB 250.000, terjual 3.000 bps, hujan 7.000 bps $\to$ 225.000 $\to$ floor 280.000
     - Door 450.000, terjual 9.000 bps $\to$ 540.000 $\to$ ceiling 500.000
     - GA 350.000, terjual 7.999 bps, hujan 6.000 bps $\to$ 346.000
2. **V2 `Modules\Shared\Domain\Algorithms\Access\AntiPassbackGate`**:
   - Memastikan pergerakan tiket bergantian secara ketat IN $\to$ OUT $\to$ IN dengan batas re-entry $N$ (fixture 2) serta penolakan replay nonce identik.
   - Sertifikat: urutan pindaian hash-chain tiket utuh, $\Sigma IN - \Sigma OUT \in \{0, 1\}$.
3. **V3 `Modules\Venue\Domain\Algorithms\ZoneCapacityGovernor`**:
   - Mengendalikan admisi masuk zona berbasis safety margin kapasitas legal dan token bucket laju masuk per menit: $\text{Kapasitas Efektif} = \lfloor\text{legal} \times (10.000 - \text{margin})/10.000\rfloor$. Admisi $= \min(\text{antrean}, \text{sisa kapasitas}, \text{token})$.
   - Vektor: legal 500, margin 500 bps (efektif 475), laju 30/menit.
4. **V4 `Modules\Venue\Domain\Algorithms\MinimumSpendSettlement`**:
   - Menghitung penyelesaian tagihan meja VIP: tagihan $=\max(\text{belanja}, \text{minimum spend})$. Deposit di-capture, kekurangan dibebankan sebagai shortfall revenue. Jika no-show, deposit hangus sebesar 50% dan sisanya dikembalikan.

---

## 7. Daftar Perluasan Wajib Fase 89 & Poles UI 35% (P-UI)

Sesuai ketentuan ketat W8, antarmuka pengguna pada Fase 89 mematuhi 10 butir polesan UI:
1. **Layar Gerbang 375 px**:
   - Didesain khusus untuk petugas operasional lapangan yang memegang ponsel pintar dengan satu tangan.
   - Kartu status hasil pemindaian berukuran besar dengan teks tebal dan kode warna kontras tinggi (memenuhi standar aksesibilitas WCAG 2.1 AA).
   - Menampilkan status verifikasi usia tanpa pernah membocorkan tanggal lahir atau data pribadi mentah (PII) tamu.
2. **Peta Zona Okupansi (Heatmap SVG)**:
   - Visualisasi grafis server-side render berbasis SVG untuk menampilkan tata letak fisik zona venue.
   - Pewarnaan gradasi dinamis (Hijau $\to$ Kuning $\to$ Oranye $\to$ Merah Berkedip) berdasarkan rasio okupansi riil terhadap kapasitas efektif.
3. **Kalender Acara Terpadu dengan Status Izin**:
   - Tampilan kalender interaktif yang merangkum jadwal acara mendatang beserta indikator lampu lalu lintas surat izin keramaian kepolisian.
4. **Papan Tata Letak Meja VIP (Table Floorplan)**:
   - Visualisasi posisi meja, status reservasi (`AVAILABLE`, `HELD`, `SEATED`, `SETTLED`), dan nilai komitmen minimum spend.
5. **Simulator Harga Tiket Dinamis V1**:
   - Alat simulasi internal bagi Venue Manager untuk menguji skenario perubahan harga tiket berdasarkan rasio keterisian kuota dan prakiraan cuaca pesisir.
6. **Laporan & Dasbor Penjualan per Tier dan Jam**:
   - Grafik batang dan garis SVG untuk tren penjualan tiket dan pemesanan meja VIP per jam operasional.
7. **Bilah Ekspor CSV Multiformat**:
   - Fitur ekspor berkas CSV resmi untuk seluruh data penjualan tiket, riwayat pindaian gerbang, dan audit minimum spend meja VIP.

---

## 8. Matriks Pengujian Akseptansi L.2 (Plafon Keras $\le 5$ Test)

Seluruh skenario pengujian Fase 89 digabungkan secara elegan ke dalam maksimal 5 test akseptansi pada `tests/Acceptance/VenueOps/VenueOpsAcceptanceTest.php`:
1. **Test 1 (Akses RBAC Terpadu)**: Memvalidasi hak akses seluruh rute Fase 89 (`/venue/events`, `/venue/gate`, `/venue/zones`, `/venue/tables`, `/venue/permits`, `/guest/tickets`) untuk peran berhak (200/302) dan peran tidak berhak (403).
2. **Test 2 (Alur Utama End-to-End & Ledger)**: Simulasi siklus hidup tiket penuh: pembelian dinamis V1 $\to$ tiket gapless & hash-chain $\to$ pindaian gerbang V2 $\to$ penutupan event $\to$ pengakuan pendapatan akrual unearned revenue ke revenue final $\to$ verifikasi idempotensi.
3. **Test 3 (Jalur Galat & Aturan Bisnis Kunci)**: Pengujian penolakan pindaian ganda anti-passback, penolakan tamu di bawah umur 21 tahun tanpa kebocoran PII, penolakan admisi saat zona penuh V3, serta eksekusi no-show forfeiture meja VIP $50\%$ V4.
4. **Test 4 (IDOR Guard Portal Tamu)**: Pengujian anti-IDOR yang memastikan tamu hanya dapat melihat dan mengakses tiket digital serta kode QR miliknya sendiri.
5. **Test 5 (Audit Dua Sumber ven:audit & Fixture)**: Pengujian kesetimbangan audit dua sumber: data bersih keluar dengan exit code 0; injeksi anomali fixture korupsi keluar dengan exit code $\ne 0$ dan menyebutkan entitas yang rusak.

