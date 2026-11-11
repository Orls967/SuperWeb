# Desain Teknis Arsitektur Fase 54 (Kelengkapan Tata Kelola Finansial Grup & Tutup Buku)

**Modul**: `EnterpriseFinance` (`modules/EnterpriseFinance/`)  
**Prefiks Tabel**: `ef_`  
**Paket Rilis**: W8-P5-b.1  
**Target Logika B1**: $\ge 6.000$ baris (Target: $6.300+$ baris)  
**Target Dokumentasi B3**: $\ge 1.200$ baris  
**Kepatuhan UI**: 0 pelanggaran `php artisan ui:lint`  

---

## 1. Arsitektur Komponen & Aliran Data

Modul `EnterpriseFinance` memegang peranan krusial sebagai orkestrator integritas tata kelola keuangan multi-entitas konglomerasi. Desain kelengkapan ini menghubungkan alur penganggaran operasional hingga penutupan siklus fiskal dan pembentukan bukti audit.

```mermaid
flowchart TD
    subgraph OperationalInput["Input Transaksional Modul Hulu"]
        PO["Procurement (Purchase Order)"]
        AR["Accounts Receivable / Billing"]
        AP["Accounts Payable / Invoicing"]
        INV["WMS / Inventory Movements"]
        FA["Fixed Assets / Depreciation"]
    end

    subgraph GovernanceCore["EnterpriseFinance Engine"]
        BCS["EnterpriseBudgetCycleStateMachine<br/>(8 Status, Versioning, ApprovalEngine)"]
        BCP["BudgetCommitmentService<br/>(implements BudgetCommitmentPort)"]
        ENC["ef_budget_encumbrances<br/>(Reserved, Liquidated, Released)"]
        FPC["FinancialPeriodClosingService<br/>(Checklist, Subledger Reconciliation)"]
        SOD["SodGovernanceService<br/>(Conflict Matrix & Expiring Exceptions)"]
        RCM["InternalControlTestingService<br/>(Automated ICFR Daily Audits)"]
        TAX["CorporateTaxReconciliationService<br/>(PPN Gapless Draft & Aggregation)"]
        ADP["AuditDataPackGeneratorService<br/>(Deterministic ZIP & SHA-256 Manifest)"]
    end

    subgraph LedgerBridge["Jurnal Buku Besar Terpadu"]
        POST["EnterpriseFinanceLedgerPoster"]
        ACCTS["EnterpriseFinanceAccounts<br/>(ef:income_summary, ef:retained_earnings,<br/>ef:vat_output_payable, ef:vat_settlement_clearing)"]
        LEDGER["Modules\\Banking\\Application\\Services\\LedgerService"]
    end

    subgraph ObservabilityLayer["Observabilitas & Kepatuhan"]
        AUD["AuditEnterpriseFinanceCommand<br/>(10 Dimensi Pemeriksaan Dua Sumber)"]
        ESC["ComplianceEscalationService<br/>(Multi-tier H-30/14/7/1/Overdue)"]
        INBOX["TaskInboxService::createTask()<br/>(Penerbitan Tugas Mitigasi Manajerial)"]
        SPINE["EventSpineInterface / OutboxBusInterface"]
    end

    PO -->|reserve / liquidate| BCP
    BCP --> ENC
    ENC --> BCS
    AR & AP & INV & FA -->|SubledgerTotalProvider| FPC
    FPC -->|Penutupan Nominal| POST
    POST --> ACCTS
    ACCTS --> LEDGER
    FPC -->|Kunci Periode| BCS
    SOD -->|Deteksi Konflik| INBOX
    RCM -->|Defisiensi Kontrol| INBOX
    TAX -->|Setoran PPN| POST
    FPC & TAX & SOD --> AUD
    ESC --> SPINE
```

---

## 2. Diagram Interaksi & Alur Sequence D2

### A. Alur Siklus Komitmen Anggaran Pengadaan (Budget Encumbrance Lifecycle)
Diagram urutan interaksi reservasi pagu pengadaan barang, verifikasi threshold, hingga likuidasi saat penerbitan faktur:

