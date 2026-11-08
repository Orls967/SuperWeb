# ADR-0051: Gerbang Kepekaan Faktor Eksternal dan Parameter Berversi (G7-D51)

## Status
Diterima (Accepted) — 2026-11-08

## Konteks
Operasional aviasi dan maritim sangat rentan terhadap fluktuasi variabel eksternal seperti harga bahan bakar avtur/bunker, kurs mata uang, cuaca buruk, erupsi abu vulkanik, penutupan pelabuhan/bandara, dan kebijakan karantina internasional. Mengakses parameter eksternal secara acak atau hard-coded melanggar prinsip determinisme sistem.

## Keputusan
1. **Gerbang Akses Tunggal per Modul:**
   - Modul Aviasi: `Modules\Aviation\Domain\Services\AviationExternalFactors` membaca tabel `avi_external_factors`.
   - Modul Armada Port: `Modules\Port\Domain\Services\FleetExternalFactors` membaca tabel `prt_fleet_external_factors`.
   - Modul Marikultur: `Modules\Marine\Domain\Services\MarineExternalFactors` membaca tabel `mar_external_factors`.
2. **Metadata Parameter Berversi:** Setiap baris faktor eksternal menyimpan `factor_key`, `factor_value`, `effective_from` (UTC), `source` (sumber resmi/institusi), dan `label` (`regulasi`, `asumsi_simulasi`, `fixture`).
3. **Penyelarasan W8-P10:** Gerbang layanan ini dirancang sebagai adaptor yang akan digantikan oleh `ExternalFactorReader` pada integrasi Mesin Dunia W8-P10 tanpa perlu merombak kode pemanggil di lapisan aplikasi.
4. **Tindakan Berbasis Kebijakan:** Perubahan faktor kritis (seperti penutupan bandara akibat erupsi) mengeksekusi Action dengan State Machine atau persetujuan terstruktur (approval), bukan sekadar penyesuaian angka pasif.

## Konsekuensi
Menjaga konsistensi simulasi skenario deterministik (dunia normal vs dunia kejutan) dan memudahkan transisi menuju engine telemetri waktu nyata.
