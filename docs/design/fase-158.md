# Arsitektur & Desain Fase 158 — Lini 18: Insurance Embedded 30 Lini & Broker Marketplace

## 1. Diagram Arsitektur Komponen

```mermaid
flowchart TD
    subgraph MultiLineContext["Konteks Multi-Lini (30 Lini)"]
        OTO["Otomotif (HODL)"]
        HTL["Hotel Booking"]
        VEN["Venue Ticket"]
        LGX["Logistics Cargo"]
        AGR["Agri Farm"]
    end

    subgraph EmbeddedCore["Modules\\Insurance\\Embedded"]
        EMatrix["EmbeddedInsuranceMatrix"]
        EPolicy["EmbeddedPolicyService"]
        PTrigger["ParametricTriggerEngine"]
        CHub["CustomerHubService"]
    end

    subgraph BrokerMarketplace["Modules\\Insurance\\Broker"]
        BRegistry["BrokerRegistry"]
        BCommission["CommissionSettler"]
        BPerformance["PerformanceEvaluator"]
    end

    subgraph FraudSIU["Modules\\Insurance\\Fraud"]
        FScoring["FraudScoringEngine"]
        UnionFind["A6: Graph\\UnionFind (Ring Fraud)"]
        SIUWorkflow["SiuInvestigationService"]
    end

    subgraph ExternalContracts["Kontrak Eksternal"]
        G7K1["G7-K1: Agri NdviIndexReader"]
        BankLedger["Bank Ledger (Komisi & Payout)"]
    end

    MultiLineContext --> EMatrix
    EMatrix --> EPolicy
    AGR --> G7K1
    G7K1 --> PTrigger
    PTrigger --> BankLedger
    BRegistry --> BCommission
    BCommission --> BankLedger
    EPolicy --> FScoring
    FScoring --> UnionFind
    UnionFind --> SIUWorkflow
    EPolicy --> CHub
```

## 2. Model Relasional & Skema Database
- `ins_embedded_products`: Penawaran embedded per lini dan jenis konteks.
- `ins_embedded_policies`: Polis yang diterbitkan secara embedded dari transaksi lini lain.
- `ins_parametric_triggers`: Definisi trigger parametrik (indikator, ambang, formula payout).
- `ins_parametric_events`: Rekaman eksekusi trigger dan bukti data oracle/sensor.
- `ins_brokers`: Profil broker/agen (merujuk ke Party), status lisensi, rating kinerja.
- `ins_broker_commissions`: Riwayat komisi dan status penyelesaian ledger.
- `ins_fraud_assessments`: Penilaian skor risiko fraud per klaim atau pengajuan.
- `ins_siu_investigations`: Berkas investigasi SIU, temuan ring fraud, keputusan four-eyes.
- `ins_blacklisted_entities`: Entitas/identitas yang diblacklist dari hasil investigasi SIU.

## 3. Algoritma Bersertifikat A6 (`UnionFind`)
- Struktur data Disjoint Set Union (DSU) dengan path compression dan rank heuristic.
- Mendeteksi kluster/lingkaran entitas terkait (alamat sama, rekening sama, perangkat sama, nomor telepon sama) antar klaim berulang.
- Mengembalikan komponen terhubung dan sertifikat disjoint sets untuk divalidasi oleh `UnionFindCertificateVerifier`.

## 4. Kontrak Antar-Modul G7-K1 (`NdviIndexReader`)
- Interface `Modules\Agri\Contracts\NdviIndexReader` dengan metode `readIndex(string $plotCode, CarbonImmutable $date): array{ndvi_score: int, rainfall_deficit_bps: int}`.
- Diimplementasikan di `Modules\Agri\Application\Services\AgriNdviIndexReaderService`.

