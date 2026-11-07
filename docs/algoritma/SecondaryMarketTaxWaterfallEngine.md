# Algoritma SecondaryMarketTaxWaterfallEngine (V12)

## 1. Landasan Regulasi & Prinsip Akuntansi Fiskal
Pasar sekunder tiket hiburan dan pertunjukan seni di Indonesia tunduk pada kepatuhan fiskal daerah, khususnya pemungutan **Pajak Barang dan Jasa Tertentu (PBJT) Jasa Kesenian dan Hiburan** sebesar 10% sesuai Undang-Undang Nomor 1 Tahun 2022 tentang Hubungan Keuangan antara Pemerintah Pusat dan Pemerintahan Daerah (UU HKPD).

Selain kewajiban perpajakan pemerintah daerah, platform juga harus memfasilitasi pembagian komisi operasional platform sebesar 8% dan pembagian royalti perlindungan hak cipta kepada promotor dan artis sebesar 2%. Algoritma **SecondaryMarketTaxWaterfallEngine (V12)** mengimplementasikan hukum konservasi moneter tertutup (*closed monetary conservation law*) untuk mendistribusikan setiap rupiah transaksi sekunder secara eksak tanpa kehilangan presisi pecahan sen (*fractional cent loss*).

## 2. Model Distribusi Air Terjun (Monetary Waterfall Model)
Diberikan parameter masukan transaksi:
- $G \in \mathbb{N}^+$: Nilai bruto transaksi penjualan sekunder (*Gross Resale Price IDR*),
- $R_{\text{PBJT}} = 1000 \text{ bps}$ (10.00% Pajak Hiburan PBJT Daerah),
- $R_{\text{Platform}} = 800 \text{ bps}$ (8.00% Komisi Layanan Escrow Platform),
- $R_{\text{Promoter}} = 200 \text{ bps}$ (2.00% Royalti Promotor & Seniman).

### 2.1. Formula Alokasi Komponen Moneter
Alokasi setiap komponen dihitung menggunakan pembagian integer lantai (*floor integer division*):
$$A_{\text{PBJT}} = \left\lfloor \frac{G \times R_{\text{PBJT}}}{10000} \right\rfloor$$
$$A_{\text{Platform}} = \left\lfloor \frac{G \times R_{\text{Platform}}}{10000} \right\rfloor$$
$$A_{\text{Promoter}} = \left\lfloor \frac{G \times R_{\text{Promoter}}}{10000} \right\rfloor$$

### 2.2. Penegakan Hukum Konservasi Moneter Tertutup
Untuk menjamin tidak ada saldo yang hilang akibat pembulatan lantai ke bawah, nilai pencairan bersih kepada penjual (*Seller Net Payout*) dihitung sebagai selisih eksak terhadap total bruto:
$$A_{\text{Seller}} = G - (A_{\text{PBJT}} + A_{\text{Platform}} + A_{\text{Promoter}})$$

Dengan konstruksi matematis ini, identitas konservasi terpenuhi secara absolut:
$$A_{\text{PBJT}} + A_{\text{Platform}} + A_{\text{Promoter}} + A_{\text{Seller}} \equiv G$$

### 2.3. Bukti Ketiadaan Kebocoran Pecahan Sen (Zero-Leakage Invariant)
Didefinisikan fungsi varians $\delta(G)$:
$$\delta(G) = G - \left( A_{\text{PBJT}} + A_{\text{Platform}} + A_{\text{Promoter}} + A_{\text{Seller}} \right)$$

Substitusi nilai $A_{\text{Seller}}$:
$$\delta(G) = G - \left( A_{\text{PBJT}} + A_{\text{Platform}} + A_{\text{Promoter}} + \left[ G - (A_{\text{PBJT}} + A_{\text{Platform}} + A_{\text{Promoter}}) \right] \right) = G - G = 0$$

Terbukti bahwa $\forall G \in \mathbb{N}^+, \delta(G) = 0$. Tidak ada selisih saldo pembulatan yang menguap ataupun menciptakan liabilitas semu pada buku besar (*general ledger*).

## 3. Struktur Sertifikat Kriptografis (SecondaryMarketTaxWaterfallCertificate)
Setiap eksekusi distribusi menghasilkan sertifikat resmi bertanda tangan HMAC-SHA256:
$$\text{Signature} = \operatorname{HMAC-SHA256}(K, G \parallel A_{\text{PBJT}} \parallel A_{\text{Platform}} \parallel A_{\text{Promoter}} \parallel A_{\text{Seller}} \parallel \text{Timestamp})$$

Sertifikat ini menjadi dasar hukum bagi rekonsiliasi laporan SPT Masa Pajak Daerah dan laporan pencairan royalti musisi.

