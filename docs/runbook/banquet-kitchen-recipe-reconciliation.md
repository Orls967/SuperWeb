# Runbook Operasional: Rekonsiliasi BOM Resep Banquet & Pengurangan Stok Resto

## 1. Konteks Layanan
Runbook ini memandu Kepala Gudang F&B, Cost Controller, dan Sous Chef dalam melakukan pemotongan stok bahan baku mentah, pelacakan susut bahan (*trimming loss*), dan rekonsiliasi varians aktual konsumsi jamuan makan berskala besar:
- Service Terkait: `Modules\Hotel\Application\Services\HotelBanquetRecipeBomService`
- Action Terkait: `Modules\Hotel\Application\Actions\GenerateBanquetBeoProductionSheetAction`

## 2. Diagram Alur Transaksi Bahan Baku
```
[BEO Disetujui Sales & Klien]
             │
             ▼
[Kalkulasi BOM Resep via BanquetBomExploder]
             │
             ▼
[Penerbitan BanquetBomExplosionCertificate]
             │
             ▼
[Verifikasi Ketersediaan Stok di Gudang Modul Resto]
             │
     ┌───────┴───────┐
     ▼               ▼
[Stok Cukup]    [Stok Kurang]
     │               │
     │               ▼
     │          [Trigger Otomatis Purchase Requisition ke Procurement]
     │               │
     └───────┬───────┘
             ▼
[Eksekusi Pemotongan Batch Stok saat status PRODUCTION_COMMENCED]
             │
             ▼
[Pencatatan COGS Dapur ke Jurnal Akuntansi]
             │
             ▼
[Pasca Acara: Audit Fisik Sisa Bahan & Varians Penuangan Minuman]
             │
             ▼
[Penyesuaian Jurnal Penutup: Real COGS vs Budget COGS]
```

## 3. Prosedur Rekonsiliasi H+1 Pasca-Acara
1. Buka antarmuka **Kitchen Inventory Reconciliation**.
2. Pilih nomor BEO yang telah selesai diselenggarakan.
3. Bandingkan dua kolom data:
   - **Theoretical BOM Consumption (A):** Dihitung otomatis oleh `BanquetBomExploder` berdasarkan porsi terjamin + buffer 5%.
   - **Actual Stock Requisition (B):** Bahan aktual yang ditarik dari gudang selama persiapan.
   - **Returned Unused Stock (C):** Bahan mentah utuh yang dikembalikan ke ruang dingin pendingin (*chiller/freezer*).
4. Hitung Konsumsi Bersih Aktual: $Konsumsi_{\text{net}} = B - C$.
5. Hitung Varians: $Varians = Konsumsi_{\text{net}} - A$.
6. Jika $Varians > 5\%$, sistem mewajibkan penulisan keterangan penyebab varians:
   - Faktor kualitas bahan baku (daging berlemak berlebih sehingga susut pembersihan lebih tinggi).
   - Kesalahan persiapan dapur (*spoilage/burning*).
   - Penambahan jumlah porsi dadakan atas permintaan tamu di lokasi.

## 4. Penanganan Varians Penuangan Bar (Beverage Pour Variance)
Untuk bar minuman terbuka (*open bar*):
1. Hitung jumlah botol kosong yang dikumpulkan oleh Bar Captain.
2. Bandingkan dengan jumlah botol yang dialokasikan pada lembar BEO.
3. Rumus toleransi tumpah/busa bir:
   $$Rasio_{\text{tumpah}} = \frac{Volume_{\text{aktual}} - Volume_{\text{teoritis}}}{Volume_{\text{teoritis}}} \times 100\%$$
4. Bila rasio tumpah $> 7\%$, Cost Controller melakukan wawancara verifikasi dengan Bartender in-charge.

