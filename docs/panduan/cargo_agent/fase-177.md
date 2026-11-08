# Panduan Pengguna: Air Cargo Agent (Fase 177)

Peran **Air Cargo Agent (`cargo_agent`)** bertanggung jawab atas penerimaan fisik kargo udara, pemeriksaan kepatuhan barang berbahaya (Dangerous Goods / DGR), dan penjaminan rantai kustodi muatan sebelum dimuat ke lambung pesawat.

---

## 1. Prosedur Penerimaan Kargo Udara (Intake Desk)

Akses antarmuka operasional kargo melalui rute `/aviation/cargo`:
1. Buat nomor surat muatan udara baru (*Air Waybill* / AWB) dengan format `AWB-{FLIGHT_NUMBER}-{SERIAL}`.
2. Timbang fisik barang dan ukur dimensi kemasan dalam satuan milimeter untuk perhitungan berat volume.
3. Tentukan klasifikasi sifat barang (*Nature of Goods*):
   - Kargo umum (*General Cargo*).
   - Barang mudah rusak / dingin (*Perishables*).
   - Barang berbahaya (*Dangerous Goods*).

---

## 2. Inspeksi Barang Berbahaya (IATA DGR Checker A5)

Bila paket dinyatakan memuat zat atau bahan berbahaya:
1. Centang opsi **Barang Berbahaya (DG)** pada formulir penerimaan.
2. Masukkan **Nomor PBB (UN Number)**, misal: `UN1845` untuk Es Kering (*Dry Ice*), `UN3481` untuk Baterai Litium-Ion.
3. Masukkan **Kelas Bahaya (DG Class)**, misal: `Class 9` (Miscellaneous).
4. Verifikasi bahwa dokumen deklarasi pengirim (*Shipper's Declaration*) telah bertanda tangan resmi.
5. Tekan tombol **Validasi DGR & Terima Kargo**.
6. Sistem memverifikasi batasan kargo terhadap regulasi kabin pesawat penumpang:
   - Jika ditolak: Sistem mengeluarkan pesan `DangerousGoodsRejectedException` dan melarang pemuatan.
   - Jika lolos: Paket berstatus `dg_cleared`, hash rantai kustodi dibuat, dan sistem membukukan piutang kargo pada akun buku besar `avi:cargo_receivable:IDR`.
