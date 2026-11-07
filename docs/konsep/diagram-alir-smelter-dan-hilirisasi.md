# Diagram Alir Proses & Rekayasa Metalurgi Hilirisasi Smelter (Fase 121)

## 1. Ikhtisar Diagram Alir Proses (Process Flow Diagram - PFD)

Diagram alir proses ini menggambarkan rangkaian rekayasa pengolahan bijih nikel laterit saprolit dan limonit mulai dari penumpukan bijih mentah (*raw ore stockpile*), kalsinasi reduksi dalam tanur putar (*rotary kiln*), peleburan dalam tanur busur listrik terendam (*submerged arc furnace* RKEF 45 MVA), penuangan logam cair (*tapping*), pencetakan balok ingot *Nickel Pig Iron* (NPI), penanganan terak nikel (*granulated nickel slag*), hingga penatausahaan resi gudang (SRG) dan perdagangan bursa LME.

```
+-----------------------------------------------------------------------------------------------------------------------+
|                                    BLOCK FLOW DIAGRAM (BFD) - SMELTER RKEF LATERIT                                    |
+-----------------------------------------------------------------------------------------------------------------------+
|                                                                                                                       |
|   [Ore Feed Stockpile] ──► [Drying Rotary Drum] ──► [Rotary Kiln Calciner] ──► [Electric Arc Furnace (RKEF)]          |
|       (WMT: 30-35% H2O)        (Pre-dried: 18% H2O)      (Calcined Ore: 900°C)       (Operating Temp: 1550°C)         |
|                                                                                           │                           |
|                                                    ┌──────────────────────────────────────┴──────────────────────┐    |
|                                                    ▼                                                             ▼    |
|                                        [Liquid Metal (NPI)]                                             [Liquid Slag] |
|                                          (Tapping: 1520°C)                                            (Tapping: 1550°C)|
|                                                    │                                                             │    |
|                                                    ▼                                                             ▼    |
|                                        [Ingot Casting Machine]                                       [Slag Granulation]|
|                                         (Cooling & Bundling)                                          (Water Quench)  |
|                                                    │                                                             │    |
|                                                    ▼                                                             ▼    |
|                                        [Spectrometry Lab Assay]                                      [Slag Stockpile] |
|                                         (ICP-OES / XRF Cert)                                          (Cement / Road) |
|                                                    │                                                             │    |
|                                                    ▼                                                             ▼    |
|                                        [Digital Warehouse (SRG)]                                     [SIMPEL KLHK]    |
|                                         (Bappebti ISWARE Reg)                                         (Manifest Residue)|
|                                                    │                                                                  |
|                                                    ▼                                                                  |
|                                        [Metals Trading Desk]                                                          |
|                                         (LME Hedge & SCF Bank)                                                        |
+-----------------------------------------------------------------------------------------------------------------------+
```

---

## 2. Rincian Unit Operasi Pirometalurgi

### A. Unit 100: Pengeringan & Kalsinasi Umpan Bijih (Drying & Calcination)
1. **Rotary Dryer**:
   - Bijih laterit basah dari stockpile dimasukkan dengan laju umpan 120–160 WMT/jam.
   - Gas panas dari *exhaust* tanur kiln dialirkan secara *counter-current* untuk mengurangi kadar air bebas dari $33\%$ menjadi $18\%$.
2. **Rotary Kiln Calciner**:
   - Bijih kering dicampur dengan reduktor batubara/kokas ($10\% - 12\%$ dari berat bijih) dan pemanas burner berbahan bakar batubara halus.
   - Suhu material ditingkatkan hingga $900^\circ\text{C} - 950^\circ\text{C}$ untuk melepaskan air kristal (*loss on ignition* - LOI) dan mereduksi sebagian besi trivalen ($\text{Fe}_2\text{O}_3 \to \text{FeO}$).
   - Kalsin panas (*hot calcine*) dipindahkan secara terisolasi termal ke bin penyimpan di atas tanur listrik.

### B. Unit 200: Peleburan Reduksi Tanur Busur Listrik (Submerged Arc Furnace - SAF)
1. **Karakteristik Tanur 45 MVA**:
   - Tiga elektroda Söderberg self-baking berdiameter 1.250 mm tersusun secara delta.
   - Arus listrik bolak-balik dialirkan melalui elektroda yang terendam di dalam lapisan terak, menghasilkan panas joule resistif ($Q = I^2 R t$).
   - Temperatur inti busur mencapai $> 2.000^\circ\text{C}$, mempertahankan temperatur leburan logam pada $1.520^\circ\text{C} \pm 20^\circ\text{C}$ dan terak pada $1.550^\circ\text{C} \pm 20^\circ\text{C}$.
