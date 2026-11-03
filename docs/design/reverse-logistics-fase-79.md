# Design — Fase 79: Reverse Logistics ke Bahan Baku Manufaktur (P1.1)

## 1. Domain Model & Arsitektur Data

### 1.1 Tabel & Relasi
- Modul `Logistics`:
  - `lgx_shipments`: Memperluas dengan kolom `is_reverse` (boolean), `reverse_reason` (string 32), `source_party_id` (foreignId to users/parties), `material_code` (string 32), `grade_code` (string 16), `net_weight_grams` (unsignedBigInteger), `destination_facility_id` (foreignId to locations).
  - Status enum reverse: `requested`, `picked_up`, `at_hub`, `delivered_to_plant`, `received`, `rejected`.
  - `lgx_tracking_events`: Mencatat hash-chain kustodi untuk setiap transisi status reverse.
- Modul `Manufacturing` (aditif):
  - `mfg_material_lots`: Ditambahkan kolom `source_reverse_shipment_id` (unsignedBigInteger unique nullable), `grade_code` (string 16 nullable).
  - Satu shipment reverse menghasilkan tepat 1 lot material (1:1 constraint).
- Modul `Pricing` (aditif):
  - Tabel `pric_material_prices`: `id`, `material_code`, `grade_code`, `price_per_kg_idr`, `effective_from`, `effective_to`, `is_active`, timestamps.
- Modul `Esg` (aditif):
  - Tabel `esg_circular_factors`: `id`, `material_code`, `co2e_factor_g_per_kg`, `version`, `effective_from`, `effective_to`, `source_label`, timestamps.
  - Tabel `esg_avoided_emissions`: `id`, `source_type`, `source_id`, `material_code`, `quantity_grams`, `avoided_co2e_grams`, `idempotency_key`, `recorded_at`, timestamps.

## 2. Kontrak Antar-Modul (G6 Catalog)

### 2.1 K1: `Modules\Manufacturing\Contracts\RecycledMaterialReceiver`
```php
namespace Modules\Manufacturing\Contracts;

interface RecycledMaterialReceiver
{
    public function receive(
        int $reverseShipmentId,
        string $materialCode,
        string $gradeCode,
        int $quantityGrams,
        int $unitPricePerKgIdr,
        string $idempotencyKey
    ): int;
}
```

### 2.2 K2: `Modules\Pricing\Contracts\MaterialPriceQuote`
```php
namespace Modules\Pricing\Contracts;

use Carbon\CarbonImmutable;

interface MaterialPriceQuote
{
    public function pricePerKgIdr(string $materialCode, string $gradeCode, CarbonImmutable $at): ?int;
}
```

### 2.3 K3: `Modules\Esg\Contracts\EmissionBenefitRecorder`
```php
namespace Modules\Esg\Contracts;

use Carbon\CarbonImmutable;

interface EmissionBenefitRecorder
{
    public function recordAvoidedEmission(
        string $sourceType,
        int $sourceId,
        string $materialCode,
        int $quantityGrams,
        CarbonImmutable $at,
        string $idempotencyKey
    ): int;
}
```

## 3. Akuntansi & Ledger Posting

- Akru biaya carrier:
  - Debit: `lgx:carrier_cost:IDR`
  - Kredit: `ap:carrier:{id}:IDR`
- Penerimaan bahan daur ulang (K1):
  - Debit: `inv:materials:IDR` (aset persediaan bahan baku)
  - Kredit: `mfg:recycled_material_clearing:IDR` (liabilitas kliring penerimaan)
- Pencatatan nota sumber / payable pemasok:
  - Debit: `mfg:recycled_material_clearing:IDR`
  - Kredit: `ap:supplier:{party}:IDR`
- Nilai lot dihitung eksak:
  `$nilaiIdr = (int) round(($quantityGrams * $unitPricePerKgIdr) / 1000, 0, PHP_ROUND_HALF_UP);`

## 4. Algoritma MinCostFlow

- Kelas: `Modules\Shared\Domain\Algorithms\Flow\MinCostFlow`
- Verifier: `Modules\Shared\Domain\Algorithms\Flow\MinCostFlowCertificateVerifier`
- Algoritma: Successive Shortest Path dengan potensial node Johnson (Bellman-Ford inisial bila ada biaya negatif, lalu Dijkstra berbobot non-negatif tereduksi $c^\pi_{ij} = c_{ij} + \pi_i - \pi_j \ge 0$).
- Sertifikat:
  1. Konservasi aliran di setiap node.
  2. Batas kapasitas: $0 \le f_{ij} \le u_{ij}$.
  3. Reduced cost condition: Pada graf residual, untuk setiap busur dengan sisa kapasitas $> 0$, berlaku $c_{ij} + \pi_i - \pi_j \ge 0$.
- Kompleksitas: $O(F \cdot E \log V)$.

## 5. Peran, Otorisasi & Rute

- `/logistics/reverse` (GET, POST): `hub_operator`, `logistics_admin`.
- `/manufacturing/recycled-receipts` (GET, POST): `plant_receiver`.
- Policy anti-IDOR: Pengguna sumber (`material_seller`, resto, store, dsb.) hanya dapat melihat pengiriman reverse miliknya.

## 6. Audit Dua Sumber: `logi:circular-audit`

1. Sumber 1: Verifikasi tabel operasional:
   - Setiap shipment reverse berstatus `received` memiliki tepat 1 baris di `mfg_material_lots`.
   - $\sum$ gram lot = $\sum$ gram timbang penerimaan shipment reverse.
2. Sumber 2: Verifikasi konsistensi buku besar:
   - Saldo akun `mfg:recycled_material_clearing:IDR` = $\sum$ nilai lot yang belum diselesaikan nota sumbernya.
   - Saldo akun `lgx:carrier_payable_on_hold:IDR` = $\sum$ sengketa terbuka (setelah P1.3).
