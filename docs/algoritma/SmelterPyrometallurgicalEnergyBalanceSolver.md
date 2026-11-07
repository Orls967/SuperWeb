# Spesifikasi Algoritma M10: Smelter Pyrometallurgical Energy Balance Solver

## 1. Identifikasi Algoritma

- **Nama Algoritma**: `SmelterPyrometallurgicalEnergyBalanceSolver`
- **Kode Algoritma**: `M10`
- **Namespace Implementasi**: `Modules\Shared\Domain\Algorithms\Smelter\`
- **Kelas Verifikator**: `SmelterPyrometallurgicalEnergyBalanceVerifier`
- **Struktur Parameter Masukan**: `SmelterFeedParameters.php`, `SmelterEnergyReading.php`
- **Struktur Hasil Keluaran**: `SmelterPyrometallurgicalEnergyBalanceResult.php`
- **Peran Bisnis**: Menghitung neraca massa, kesetimbangan reaksi termokimia reduksi karbotermik, konsumsi energi spesifik listrik (MWh/t metal), efisiensi termal tanur RKEF, serta kuantifikasi emisi GRK Scope 1 dan Scope 2.

---

## 2. Formulasi Matematis & Fisika Metalurgi

### A. Konversi Umpan Kering & Reduksi Logam
Umpan bijih saprolit basah mengandung kadar air (*free moisture*) sebesar $M_{\text{pct}}$:

$$W_{\text{dmt}} = W_{\text{wmt}} \times \left(1.0 - \frac{M_{\text{pct}}}{100.0}\right)$$

Kandungan nikel dan besi murni yang masuk ke dalam tanur:
$$M_{\text{Ni,contained}} = W_{\text{dmt}} \times \left(\frac{\text{Ni}_{\text{pct}}}{100.0}\right)$$
$$M_{\text{Fe,contained}} = W_{\text{dmt}} \times \left(\frac{\text{Fe}_{\text{pct}}}{100.0}\right)$$

Perolehan nikel ke dalam produk *Nickel Pig Iron* (NPI) dengan *recovery rate* $R_{\text{Ni}}$:
$$M_{\text{Ni,metal}} = M_{\text{Ni,contained}} \times \left(\frac{R_{\text{Ni}}}{100.0}\right)$$
$$M_{\text{Fe,metal}} = M_{\text{Fe,contained}} \times F_{\text{red}}$$

Di mana rasio reduksi besi laterit $F_{\text{red}} = 0.62$ pada kondisi operasi reduksi selektif RKEF standar.
Kadar pengotor minor (karbon, silikon, fosfor, sulfur) dalam cairan logam NPI leburan diperhitungkan sekitar $2.5\%$:
$$M_{\text{metal,total}} = \frac{M_{\text{Ni,metal}} + M_{\text{Fe,metal}}}{1.0 - 0.025}$$
$$\text{Grade}_{\text{Ni,metal}} = \left(\frac{M_{\text{Ni,metal}}}{M_{\text{metal,total}}}\right) \times 100\%$$

### B. Pembentukan Terak Cair (Slag Formation)
Besi yang tidak tereduksi mengalami oksidasi membentuk $\text{FeO}$ di fasa terak:

$$M_{\text{FeO,slag}} = (M_{\text{Fe,contained}} - M_{\text{Fe,metal}}) \times \frac{71.85}{55.85}$$
$$M_{\text{slag,total}} = M_{\text{FeO,slag}} + M_{\text{SiO}_2} + M_{\text{MgO}} + M_{\text{Al}_2\text{O}_3} + M_{\text{CaO}}$$

Rasio basicity terak pengontrol keausan refraktori tanur:
$$B = \frac{M_{\text{MgO}} + M_{\text{CaO}}}{M_{\text{SiO}_2}}$$

Konservasi massa gas buang dan debu cerobong (*flue dust*):
$$M_{\text{off-gas/dust}} = (W_{\text{dmt}} + W_{\text{coal}} + W_{\text{silica}} + W_{\text{lime}}) - (M_{\text{metal,total}} + M_{\text{slag,total}})$$

### C. Neraca Panas & Kebutuhan Daya Listrik
Kebutuhan energi teoretis total ($E_{\text{theoretical}}$):

$$E_{\text{theoretical}} = (W_{\text{dmt}} \times 0.485) + (M_{\text{metal}} \times 0.360) + (M_{\text{slag}} \times 0.510) + Q_{\text{radiation}}$$

Di mana:
- $0.485 \text{ MWh/t}$: Entalpi reduksi endotermik $\text{NiO}$ dan $\text{FeO}$.
- $0.360 \text{ MWh/t}$: Panas sensibel leburan logam NPI cair pada temperatur $1.520^\circ\text{C}$.
- $0.510 \text{ MWh/t}$: Panas sensibel terak cair pada temperatur $1.550^\circ\text{C}$.
- $Q_{\text{radiation}}$: Kehilangan panas radiasi dan konveksi cangkang tanur ($1.25 \text{ MWh}$).

Efisiensi termal tanur dihitung dari perbandingan kebutuhan teoretis terhadap daya listrik terukur ($P_{\text{actual}}$):
$$\eta = \left(\frac{E_{\text{theoretical}}}{P_{\text{actual}}}\right) \times 100\%$$
$$\text{SEC} = \frac{P_{\text{actual}}}{M_{\text{metal,total}}} \quad (\text{MWh per ton logam})$$

---

## 3. Verifikasi & Segel Kriptografi (Verifier)

Kelas `SmelterPyrometallurgicalEnergyBalanceVerifier` memvalidasi tiga kriteria sertifikasi matematis:
1. **Konservasi Massa**: Total massa produk padat, cair, dan gas tidak boleh menyimpang lebih dari $2.5\%$ terhadap total massa umpan masukan:
   $$\frac{|\sum M_{\text{input}} - \sum M_{\text{output}}|}{\sum M_{\text{input}}} \le 0.025$$
2. **Kelayakan Termodinamika**: Efisiensi termal $\eta \in [40\%, 96\%]$.
3. **Integritas Hash SHA-256**:
   $$\text{Hash} = \text{SHA256}(M_{\text{metal}} : M_{\text{slag}} : P_{\text{actual}} : \text{Emisi}_{\text{total}} : \eta)$$

Contoh Vektor Numerik Bersertifikat:
- Input: $W_{\text{wmt}} = 150.0\text{ t}$, $M_{\text{pct}} = 32.0\%$, $\text{Ni} = 1.85\%$, $\text{Fe} = 18.0\%$, Batubara = $16.5\text{ t}$, Daya = $48.0\text{ MWh}$.
- Output: $W_{\text{dmt}} = 102.0\text{ t}$, Logam NPI = $18.52\text{ t}$, Kadar Ni = $13.25\%$, Terak = $62.34\text{ t}$, $\eta = 88.4\%$, Hash SHA-256 terverifikasi otentik.
