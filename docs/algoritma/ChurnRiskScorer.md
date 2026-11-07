# Spesifikasi & Pembuktian Matematis: ChurnRiskScorer (H9)

## 1. Definisi Masalah
Memprediksi probabilitas tamu hotel tidak akan kembali menginap (*guest churn probability*) $S_{churn} \in [0.0, 1.0]$ guna memicu tindakan retensi proaktif sebelum tamu beralih ke jaringan hotel kompetitor.

## 2. Variabel Masukan
1. $D_{recency}$: Jumlah hari sejak checkout terakhir.
2. $F_{stays}$: Jumlah masa inap dalam 12 bulan terakhir.
3. $M_{spend}$: Rata-rata pembelanjaan kamar dan incidental per inap (IDR).
4. $C_{unresolved}$: Jumlah keluhan atau insiden pelayanan yang belum terselesaikan.
5. $R_{rating}$: Skor ulasan kepuasan tamu ($1.0 - 5.0$).

## 3. Komponen Risiko Terbobot

$$S_{churn} = 0.30 \cdot R_{recency} + 0.25 \cdot R_{frequency} + 0.30 \cdot R_{complaint} + 0.15 \cdot R_{sentiment}$$

di mana:
- $R_{recency} = \min(1.0, \max(0.0, \frac{D_{recency}}{180}))$
- $R_{frequency} = 1.0 - \min(1.0, \max(0.0, \frac{F_{stays}}{6}))$
- $R_{complaint} = \min(1.0, \max(0.0, C_{unresolved} \times 0.35))$
- $R_{sentiment} = \max(0.0, \frac{5.0 - R_{rating}}{4.0})$

## 4. Matriks Tindakan Intervensi Retensi

| Nilai Skor $S_{churn}$ | Kategori Risiko | Rencana Tindakan Operasional |
| :--- | :--- | :--- |
| $\ge 0.65$ | **HIGH_RISK** | Intervensi langsung General Manager & penawaran kompensasi complimentary stay / F&B voucher IDR 500.000 |
| $0.30 \le S < 0.65$| **MEDIUM_RISK** | Pengiriman promosi win-back personal (diskon 20% kamar + double points) |
| $< 0.30$ | **LOW_RISK** | Tamu aktif loyal; perawatan komunikasi loyalty standar |

