# Definition of Ready (DoR) — Lini 2: Pertambangan (Mining MVP Vertical Slice)

#### DoR Lini 2 — Mining MVP
- **Disetujui pemilik:** Internal Gate L2 (Pelaksana Pengembangan Vertical Slice)
- **Tujuan bisnis (1–2 kalimat):** Menyediakan operasional tambang terpadu pit-to-port yang mencakup penimbangan muatan truk dengan tiket hash-chain tamper-evident dan mutasi stockpile atomik, penugasan armada truk-ke-shovel deterministik dengan Hungarian solver bersertifikat dual, pencampuran batubara/nikel multi-stockpile menggunakan simplex rasional eksak, rekonsiliasi tiga titik produksi-stockpile-pengapalan berentitas investigasi, perhitungan royalti statuter bulanan idempoten berbasis produksi terverifikasi dengan posting delta finalisasi, serta penegakan K3 interlock area dan evaluasi anomali BBM kontraktor berbasis robust statistics.
- **Kutipan Pasal Dokumen Rujukan:**
  - `docs/KONSEP.md` §A1 (baris 70): Registry modul & prefiks tabel `min_`.
  - `docs/KONSEP.md` §A2 (baris 109–150): Aturan buku besar double-entry, saldo normal, larangan saldo negatif tanpa alasan, akun kontrol statis, audit dua sumber.
  - `docs/KONSEP.md` §A3 (baris 151–158): Larangan float pada moneter/kuantitas kritis, integer rupiah, tonase kg bigint, kalori integer kcal/kg, grade bps.
  - `docs/KONSEP.md` §A4 (baris 159–188): Idempotensi key deterministik `{prefiks}:{aksi}:{id_bisnis}` tanpa random/waktu.
  - `docs/KONSEP.md` §A5 (baris 189–196): Tipe transaksi ledger registry enum <= 32 karakter.
  - `docs/KONSEP.md` §A6 (baris 197–204): RBAC per modul via seeder idempoten `MiningRbacSeeder`.
  - `docs/KONSEP.md` §A7 (baris 205–208): Kriptografi hash-chain kanonikal berurutan (`CanonicalHashChain`).
  - `docs/KONSEP.md` §A8 (baris 209–220): `ClockInterface` untuk seluruh waktu bisnis, zona waktu lokasi tambang (WITA/WIB/WIT).
  - `docs/KONSEP.md` §A10 (baris 232–241): Perlindungan mass assignment, larangan `$guarded = []`.
  - `docs/KONSEP.md` §A12 (baris 248–265): Skema relasi lintas modul `cross_module_columns`.
  - `docs/KONSEP.md` §A13 (baris 266–287): Larangan teknis `back()->errors()` dan jalan pintas terlarang.
  - `docs/KONSEP.md` §A15 (A15.1–A15.6, baris 324–470): Template normatif Action, Service, Seeder, Model append-only.
  - `docs/KONSEP.md` §12F (baris 1204–1229): Spesifikasi vertical slice minimum Pertambangan (Slice 1 Weighbridge & Dispatch, Slice 2 Grade & Rekonsiliasi, Slice 3 Royalti & K3).
  - `docs/PROGRESS.md` P2 (baris 97), P3 (baris 124, larangan X1–X25), P8 (baris 202), P9 (baris 243).
- **Modul pemilik & prefiks:**
  - Modul: `Mining`
  - Prefiks tabel: `min_` (terdaftar di `config/modules.php` dan `KONSEP.md` §A1).
- **Prasyarat & statusnya (harus ✅):**
  - Bagian 0: Pustaka Algoritma Bersama (`CanonicalHashChain`, `RobustStatistics`, `HungarianSolver`, `RationalSimplex`, `IntegerBlendRounder`) — Status: ✅ (Lolos G-B0).
  - Lini 1: Venue Entertainment MVP — Status: ✅ (Lolos G-L1).
  - Core Modules: `Banking` (Ledger), `Core` (ClockInterface, ApprovalEngineInterface), `Asset` (Equipment register), `Party` (Contractor & Worker).
