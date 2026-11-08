# G7-D48: Charter Party Settlement & Laytime Dispute Standard

## 1. Konteks Bisnis & Fondasi Hukum Maritim Internasional

Kontrak sewa kapal niaga (*Charter Party*) merupakan instrumen perikatan komersial tertua dan paling krusial dalam perdagangan maritim global. Perselisihan terkait perhitungan waktu muat/bongkar (*Laytime*) dan klaim denda keterlambatan (*Demurrage*) atau premi efisiensi (*Despatch*) menyumbang lebih dari $65\%$ sengketa hukum di forum arbitrase maritim seperti London Maritime Arbitrators Association (LMAA) dan Singapore Chamber of Maritime Arbitration (SCMA).

Keputusan arsitektur **G7-D48** menetapkan standarisasi kalkulasi deterministik, pencatatan berbasis *Statement of Facts* (SOF) append-only, dan otomatisasi arbitrase sistem terhadap klausula perkapalan internasional yang mengikat antara pemilik kapal (*Shipowner*) dan penyewa (*Charterer*).

---

## 2. Definisi Istilah Standar BIMCO Laytime Definitions 2013

Dalam sistem SuperWeb Port Fleet Management, istilah-istilah berikut memiliki perlakuan invarian kode:

| Istilah | Kepanjangan / Kodifikasi | Definisi Operasional Sistem | Perlakuan Penghitungan Waktu |
|---|---|---|---|
| **NOR** | *Notice of Readiness* | Surat pemberitahuan nahkoda bahwa kapal telah tiba di pelabuhan dan secara fisik siap menerima/membongkar kargo. | Memicu jam hitung mundur masa tenggang (*Turn-Time*). |
| **Turn-Time** | *Free Waiting Period* | Waktu tenggang antara diterimanya NOR oleh agen/charterer hingga dimulainya laytime berjalan (lazimnya 6 jam atau 12 jam). | Waktu ditangguhkan (*excluded from running laytime*). |
| **SHINC** | *Sundays and Holidays Included* | Seluruh hari Minggu dan hari libur resmi di pelabuhan bersangkutan dihitung sebagai waktu kerja normal. | Laytime berjalan tanpa henti $24/7$. |
| **SHEX** | *Sundays and Holidays Excluded* | Hari Minggu dan hari libur resmi dikecualikan dari jam kerja yang diizinkan untuk pemuatan/pembongkaran. | Waktu jam dihentikan dari pukul 17:00 hari sebelumnya hingga 08:00 hari kerja berikutnya. |
| **WWD** | *Weather Working Day* | Hari kerja di mana kondisi cuaca mengizinkan pemuatan/pembongkaran kargo tanpa membahayakan muatan atau kru. | Periode hujan lebat atau angin kencang dicatat sebagai *weather interruption* dan mengurangi laytime terpakai. |
| **Demurrage** | *Liquidated Damages for Detention* | Kompensasi ganti rugi yang dibayar charterer kepada pemilik kapal atas keterlambatan pelepasan kapal melampaui laytime yang disepakati. | Dihitung per jam berdasarkan tarif harian yang tercantum pada *Fixture Recap*. |
| **Despatch** | *Despatch Money* | Insentif yang dibayarkan pemilik kapal kepada charterer bila operasi muat/bongkar selesai sebelum batas laytime habis. | Ditetapkan sebesar $50\%$ dari tarif harian demurrage kecuali jika diperjanjikan lain (*half demurrage*). |

---

## 3. Diagram Alur Penyelesaian Sengketa Laytime

