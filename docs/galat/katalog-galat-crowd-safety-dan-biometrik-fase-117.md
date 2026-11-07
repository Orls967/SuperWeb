# Katalog Galat & Penanganan Exception — Fase 117

## 1. Daftar Galat Domain Biometrik (`Modules\Venue\Domain\Exceptions`)
| Kode Galat | Nama Exception | HTTP Status | Penjelasan & Solusi |
|---|---|---|---|
| `ERR_BIO_001` | `BiometricLivenessFailedException` | 422 | Skor liveness di bawah ambang 85%. Sarankan lepas kacamata atau gunakan QR manual. |
| `ERR_BIO_002` | `BiometricTemplateMismatchException` | 403 | Hash templat wajah tidak cocok dengan profil terdaftar. Akses ditolak. |
| `ERR_BIO_003` | `BiometricCredentialRevokedException` | 403 | Kredensial biometrik telah dicabut oleh pengguna atau admin. Harus mendaftar ulang. |
| `ERR_BIO_004` | `BiometricConsentMissingException` | 400 | Tamu belum memberikan persetujuan privasi eksplisit. |

## 2. Daftar Galat Domain Keselamatan Kerumunan
| Kode Galat | Nama Exception | HTTP Status | Penjelasan & Solusi |
|---|---|---|---|
| `ERR_CRD_101` | `ZoneCapacityExceededException` | 409 | Zona telah mencapai kapasitas legal 100%. Pintu gerbang otomatis menolak scan masuk. |
| `ERR_CRD_102` | `GateRestrictedByAiException` | 423 | Gerbang dikunci oleh prediktor crowd surge AI. Alihkan tamu ke gerbang sekunder. |
| `ERR_CRD_103` | `CrowdEvacuationActiveException` | 503 | Status evakuasi darurat aktif. Seluruh turnstile masuk terkunci, pintu keluar terbuka. |

## 3. Daftar Galat Promosi Kontekstual On-Site
| Kode Galat | Nama Exception | HTTP Status | Penjelasan & Solusi |
|---|---|---|---|
| `ERR_PRM_201` | `OfferCooldownActiveException` | 429 | Promo masih dalam jendela pendingin 1 jam. Tahan pengiriman. |
| `ERR_PRM_202` | `DailyOfferQuotaExhaustedException` | 429 | Tamu telah menerima kuota maksimal 3 penawaran malam ini. |
| `ERR_PRM_203` | `MarginFloorViolationException` | 422 | Diskon melanggar batas bawah HPP/COGS. Normalisasi ke harga lantai. |

