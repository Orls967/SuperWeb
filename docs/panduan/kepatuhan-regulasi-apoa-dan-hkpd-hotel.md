# Pedoman Kepatuhan Regulasi Perhotelan: APOA Keimigrasian & Pajak PBJT HKPD

Dokumen ini memuat landasan hukum, prosedur pematuhan, dan integrasi teknis sistem terhadap regulasi keimigrasian orang asing (APOA) dan pajak daerah (PBJT).

---

## 1. Kepatuhan Pelaporan Orang Asing (APOA — UU No. 6 Tahun 2011)

### 1.1 Dasar Hukum & Mandat Peraturan
Berdasarkan Pasal 72 ayat (1) dan (2) Undang-Undang Republik Indonesia Nomor 6 Tahun 2011 tentang Keimigrasian:
1. Penanggung jawab tempat penginapan wajib memberikan data dan informasi mengenai orang asing yang menginap di tempat penginapannya kepada Pejabat Imigrasi yang berwenang.
2. Penyampaian data dan informasi wajib dilakukan paling lambat dalam kurun waktu 1x24 jam (satu kali dua puluh empat jam) sejak orang asing tersebut mulai menginap.
3. Sanksi pidana kurungan dan denda administratif berlaku bagi pengelola penginapan yang sengaja tidak melaporkan keberadaan orang asing.

### 1.2 Implementasi Teknis & Alur Kerja Sistem (Adapter S1)
- Setiap kali tamu dengan kewarganegaraan non-Indonesia (WNA) melakukan check-in di front desk:
  - Petugas memindai nomor paspor, mencatat tanggal lahir, kebangsaan, dan tanggal kedatangan.
  - Sistem membuat entri di tabel `htl_apoa_reports` dengan status `pending`.
  - Adapter simulasi `ApoaSimulationAdapter` secara otomatis mengirimkan payload terenkripsi ke endpoint gateway Direktorat Jenderal Imigrasi.
  - Laporan yang berhasil diterima mendapatkan status `submitted` dengan nomor lacak resmi (*APOA Tracking Number*).
- Jika dalam 24 jam laporan belum terkirim akibat gangguan jaringan, status laporan otomatis bertransisi menjadi `late` dan memicu notifikasi peringatan eskalasi ke General Manager.

---

## 2. Pemungutan & Penjurnalan Pajak Barang dan Jasa Tertentu (PBJT Jasa Perhotelan)

### 2.1 Ketentuan Hukum UU No. 1 Tahun 2022 (HKPD)
Undang-Undang Nomor 1 Tahun 2022 tentang Hubungan Keuangan antara Pemerintah Pusat dan Pemerintahan Daerah (UU HKPD) mengatur ketentuan pajak hotel:
1. **Objek Pajak PBJT**: Pelayanan yang disediakan oleh hotel, termasuk jasa penyewaan kamar, fasilitas penunjang, dan konsumsi makanan/minuman yang disediakan oleh pengelola hotel.
2. **Dasar Pengenaan Pajak (DPP)**: Jumlah yang dibayarkan kepada hotel oleh konsumen, yang mencakup harga dasar kamar ditambah biaya pelayanan (*service charge*).
3. **Tarif Maksimal**: Tarif PBJT Jasa Perhotelan ditetapkan paling tinggi sebesar 10% (sepuluh persen) sesuai dengan Peraturan Daerah (Perda) kabupaten/kota masing-masing.

### 2.2 Rumus Perhitungan & Penjurnalan Buku Besar
Misalkan tarif sewa kamar dasar adalah $R_0 = 	ext{Rp } 1.000.000$ dan service charge $5\%$:
$$	ext{Service Charge} = 1.000.000 	imes 0,05 = 	ext{Rp } 50.000$$
$$	ext{DPP PBJT} = 1.000.000 + 50.000 = 	ext{Rp } 1.050.000$$
$$	ext{Pajak PBJT (10\%)} = 1.050.000 	imes 0,10 = 	ext{Rp } 105.000$$
$$	ext{Total Tagihan Kamar Tamu} = 1.000.000 + 50.000 + 105.000 = 	ext{Rp } 1.155.000$$

Penjurnalan double-entry otomatis saat Night Audit:
- **Debit**: `htl:guest_ledger:IDR` sebesar Rp 1.155.000
- **Kredit**: `htl:room_revenue:IDR` sebesar Rp 1.000.000
- **Kredit**: `htl:service_charge_payable:IDR` sebesar Rp 50.000
- **Kredit**: `htl:tax_payable:IDR` sebesar Rp 105.000
Total Debit (Rp 1.155.000) tepat seimbang dengan Total Kredit (Rp 1.155.000).
