# Definition of Ready (DoR) Fase 158 — Lini 18: Insurance Embedded 30 Lini & Broker Marketplace

## 1. Identifikasi & Status
- **Fase:** 158 (G7-P1.2)
- **Lini:** 18 (Asuransi Lanjutan)
- **Modul Pemilik:** `Insurance` (`ins_`)
- **Modul Kolaborator:** `Agri` (G7-K1 NdviIndexReader), `Party`, `Bank` (Ledger), `Shared` (Graph\UnionFind)
- **Kondisi Awal (L.0):** Kode warisan modul `Integration` (`EmbeddedInsuranceService`, commit `0ba3cab`) berupa query builder mentah `DB::table()` tanpa integrasi domain, tanpa rute/UI, dan audit tautologis. Seluruh fungsionalitas dibangun ulang sebagai vertical slice mandiri di modul `Insurance`.

## 2. Rujukan Konsep & Aturan
- `docs/KONSEP.md` §A0–§A17
- Subbagian Lini 18: Insurance Embedded, Parametric Insurance, Broker Marketplace, SIU Fraud Investigation
- Algoritma Bersertifikat A6: `Modules\Shared\Domain\Algorithms\Graph\UnionFind`
- Kontrak Antar-Modul G7-K1: `Modules\Agri\Contracts\NdviIndexReader`
- Anti-Isian (K-4): Seluruh baris adalah kode produksi substantif dan berpengetikan ketat

## 3. Lingkup Pekerjaan (Vertical Slice V1–V12)
1. **Algoritma Bersertifikat A6 (`UnionFind`)**: Disjoint-set union dengan path compression dan union by rank, verifier sertifikat komponen, deteksi sindikat fraud (ring fraud) klaim asuransi lintas polis/pihak.
2. **Kontrak G7-K1 (`NdviIndexReader`)**: Dibuat di `Modules\Agri\Contracts\NdviIndexReader` dan diimplementasikan di `Modules\Agri\Application\Services\AgriNdviIndexReaderService` (membaca indeks vegetasi NDVI dan defisit curah hujan per petak).
3. **Katalog Embedded Insurance Matrix**: Konfigurasi dan penawaran produk proteksi terintegrasi ke 8+ lini (kredit otomotif HODL-to-Drive, hotel cancellation, ticket refund venue, cargo logistics, mall rent deposit, EPC bond bridge, mining liability, agri parametric).
4. **Parametric Trigger Engine**: Auto-adjudication dan auto-payout klaim parametrik berdasarkan feed eksternal/NDVI tanpa survei lapangan manual.
5. **Broker & Agent Marketplace**: Pendaftaran broker, tier komisi, assignment produk multi-asuransi, settlement komisi via ledger, penilaian kinerja broker.
6. **Customer Insurance Hub**: Dashboard konsolidasi polis multi-lini untuk nasabah, pengajuan klaim satu pintu, kalkulasi diskon bundling otomatis.
7. **Fraud Scoring & SIU Investigation Workflow**: Deteksi klaim anomali, ring fraud detection via A6, status hold klaim berisiko tinggi, workflow investigasi SIU (Special Investigation Unit) dengan four-eyes review dan blacklist.
8. **Audit Dua-Sumber**: Command `ins:embedded-audit` dan registrasi fixture korupsi `EmbeddedAuditCorruptionFixture`.
9. **UI Blade (Responsive 375px)**: Portal embedded offers, broker marketplace, customer hub, SIU investigation dashboard.
10. **Acceptance Test**: Maksimal 5 acceptance test di `tests/Acceptance/InsEmbedded/InsEmbeddedAcceptanceTest.php`.

