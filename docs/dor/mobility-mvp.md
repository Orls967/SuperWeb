# Definition of Ready (DoR) — Lini 3: Mobility (Telematics, EV Charging & B2B Fleet MVP)

#### DoR Lini 3 — Mobility MVP Vertical Slice
- **Disetujui pemilik:** Internal Gate L3 (Pelaksana Pengembangan Vertical Slice)
- **Tujuan bisnis (1–2 kalimat):** Menyediakan sistem mobilitas dan manajemen armada komersial terintegrasi yang mencakup penerimaan streaming telematika OBD-II/CAN append-only dan deteksi anomali berbasis statistik robust MAD dengan pemenuhan tiket servis otomatis, alokasi daya SPKLU EV cerdas dengan algoritma Dinic Max-Flow bersertifikat min-cut terverifikasi beserta meteran kWh dinamis TOU ber-hold/capture ledger, serta penagihan sewa armada B2B bulanan terverifikasi PSAK 73 ber-idempotency deterministik dan penegakan batas wilayah geofencing eksak integer.
- **Kutipan Pasal Dokumen Rujukan:**
  - `docs/KONSEP.md` §A1 (baris 102): Penggabungan modul Telematics, Ev, dan Fleet di bawah kepemilikan prefiks tunggal `oto_`.
  - `docs/KONSEP.md` §A2 (baris 108–165): Aturan buku besar double-entry, single sign convention debit negatif (−) dan kredit positif (+), aturan saldo normal, larangan saldo negatif tanpa alasan, akun kontrol statis, audit dua sumber.
  - `docs/KONSEP.md` §A3 (baris 151–158): Larangan float pada moneter/kuantitas kritis, integer rupiah, integer Wh/kWh, deci-°C, mV, permil BBM, meter odometer.
  - `docs/KONSEP.md` §A4 (baris 181–210): Idempotensi key deterministik `{prefiks}:{aksi}:{id_bisnis}` tanpa random/waktu.
  - `docs/KONSEP.md` §A5 (baris 211–225): Tipe transaksi ledger registry enum $\le 32$ karakter (`oto:ev_hold`, `oto:ev_capture`, `oto:fleet_bill`, `oto:fleet_sla_credit`).
  - `docs/KONSEP.md` §A6 (baris 226–233): RBAC per modul via seeder idempoten `MobilityRbacSeeder` (`fleet_manager`, `ev_operator`, `station_operator`, `driver`, `finance_officer_oto`).
  - `docs/KONSEP.md` §A7 (baris 234–245): `ClockInterface` untuk seluruh waktu bisnis, zona waktu stasiun/lokasi armada (`Asia/Jakarta`, `Asia/Makassar`, `Asia/Jayapura`).
  - `docs/KONSEP.md` §A8 (baris 246–258): Penanganan mata uang integer penuh IDR, Brick\Math BigDecimal/BigRational, HalfUp rounding, Largest Remainder apportionment.
  - `docs/KONSEP.md` §A10 (baris 234–245): Simulation kernel, determinisme seed tanpa `Carbon::setTestNow()` di kode produksi.
  - `docs/KONSEP.md` §A13 (baris 270–280): Audit hash-chain, append-only facts (PR5), larangan sensor default diam-diam (X25).
  - `docs/KONSEP.md` §A15 (A15.1–A15.6, baris 350–450): Template normatif Action, Service, Seeder, Model append-only.
  - `docs/KONSEP.md` §1F (baris 520–545): Spesifikasi vertical slice minimum Mobility (Slice 1 Telematics → Draf Booking AutoServe, Slice 2 Sesi EV, Slice 3 Sewa Armada B2B, posting ledger, rute & role, command `oto:recompute-baselines`, `oto:bill-fleet`, `auto:audit`).
  - `docs/KONSEP.md` §10F (baris 1139–1160) & §12F (baris 1176–1210): Koordinasi alat berat dan armada logistik.
  - `docs/PROGRESS.md` P2 (baris 97), P3 (baris 124, larangan X1–X25, khususnya X6 tanda manual, X15 silent pass, X25 default diam-diam), P8 (baris 202, V1–V12), P9 (baris 243).
- **Modul pemilik & prefiks:**
  - Modul: `Mobility` (menyatukan fungsionalitas `Telematics`, `Ev`, dan `Fleet` yang saling terikat)
  - Prefiks tabel: `oto_` (terdaftar di `config/modules.php` dan `KONSEP.md` §A1).
- **Prasyarat & statusnya (harus ✅):**
  - Bagian 0: Pustaka Algoritma Bersama (`CanonicalHashChain`, `RobustStatistics`, `PointInPolygon`, `DinicMaxFlow`, `LargestRemainder`) — Status: ✅ (Lolos G-B0).
  - Lini 1: Venue Entertainment MVP — Status: ✅ (Lolos G-L1).
  - Lini 2: Pertambangan MVP — Status: ✅ (Lolos G-L2).
  - Core Modules: `Banking` (Ledger), `Core` (ClockInterface, Vehicle registry), `AutoServe` (Service booking intake), `Payment` (PaymentGateway).