```mermaid
sequenceDiagram
    autonumber
    actor Buyer as Tim Pengadaan (Procurement)
    participant Listener as ProcurementPOListener
    participant Port as BudgetCommitmentPort
    participant Service as BudgetCommitmentService
    participant SM as EnterpriseBudgetCycleStateMachine
    participant Outbox as OutboxBusInterface

    Buyer->>Listener: Menerbitkan Purchase Order (PO)
    Listener->>Port: reserve(BudgetCommitmentRequest)
    Port->>Service: Validasi Pagu Anggaran Tersedia
    Service->>SM: Periksa Status Anggaran (Wajib ACTIVE)
    alt Anggaran Tidak Aktif atau Pagu Habis
        Service-->>Port: Exception BudgetGuardrailBreach
        Port-->>Listener: Gagal Cadangkan Anggaran
    else Pagu Cukup & Status ACTIVE
        Service->>Service: Catat ef_budget_encumbrances (RESERVED)
        opt Pagu Tersisa < Ambang Batas (10%)
            Service->>Outbox: Pancarkan enterprise.budget.threshold_exceeded.v1
        end
        Service-->>Port: BudgetCommitmentResult (Success)
        Port-->>Listener: Komitmen Terikat
    end
    Note over Buyer,Service: Saat Faktur Vendor Diterima & Diverifikasi
    Buyer->>Port: liquidate(commitmentCode, actualAmountMinor)
    Port->>Service: Update Status -> LIQUIDATED & Catat Selisih
    Service-->>Port: Konfirmasi Likuidasi Sukses
```

### B. Alur Checklist Tutup Buku & Penutupan Akun Nominal Periode Fiskal
Diagram urutan eksekusi checklist tutup buku bulanan, rekonsiliasi total subledger terhadap buku besar, penutupan akun pendapatan dan beban ke ikhtisar laba rugi, pemindahan saldo bersih ke laba ditahan, serta penguncian periode permanen:

```mermaid
sequenceDiagram
    autonumber
    actor Controller as Finance Controller
    participant CloseService as FinancialPeriodClosingService
    participant Adapters as SubledgerTotalAdapters
    participant Ledger as LedgerService
    participant Poster as EnterpriseFinanceLedgerPoster
    participant PeriodLock as ef_financial_period_locks
    participant Outbox as OutboxBusInterface

    Controller->>CloseService: executeClosingChecklist(entity, period)
    CloseService->>Adapters: calculateSubledgerTotals(entity, asOfDate)
    Adapters-->>CloseService: Totals (AR, AP, Fixed Asset, Inventory)
    CloseService->>Ledger: getTrialBalanceTotals(entity, period)
    Ledger-->>CloseService: Saldo Akun Terkait
    CloseService->>CloseService: Rekonsiliasi Subledger vs Ledger
    alt Diskrepansi > 0 (Subledger Tidak Cocok)
        CloseService-->>Controller: Gagal: Diskrepansi Ditemukan
    else Selisih = 0 (Rekonsiliasi Sempurna)
        CloseService->>Poster: postPeriodClosing(entity, period, revenueMinor, expenseMinor)
        Poster->>Ledger: Jurnal 1: Tutup Akun Pendapatan ke ef:income_summary
        Poster->>Ledger: Jurnal 2: Tutup Akun Beban ke ef:income_summary
        alt Laba Bersih (Net Profit)
            Poster->>Ledger: Jurnal 3: Debit ef:income_summary, Kredit ef:retained_earnings
        else Rugi Bersih (Net Loss)
            Poster->>Ledger: Jurnal 3: Debit ef:retained_earnings, Kredit ef:income_summary
        end
        CloseService->>PeriodLock: createLock(entity, period, voucherId)
        CloseService->>Outbox: Pancarkan enterprise.period.closed.v1
        CloseService-->>Controller: Periode Berhasil Ditutup & Terkunci
    end
```

---

## 3. Detail Spesifikasi Komponen Logika Bisnis

### A. Kontrak `BudgetCommitmentPort`
Kontrak layanan yang disediakan oleh `EnterpriseFinance` untuk dipanggil oleh modul transaksional seperti Procurement:
```php
namespace Modules\EnterpriseFinance\Contracts;

use Modules\EnterpriseFinance\Domain\DTOs\BudgetCommitmentRequest;
use Modules\EnterpriseFinance\Domain\DTOs\BudgetCommitmentResult;

interface BudgetCommitmentPort
{
    /**
     * Mencadangkan pagu anggaran untuk komitmen baru (PO/Kontrak).
     */
    public function reserve(BudgetCommitmentRequest $request): BudgetCommitmentResult;

    /**
     * Melikuidasi komitmen yang telah terealisasi ke faktur/beban aktual.
     */
    public function liquidate(string $commitmentCode, int $actualAmountMinor): BudgetCommitmentResult;

    /**
     * Melepaskan komitmen terbuka yang dibatalkan atau tidak terpakai.
     */
    public function release(string $commitmentCode, string $reason): BudgetCommitmentResult;
}
```

