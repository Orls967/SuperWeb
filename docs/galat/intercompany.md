# Register Galat & Kode Kesalahan: Modul Intercompany

**Prefix Galat**: `IC-`  
**Modul**: `Modules\Intercompany` (`ic_`)  

---

| Kode Galat | Pesan Kesalahan Baku | Penyebab Bisnis / Teknis | Tindakan Korektif |
|---|---|---|---|
| `IC-001` | `IDENTICAL_ENTITIES_NOT_ALLOWED` | Entitas penjual dan pembeli bernilai sama pada transaksi intercompany. | Pastikan entitas penjual dan pembeli merupakan badan hukum yang berbeda. |
| `IC-002` | `ENTITY_NOT_FOUND` | Kode entitas tidak terdaftar di tabel master `ic_entities`. | Daftarkan entitas legal terlebih dahulu di master entitas grup. |
| `IC-003` | `ENTITY_INACTIVE` | Entitas legal dalam status non-aktif atau dalam proses likuidasi. | Aktifkan entitas atau gunakan entitas pengganti yang sah. |
| `IC-004` | `INVALID_ARM_LENGTH_MARGIN` | Margin transaksi berada di luar rentang interkuartil PMK 172/2023 tanpa persetujuan komite pajak. | Sesuaikan harga transaksi ke titik median atau ajukan permohonan deviasi ke tim transfer pricing. |
| `IC-005` | `TP_RULE_NOT_FOUND` | Aturan penentuan harga transfer untuk kategori produk ini tidak ditemukan. | Buat aturan transfer pricing baru di `ic_transfer_pricing_rules`. |
| `IC-006` | `INSUFFICIENT_TP_BENCHMARK_DATA` | Jumlah data pembanding independen kurang dari batas minimum statistik ($n < 4$). | Tambahkan data pembanding independen yang relevan dari basis data komersial resmi. |
| `IC-007` | `INVALID_STATE_TRANSITION` | Percobaan transisi status yang melanggar aturan state machine 8 status. | Ikuti alur transisi baku (DRAFT $\to$ SUBMITTED $\to$ ARMS_LENGTH_VALIDATED $\to$ POSTED $\to$ RECONCILED $\to$ ELIMINATED). |
| `IC-008` | `MAKER_CHECKER_VIOLATION` | Operator yang mengajukan transaksi mencoba menyetujui transaksi yang sama. | Validasi dan persetujuan wajib dilakukan oleh pengguna berwenang yang berbeda (*four-eyes principle*). |
| `IC-009` | `MIRROR_POSTING_FAILED` | Gagal membukukan tagihan pembelian cermin di entitas pembeli. | Periksa konfigurasi akun buku besar entitas pembeli dan ulangi proses posting cermin. |
| `IC-010` | `BILATERAL_RECONCILIATION_DISCREPANCY` | Terdapat selisih saldo piutang dan hutang antar pasangan entitas pada tanggal cut-off. | Lakukan rekonsiliasi faktur per faktur untuk menemukan transaksi transit atau nota debit/kredit yang belum tercatat. |
| `IC-011` | `LOAN_REPAYMENT_EXCEEDS_PRINCIPAL` | Jumlah pelunasan pokok pinjaman melebihi sisa saldo pokok pinjaman. | Periksa riwayat pembayaran cicilan dan sesuaikan nominal pelunasan dengan sisa pokok riil. |
| `IC-012` | `INVALID_INTEREST_RATE_BPS` | Suku bunga atau spread pinjaman bernilai negatif atau melebihi batas wajar pasar. | Gunakan suku bunga acuan pasar positif dalam basis points integer. |
| `IC-013` | `WITHHOLDING_TAX_CALCULATION_ERROR` | Gagal menghitung potongan pajak PPh Pasal 23 (15%) atau Pasal 26 (20%). | Pastikan status domisili pajak entitas debitur/kreditur terkonfigurasi dengan benar. |
| `IC-014` | `COST_SHARING_BASIS_INVALID` | Basis alokasi biaya bersama tidak dikenali (bukan headcount atau revenue). | Pilih basis alokasi yang sah: `HEADCOUNT` atau `REVENUE`. |
| `IC-015` | `COST_SHARING_TOTAL_RATIO_MISMATCH` | Total rasio alokasi biaya seluruh entitas penerima tidak sama dengan 100% (100.000 permil). | Sesuaikan rasio alokasi agar jumlah kumulatif seluruh anak tepat 100.000 permil. |
| `IC-016` | `UNREALIZED_PROFIT_CALCULATION_ERROR` | Perhitungan laba persediaan intra-grup menghasilkan angka negatif atau rasio margin tidak valid. | Periksa margin laba kotor awal penjualan intra-grup dan sisa stok fisik di gudang pembeli. |
| `IC-017` | `FCTR_TRANSLATION_RATE_MISSING` | Kurs penutupan atau kurs rata-rata valas anak perusahaan tidak ditemukan pada tanggal pelaporan. | Daftarkan kurs penutupan dan kurs rata-rata di modul Treasury (`ExchangeRateLookup`). |
| `IC-018` | `NCI_TOTAL_OWNERSHIP_MISMATCH` | Penjumlahan kepemilikan saham induk dan non-pengendali tidak sama dengan 100% (100.000 permil). | Koreksi persentase kepemilikan di tabel master anak perusahaan agar total tepat 100%. |
| `IC-019` | `ASSET_TRANSFER_NBV_MISMATCH` | Nilai buku neto aset tetap tidak sesuai dengan harga perolehan dikurangi akumulasi penyusutan. | Sinkronkan catatan subledger aset tetap sebelum melakukan transfer antar-entitas. |
| `IC-020` | `CUSTOMS_DECLARATION_INVALID` | Dokumen pabean ekspor-impor (PEB/PIB) tidak lengkap pada pengiriman stok lintas batas. | Lengkapi nomor pendaftaran dokumen kepabeanan dan perincian bea masuk/PPN impor. |

