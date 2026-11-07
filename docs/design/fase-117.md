# Dokumen Desain Teknis (Design Spec) — Fase 117: Guest Experience AI, Biometric Entry & Crowd Safety

**Modul Pemilik:** `Venue` (`modules/Venue/`, sub-item 117.1 s/d 117.7)  
**Linis Bisnis:** KONSEP §10F, Lini 15  
**Pelaksana:** Lead Cryptographer, Lead AI Systems Architect & Safety Engineer `[G7-P5]`  

---

## 1. Arsitektur Domain & Diagram Komponen

```
+-------------------------------------------------------------------------------------------------------+
|                                    MODUL VENUE & ENTERTAINMENT (FASE 117)                            |
+-------------------------------------------------------------------------------------------------------+
                                                   |
      +--------------------------------------------+--------------------------------------------+
      |                                            |                                            |
      v                                            v                                            v
[BIOMETRIC TURNSTILE ENTRY]              [CROWD SAFETY & HEATMAP]                   [GUEST EXPERIENCE & PROMO]
- BiometricCredential (Model)            - CrowdSafetyTelemetry (Model)             - PersonalizedPromo (Model)
- BiometricEnrolmentStateMachine         - CrowdCrushIncident (Model)               - ZoneWpsMetric (Model)
- BiometricLivenessMatcherEngine (V13)   - CrowdIncidentStateMachine                - WaitPerServiceGovernor (V15)
- BiometricPrivacyVaultService           - CrowdDensitySurgePredictor (V14)         - PersonalizedOfferRouter (V16)
- TurnstileDoorController                - CrowdCrushProtocolService                - GuestExperienceScoreService
- BiometricKioskSimulatorView            - InteractiveLiveHeatmapSvgView            - PersonalizedPromoBannerView
      |                                            |                                            |
      +--------------------------------------------+--------------------------------------------+
                                                   |
                                                   v
                                 [SAGA TERTUTUP DENGAN KOMPENSASI]
                                - CrowdEmergencyEvacuationSaga
                                   Step 1: Lock Inflow Turnstiles
                                   Step 2: Open Secondary Emergency Exits
                                   Step 3: Dispatch Crowd Safety & Medical Team
                                   Compensating: Re-open Gate, Recalibrate Occupancy
                                                   |
                                                   v
                                 [KONTRAK ADITIF LINTAS MODUL]
                                - G7-K23: MedicalEmergencyResponseDispatcher (Healthcare)
                                - G7-K24: VenueZoneSurveillanceFeedGateway (Security)
                                - G7-K25: MemberPersonalizationProfileProvider (Crm)
                                                   |
                                                   v
                                  [AUDIT COMMAND & CORRUPTION FIXTURE]
                                - Command: venue:audit-crowd
                                - Fixture: VenCrowdAuditCorruptionFixture
```

---

## 2. Rincian 4 Algoritma Bersertifikat (V13–V16)

### 2.1 Algoritma V13: `BiometricLivenessMatcherEngine`
- **Tujuan:** Verifikasi identitas biometrik wajah nir-sentuh di gerbang turnstile dengan perlindungan privasi zero-leakage.
- **Logika Matematis:**
  1. Input: Vektor fitur biometrik $\mathbf{v} \in \mathbb{R}^d$, tantangan liveness $c$, garam privasi $s$.
  2. Liveness Challenge Response:
     $$\text{Liveness Score} = \mathcal{H}_{HMAC}(c \parallel \text{Proof}, K_{\text{liveness}}) \pmod{10000}$$
     Jika $\text{Liveness Score} < 8.500$ bps (85%), entri ditolak (`LIVENESS_CHALLENGE_FAILED`).
  3. Non-Reversible Template Hash:
     $$\mathbf{h} = \text{HMAC-SHA256}(\mathbf{v} \parallel s, K_{\text{vault}})$$
  4. Template Comparison: Deterministic timing-safe string comparison hash $\mathbf{h}_{\text{probe}} \equiv \mathbf{h}_{\text{stored}}$.
