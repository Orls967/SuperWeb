# Spesifikasi Algoritma M11: LME Metal Mark-to-Market Valuator

## 1. Identifikasi Algoritma

- **Nama Algoritma**: `LmeMetalMarkToMarketValuator`
- **Kode Algoritma**: `M11`
- **Namespace Implementasi**: `Modules\Shared\Domain\Algorithms\MetalsTrading\`
- **Kelas Verifikator**: `LmeMetalMarkToMarketVerifier`
- **Struktur Parameter Masukan**: `LmeMarketQuote.php`
- **Struktur Hasil Keluaran**: `LmeMetalMarkToMarketResult.php`
- **Peran Bisnis**: Menghitung valuasi harian posisi lindung nilai (*hedging*) komoditas logam bursa London Metal Exchange (LME), laba/rugi belum terealisasi (*unrealized PnL* USD dan IDR), serta mengevaluasi ambang batas setoran margin variasi (*variation margin call*).

---

## 2. Formulasi Matematis Mark-to-Market & Margin Call

### A. Penyesuaian Harga Pasar Efektif
Harga settlement resmi tunai (*LME cash settlement price*) disesuaikan dengan faktor mutu kadar nikel produk fisik ($K_{\text{quality}}$) dan selisih biaya angkut kargo (*freight differential* $F_{\text{adj}}$):

$$P_{\text{market,effective}} = \lfloor (P_{\text{LME,cash}} \times K_{\text{quality}}) + F_{\text{adj}} \rfloor$$

Di mana:
- $P_{\text{LME,cash}}$: Harga acuan tunai resmi penutupan LME dalam satuan sen dolar per metrik ton.
- $K_{\text{quality}}$: Faktor penyesuaian mutu (misal $1.0$ untuk katoda Grade A atau disesuaikan untuk NPI/FeNi).
- $F_{\text{adj}}$: Premi atau diskonto pengiriman pelabuhan tujuan (CIF Rotterdam / Shanghai).

### B. Kalkulasi PnL Belum Terealisasi (Unrealized PnL)
Untuk posisi lindung nilai jual (*short hedge*), perusahaan produsen menjual kontrak berjangka untuk melindungi risiko depresiasi harga logam fisik:

$$\Delta P = P_{\text{market,effective}} - P_{\text{entry}}$$
$$\text{PnL}_{\text{USD,cents}} = \text{Sign}_{\text{pos}} \times \Delta P \times M_{\text{tons}}$$

Di mana $\text{Sign}_{\text{pos}} = -1$ untuk posisi SHORT, dan $+1$ untuk posisi LONG.
Konversi nilai keuntungan/kerugian ke mata uang rupiah (IDR):
$$\text{PnL}_{\text{IDR}} = \lfloor \left(\frac{\text{PnL}_{\text{USD,cents}}}{100}\right) \times \text{Kurs}_{\text{JISDOR}} \rfloor$$

Persentase hasil laba/rugi terhadap modal masuk (*return on entry notional*):
$$\text{PnL}_{\text{pct}} = \left(\frac{\text{PnL}_{\text{USD,cents}}}{P_{\text{entry}} \times M_{\text{tons}}}\right) \times 100\%$$

### C. Evaluasi Margin Call & Margin Variasi
Berdasarkan aturan kliring bursa berjangka komoditas:
- **Initial Margin Requirement**: $12\%$ dari total nilai nosional kontrak pada harga pasar berjalan.
- **Maintenance Margin Threshold**: $8\%$ dari total nilai nosional kontrak pada harga pasar berjalan.
- **Margin Buffer**:
  $$\text{Buffer}_{\text{margin}} = \text{Margin}_{\text{initial}} - \text{Margin}_{\text{maintenance}}$$

Bila terjadi kerugian kumulatif ($\text{PnL}_{\text{IDR}} < 0$) yang melampaui batas $\text{Buffer}_{\text{margin}}$:
$$\text{IsMarginCallTriggered} = \text{TRUE}$$
$$\text{Variation Margin Required} = |\text{PnL}_{\text{IDR}}|$$

---

## 3. Verifikasi & Segel Kriptografi (Verifier)

Kelas `LmeMetalMarkToMarketVerifier` melakukan pengujian ketat atas:
1. **Konsistensi Arah PnL**: Memastikan arah laba/rugi sesuai dengan jenis posisi SHORT/LONG:
   $$|\text{PnL}_{\text{actual}} - (\text{Sign} \times \Delta P \times M)| \le 10 \text{ Cents}$$
2. **Akurasi Konversi Mata Uang**: Selisih nilai IDR terhadap kurs tukar tidak boleh melebihi toleransi Rp 500.
3. **Logika Pemicu Margin Call**: Margin call hanya boleh aktif bila kerugian melampaui buffer margin pemeliharaan.
4. **Segel HMAC SHA-256**:
   $$\text{Hash} = \text{SHA256}(\text{PositionType} : M_{\text{tons}} : P_{\text{market}} : \text{PnL}_{\text{USD}} : \text{PnL}_{\text{IDR}} : \text{MarginReq} : \text{IsCall})$$

Contoh Vektor Numerik Bersertifikat:
- Posisi: SHORT 100 MT, Entry $16,500/t (1,650,000 cents), Harga LME $16,000/t (1,600,000 cents), Kurs Rp 16.250.
- Hasil: $\text{PnL}_{\text{USD}} = +50,000.00 \text{ USD}$, $\text{PnL}_{\text{IDR}} = +\text{Rp } 812.500.000$, $\text{MarginCall} = \text{FALSE}$.
