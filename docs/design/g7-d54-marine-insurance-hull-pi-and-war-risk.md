# G7-D54: Marine Insurance Architecture: Hull & Machinery, P&I Clubs & War Risk Binding

## 1. Konteks Industri Asuransi Maritim Global

Asuransi maritim adalah fondasi proteksi risiko kapal niaga internasional. Kerugian akibat bahaya laut (*perils of the sea*) dikelompokkan ke dalam tiga pilar proteksi yang terpisah namun saling melengkapi:
1. **Hull & Machinery (H&M)**: Melindungi fisik badan kapal, mesin induk, dan instalasi navigasi terhadap kerusakan material, kebakaran, kandas, atau tabrakan.
2. **Protection & Indemnity (P&I)**: Asuransi kewajiban hukum pihak ketiga (*third-party liability*) yang diselenggarakan oleh klub mutual nirlaba (*P&I Clubs* di bawah *International Group of P&I Clubs*), mencakup tanggung jawab pencemaran minyak, klaim cedera awak kapal, dan kerusakan muatan kargo.
3. **War Risk & Piracy Cover**: Perlindungan khusus dari ancaman perang, terorisme, sabotase ranjau laut, dan pembajakan di kawasan perairan berisiko tinggi.

Keputusan arsitektur **G7-D54** menetapkan integrasi modul armada kapal dengan modul asuransi (`Modules\Insurance`), penegakan klausula war risk otomatis berbasis wilayah Joint War Committee (JWC), serta manajemen klaim maritim berantai dokumen.

---

## 2. Klausula Standar Hull & Machinery (Institute Time Clauses Hulls)

Mengadopsi klausula *Institute Time Clauses - Hulls (ITCH 1/10/83)*:

| Klausula / Prinsip | Deskripsi Ketentuan ITCH 1/10/83 | Perlakuan Otomatis Sistem |
|---|---|---|
| **Perils Covered (Klausula 6)** | Bahaya laut, angin ribut, kandas, kebakaran, ledakan boiler, kelalaian nahkoda/kru (*Inchmaree Clause*). | Memverifikasi penyebab klaim terhadap log telemetri kapal dan laporan resmi surveyor. |
| **Deductible / Waralaba (Klausula 12)** | Batas potongan risiko mandiri yang ditanggung pemilik kapal sebelum ganti rugi asuransi dibayarkan. | Sistem otomatis memotong nilai deductible dari total tagihan bengkel galangan perbaikan. |
| **Collision Liability (3/4ths RDC - Klausula 8)** | Menanggung $3/4$ dari kewajiban ganti rugi kerusakan fisik kapal lain akibat tabrakan (*Running Down Clause*). | Sisa $1/4$ kewajiban tabrakan dialihkan otomatis ke polis asuransi P&I Club. |
| **Duty of Assured (Sue & Labour - Klausula 13)** | Kewajiban pemilik kapal untuk mengambil tindakan pencegahan darurat guna meminimalkan kerugian lebih lanjut. | Seluruh biaya sewa kapal pemadam atau tali tunda darurat dijamin pengembaliannya (*indemnity*). |

---

## 3. Asuransi Tanggung Jawab Hukum P&I (Protection & Indemnity)

Klub P&I menanggung kewajiban hukum yang tidak ditutup oleh polis standar H&M:
1. **Pencemaran Lingkungan Laut (Oil Pollution Liability)**: Ganti rugi pembersihan tumpahan minyak dan kompensasi lingkungan hidup sesuai Konvensi Internasional CLC 1992 (*Civil Liability Convention*).
2. **Kompensasi Jiwa & Repatriasi Awak (Crew Liability)**: Tanggungan biaya rawat inap rumah sakit, santunan cacat tetap, dan repatriasi jenazah sesuai standar MLC 2006.
3. **Klaim Kerusakan & Kehilangan Kargo (Cargo Liability)**: Pertanggungjawaban atas kargo basah, rusak busuk karena pendingin mati, atau hilang dalam perjalanan sesuai Aturan Hague-Visby (*Hague-Visby Rules*).
4. **Pengangkatan Kerangka Kapal (Wreck Removal)**: Biaya penyingkiran bangkai kapal yang tenggelam di alur pelayaran sesuai Konvensi Nairobi 2007 (*Nairobi International Convention on the Removal of Wrecks*).

---

## 4. Mekanisme War Risk & Joint War Committee (JWC) Area Binding

```
+---------------------------------------------------------------+
|                      PENETAPAN RUTE VOYAGE                    |
+-------------------------------+-------------------------------+
                                |
                                v
+---------------------------------------------------------------+
| Verifikasi Koordinat Terhadap Daftar Kawasan Berisiko JWC    |
| (Contoh: Teluk Aden, Laut Merah Selatan, Selat Hormuz)        |
+-------------------------------+-------------------------------+
                                |
        +-----------------------+-----------------------+
        |                                               |
        v                                               v
+-------------------------------+       +-------------------------------+
| Berlayar di Luar Kawasan JWC  |       | Memasuki Kawasan Berisiko JWC |
| - Tarif premi standar normal  |       | - Wajib Notice of Transit 48j |
| - Tidak perlu premi tambahan  |       | - Kalkulasi Additional Premium|
+-------------------------------+       +---------------+---------------+
                                                        |
                                                        v
                                        +-------------------------------+
                                        | Pengikatan Polis War Risk AP  |
                                        | - Tarif: 0.15% - 0.50% Nilai  |
                                        | - Jurnal: Debit Beban Asuransi|
                                        | - Konfirmasi Breach Approval  |
                                        +-------------------------------+
```

Layanan `MaritimeInsuranceClaimService` memastikan tidak ada kapal yang memasuki area berisiko tinggi (*Listed High Risk Area*) tanpa pengikatan perlindungan tambahan (*Additional Premium - AP*) yang telah disetujui perusahaan penjamin asuransi (*underwriter approval*).

