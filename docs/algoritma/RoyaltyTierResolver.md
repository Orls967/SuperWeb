# Algoritma Resolusi Tarif Royalti Pertambangan Bertingkat (M4: RoyaltyTierResolver)

## 1. Latar Belakang & Landasan Regulasi
Sesuai dengan Peraturan Pemerintah Republik Indonesia No. 19 Tahun 2025 Lampiran I tentang Jenis dan Tarif atas Jenis Penerimaan Negara Bukan Pajak (PNBP) yang Berlaku pada Kementerian Energi dan Sumber Daya Mineral, penentuan tarif royalti komoditas batubara dan mineral logam mengalami perubahan fundamental:
1. Skema tarif tunggal (*flat rate*) telah digantikan oleh skema progresif multi-dimensi (*multi-tier sliding scale*).
2. Penentuan besaran tarif bergantung secara simultan pada:
   - Nilai Kalori Batubara (*Gross as Received / GAR* dalam satuan kcal/kg).
   - Harga Batubara Acuan (*HBA*) resmi yang diterbitkan bulanan oleh Menteri ESDM (dalam USD/ton).
   - Bentuk badan usaha dan perizinan konsesi (IUP vs IUPK).
   - Kepatuhan pasokan pasar domestik (*Domestic Market Obligation / DMO*).

`RoyaltyTierResolver` adalah implementasi domain murni yang deterministik, bebas efek samping (*pure function*), dan menghasilkan sertifikat keaslian kriptografis HMAC-SHA256 yang dapat diaudit secara independen oleh inspektur tambang dan auditor BPK/BPKP.

---

## 2. Matriks Regulasi PP 19/2025 (Komoditas Batubara)

Tarif royalti batubara diatur berdasarkan matriks dua dimensi berikut:

| Kategori Kalori (GAR) | Rentang HBA (USD/Ton) | Kode Tier Regulasi | Tarif Royalti Efektif (bps) | Tarif Royalti (%) |
|---|---|---|---|---|
| Rendah ($< 4.200\text{ kcal/kg}$) | $< 70.00$ | `TIER_LOW_HBA1` | $400\text{ bps}$ | $4.00\%$ |
| Rendah ($< 4.200\text{ kcal/kg}$) | $70.00 \le \text{HBA} < 90.00$ | `TIER_LOW_HBA2` | $500\text{ bps}$ | $5.00\%$ |
| Rendah ($< 4.200\text{ kcal/kg}$) | $90.00 \le \text{HBA} < 110.00$ | `TIER_LOW_HBA3` | $600\text{ bps}$ | $6.00\%$ |
| Rendah ($< 4.200\text{ kcal/kg}$) | $\ge 110.00$ | `TIER_LOW_HBA4` | $700\text{ bps}$ | $7.00\%$ |
| Menengah ($4.200 - 5.200\text{ kcal/kg}$) | $< 70.00$ | `TIER_MED_HBA1` | $600\text{ bps}$ | $6.00\%$ |
| Menengah ($4.200 - 5.200\text{ kcal/kg}$) | $70.00 \le \text{HBA} < 90.00$ | `TIER_MED_HBA2` | $750\text{ bps}$ | $7.50\%$ |
| Menengah ($4.200 - 5.200\text{ kcal/kg}$) | $90.00 \le \text{HBA} < 110.00$ | `TIER_MED_HBA3` | $900\text{ bps}$ | $9.00\%$ |
| Menengah ($4.200 - 5.200\text{ kcal/kg}$) | $\ge 110.00$ | `TIER_MED_HBA4` | $1.050\text{ bps}$ | $10.50\%$ |
| Tinggi ($> 5.200\text{ kcal/kg}$) | $< 70.00$ | `TIER_HIGH_HBA1` | $800\text{ bps}$ | $8.00\%$ |
| Tinggi ($> 5.200\text{ kcal/kg}$) | $70.00 \le \text{HBA} < 90.00$ | `TIER_HIGH_HBA2` | $950\text{ bps}$ | $9.50\%$ |
| Tinggi ($> 5.200\text{ kcal/kg}$) | $90.00 \le \text{HBA} < 110.00$ | `TIER_HIGH_HBA3` | $1.150\text{ bps}$ | $11.50\%$ |
| Tinggi ($> 5.200\text{ kcal/kg}$) | $\ge 110.00$ | `TIER_HIGH_HBA4` | $1.350\text{ bps}$ | $13.50\%$ |

