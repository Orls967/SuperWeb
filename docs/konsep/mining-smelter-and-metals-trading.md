# Konsep Teknis: Smelter Pyrometallurgy, Sistem Resi Gudang & Metals Trading Desk (Fase 121)

## 1. Pendahuluan & Latar Belakang Mandat Hilirisasi Mineral

Fase 121 merupakan puncak dari mandat hilirisasi mineral dalam arsitektur penambangan modern (*Integrated Mine-to-Market Smelter & Metals Trading Ecosystem*) pada Modul Pertambangan (*Mining*). Sesuai dengan amanat Undang-Undang No. 3 Tahun 2020 tentang Perubahan atas UU No. 4 Tahun 2009 tentang Pertambangan Mineral dan Batubara (UU Minerba), Peraturan Pemerintah No. 26 Tahun 2022, serta Kepmen ESDM No. 1827 K/30/MEM/2018, pemegang Izin Usaha Pertambangan Operasi Produksi (IUP OP) wajib melakukan peningkatan nilai tambah mineral mentah di dalam negeri melalui fasilitas pengolahan dan pemurnian (*smelter*).

Sistem hilirisasi mineral terintegrasi Fase 121 mencakup enam pilar operasional dan finansial:
1. **Peleburan Pirometalurgi & Hidrometalurgi**: Pengendalian operasional tanur *Rotary Kiln Electric Arc Furnace* (RKEF) untuk bijih saprolit berkadar tinggi ($> 1.5\% \text{ Ni}$) dan bejana tekan *High Pressure Acid Leach* (HPAL) untuk bijih limonit berkadar rendah ($1.0\% - 1.4\% \text{ Ni}$).
2. **Solver Neraca Massa & Energi Termodinamika (Algoritma M10)**: Solver deterministic untuk menghitung perolehan logam murni (*metal recovery rate*), konsumsi daya listrik spesifik (MWh/ton logam), efisiensi termal tanur busur listrik, dan kuantifikasi emisi GRK Scope 1 dan Scope 2.
3. **Pengujian Laboratorium Mutu Spektrometri XRF/ICP-OES**: Analisis elemental kuantitatif terakreditasi KAN yang memvalidasi kadar logam primer dan batas toleransi pengotor (*impurity thresholds*) sesuai standar pasar fisik *London Metal Exchange* (LME).
4. **Penatausahaan Sistem Resi Gudang (SRG) Digital (Algoritma M12)**: Penerbitan resi gudang terenkripsi yang dicatatkan secara *real-time* ke Pusat Registrasi ISWARE (PT Kliring Berjangka Indonesia / Bappebti Kementerian Perdagangan) dan beragun pembiayaan modal kerja *Supply Chain Financing* (SCF) perbankan nasional.
5. **Trading Desk & Lindung Nilai Bursa LME (Algoritma M11)**: Manajemen portofolio penjualan fisik (*physical offtake*) dan instrumen derivatif lindung nilai (*hedging forward / swap*) dengan mekanisme penilaian harian *Mark-to-Market* (MTM) dan peringatan otomatis panggilan margin variasi (*variation margin call*).
6. **Ekonomi Sirkular & Monetisasi Produk Sampingan**: Pengelolaan terak nikel (*nickel slag*) sebagai agregat infrastruktur jalan dan bahan baku semen ramah lingkungan yang patuh terhadap manifest limbah digital SIMPEL KLHK.

```
+---------------------------------------------------------------------------------------------------+
|                     INTEGRATED SMELTER & METALS TRADING LIFECYCLE (FASE 121)                      |
+---------------------------------------------------------------------------------------------------+
|  [1. PYROMETALLURGY RKEF]    [2. LAB QUALITY ASSAY]     [3. DIGITAL WAREHOUSE]   [4. TRADING DESK]|
|   - Rotary Kiln Calcining     - XRF / ICP-OES Spectrum   - Bappebti ISWARE Reg    - LME Hedging   |
|   - Submerged Arc Smelt       - LME Purity Specs         - Risk Haircut Model M12 - Mark-to-Market|
|   - Energy Balance M10        - Grade Ni / Fe / C / P    - Bank SCF Pledging      - Variation Call|
|   - Scope 1 & 2 GHG Emis      - Cryptographic HMAC       - Digital Seal Lien      - Physical Settle|
+---------------------------------------------------------------------------------------------------+
                                                  │
                                                  ▼
                        [5. FINANCIAL DOUBLE-ENTRY LEDGER POSTING]
                         - Smelter Power Consumption (Expense/Payable)
                         - Refined Metal Capitalization (Inventory/Clearing)
                         - LME MTM Daily Settlement (Cash/Gain/Loss)
                         - Bank SCF Financing Loan (Cash/Liability)
                         - Byproduct Slag Monetization (Receivable/Revenue)
```

