# Kajian Rekayasa Ekonomi Sirkular & Pemanfaatan Terak Nikel Slag (Fase 121)

## 1. Pendahuluan & Karakteristik Material Terak Nikel (Nickel Slag)

Peleburan pirometalurgi laterit saprolit menggunakan tanur busur listrik terendam RKEF menghasilkan terak nikel (*ferronickel / NPI slag*) dalam jumlah masif, dengan rasio generasi sekitar 3.0 hingga 3.5 ton terak per setiap 1 ton produk logam leburan. Berdasarkan Peraturan Pemerintah No. 22 Tahun 2021 tentang Penyelenggaraan Perlindungan dan Pengelolaan Lingkungan Hidup, terak nikel diklasifikasikan sebagai **Limbah Non-B3 Terdaftar**, yang membuka peluang strategis untuk pemanfaatan bernilai tambah tinggi (*high-value circular economy*) daripada penimbunan di fasilitas penampungan (*slag dump*).

```
+---------------------------------------------------------------------------------------------------+
|                        CIRCULAR ECONOMY VALUE CHAIN: NICKEL SLAG                                  |
+---------------------------------------------------------------------------------------------------+
|  [MOLTEN SLAG 1550°C] ──► [WATER QUENCHING GRANULATION] ──► [GRANULATED SLAG 0.5 - 4 MM]          |
|                                                                     │                             |
|                     ┌───────────────────────────────────────────────┴───────────────────────┐     |
|                     ▼                                                                       ▼     |
|       [INFRASTRUKTUR JALAN TAMBANG]                                           [INDUSTRI SEMEN]    |
|        - Subbase Agregat Kasar                                                 - Supplementary    |
|        - CBR Lapangan > 90%                                                      Cementitious     |
|        - Pengganti Pasir Sungai Alami                                          - Beton Geopolimer |
+---------------------------------------------------------------------------------------------------+
```

---

## 2. Karakterisasi Kimiawi & Uji Toksisitas Pelindian (TCLP)

Komposisi kimiawi terak nikel didominasi oleh fasa amorf silika dan magnesium oksida hasil pembekuan cepat (*rapid water quenching*):
- Silika ($\text{SiO}_2$): $42.0\% - 48.0\%$
- Magnesium Oksida ($\text{MgO}$): $24.0\% - 30.0\%$
- Besi Oksida ($\text{FeO}$): $14.0\% - 18.0\%$
- Alumina ($\text{Al}_2\text{O}_3$): $2.5\% - 4.5\%$
- Kalsium Oksida ($\text{CaO}$): $1.5\% - 3.5\%$

### Uji Kepatuhan Pelindian Beracun (TCLP - Toxicity Characteristic Leaching Procedure)
Uji laboratorium independen memverifikasi bahwa konsentrasi logam berat terlindi berada jauh di bawah baku mutu Lampiran XI PP No. 22/2021:
- Timbal ($\text{Pb}$): $< 0.05 \text{ mg/L}$ (Baku Mutu: $5.0 \text{ mg/L}$)
- Kadmium ($\text{Cd}$): $< 0.01 \text{ mg/L}$ (Baku Mutu: $1.0 \text{ mg/L}$)
- Kromium Heksavalen ($\text{Cr}^{6+}$): $< 0.02 \text{ mg/L}$ (Baku Mutu: $5.0 \text{ mg/L}$)
- Arsenik ($\text{As}$): $< 0.01 \text{ mg/L}$ (Baku Mutu: $5.0 \text{ mg/L}$)
- Merkuri ($\text{Hg}$): $< 0.001 \text{ mg/L}$ (Baku Mutu: $0.2 \text{ mg/L}$)

---

## 3. Aplikasi Rekayasa Sipil & Manfaat Lingkungan

### A. Pengganti Agregat Halus Jalan Tambang (*Road Subbase Aggregate*)
- Terak nikel tergranulasi memiliki kekerasan Mohs 6.0 – 7.0 dan sudut geser dalam ($\phi \approx 42^\circ$), menghasilkan daya dukung tanah dasar (*California Bearing Ratio* - CBR) mencapai $> 95\%$, jauh melampaui pasir alam biasa ($60\% - 75\%$).
- Mengeliminasi kebutuhan penambangan pasir kuarsa sungai alam, melindungi ekosistem sempadan sungai dari abrasi dan erosi.

### B. Material Sementisi Tambahan (*Supplementary Cementitious Material* - SCM)
- Penggilingan terak nikel menjadi kehalusan Blaine $4.000 \text{ cm}^2/\text{g}$ mengaktifkan sifat pozzolanik laten.
- Pencampuran dengan aktivator alkali (natrium hidroksida / natrium silikat) menghasilkan matriks polimer anorganik *geopolymer concrete* dengan kuat tekan 28 hari mencapai $45 - 55 \text{ MPa}$.
- Pengurangan jejak karbon semen: Setiap penggantian 1 ton klinker semen Portland dengan terak nikel tergranulasi mereduksi emisi sebesar $0.82 \text{ tCO}_2\text{e}$.

---

## 4. Mekanisme Pelaporan & Penatausahaan SIMPEL KLHK

Sistem Fase 121 menghubungkan penimbangan dan pemanfaatan produk sampingan melalui aksi `DispatchSmelterByproductSlagAction` dan gateway `ByProductResidueOfftakeGateway`:
1. Penerbitan nomor manifest lingkungan unik bertanda tangan digital.
2. Sinkronisasi data tonase dan pihak offtaker ke portal Sistem Pelaporan Elektronik Lingkungan Hidup (SIMPEL KLHK).
3. Pengakuan pendapatan penjualan produk sampingan ke akun buku besar double-entry `min:byproduct_sales_revenue:IDR`.
