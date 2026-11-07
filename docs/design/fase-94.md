# Desain Arsitektur & Permodelan Teknis — Fase 94: Weighbridge, Grade Reconciliation, Royalty, HSE & Offtake

Dokumen ini menjabarkan desain naratif, diagram State Machine (D1), alur proses & saga dengan kompensasi (D2), struktur relasi entitas (D3), alur pembukuan buku besar (D4), dan konteks integrasi lintas modul (D5) untuk **Fase 94**.

---

## 1. Diagram State Machine (D1)

Fase 94 menegakkan 4 State Machine independen (tiga berstatus 8, satu berstatus 10):

### 1.1 `ReconciliationRunStateMachine` (8 Status — D94-1)
```mermaid
stateDiagram-v2
    [*] --> open
    open --> collecting
    collecting --> balancing
    balancing --> approved : |r| <= toleransi
    balancing --> variance_detected : |r| > toleransi
    variance_detected --> investigating
    investigating --> approved
    approved --> locked
    locked --> reopened
    reopened --> balancing
    locked --> [*]
```

### 1.2 `RoyaltyReturnStateMachine` (8 Status — D94-4)
```mermaid
stateDiagram-v2
    [*] --> draft
    draft --> calculated
    calculated --> reviewed
    reviewed --> approved
    approved --> filed
    filed --> paid
    paid --> amended
    amended --> paid
    paid --> closed
    closed --> [*]
```

### 1.3 `WorkPermitStateMachine` (8 Status — D94-5)
```mermaid
stateDiagram-v2
    [*] --> requested
    requested --> risk_assessed
    requested --> rejected
    risk_assessed --> approved
    risk_assessed --> rejected
    approved --> active
    active --> suspended
    suspended --> active
    active --> expired
    active --> closed
    expired --> closed
    closed --> [*]
    rejected --> [*]
```

### 1.4 `OfftakeShipmentStateMachine` (10 Status — D94-7)
```mermaid
stateDiagram-v2
    [*] --> contracted
    contracted --> nominated
    contracted --> cancelled
    nominated --> lc_opened
    nominated --> cancelled
    lc_opened --> loading
    lc_opened --> cancelled
    loading --> provisional_invoiced
    loading --> disputed
    provisional_invoiced --> final_assayed
    final_assayed --> final_invoiced
    final_assayed --> disputed
    disputed --> final_invoiced
    disputed --> cancelled
    final_invoiced --> settled
    settled --> [*]
    cancelled --> [*]
```

---

## 2. Alur Utama & Saga Offtake Lintas Modul dengan 3 Cabang Kompensasi (D2)

Diagram berikut menggambarkan alur eksekusi `Modules\Mining\Application\Sagas\OfftakeShipmentLifecycleSaga` beserta ketiga cabang kompensasinya (LC diskrepan, larangan ekspor mendadak T-5, dan kuota DMO belum terpenuhi):

```mermaid
flowchart TD
    A["Kontrak Offtake (contracted)"] --> B["Cek Kebijakan Ekspor & DMO via MiningExternalFactors"]
    B -- "DMO < 2500 bps (Ekspor)" --> C1["KOMPENSASI 3: Blokir Ekspor sampai Kuota DMO Terpenuhi"]
    B -- "Larangan Ekspor Aktif" --> C2["KOMPENSASI 2: Batalkan Nominasi Ekspor & Re-route ke Smelter Domestik + Approval"]
    B -- "Lolos Kebijakan" --> D["Nominasi Pengapalan (nominated)"]
    D --> E["Buka & Verifikasi LC via G7-K11 OfftakeLetterOfCreditGateway"]
    E -- "Status LC: discrepant" --> C3["KOMPENSASI 1: Tahan Muatan, Kembalikan Stok ke Stockpile / Alihkan ke Pembeli Domestik"]
    E -- "Status LC: accepted" --> F["Pemuatan Tongkang/Kapal dari Tiket Timbang (loading)"]
    F --> G["Terbitkan Invoice Provisional (provisional_invoiced) & Posting Debit trade_receivable / Kredit sales_revenue"]
    G --> H["Verifikasi Assay Final & Penyesuaian Harga M6 (final_assayed -> final_invoiced)"]
    H --> I["Pelunasan & Pencatatan Selisih Kurs ke min:fx_gain_loss:IDR (settled)"]
```