- **Item yang dikerjakan sesi ini & yang ditunda (→ Register Minus):**
  - Dikerjakan:
    1. Seeder role Mobility (`fleet_manager`, `ev_operator`, `station_operator`, `driver`, `finance_officer_oto`) via `RbacService` secara idempoten (U-5, Z8).
    2. Master data stasiun EV, charger, zona geofence depot, tipe perangkat telemetri, kontrak armada, dan aturan tarif time-of-use.
    3. Bagan akun `MobilityAccounts` dengan kode standar KONSEP §1F (`wallet:user:{id}:IDR`, `escrow:payment:IDR`, `oto:ev_charging_revenue:IDR`, `ar:oto:fleet:{party}:IDR`, `oto:fleet_lease_revenue:IDR`, `expense:oto:fleet_sla_credit:IDR`) dengan saldo normal benar (`allow_negative => false` kecuali akun escrow/kliring dan piutang).
    4. Model Tick Telemetri (`oto_telematics_ticks`) append-only (PR5) unik berpasangan `(device_id, sequence)`, penanganan out-of-order, pencatatan celah `oto_telematics_sequence_gaps`, penolakan default siluman sensor (NULL tetap NULL), dan satuan integer (deci-°C, mV, permil BBM, meter odometer) (O-1).
    5. Perhitungan baseline robust `oto:recompute-baselines` per kendaraan per metrik berbasis Median & MAD 7 hari lokal terakhir (`RobustStatistics` B0.3); deteksi anomali formula integer tanpa pembagian float/nol; fallback ambang absolut minimum saat MAD = 0 (O-2).
    6. Penanganan DTC kritis via config, penandaan status unit grounded, penerbitan event `auto.telematics.anomaly_detected.v1` tepat sekali per (kendaraan, jenis, hari lokal) yang memicu listener AutoServe membuat draf booking (O-3, Z9).
    7. Penegakan interlock penolakan kendaraan grounded pada dispatch Logistik / Fleet (O-4, Z9).
    8. Evaluasi geofence batas depot/zona menggunakan algoritma integer murni `PointInPolygon` B0.4 (mikroderajat, cross product int64, titik di tepi = di dalam) (O-8).
    9. Sesi pengisian EV (`oto_ev_sessions`): state machine `reserved -> charging -> completed | cancelled`; mulai sesi dengan `PaymentGateway::hold(estimasi)`; selesai sesi dengan `PaymentGateway::capture(Σ energi × tarif)` dan pengembalian sisa deposit; penolakan stop sesi ganda idempoten (O-5).
    10. Meter value EV (`oto_ev_meter_values`) append-only (PR5), verifikasi monoton non-menurun; perhitungan konsumsi energi per jendela tarif Time-Of-Use (termasuk jendela yang melintasi tengah malam) menggunakan interpolasi linear dan apportionment integer `LargestRemainder` B0.2 (O-5).
    11. Smart charging daya SPKLU menggunakan `DinicMaxFlow` B0.6 per slot waktu 15 menit dengan batas kapasitas trafo site dan batas daya charger; verifikasi dual min-cut certificate melalui `FlowCutCertificateVerifier` (PR2, O-6); penanganan beban berlebih dengan pelaporan kekurangan alokasi.
    12. Penagihan sewa armada B2B bulanan dengan key deterministik `oto:fleet:bill:{contract}:{periode}`; kompensasi kredit SLA deterministik ber-key `oto:fleet:sla:{breach_id}` = fee unit × `sla_credit_bps` (O-7).
    13. Verifikasi & penegakan jadwal amortisasi PSAK 73: $\sum \text{pokok} + \sum \text{bunga} = \sum \text{pembayaran}$ persis dan saldo liabilitas akhir 0 rupiah (O-7).
    14. Penguatan perintah audit dua-sumber: `tlm:audit` (tick unik, gap integritas, baseline valid), `ev:audit` (Σ kWh×tarif = Σ capture revenue, meter monotonik, sertifikat Dinic valid), `flt:audit` (tagihan = AR, kredit SLA = breach × bps, rekonsiliasi amortisasi) dan konsolidasi `auto:audit` / `mob:audit`.
    15. Antarmuka UI mobile-first 375 px pada `/oto/charging` untuk pengemudi dan dasbor operasional `/oto/fleet`.
  - Ditunda ke Register Minus:
    - V2G (Vehicle-to-Grid) feeding dinamis ke pasar spot listrik PLN (Pilar 10 Energi Fase 88).
    - Asuransi berbasis perilaku mengemudi OBD-II (UBI - Usage-Based Insurance, Pilar 2 Fase 74).
