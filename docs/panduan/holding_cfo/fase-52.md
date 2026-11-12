# Panduan Pengguna — Holding Chief Financial Officer (CFO)

**Modul**: `Modules\Intercompany` (Fase 52)  
**Sasaran Pengguna**: Direktur Keuangan Grup, VP Corporate Finance, Head of Group Consolidation  
**Tanggal Efektif**: 2026-11-12

---

## 1. Ikhtisar Eksekutif & Struktur Tata Kelola Grup

Sebagai Holding CFO, Anda bertanggung jawab penuh atas keakuratan laporan keuangan konsolidasian grup konglomerasi, alokasi likuiditas terpusat, kepatuhan perpajakan transfer pricing, serta tata kelola kepentingan non-pengendali (*Non-Controlling Interest / NCI*).

Sistem Intercompany Fase 52 menyediakan alat komprehensif bagi kantor CFO:
1. **Registri Hirarki Grup Terpadu**: Visualisasi struktur kepemilikan holding secara real-time, mendeteksi jalur kepemilikan langsung maupun tidak langsung melalui `GroupEntityRegistry`.
2. **Kalkulator Netting Multilateral**: Mereduksi kebutuhan arus kas lintas entitas hingga 60% melalui pembatalan utang sirkular otomatis.
3. **Mesin Konsolidasi Multitier PSAK 65 / IFRS 10**: Menghasilkan neraca konsolidasi berimbang secara matematis (*zero variance balance sheet*) dengan eliminasi saldo timbal balik dan laba persediaan belum terealisasi.
4. **Pintu Penguncian Periode Resmi (*Period Lock Gate*)**: Menjamin kepastian angka laporan keuangan final dengan tanda tangan kriptografis dan pemancaran event domain resmi.

---

## 2. Navigasi Hirarki & Perimeter Konsolidasi

Akses registri entitas melalui menu **Konsolidasi Grup & IC $\to$ Struktur Kepemilikan**:
- Sistem menampilkan diagram pohon kepemilikan (*Consolidation Tree*) yang disusun dari entitas holding utama hingga anak perusahaan tingkat terbawah.
- **Perimeter Konsolidasi Otomatis**:
  - **Konsolidasi Penuh (Full Consolidation)**: Diterapkan otomatis untuk seluruh anak perusahaan dengan kepemilikan efektif $> 50,00\%$ ($> 5.000\text{ bps}$). Seluruh pos aset, liabilitas, pendapatan, dan beban digabungkan $100\%$ baris-demi-baris, dengan porsi minoritas dialokasikan ke pos Ekuitas NCI.
  - **Metode Ekuitas (Equity Method)**: Diterapkan untuk entitas asosiasi dengan kepemilikan $20,00\% - 49,99\%$. Nilai tercatat investasi disesuaikan secara berkala berdasarkan bagian laba/rugi entitas asosiasi sesuai PSAK 15.
  - **Metode Biaya / Nilai Wajar (Cost Method)**: Diterapkan untuk investasi minoritas $< 20,00\%$.
- **Pemeriksaan Sirkularitas**: Sistem secara otomatis memblokir pembentukan relasi kepemilikan saham silang (*cross-holding*) yang melanggar ketentuan hukum perseroan terbatas.

---

## 3. Eksekusi Penguncian Periode Konsolidasi (*Period Lock*)

### Prosedur Akhir Bulan (Closing Procedure)
1. **Pemeriksaan Diskrepansi Bilateral**:
   Sebelum penguncian, pastikan tim akuntansi telah menjalankan penyisiran selisih:
   ```bash
   php artisan group:mismatch-sweep
   ```
   Pastikan perintah menghasilkan status kode `0` (tidak ada pos gantung transaksi penjual tanpa tagihan pembeli).
