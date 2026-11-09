# ADR-0055: Pelindungan Data Medis Pribadi Spesifik dan Hak Akses Terkendali

## Konteks & Masalah
Undang-Undang No. 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP) dan Permenkes 24/2022 mengklasifikasikan data kesehatan pasien sebagai data pribadi spesifik yang wajib dilindungi dari kebocoran dan akses tidak sah. Data medis tidak boleh tersimpan dalam teks terbuka pada basis data maupun tercetak pada log aplikasi.

## Keputusan Arsitektur
1. **Enkripsi At-Rest**: Informasi klinis sensitif (alergi, diagnosa kronis, riwayat bedah, terapi obat) dienkripsi menggunakan envelope encryption Laravel dengan kunci terkelola (`cast` terenkripsi).
2. **Blind Index Pencarian**: Identitas Nomor Induk Kependudukan (NIK) dan nomor paspor dilindungi HMAC blind index untuk memungkinkan query pencarian eksak tanpa mendekripsi seluruh kolom tabel.
3. **Pencatatan Audit Akses**: Setiap pembacaan rekam medis tercatat secara persisten pada tabel `hsp_data_vault_accesses`.
4. **Mekanisme Break-Glass**: Akses darurat oleh dokter di IGD/ICU tanpa persetujuan awal pasien mewajibkan pengisian alasan klinis dan persetujuan retrospektif melalui `ApprovalEngine`.
5. **Retensi Berversi**: Masa retensi rekam medis disimpan sebagai parameter berversi (minimal 25 tahun sesuai Permenkes 24/2022).

## Konsekuensi
- Memenuhi kepatuhan hukum tertinggi pelindungan data pribadi medis.
- Pencarian data medis terbatas pada indeks terstruktur.

