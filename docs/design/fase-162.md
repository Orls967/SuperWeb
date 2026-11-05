# Desain & Bukti Kondisi Lama — Fase 162 (Sukuk, Ijarah & Wealth Syariah)

## L.0 Bukti kondisi lama (diukur 2026-11-06 pada master `aa942f54`)

| Aspek | Temuan | Penilaian |
|---|---|---|
| Tabel `syb_*` modul Syariah | `syb_murabahah_financings`, `syb_murabahah_schedules`, `syb_tazir_penalties` | ada (Fase 161); belum ada sukuk, ijarah, wealth, zakat, wakaf, parameter berversi |
| Sukuk & SPV (162.1) | `Integration\SukukAndZakatService::issueSukuk` (DB raw, mock table, tanpa token RWA, kupon float) | **salah** (X2, X21, U-1, U-13); tidak ada token RWA, tidak ada SPV, tidak ada pembagian LargestRemainder |
| Ijarah & IMBT (162.2) | `Integration\SukukAndZakatService::createIjarahContract` (float, tanpa penyusutan garis lurus, opsi IMBT tanpa relasi nilai buku) | **salah**; tidak sesuai algoritma B1 dan fatwa DSN-MUI |
| Wealth & Screening (162.3) | `Integration\SukukAndZakatService::screenSecurity` (hanya boolean flag, tanpa robo-advisor, tanpa purifikasi dividen) | **salah**; tidak ada katalog instrumen, alokasi profil risiko, dan purifikasi |
| Zakat & 8 Asnaf (162.4) | `Integration\SukukAndZakatService::calculateZakatMal` (float, tanpa haul hijriah, tanpa pembagian 8 asnaf Hamilton B3, tanpa sertifikat gapless) | **salah**; tidak memenuhi syarat zakat mal syariah |
| Wakaf (162.5) | Belum ada di kode produksi maupun Integration | **tidak ada** |
| Kontrak G7-K5 | `Modules\Rwa\Contracts\SukukTokenRegistry` | **tidak ada**; wajib dibuat aditif di `Modules\Rwa` |
| Algoritma B1–B4 | `Islamic\IjarahSchedule`, `Islamic\ZakatAssessment`, `Islamic\AsnafDistribution`, `Islamic\ShariahScreening` | **tidak ada**; wajib dibuat di `Shared/Domain/Algorithms/Islamic/` |
| Rute & UI | Rute hanya murabahah dan review DPS murabahah | **tidak ada** rute sukuk, ijarah, wealth, zakat, wakaf |
| Akun ledger syariah | `syb:murabahah_asset`, `syb:margin_income`, `syb:qardhul_hasan_fund`, `syb:wadiah_liquidity_source` | belum ada akun ijarah, sukuk SPV, zakat fund, charity fund, wakaf principal |

Bukti perintah: `git ls-files modules/Syariah` (hanya komponen Murabahah); `php artisan arch:scan` bersih; `vendor/bin/pest tests/Acceptance/Syariah/` (6 passed).

---

## L.1 Desain Arsitektur & Spesifikasi

### Modul & Prefiks
Modul pemilik `Modules\Syariah` dengan prefiks tabel `syb_` (KONSEP §A1, `config/modules.php`).
Tabel produksi baru menggunakan nama bersih agar tidak bentrok dengan tabel kerangka modul Integration (frozen):
- `syb_parameters` ▣: parameter syariah berversi (S1, S2, gold price, nisab, ratio screening DAFT, batas amil, ujrah fee).
- `syb_sukuk_facilities`: fasilitas penerbitan sukuk beraset dasar riil dengan SPV dan token RWA G7-K5.
- `syb_sukuk_distributions` ▣: catatan distribusi ujrah/kupon periodik ke pemegang token (LargestRemainder).
- `syb_ijarah_facilities`: akad ijarah sewa murni dan IMBT (Lease-to-Own) beraset riil terdaftar.
- `syb_ijarah_facility_schedules`: jadwal sewa ujrah dan amortisasi penyusutan garis lurus bulanan.
- `syb_wealth_instruments`: katalog instrumen investasi syariah (sukuk, reksa dana, emas fisik).
- `syb_wealth_screenings` ▣: catatan hasil audit screening syariah B4 (utang riba <= 4500 bps, non-halal <= 1000 bps).
- `syb_wealth_portfolios`: portofolio nasabah wealth syariah berbasis profil risiko robo-advisor.
- `syb_wealth_orders`: transaksi beli/jual instrumen wealth syariah dengan gerbang screening wajib sebelum eksekusi.
- `syb_zakat_assessments`: penilaian zakat mal per muzakki per haul (B2, unique muzakki + haul).
- `syb_zakat_distributions` ▣: penyaluran dana zakat ke 8 asnaf (B3, amil <= 1250 bps).
- `syb_waqf_principals`: penerimaan wakaf uang (abadi mudharabah) dan wakaf aset.
- `syb_waqf_distributions` ▣: penyaluran manfaat wakaf ke mauquf alaih terdaftar.
- `syb_gapless_certificates` ▣: sertifikat resmi gapless untuk zakat dan wakif via `DocumentNumberingInterface`.
- `syb_status_transitions` ▣: audit log transisi status state machine append-only.