- **Item yang dikerjakan sesi ini & yang ditunda (→ Register Minus):**
  - Dikerjakan:
    1. Seeder role Mining (`weighbridge_operator`, `dispatcher_mine`, `mine_engineer`, `finance_officer`, `hse_officer`) via `RbacService` secara idempoten (U-5, Z8).
    2. Master data site (dengan timezone lokasi WITA/WIB/WIT), pit, area (status ACTIVE/STOPPED), shovel slot, stockpile, equipment (tara & kapasitas terdaftar).
    3. Bagan akun `MiningAccounts` dengan kode standar KONSEP §12F (`expense:min:royalty:IDR`, `min:royalty_payable:IDR`, `min:penalty_income:IDR`, `min:sales_revenue:IDR`, dll.) dengan saldo normal benar (`allow_negative => false`).
    4. Model Stockpile agregat kuantitas (PR4: simpan $\Sigma\text{kg}$ dan $\Sigma(\text{kg}\times\text{parameter})$ integer, bukan rata-rata).
    5. Tiket timbang jembatan timbang (WeighbridgeTicket) append-only (PR5) dengan validasi tara truk ± `tare_tolerance_bps` (default 100), flag overload bila muatan > kapasitas, penolakan tiket duplikat truk sama < `min_cycle_seconds` (default 600), hash-chain kanonikal per site, serta pembuatan mutasi stockpile (`min_stockpile_movements`) atomik dalam satu transaksi database (T-1, T-2).
    6. Event domain `min.weighbridge.recorded.v1`.
    7. Penugasan armada (Dispatch) dengan `HungarianSolver` (B0.5) meminimalkan total detik siklus (muat + angkut + antre), shovel diperluas menjadi slot per jam, eliminasi truk grounded / izin kerja kedaluwarsa / area dihentikan, sertifikat dual tersimpan & terverifikasi `AssignmentCertificateVerifier`, serta mekanisme override dispatcher tercatat (T-3).
    8. Rencana pencampuran bijih/batubara (BlendPlan) menggunakan `RationalSimplex` (B0.7) + `IntegerBlendRounder` (B0.8) dengan verifikasi `LpCertificateVerifier` (PR2, T-4) dan eksekusi pengapalan yang menarik movement keluar stockpile sesuai kuota.
    9. Rekonsiliasi tiga titik $\Sigma\text{timbang} = \Sigma\text{mutasi stockpile} = \Sigma\text{pengapalan} \pm \text{recon\_tolerance\_bps}$ (default 50 bps); selisih di luar toleransi membuka entitas `ReconciliationInvestigation` (T-5).
    10. Perhitungan royalti bulanan statuter berbasis produksi timbang terverifikasi per (site, komoditas, bulan lokal) sesuai rumus integer exact T-6, idempoten, posting jurnal akrual, dan pemrosesan finalisasi harga/kurs via posting delta (T-6).
    11. Manajemen K3 & izin kerja berisiko lewat `ApprovalEngineInterface` bermasa berlaku; insiden kategori tinggi menghentikan area tambang secara otomatis dan menolak aktivitas penimbangan dan dispatch (T-7).
    12. Evaluasi kontraktor dan penalti sekali per evaluasi; deteksi anomali konsumsi BBM per 100 ton-km menggunakan `RobustStatistics` median & MAD (B0.3, T-8) yang memicu hold pembayaran kontraktor.
    13. Penguatan command `min:audit` (`mining:audit`) dengan verifikasi dua-sumber: $\Sigma\text{timbang neto} = \Sigma\text{movement masuk}$, $\Sigma\text{movement keluar} = \Sigma\text{pengapalan} \pm \text{toleransi}$, royalti = produksi $\times$ tarif, BBM terbayar = konsumsi terukur $\pm$ toleransi, keabsahan rantai hash-chain, dan verifikasi ulang seluruh sertifikat Hungarian & Simplex.
    14. UI timbangan digital mobile-friendly 375 px di `/mining/weighbridge` khusus role `weighbridge_operator` dan rute dispatch di `/mining/dispatch`.
  - Ditunda ke Register Minus:
    - Integrasi GPS live telemetri sub-detik OBD-II truk bergerak kontinu (ditangani pada Lini 3 Mobility).
    - Offtake letter-of-credit antar-negara dengan Trade Finance (Fase 122).
