# Runbook Operasional — Eskalasi Margin Banquet & BOM Explosion Dapur

## 1. Identifikasi Prosedur & Tujuan
Runbook ini memandu Tim Penjualan MICE (*Meetings, Incentives, Conferences, Exhibitions*), Tim Pembelian (*Procurement*), dan Tim Eksekutif Dapur (*Executive Chef*) dalam menangani skenario eskalasi biaya bahan baku makanan dan penurunan margin kotor kontrak jamuan (*Banquet Event Order / BEO*).

---

## 2. Pemicu Insiden (Incident Triggers)
Runbook ini wajib diaktifkan ketika salah satu kondisi berikut terpenuhi:
1. Proyeksi margin kotor kontrak banquet turun di bawah ambang batas kritis:
   $$\text{Margin\_Kotor} < 40.0\%$$
2. Harga pokok komoditas bahan baku utama (daging sapi impor, makanan laut, telur) mengalami lonjakan harga pasar $> 15\%$ sejak tanggal penandatanganan memorandum penawaran awal.
3. Klien meminta perubahan mendadak (*Late Menu Modification*) kurang dari 72 jam sebelum jamuan dilaksanakan dengan penambahan porsi atau peningkatan bahan premium tanpa persetujuan penyesuaian harga jual per porsi (*Pax Price*).

---

## 3. Matriks Tanggung Jawab & Eskalasi (RACI)

```
+------------------------------------+-------+-------+-------+-------+
| Tahapan Tindakan                   | Sales | Chef  | Purc  | GM    |
+------------------------------------+-------+-------+-------+-------+
| Deteksi Defisit Margin (<40%)      | R     | C     | I     | I     |
| Ledakan Komposisi Resep (BOM)      | I     | R     | C     | I     |
| Pencarian Alternatif Substitusi    | C     | R     | R     | I     |
| Negosiasi Penyesuaian Harga Klien  | R     | I     | I     | C     |
| Otorisasi Kebijakan Khusus GM      | C     | C     | C     | A     |
+------------------------------------+-------+-------+-------+-------+
* R = Responsible, A = Accountable, C = Consulted, I = Informed
```

---

## 4. Prosedur Operasional Standar (Langkah Demi Langkah)

### Langkah 1: Jalankan Ledakan Resep (BOM Explosion)
Jalankan evaluasi resep terpadu melalui sistem kitchen intelligence untuk mengidentifikasi komponen bahan penyumbang kenaikan biaya:
1. Buka modul *Banquet Kitchen Intelligence* pada dasbor BEO.
2. Pilih nomor kontrak acara bersangkutan (contoh: `MICE-BEO-2026-092`).
3. Tekan tombol **Explode Recipe Ingredients**.
4. Sistem menghitung kuantitas bahan netto dikalikan *Yield Loss Factor* dapur:
   $$\text{Gross\_Qty} = \frac{\text{Net\_Qty\_Per\_Portion} \times \text{Pax\_Count}}{1 - \text{Trim\_Loss\_Ratio}}$$

### Langkah 2: Evaluasi Substitusi Bahan Baku (Alternative Sourcing)
Executive Chef berkonsultasi dengan Manajer Pengadaan untuk menjajaki alternatif bahan:
- Apakah terdapat potongan daging alternatif yang setara secara tekstur dan rasa dengan harga pasar lebih stabil?
- Apakah ada hasil tani lokal binaan hotel yang dapat menggantikan sayuran impor berbiaya tinggi?
- Bila substitusi disetujui Chef, perbarui formulasi resep pada sistem dan kalkulasi ulang margin.

### Langkah 3: Pengajuan Otorisasi GM (Dispensasi Margin Rendah)
Bila penyesuaian resep tetap menghasilkan margin kotor $< 40.0\%$ dan klien korporat strategis menolak penyesuaian harga jual per porsi:
1. Sales Manager menyusun memo ringkas alasan strategis (contoh: Kemitraan tahunan bernilai Rp 1,5 Miliar).
2. General Manager meninjau proposal dan menginput PIN otorisasi otentikasi ganda pada sistem BEO.
3. Kontrak berstatus `GM_MARGIN_OVERRIDE_APPROVED` dan diteruskan ke bagian produksi dapur.
