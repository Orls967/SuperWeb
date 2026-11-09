# Definition of Ready (DoR) — Fase 237: Kualitas Kode, Metrik Fase & Platform DX

## 1. Metadata & Identitas Fase
- **ID Fase:** 237
- **Pilar / Modul:** Platform & Developer Experience (`Platform`, prefiks `plt_`)
- **Status:** 🔵 DIKERJAKAN
- **Prasyarat:** Trunk master di `00b92adb` (semua PR G7-P7 & G7-P8 merged) ✅
- **Mandat Khusus:**
  - IZIN-1: Modul baru `Platform` dengan prefiks tabel `plt_` telah disetujui.
  - IZIN-2: Generator dokumentasi D1 murni native PHP (`app/Quality/Docs/Render/`), tanpa Python/Chromium.
  - IZIN-3: Modul lama `DeveloperExperienceQualityService` dan `ReleaseTrainDeploymentSafetyService` di `Modules/Integration` diberi anotasi `@deprecated` dan dicatat di `docs/catalog/integration-retirement.md`.
  - T-9: Batasan aditif Core maksimal 40 baris (CorrelationId middleware & logging context).
  - T-10: Suite metrik fase wajib menangkap 3 fixture padding sengaja di `tests/Fixtures/Quality/Padding/`.

---

## 2. Rencana Anggaran Baris (RAB) — Standar W8 T-2
Target Keras B1: **≥ 7.500 baris logika non-test/non-blade/non-database**.
Target Keras B3: **≥ 1.500 baris dokumentasi teknis**.

| Lapisan Arsitektur | Rencana B1 | Rincian Komponen Utama |
|---|---|---|
| **Algoritma Bersertifikat & Value Objects** | 1.350 | A1 `DuplicateWindowDetector` (Rabin-Karp Horner basis 257 mod P), A2 `DecisionDensityCounter` (AST token analysis, cyclomatic weight), A3 `ClassReachabilityGraph` (dependency edge builder, BFS/Tarjan reachability, orphan detector), Certificate models & Verifiers |
| **State Machine & Enums** | 780 | `SandboxEnvironmentStateMachine` (7 status: requested, provisioning, seeded, ready, expiring, expired, destroyed), Guard policies, State enums (`SandboxStatus`, `SandboxTier`, `MetricCategory`, `SloTier`, `PostmortemSeverity`) |
| **Aplikasi (Actions, Services & Analyzers)** | 2.750 | Sandbox lifecycle engine, Data synthesis & masking actions, Quality metrics engine (Ratchet evaluator, anti-padding analyzer M2–M5), SLO & Error budget calculator, Correlation tracer |
| **Dokumentasi SVG Render & Bridges** | 880 | Pure PHP D1 vector SVG generation (`SvgCanvas`, `MermaidToSvgCompiler`, `ArchitectureDiagramRenderer`), Integration legacy deprecated wrappers |
| **HTTP (Controllers, Requests, Policies, ViewModels)** | 1.050 | Web controllers (`/platform/dev`), anti-IDOR policies, strict FormRequests, ViewModels |
| **Console Commands & Audit Dua Sumber** | 980 | 13 Commands: `quality:phase-metrics`, `quality:coverage-gap`, `quality:pipeline-status`, `docs:export`, `docs:generate`, `docs:check`, `docs:drift`, `platform:sandbox-expire`, `platform:synth-data`, `platform:mask-verify`, `platform:anonymize`, `platform:error-budget`, `platform:audit` |
| **Domain Models & Observers** | 460 | 8 Eloquent Models ber-prefix `plt_`, transition observers, audit trails |
| **Total Rencana B1** | **8.250** | **Ambang batas minimal: 7.500 baris (Margin aman: +750 baris)** |

---

## 3. Skema Database (Prefix `plt_`)
1. `plt_sandboxes`: Data environment sandbox (name, slug, tier, status, owner_id, expires_at, metadata).
2. `plt_sandbox_transitions`: Log append-only riwayat transisi status sandbox.
3. `plt_phase_metrics`: Snapshot metrik fase hasil analisa `quality:phase-metrics` (phase_id, loc_total, b1_loc, b2_loc, b3_loc, decision_density, duplicate_ratio, orphan_count, status).
4. `plt_service_slos`: Definisi target SLO per service (service_name, target_sli_bps, error_budget_mins, current_burn_rate).
5. `plt_postmortems`: Catatan postmortem insiden keandalan (incident_ref, root_cause, action_items, severity).
6. `plt_synth_datasets`: Metadata synthetic dataset yang digenerate untuk pengujian/sandbox.
7. `plt_mask_rules`: Aturan masking PII per kolom dan domain.
8. `plt_audit_runs`: Hasil rekonsiliasi audit dua sumber platform.

---

## 4. Algoritma Bersertifikat (A1, A2, A3)
1. **A1 `DuplicateWindowDetector`**:
   - Algoritma Rabin-Karp berbasis rolling polynomial hash: $H(s) = \sum_{i=0}^{k-1} c_i \cdot 257^{k-1-i} \pmod P$.
   - Normalisasi token: whitespace strip, identifier/variable anonymization, comment discard.
   - Deteksi window $W \in [20, 50]$ token dengan deteksi tabrakan aman (collision-safe token verification).
2. **A2 `DecisionDensityCounter`**:
   - Token stream scanner untuk mendeteksi percabangan keputusan (`T_IF`, `T_ELSEIF`, `T_SWITCH`, `T_CASE`, `T_CATCH`, operator `&&`, `||`, `??`).
   - Rasio kepadatan keputusan: $\text{Density} = \frac{\text{DecisionPoints}}{\text{ExecutableLOC}}$.
3. **A3 `ClassReachabilityGraph`**:
   - Parsing deklarasi kelas dan dependensi imports/uses/type-hints.
   - Konstruksi directed graph $G = (V, E)$.
   - BFS/DFS traversal dari root set (Controllers, Commands, Event Listeners, Service Providers) untuk menemukan kelas tak terjangkau (orphan/unreachable classes M-5).

---

## 5. Kepatuhan Standar W8
- Tidak ada database ledger domain / posting akuntansi (platform infrastructure).
- Strict types di semua file PHP baru.
- Penambahan 1 allowlist entry pada `config-allowlists.json` dengan anchor keputusan yang valid.
- Verifikasi T-10 terhadap 3 fixture padding sengaja di `tests/Fixtures/Quality/Padding/`.