---

## 2. Termodinamika & Neraca Massa Peleburan Pyrometallurgical RKEF (Algoritma M10)

Proses peleburan bijih nikel laterit saprolit menjadi *Nickel Pig Iron* (NPI) atau *Ferronickel* (FeNi) pada tanur busur listrik terendam (*submerged arc furnace* 45 MVA) berlangsung pada temperatur operasional $1.500^\circ\text{C} – 1.600^\circ\text{C}$. Transformasi fisik dan kimiawi didasarkan pada reduksi karbotermik selektif oksida-oksida logam oleh karbon batubara atau kokas:

$$\text{NiO} + \text{C} \longrightarrow \text{Ni} + \text{CO} \quad (\Delta H^\circ_{298} = +48.2 \text{ kJ/mol})$$
$$\text{Fe}_2\text{O}_3 + 3\text{C} \longrightarrow 2\text{Fe} + 3\text{CO} \quad (\Delta H^\circ_{298} = +489.8 \text{ kJ/mol})$$
$$\text{FeO} + \text{C} \longrightarrow \text{Fe} + \text{CO} \quad (\Delta H^\circ_{298} = +155.6 \text{ kJ/mol})$$

### A. Diagram Ellingham & Reduksi Selektif
Berdasarkan diagram kestabilan termodinamika Ellingham, afinitas oksigen terhadap nikel lebih rendah daripada afinitasnya terhadap besi dan magnesium:

$$\Delta G^\circ (\text{Ni} \to \text{NiO}) > \Delta G^\circ (\text{Fe} \to \text{FeO}) \gg \Delta G^\circ (\text{Mg} \to \text{MgO}) \approx \Delta G^\circ (\text{Si} \to \text{SiO}_2)$$

Hal ini memungkinkan dilakukannya reduksi selektif di mana hampir seluruh nikel ($90\% - 95\%$) tereduksi ke fasa logam leburan, sementara reduksi besi dikendalikan pada rasio $55\% - 65\%$ untuk menghasilkan paduan NPI dengan kadar nikel komersial $10\% - 15\%$ Ni, dan magnesium serta silika seluruhnya dipertahankan pada fasa terak cair (*liquid slag*).

### B. Prinsip Konservasi Massa (Mass Conservation Law)
Total massa umpan masukan ke dalam tanur (bijih kering, reduktor batubara, flux silika, dan limestone) harus setara secara eksak dengan produk leburan yang dikeluarkan:

$$\sum M_{\text{input}} = M_{\text{ore,dry}} + M_{\text{reductant}} + M_{\text{flux,silica}} + M_{\text{flux,lime}} = M_{\text{metal}} + M_{\text{slag}} + M_{\text{off-gas/dust}} = \sum M_{\text{output}}$$

Umpan basah hasil tambang dikonversi ke tonase kering berdasarkan kadar air bebas (*free moisture*):
$$W_{\text{dmt}} = W_{\text{wmt}} \times \left(1.0 - \frac{\text{Moisture}_{\text{pct}}}{100.0}\right)$$

### C. Neraca Panas & Efisiensi Termal Tanur
Kebutuhan energi total pada tanur RKEF ($E_{\text{theoretical}}$) terdiri dari entalpi reaksi reduksi endotermik ($E_{\text{reduction}}$), panas sensibel leburan logam ($Q_{\text{metal}}$), panas sensibel terak cair ($Q_{\text{slag}}$), dan kehilangan panas konveksi/radiasi dinding tanur ($Q_{\text{loss}}$):

