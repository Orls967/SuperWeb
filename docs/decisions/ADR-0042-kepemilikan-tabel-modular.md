# ADR-0042: Kepemilikan Tabel Basis Data Modular & Aturan Longest Prefix Match (W8-D42)

## Status
Diterima (Accepted)

## Konteks
Dalam arsitektur monolit modular dengan basis data bersama (*shared database*), kegagalan pembagian kepemilikan skema dapat menyebabkan beberapa modul mengakses, memodifikasi, atau memanipulasi tabel yang sama secara langsung (*cross-module table hijacking*). Hal ini menyebabkan keterikatan skema yang rapuh (*tight database coupling*) dan merusak integritas domain. Diperlukan aturan kepemilikan tabel yang ketat dan deterministik berbasis prefiks nama tabel.

## Keputusan
1. **Aturan Kecocokan Prefiks Terpanjang (*Longest Prefix Match*)**:
   Setiap tabel basis data dimiliki tepat oleh 1 modul. Pemetaan nama tabel ke modul pemilik dilakukan berdasarkan pencocokan prefiks terpanjang yang terdaftar di konfigurasi `config/modules.php`.
   Algoritma `TablePrefixOwnershipResolver` mengurutkan prefiks secara menurun berdasarkan panjang karakter string (`strlen`), sehingga prefiks spesifik yang bertumpuk (*nested prefixes*, misalnya `hcm_pay_`) akan menang atas prefiks yang lebih pendek (`hcm_`).
2. **Pencegahan Konflik Prefiks (*PREFIX_CONFLICT*)**:
   Dua modul berbeda dilarang keras mengklaim atau mendaftarkan prefiks tabel yang identik. Konflik prefiks dianggap sebagai kegagalan validasi kritis yang memblokir proses build dan memicu pesan galat fatal pada pemeriksaan arsitektur.
3. **Pengelolaan Pengecualian & Tabel Bersama (*Shared Tables*)**:
   Tabel lintas domain yang memerlukan akses baca/tulis sementara dari modul lain wajib terdaftar secara eksplisit pada array `shared_tables` di `config/modules.php` dan memiliki tiket/rekaman waiver berbatas waktu. Akses tanpa pendaftaran memicu pelanggaran aturan `A2` (ForeignTablePrefixRule).
4. **Verifikasi Sertifikat Kepemilikan**:
   Kelas `PrefixCertificateVerifier` memvalidasi bahwa seluruh tabel yang terdeteksi dari migrasi dan pemanggilan query Eloquent / Query Builder (`DB::table`) memiliki pemilik tunggal yang sah dan tidak melanggar batasan modular.

## Konsekuensi
- Memastikan pemisahan data tingkat logika yang kuat antar modul dalam satu basis data fisik.
- Memungkinkan migrasi modul menjadi layanan mikro mandiri di masa depan tanpa pemisahan skema yang menyakitkan.
- Modul dilarang menulis atau membaca tabel modul lain secara langsung, dan diwajibkan menggunakan API / Kontrak / Event Spine.

