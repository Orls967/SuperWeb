# Model Ancaman Keamanan & Mitigasi: API v2, Webhook HMAC & Multi-tenancy (STRIDE)

**ID Dokumen:** SEC-STRIDE-55  
**Klasifikasi:** Keamanan Siber & Kepatuhan Platform Superweb  
**Tanggal:** 2026-11-12  

---

## 1. Pendekatan Analisis STRIDE

Model ancaman ini mengevaluasi arsitektur API Gateway v2, subsistem Webhook HMAC, mesin EDI, dan lapisan isolasi multi-tenancy terhadap enam kategori ancaman STRIDE:
1. **S**poofing (Pemalsuan Identitas)
2. **T**ampering (Manipulasi Data)
3. **R**epudiation (Penyangkalan Tindakan)
4. **I**nformation Disclosure (Kebocoran Informasi)
5. **D**enial of Service (Penolakan Layanan / DoS)
6. **E**levation of Privilege (Peningkatan Hak Akses)

---

## 2. Matriks Ancaman & Mitigasi Terimplementasi

| Kategori STRIDE | Vektor Serangan Spesifik | Komponen Terdampak | Tingkat Risiko | Kontrol Mitigasi Terpasang |
| :--- | :--- | :--- | :--- | :--- |
| **Spoofing** | Pemalsuan token klien B2B menggunakan brute-force token acak | `ApiV2GatewayMiddleware` | Tinggi (High) | Kunci API disimpan dalam hash SHA-256 (`key_digest`). Token mentah memiliki entropi 256-bit (`sk_live_...`). Token dicocokkan konstan-waktu. |
| **Spoofing** | Pemalsuan asal pengirim webhook oleh pihak penyerang di tengah jalan (*MitM*) | `WebhookSignatureVerifier` | Kritis (Critical) | Seluruh dispatch webhook wajib menandatangani muatan via HMAC-SHA256 dengan rahasia terenkripsi AES-256-GCM. Header `X-Signature` membawa stempel waktu Unix yang diverifikasi dalam jendela toleransi 300 detik. |
| **Tampering** | Modifikasi payload mutasi saat retry jaringan menggunakan `Idempotency-Key` sama | `IdempotencyStoreService` | Kritis (Critical) | Server menghitung hash SHA-256 kanonik dari muatan JSON. Bila kunci sama dikirim dengan payload berbeda, server mengembalikan status HTTP `409 Conflict` (ADR-0046). |
| **Tampering** | Manipulasi riwayat transisi EDI untuk memalsukan status pemrosesan pesanan | `CoreEdiTransition` | Tinggi (High) | Model Eloquent `CoreEdiTransition` mengunci operasi `UPDATE` dan `DELETE` via `DomainException: IMMUTABLE_AUDIT_LOG`. Catatan bersifat append-only. |
| **Repudiation** | Klien B2B menyangkal telah mengirimkan dokumen pesanan atau faktur tertentu | `CoreEdiInterchange` | Sedang (Medium) | Setiap dokumen EDI yang diterima menerbitkan sertifikat kanonik dengan nilai SHA-256 payload mentah, stempel waktu, dan control reference unik (`control_ref`). |
| **Repudiation** | Operator sistem menyangkal telah membaca data antar-tenant | `core_tenant_scope_bypasses` | Tinggi (High) | Metode `withoutTenantScope()` wajib mencatat `actor_user_id`, `ability`, `model_class`, dan `reason` ke tabel audit persisten sebelum kueri dieksekusi. |
| **Information Disclosure**| Pembocoran data tenant lain akibat kueri tanpa filter `WHERE tenant_id` | `TenantIsolationScope` | Kritis (Critical) | Penegakan otomatis global scope pada 11 model lintas 9 modul. Dalam mode ketat, kueri tanpa `TenantContext` melempar `TenantContextMissingException` (Fail-Closed). |
| **Information Disclosure**| Bocornya struktur tabel basis data pada respons pesan galat pengurutan | `ApiV2ResourceController` | Rendah (Low) | Parameter `sort` dibatasi ketat pada whitelist `declaredSorts()`. Pengurutan kolom ilegal menghasilkan pesan generik RFC 7807 tanpa membocorkan trace SQL. |
| **Denial of Service** | Serangan flood HTTP untuk menghabiskan memori dan koneksi worker database | `IntegerTokenBucket` | Kritis (Critical) | Pembatasan laju B1 berbasis algoritma integer token bucket di gerbang terluar middleware. Klien yang melebihi kuota langsung di-throttle pada level status HTTP 429 tanpa menyentuh controller domain. |
| **Denial of Service** | Unggahan berkas CSV berukuran gigabita untuk memicu out-of-memory crash | `StreamingBulkImportService` | Tinggi (High) | Pembacaan streaming pointer `SplFileObject` dalam potongan 500 baris. Alokasi memori konstan $< 32\text{ MB}$. Job $> 10.000$ baris wajib persetujuan admin. |
| **Elevation of Privilege**| Klien dengan token hanya-baca (`resource:read`) memanggil aksi mutasi | `ApiV2GatewayMiddleware` | Tinggi (High) | Pemeriksaan kemampuan Sanctum token / ApiClient abilities: rute mutasi (`POST/PUT/PATCH/DELETE`) mewajibkan kemampuan `resource:action` atau `*`. Pelanggaran langsung menghasilkan HTTP 403 Forbidden. |

---

## 3. Verifikasi Keamanan Otomatis

Seluruh kontrol mitigasi di atas diuji secara otomatis dalam pipeline CI melalui:
1. `tests/Acceptance/ApiV2/ApiV2AlgorithmsTest.php` (uji vektor kriptografi & pengujian sertifikat palsu).
2. `tests/Acceptance/ApiV2/ApiV2LifecycleTest.php` (uji penetrasi multi-tenant, throttling B1, penolakan idempotensi 409/422, dan kegagalan token abilities 403).
3. `tests/Architecture/AuditCommandContractTest.php` (uji audit integritas dua sumber dan simulasi korupsi data).

