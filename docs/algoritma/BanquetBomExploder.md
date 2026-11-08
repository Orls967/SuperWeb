# Algoritma H15: BanquetBomExploder

## 1. Definisi & Ruang Lingkup
Algoritma `BanquetBomExploder` mentransformasikan daftar menu perjamuan (buffet, set menu gala dinner, cocktail reception) ke dalam kebutuhan fisik bahan baku dapur, volume botol minuman bar, dan kebutuhan staf jamuan (waiter & cook), dengan memperhitungkan faktor susut pemotongan/pemasakan (*yield loss*) dan varians penuangan minuman (*pour shrinkage*).

## 2. Struktur Data Masukan (Input)
- `beoId` (string): Nomor Banquet Event Order.
- `guaranteedPax` (int): Jumlah tamu yang dijamin pemesan.
- `safetyBufferRatio` (float): Faktor cadangan porsi (default 1.05 untuk 5% ekstra).
- `menuItems` (array): Daftar item menu yang dipesan:
  - `itemId` (string), `name` (string), `type` (food/beverage)
  - `recipeIngredients` (array):
    - `ingredientId` (string), `name` (string), `portionGramsOrMl` (float), `costPerUnitIdr` (float), `yieldLossFactor` (float: 0.0 - 0.5)
- `eventDurationHours` (float): Durasi acara perjamuan (jam).
- `hourlyLaborRateIdr` (int): Upah per jam tenaga kerja kasual banquet.

## 3. Tahapan Komputasi
1. Perhitungan Jumlah Porsi Dapur:
   $$N_{\text{prep}} = \lceil \text{guaranteedPax} \times \text{safetyBufferRatio} \rceil$$
2. Agregasi Bahan Baku Makanan:
   Untuk setiap bahan mentah, kebutuhan kotor diakumulasikan:
   $$Q_{\text{gross}} = \frac{N_{\text{prep}} \times \text{portionGramsOrMl}}{1 - \text{yieldLossFactor}}$$
   $$\text{SubtotalCost}_{\text{raw}} = Q_{\text{gross}} \times \text{costPerUnitIdr}$$
3. Perhitungan Minuman & Varians Penuangan:
   Untuk minuman botolan berukuran $V_{\text{bottle}}$ (misal 750 ml untuk anggur/spirit atau 1000 ml untuk jus):
   $$Q_{\text{beverage\_servings}} = \lceil N_{\text{prep}} \times (1 + \sigma_{\text{pour}}) \rceil$$
   Jumlah botol dihitung dengan pembulatan ke atas (*ceiling*).
4. Estimasi Biaya Tenaga Kerja Banquet:
   - Waiter: $\lceil \text{guaranteedPax} / 20 \rceil$
   - Cook/Steward: $\lceil \text{guaranteedPax} / 40 \rceil$
   - Total Jam Kerja: $(L_{\text{waiter}} + L_{\text{cook}}) \times \text{eventDurationHours}$
   - Total Biaya Upah: $\text{Total Jam Kerja} \times \text{hourlyLaborRateIdr}$
5. Penerbitan Sertifikat:
   - Algoritma memproduksi `BanquetBomExplosionCertificate` yang memuat rincian bahan baku kotor, rincian botol minuman, biaya tenaga kerja, total COGS bahan, dan hash integritas SHA-256.

## 4. Struktur Output
```json
{
  "beo_id": "BEO-2026-089",
  "guaranteed_pax": 300,
  "prepared_pax": 315,
  "total_food_cogs_idr": 45000000,
  "total_beverage_cogs_idr": 18500000,
  "total_labor_cost_idr": 7200000,
  "waiters_required": 15,
  "cooks_required": 8,
  "ingredients_summary": [
    {
      "ingredient_id": "ING-BEEF-TENDERLOIN",
      "required_qty_kg": 63.0,
      "cost_idr": 18900000
    }
  ],
  "certificate_signature": "e5f2..."
}
```
