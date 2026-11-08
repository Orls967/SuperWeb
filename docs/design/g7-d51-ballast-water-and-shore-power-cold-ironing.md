# G7-D51: Ballast Water Management (BWM Convention) & Shore Power Cold Ironing

## 1. Konteks Regulasi Lingkungan Maritim

Perlindungan ekosistem laut global dari penyebaran spesies asing invasif diatur dalam *International Convention for the Control and Management of Ships' Ballast Water and Sediments, 2004* (BWM Convention). Regulasi ini mewajibkan seluruh armada internasional mematuhi standar pelepasan air balas D-2 menggunakan sistem pengolahan bersertifikat (*Ballast Water Management System - BWMS*).

Selain itu, regulasi emisi pelabuhan mengharuskan kapal yang sandar di dermaga mematikan mesin bantu generator dan menggunakan pasokan listrik dari darat (*High Voltage Shore Connection - HVSC / Cold Ironing*) untuk memangkas polusi udara lokal (SOx, NOx, dan partikulat debu PM2.5).

Keputusan arsitektur **G7-D51** menetapkan standarisasi pencatatan operasi air balas append-only dan integrasi telemetri daya darat cold ironing di pelabuhan.

---

## 2. Standar Regulasi Air Balas: D-1 vs D-2

| Parameter Standar | Standar D-1 (Pertukaran di Laut Lepas) | Standar D-2 (Pengolahan BWMS Terpasang) |
|---|---|---|
| **Mekanisme** | Penggantian air balas di laut terbuka dengan kedalaman minimal 200 meter dan jarak minimal 50 mil laut dari daratan terdekat. | Pengolahan air balas melalui filtrasi fisik, disinfeksi radiasi UV, atau klorinasi elektrokatalisis sebelum pembuangan. |
| **Batas Organisme $(\ge 50\,\mu\text{m})$**| Efisiensi pertukaran volume minimal $95\%$. | $< 10$ organisme hidup per meter kubik air balas. |
| **Batas Organisme $(10 - 50\,\mu\text{m})$**| Tidak ada batasan biologis spesifik. | $< 10$ organisme hidup per mililiter air balas. |
| **Batas Mikroba Indikator**| Tidak diukur secara mikrobiologis. | - *Toxicogenic Vibrio cholerae*: $< 1$ cfu per 100 ml<br>- *Escherichia coli*: $< 250$ cfu per 100 ml<br>- *Intestinal Enterococci*: $< 100$ cfu per 100 ml |
| **Status Kepatuhan 2026**| Ditinggalkan (Fase Transisi Selesai). | **Wajib bagi 100% armada niaga internasional**. |

---

## 3. Logika Validasi & Pencegahan Pelanggaran Pembuangan Air Balas

Algoritma validasi pada `VesselBallastWaterManagementService` menjalankan pemeriksaan ketat:
1. **Verifikasi Keberadaan Sertifikat IBWMC**: Memastikan *International Ballast Water Management Certificate* masih berlaku dan tipe BWMS telah disetujui IMO (*Type Approval Certificate*).
2. **Pemeriksaan Konsentrasi Klorin Bebas / Dosis UV**: Sebelum katup pembuangan air balas (*overboard discharge valve*) dibuka, sensor telemetri harus mencatat dosis UV minimal ($> 250\,\text{mWs/cm}^2$) atau konsentrasi Total Residual Oxidants (TRO) dalam batas aman lingkungan ($< 0.1\,\text{mg/L}$).
3. **Pencatatan Buku Catatan Air Balas Digital (BWRB)**: Setiap operasi pengisian (*ballasting*), pengolahan internal (*internal transfer*), atau pengosongan (*deballasting*) wajib menghasilkan rekaman digital berantai hash anti-manipulasi yang memuat koordinat GPS, volume dalam meter kubik, salinitas, dan tanda tangan perwira jaga.

---

## 4. Alur Kerja Daya Darat Dermaga (Cold Ironing / AMP)

```
                 +-----------------------------------+
                 |      Kapal Sandar di Dermaga      |
                 +-----------------+-----------------+
                                   |
                                   v
                 +-----------------------------------+
                 | Verifikasi Kompatibilitas Sistem |
                 | - Tegangan: 6.6 kV atau 11 kV AC  |
                 | - Frekuensi: 50 Hz atau 60 Hz     |
                 | - Standar: IEC/IEEE 80005-1       |
                 +-----------------+-----------------+
                                   |
                                   v
                 +-----------------------------------+
                 |  Penyambungan Kabel Daya HVSC     |
                 |  & Sinkronisasi Fase Listrik      |
                 +-----------------+-----------------+
                                   |
                                   v
                 +-----------------------------------+
                 | Matikan Mesin Bantu Generator     |
                 | (Auxiliary Engines Black-Shutdown)|
                 +-----------------+-----------------+
                                   |
                                   v
                 +-----------------------------------+
                 | Pencatatan Konsumsi kWh & Emisi   |
                 | - Hitung reduksi emisi Scope 1    |
                 | - Penerbitan sertifikat Green Port|
                 +-----------------------------------+
```

---

## 5. Dampak Ekonomi & Insentif Port Dues Hijau

Armada yang memanfaatkan sistem Cold Ironing di dermaga pelabuhan:
1. **Pembebasan Pajak Emisi Pelabuhan**: Pengurangan emisi gas buang dihitung langsung ke modul ESG sebagai reduksi emisi Scope 1.
2. **Penghematan Biaya Pemeliharaan Mesin**: Mengurangi jam kerja generator diesel kapal (*auxiliary engine running hours*), memperpanjang siklus servis berkala (PMS).
3. **Penerbitan Kredit Hijau Terverifikasi**: Setiap megawatt-jam (MWh) daya listrik darat bersumber energi terbarukan menghasilkan catatan kredit karbon maritim yang diakui secara statutori.