$$E_{\text{theoretical}} = (W_{\text{dmt}} \times 0.485) + (M_{\text{metal}} \times 0.360) + (M_{\text{slag}} \times 0.510) + Q_{\text{radiation}}$$

Efisiensi termal tanur dihitung dari perbandingan kebutuhan teoretis terhadap daya listrik terukur ($P_{\text{actual}}$):
$$\eta_{\text{thermal}} = \left( \frac{E_{\text{theoretical}}}{P_{\text{actual,consumed}}} \right) \times 100\%$$

Sistem mengimplementasikan solver `SmelterPyrometallurgicalEnergyBalanceSolver` yang memverifikasi batas kelayakan termal $\eta_{\text{thermal}} \in [40\%, 96\%]$.

### D. Rasio Basicity Terak & Proteksi Refraktori
Terak nikel laterit didominasi oleh sistem kuaterner $\text{SiO}_2 - \text{MgO} - \text{FeO} - \text{Al}_2\text{O}_3$. Viskositas terak dan keausan bata refraktori magnesite-chrome tanur dikendalikan oleh indeks basicity terak:

$$B = \frac{\% \text{MgO} + \% \text{CaO}}{\% \text{SiO}_2}$$

- **Terak Asam ($B < 0.80$)**: Viskositas rendah namun sangat korosif terhadap dinding refraktori berbasis basa, memerlukan penambahan flux kapur ($\text{CaCO}_3$).
- **Terak Basa ($B > 1.25$)**: Viskositas tinggi yang menyebabkan pembusaan terak (*slag foaming*) dan *metal entrainment*, memerlukan penambahan flux silika ($\text{SiO}_2$).
- **Rentang Netral Optimal ($0.85 \le B \le 1.15$)**: Memastikan pemisahan fasa logam-terak yang sempurna (*sharp phase coalescence*) dengan konsumsi energi minimal.

---

## 3. Emisi Gas Rumah Kaca (GRK) Scope 1/2 & Mekanisme Nilai Ekonomi Karbon

Sesuai dengan Peraturan Presiden No. 98 Tahun 2021 tentang Penyelenggaraan Nilai Ekonomi Karbon dan UU No. 7 Tahun 2021 tentang Harmonisasi Peraturan Perpajakan (UU HPP), fasilitas pengolahan mineral wajib memantau, memitigasi, dan melaporkan inventarisasi emisi GRK secara transparan:

### A. Metodologi Kuantifikasi Emisi GRK
1. **Emisi Scope 1 (Emisi Pembakaran Langsung)**:
   Berasal dari stoikiometri oksidasi karbon reduktor batubara/kokas dan konsumsi bahan bakar diesel genset cadangan:
   $$\text{Scope 1} = (M_{\text{coal}} \times EF_{\text{coal}}) + \left(\frac{V_{\text{diesel}}}{1000} \times EF_{\text{diesel}}\right)$$
   Faktor emisi baku ESDM: Batubara = 2.4200 $\text{tCO}_2\text{e/ton}$, Diesel = 2.6800 $\text{tCO}_2\text{e/kL}$.

2. **Emisi Scope 2 (Emisi Tidak Langsung Kelistrikan)**:
   Berasal dari konsumsi tenaga listrik jaringan interkoneksi transmisi PLN atau pembangkit listrik tenaga uap (*captive coal-fired power plant*):
   $$\text{Scope 2} = P_{\text{actual,MWh}} \times EF_{\text{grid}}$$
   Faktor emisi jaringan interkoneksi Sulawesi/Maluku: $EF_{\text{grid}} = 0.7900 \text{ tCO}_2\text{e/MWh}$.

3. **Intensitas Emisi Logam & Liabilitas Pajak Karbon**:
   Intensitas emisi per metrik ton produk logam leburan dihitung dengan membagi total emisi terhadap hasil tonase NPI tertuang:
   $$I_{\text{carbon}} = \frac{\text{Scope 1} + \text{Scope 2}}{M_{\text{metal,tapped}}}$$