- **Entitas / Tabel & State Machine:**
  - `oto_telematics_devices`: master perangkat IoT telematika (id, device_id, vehicle_id, serial_number, token_hash, protocol, status: ACTIVE, GROUNDED, INACTIVE, last_heartbeat_at).
  - `oto_telematics_ticks`: titik telemetri OBD2/CAN (id, device_id, vehicle_id, sequence, recorded_at, latitude_e6, longitude_e6, rpm, speed_kmh, coolant_temp_deci_c, battery_mv, fuel_permil, odometer_meters, dtc_codes_json, idempotency_key) — APPEND-ONLY.
  - `oto_telematics_sequence_gaps`: pencatatan celah sequence telemetry stream (id, device_id, expected_sequence, received_sequence, detected_at).
  - `oto_telematics_baselines`: baseline performa kendaraan (id, vehicle_id, metric, median_value, mad_value, sample_count, calculated_at, window_days).
  - `oto_telematics_anomalies`: rekaman anomali sensor (id, vehicle_id, metric, anomaly_date, observed_value, threshold_value, draft_booking_id).
  - `oto_geofence_zones`: poligon batas geofence depot/zona (id, zone_code, name, polygon_json, min_lat_e6, max_lat_e6, min_lon_e6, max_lon_e6, is_active).
  - `oto_ev_stations`: stasiun SPKLU (id, station_code, name, timezone, max_grid_power_kw, location_type).
  - `oto_ev_chargers`: charger SPKLU (id, station_id, charger_code, charger_type: AC, DC_FAST, DC_ULTRA, max_power_kw, status: AVAILABLE, OCCUPIED, MAINTENANCE).
  - `oto_ev_tariff_windows`: jendela tarif Time-Of-Use (id, station_id, name, start_time, end_time, rate_per_kwh_idr, is_cross_midnight).
  - `oto_ev_sessions`: sesi pengisian (id, session_code, charger_id, vehicle_id, user_id, status: RESERVED, CHARGING, COMPLETED, CANCELLED, started_at, completed_at, deposit_held_idr, total_cost_idr, captured_at, refund_idr, final_soh_bps).
  - `oto_ev_meter_values`: pembacaan meteran energi (id, session_id, read_at, energy_wh, power_w, idempotency_key) — APPEND-ONLY.
  - `oto_ev_smart_charging_plans`: rencana pengisian Dinic (id, plan_uuid, station_id, time_slot, allocated_power_kw, certificate_json, status: VALID, INFEASIBLE).
  - `oto_fleet_contracts`: kontrak sewa B2B (id, contract_number, party_id, monthly_rental_idr, total_lease_value_idr, duration_months, start_date, end_date, max_downtime_hours_per_month, status: ACTIVE, TERMINATED).
  - `oto_fleet_contract_units`: unit kendaraan dalam kontrak (id, contract_id, vehicle_id, status: ASSIGNED, REPLACED, RETURNED, assigned_at).
  - `oto_fleet_invoices`: invoice sewa bulanan (id, invoice_number, contract_id, billing_period, rental_fee_idr, penalty_credit_idr, total_billed_idr, idempotency_key, status: ISSUED, PAID).
  - `oto_fleet_sla_breaches`: pencatatan pelanggaran SLA armada (id, breach_uuid, contract_id, unit_id, incident_date, downtime_hours, breach_type, penalty_bps, penalty_amount_idr, credited_invoice_id, status: RECORDED, CREDITED).
  - `oto_fleet_amortization_schedules`: jadwal amortisasi PSAK 73 (id, contract_id, period_month, principal_idr, interest_idr, payment_idr, ending_liability_idr, is_posted).
- **Register Minus:**
  - RM-OTO-1: V2G Dynamic Feed-in ke microgrid ditunda ke Pilar 10.
  - RM-OTO-2: Telematics-based dynamic insurance rating (UBI) ditunda ke Pilar 2.
- **Bukti untuk dipindahkan ke PROGRESS:**
  - Selesai implementasi MVP Telematics ingest tick append-only, deteksi anomali MAD 7 hari lokal, listener AutoServe booking.
  - Selesai alokasi daya Dinic Max-Flow EV bersertifikat min-cut, meter value append-only, hold/capture ledger.
  - Selesai penagihan B2B fleet leasing idempoten, kompensasi SLA, verifikasi amortisasi PSAK 73 $\sum$ persis.
  - Selesai command audit `tlm:audit`, `ev:audit`, `flt:audit`, dan konsolidasi `mob:audit` dengan 0 selisih.
- **Usulan DECISIONS:**
  - U-OTO-1: Penyatuan prefiks tabel `oto_` di bawah modul tunggal `Mobility` untuk menjaga integritas data lintas Telematics, EV, dan Fleet.
  - U-OTO-2: Seluruh satuan telemetri menggunakan integer tetap (deci-°C, mV, permil, meter) untuk mengeliminasi kesalahan float pada komputasi moneter dan evaluasi keselamatan.
  - U-OTO-3: Jendela tarif Time-Of-Use yang melintasi tengah malam diuraikan menjadi dua segmen kontigu sebelum alokasi konsumsi Wh berbasis Largest Remainder.
