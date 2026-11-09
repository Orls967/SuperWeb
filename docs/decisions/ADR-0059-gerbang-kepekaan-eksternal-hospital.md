# ADR-0059: Gerbang Tunggal Faktor Kepekaan Eksternal HospitalExternalFactors

## Konteks & Masalah
Operasional rumah sakit sangat dipengaruhi faktor eksternal: cuaca ekstrem, lonjakan wabah epidemi (DBD/ISPA), kurs mata uang alat medis, dan tarif paket jaminan kesehatan. Membaca parameter eksternal secara tersebar di kode domain menyulitkan pengujian deterministik dan migrasi masa depan ke Mesin Dunia (W8-P10).

## Keputusan Arsitektur
1. **Gerbang Tunggal**: Seluruh parameter lingkungan luar dibaca secara eksklusif melalui kelas gerbang `Modules\Hospital\Domain\Services\HospitalExternalFactors`.
2. **Parameter Berversi**: Kelas gerbang membaca tabel `hsp_external_factors` yang mencatat nilai faktor, kode wilayah kota, tanggal efektif, sumber regulasi, dan klasifikasi (`regulasi`, `asumsi_simulasi`, `fixture`).
3. **Persiapan Mesin Dunia**: Kelas gerbang ini dirancang untuk dapat digantikan oleh adapter `ExternalFactorReader` pada rilis W8-P10 tanpa perlu mengubah signature pemanggil di lapisan aplikasi.

## Konsekuensi
- Menghilangkan hardcoded magic numbers pada logika antisipasi bencana rumah sakit.