```
                 +-----------------------------------+
                 |    Kapal Tiba & Labuh Jangkar     |
                 +-----------------+-----------------+
                                   |
                                   v
                 +-----------------------------------+
                 |        Tender NOR Diterima        |
                 +-----------------+-----------------+
                                   |
                                   v
                 +-----------------------------------+
                 |     Hitung Mundur Turn-Time       |
                 |     (Contoh: 6 Jam Gratis)        |
                 +-----------------+-----------------+
                                   |
                                   v
                 +-----------------------------------+
                 |       Laytime Mulai Berjalan      |
                 +-----------------+-----------------+
                                   |
         +-------------------------+-------------------------+
         |                                                   |
         v                                                   v
+-------------------------------+                 +-------------------------------+
|  Kondisi Normal / SHINC       |                 |  Interupsi Cuaca Hujan (WWD)  |
|  - Jam berjalan penuh         |                 |  - Validasi SOF Agen Resmi    |
+---------------+---------------+                 |  - Jam dihentikan sementara   |
                |                                 +---------------+---------------+
                +-------------------------+-----------------------+
                                          |
                                          v
                        +-----------------------------------+
                        |   Apakah Laytime Terpakai         |
                        |   Melampaui Batas yang Diizinkan? |
                        +-----------------+-----------------+
                                          |
                        +-----------------+-----------------+
                        |                                   |
                     YA |                                NO |
                        v                                   v
+-----------------------------------------------+ +-----------------------------------------------+
| Status: DEMURRAGE                             | | Status: DESPATCH                              |
| - Prinsip: Once on demurrage, always demurrage| | - Jam sisa dihitung                           |
| - Pengecualian hujan/libur TIDAK BERLAKU LAGI | | - Terbitkan Nota Despatch = 50% Demurrage     |
| - Tagihkan Demurrage Invoice ke Charterer     | | - Kreditkan saldo ke akun charterer           |
+-----------------------------------------------+ +-----------------------------------------------+
```

---

## 4. Spesifikasi Matematis Formula Algoritma A11

1. **Waktu Laytime yang Diizinkan ($L_{\text{allowed}}$)**:
$$L_{\text{allowed}} = \frac{\text{Volume Kargo (Metric Ton)}}{\text{Kecepatan Muat/Bongkar yang Disepakati (MT/Hari)}} \times 24 \quad [\text{jam}]$$
2. **Waktu Laytime Bersih yang Terpakai ($L_{\text{used}}$)**:
$$L_{\text{used}} = (T_{\text{selesai}} - T_{\text{mulai laytime}}) - \sum \Delta t_{\text{interupsi cuaca yang sah}} - \sum \Delta t_{\text{libur SHEX}}$$
3. **Penyelesaian Finansial**:
   - Jika $L_{\text{used}} > L_{\text{allowed}}$:
     $$\Delta t_{\text{demurrage}} = L_{\text{used}} - L_{\text{allowed}}$$
     $$\text{Nilai Tagihan Demurrage} = \frac{\Delta t_{\text{demurrage}}}{24} \times \text{Tarif Harian Demurrage}$$
   - Jika $L_{\text{used}} < L_{\text{allowed}}$:
     $$\Delta t_{\text{despatch}} = L_{\text{allowed}} - L_{\text{used}}$$
     $$\text{Nilai Pembayaran Despatch} = \frac{\Delta t_{\text{despatch}}}{24} \times (0.5 \times \text{Tarif Harian Demurrage})$$

---

## 5. Keputusan Teknis Terikat (Architectural Invariants)

1. **Immutability Statement of Facts**: Catatan waktu SOF ditandatangani secara kriptografis oleh Nahkoda (*Master*) dan Agen Pelabuhan (*Port Agent*). Data log interupsi tidak dapat dimodifikasi setelah status voyage masuk ke tahap verifikasi penagihan.
2. **Koneksi Double-Entry Otomatis**: Setiap penerbitan `FleetDemurrageInvoice` secara otomatis memicu pencatatan jurnal akrual debit pada `prt:receivable:demurrage` dan kredit pada `prt:revenue:freight` (atau pendapatan demurrage khusus).
3. **Pencegahan Sengketa Ganda**: Invoice demurrage memiliki referensi silang terhadap nomor voyage dan charter party ID. Sistem memblokir penerbitan invoice kedua untuk port call yang sama sebelum rekonsiliasi sengketa pertama selesai (*idempotent billing gate*).