Sesuai peta jalan transisi energi sektor industri mineral ESDM, ambang batas atas batas emisi (*emission cap*) ditetapkan sebesar $2.50 \text{ tCO}_2\text{e/t metal}$. Bila intensitas melebihi batas tersebut, kelebihan emisi dikenakan tarif pajak karbon:
$$\text{Taxable Excess} = \max(0, \text{Total Emissions} - (M_{\text{metal}} \times 2.50))$$
$$\text{Carbon Tax Liability (IDR)} = \lfloor \text{Taxable Excess} \times \text{Rp } 30.000 \rfloor$$

---

## 4. Sistem Resi Gudang (SRG) Digital & Model Haircut Kolateral (Algoritma M12)

Sistem Resi Gudang (SRG) komoditas nikel dan produk hilir diatur dalam UU No. 9 Tahun 2006 jo. UU No. 9 Tahun 2011 tentang Sistem Resi Gudang. Resi gudang elektronik diterbitkan oleh Pengelola Gudang terakreditasi dan dicatatkan pada Pusat Registrasi ISWARE (PT Kliring Berjangka Indonesia di bawah pengawasan Bappebti Kementerian Perdagangan).

Untuk memanfaatkan resi gudang sebagai agunan fasilitas pinjaman modal kerja *Supply Chain Financing* (SCF) perbankan nasional, sistem menerapkan model perhitungan potongan nilai risiko kolateral dinamis (`WarehouseReceiptDigitalCollateralHaircutModel` M12):

### A. Parameter Risiko Multidimensi
Potongan nilai agunan total ($H_{\text{total}}$) dihitung secara aditif berdasarkan 4 parameter risiko objektif:

$$H_{\text{total}} = \min(50.0\%, \max(10.0\%, H_{\text{base}} + H_{\text{volatility}} + H_{\text{duration}} + H_{\text{assay}}))$$

1. **Haircut Dasar Komoditas ($H_{\text{base}}$)**:
   - $15.0\%$ untuk produk olahan nikel (NPI / FeNi) dengan likuiditas pasar bilateral menengah.
   - $10.0\%$ untuk katoda tembaga murni LME Grade A dengan likuiditas global tinggi.
2. **Haircut Volatilitas Pasar 30 Hari ($H_{\text{volatility}}$)**:
   $$H_{\text{volatility}} = \max(0, (\sigma_{30\text{d}} - 15\%) \times 0.25)$$
   Setiap kenaikan 1% volatilitas tahunan di atas ambang normal 15% menambah haircut sebesar 0.25%.
3. **Haircut Durasi Simpan Gudang ($H_{\text{duration}}$)**:
   $$H_{\text{duration}} = \begin{cases} (T_{\text{days}} - 30) \times 0.05\% & \text{jika } T_{\text{days}} > 30 \\ 0 & \text{lainnya} \end{cases}$$
   Mengakomodasi depresiasi fisik atau risiko oksidasi material yang disimpan lama di gudang.
4. **Haircut Toleransi Variansi Uji Mutu ($H_{\text{assay}}$)**:
   $$H_{\text{assay}} = \max(0, (\Delta_{\text{variance}} - 0.5\%) \times 2.0)$$
   Memberikan penalti risiko jika disparitas hasil uji lab penguji melebihi 0.5%.

### B. Batas Fasilitas Pembiayaan Bank SCF
Nilai kotor agunan (*gross collateral value*):
$$V_{\text{gross}} = M_{\text{certified,tons}} \times P_{\text{unit,market,IDR}}$$

Rasio Loan-to-Value (LTV) efektif dan batas pembiayaan SCF:
$$\text{LTV}_{\text{effective}} = 100\% - H_{\text{total}}$$
$$\text{LTV}_{\text{final}} = \min(\text{LTV}_{\text{effective}}, \text{LTV}_{\text{bank,cap}})$$
$$\text{Credit Limit}_{\text{SCF}} = \lfloor V_{\text{gross}} \times \left(\frac{\text{LTV}_{\text{final}}}{100}\right) \rfloor$$

Ambang batas pemicu eksekusi likuidasi lelang agunan (*liquidation threshold*):
$$V_{\text{liquidation}} = \lfloor \text{Credit Limit}_{\text{SCF}} \times 1.15 \rfloor$$

---

## 5. Metals Trading Desk, Lindung Nilai LME & Valuasi Mark-to-Market (Algoritma M11)