### B. Kontrak `ComplianceDeadlineSource`
Kontrak penyedia daftar kewajiban kepatuhan hukum dan regulasi lintas modul:
```php
namespace Modules\EnterpriseFinance\Contracts;

interface ComplianceDeadlineSource
{
    /**
     * Nama sumber penyedia kewajiban kepatuhan.
     */
    public function getSourceName(): string;

    /**
     * Mengambil seluruh tenggat waktu aktif pada atau sebelum tanggal rujukan.
     *
     * @return list<array{
     *     code: string,
     *     title: string,
     *     category: string,
     *     due_date: string,
     *     responsible_role: string,
     *     entity_code: string,
     *     penalty_estimate_minor: int
     * }>
     */
    public function getDeadlines(string $asOfDate): array;
}
```

### C. Implementasi `EnterpriseBudgetCycleStateMachine` (8 Status)
Siklus status formal:
1. `DRAFT`: Inisiasi struktur hierarki mata anggaran (Directorate $\to$ Cost Center $\to$ Account). Guard: $\Sigma \text{Anak} \le \text{Induk}$.
2. `SUBMITTED`: Pengajuan anggaran untuk evaluasi. Guard: Struktur hierarki lengkap tanpa celah alokasi.
3. `REVIEWED`: Penelaahan awal oleh Tim Perencana Anggaran. Guard: Catatan review lengkap.
4. `APPROVED`: Persetujuan resmi melalui otorisasi *Four-Eyes Principle* (Maker $\neq$ Checker via `ApprovalEngine`).
5. `ACTIVE`: Pagu anggaran efektif berlaku. Guard: Tanggal saat ini berada dalam rentang tahun fiskal.
6. `FROZEN`: Pembekuan sementara akibat penguncian periode tutup buku. Guard: Transaksi komitmen baru ditolak.
7. `REVISED`: Revisi resmi disetujui. Guard: Anggaran versi lama ditandai final `REVISED`, dan entri anggaran versi baru diterbitkan dengan status `APPROVED $\to$ ACTIVE`.
8. `CLOSED`: Penutupan buku akhir tahun. Guard: Seluruh komitmen terbuka telah dilikuidasi atau dilepas.

---

## 4. Rincian Command Terjadwal & Integrasi

1. `enterprise:budget-sync`:
   - Frekuensi: Tiap jam.
   - Fungsi: Membandingkan total komitmen tercatat di `ef_budget_encumbrances` dengan Purchase Order berstatus disetujui di modul Procurement. Mendeteksi komitmen gantung (*dangling reservations*).
2. `enterprise:close-checklist {period} {--entity=}`:
   - Frekuensi: Akhir bulan fiskal.
   - Fungsi: Memvalidasi 4 prasyarat penutupan buku (Zero Subledger Discrepancy, Complete Asset Depreciation, Zero Unapproved Journal, All Bank Accounts Reconciled).
3. `enterprise:compliance-escalate`:
   - Frekuensi: Harian (pukul 07.00 WIB).
   - Fungsi: Memindai seluruh `ComplianceDeadlineSource` dan memicu eskalasi berjenjang (H-30, H-14, H-7, H-1, dan Overdue) ke bus notifikasi.
4. `enterprise:control-tests`:
   - Frekuensi: Harian (pukul 01.00 WIB).
   - Fungsi: Menjalankan uji kontrol otomatis RCM (Four-Eyes, 3-Way Match, Weighing Tolerances, Margin Floor), mencatat hasil uji ke `ef_control_test_runs`, dan menerbitkan tugas `CoreTask` saat terdeteksi anomali.
5. `enterprise:data-pack {period} {--entity=}`:
   - Frekuensi: Sesuai permintaan audit KAP.
   - Fungsi: Membangun arsip ZIP deterministik berstruktur standar berisi Buku Besar, Neraca Saldo, Mutasi Kas Bank Terverifikasi, Daftar Aset Tetap, Ringkasan Pajak, dan manifest integritas `manifest.sha256`.
6. `enterprise:sod-scan`:
   - Frekuensi: Harian.
   - Fungsi: Menilai pemetaan user-role terhadap 12 aturan konflik SoD korporat, menerbitkan event `enterprise.sod.violation_detected.v1` bila terdeteksi pelanggaran tanpa dispensasi aktif.
7. `enterprise:tax-recon {period} {--entity=}`:
   - Frekuensi: Bulanan.
   - Fungsi: Merekonsiliasi PPN Keluaran vs PPN Masukan, menghasilkan nomor seri faktur gapless simulasi, mengompilasi bukti potong PPh 21/23/4(2)/22, dan menghasilkan draf SPT Masa.
8. `enterprise:audit`:
   - Frekuensi: Harian & pasca perubahan.
   - Fungsi: Audit komprehensif dua sumber 10 dimensi untuk memverifikasi konsistensi data modul terhadap basis data buku besar dan fakta eksternal.
