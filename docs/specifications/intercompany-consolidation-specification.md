# Spesifikasi Arsitektur & Matematis — Konsolidasi Keuangan & Netting Multilateral

- **ID Dokumen**: `SPEC-FIN-CONSOL-0052`
- **Domain**: Arsitektur Keuangan, Konsolidasi PSAK 65, Teori Graf Multilateral Netting
- **Status**: TERKUNCI (Production Architecture Standard)
- **Tanggal Diperbarui**: 2026-11-12

---

## 1. Landasan Teori Graf Multilateral Netting Intercompany

### 1.1 Representasi Matriks Kewajiban Bruto
Sistem memodelkan jaringan utang-piutang intra-grup sebagai Graf Berarah Terbobot (*Weighted Directed Graph*) $G = (V, E, W)$, di mana:
- $V = \{e_1, e_2, \dots, e_N\}$ adalah himpunan entitas legal grup konglomerasi yang berpartisipasi.
- $E \subseteq V \times V$ adalah himpunan transaksi utang-piutang berarah antar-entitas.
- $W: E \to \mathbb{Z}^+$ adalah bobot kewajiban moneter dalam satuan sen minor units ekuivalen IDR.

Kewajiban disajikan dalam bentuk Matriks Kewajiban $M \in \mathbb{Z}^{N \times N}$, di mana:
$$M_{ij} = \text{kewajiban yang wajib dibayar oleh entitas } e_i \text{ kepada entitas } e_j$$
Sifat invarian dasar graf: $M_{ii} = 0 \quad (\forall i \in \{1, \dots, N\})$.

### 1.2 Algoritma Pembatalan Siklus Utang Multilateral (Cycle Cancellation)
Keberadaan siklus berarah $C = (v_1, v_2, \dots, v_k, v_1)$ dalam graf merepresentasikan utang sirkular di mana kas berputar tanpa mengubah posisi kekayaan bersih entitas.
1. **Identifikasi Siklus**: Menggunakan penelusuran Depth-First Search (DFS) dengan status penandaan tiga warna (*white, gray, black*) untuk mendeteksi tepi balik (*back-edges*).
2. **Kalkulasi Nilai Bottleneck**:
   $$\beta = \min_{1 \le m < k} M_{v_m v_{m+1}}$$
3. **Reduksi Aliran Siklus**:
   $$M_{v_m v_{m+1}} \leftarrow M_{v_m v_{m+1}} - \beta \quad (\forall m \in \{1, \dots, k-1\})$$
   $$M_{v_k v_1} \leftarrow M_{v_k v_1} - \beta$$
Proses diulang hingga graf bebas dari siklus dengan bobot positif ($M$ menjadi Directed Acyclic Graph / DAG yang tereduksi).

### 1.3 Perhitungan Posisi Bersih & Invarian Konservasi Nol
Untuk setiap entitas $e_k \in V$:
$$\text{Total Utang Bruto (Gross Payable)}_k = \sum_{j=1}^N M_{kj}$$
$$\text{Total Piutang Bruto (Gross Receivable)}_k = \sum_{i=1}^N M_{ik}$$
$$\text{Posisi Bersih (Net Position)}_k = \text{Gross Receivable}_k - \text{Gross Payable}_k$$

**Teorema Invarian Konservasi Nol (Zero-Sum Invariant)**:
$$\sum_{k=1}^N \text{Net Position}_k = \sum_{k=1}^N \sum_{i=1}^N M_{ik} - \sum_{k=1}^N \sum_{j=1}^N M_{kj} = 0$$
Bukti: Setiap elemen $M_{ij}$ muncul tepat satu kali dengan tanda positif pada penjumlahan piutang dan satu kali dengan tanda negatif pada penjumlahan utang.