---

## 3. Formulasi Matematis & Langkah Penyelesaian

### 3.1 Klasifikasi Kategori Kalori
Diberikan nilai kalori teruji laboratorium $\text{GAR}$ (dalam $\text{kcal/kg}$):

$$\text{Category}(\text{GAR}) = \begin{cases} 
\text{LOW}, & \text{bila } \text{GAR} < 4.200 \\
\text{MEDIUM}, & \text{bila } 4.200 \le \text{GAR} \le 5.200 \\
\text{HIGH}, & \text{bila } \text{GAR} > 5.200
\end{cases}$$

### 3.2 Klasifikasi Tingkat Harga Acuan (HBA Tier)
Diberikan Harga Batubara Acuan resmi dalam sen dollar AS ($\text{HBA}_{\text{cents}}$):

$$\text{Tier}(\text{HBA}) = \begin{cases}
1, & \text{bila } \text{HBA}_{\text{cents}} < 7.000 \quad (<\text{USD } 70.00) \\
2, & \text{bila } 7.000 \le \text{HBA}_{\text{cents}} < 9.000 \quad (\text{USD } 70.00 - 89.99) \\
3, & \text{bila } 9.000 \le \text{HBA}_{\text{cents}} < 11.000 \quad (\text{USD } 90.00 - 109.99) \\
4, & \text{bila } \text{HBA}_{\text{cents}} \ge 11.000 \quad (\ge \text{USD } 110.00)
\end{cases}$$

### 3.3 Penentuan Tarif Dasar ($R_{\text{base}}$)
Dari kombinasi $\text{Category}$ dan $\text{Tier}$, sistem mengambil nilai basis points ($1\text{ bps} = 0.01\%$) dari tabel pencarian deterministik:

$$R_{\text{base}} = \mathbf{Lookup}[\text{Category}, \text{Tier}]$$

### 3.4 Penyesuaian Diskon DMO
Bila pengapalan dialokasikan untuk pemenuhan kewajiban kelistrikan nasional (PLN / IPP) berdasarkan alokasi resmi DMO:

$$R_{\text{effective}} = \begin{cases}
\min(R_{\text{base}}, 500\text{ bps}), & \text{bila penjualan berstatus DMO PLN (Cap tarif)} \\
R_{\text{base}}, & \text{untuk ekspor umum atau smelter non-IPP}
\end{cases}$$

### 3.5 Perhitungan Nilai Royalti Terutang
Diberikan tonase produksi terverifikasi $W_{\text{tons}}$ dan harga dasar transaksi per ton $P_{\text{ton}}$:

$$\text{RoyaltyAmount}_{\text{USD}} = W_{\text{tons}} \times P_{\text{ton}} \times \frac{R_{\text{effective}}}{10.000}$$

$$\text{RoyaltyAmount}_{\text{IDR}} = \operatorname{round}\Big(\text{RoyaltyAmount}_{\text{USD}} \times \text{ExchangeRate}_{\text{JISDOR}}\Big)$$

---

## 4. Struktur Sertifikat Kriptografis & Pembuktian Integritas

Setiap eksekusi `RoyaltyTierResolver::resolve` menerbitkan objek *Value Object* `RoyaltyTierResolverResult` yang memuat bukti kriptografis anti-penyangkalan (*non-repudiation*):

```json
{
  "resolver_algorithm": "PP19_2025_TIER_RESOLVER_V1",
  "commodity": "COAL",
  "calorific_value_gar": 4850,
  "hba_cents": 8500,
  "resolved_tier_code": "TIER_MED_HBA2",
  "effective_rate_bps": 750,
  "calculated_royalty_idr": 921093750,
  "certificate_payload": {
    "evaluated_at": "2026-11-07T12:00:00Z",
    "calorific_category": "MEDIUM",
    "hba_tier_index": 2,
    "input_vector_digest": "4850|8500|COAL|IUP_OP"
  },
  "certificate_signature": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
}
```