Perdagangan produk logam hasil smelter menghadapi volatilitas harga global bursa *London Metal Exchange* (LME). Trading desk memanfaatkan kombinasi kontrak penjualan fisik (*physical offtake*) dan instrumen berjangka lindung nilai (*hedging forward / swap*):

### A. Struktur Kurva Forward (Contango vs Backwardation)
Spread harga kontrak forward 3-bulan ($P_{3\text{M}}$) terhadap harga tunai penyelesaian (*cash settlement* $P_{\text{Cash}}$):

$$\Delta_{\text{spread}} = P_{3\text{M}} - P_{\text{Cash}}$$

- **Contango ($\Delta_{\text{spread}} \ge 0$)**: Harga forward lebih tinggi daripada harga tunai, mencerminkan biaya simpan (*carrying cost*), pergudangan, asuransi, dan suku bunga pinjaman.
- **Backwardation ($\Delta_{\text{spread}} < 0$)**: Harga tunai lebih tinggi daripada harga forward akibat defisit pasokan fisik segera di gudang LME global.

### B. Valuasi Mark-to-Market (MTM) Harian
Posisi lindung nilai dinilai kembali setiap hari terhadap harga penutupan resmi LME:

$$\Delta P = P_{\text{market,effective}} - P_{\text{entry}}$$
$$\text{PnL}_{\text{USD,cents}} = \text{Sign}_{\text{position}} \times \Delta P \times M_{\text{tons}}$$
$$\text{PnL}_{\text{IDR}} = \lfloor \left(\frac{\text{PnL}_{\text{USD,cents}}}{100}\right) \times \text{Kurs}_{\text{JISDOR}} \rfloor$$

Di mana untuk posisi jual (*short hedge*), $\text{Sign}_{\text{position}} = -1$.

### C. Pemicu Panggilan Margin Variasi (Variation Margin Call)
Setoran margin awal (*initial margin*) dipersyaratkan sebesar $12\%$ dari nilai nosional kontrak, dengan ambang batas margin pemeliharaan (*maintenance margin*) sebesar $8\%$. Jika akumulasi kerugian yang belum direalisasi menyebabkan ekuitas turun di bawah margin pemeliharaan:

$$\text{Buffer}_{\text{margin}} = \text{Margin}_{\text{initial}} - \text{Margin}_{\text{maintenance}}$$
$$\text{Margin Call Triggered} \iff |\text{PnL}_{\text{loss,IDR}}| > \text{Buffer}_{\text{margin}}$$
$$\text{Variation Margin Required} = |\text{PnL}_{\text{loss,IDR}}|$$

---

## 6. Integrasi Buku Besar Double-Entry Finansial (Ledger Posting)

Seluruh aktivitas operasional dan finansial hilirisasi dicatat ke dalam buku besar akuntansi berpasangan (*double-entry financial ledger*):

1. **Konsumsi Listrik Tanur Smelter**:
   - Debit: `expense:min:smelter_electricity:IDR`
   - Kredit: `min:smelter_electricity_payable:IDR`
2. **Kapitalisasi Persediaan Logam Olahan (NPI/FeNi)**:
   - Debit: `min:metals_inventory:IDR`
   - Kredit: `min:joint_production_cost_clearing:IDR`
3. **Penyelesaian Mark-to-Market LME (Gain / Loss)**:
   - Keuntungan: Debit `min:bank_cash:IDR` | Kredit `min:metals_trading_gain:IDR`
   - Kerugian: Debit `min:metals_trading_loss:IDR` | Kredit `min:bank_cash:IDR`
4. **Pencairan Fasilitas Pinjaman Bank SCF Resi Gudang**:
   - Debit: `min:bank_cash:IDR`
   - Kredit: `min:scf_financing_liability:IDR`
5. **Pelunasan Fasilitas Pinjaman Bank SCF & Pelepasan Agunan**:
   - Debit: `min:scf_financing_liability:IDR`
   - Kredit: `min:bank_cash:IDR`
6. **Penjualan Terak Nikel Slag untuk Pemanfaatan Infrastruktur**:
   - Debit: `min:byproduct_receivable:IDR`
   - Kredit: `min:byproduct_sales_revenue:IDR`
