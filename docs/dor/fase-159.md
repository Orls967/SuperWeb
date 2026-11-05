# Definition of Ready (DoR) — Fase 159: Life, Health & Wellness Insurance Advanced

**Lini:** 18 (Asuransi Lanjutan)  
**Domain Modul:** `modules/Insurance/`  
**Zona Aditif:** `modules/Hospital/` (G7-K4: `EncounterBillReader`), `modules/Shared/Domain/Algorithms/LifeContingency/`  
**Prasyarat:** Fase 72 ✅, Fase 157 ✅, Fase 158 🔵

## 1. Lingkup & Sasaran
Membangun vertical slice terintegrasi untuk asuransi jiwa (term life, saving plans), proteksi kesehatan komprehensif (cashless hospital admission guarantee letter via integrasi RS), program wellness rewards anti-gaming, dan portofolio unit link dengan perhitungan NAV harian presisi (integer IDR minor unit).

## 2. Rincian Item (159.1 – 159.6)
1. **159.1 Term Life & Saving Plans**:
   - Polis asuransi jiwa berjangka dengan premi periodik, death benefit, tahapan tabungan (saving maturity).
   - Underwriting medis dan registrasi penerima manfaat (beneficiary management) berbasis referensi Party.
   - Klaim kematian dengan validasi berkas akta wafat dan review klaim empat mata.
2. **159.2 Health Insurance Full**:
   - Skema reimbursement vs cashless di rumah sakit rekanan.
   - Penerbitan Guarantee Letter (GL / Surat Jaminan) secara gapless melalui kontrak `EncounterBillReader`.
   - Adjudikasi klaim rumah sakit real-time dan mekanisme banding klaim (claim appeal workflow).
3. **159.3 Wellness Rewards & Anti-Gaming**:
   - Pencatatan aktivitas harian pengguna (langkah kaki, denyut jantung, tidur).
   - Aturan anti-gaming (deteksi lonjakan tidak wajar > 50.000 langkah/hari atau duplikasi timestamp).
   - Konversi skor kesehatan menjadi diskon premi atau poin reward loyalitas.
4. **159.4 Unit Link Portfolio (Simulasi)**:
   - Alokasi premi nasabah ke unit dana investasi (Equity Fund, Fixed Income, Money Market).
   - Perhitungan Nilai Aktiva Bersih (NAV) harian berbasis unit pricing minor unit.
   - Rekonsiliasi saldo unit vs unit balance di ledger (`ins:unit_link_holdings:IDR`).
5. **159.5 Integrasi Kontrak Rumah Sakit (G7-K4)**:
   - Kontrak `Modules\Hospital\Contracts\EncounterBillReader` membaca tagihan rawat inap dan memvalidasi eligibility limit.
6. **159.6 Tests & Audit**:
   - Algoritma A7: `Modules\Shared\Domain\Algorithms\LifeContingency\TermLifePremium`.
   - Audit dua-sumber: `php artisan ins:life-health-audit` & `LifeHealthAuditCorruptionFixture`.

## 3. Ambang Kode Produksi
- Target: ≥ 4.000 baris kode produksi substantif tanpa isian (K-4).
- Acceptance tests: ≤ 5 tests per fase di `tests/Acceptance/InsLifeHealth/`.

