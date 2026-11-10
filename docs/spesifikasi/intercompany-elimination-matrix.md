# Spesifikasi Teknis: Matriks Eliminasi Bilateral & Pelaporan Segmen Konsolidasi

**Namespace**: `Modules\Intercompany`  
**Fase**: Fase 52 — Intercompany, Transfer Pricing & Konsolidasi  

---

## 1. Konsep Matriks Eliminasi Bilateral $(N \times N)$
Dalam grup konglomerasi dengan $N$ entitas anak, setiap transaksi timbal balik dapat direpresentasikan dalam matriks persegi berdimensi $N \times N$, di mana baris merepresentasikan entitas penjual / kreditur dan kolom merepresentasikan entitas pembeli / debitur:

$$M_{ij} = \text{Saldo Transaksi dari Entitas } i \text{ ke Entitas } j$$
Dengan syarat invarian:
1. Elemen diagonal utama selalu nol: $M_{ii} = 0 \quad (\forall i \in \{1, \dots, N\})$, karena entitas tidak dapat bertransaksi dengan dirinya sendiri.
2. Saldo Piutang $AR_{ij}$ pada pembukuan entitas $i$ wajib berpasangan dengan Saldo Hutang $AP_{ji}$ pada pembukuan entitas $j$:
   $$AR_{ij} = AP_{ji}$$
3. Total Eliminasi Piutang Grup:
   $$E_{AR} = \sum_{i=1}^N \sum_{j=1, j \ne i}^N AR_{ij}$$
4. Total Eliminasi Hutang Grup:
   $$E_{AP} = \sum_{j=1}^N \sum_{i=1, i \ne j}^N AP_{ji}$$
5. Invarian Keseimbangan Konsolidasi:
   $$E_{AR} = E_{AP} \implies \Sigma \text{Debit} - \Sigma \text{Kredit} = 0$$

---

## 2. Struktur Pelaporan Segmen Usaha Terkonsolidasi (Segment Reporting)
Berdasarkan PSAK 5 (IFRS 8 - Segmen Operasi), entitas konglomerasi wajib melaporkan kinerja keuangan berdasarkan lini usaha operasional dan wilayah geografis.

### A. Segmen Operasi Grup
Grup konglomerasi membagi operasinya ke dalam 5 segmen utama:
1. **Manufaktur & Energi (`SEG_MFG`)**: Pabrik produksi, pembangkit listrik industri, dan perakitan mesin.
2. **Logistik & Rantai Pasok (`SEG_LOG`)**: Armada angkutan darat/laut/udara, pergudangan sentral (*Distribution Center*), dan operasional pelabuhan.
3. **Retail & Store (`SEG_RTL`)**: Jaringan toko swalayan, gerai ritel, dan e-commerce.
4. **Restoran & Cloud Kitchen (`SEG_RST`)**: Jaringan restoran, kafe, dan sentra dapur awan.
5. **Properti & Mall (`SEG_PRP`)**: Pusat perbelanjaan, penyewaan ruang komersial, dan perhotelan.

### B. Rumus Konsolidasi Segmen
Untuk setiap segmen $S$:
1. **Pendapatan Eksternal**: Penjualan murni ke pihak ketiga independen.
2. **Pendapatan Inter-Segmen**: Penjualan ke segmen lain di dalam grup.
3. **Pendapatan Kotor Segmen**:
   $$\text{TotalRevenue}_S = \text{ExternalRevenue}_S + \text{InterSegmentRevenue}_S$$
4. **Kolom Eliminasi**:
   $$\text{EliminationRevenue} = -\sum_{S} \text{InterSegmentRevenue}_S$$
5. **Pendapatan Konsolidasi Grup**:
   $$\text{ConsolidatedRevenue} = \sum_{S} \text{TotalRevenue}_S + \text{EliminationRevenue} = \sum_{S} \text{ExternalRevenue}_S$$

---

## 3. Analisis FAR (Functions, Assets, Risks) Dokumen Transfer Pricing
Sebagai bagian dari pemenuhan draf Local File Transfer Pricing (PMK 172/2023 Bab III):
1. **Analisis Fungsi (Functions)**:
   - Fungsi Riset & Pengembangan (R&D): Dilakukan terpusat oleh Holding.
   - Fungsi Manufaktur: Pengadaan bahan baku, produksi, pengendalian kualitas (dilakukan oleh SUB_A).
   - Fungsi Pemasaran & Distribusi: Penjualan, pergudangan, layanan purna jual (dilakukan oleh SUB_B).
2. **Analisis Aset (Assets)**:
   - Aset Berwujud: Mesin pabrik, armada truk, gedung gudang.
   - Aset Tidak Berwujud: Merek dagang, paten teknologi, formula produk.
3. **Analisis Risiko (Risks)**:
   - Risiko Pasar & Fluktuasi Harga: Ditanggung oleh entitas pembeli/distributor.
   - Risiko Kredit: Ditanggung oleh entitas penjual.
   - Risiko Valuta Asing: Dilindung nilai melalui kontrak forward Treasury.