- **Entitas / Tabel & State Machine:**
  - `min_sites`: master konsesi/lokasi tambang (id, site_code, name, commodity, timezone, location).
  - `min_pits`: pit tambang (id, site_id, pit_code, name, target_production_ton).
  - `min_areas`: area operasional pit/stockpile (id, site_id, pit_id, area_code, name, status: ACTIVE, STOPPED, stop_reason, stopped_at).
  - `min_equipment`: armada alat berat (id, site_id, equipment_code, type: HAUL_TRUCK, EXCAVATOR, tare_weight_kg, capacity_kg, status: AVAILABLE, DISPATCHED, GROUNDED, MAINTENANCE).
  - `min_shovel_slots`: slot penugasan shovel per jam (id, equipment_id, slot_code, hour_window, capacity_trucks_per_hour).
  - `min_stockpiles`: lokasi timbunan bijih/batubara (id, site_id, stockpile_code, name, total_kg, sum_kg_calories, sum_kg_ash_bps, sum_kg_sulfur_bps, sum_kg_moisture_bps, sum_kg_metal_bps).
  - `min_weighbridge_tickets`: tiket timbang (id, ticket_uuid, ticket_number, site_id, pit_id, area_id, truck_id, driver_party_id, gross_weight_kg, tare_weight_kg, net_weight_kg, is_overload, ticket_hash, prev_hash, destination_stockpile_id, weighed_at) — APPEND-ONLY.
  - `min_stockpile_movements`: mutasi kuantitas stockpile (id, movement_uuid, site_id, stockpile_id, movement_type: INFLOW_HAUL, OUTFLOW_BLEND, direction: IN, OUT, weight_kg, reference_type, reference_id) — APPEND-ONLY.
  - `min_dispatch_plans`: batch dispatch run teroptimasi (id, plan_uuid, site_id, shift_code, algorithm: HUNGARIAN, total_cost_seconds, certificate_json, status: COMMITTED, OVERRIDDEN).
  - `min_dispatch_assignments`: baris penugasan truk-shovel (id, dispatch_plan_id, truck_id, shovel_slot_id, estimated_cycle_seconds, is_override, override_reason, override_by_user_id).
  - `min_blend_plans`: formula blending pengapalan (id, plan_uuid, site_id, target_vessel_name, target_min_kg, target_max_kg, target_spec_json, allocated_kg, status: DRAFT, SOLVED, EXECUTED, certificate_json).
  - `min_reconciliation_runs`: pencocokan tiga titik (id, run_uuid, site_id, period_start, period_end, total_weighed_kg, total_stockpile_in_kg, total_stockpile_out_kg, total_shipped_kg, variance_kg, variance_bps, status: BALANCED, DISCREPANCY_INVESTIGATION).
  - `min_reconciliation_investigations`: investigasi variansi di luar toleransi (id, investigation_code, reconciliation_run_id, status: OPEN, UNDER_REVIEW, RESOLVED, resolution_notes).
  - `min_royalty_returns`: SPT royalti bulanan (id, return_uuid, site_id, commodity, period_local, total_production_kg, benchmark_price_usd_cents_per_ton, exchange_rate_idr_per_usd_1e6, royalty_rate_bps, calculated_royalty_idr, finalized_royalty_idr, delta_royalty_idr, status: PROVISIONAL, FINALIZED).
  - `min_work_permits`: izin kerja K3 (id, permit_number, site_id, area_id, worker_party_id, permit_type, valid_from, valid_until, approval_status: PENDING, APPROVED, REJECTED, EXPIRED).
  - `min_safety_incidents`: catatan insiden K3 (id, incident_code, site_id, area_id, severity: LOW, MEDIUM, HIGH, FATALITY, description, area_stopped: bool, occurred_at).
  - `min_contractor_evaluations`: penilaian kepatuhan & BBM vendor (id, evaluation_uuid, contractor_party_id, site_id, period, total_ton_km, fuel_consumed_liters, sfc_per_100_ton_km, baseline_median, baseline_mad, is_anomaly, penalty_amount_idr, payment_held: bool).
