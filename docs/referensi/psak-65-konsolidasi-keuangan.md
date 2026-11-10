# Referensi Standar Akuntansi: PSAK 65 / IFRS 10 (Laporan Keuangan Konsolidasian)

## 1. Prinsip Pengendalian Tunggal (*Control Principle*)
Sesuai PSAK 65 paragraf 5-7, entitas induk dianggap mengendalikan investee jika dan hanya jika investor memiliki seluruh hal berikut:
1. Kekuasaan atas investee (*power over the investee*): hak yang memberikan kemampuan kini untuk mengarahkan aktivitas relevan, yaitu aktivitas yang secara signifikan mempengaruhi imbal hasil investee.
2. Eksposur atau hak atas imbal hasil variabel (*exposure, or rights, to variable returns*) dari keterlibatannya dengan investee.
3. Kemampuan untuk menggunakan kekuasaannya atas investee untuk mempengaruhi jumlah imbal hasil investor (*link between power and returns*).

Ketika kriteria pengendalian terpenuhi, seluruh aset, liabilitas, ekuitas, pendapatan, beban, dan arus kas dari entitas induk dan entitas anak digabungkan baris demi baris (*line-by-line consolidation*).

---

## 2. Kertas Kerja Konsolidasi (*Consolidation Work Paper*)
Penyusunan laporan konsolidasi dilakukan melalui kertas kerja konsolidasi 5 kolom:
1. Saldo Percobaan Entitas Induk (*Parent Trial Balance*).
2. Saldo Percobaan Seluruh Entitas Anak (*Subsidiaries Trial Balance*).
3. Kolom Eliminasi Debit (*Elimination Debit*).
4. Kolom Eliminasi Kredit (*Elimination Credit*).
5. Saldo Konsolidasi Akhir (*Consolidated Balance*).

### Tabel Siklus Kertas Kerja Konsolidasi

| Akun Laporan Keuangan | Induk (IDR) | Anak A (IDR) | Eliminasi Debit | Eliminasi Kredit | Konsolidasi (IDR) |
|---|---|---|---|---|---|
| Kas dan Setara Kas | 50.000.000 | 20.000.000 | - | - | 70.000.000 |
| Piutang Pihak Ketiga | 35.000.000 | 15.000.000 | - | - | 50.000.000 |
| **Piutang Intercompany** | 10.000.000 | - | - | 10.000.000 | **0** |
| Persediaan Barang | 40.000.000 | 25.000.000 | - | 2.500.000 | 62.500.000 |
| Aset Tetap Neto | 150.000.000 | 80.000.000 | - | - | 230.000.000 |
| **Total Aset** | **285.000.000** | **140.000.000** | - | **12.500.000** | **412.500.000** |
| Hutang Pihak Ketiga | 45.000.000 | 20.000.000 | - | - | 65.000.000 |
| **Hutang Intercompany** | - | 10.000.000 | 10.000.000 | - | **0** |
| Ekuitas Saham Induk | 200.000.000 | - | - | - | 200.000.000 |
| Ekuitas Saham Anak | - | 100.000.000 | 100.000.000 | - | **0** |
| Ekuitas Non-Controlling Interest | - | - | - | 20.000.000 | 20.000.000 |
| Saldo Laba Ditahan Konsolidasi | 40.000.000 | 10.000.000 | 10.000.000 | 2.500.000 | 127.500.000 |
| **Total Liabilitas & Ekuitas** | **285.000.000** | **140.000.000** | **120.000.000** | **32.500.000** | **412.500.000** |

---

## 3. Penanganan Transaksi Hilir (*Downstream*) vs Transaksi Hulu (*Upstream*)
Dalam perlakuan laba antar-perusahaan yang belum terealisasi (*unrealized profit*):
1. **Transaksi Hilir (Downstream: Induk menjual ke Anak)**:
   - Keuntungan intra-grup diakui sepenuhnya di buku induk.
   - Eliminasi laba belum terealisasi dibebankan 100% terhadap laba entitas induk, sehingga tidak mempengaruhi hak kepentingan non-pengendali (NCI).
2. **Transaksi Hulu (Upstream: Anak menjual ke Induk)**:
   - Keuntungan intra-grup dicatat di buku entitas anak.
   - Eliminasi laba belum terealisasi dialokasikan secara proporsional antara hak entitas induk dan hak kepentingan non-pengendali (NCI) sesuai persentase kepemilikan saham.

---

## 4. Prosedur Audit Konsolidasi Kepatuhan
1. **Verifikasi Saldo Nol**: Setiap akun resiprokal (`ic:ar` dan `ic:ap`) wajib bersaldo Rp 0 pada neraca konsolidasi final.
2. **Verifikasi Transaksi Transit**: Jika terdapat saldo transit di akun kliring konsolidasi, auditor wajib memverifikasi dokumen pengiriman fisik (Surat Jalan/POD) dan rekening koran mutasi bank bulan berikutnya.
3. **Verifikasi Proporsi NCI**: Nilai ekuitas NCI wajib diverifikasi silang terhadap persentase kepemilikan saham resmi pada akta notaris perubahan anggaran dasar terakhir anak perusahaan.