### Prosedur Verifikasi Independen
Pihak ketiga (auditor eksternal atau regulator) menjalankan `RoyaltyTierResolverCertificateVerifier::verify`:
1. Membaca ulang parameter masukan yang tercantum dalam payload sertifikat.
2. Menghitung ulang intisari masukan (*input vector digest*).
3. Merekonstruksi kanonikal string: `PP19_2025|COAL|4850|8500|750|{evaluated_at}`.
4. Memvalidasi tanda tangan HMAC-SHA256 menggunakan kunci publik konsesi.
5. Bila tanda tangan cocok dan rate tidak melenceng dari batas tabel, verifikasi dinyatakan `VALID`.

---

## 5. Matriks Vektor Uji (*Verification Test Vectors*)

Tabel vektor uji berikut diverifikasi secara otomatis pada suite `tests/Unit/Algorithms/RoyaltyTierResolverTest.php`:

| ID Vektor | Komoditas | GAR (kcal/kg) | HBA (USD/ton) | Ekspektasi Kode Tier | Ekspektasi Rate (bps) | Status Uji |
|---|---|---|---|---|---|---|
| `VEC-M4-01` | Batubara | $3.800$ | $65.00$ | `TIER_LOW_HBA1` | $400$ | PASS |
| `VEC-M4-02` | Batubara | $3.800$ | $85.00$ | `TIER_LOW_HBA2` | $500$ | PASS |
| `VEC-M4-03` | Batubara | $3.800$ | $105.00$ | `TIER_LOW_HBA3` | $600$ | PASS |
| `VEC-M4-04` | Batubara | $3.800$ | $125.00$ | `TIER_LOW_HBA4` | $700$ | PASS |
| `VEC-M4-05` | Batubara | $4.800$ | $65.00$ | `TIER_MED_HBA1` | $600$ | PASS |
| `VEC-M4-06` | Batubara | $4.800$ | $85.00$ | `TIER_MED_HBA2` | $750$ | PASS |
| `VEC-M4-07` | Batubara | $4.800$ | $105.00$ | `TIER_MED_HBA3` | $900$ | PASS |
| `VEC-M4-08` | Batubara | $4.800$ | $125.00$ | `TIER_MED_HBA4` | $1.050$ | PASS |
| `VEC-M4-09` | Batubara | $5.600$ | $65.00$ | `TIER_HIGH_HBA1` | $800$ | PASS |
| `VEC-M4-10` | Batubara | $5.600$ | $85.00$ | `TIER_HIGH_HBA2` | $950$ | PASS |
| `VEC-M4-11` | Batubara | $5.600$ | $105.00$ | `TIER_HIGH_HBA3` | $1.150$ | PASS |
| `VEC-M4-12` | Batubara | $5.600$ | $125.00$ | `TIER_HIGH_HBA4` | $1.350$ | PASS |
| `VEC-M4-13` | Nikel | N/A ($1.85\%\text{ Ni}$) | $16.500\text{ USD/ton}$ | `TIER_NICKEL_ORE` | $1.000$ | PASS |
| `VEC-M4-14` | Nikel | N/A ($1.50\%\text{ Ni}$) | $16.500\text{ USD/ton}$ | `TIER_NICKEL_ORE_LOW` | $500$ | PASS |

---

## 6. Integrasi dengan Buku Besar Keuangan (General Ledger)

Setelah resolusi tarif disahkan oleh `CalculateVerifiedRoyaltyAction`, sistem memanggil `MiningFase94LedgerPostingService::postRoyaltyAccrual`:
- **Debit**: `min:royalty_expense:IDR` (Beban Operasional Penambangan - Royalti Negara).
- **Kredit**: `min:royalty_payable:IDR` (Kewajiban Lancar - Utang Royalti Minerba ESDM).

Saat penyetoran kas negara dilakukan melalui modul Kasir / Kas Bank:
- **Debit**: `min:royalty_payable:IDR`.
- **Kredit**: `cash:bank_idr:IDR`.