- **Posting Ledger (KONSEP §12F baris 1211–1219):**
  - Akru Royalti Bulanan:
    - Debit: `expense:min:royalty:IDR` (Beban Royalti Minerba)
    - Kredit: `min:royalty_payable:IDR` (Utang Royalti PNBP)
  - Penyesuaian Finalisasi Royalti (Posting Delta):
    - Debit: `expense:min:royalty:IDR` (sebesar nilai delta)
    - Kredit: `min:royalty_payable:IDR` (sebesar nilai delta)
  - Penalti Kontraktor (K3 / BBM Anomali):
    - Debit: `ar:min:contractor:{party}:IDR` (Piutang Klaim Penalti Kontraktor)
    - Kredit: `min:penalty_income:IDR` (Pendapatan Denda Operasional)
  - Biaya BBM Vendor:
    - Debit: `expense:min:fuel:IDR` (Beban Bahan Bakar Tambang)
    - Kredit: `ap:fuel_supplier:{party}:IDR` (Utang Suplier BBM)
  - Penjualan Offtake / Ekspor:
    - Debit: `ar:min:offtaker:{party}:IDR` (Piutang Penjualan Bijih/Batubara)
    - Kredit: `min:sales_revenue:IDR` (Pendapatan Penjualan Tambang)
- **Rute & Role (KONSEP §12F baris 1220):**
  - `/mining/dispatch` → Role: `dispatcher_mine` (dan `mine_manager`)
  - `/mining/weighbridge` → Role: `weighbridge_operator` (viewport 375 px)
  - `/mining/stockpiles` → Role: `mine_engineer` (manajemen blending & assay)
  - `/mining/royalties` → Role: `finance_officer` (akru & finalisasi royalti)
  - `/mining/hse` → Role: `hse_officer` (izin kerja PTW & penghentian area)
  - `/mining/admin` → Role: `mine_manager`
- **Audit Dua Sumber (`min:audit` / `mining:audit`):**
  1. $\Sigma$ Tiket timbang neto masuk = $\Sigma$ Mutasi stockpile inflow persis (0 selisih kg).
  2. $\Sigma$ Mutasi stockpile outflow = $\Sigma$ Pengapalan kapal/tongkang $\pm$ toleransi (`recon_tolerance_bps`).
  3. Royalti bulanan terposting = Tonase terverifikasi $\times$ formula tarif exact integer bps (0 selisih IDR).
  4. Integritas hash-chain tiket timbang utuh dan tidak terputus per site.
  5. Seluruh sertifikat penugasan Hungarian dan sertifikat blending Simplex tersimpan diverifikasi ulang valid.
  6. Pembukuan double-entry seluruh transaksi mining seimbang nol ($\Sigma\text{debit} = \Sigma\text{kredit}$).
- **Register Minus Usulan (6 Kolom):**

| ID Minus | Modul | Komponen | Deskripsi Kekurangan | Tingkat Keparahan | Rencana Mitigasi |
|---|---|---|---|---|---|
| MIN-01 | Mining | Telematics | GPS live sub-detik obd streaming armada belum real-time websocket | P2 | Ditangani di Lini 3 Mobility via IoT telemetry pipeline |
| MIN-02 | Mining | Trade | Settlement letter of credit multi-mata uang valas otomatis | P2 | Ditangani pada integrasi Fase 122 Export & Trade Finance |
| MIN-03 | Mining | Underground | Model 3D elemen hingga digital twin ventilasi terowongan bawah tanah | P2 | Dikembangkan pada fase ekspansi eksplorasi lanjutan |

---

### Bukti untuk dipindahkan ke PROGRESS (diisi setelah selesai)
- **Status Gate L2:** LULUS PENUH
- **Total Test Acceptance:** >= 70 passed
- **Simulasi 30 Hari:** Lulus tanpa selisih (exit 0)
- **Sabotase Mandiri:** Semua skenario terdeteksi dan tertolak
- **ArchScan:** 0 pelanggaran baru
