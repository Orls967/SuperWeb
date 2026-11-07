# Spesifikasi Algoritma M12: Warehouse Receipt Digital Collateral Haircut Model

## 1. Identifikasi Algoritma

- **Nama Algoritma**: `WarehouseReceiptDigitalCollateralHaircutModel`
- **Kode Algoritma**: `M12`
- **Namespace Implementasi**: `Modules\Shared\Domain\Algorithms\MetalsTrading\`
- **Kelas Verifikator**: `WarehouseReceiptDigitalCollateralHaircutVerifier`
- **Struktur Hasil Keluaran**: `WarehouseReceiptDigitalCollateralHaircutResult.php`
- **Peran Bisnis**: Menghitung potongan nilai risiko agunan (*dynamic collateral haircut*) atas Resi Gudang (SRG) komoditas logam olahan, menentukan rasio Loan-to-Value (LTV) efektif, batas kredit maksimum pembiayaan *Supply Chain Financing* (SCF) bank, dan ambang batas eksekusi likuidasi agunan.

---

## 2. Formulasi Matematis Model Haircut Kolateral

### A. Komponen Penilaian Risiko Multidimensi
Potongan nilai agunan total ($H_{\text{total}}$) dihitung secara aditif berdasarkan 4 parameter risiko objektif:

$$H_{\text{total}} = \min(50.0\%, \max(10.0\%, H_{\text{base}} + H_{\text{volatility}} + H_{\text{duration}} + H_{\text{assay}}))$$

1. **Haircut Dasar Komoditas ($H_{\text{base}}$)**:
   - $15.0\%$ untuk produk olahan ferronickel dan NPI (likuiditas pasar menengah).
   - $10.0\%$ untuk katoda tembaga murni LME Grade A (likuiditas global tinggi).
2. **Haircut Volatilitas Pasar 30 Hari ($H_{\text{volatility}}$)**:
   $$H_{\text{volatility}} = \max(0, (\sigma_{30\text{d}} - 15\%) \times 0.25)$$
   Setiap kenaikan 1% volatilitas historis tahunan di atas ambang normal 15% menambah haircut sebesar 0.25%.
3. **Haircut Durasi Simpan Gudang ($H_{\text{duration}}$)**:
   $$H_{\text{duration}} = \begin{cases} (T_{\text{days}} - 30) \times 0.05\% & \text{jika } T_{\text{days}} > 30 \\ 0 & \text{lainnya} \end{cases}$$
   Mengakomodasi depresiasi fisik atau risiko oksidasi permukaan logam yang disimpan lama.
4. **Haircut Toleransi Variansi Uji Mutu ($H_{\text{assay}}$)**:
   $$H_{\text{assay}} = \max(0, (\Delta_{\text{variance}} - 0.5\%) \times 2.0)$$
   Memberikan penalti risiko jika disparitas hasil uji lab penguji melebihi 0.5%.

### B. Nilai Agunan & Plafond Pembiayaan Bank SCF
Nilai kotor agunan (*gross collateral value*):
$$V_{\text{gross}} = \lfloor M_{\text{certified,tons}} \times P_{\text{unit,market,IDR}} \rfloor$$

Rasio LTV efektif dan potongan nilai nominal:
$$\text{LTV}_{\text{effective}} = 100\% - H_{\text{total}}$$
$$\text{Deduction}_{\text{IDR}} = \lfloor V_{\text{gross}} \times \left(\frac{H_{\text{total}}}{100}\right) \rfloor$$
$$V_{\text{net}} = V_{\text{gross}} - \text{Deduction}_{\text{IDR}}$$

Plafond kredit pembiayaan maksimum SCF yang dapat ditarik:
$$\text{LTV}_{\text{final}} = \min(\text{LTV}_{\text{effective}}, \text{LTV}_{\text{bank,cap}})$$
$$\text{Credit Limit}_{\text{SCF}} = \lfloor V_{\text{gross}} \times \left(\frac{\text{LTV}_{\text{final}}}{100}\right) \rfloor$$

Ambang batas pemicu eksekusi likuidasi lelang agunan (*liquidation threshold*):
$$V_{\text{liquidation}} = \lfloor \text{Credit Limit}_{\text{SCF}} \times 1.15 \rfloor$$

---

## 3. Verifikasi & Segel Kriptografi (Verifier)

Kelas `WarehouseReceiptDigitalCollateralHaircutVerifier` melakukan validasi otomatis:
1. **Batas Rentang Haircut**: $H_{\text{total}} \in [10.0\%, 50.0\%]$.
2. **Konsistensi LTV**: $\text{LTV}_{\text{effective}} + H_{\text{total}} = 100.0\% \pm 0.01\%$.
3. **Monotonisitas Pembiayaan**: Plafond kredit tidak boleh melampaui nilai bersih agunan ($V_{\text{net}}$):
   $$\text{Credit Limit}_{\text{SCF}} \le V_{\text{net}}$$
4. **Segel Digital Kriptografi SHA-256**:
   $$\text{DigitalSeal} = \text{SHA256}(M_{\text{tons}} : P_{\text{unit}} : V_{\text{gross}} : H_{\text{total}} : \text{CreditLimit} : V_{\text{liquidation}})$$

Contoh Vektor Numerik Bersertifikat:
- Komoditas: NPI, Berat = $100.0\text{ MT}$, Harga = Rp 240.000.000 / ton, Volatilitas = $20.0\%$, Usia Simpan = 10 hari.
- Hasil: $V_{\text{gross}} = \text{Rp } 24.000.000.000$, Haircut $= 16.25\%$, $\text{LTV} = 80.0\%$, Plafond SCF $= \text{Rp } 19.200.000.000$, Segel digital terverifikasi.
