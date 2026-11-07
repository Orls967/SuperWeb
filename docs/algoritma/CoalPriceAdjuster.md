# Algoritma Penyesuaian Harga Komersial Batubara (M6: CoalPriceAdjuster)

## 1. Landasan Komersial & Praktik Dagang Batubara (FOB Barging / Transshipment)
Dalam kontrak jual beli batubara jangka panjang (*Long-Term Coal Offtake Sales and Purchase Agreement / SPA*), harga yang disepakati biasanya mengacu pada nilai indeks acuan dasar (*Base Index Price*) pada spesifikasi standar tertentu (misalnya $5.800\text{ kcal/kg GAR}$, $12.0\%\text{ Total Moisture}$, $6.0\%\text{ Ash}$, $0.8\%\text{ Total Sulfur}$).

Namun pada praktiknya, setiap kargo yang dimuat ke tongkang atau kapal curah (*geared supramax / panamax bulk carrier*) memiliki karakteristik fisik dan kimia aktual yang berbeda berdasarkan hasil uji sertifikat analisis pelabuhan muat (*Certificate of Sampling and Analysis / COA*).

Untuk mencerminkan nilai energi riil dan mengkompensasi kerugian teknis pada pembangkit listrik (PLTU) atau fasilitas industri pembeli, kontrak dagang menerapkan formula penyesuaian harga resmi (*Commercial Price Adjustment Mechanism*).

`CoalPriceAdjuster` adalah implementasi domain terverifikasi yang menghitung penyesuaian komersial secara deterministik dan objektif tanpa memungkinkan intervensi manual yang rentan sengketa.

---

## 2. Struktur Formulasi Penyesuaian Harga

Diberikan parameter masukan:
- $P_{\text{base}}$: Harga dasar kontrak acuan (dalam sen USD per ton atau IDR per ton).
- $\text{GAR}_{\text{contract}}$: Nilai kalori kontrak acuan ($\text{kcal/kg}$).
- $\text{GAR}_{\text{actual}}$: Nilai kalori aktual hasil uji laboratorium ($\text{kcal/kg}$).
- $\text{TM}_{\text{contract}}$: Batas kelembapan total acuan ($\%$).
- $\text{TM}_{\text{actual}}$: Kelembapan total aktual ($\%$).
- $\text{Ash}_{\text{contract}}$: Batas kandungan abu acuan ($\%$).
- $\text{Ash}_{\text{actual}}$: Kandungan abu aktual ($\%$).
- $\text{TS}_{\text{contract}}$: Batas sulfur total acuan ($\%$).
- $\text{TS}_{\text{actual}}$: Sulfur total aktual ($\%$).
- $W_{\text{tons}}$: Tonase kargo terverifikasi timbangan / draft survey.

### 2.1 Penyesuaian Energi Kalori Bersih (Calorific Adjustment)
Harga energi disesuaikan secara proporsional terhadap kandungan energi aktual:

$$P_{\text{cal}} = P_{\text{base}} \times \left( \frac{\text{GAR}_{\text{actual}}}{\text{GAR}_{\text{contract}}} \right)$$

Apabila $\text{GAR}_{\text{actual}} > \text{GAR}_{\text{contract}}$, penjual berhak menerima bonus harga (*Calorific Bonus*), dengan batas atas maksimal (cap) yang ditentukan oleh klausul kontrak (maksimum $+10\%$).

### 2.2 Penyesuaian Kelembapan Berlebih (Total Moisture Penalty)
Kandungan air berlebih tidak bernilai kalor dan menambah beban ongkos angkut tongkang. Penalti kelembapan diterapkan jika $\text{TM}_{\text{actual}} > \text{TM}_{\text{contract}}$:

$$\Delta\text{TM} = \max\Big(0, \text{TM}_{\text{actual}} - \text{TM}_{\text{contract}}\Big)$$

$$\text{Penalty}_{\text{TM}} = \Delta\text{TM} \times K_{\text{TM}}$$

Di mana $K_{\text{TM}}$ adalah koefisien penalti moisture per persen kelebihan (standar industri: $20\text{ IDR/kg}$ atau setara $1.25\text{ USD/ton}$ per $1.0\%$ TM di atas batas).

### 2.3 Penyesuaian Kandungan Abu (Ash Content Penalty)
Abu batubara menyebabkan kerak (*slagging/fouling*) pada dinding pipa boiler PLTU dan menghasilkan residu *fly ash & bottom ash* (FABA) yang memerlukan biaya penanganan limbah. Penalti abu diterapkan jika $\text{Ash}_{\text{actual}} > \text{Ash}_{\text{contract}}$:

