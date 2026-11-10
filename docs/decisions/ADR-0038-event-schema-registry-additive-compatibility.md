# ADR-0038: Event Schema Registry & Additive Compatibility Enforcement (W8-D38)

## Status
Diterima (Accepted)

## Konteks
Event dalam arsitektur terdistribusi berperan sebagai kontrak publik antar-modul. Perubahan skema payload yang tidak terkontrol (seperti penghapusan properti, perubahan tipe data primitif, atau penambahan atribut wajib baru) dapat memicu kegagalan fatal (*silent contract breakage*) pada modul konsumen yang masih mengonsumsi struktur lama. Diperlukan penegakan aturan evolusi skema berbasis validasi aditif (*backward-compatibility enforcement*).

## Keputusan
1. **Pusat Registrasi Skema (`EventSchemaRegistry`)**:
   Dibuat service sentral `Modules\Core\Application\Services\EventSchemaRegistry` yang mengelola pendaftaran skema event berversi di tabel `core_event_schemas`.
   Format nama event wajib mengikuti konvensi KONSEP.md §A4.1: `{domain}.{entitas}.{kejadian}.v{n}`.

2. **Penegakan Kompatibilitas Aditif via Algoritma A6 (`SchemaAdditiveCompatibilityChecker`)**:
   Setiap upaya pendaftaran versi baru suatu event ($v_{new}$) diverifikasi terhadap versi aktif saat ini ($v_{current}$):
   - **Perubahan Aditif Legal (Diizinkan)**:
     - Menambah properti baru yang bersifat opsional (tidak tercantum di `required`).
     - Menambah nilai baru ke dalam larik `enum`.
     - Memperluas batasan numerik (`minimum` yang lebih rendah atau `maximum` yang lebih tinggi).
   - **Perubahan Merusak / Breaking Changes (Ditolak dengan Exception)**:
     - `PROPERTY_REMOVED`: Properti yang ada pada versi lama dihilangkan.
     - `TYPE_CHANGED`: Tipe data properti diubah (misal dari integer menjadi string atau number).
     - `REQUIRED_ADDED`: Properti baru ditambahkan ke dalam daftar atribut wajib (`required`).
     - `ENUM_VALUE_REMOVED`: Nilai enum yang ada pada versi lama dihapus.
     - `RANGE_NARROWED`: Batasan rentang dipersempit (misal nilai minimum dinaikkan).
   Perubahan yang melanggar aturan di atas **wajib ditolak** saat pendaftaran. Modul domain yang membutuhkan struktur berbeda secara fundamental diwajibkan menerbitkan event baru dengan kenaikan versi mayor ($v_{n+1}$) dan menjalankan masa transisi koeksistensi.

3. **Validasi Payload saat Publishing**:
   Metode `EventSpineService::publish()` memvalidasi payload event terhadap skema aktif yang terdaftar di `EventSchemaRegistry`. Pelanggaran struktur payload akan memicu `EventSchemaValidationException` dan dikarantina sebagai poison message jika terjadi berulang.

## Konsekuensi
- Mencegah disintegrasi data antar-modul akibat perubahan skema sepihak.
- Memastikan kompatibilitas ke belakang tanpa mengorbankan fleksibilitas evolusi domain.

