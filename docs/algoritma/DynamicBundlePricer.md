# Algoritma DynamicBundlePricer (V10)

## 1. Latar Belakang & Tujuan Bisnis
Konsep bundling terintegrasi pada Superweb Venue menggabungkan tiket acara hiburan dengan inventaris mitra perhotelan (G7-K20) dan armada penjemputan logistik shuttle (G7-K21). Penentuan harga diskon gabungan dinamis (*dynamic bundle pricing*) harus memberikan insentif nilai tambah bagi konsumen sekaligus melindungi operator venue dari penjualan di bawah harga pokok produksi (*loss leader leakage*).

Algoritma **DynamicBundlePricer (V10)** dirancang untuk mengevaluasi permintaan diskon paket secara otomatis dengan jaminan pengaman batas bawah harga (*floor price guardrail*) yang tidak dapat dilanggar oleh konfigurasi diskon promosi apa pun.

## 2. Model Matematika & Penegakan Guardrail
Diberikan himpunan parameter masukan:
- $B_{\text{base}} \in \mathbb{N}^+$: Total akumulasi harga eceran komponen (*Total Base Price IDR*),
- $D_{\text{bps}} \in [0, 5000]$: Tingkat diskon yang diminta dalam basis points ($1 \text{ bps} = 0.01\%$, batas atas 50%),
- $P_{\text{floor}} \in \mathbb{N}^+$: Batas ambang bawah harga pengaman laba (*Floor Price IDR*).

### 2.1. Perhitungan Diskon Nominal Dasar
Potongan nominal diskon dihitung melalui perkalian integer berpresisi basis points:
$$\Delta_{\text{raw}} = \left\lfloor \frac{B_{\text{base}} \times D_{\text{bps}}}{10000} \right\rfloor$$

Harga sementara paket sebelum guardrail:
$$P_{\text{raw}} = B_{\text{base}} - \Delta_{\text{raw}}$$

### 2.2. Penegakan Batas Bawah Harga (Floor Guardrail)
Harga akhir paket bundling ditentukan melalui fungsi batas bawah non-linear:
$$P_{\text{final}} = \max(P_{\text{raw}}, P_{\text{floor}})$$

Indikator pemicu batas bawah $G \in \{0, 1\}$ didefinisikan sebagai:
$$G = \begin{cases}
1, & \text{jika } P_{\text{raw}} < P_{\text{floor}} \\
0, & \text{jika } P_{\text{raw}} \ge P_{\text{floor}}
\end{cases}$$

### 2.3. Rekalkulasi Diskon Efektif Konsumen
Apabila guardrail terpicu ($G = 1$), persentase diskon efektif yang dirasakan konsumen ($D_{\text{effective}}$) dihitung ulang untuk mencegah salah saji dalam kampanye pemasaran:
$$\text{Total Savings IDR} = B_{\text{base}} - P_{\text{final}}$$
$$D_{\text{effective}} = \left\lfloor \frac{(B_{\text{base}} - P_{\text{final}}) \times 10000}{B_{\text{base}}} \right\rfloor$$

## 3. Struktur Sertifikat Kriptografis (DynamicBundlePriceCertificate)
Bukti penentuan harga bundel diterbitkan dalam bentuk sertifikat berintegritas tinggi:
$$\text{Signature} = \operatorname{HMAC-SHA256}(K, B_{\text{base}} \parallel P_{\text{final}} \parallel D_{\text{effective}} \parallel P_{\text{floor}} \parallel G \parallel \text{Timestamp})$$

Atribut sertifikat:
- `total_base_price_idr`: Total harga dasar seluruh komponen bundel.
- `bundle_price_idr`: Harga final setelah kalkulasi guardrail.
- `effective_discount_bps`: Nilai diskon efektif dalam basis points.
- `total_savings_idr`: Nilai penghematan riil pembeli dalam Rupiah.
- `floor_price_idr`: Nilai batas bawah pengaman harga modal.
- `is_floor_triggered`: Boolean penanda intervensi guardrail.
- `signature`: Hash SHA-256 penjamin integritas matematis.
- `computed_at`: Stempel waktu komputasi ISO-8601.

## 4. Keunggulan Teknis
1. **Zero Monetary Leakage:** Seluruh operasi menggunakan pembagian integer tertutup, meniadakan selisih sen pecahan mata uang.
2. **Kepatuhan Margin:** Bundling tidak pernah menghasilkan transaksi berstatus rugi kotor (*negative gross margin*).
3. **Auditability:** Sertifikat harga tersimpan di basis data bersama entri alokasi pesanan untuk kebutuhan audit kepatuhan promosi.

