# Bukti Kondisi Lama L.0 — Fase 94: Weighbridge, Grade Reconciliation, Royalty, HSE & Offtake

**Tanggal Audit L.0**: 2026-11-07  
**SHA Awal Fase 94**: `3afa87d5` (`[G7-P4.1] Fase 93`)  
**Pelaksana**: `[G7-P4]`

---

## 1. Inventaris Kode Lama Modul `Mining` Terkait Lingkup Fase 94

Pemeriksaan terhadap kondisi awal repositori sebelum implementasi Fase 94 menemukan kerangka tipis berikut:

| File Lama | Jumlah Baris | Temuan Kesenjangan Terhadap Standar Ketat W8 (Fase 94) |
|---|---:|---|
| `modules/Mining/Application/Services/MiningComplianceAndRoyaltyService.php` | 168 | Perhitungan royalti masih memakai tarif tunggal statis (`royalty_rate_bps` yang dikirim sebagai argumen input), belum membaca tabel tarif berversi PP 19/2025 (`min_royalty_rate_versions`) dan belum menerapkan algoritma bersertifikat **M4 `RoyaltyTierResolver`** maupun konversi sen USD ke IDR dengan kurs berversi. |
| `modules/Mining/Domain/Services/MiningWeighbridgeChainService.php` | 92 | Hash-chain per site sudah ada tetapi belum menolak tiket *double-dip* `(plat, waktu ±60 detik)` sesuai **G7-D28**, serta belum memiliki mekanisme tiket pembalik (*reversal ticket*) append-only. |
| `modules/Mining/Application/Services/MiningHseAndContractorService.php` | 184 | Belum memiliki State Machine 8 status `WorkPermitStateMachine` (`requested` $\to$ `risk_assessed` $\to$ `approved` $\to$ `active` $\to$ `suspended` $\to$ `expired` $\to$ `closed` $\to$ `rejected`), belum terhubung dengan pemindaian sensor lingkungan (`mining:env-threshold-scan`) yang menangguhkan izin kerja area secara otomatis, dan belum membukukan denda lingkungan ke `min:environmental_penalty_expense:IDR` / `min:environmental_penalty_payable:IDR`. |
| `modules/Mining/Application/Services/MiningEnvironmentalComplianceService.php` | 167 | Belum mengimplementasikan jadwal *unwinding* provisi reklamasi pascatambang bulanan (`mining:reclamation-unwind {month}`) dan pembuatan proyek reklamasi lewat kontrak `Modules\Epc\Contracts\ProjectDraftFactory`. |
| Algoritma M4, M5, M6 | 0 | `Modules\Mining\Domain\Algorithms\RoyaltyTierResolver` (M4), `Modules\Shared\Domain\Algorithms\Metallurgy\MassBalanceReconciler` (M5), dan `Modules\Mining\Domain\Algorithms\CoalPriceAdjuster` (M6) beserta `CertificateVerifier`-nya belum ada. |
| Kontrak Lintas Modul G7-K11 & G7-K12 | 0 | `Modules\TradeFinance\Contracts\OfftakeLetterOfCreditGateway` (G7-K11) dan `Modules\Manufacturing\Contracts\ProcessOutputRecorder` (G7-K12) belum dibuat. |
| State Machine Fase 94 | 0 | `ReconciliationRunStateMachine` (8 status), `RoyaltyReturnStateMachine` (8 status), `WorkPermitStateMachine` (8 status), dan `OfftakeShipmentStateMachine` (10 status) belum ada. |
| Saga Offtake Lintas Modul | 0 | `OfftakeShipmentLifecycleSaga` (kontrak $\to$ nominasi $\to$ LC G7-K11 $\to$ muat $\to$ invoice provisional $\to$ assay final $\to$ invoice final $\to$ settlement, dengan 3 cabang kompensasi: LC diskrepan, larangan ekspor mendadak, dan blokir kuota DMO) belum ada. |
| Rute, UI & Command Fase 94 | Parsial | Rute `/mining/grade`, `/mining/royalty`, `/mining/reclamation`, `/mining/offtake`, `/offtaker/mining` serta command `mining:reconcile {month}`, `mining:royalty-calc {month}`, `mining:permit-expiry-scan`, `mining:reclamation-unwind {month}`, `mining:env-threshold-scan`, dan `mining:audit-royalty` belum tersedia. |

---

## 2. Keputusan L.0 terhadap Kode Lama (Batas T-9)

1. Kelas-kelas lama (`MiningComplianceAndRoyaltyService`, `MiningWeighbridgeChainService`, `MiningHseAndContractorService`, `MiningEnvironmentalComplianceService`) **dipertahankan utuh** agar seluruh test regresi lama (`MiningWeighbridgeRoyaltyAndHseTest`, `MiningWeighbridgeEnterpriseDeepeningTest`, `MiningEnvironmentalComplianceTest`, `MiningHseAndContractorTest`) tetap hijau.
2. Seluruh kapabilitas Fase 94 dibangun sebagai komponen baru yang terhubung penuh ke database, State Machine, Saga, Kontrak G7-K11/K12, Buku Besar, Rute Web, Blade UI, dan CLI Commands dengan target **B1 $\ge 7.000$ baris** dan **B3 $\ge 1.500$ baris**.
