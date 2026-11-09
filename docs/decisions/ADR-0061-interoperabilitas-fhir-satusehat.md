# ADR-0061: Interoperabilitas Standar FHIR R4 SatuSehat Kemenkes RI

## Status
Diterima (Accepted)

## Konteks
Pemerintah Indonesia melalui Kementerian Kesehatan RI menerbitkan regulasi PMK No. 24 Tahun 2022 tentang Rekam Medis, yang mewajibkan seluruh fasilitas pelayanan kesehatan (fasyankes) menghubungkan sistem informasi manajemen rumah sakit (SIMRS) dengan platform integrasi data kesehatan nasional SatuSehat. Platform SatuSehat menggunakan standar protokol Fast Healthcare Interoperability Resources (FHIR) rilis 4 (R4) dengan format pertukaran JSON berbasis RESTful API.

Rumah sakit membutuhkan arsitektur pemetaan yang mampu mentransformasikan entitas lokal internal (admisi, ranjang, observasi tanda vital, diagnosis ICD-10, tindakan) menjadi payload FHIR Bundle Transaction yang valid, taat format, dan tervalidasi secara deterministik sebelum proses transmisi jaringan.

## Keputusan Arsitektur
1. **Pemetaan Terisolasi Tanpa Polusi Domain**: Seluruh transformasi model domain internal rumah sakit ke dalam struktur FHIR R4 dikelola oleh layanan terdedikasi `SatuSehatFHIRMappingService` di namespace `Modules\Hospital\Domain\Services\FHIR\`. Entitas domain internal tidak boleh tercemar oleh atribut skema FHIR yang volatil.
2. **Penggunaan FHIR Bundle Transaction**: Setiap episode pelayanan admisi rawat inap dan observasi berkala dikemas dalam satu transaksi atomik bertipe `transaction` (`FHIRBundlePayloadDTO`). Ini menjamin konsistensi bahwa jika salah satu resource (misal Condition ICD-10) ditolak oleh server SatuSehat, seluruh bundle tidak tersimpan parsial.
3. **Penyelarasan Identitas Nasional (IHS)**: ID subjek pasien dalam resource FHIR menggunakan format `Patient/{IHS_NUMBER}` yang ditautkan melalui blind-index NIK pasien yang tersimpan secara terenkripsi at-rest sesuai kepatuhan UU PDP No. 27/2022.
4. **Validasi Sintaksis Pra-Kirim**: Aksi `ValidateSatuSehatFHIRComplianceAction` memvalidasi kelengkapan properti wajib (resourceType, ID, fullUrl, coding system) sebelum payload masuk ke antrean pengiriman guna mencegah kegagalan HTTP 422 di sisi gateway kementerian.

## Konsekuensi
- **Positif**:
  - Kepatuhan 100% terhadap mandat integrasi SatuSehat Kemenkes RI.
  - Skema data internal ERP tetap murni, bersih, dan independen dari perubahan profil SatuSehat di masa depan.
  - Memudahkan integrasi lanjutan ke modul klaim BPJS Kesehatan VClaim dan asuransi swasta.
- **Negatif**:
  - Diperlukan pemeliharaan kamus pemetaan terminologi (LOINC untuk observasi laboratorium/vital sign, SNOMED-CT untuk prosedur khusus, dan ICD-10 untuk diagnosis).