2. **Eksekusi Roll-Up Konsolidasi & Penguncian**:
   Jalankan perintah penguncian konsolidasi resmi:
   ```bash
   php artisan group:consolidation-lock --period=2026-10 --holding=HOLDING_CORP
   ```
   Perintah ini melakukan:
   - Penggabungan neraca lajur seluruh entitas dalam perimeter konsolidasi penuh.
   - Translasi mata uang asing sesuai PSAK 10 (pos moneter pada kurs penutup, pos laba rugi pada kurs rata-rata, selisih masuk ke *FCTR*).
   - Pembentukan dan penyimpanan jurnal eliminasi saldo resiprokal (AR/AP, pinjaman, penjualan intra-grup, dan laba persediaan belum terealisasi).
   - Kalkulasi atribusi laba bersih untuk Pemilik Entitas Induk dan NCI.
   - Pemancaran event domain resmi: `intercompany.consolidation.locked.v1`.
   - Penerbitan ringkasan neraca konsolidasian berimbang dengan bukti hash integritas SHA-256.

---

## 4. Tata Kelola Dividen NCI & Pemotongan Pajak

Sistem mengatur kaskade pembagian dividen dari anak perusahaan:
1. **Cadangan Wajib Statutori (UU PT No. 40/2007 Pasal 70)**:
   Sistem memvalidasi bahwa minimal 20% modal disetor telah disisihkan ke cadangan wajib sebelum dividen dibagikan dari saldo laba.
2. **Pembebasan Pajak Dividen Induk**:
   Berdasarkan UU Harmonisasi Peraturan Perpajakan (UU HPP), dividen yang mengalir ke entitas holding domestik dibebaskan dari pemotongan PPh 23 (tarif 0%). Jurnal eliminasi otomatis membatalkan dividen intra-grup ini dari laporan laba rugi konsolidasian.
3. **Pembayaran ke Pemegang Saham Minoritas (NCI)**:
   Dividen yang menjadi hak pihak eksternal dikenakan pemotongan PPh 23 (15%) untuk wajib pajak dalam negeri atau PPh 26 (20% / tarif reduksi P3B) untuk investor asing. Saldo bersih dibayarkan sebagai arus kas keluar riil dari rekening bank anak perusahaan.

---

## 5. Optimalisasi Likuiditas Treasury Grup (Netting & Cash Pool)

1. **Multilateral Netting Bulanan**:
   Untuk mengurangi biaya transfer kawat bank dan risiko nilai tukar, treasury mengeksekusi penyelesaian bersih:
   ```bash
   php artisan group:matrix-reconcile
   ```
   Seluruh kewajiban intra-grup diselesaikan melalui satu instruksi kliring per entitas terhadap akun penyelesaian pusat.
2. **Rekening Saldo Nol Terpusat (Cash Pooling)**:
   Setiap sore hari kerja, kelebihan kas di atas batas buffer operasional anak perusahaan disapu ke rekening induk:
   ```bash
   php artisan group:cash-pool-sweep --pool=CPOOL-MAIN --date=2026-10-31
   ```
   Entitas yang mengalami defisit sementara menerima injeksi likuiditas otomatis dengan pengenaan bunga pasar wajar (*arm's length borrowing rate*).

---

## 6. Indikator Kinerja Utama (KPIs) Konsolidasi Grup

Kantor CFO memantau metrik efisiensi konsolidasi berikut setiap penutupan buku:
1. **Days to Close (DTC)**: Target waktu penyelesaian konsolidasi grup dari hari terakhir periode hingga penguncian resmi adalah maksimal $3\text{ hari kerja}$.
2. **Netting Efficiency Ratio (NER)**:
   $$\text{NER} = \frac{\text{Total Kewajiban Bruto} - \text{Total Kas Penyelesaian Bersih}}{\text{Total Kewajiban Bruto}} \times 100\%$$
   Target NER adalah $\ge 60,00\%$, menunjukkan minimal 60% utang sirkular berhasil dieliminasi tanpa transfer kas fisik.
3. **Discrepancy Resolution Rate**: $100\%$ selisih transaksi cermin wajib diselesaikan sebelum penguncian periode; 0 pos gantung tanpa tagihan diizinkan melewati tanggal cut-off.
4. **Effective Tax Rate (ETR) Alignment**: Deviasi ETR konsolidasian terhadap tarif pajak statutori (22%) akibat penyesuaian transfer pricing dan pajak tangguhan tidak boleh melebihi $\pm 150\text{ bps}$.