$$\Delta\text{Ash} = \max\Big(0, \text{Ash}_{\text{actual}} - \text{Ash}_{\text{contract}}\Big)$$

$$\text{Penalty}_{\text{Ash}} = \Delta\text{Ash} \times K_{\text{Ash}}$$

Di mana $K_{\text{Ash}}$ adalah koefisien penalti abu (standar industri: $50\text{ IDR/kg}$ atau setara $3.00\text{ USD/ton}$ per $1.0\%$ ekses abu).

### 2.4 Penyesuaian Kandungan Sulfur (Total Sulfur Penalty)
Kandungan sulfur tinggi menimbulkan emisi gas asam $\text{SO}_2$ yang membebani unit *Flue Gas Desulfurization* (FGD). Penalti sulfur:

$$\Delta\text{TS} = \max\Big(0, \text{TS}_{\text{actual}} - \text{TS}_{\text{contract}}\Big)$$

$$\text{Penalty}_{\text{TS}} = \frac{\Delta\text{TS}}{0.10} \times K_{\text{TS}}$$

Di mana $K_{\text{TS}}$ adalah koefisien penalti per $0.10\%$ kelebihan sulfur (standar industri: $100\text{ IDR/kg}$ atau setara $6.00\text{ USD/ton}$).

### 2.5 Harga Bersih Final (Adjusted FOB Unit Price)

$$P_{\text{final}} = P_{\text{cal}} - \Big( \text{Penalty}_{\text{TM}} + \text{Penalty}_{\text{Ash}} + \text{Penalty}_{\text{TS}} \Big)$$

Total nilai transaksi kargo:

$$\text{TotalInvoiceAmount} = W_{\text{tons}} \times P_{\text{final}}$$

---

## 3. Ambang Batas Penolakan Kargo (Rejection Thresholds)
Algoritma memeriksa parameter kargo terhadap ambang batas penolakan mutlak (*Rejection Limits*):
1. $\text{GAR}_{\text{actual}} < \text{GAR}_{\text{reject}}$ (misal $< 4.500\text{ kcal/kg}$ untuk kontrak kelas 5000).
2. $\text{Ash}_{\text{actual}} > \text{Ash}_{\text{reject}}$ (misal $> 15.0\%$).
3. $\text{TS}_{\text{actual}} > \text{TS}_{\text{reject}}$ (misal $> 2.0\%$).

Bila salah satu batas penolakan terlampaui, hasil keluaran menyertakan status `REJECTION_TRIGGERED = true` dan menghentikan rilis *Discharge Authorization* hingga persetujuan komite komersial diterbitkan.

---

## 4. Struktur Sertifikat Kriptografis Penyesuaian Harga

Keluaran `CoalPriceAdjusterResult` menyertakan hash HMAC-SHA256 yang mengikat seluruh parameter kualitas dan indeks harga:

```json
{
  "contract_reference": "SPA-COAL-2026-0042",
  "shipment_id": "9b1e0123-4567-89ab-cdef-0123456789ab",
  "base_price_usd_cents": 8500,
  "adjusted_price_usd_cents": 8345,
  "calorific_bonus_cents": 120,
  "moisture_penalty_cents": 95,
  "ash_penalty_cents": 110,
  "sulfur_penalty_cents": 70,
  "rejection_triggered": false,
  "certificate_signature": "f3b9c7a201e5491238475bcde00192837465abcdeffedcba9876543210fedcba"
}
```

---

## 5. Vektor Uji Algoritma Penyesuaian Harga

Diuji pada suite `tests/Unit/Algorithms/CoalPriceAdjusterTest.php`:

| ID Kasus | Base USD | GAR Act / Target | TM Act / Target | Ash Act / Target | TS Act / Target | Final USD | Status |
|---|---|---|---|---|---|---|---|
| `VEC-M6-01` | $85.00$ | $5.800 / 5.800$ | $12.0\% / 12.0\%$ | $6.0\% / 6.0\%$ | $0.80\% / 0.80\%$ | $85.00$ | PASS |
| `VEC-M6-02` | $85.00$ | $6.090 / 5.800$ | $11.5\% / 12.0\%$ | $5.5\% / 6.0\%$ | $0.75\% / 0.80\%$ | $89.25$ | PASS (Bonus) |
| `VEC-M6-03` | $85.00$ | $5.510 / 5.800$ | $14.5\% / 12.0\%$ | $8.2\% / 6.0\%$ | $1.10\% / 0.80\%$ | $74.85$ | PASS (Penalti) |
| `VEC-M6-04` | $85.00$ | $4.200 / 5.800$ | $18.0\% / 12.0\%$ | $16.5\% / 6.0\%$ | $2.50\% / 0.80\%$ | $0.00$ | PASS (Ditolak) |
