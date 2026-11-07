# Definition of Ready (DoR) — Fase 94: Weighbridge, Grade Reconciliation, Royalty, HSE & Offtake

**Fase**: 94 (`[G7-P4.2]`)  
**Modul Pemilik**: `Modules\Mining` (`min_`), Aditif di `Modules\Shared\Domain\Algorithms\Metallurgy`, `Modules\TradeFinance`, dan `Modules\Manufacturing`  
**Ambang Keras B1 (T-1)**: $\ge 7.000$ baris logika PHP bersih (kelas Lebar: 8 sub-item 94.1–94.8 + perluasan wajib)  
**Ambang Dokumentasi B3 (T-1)**: $\ge 1.500$ baris dokumentasi teknis tulis-tangan  
**Acuan**: `docs/KONSEP.md` §A0–§A17, §12F (baris 1200–1250), `docs/PROGRESS.md` Fase 94 (baris 9735–9834)

---

## 1. Kutipan Referensi KONSEP & PROGRESS

1. **`docs/KONSEP.md` §12F (baris 1205–1245)**:
   - Menetapkan kewajiban integrasi timbangan jembatan hash-chain (`CanonicalHashChain`), rekonsiliasi neraca massa metalurgi bijih $\to$ konsentrat $\to$ pengapalan, perhitungan PNBP royalti minerba dari tonase terverifikasi berdasarkan tabel tarif berversi PP 19/2025, pengelolaan izin kerja berisiko tinggi (HSE), provisi reklamasi pascatambang, dan penyelesaian pengapalan offtake berbasis Letter of Credit.
2. **`docs/PROGRESS.md` Fase 94 (baris 9740–9829)**:
   - Mencakup 8 sub-item fungsional (`94.1` s/d `94.8`), 1 sub-item test kunci (`94.9` digabung ke $\le 5$ file acceptance test di `tests/Acceptance/MiningCompliance`), dan `94.10` quality gate.

---

## 2. Rencana Anggaran Baris (RAB) B1 Fase 94 (T-2 — Kelas Lebar $\ge 7.000$ Baris)

Karena Fase 94 memiliki 8 sub-item penuh dan ambang keras $\text{B1} \ge 7.000$ baris, anggaran baris per lapisan ditetapkan secara proporsional terhadap rentang standar T-2:

| Lapisan | Rencana Standar T-2 | Rencana B1 Fase 94 (Skala $\ge 7.000$) | Komponen Utama yang Dibangun |
|---|---:|---:|---|
| Algoritma & value object | 900–1.400 | **1.000–1.400** | M4 `RoyaltyTierResolver` (+ Result & Verifier), M5 `MassBalanceReconciler` (+ Result & Verifier), M6 `CoalPriceAdjuster` (+ Result & Verifier) |
| State machine (enum, tabel transisi, guard, riwayat, exception) | 500–900 | **650–900** | `ReconciliationRunStateMachine` (8), `RoyaltyReturnStateMachine` (8), `WorkPermitStateMachine` (8), `OfftakeShipmentStateMachine` (10) |
| Aplikasi (Action/Service per aksi bisnis) | 1.800–2.800 | **2.200–2.800** | Actions & Services untuk Weighbridge Anti-Double-Dip, Stockpile 3D, Blending LP & Assay Bias, Smelter Routing, Royalti PP 19/2025, IUP Expiry, HSE Permit & CAPA, Environmental Sensor Shutdown, Reclamation Unwinding, Offtake Pricing, ESG Scope 1/2 |
| Integrasi (kontrak, adaptor, listener, saga) | 500–900 | **700–900** | Kontrak G7-K11 (`OfftakeLetterOfCreditGateway`), G7-K12 (`ProcessOutputRecorder`), `OfftakeShipmentLifecycleSaga` (3 cabang kompensasi), adaptor ke `Epc`, `Esg`, `Trade`, `Core\ApprovalEngine`, `DocumentNumbering` |
| Posting ledger | 300–500 | **350–500** | `MiningFase94LedgerPostingService` (8 kejadian D94-9: royalti terutang, bayar royalti, invoice provisional, penyesuaian final, susut rekonsiliasi, unwinding reklamasi, denda lingkungan, selisih kurs settlement) |
| HTTP (controller tipis, FormRequest, Policy, ViewModel) | 700–1.100 | **800–1.100** | 7 Controller (`Weighbridge`, `Grade`, `Royalty`, `Hse`, `Reclamation`, `Offtake`, `OfftakerPortal`), `MiningFase94AccessPolicy`, `MiningFase94ComplianceViewModel` |
| Command & laporan CSV | 500–900 | **600–900** | `mining:reconcile`, `mining:royalty-calc`, `mining:permit-expiry-scan`, `mining:reclamation-unwind`, `mining:env-threshold-scan`, `mining:audit-royalty`, `ExportMiningComplianceCsvAction` |
| Model | 300–500 | **500–700** | Eloquent Models tabel Fase 94 (`MiningRoyaltyRateVersion`, `MiningReferencePrice`, `MiningReconciliationRun`, `MiningAssayComparison`, `MiningConcessionPermit`, `MiningEnvironmentalReading`, `MiningReclamationSchedule`, `MiningOfftakeContract`, `MiningOfftakeShipment`) |
| **Total Rencana B1** | — | **7.000–7.600** | **$\ge 7.000$ baris (M-2 $\ge 55\%$, M-3 $\le 20\%$, M-4 $= 0$, M-5 $= 0$)** |