2. **Reaksi Kimia Utama di Zona Krusibel**:
   $$\text{NiO} + \text{C} \longrightarrow \text{Ni} + \text{CO} \quad (\Delta H^\circ_{1500^\circ\text{C}} = +46.8 \text{ kJ/mol})$$
   $$\text{FeO} + \text{C} \longrightarrow \text{Fe} + \text{CO} \quad (\Delta H^\circ_{1500^\circ\text{C}} = +152.4 \text{ kJ/mol})$$
   Reaksi reduksi berlangsung cepat di antarmuka gas-padat-cair, dengan gas $\text{CO}$ keluar menuju sistem penangkap debu *baghouse filter*.

### C. Unit 300: Penuangan Logam & Terak (Tapping & Separation)
1. **Metal Tap Hole**:
   - Lubang sadap logam dibuka menggunakan bor pneumatik dan penusuk busur oksigen (*oxygen lance*).
   - Logam cair NPI mengalir melalui *runner* tahan api berlapis grafit menuju cetakan briket ingot berpendingin air (*pig casting machine*).
2. **Slag Tap Hole**:
   - Terak cair dengan densitas lebih ringan ($\rho \approx 2.8 \text{ t/m}^3$) mengapung di atas leburan logam ($\rho \approx 7.0 \text{ t/m}^3$) dan disadap secara berkala melalui lubang terak di elevasi yang lebih tinggi.
   - Terak cair diarahkan ke unit granulasi air bertekanan tinggi (*high-pressure water jet quenching*), menghasilkan pasir terak berbutir halus ($0.5 - 4 \text{ mm}$).

---

## 3. Rantai Logistik Kustodi: Dari Brankas Gudang Hingga Bursa LME

```
+---------------------------------------------------------------------------------------------------+
|                        CHAIN OF CUSTODY INTEGRATION ARCHITECTURE                                  |
+---------------------------------------------------------------------------------------------------+
|   [FURNACE RUN BATCH] ──► [HMAC Run Seal: SHA256(run_code : ore_feed_wmt)]                       |
|           │                                                                                       |
|           ▼                                                                                       |
|   [LABORATORY ASSAY]  ──► [HMAC Lab Seal: SHA256(assay_number : Ni% : Fe% : specs)]              |
|           │                                                                                       |
|           ▼                                                                                       |
|   [WAREHOUSE RECEIPT] ──► [ISWARE Seal: SHA256(weight : price : haircut : credit_limit)]         |
|           │                                                                                       |
|           ▼                                                                                       |
|   [BANK SCF FACILITY] ──► [Double-Entry Ledger: Debit Cash / Credit SCF Liability]                |
|           │                                                                                       |
|           ▼                                                                                       |
|   [TRADING DESK HEDGE]──► [LME Daily MTM: Cash/3M Contango-Backwardation Spread Valuator]        |
+---------------------------------------------------------------------------------------------------+
```

---

## 4. Parameter Sensorik & Arsitektur Telemetri SCADA

Sistem Fase 121 menerima aliran data telemetri berkecepatan tinggi dari Programmable Logic Controller (PLC) Siemens S7-1500 dan sensor industri terpasang:
- **Termokopel Tipe B / R**: Memantau temperatur refraktori dasar dan dinding samping tanur (ambang batas peringatan: $1.450^\circ\text{C}$, ambang kritis: $1.650^\circ\text{C}$).
- **Sensor Tekanan Diferensial Air Pendingin**: Memantau suplai pendingin mantel tanur (ambang batas minimum: $3.5 \text{ bar}$).
- **Sensor Posisi Elektroda LVDT**: Mengukur kedalaman penetrasi elektroda ke dalam lapisan terak leburan untuk stabilitas busur listrik.
- **Continuous Emission Monitoring System (CEMS)**: Menganalisis konsentrasi gas buang cerobong ($\text{SO}_2, \text{NO}_x, \text{CO}, \text{CO}_2$, dan partikulat debu) secara kontinu untuk pelaporan ke portal SIMPEL KLHK.