- **Sertifikasi:** Mengeluarkan sertifikat HMAC-SHA256 berisi timestamp, gate ID, user ID, status verifikasi, dan hash template.

### 2.2 Algoritma V14: `CrowdDensitySurgePredictor`
- **Tujuan:** Prediksi lonjakan kepadatan zona 15 menit ke depan untuk mencegah crowd crush sebelum terjadi.
- **Logika Matematis:**
  1. Inflow Velocity: $V_{in} = \frac{\Delta H_{in}}{\Delta t}$ (orang/menit).
  2. Outflow Velocity: $V_{out} = \frac{\Delta H_{out}}{\Delta t}$ (orang/menit).
  3. Projected Headcount:
     $$\hat{H}_{t+15} = H_t + 15 \times \left( \lambda_{in} \cdot V_{in} - \lambda_{out} \cdot V_{out} \right) \times \gamma_{\text{weather}}$$
  4. Predicted Density:
     $$\hat{D}_{t+15} = \frac{\hat{H}_{t+15}}{\text{Area Zone } m^2}$$
  5. Action Matrix:
     - Jika $\hat{D}_{t+15} \ge 4.5 \text{ orang/m}^2 \lor \hat{H}_{t+15} \ge \text{Capacity}$: `RESTRICT_ACCESS_AND_OPEN_SECONDARY`.
     - Jika $\hat{D}_{t+15} \ge 3.5 \text{ orang/m}^2$: `SLOW_ENTRY_RATE`.
     - Jika $\hat{D}_{t+15} < 3.0 \text{ orang/m}^2$: `NORMAL_OPERATION`.

### 2.3 Algoritma V15: `WaitPerServiceGovernor`
- **Tujuan:** Optimalisasi kepuasan antrean bar dan F&B dengan model antrean M/M/c deterministik.
- **Logika Matematis:**
  1. Tingkat kedatangan pelanggan $\lambda$ (transaksi/menit).
  2. Tingkat layanan per kasir/bartender $\mu$ (transaksi/menit).
  3. Jumlah server aktif $c$.
  4. Utilisasi sistem $\rho = \frac{\lambda}{c \mu}$.
  5. Estimasi waktu tunggu rata-rata dalam antrean $W_q$.
  6. Skor WPS (0–100):
     $$\text{WPS} = \max\left(0, 100 - \left\lfloor \frac{W_q \times 100}{W_{\text{target}}} \right\rfloor \right)$$
  7. Jika $\text{WPS} < 60 \land \rho > 0.85$: Terbitkan sinyal `BAR_REINFORCEMENT_REQUIRED` untuk menambah shift kasir.

### 2.4 Algoritma V16: `PersonalizedOfferContextRouter`
- **Tujuan:** Penawaran eksklusif on-site untuk member VIP di lokasi tanpa mengganggu pengalaman tamu.
- **Logika Matematis:**
  1. Target Zone Matching: Lokasi zona tamu VIP $\equiv$ zona target promosi.
  2. Anti-Fatigue Filtering:
     $$\Delta t_{\text{last\_offer}} \ge 3600 \text{ detik} \land N_{\text{tonight}} < 3$$
  3. Margin Conservation Guardrail:
     $$\text{Final Price} = \text{Menu Price} \times (1 - \text{Discount Rate}) \ge \text{COGS Floor Price}$$
  4. Idempotency Envelope: Menghasilkan hash unik penawaran berbasis `(user_id, zone_id, event_id, hour_slot)`.

---

## 3. Desain State Machine Domain

### 3.1 `BiometricEnrolmentStateMachine`
- **Status:**
  1. `PENDING_CONSENT`: Tamu baru mendaftar, belum memberikan persetujuan privasi.
  2. `CONSENTED`: Persetujuan pemrosesan data biometrik terekam.
  3. `CAPTURE_IN_PROGRESS`: Proses pemindaian wajah di kamera/kios.
  4. `LIVENESS_VERIFIED`: Tes liveness anti-spoofing berhasil.
  5. `TEMPLATE_HASHED`: Fitur diekstraksi dan di-hash satu arah.
  6. `ENROLLED_ACTIVE`: Kredensial aktif dapat dipakai di gerbang.
  7. `REVOKED`: Kredensial dicabut atas permintaan tamu atau administrator.
