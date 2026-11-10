# Dokumentasi Algoritma: `BilateralEliminationMatcher` (D5)

**Namespace**: `Modules\Intercompany\Domain\Algorithms\BilateralEliminationMatcher`  
**Fase Terkait**: Fase 52 — Intercompany, Transfer Pricing & Konsolidasi Keuangan Grup  
**Sertifikat Terkait**: `BilateralEliminationCertificate`, `BilateralEliminationVerifier`  

---

## 1. Intuisi Bisnis & Landasan Teori
Sesuai PSAK 65 (IFRS 10), penyusunan laporan keuangan konsolidasi mewajibkan eliminasi saldo akun timbal balik (*reciprocal balances*) dan transaksi intra-kelompok usaha.
Pada grup konglomerasi berskala besar dengan puluhan entitas anak:
1. Entitas Penjual mencatat Piutang Intercompany ($AR_{\text{seller}}$) dan Pendapatan Penjualan ($Sales_{\text{seller}}$).
2. Entitas Pembeli mencatat Hutang Intercompany ($AP_{\text{buyer}}$) dan Beban Pokok Penjualan / Pembelian ($COGS_{\text{buyer}}$).
3. Jika terdapat diskrepansi antara $AR$ dan $AP$, selisih tersebut harus dipilah secara deterministik: apakah akibat barang dalam perjalanan (*goods-in-transit*), setoran kas dalam perjalanan (*remittance-in-transit*), atau selisih kurs valuta asing.

Algoritma `BilateralEliminationMatcher` melakukan agregasi saldo bilateral berpasangan, mencocokkan dokumen tagihan, mengukur deviasi saldo resiprokal, dan menghasilkan voucher jurnal eliminasi konsolidasi berimbang secara matematis ($\Sigma \text{Debit} = \Sigma \text{Kredit}$).

---

## 2. Formulasi Matematis

### A. Agregasi Saldo Pasangan Bilateral
Untuk setiap pasangan entitas terurut $(A, B)$ dengan $A \ne B$:
- Saldo Piutang $A$ ke $B$:
  $$AR_{A \to B} = \sum_{t \in \text{Tx}(A \to B)} \text{Amount}_{\text{unsettled}}(t)$$
- Saldo Hutang $B$ ke $A$:
  $$AP_{B \to A} = \sum_{t \in \text{Tx}(B \leftarrow A)} \text{Amount}_{\text{unsettled}}(t)$$
- Diskrepansi Saldo Resiprokal:
  $$\Delta_{\text{reciprocal}} = |AR_{A \to B} - AP_{B \to A}|$$

### B. Kriteria Evaluasi & Klasifikasi
Diberikan ambang batas toleransi cut-off $T_{\text{tol}} = 0$ (toleransi absolut nol pada tanggal pelaporan):
1. **EXACT_MATCH**:
   Jika $\Delta_{\text{reciprocal}} = 0$, seluruh saldo $AR_{A \to B}$ dan $AP_{B \to A}$ dieliminasi penuh.
   Nilai eliminasi neraca: $E_{\text{balance}} = AR_{A \to B}$.
2. **TIMING_DIFFERENCE (In-Transit)**:
   Jika $\Delta_{\text{reciprocal}} > 0$ dan selisih terbukti berasal dari transaksi yang diterbitkan menjelang cut-off (dalam rentang waktu transit $\le 3$ hari kerja):
   $$E_{\text{balance}} = \min(AR_{A \to B}, AP_{B \to A})$$
   $$\text{InTransitTransit} = \Delta_{\text{reciprocal}}$$
   Sistem membuat jurnal penyesuaian transit sementara di akun kliring.
3. **DISCREPANCY_UNRESOLVED**:
   Jika $\Delta_{\text{reciprocal}} > 0$ dan transaksi tidak dapat dicocokkan, algoritma menerbitkan peringatan pengecualian (*exception flag*) ke antrean TaskInbox.

### C. Jurnal Eliminasi Berimbang
Untuk setiap pasangan yang disetujui:
- **Eliminasi Neraca (Balance Sheet)**:
  - Debit `ic:ap:A:B:IDR` = $-E_{\text{balance}}$
  - Kredit `ic:ar:B:A:IDR` = $+E_{\text{balance}}$
  - Netto = $-E_{\text{balance}} + E_{\text{balance}} = 0$.
- **Eliminasi Laba Rugi (Income Statement)**:
  - Debit `ic:sales:A:IDR` = $-E_{\text{pnl}}$
  - Kredit `ic:cogs:A:B:IDR` = $+E_{\text{pnl}}$
  - Netto = $-E_{\text{pnl}} + E_{\text{pnl}} = 0$.

---

## 3. Vektor Uji Algoritma

### Vektor 1: Pasangan Tepat Sama (Exact Match Nol Selisih)
- Entitas Penjual: `HOLDING`, Entitas Pembeli: `SUB_A`.
- 3 Faktur Penjual: Rp 5.000.000, Rp 3.000.000, Rp 2.000.000 $\implies AR = 10.000.000$.
- 2 Tagihan Pembeli: Rp 8.000.000, Rp 2.000.000 $\implies AP = 10.000.000$.
- $\Delta = |10.000.000 - 10.000.000| = 0$.
- Hasil: `status = MATCHED_EXACT`, `elimination_amount = 10.000.000`, `discrepancy = 0`.
- Jurnal: Debit AP Rp 10.000.000, Kredit AR Rp 10.000.000 (Netto = 0).

### Vektor 2: Diskrepansi Barang Dalam Perjalanan (In-Transit)
- Penjual: `SUB_A`, Pembeli: `SUB_B`.
- Penjual mencatat pengiriman barang pada 31 Oktober senilai Rp 4.000.000 $\implies AR = 14.000.000$.
- Pembeli baru menerima barang tanggal 2 November $\implies AP = 10.000.000$.
- $\Delta = |14.000.000 - 10.000.000| = 4.000.000$.
- Hasil: `status = IN_TRANSIT_DETECTED`, `matched_amount = 10.000.000`, `in_transit_amount = 4.000.000`.

### Vektor 3: Multi-Entitas Bilateral Matrix (3 Entitas Saling Bertransaksi)
- Pasangan (HOLDING, SUB_A): $AR = 15.000.000, AP = 15.000.000 \implies$ Match 15.000.000.
- Pasangan (SUB_A, SUB_B): $AR = 7.500.000, AP = 7.500.000 \implies$ Match 7.500.000.
- Pasangan (SUB_B, HOLDING): $AR = 3.200.000, AP = 3.200.000 \implies$ Match 3.200.000.
- Total Eliminasi Konsolidasi Grup = Rp 25.700.000.
- Invarian: Total Debit Eliminasi = Total Kredit Eliminasi = Rp 25.700.000.

---

## 4. Sertifikat Hash SHA-256
Sertifikat `BilateralEliminationCertificate` memuat:
- `period`, `entity_pair_count`, `total_matched_elimination_idr`, `total_in_transit_idr`, `unresolved_discrepancy_idr`
- Matriks rincian eliminasi per pasangan entitas
- `certificate_hash` = $\text{SHA-256}(\text{JSON canonical terurut})$
- Diverifikasi oleh `BilateralEliminationVerifier`.
