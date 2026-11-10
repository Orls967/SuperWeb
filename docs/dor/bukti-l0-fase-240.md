# Bukti Kondisi Awal L.0 — Fase 240: Experience Design System & Accessibility

**Fase:** Fase 240 (Gelombang 8, Prompt W8-P2.1)  
**Tanggal:** 2026-11-10  
**Sha Awal Fase:** `3bd1e38e31528ebe52f35c723418632d53e81685`  
**Modul Pemilik:** `Core` (tabel database prefiks `core_`) & `Shared` (komponen Blade `x-ds.*`, view-models, algoritma, konsol)  

---

## 1. Inventaris Faktual Kondisi Awal (Tabel Status: Ada / Benar / Salah / Tidak Ada)

| Artefak / Komponen | Status Faktual | Bukti Lokasi / Temuan | Tindakan Sesuai Protokol |
|---|---|---|---|
| Modul `Integration` Service: `DesignSystemAccessibilityService` | Ada (Salah / Kerangka Lama) | `modules/Integration/Application/Services/DesignSystemAccessibilityService.php` | Diberi tag PHPDoc `@deprecated`. Dilarang diperluas, disalin, atau dihapus (X2, X21, IZIN-3). |
| Tabel `platform_design_tokens`, `platform_ui_components`, dll. | Ada (Salah / Skema Lama di Integration) | `modules/Integration/database/migrations/2026_10_08_240001_create_design_system_accessibility_tables.php` | Dibekukan. Tabel produksi baru dibuat di modul `Core` dengan prefiks `core_ds_*` dan `core_ux_*`. |
| Model Eloquent Design System | Tidak Ada | Belum ada model Eloquent untuk token, komponen, temuan a11y, dan riset UX di `modules/Core/Domain/Models` | Dibuat di `Modules\Core\Domain\Models`: `DsToken`, `DsComponent`, `DsComponentHistory`, `DsA11yFinding`, `DsComponentUsage`, `DsUserPref`, `UxFinding`, `UxIteration`. |
| Action Bisnis Siklus Hidup Komponen | Tidak Ada | Belum ada Action untuk transisi State Machine, registrasi token, pemindaian pemakaian | Dibuat di `Modules\Core\Application\Actions\DesignSystem` dan `Modules\Shared\Application\Actions`. |
| Komponen Blade `x-ds.*` | Tidak Ada | Namespace anonim `ds` belum terdaftar di `SharedServiceProvider`. Komponen yang ada berupa `x-shared.*` dan `x-enterprise.*`. | Daftarkan `Blade::anonymousComponentPath` untuk `ds` di `SharedServiceProvider`. Bangun $\ge 25$ komponen kanonikal di `modules/Shared/resources/views/components/ds/`. |
| Komponen Legacy `x-shared.*` & `x-enterprise.*` | Ada (Benar) | `modules/Shared/resources/views/components/` (badge, card, empty-state, modal, money, stat, table, toast) dan `resources/views/components/enterprise/` (command-palette, svg-kpi-chart, export-toolbar) | Dipertahankan API-nya utuh (props/slots tidak berubah). Dijadikan pembungkus tipis (*thin wrapper*) ke komponen `x-ds.*` baru. |
| Layout Utama `resources/views/layouts/app.blade.php` | Ada (Monolitik 439 baris) | `resources/views/layouts/app.blade.php` memuat modal pencarian Ctrl+K dan navigasi monolitik. | Dipecah dan dimigrasikan menjadi `x-ds.shell`, `x-ds.sidebar`, `x-ds.topbar`, `x-ds.command-palette` tanpa mengubah perilaku aplikasi. |
| Algoritma Bersertifikat A1 (`WcagContrastCalculator`) | Tidak Ada | Belum ada kalkulator kontras WCAG berbasis linearisasi sRGB bulat (ppm & bps). | Dibuat di `Modules\Shared\Domain\Algorithms\Ui\WcagContrastCalculator` dengan sertifikat verifier. |
| Algoritma Bersertifikat A2 (`ThemeTokenResolver`) | Tidak Ada | Belum ada resolver token tema pewarisan (`hc` $\leftarrow$ `dark` $\leftarrow$ `light`) dan alias resolusi. | Dibuat di `Modules\Shared\Domain\Algorithms\Ui\ThemeTokenResolver` dengan sertifikat verifier. |
| Algoritma Bersertifikat A3 (`A11yHtmlLinter`) | Tidak Ada | Belum ada linter DOM statis untuk aturan aksesibilitas (IMG_ALT, INPUT_LABEL, BUTTON_NAME, LINK_NAME, TABINDEX_POSITIVE). | Dibuat di `Modules\Shared\Domain\Algorithms\Ui\A11yHtmlLinter` dengan sertifikat verifier. |
| Console Command `ui:lint` | Tidak Ada | Belum ada linter UI untuk validasi Blade, kontras token, aturan WCAG, dan baseline ratchet. | Dibuat di `Modules\Shared\Console\Commands\UiLintCommand`. |
| Console Command `ds:tokens-build` | Tidak Ada | Belum ada generator CSS variabel token tema dari basis data. | Dibuat di `Modules\Shared\Console\Commands\DsTokensBuildCommand`. |
| Console Command `ds:usage-scan` | Tidak Ada | Belum ada pemindai pemakaian komponen pada view Blade aplikasi. | Dibuat di `Modules\Shared\Console\Commands\DsUsageScanCommand`. |
| Console Command `ds:audit` | Tidak Ada | Belum ada audit dua sumber integritas status komponen vs riwayat dan kepatuhan kontras. | Dibuat di `Modules\Shared\Console\Commands\DsAuditCommand`, didaftarkan ke `AuditCommandRegistry`. |
| Rute `/design-system*` | Tidak Ada | Belum ada rute untuk katalog komponen, rilis tata kelola, dan papan riset UX. | Didaftarkan di `modules/Core/routes/web.php` dan `tests/Architecture/route-roles.php`. |
| Acceptance Tests | Tidak Ada | Belum ada suite acceptance test untuk tata kelola Design System & Aksesibilitas. | Dibuat maksimal 5 test di `tests/Acceptance/DesignSystem/DesignSystemAcceptanceTest.php`. |

---

## 2. Rujukan Sumber Standar Aksesibilitas (WCAG 2.1)
- **W3C Web Content Accessibility Guidelines (WCAG) 2.1**:
  - Success Criterion 1.4.3 Contrast (Minimum) (Level AA): Rasio kontras teks normal minimal 4.5:1 (45.000 bps) dan teks besar minimal 3:1 (30.000 bps).
  - Success Criterion 1.4.6 Contrast (Enhanced) (Level AAA): Rasio kontras teks normal minimal 7:1 (70.000 bps).
  - Success Criterion 1.4.11 Non-text Contrast (Level AA): Rasio kontras komponen antarmuka pengguna (UI components) minimal 3:1 (30.000 bps).
  - Success Criterion 2.4.7 Focus Visible (Level AA): Indikator fokus keyboard terlihat jelas (`focus-visible`).
  - Definisi Luminansi Relatif ($L$): $L = 0.2126 \times R + 0.7152 \times G + 0.0722 \times B$ di mana kanal warna disesuaikan dengan kurva gamma sRGB $c \le 0.04045 \implies c/12.92$ atau $((c+0.055)/1.055)^{2.4}$.
- **Tanggal Akses Verifikasi Sumber:** 2026-11-10.
- **Implementasi Matematis:** Semua perhitungan rasio ditransformasikan ke aritmetika integer presisi parts-per-million (ppm) dan basis-points (bps) untuk menjamin determinisme penuh di seluruh platform komputasi.