- **Transisi Legal:**
  - `PENDING_CONSENT` $\to$ `CONSENTED`
  - `CONSENTED` $\to$ `CAPTURE_IN_PROGRESS`
  - `CAPTURE_IN_PROGRESS` $\to$ `LIVENESS_VERIFIED`
  - `LIVENESS_VERIFIED` $\to$ `TEMPLATE_HASHED`
  - `TEMPLATE_HASHED` $\to$ `ENROLLED_ACTIVE`
  - `ENROLLED_ACTIVE` $\to$ `REVOKED`

### 3.2 `CrowdIncidentStateMachine`
- **Status:**
  1. `NORMAL`: Kapasitas aman ($< 70\%$).
  2. `WARNING_SURGE`: Kepadatan meningkat mendekati $85\%$.
  3. `GATE_RESTRICTED`: Pintu utama ditutup sebagian, alur masuk diperlambat.
  4. `SECONDARY_DISPATCH`: Gerbang darurat/sekunder dibuka untuk mendistribusikan massa.
  5. `EVACUATION_STANDBY`: Kepadatan ekstrem ($> 95\%$), tim medis disiagakan.
  6. `RESOLVED_CLEAR`: Kepadatan normal kembali, insiden ditutup.

---

## 4. Desain Skema Basis Data

Tabel-tabel baru / diperluas dengan prefix `ven_`:
1. `ven_biometric_credentials`: Kolom `user_id`, `credential_code`, `biometric_template_hash`, `liveness_score_bps`, `status`, `sensor_model`, `consented_at`, `revoked_at`.
2. `ven_crowd_telemetries`: Kolom `event_id`, `zone_id`, `current_headcount`, `predicted_headcount_15m`, `density_sqm_bps`, `recommended_action`, `gate_restricted`, `created_at`.
3. `ven_crowd_incidents`: Kolom `event_id`, `zone_id`, `incident_code`, `severity_level`, `status`, `triggered_by`, `operator_notes`, `resolved_at`.
4. `ven_personalized_promos`: Kolom `user_id`, `event_id`, `zone_id`, `promo_dispatch_code`, `title`, `discount_bps`, `floor_price_idr`, `status`, `delivered_at`, `redeemed_at`.
5. `ven_zone_wps_metrics`: Kolom `event_id`, `zone_id`, `active_servers_count`, `arrival_rate_bps`, `service_rate_bps`, `wps_score`, `reinforcement_needed`, `recorded_at`.
6. `ven_gate_turnstiles`: Kolom `venue_id`, `zone_id`, `gate_code`, `gate_name`, `turnstile_type`, `is_emergency_exit`, `is_locked`, `total_scans_in`, `total_scans_out`.

---

## 5. Rencana Pengujian (Acceptance Tests $\le 5$)

1. `test_biometric_face_id_enrolment_liveness_and_one_way_hash_entry`: Menguji pendaftaran biometrik, pembuktian liveness, penyimpanan hash non-reversible, dan verifikasi entri gerbang sukses.
2. `test_crowd_density_surge_prediction_and_automatic_gate_restriction`: Menguji proyeksi kepadatan 15 menit, penutupan gerbang otomatis saat melebihi ambang batas, dan aktivasi gerbang sekunder.
3. `test_emergency_evacuation_saga_and_compensating_gate_release`: Menguji koordinasi saga darurat, pembukaan gerbang evakuasi, dan kompensasi pemulihan headcount.
4. `test_wait_per_service_scoring_and_bartender_reinforcement_signal`: Menguji kalkulasi WPS bar, deteksi antrean panjang, dan penerbitan sinyal bantuan shift.
5. `test_personalized_vip_offer_context_routing_and_anti_fatigue_guardrail`: Menguji penawaran VIP on-site, filter anti-fatigue (anti-spam), de-duplikasi idempotensi, dan batas lantai harga COGS.

