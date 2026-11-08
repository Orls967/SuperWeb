# G7-D55: Bunker Procurement, ISO 8217 Quality Standards & Fuel Hedging Derivatives

## 1. Konteks Finansial & Risiko Volatilitas Harga Bahan Bakar Maritim

Biaya bahan bakar bunker (*Bunker Fuel Expenses*) merupakan komponen biaya operasional terbesar dalam pelayaran samudera, menyumbang antara $50\%$ hingga $70\%$ dari total pengeluaran pelayaran (*voyage expenses*). Fluktuasi harga minyak mentah dunia di pasar bursa komoditas (ICE Brent / NYMEX WTI) dan ketatnya spesifikasi sulfur MARPOL Annex VI mengharuskan operator armada menjalankan strategi lindung nilai (*hedging*) serta verifikasi kualitas laboratorium sebelum bahan bakar dibakar di mesin induk.

Keputusan arsitektur **G7-D55** menetapkan standardisasi pengujian laboratorium bunker sesuai ISO 8217, tata kelola instrumen derivatif lindung nilai, dan rekonsiliasi akuntansi mark-to-market.

---

## 2. Parameter Uji Kualitas Laboratorium Standar ISO 8217:2017 (Grade RMG 380)

Sebelum bahan bakar dipompakan dari tongkang bunker (*bunker barge*) ke tangki penyimpanan kapal (*bunker tanks*), sampel representatif disegel (*MARPOL Delivered Sample*) dan dikirim ke laboratorium independen terakreditasi:

| Parameter Kualitas | Batas Spesifikasi ISO 8217 | Satuan Ukuran | Risiko Operasional Mesin Jika Tidak Sesuai |
|---|---|---|---|
| **Kadar Sulfur** | Maksimal $0.50\%$ (Global) / $0.10\%$ (ECA) | $\%$ massa ($m/m$) | Pelanggaran statutori MARPOL Annex VI, penahanan kapal oleh Port State Control (PSC). |
| **Kandungan Air** | Maksimal $0.50\%$ | $\%$ volume ($v/v$) | Penurunan daya mesin, korosi injektor, kavitasi pompa bahan bakar, resiko ledakan boiler. |
| **Viskositas Kinematik ($50^\circ\text{C}$)** | Maksimal $380.0$ | $\text{mm}^2/\text{s}$ ($\text{cSt}$) | Pemanasan awal (*pre-heating*) berlebih, pembentukan karbon berkerak di kepala torak. |
| **Densitas pada $15^\circ\text{C}$** | Maksimal $991.0$ | $\text{kg}/\text{m}^3$ | Pemisahan sentrifugal air dan lumpur (*purifier efficiency*) terganggu jika densitas mendekati air laut. |
| **Titik Nyala (Flash Point)** | Minimal **$60.0$** | $^\circ\text{C}$ | **Pelanggaran Kritis SOLAS**: Resiko ledakan uap bahan bakar di kamar mesin. |
| **Titik Tuang (Pour Point)** | Maksimal $30.0$ | $^\circ\text{C}$ | Minyak membeku di dalam pipa tangki dasar ganda (*solidification*) di perairan dingin. |
| **Katalis Halus (Aluminium + Silikon)** | Maksimal $60.0$ | $\text{mg}/\text{kg}$ ($\text{ppm}$) | Partikel abrasif keras yang mengikis dinding silinder liner dan cincin torak mesin induk (*abrasive wear*). |

Layanan `BunkerProcurementAndHedgingService` memblokir penggunaan bunker secara otomatis bila sertifikat laboratorium menunjukkan pelanggaran parameter kritis (khususnya Flash Point $< 60^\circ\text{C}$ atau Sulfur $> 0.50\%$).

---

## 3. Strategi Lindung Nilai Derivatif (Fuel Hedging Instruments)

Untuk melindungi margin kontrak sewa kapal berjangka panjang (*long-term COA / Time Charter*) dari lonjakan harga BBM:

```
+---------------------------------------------------------------+
|                       STRATEGI HEDGING                        |
+-------------------------------+-------------------------------+
                                |
        +-----------------------+-----------------------+
        |                                               |
        v                                               v
+-------------------------------+       +-------------------------------+
| 1. Bunker Fuel Swap Contract  |       | 2. Bunker Call Option (Cap)   |
| - Mengunci harga tetap (Fixed)|       | - Membayar premi di muka      |
| - Rekonsiliasi selisih tunai  |       | - Proteksi kenaikan harga     |
|   terhadap indeks MOPS 0.5%   |       | - Fleksibilitas saat harga turun|
+-------------------------------+       +-------------------------------+
```

1. **Bunker Swap Contract**:
   - Pemilik kapal menyepakati harga tetap \$620 / MT untuk volume 5.000 MT selama 6 bulan.
   - Jika harga indeks pasar Singapura (*Platts MOPS Marine Fuel 0.5%*) naik ke \$700 / MT, lembaga kliring bursa membayar selisih \$80 / MT kepada pemilik kapal.
   - Jika harga indeks turun ke \$550 / MT, pemilik kapal membayar selisih \$70 / MT kepada lembaga kliring.
2. **Akuntansi Mark-to-Market (MTM)**:
   Seluruh posisi kontrak derivatif yang masih terbuka dievaluasi nilainya pada setiap penutupan buku akhir bulan, dengan keuntungan/kerugian yang belum terealisasi (*unrealized P&L*) dicatat pada akun cadangan ekuitas lindung nilai arus kas (*Cash Flow Hedge Reserve*).