### 1.4 Algoritma Pencocokan Kas Kliring Minimal (Two-Pointer Greedy Settlement)
Untuk menyelesaikan posisi bersih dengan jumlah transaksi kawat bank sekecil mungkin:
- Himpunan Debitur Bersih: $D = \{(e_i, |\text{Net Position}_i|) \mid \text{Net Position}_i < 0\}$
- Himpunan Kreditur Bersih: $C = \{(e_j, \text{Net Position}_j) \mid \text{Net Position}_j > 0\}$
- Dua penunjuk melakukan pencocokan serakah:
  $$S = \min(d_{\text{sisa}}, c_{\text{sisa}})$$
  Penerbitan instruksi pembayaran kas dari debitur ke kreditur sebesar $S$.
- Jumlah instruksi transfer kas terbukti tidak akan pernah melebihi $|D| + |C| - 1 \le N - 1$.

---

## 2. Arsitektur Roll-Up Konsolidasi Multitier (PSAK 65 / IFRS 10)

### 2.1 Penelusuran Pohon Konsolidasi Berdasarkan Urutan Topologis
Struktur kepemilikan saham diproses secara *bottom-up* (dari daun ke akar).
1. Setiap entitas diberi peringkat kedalaman (*depth rank*) dari entitas induk utama:
   $$\text{Depth}(\text{Root}) = 0$$
   $$\text{Depth}(e) = \max_{p \in \text{Parents}(e)} (\text{Depth}(p) + 1)$$
2. Proses konsolidasi diurutkan secara menurun berdasarkan $\text{Depth}(e)$ sehingga seluruh entitas anak dan cucu telah terkonsolidasi penuh sebelum entitas induk menggabungkan neraca tingkat atas.

### 2.2 Translasi Valuta Asing Entitas Asing (PSAK 10 / IAS 21)
Untuk anak perusahaan dengan mata uang fungsional selain IDR:
- **Pos Aset dan Liabilitas**: Ditranslasikan pada kurs penutup tanggal pelaporan (*closing spot rate*, $R_{\text{close}}$).
- **Pos Laba Rugi (Pendapatan dan Beban)**: Ditranslasikan pada kurs rata-rata tertimbang periode berjalan (*weighted average rate*, $R_{\text{avg}}$).
- **Pos Ekuitas Modal Saham**: Ditranslasikan pada kurs historis tanggal transaksi (*historical rate*, $R_{\text{hist}}$).
- **Selisih Translasi Valuta Asing (FCTR)**:
  $$\Delta\text{FCTR} = \text{Total Aset Translated} - (\text{Total Liabilitas Translated} + \text{Total Ekuitas Translated})$$
  Dialokasikan ke pos Pendapatan Komprehensif Lain (*Other Comprehensive Income / OCI*) dalam ekuitas konsolidasian.

### 2.3 Eliminasi Saldo dan Transaksi Resiprokal
Setiap pos akun timbal balik dieliminasi secara simultan:
1. **Piutang & Utang Usaha Intra-Grup**:
   $$\text{Debit: IC Accounts Payable} \quad E_{\text{AP}}$$
   $$\text{Kredit: IC Accounts Receivable} \quad E_{\text{AR}}$$
   $$\text{di mana } E = \min(\text{IC AP Total}, \text{IC AR Total})$$
2. **Penjualan & Pembelian Intra-Grup**:
   $$\text{Debit: IC Sales Revenue} \quad S$$
   $$\text{Kredit: IC Cost of Goods Sold} \quad S$$
3. **Pinjaman Intra-Grup**:
   $$\text{Debit: IC Loans Payable} \quad L$$
   $$\text{Kredit: IC Loans Receivable} \quad L$$

### 2.4 Eliminasi Laba Belum Terealisasi pada Persediaan Akhir
Apabila entitas penjual membebankan mark-up keuntungan atas barang yang masih tersimpan dalam persediaan akhir entitas pembeli:
$$\text{Laba Belum Terealisasi (Unrealized Profit)} = \text{Nilai Persediaan Akhir} \times \frac{\text{Margin Bps}}{10.000}$$
- **Jurnal Eliminasi Konsolidasi**:
  $$\text{Debit: Saldo Laba Konsolidasian (Retained Earnings)} \quad U$$
  $$\text{Kredit: Persediaan Konsolidasian (Inventory)} \quad U$$
