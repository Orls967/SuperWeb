# Definition of Ready (DoR) — Fase 188: Tata Kelola Identitas, Akses & Multi-Tenancy 30 Lini

**Status:** APPROVED (Disetujui untuk Eksekusi Solo)  
**Referensi Rencana:** `rencana-gelombang-8-25.md` (§W8-P4.3)  
**Baseline SHA:** `a2d3b634984fa35e5e8c5bf100a57314c78f0b5f`

---

## 1. Prasyarat & Lingkup Pekerjaan

### A. Prasyarat Sistem
1. Fase R4 (PR #112) dan Fase 55 (PR #113) telah merged penuh ke branch `master`.
2. Seluruh check-runs CI master hijau tanpa kegagalan.
3. Nol PR aktif di repository (Solo execution mode).

### B. Lingkup Fungsional (V1 – V12)
1. **Model ABAC Murni Tanpa I/O (`DenyOverridesEvaluator` - C1):**
   - Evaluasi murni berbasis memori dengan multi-dimensional scoping (`entity_id`, `region_id`, `site_id`, `project_id`, `valid_from` s.d. `valid_to`).
   - Strategi mutlak *Deny-Overrides* berformat fail-closed.
2. **Graf Klaster Identitas Multi-Pihak (`WeightedIdentityLinker` - C2):**
   - Penautan berbobot: NIK Hash (100), Email Hash (60), Phone Hash (50), Nama+DOB Hash (40) dengan ambang $\ge 90$.
   - Penegakan izin ganda (*double consent*) dengan status `BLOCKED_NO_CONSENT`.
3. **Agregator Eksposur Vendor Terpadu (`VendorExposureAggregator` - C3):**
   - Konsolidasi liabilitas vendor lintas lini bisnis (PO terbuka, un-invoiced GR, deposit, warranty claims).
   - Seluruh perhitungan menggunakan integer minor unit IDR (bebas float IEEE 754).
4. **State Machine Siklus Hidup Access Grant (8 Status):**
   - `requested` $\to$ `policy_checked` $\to$ `approved` $\to$ `provisioned` $\to$ `active` $\to$ `suspended` $\to$ `revoked` $\to$ `expired`.
   - Riwayat transisi imutabel `CoreAccessGrantTransition`.
   - Pencabutan sub-detik efektif seketika melalui stempel invalidasi izin.
5. **Kampanye Re-Sertifikasi Akses Periodik (`access:recert-campaign`):**
   - Pelaksanaan audit hak akses manajerial dengan tenggat waktu otomatis.
   - Hak akses yang tidak dikonfirmasi sebelum batas waktu otomatis dicabut (`expired`/`revoked`).
6. **Probe Harian Isolasi Multi-Tenant Otomatis (`access:tenant-probe`):**
   - Pengujian random probe lintas tenant atas model-model terdaftar; kegagalan isolasi memicu `403`.
7. **Perintah Audit Dua-Sumber (`access:audit`):**
   - Membandingkan status grant aktif dan aturan kebijakan terhadap integritas kriptografi SHA-256.
   - Fixture sabotase `AccessAuditCorruptionFixture` mendeteksi anomali dengan exit code `1`.
8. **Antarmuka Pengguna Tata Kelola Akses (B2):**
   - 4 tampilan manajerial: `admin.access.grants`, `admin.access.policies`, `admin.access.identity`, `admin.access.campaigns`.
   - Menggunakan 31 komponen `x-ds.*`, responsif mobile 375px dan desktop 1280px, 0 `ui:lint` violations.

---

## 2. Kriteria Penerimaan Teknis & Metrik Gate

| Parameter Metrik | Ambang Batas Wajib | Cara Pengukuran |
|---|---|---|
| **B1 PHP Logic Additions** | $\ge 7,000$ baris | `git diff --numstat` pada `modules/` + `app/` (tanpa test, blade, migrasi) |
| **B3 Handwritten Docs** | $\ge 1,500$ baris | `git diff --numstat` pada `docs/**/*.md` (tanpa autogen openapi/codebase) |
| **B2 UI Blade Lines** | $\ge 500$ baris | Komponen desain sistem terdaftar, 0 `ui:lint` |
| **Anti-Padding M-2** | $\ge 55\%$ | Decision density AST |
| **Anti-Padding M-3** | $\le 20\%$ | Anemic DTO ratio |
| **Anti-Padding M-4** | $= 0$ | Duplikasi blok token |
| **Anti-Padding M-5** | $= 0$ | Kelas yatim tanpa jalur pemanggilan |
| **Pencatatan Buku Besar (U-3)** | Tepat 0 transaksi | RAB = 0 transaksi ke `LedgerService` |
| **Aturan Arsitektur** | Bersih (0 baru) | `php artisan arch:scan` A1=0, A2=15, A9=0, A12=300 |

---

## 3. Delapan Keputusan Desain (ADR W8-D53 s/d W8-D60)

1. **W8-D53:** Evaluator Otorisasi ABAC Murni Memori Deny-Overrides
2. **W8-D54:** Multi-Dimensional Scoping (Entity, Region, Site, Project, Waktu)
3. **W8-D55:** Siklus Hidup Access Grant 8 Status & Pencabutan Sub-Detik
4. **W8-D56:** Graf Klaster Identitas Multi-Pihak Berbobot & Persetujuan Ganda
5. **W8-D57:** Agregasi Eksposur Kredit Grup Vendor Lintas Lini
6. **W8-D58:** Probe Otomatis Harian Isolasi Multi-Tenant
7. **W8-D59:** Kampanye Re-Sertifikasi Akses Periodik dengan Auto-Revocation
8. **W8-D60:** Audit Akses & Identitas Dua-Sumber dengan Fixture Korupsi Terdaftar

