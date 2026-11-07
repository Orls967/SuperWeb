# Kamus Data Skema Basis Data Crowd Safety & Biometrik — Fase 117

## 1. Tabel `ven_biometric_credentials`
Menyimpan hash templat biometrik satu arah untuk otentikasi pintu masuk wajah nir-sentuh.

| Kolom | Tipe Data | Keterangan & Batasan |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | Kunci primer |
| `user_id` | BIGINT UNSIGNED | ID pengguna terdaftar |
| `credential_code` | VARCHAR(64) UNIQUE | Kode unik kredensial `BIO-{user_id}-{hash}` |
| `biometric_template_hash` | VARCHAR(64) | Hash HMAC-SHA256 dari vektor wajah + garam |
| `liveness_score_bps` | INT UNSIGNED | Skor kepercayaan liveness (0 - 10000 bps) |
| `sensor_model` | VARCHAR(64) | Tipe perangkat sensor pemindai kios/turnstile |
| `status` | VARCHAR(32) | Status kredensial (`ENROLLED_ACTIVE`, `REVOKED`) |
| `consented_at` | TIMESTAMP | Waktu persetujuan pemrosesan data privasi |
| `revoked_at` | TIMESTAMP NULL | Waktu pencabutan kredensial |
| `created_at` | TIMESTAMP | Waktu pembuatan entri |
| `updated_at` | TIMESTAMP | Waktu pembaruan terakhir |

## 2. Tabel `ven_crowd_telemetries`
Menyimpan riwayat telemetri kepadatan zona dan prediksi keselamatan masa depan.

| Kolom | Tipe Data | Keterangan & Batasan |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | Kunci primer |
| `event_id` | BIGINT UNSIGNED | ID acara/festival yang sedang berlangsung |
| `zone_id` | BIGINT UNSIGNED | ID zona venue yang dipantau |
| `current_headcount` | INT UNSIGNED | Jumlah fisik orang di dalam zona |
| `predicted_headcount_15m` | INT UNSIGNED | Proyeksi headcount 15 menit ke depan |
| `density_sqm_bps` | INT UNSIGNED | Kerapatan orang per meter persegi (bps) |
| `recommended_action` | VARCHAR(64) | Rekomendasi AI keselamatan kerumunan |
| `gate_restricted` | BOOLEAN | Apakah gerbang masuk dibatasi otomatis |
| `created_at` | TIMESTAMP | Waktu perekaman telemetri |

## 3. Tabel `ven_crowd_incidents`
Mencatat insiden keselamatan kerumunan, eskalasi gerbang, dan tindakan operator.

| Kolom | Tipe Data | Keterangan & Batasan |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | Kunci primer |
| `event_id` | BIGINT UNSIGNED | ID acara |
| `zone_id` | BIGINT UNSIGNED | ID zona terdampak |
| `incident_code` | VARCHAR(64) UNIQUE | Kode insiden `INC-{event_id}-{zone_id}-{seq}` |
| `severity_level` | VARCHAR(32) | Tingkat keparahan (`LOW`, `MEDIUM`, `HIGH`, `CRITICAL`) |
| `status` | VARCHAR(32) | Status insiden (`NORMAL`, `WARNING_SURGE`, `GATE_RESTRICTED`, dll) |
| `triggered_by` | VARCHAR(64) | Pemicu insiden (`AI_PREDICTION`, `OPERATOR_MANUAL`, `SENSOR_TRIP`) |
| `operator_notes` | TEXT NULL | Catatan investigasi dan penanganan operator |
| `resolved_at` | TIMESTAMP NULL | Waktu insiden dinyatakan tuntas |
| `created_at` | TIMESTAMP | Waktu insiden dibuka |

## 4. Tabel `ven_personalized_promos`
Mencatat penawaran promo on-site kontekstual yang dikirimkan ke gawai tamu VIP.

| Kolom | Tipe Data | Keterangan & Batasan |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | Kunci primer |
| `user_id` | BIGINT UNSIGNED | ID tamu penerima |
| `event_id` | BIGINT UNSIGNED | ID acara |
| `zone_id` | BIGINT UNSIGNED | ID zona keberadaan tamu |
| `promo_dispatch_code` | VARCHAR(64) UNIQUE | Kode deterministik penawaran |
| `title` | VARCHAR(128) | Judul promo yang ditampilkan ke tamu |
| `discount_bps` | INT UNSIGNED | Besaran diskon dalam basis poin |
| `floor_price_idr` | BIGINT UNSIGNED | Batas harga terendah COGS perlindungan margin |
| `status` | VARCHAR(32) | Status promo (`DELIVERED`, `REDEEMED`, `EXPIRED`) |
| `delivered_at` | TIMESTAMP | Waktu pengiriman |
| `redeemed_at` | TIMESTAMP NULL | Waktu penukaran promo |

## 5. Tabel `ven_zone_wps_metrics`
Mencatat metrik waktu tunggu layanan kasir/bar dan skor WPS per zona.

| Kolom | Tipe Data | Keterangan & Batasan |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | Kunci primer |
| `event_id` | BIGINT UNSIGNED | ID acara |
| `zone_id` | BIGINT UNSIGNED | ID zona |
| `active_servers_count` | INT UNSIGNED | Jumlah kasir/bartender aktif |
| `arrival_rate_bps` | INT UNSIGNED | Laju kedatangan pesanan per menit (bps) |
| `service_rate_bps` | INT UNSIGNED | Laju pemrosesan pesanan per menit per kasir (bps) |
| `wps_score` | INT UNSIGNED | Skor kepuasan layanan (0 - 100) |
| `reinforcement_needed` | BOOLEAN | Apakah bantuan shift kasir diperlukan |
| `recorded_at` | TIMESTAMP | Waktu pencatatan |

