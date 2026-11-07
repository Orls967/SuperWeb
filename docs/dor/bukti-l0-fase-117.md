# Bukti Kondisi Awal (L.0) — Fase 117: Guest Experience AI, Biometric Entry & Crowd Safety

**Domain:** Venue & Entertainment (`modules/Venue/`, sub-item 117.1 s/d 117.7)  
**Worktree:** `sw-g7-p5`  
**Base Commit:** `92e39cddf08d73e699cc671748a79fc8f05a458f` (Merge PR #88 Fase 116)  
**Waktu Pengambilan:** 2026-11-08  
**Pelaksana:** Lead Cryptographer, Lead AI Systems Architect & Safety Engineer `[G7-P5]`  

---

## 1. Verifikasi Status Trunk & Baseline Awal

Sebelum memulai penulisan kode pada Fase 117, kondisi git trunk dan status pengujian diverifikasi:
1. **Master Trunk:** Bersih pada commit `92e39cddf08d73e699cc671748a79fc8f05a458f` (PR #86 Fase 89, PR #87 Fase 90, dan PR #88 Fase 116 telah merged penuh).
2. **Architecture Scan (`php artisan arch:scan`):** 0 Pelanggaran Baru, 0 Entri Basi (Aturan A1–A13 bersih tanpa celah).
3. **Pest Architecture Test Suite:** 100% HIJAU (SpecsAndDiagrams, RouteAuthorizationMatrix, AuditCommandContract).
4. **CI Master:** Seluruh workflow runs berstatus `success` / `skipped` (Pest mutation test skipped normal).

---

## 2. Inventaris Aset Awal Domain Fase 117

Berdasarkan audit forensik, Fase 117 berstatus **KERANGKA** dengan komponen awal:

| Komponen | Status Awal | Catatan Analisis & Rencana Perluasan |
|---|---|---|
| **Tabel `ven_biometric_credentials`** | Kerangka dasar | Menyimpan credential code, template hash, liveness proof; perluasan field consent token, sensor model, revocability timestamp. |
| **Tabel `ven_crowd_telemetries`** | Kerangka dasar | Menyimpan headcount, predicted 15m, recommended action; perluasan density per meter persegi, pressure index, gate restriction flag. |
| **Tabel `ven_crowd_incidents`** | Kerangka dasar | Menyimpan incident code, zone id, severity, response log; perluasan evakuasi standby, dispatch log, operator confirmation. |
| **Tabel Personalized Offers** | Kerangka dasar | Perluasan anti-fatigue cooldown, location geofence, conversion rate tracking, and ledger margin hook. |
| **Algoritma Bersertifikat (V13–V16)** | Belum bersertifikat | Wajib dibuat bersertifikat HMAC-SHA256: `BiometricLivenessMatcherEngine` (V13), `CrowdDensitySurgePredictor` (V14), `WaitPerServiceGovernor` (V15), `PersonalizedOfferContextRouter` (V16). |
| **State Machine Domain** | Belum ada | Wajib dibuat: `BiometricEnrolmentStateMachine` (7 status), `CrowdIncidentStateMachine` (6 status). |
| **Kontrak Aditif Lintas Modul** | Belum ada | Wajib dibuat: G7-K23 (`MedicalEmergencyResponseDispatcher`), G7-K24 (`VenueZoneSurveillanceFeedGateway`), G7-K25 (`MemberPersonalizationProfileProvider`). |
| **Saga Terkoordinasi** | Belum ada | Wajib dibuat: `CrowdEmergencyEvacuationSaga` dengan kompensasi otomatis pembukaan gate dan pemulihan headcount. |
| **Tampilan UI Blade (P-UI 1–10)** | Belum ada | Wajib dibuat: Live Interactive Crowd Heatmap SVG (375px), Biometric Scanner Kiosk Simulator, WPS & Bar Queue Metrics Dashboard, Incident Dispatch Console, On-site VIP Promo Banner. |
| **Acceptance Tests (`tests/Acceptance/VenueCrowd`)** | Belum ada | Wajib dibuat di L.2 ($\le 5$ test terpadu mencakup sub-item 117.1–117.6). |
| **Command Audit & Fixture** | Belum ada | Wajib dibuat: `venue:audit-crowd` dan `VenCrowdAuditCorruptionFixture` terdaftar di `AuditCommandRegistry`. |

---

## 3. Komitmen Ambang Keras W8 Fase 117

Sesuai standar W8:
- **B1 (Domain Logic):** $\ge 6.000$ baris logika bersih (services, actions, sagas, algorithms, state machines, DTOs, value objects).
- **B2 (UI Blade):** $\ge 35\% \times B1$ ($\ge 2.100$ baris Blade) tanpa isian kosong.
- **B3 (Dokumentasi Teknis):** $\ge 1.500$ baris tulisan tangan di `docs/`.
- **Status Akhir:** Menyala `[🔵]` di `docs/PROGRESS.md` setelah PR #89 merged ke master.

