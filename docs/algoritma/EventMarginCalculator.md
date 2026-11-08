# Algoritma H16: EventMarginCalculator

## 1. Definisi & Ruang Lingkup
Algoritma `EventMarginCalculator` mengevaluasi kelayakan finansial proposal event MICE, pameran expo, dan pernikahan dengan mengalkulasikan total pendapatan versus total biaya langsung (*direct variable costs*). Algoritma ini menegakkan batas bawah guardrail margin kotor minimal 40,0% untuk melindungi profitabilitas properti hotel.

## 2. Struktur Data Masukan (Input)
- `proposalId` (string): Nomor proposal penawaran acara.
- `revenueBreakdown` (array):
  - `hallRentalIdr` (int)
  - `foodAndBeverageIdr` (int)
  - `avAndLightingIdr` (int)
  - `roomBlockCommissionIdr` (int)
  - `exhibitionBoothRentalIdr` (int)
  - `otherAddonsIdr` (int)
- `costBreakdown` (array):
  - `foodCogsIdr` (int)
  - `beverageCogsIdr` (int)
  - `casualLaborCostIdr` (int)
  - `thirdPartyAvRentalCostIdr` (int)
  - `expoElectricityAndUtilityCostIdr` (int)
  - `cleaningAndWasteDisposalCostIdr` (int)
- `guardrailMinMarginPercent` (float): Ambang batas bawah minimum (default 40.0%).

## 3. Logika Komputasi & Guardrail Enforcement
1. Agregasi Pendapatan Total ($R_{\text{total}}$):
   $$R_{\text{total}} = \sum R_{\text{component}}$$
2. Agregasi Biaya Langsung Total ($C_{\text{direct}}$):
   $$C_{\text{direct}} = \sum C_{\text{component}}$$
3. Perhitungan Laba Kotor Moneter & Persentase:
   $$\text{GrossMarginIdr} = R_{\text{total}} - C_{\text{direct}}$$
   $$\text{GrossMarginPercent} = \begin{cases} 
   \left( \frac{\text{GrossMarginIdr}}{R_{\text{total}}} \right) \times 100\%, & \text{bila } R_{\text{total}} > 0 \\
   0.0\%, & \text{bila } R_{\text{total}} \le 0
   \end{cases}$$
4. Validasi Guardrail:
   - Jika $\text{GrossMarginPercent} \ge \text{guardrailMinMarginPercent}$:
     Status: `GUARDRAIL_COMPLIANT_APPROVED`.
     Nilai kekurangan harga: $\Delta R_{\text{shortfall}} = 0$.
   - Jika $\text{GrossMarginPercent} < \text{guardrailMinMarginPercent}$:
     Status: `GUARDRAIL_BREACH_REJECTED`.
     Harga jual minimum yang diwajibkan:
     $$R_{\text{required}} = \left\lceil \frac{C_{\text{direct}}}{1 - (\text{guardrailMinMarginPercent} / 100)} \right\rceil$$
     Kekurangan pendapatan minimum:
     $$\Delta R_{\text{shortfall}} = R_{\text{required}} - R_{\text{total}}$$
5. Penerbitan Sertifikat:
   - Memproduksi `EventMarginCertificate` yang berisi seluruh metrik finansial, status kepatuhan, rekomendasi harga koreksi, dan verifikasi tanda tangan digital SHA-256.

## 4. Struktur Output
```json
{
  "proposal_id": "PROP-MICE-2026-441",
  "total_revenue_idr": 150000000,
  "total_direct_cost_idr": 82000000,
  "gross_margin_idr": 68000000,
  "gross_margin_percent": 45.33,
  "guardrail_threshold_percent": 40.0,
  "is_compliant": true,
  "status": "GUARDRAIL_COMPLIANT_APPROVED",
  "price_shortfall_idr": 0,
  "certificate_signature": "d9c3..."
}
```