---

## 3. Diagram Relasi Entitas Database Fase 94 (D3)

```mermaid
erDiagram
    MIN_SITES ||--o{ MIN_WEIGHBRIDGE_TICKETS : "mencatat tiket"
    MIN_SITES ||--o{ MIN_STOCKPILES : "memiliki stockpile"
    MIN_SITES ||--o{ MIN_RECONCILIATION_RUNS : "merekonsiliasi"
    MIN_SITES ||--o{ MIN_ROYALTY_RETURNS : "menghitung royalti"
    MIN_SITES ||--o{ MIN_WORK_PERMITS : "mengeluarkan izin"
    MIN_SITES ||--o{ MIN_ENVIRONMENTAL_READINGS : "memantau sensor"
    MIN_SITES ||--o{ MIN_RECLAMATION_SCHEDULES : "menjadwalkan reklamasi"
    MIN_SITES ||--o{ MIN_OFFTAKE_CONTRACTS : "mengikat kontrak"
    MIN_OFFTAKE_CONTRACTS ||--o{ MIN_OFFTAKE_SHIPMENTS : "mengapalkan"
    MIN_ROYALTY_RATE_VERSIONS ||--o{ MIN_ROYALTY_RETURNS : "menentukan tarif"
```

---

## 4. Alur Pembukuan Buku Besar 8 Kejadian Fase 94 (D4)

```mermaid
flowchart TD
    P["MiningFase94LedgerPostingService"] --> J1["1. Royalti Terutang: Dr expense:min:royalty:IDR | Cr min:royalty_payable:IDR"]
    P --> J2["2. Bayar Royalti: Dr min:royalty_payable:IDR | Cr bank:clearing:IDR"]
    P --> J3["3. Invoice Provisional: Dr min:trade_receivable:IDR | Cr min:sales_revenue:IDR"]
    P --> J4["4. Penyesuaian Final Assay/Kalori: Dr/Cr min:trade_receivable:IDR <-> min:sales_revenue:IDR"]
    P --> J5["5. Susut Rekonsiliasi Disetujui: Dr min:inventory_shrinkage_expense:IDR | Cr min:ore_stockpile_inventory:IDR"]
    P --> J6["6. Unwinding Provisi Reklamasi: Dr min:reclamation_expense:IDR | Cr min:reclamation_provision_liability:IDR"]
    P --> J7["7. Denda Lingkungan: Dr min:environmental_penalty_expense:IDR | Cr min:environmental_penalty_payable:IDR"]
    P --> J8["8. Selisih Kurs Settlement: Dr/Cr min:fx_gain_loss:IDR <-> min:trade_receivable:IDR"]
```

---

## 5. Konteks Integrasi Lintas Modul & Role RBAC (D5)

```mermaid
flowchart LR
    R1["weighbridge_operator"] --> W["/mining/weighbridge"]
    R2["metallurgist"] --> G["/mining/grade"]
    R3["royalty_officer & finance_officer"] --> Y["/mining/royalty"]
    R4["hse_officer"] --> H["/mining/hse"]
    R5["mine_manager"] --> M["/mining/reclamation"]
    R6["offtake_manager"] --> O["/mining/offtake"]
    R7["offtaker"] --> P["/offtaker/mining"]
    O --> K11["G7-K11 TradeFinance OfftakeLetterOfCreditGateway"]
    G --> K12["G7-K12 Manufacturing ProcessOutputRecorder"]
    M --> EPC["Epc ProjectDraftFactory"]
    H --> ESG["Esg EmissionBenefitRecorder"]
    Y --> DOC["Core DocumentNumbering & ApprovalEngine"]
```
