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


---

## 5. Matriks Pengujian Akseptansi & Invarian Kunci (L.2)

Untuk memastikan bahwa fungsionalitas diuji secara mendalam tanpa melebihi batas $\le 5$ acceptance test, seluruh skenario dikonsolidasikan ke dalam 5 berkas pengujian akseptansi utama:

| Berkas Acceptance Test | Fokus Pengujian & Invarian Kunci | Verifikasi Komponen |
|---|---|---|
| `MiningComplianceAccessTest.php` | 1. Otorisasi rute multi-peran (`weighbridge_operator`, `metallurgist`, `royalty_officer`, `hse_officer`, `mine_manager`, `offtake_manager`).<br>2. Penolakan akses lintas peran tanpa wewenang (403 Forbidden).<br>3. Kebijakan RBAC seragam pada Web dan API. | `MiningFase94AccessPolicy`, Web Middleware |
| `MiningComplianceAuditAndCorruptionTest.php` | 1. Deteksi pemutusan rantai hash timbangan (`broken hash chain`).<br>2. Verifikasi tiket timbang pembalik anti-double dip.<br>3. Peringatan otomatis manipulasi nilai timbangan.<br>4. Perintah audit konsol `mining:audit-weighbridge-hash-chain`. | `WeighbridgeAnomalyDetector`, `RecordHashChainWeighbridgeTicketAction` |
| `MiningComplianceBusinessRulesTest.php` | 1. Resolusi tarif royalti bertingkat PP 19/2025 (M4).<br>2. Rekonsiliasi neraca massa kuadrat terkecil (M5) & uji Chi-Kuadrat.<br>3. Penyesuaian harga komersial batubara dan penalti penolakan (M6).<br>4. Evaluasi deplesi cadangan KCMI & batas getaran peledakan SNI 7571:2010. | M4, M5, M6, `MineExplorationReserveDepletionTracker`, `MineBlastingSeismicMonitor` |
| `MiningComplianceE2EAndLedgerTest.php` | 1. Siklus penuh pengapalan offtake via saga terdistribusi.<br>2. Integrasi gerbang perbankan Letter of Credit G7-K11.<br>3. Pemuatan palka dan survei sarat tongkang.<br>4. Pembukuan 8 kejadian buku besar keuangan secara berpasangan debit/kredit seimbang. | `OfftakeShipmentLifecycleSaga`, `MiningFase94LedgerPostingService` |
| `MiningComplianceOfftakerPortalIdorTest.php` | 1. Isolasi penyewa multi-tenant pembeli mandiri offtaker.<br>2. Penolakan manipulasi parameter IDOR antar pembeli.<br>3. Pengunggahan aman sertifikat analisis pelabuhan bongkar (COA).<br>4. Validasi rekonsiliasi penyelesaian akhir faktur. | `MiningOfftakerPortalController`, Security Policy |

---

## 6. Matriks Skenario Seeder T1 Bernama (L.4)

Seeder `MiningFase94Seeder` memuat minimal 15 kasus nyata industri pertambangan Indonesia:
1. `SITE-COAL-MAHAKAM-01`: Konsesi pertambangan batubara open-pit di Kutai Kartanegara, Kalimantan Timur.
2. `SITE-NI-KONAWE-01`: Konsesi pertambangan bijih nikel laterit di Morowali / Konawe, Sulawesi Tenggara.
3. `TIER-PP19-COAL-ALL`: 12 versi tarif royalti berjenjang batubara PP 19/2025.
4. `HBA-HMA-HISTORIC-2026`: Seri harga acuan resmi Ditjen Minerba ESDM periode Juni s/d November 2026.
5. `PERMIT-IUP-PPKH-SET`: 7 izin konsesi resmi (IUP-OP Batubara, IUP-OP Nikel, PPKH Kehutanan, AMDAL, RKAB).
6. `CHAIN-WB-VALID-01`: 10 transaksi penimbangan truk berantai SHA-256 tanpa diskontinuitas.
7. `OVERLOAD-TRUCK-CASE`: Skenario pelanggaran batas daya angkut rated armada (> 115% GVW).
8. `CALIBRATION-WB-CASE`: Rekaman kalibrasi statis jembatan timbang menggunakan kendaraan uji metrologi.
9. `M5-RECON-BALANCED`: Siklus rekonsiliasi neraca massa konsentrat pabrik normal dengan $\chi^2 \le 1.84$.
10. `M5-RECON-GROSS-ERROR`: Kasus simulasi kerusakan sensor belt scale yang menghasilkan status `ANOMALY_DETECTED`.
11. `BLEND-COAL-ROM-SET`: Optimasi blending multi-stockpile untuk memenuhi spesifikasi kontrak $5.800\text{ kcal/kg}$.
12. `ROYALTY-PP19-ACCRUAL`: Penerbitan ketetapan kewajiban royalti bulanan dengan pemenuhan kuota DMO.
13. `OFFTAKE-SPA-PLN-01`: Kontrak perjanjian jual beli batubara jangka panjang dengan pembangkit listrik domestik.
14. `OFFTAKE-SHIP-BARGE-01`: Pengapalan tongkang batubara $50.000\text{ ton}$ dengan nominasi kapal dan draft survey.
15. `HSE-PTW-BLASTING-01`: Izin kerja K3 risiko tinggi peledakan lapangan dengan verifikasi getaran SNI 7571:2010.

---

## 7. Rencana Verifikasi Sabotase & Ledger Invariants (L.5)

Pengujian sabotase (*Sabotage Testing*) dijalankan dengan sengaja menyuntikkan kekeliruan logika pada komponen akuntansi:
- Membalik posisi debit dan kredit pada `MiningFase94LedgerPostingService::postRoyaltyAccrual`.
- Mengubah formula perhitungan neraca massa atau menghilangkan validasi toleransi varians.
- Memastikan suite acceptance test mendeteksi perubahan tersebut dan langsung berstatus FAIL.
- Mengembalikan kode ke kondisi semula untuk menjamin keaslian pengujian.

---

## 8. Lembar Pengesahan Gerbang Kualitas (Quality Gate Sign-Off)

| Gerbang Kualitas | Penanggung Jawab | Kriteria Kelulusan | Status |
|---|---|---|---|
| **Gerbang 1 (DoR & Konsep)** | Pelaksana [G7-P4] | DoR, ADR, dan Spesifikasi lengkap di `docs/`. | APPROVED |
| **Gerbang 2 (Acceptance Tests)** | Pelaksana [G7-P4] | $\le 5$ acceptance test lulus 100% tanpa skip. | APPROVED |
| **Gerbang 3 (Algoritma M4-M6)** | Pelaksana [G7-P4] | 3 algoritma bersertifikat lolos uji verifier & unit tests. | APPROVED |
| **Gerbang 4 (Seeder T1)** | Pelaksana [G7-P4] | Seeder berjalan lancar dua kali (normal & kejutan). | APPROVED |
| **Gerbang 5 (Sabotase)** | Pelaksana [G7-P4] | Sabotase gagal deterministik, pulih sempurna. | APPROVED |
| **Gerbang 6 (Arsitektur & B1/B3)** | Pelaksana [G7-P4] | B1 $\ge 7.000$, B3 $\ge 1.500$, `arch:scan` 0 Baru 0 Basi. | APPROVED |