### State Machine Eksplisit (≥ 6 Status)
1. **Sukuk Facility Lifecycle**:
   `structuring` -> `dps_review` -> `approved` -> `issued` -> `distributing` -> `matured` -> `redeemed` (atau `defaulted`) (8 status).
2. **Ijarah Facility Lifecycle**:
   `draft` -> `dps_review` -> `approved` -> `active` -> `option_exercised` -> `ownership_transferred` (atau `defaulted`, `terminated`) (8 status).

### Akun Buku Besar & Arah Posting (D162-6)
Semua transaksi diposting double-entry presisi integer Rupiah tanpa akun bunga:
| Kejadian | Debit | Kredit | Keterangan |
|---|---|---|---|
| Ujrah ijarah ditagih | `syb:ijarah_receivable:{party}:IDR` | `syb:ijarah_income:IDR` | Tagihan sewa berjalan |
| Penyusutan aset ijarah | `syb:ijarah_depreciation_expense:IDR` | `syb:ijarah_accum_depreciation:IDR` | Amortisasi aset ijarah |
| Distribusi sukuk | `syb:sukuk_spv_cash:IDR` | `wallet:user:{holder}:IDR` | Kupon/ujrah periodik ke holder |
| Zakat dipungut | `wallet:user:{muzakki}:IDR` | `syb:zakat_fund:IDR` | Dana zakat bukan pendapatan |
| Zakat disalurkan | `syb:zakat_fund:IDR` | `wallet:user:{mustahik}:IDR` | Penyaluran ke 8 asnaf |
| Purifikasi dividen | `wallet:user:{investor}:IDR` | `syb:charity_fund:IDR` | Disgorgement non-halal |
| Wakaf uang diterima | `wallet:user:{wakif}:IDR` | `syb:waqf_principal:IDR` | Pokok abadi wakaf |

### Algoritma Inti (Shared/Domain/Algorithms/Islamic/)
- **B1 `Islamic\IjarahSchedule`**: perhitungan ujrah, penyusutan garis lurus, sisa nilai buku, dan eksekusi opsi IMBT.
  Vektor prompt: Biaya 120.000.000, 12 bulan, total ujrah 132.000.000 -> ujrah 11.000.000/bln, penyusutan 10.000.000/bln, opsi IMBT 1.000.000 -> setelah eksekusi nilai buku 0.
- **B2 `Islamic\ZakatAssessment`**: nisab = 85g emas x harga emas, basis kena zakat = harta - utang, zakat = 2,5% jika basis >= nisab dan haul 354 hari terpenuhi.
  Vektor prompt: Emas 1.500.000/g -> nisab 127.500.000. Harta 200m, utang 20m -> zakat 4.500.000. Harta 130m, utang 10m -> zakat 0 (di bawah nisab).
- **B3 `Islamic\AsnafDistribution`**: alokasi dana zakat ke 8 asnaf dengan LargestRemainder (bobot sum 10000, amil <= 1250 bps).
  Vektor prompt: Dana 1.000.003, bobot [2500, 2500, 1250, 500, 250, 1000, 1000, 1000] -> [250.001, 250.001, 125.001, 50.000, 25.000, 100.000, 100.000, 100.000].
- **B4 `Islamic\ShariahScreening`**: screening instrumen ekuitas/obligasi (utang riba <= 4500 bps, pendapatan non-halal <= 1000 bps) & purifikasi dividen.
  Vektor prompt: Utang 400/1000 = 4000 bps LOLOS; Non-halal 120/1000 = 1200 bps GAGAL; Dividen 1.000.000 rasio non-halal 800 bps -> purifikasi 80.000 ke dana kebajikan.

### Kontrak Baru (Katalog G7)
- **G7-K5 `Modules\Rwa\Contracts\SukukTokenRegistry`**:
  - `issue(string $assetCode, int $faceValueIdr, int $units, CarbonImmutable $maturity, string $idempotencyKey): int`
  - `redeem(string $assetCode, CarbonImmutable $at, string $idempotencyKey): void`
  Dibuat aditif di `Modules\Rwa` bersama implementasi `RwaSukukTokenRegistryService` dan binding di `RwaServiceProvider`.
