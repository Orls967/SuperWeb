# Algoritma: DangerousGoodsEligibilityChecker (A5)

## 1. Lokasi Kode & Arsitektur
- **Kelas Algoritma:** `Modules\Shared\Domain\Algorithms\Aviation\DangerousGoodsEligibilityChecker`
- **Sertifikat:** `Modules\Shared\Domain\Algorithms\Aviation\DangerousGoodsCertificate`
- **Verifier:** `Modules\Shared\Domain\Algorithms\Aviation\DangerousGoodsCertificateVerifier`
- **Hasil:** `Modules\Shared\Domain\Algorithms\Aviation\DangerousGoodsResult`
- **Pillar/Lini:** Lini 26 (Aviasi, Fase 177)
- **Kompleksitas Waktu:** $O(1)$ pencarian indeks tabel aturan PBB (UN Number).
- **Kompleksitas Ruang:** $O(1)$ penyimpanan integer batas gram dan menit.

## 2. Intuisi Bisnis & Regulasi IATA DGR
Pengangkutan kargo udara barang berbahaya (*Dangerous Goods*) tunduk pada regulasi ICAO Technical Instructions dan IATA Dangerous Goods Regulations (DGR):
1. **Nomor PBB (UN Number) & Moda Angkutan:**
   - **PAX (Passenger Aircraft):** Penerbangan yang mengangkut penumpang umum memiliki pembatasan kuantitas paket yang sangat ketat untuk mencegah ledakan, kebakaran, atau kontaminasi beracun di kabin.
   - **CAO (Cargo Aircraft Only):** Penerbangan khusus angkutan kargo tanpa penumpang umum diizinkan membawa paket dalam batas kuantitas yang lebih besar.
   - Mode tidak terdaftar untuk suatu kode UN (seperti baterai litium UN3480 pada penerbangan berpenumpang) dilarang mutlak (`MODE_FORBIDDEN`).
2. **Batas Kuantitas Bersih per Paket:** Paket yang melebihi batas gram yang diizinkan untuk moda terkait ditolak dengan alasan `QTY_OVER_LIMIT`.
3. **Barang Berpendingin & Dry Ice (UN1845):** Komoditas farmasi atau bahan biologis beku yang menggunakan es kering (*dry ice*) atau kontainer berinsulasi termal ULD (*Unit Load Device*) harus memiliki durasi daya tahan pendinginan (*holdover time*) minimal sama dengan durasi sektor penerbangan ditambah cadangan darurat 120 menit:
   $$T_{\text{holdover}} \ge D_{\text{sector}} + 120\text{ menit}$$
   Bila kurang, muatan ditolak dengan alasan `HOLDOVER_SHORT` untuk mencegah kerusakan muatan akibat pemanasan (*thermal runaway* / pembusukan).

## 3. Tabel Aturan Standar (Asumsi Simulasi)
| UN Number | Nama Barang | Mode PAX (Maks Gram) | Mode CAO (Maks Gram) | Catatan |
|---|---|---|---|---|
| **UN1993** | Cairan Mudah Terbakar (Flammable Liquid, n.o.s.) | 5.000 g | 60.000 g | Pembatasan kemasan Kelas 3 |
| **UN3480** | Baterai Litium-Ion (Lithium Ion Batteries) | Dilarang (`MODE_FORBIDDEN`) | 35.000 g | Khusus pesawat kargo murni |
| **UN1845** | Karbon Dioksida Padat / Dry Ice | 200.000 g | 200.000 g | Wajib ventilasi dan pemantauan holdover |

## 4. Contoh Hitung Langkah Demi Langkah (Vektor Prompt)
1. **UN1993, Mode PAX, Berat 4.000 g:**
   - Batas PAX untuk UN1993 adalah 5.000 g.
   - Berat $4.000\text{ g} \le 5.000\text{ g} \implies$ Izin diberikan (`isEligible = true`), pelanggaran `[]`.
2. **UN1993, Mode PAX, Berat 6.000 g:**
   - Berat $6.000\text{ g} > 5.000\text{ g} \implies$ Ditolak (`isEligible = false`), pelanggaran `['QTY_OVER_LIMIT']`.
3. **UN3480, Mode PAX, Berat 10.000 g:**
   - Mode PAX tidak terdaftar pada tabel UN3480 $\implies$ Ditolak, pelanggaran `['MODE_FORBIDDEN']`.
4. **UN3480, Mode CAO, Berat 30.000 g:**
   - Batas CAO adalah 35.000 g.
   - Berat $30.000\text{ g} \le 35.000\text{ g} \implies$ Izin diberikan (`isEligible = true`), pelanggaran `[]`.
5. **UN1845, Mode CAO, Berat 150.000 g, Sektor 180 Menit, Daya Tahan ULD 300 Menit:**
   - Kuantitas $150.000\text{ g} \le 200.000\text{ g} \implies$ Kuota lolos.
   - Holdover yang dibutuhkan $= 180 + 120 = 300\text{ menit}$.
   - Daya tahan ULD $300\text{ menit} \ge 300\text{ menit} \implies$ Izin diberikan (`isEligible = true`), pelanggaran `[]`.
6. **UN1845, Mode CAO, Berat 150.000 g, Sektor 180 Menit, Daya Tahan ULD 299 Menit:**
   - Daya tahan ULD $299\text{ menit} < 300\text{ menit} \implies$ Ditolak dengan pelanggaran `['HOLDOVER_SHORT']`.

## 5. Sertifikat & Verifikasi Integritas
Algoritma menerbitkan `DangerousGoodsCertificate` yang mencatat snapshot nomor UN, mode, berat paket, durasi sektor, waktu holdover, status kelayakan, dan daftar pelanggaran yang terurut alfabet. `DangerousGoodsCertificateVerifier` menjamin bahwa muatan lolos hanya jika daftar pelanggaran kosong dan berat paket tidak bernilai negatif.