- **Pengakuan Aset Pajak Tangguhan (Deferred Tax Asset / DTA)**:
  Karena perbedaan temporer nilai tercatat persediaan komersial vs dasar pengenaan pajak:
  $$\text{DTA Nominal} = \left\lfloor\frac{U \times \text{TaxRateBps}}{10.000}\right\rfloor$$
  $$\text{Debit: Aset Pajak Tangguhan (DTA)} \quad \text{DTA Nominal}$$
  $$\text{Kredit: Beban Pajak Penghasilan (Tax Benefit)} \quad \text{DTA Nominal}$$

### 2.5 Atribusi Laba Bersih & Ekuitas Kepentingan Non-Pengendali (NCI)
Sesuai PSAK 65 paragraf B94:
Untuk setiap entitas anak $k$ dengan bobot kepemilikan non-pengendali $\text{NCI Bps}_k$:
$$\text{Laba Bersih NCI}_k = \left\lfloor\frac{\text{Laba Bersih Anak}_k \times \text{NCI Bps}_k}{10.000}\right\rfloor$$
$$\text{Ekuitas NCI}_k = \left\lfloor\frac{\text{Total Ekuitas Bersih Anak}_k \times \text{NCI Bps}_k}{10.000}\right\rfloor$$
Total laba konsolidasian diatribusikan:
$$\text{Laba Bersih Konsolidasian} = \text{Laba Bagian Induk} + \sum_{k} \text{Laba Bersih NCI}_k$$

---

## 3. Spesifikasi Tanda Tangan Integritas Kriptografis

Setiap hasil eksekusi konsolidasi menghasilkan bukti integritas deterministik:
$$\text{Seed} = \text{RunId} \parallel \text{Period} \parallel \text{HoldingCode} \parallel \text{Assets} \parallel \text{Liabilities} \parallel \text{NetProfit} \parallel \text{JournalCount}$$
$$\text{IntegrityHash} = \text{HMAC-SHA256}(\text{Seed}, \text{SecretKey})$$
Hash ini disimpan secara persisten dan divalidasi pada setiap audit berkala untuk membuktikan tidak ada modifikasi data pasca-penguncian.

---

## 4. Contoh Numerik Kertas Kerja Konsolidasi Lengkap (Consolidation Worksheet)

Misalkan Induk (`HOLDING_CORP`) memiliki $80,00\%$ ($8.000\text{ bps}$) saham Anak (`ENT_MANUF_01`), dengan Kepentingan Non-Pengendali (NCI) sebesar $20,00\%$ ($2.000\text{ bps}$).

### 4.1 Neraca Lajur Individual Sebelum Eliminasi (dalam Rupiah)
| Akun | Induk (HOLDING_CORP) | Anak (ENT_MANUF_01) | Gabungan Bruto | Eliminasi Debit | Eliminasi Kredit | Neraca Konsolidasi |
|:---|---:|---:|---:|---:|---:|---:|
| **Kas & Setara Kas** | 500.000.000 | 200.000.000 | 700.000.000 | - | - | 700.000.000 |
| **Piutang Pihak Ketiga** | 300.000.000 | 150.000.000 | 450.000.000 | - | - | 450.000.000 |
| **Piutang Intercompany** | 200.000.000 | - | 200.000.000 | - | (1) 200.000.000 | 0 |
| **Persediaan Barang** | 150.000.000 | 350.000.000 | 500.000.000 | - | (3) 25.000.000 | 475.000.000 |
| **Pinjaman IC Diberikan** | 500.000.000 | - | 500.000.000 | - | (2) 500.000.000 | 0 |
| **Aset Tetap Bersih** | 1.000.000.000 | 800.000.000 | 1.800.000.000 | - | - | 1.800.000.000 |
| **Aset Pajak Tangguhan** | - | - | 0 | (4) 5.500.000 | - | 5.500.000 |
| **Total Aset** | **2.650.000.000** | **1.500.000.000** | **4.150.000.000** | **5.500.000** | **725.000.000** | **3.430.500.000** |
| **Utang Pihak Ketiga** | 250.000.000 | 180.000.000 | 430.000.000 | - | - | 430.000.000 |
| **Utang Intercompany** | - | 200.000.000 | 200.000.000 | (1) 200.000.000 | - | 0 |
| **Pinjaman IC Diterima** | - | 500.000.000 | 500.000.000 | (2) 500.000.000 | - | 0 |
| **Modal Saham Induk** | 1.500.000.000 | - | 1.500.000.000 | - | - | 1.500.000.000 |
| **Saldo Laba Induk** | 900.000.000 | - | 900.000.000 | (3) 19.500.000 | - | 880.500.000 |
| **Ekuitas NCI (20%)** | - | 620.000.000 | 620.000.000 | - | - | 620.000.000 |
| **Total Liabilitas & Ekuitas**| **2.650.000.000** | **1.500.000.000** | **4.150.000.000** | **719.500.000** | **-** | **3.430.500.000** |