---

## 3. Spesifikasi Algoritma Bersertifikat Baru Fase 94

1. **M4 `Modules\Mining\Domain\Algorithms\RoyaltyTierResolver`**:
   - Menentukan tarif royalti (bps) dari tabel berversi berdasarkan `(komoditas, produk, metode_tambang, pita_kalori, tier_harga_acuan)` dengan batas bawah inklusif.
   - Menghitung kewajiban royalti dalam sen USD ($\lfloor \text{volume\_ton} \times \text{harga\_patokan\_sen} \times \text{tarif\_bps} / 10.000 \rfloor$) lalu mengonversinya ke IDR ($\lfloor \text{royalti\_sen\_usd} \times \text{kurs\_idr} / 100 \rfloor$).
2. **M5 `Modules\Shared\Domain\Algorithms\Metallurgy\MassBalanceReconciler`**:
   - Menyeimbangkan neraca massa logam terkandung ($\text{feed} = \text{concentrate} + \text{tailings}$) menggunakan koreksi berbobot varians ($r = \text{feed} - \text{concentrate} - \text{tailings}$, $\Delta_i = \lfloor |r| \times v_i / \sum v \rfloor$, sisa diberikan ke komponen ber-varians terbesar) dan menghitung *metallurgical recovery* dalam bps ($\lfloor \text{concentrate}' \times 10.000 / \text{feed}' \rfloor$).
3. **M6 `Modules\Mining\Domain\Algorithms\CoalPriceAdjuster`**:
   - Menghitung harga jual batubara tersesuaikan per ton dalam sen USD ($\lfloor \text{harga\_dasar\_sen} \times \text{GAR\_aktual} / \text{GAR\_acuan} \rfloor - \text{penalti\_abu\_sen} - \text{penalti\_sulfur\_sen}$) dan menolak pengapalan apabila kadar abu atau sulfur melampaui ambang penolakan (*rejection limit*).

---

## 4. Kontrak Lintas Modul Baru & Integrasi

1. **G7-K11 `Modules\TradeFinance\Contracts\OfftakeLetterOfCreditGateway`**:
   - Dibuat di `modules/TradeFinance/Contracts/OfftakeLetterOfCreditGateway.php` dengan implementasi `Modules\TradeFinance\Application\Services\OfftakeLetterOfCreditGatewayService` yang membungkus `IssueDocumentaryLetterOfCreditAction` dan `VerifyLcDocumentPresentationAction`, didaftarkan di `TradeFinanceServiceProvider`.
2. **G7-K12 `Modules\Manufacturing\Contracts\ProcessOutputRecorder`**:
   - Dibuat di `modules/Manufacturing/Contracts/ProcessOutputRecorder.php` dengan implementasi `Modules\Manufacturing\Application\Services\ProcessOutputRecorderService` yang membungkus `RecordShopFloorWorkOrderOutputAction`, didaftarkan di `ManufacturingServiceProvider`.
3. **Integrasi Kontrak Eksisting**:
   - `Modules\Core\Contracts\DocumentNumberingContract` (penomoran dokumen bukti bayar royalti gapless).
   - `Modules\Core\Contracts\ApprovalEngineContract` (persetujuan selisih rekonsiliasi, perpanjangan IUP, dan re-route larangan ekspor).
   - `Modules\Epc\Contracts\ProjectDraftFactory` (pembuatan proyek reklamasi pascatambang).
   - `Modules\Esg\Contracts\EmissionBenefitRecorder` & `VerifiedEmissionReader` (pencatatan emisi Scope 1 & 2).