*Keterangan Jurnal Eliminasi:*
- (1) Eliminasi piutang-utang usaha intra-grup: Rp 200.000.000.
- (2) Eliminasi saldo pokok pinjaman intra-grup: Rp 500.000.000.
- (3) Eliminasi laba belum terealisasi penjualan downstream persediaan akhir: Rp 25.000.000.
- (4) Pengakuan aset pajak tangguhan atas laba persediaan belum terealisasi (tarif 22%): Rp 5.500.000.
- **Pemeriksaan Keseimbangan**: Total Aset Konsolidasian (Rp 3.430.500.000) TEPAT SAMA dengan Total Liabilitas & Ekuitas Konsolidasian (Rp 3.430.500.000) dengan varians Rp 0.

---

## 5. Penanganan Valuta Asing & Skema Hedging Intra-Grup

1. **Translasi Mata Uang Moneter vs Non-Moneter**:
   - Pos moneter (kas, piutang, utang) ditranslasikan menggunakan kurs penutup akhir periode.
   - Pos non-moneter yang dinilai pada biaya historis ditranslasikan menggunakan kurs pada tanggal transaksi awal.
2. **Eliminasi Selisih Kurs Transaksi Intra-Grup**:
   Apabila entitas penjual mencatat piutang dalam valuta asing (USD) dan entitas pembeli mencatat utang dalam mata uang fungsional (IDR), selisih laba/rugi selisih kurs yang timbul akibat fluktuasi nilai tukar dieliminasi pada tingkat konsolidasi jika piutang tersebut merupakan bagian dari investasi bersih pada entitas asing (PSAK 10 paragraf 32).
3. **Fasilitas Lindung Nilai Antar-Perusahaan (Intercompany Hedging)**:
   Entitas treasury induk dapat bertindak sebagai *in-house central bank* yang memberikan kontrak berjangka (forward/swap) kepada entitas anak untuk melindungi nilai eksposur mata uang komersial tanpa perlu bertransaksi dengan bank eksternal secara individual.

---

## 6. Rekonsiliasi Laporan Arus Kas Konsolidasian

Dalam menyusun Laporan Arus Kas Konsolidasian (PSAK 2 / IAS 7):
1. **Arus Kas Operasi**:
   Seluruh pembayaran dan penerimaan kas antar-entitas untuk transaksi penjualan barang, jasa bersama, dan royalti dieliminasi 100%. Hanya arus kas yang melibatkan pihak ketiga eksternal yang diakui.
2. **Arus Kas Investasi**:
   Pembelian atau pengalihan aset tetap intra-grup (`ic_inter_entity_transfers`) dieliminasi dari pos pengeluaran modal (*capital expenditures*) konsolidasian.
3. **Arus Kas Pendanaan**:
   Pencairan dan pelunasan pinjaman intra-grup dieliminasi penuh. Pembayaran dividen dari entitas anak ke entitas induk dieliminasi, sedangkan pembayaran dividen ke pemegang saham minoritas (NCI) disajikan sebagai arus kas pendanaan keluar (*financing cash outflow*).

