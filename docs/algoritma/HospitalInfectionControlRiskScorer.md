# Algoritma HospitalInfectionControlRiskScorer

## 1. Definisi dan Tujuan
Algoritma `HospitalInfectionControlRiskScorer` menghitung skor risiko infeksi nosokomial (*Healthcare-Associated Infections* / HAIs) bagi pasien rawat inap dan menentukan protokol kewaspadaan isolasi (*standard*, *contact*, *droplet*, atau *airborne isolation*) secara deterministik dan tersertifikasi kriptografis.

## 2. Parameter Input
| Parameter | Tipe | Rentang / Format | Keterangan |
| :--- | :--- | :--- | :--- |
| `has_invasive_device` | bool | `true`/`false` | Terpasang kateter urin, CVC, ETT |
| `device_days` | int | $\ge 0$ hari | Durasi pemasangan alat invasif |
| `is_immunocompromised` | bool | `true`/`false` | Kemoterapi, transplantasi, neutropenia |
| `prior_multidrug_resistant_organism` | bool | `true`/`false` | Riwayat MRSA, ESBL, VRE, CRE |
| `has_open_wound` | bool | `true`/`false` | Luka bakar, ulkus dekubitus derajat 3-4 |
| `current_antibiotic_days` | int | $\ge 0$ hari | Durasi terapi antimikroba spektrum luas |
| `respiratory_symptoms` | bool | `true`/`false` | Batuk produktif, hemoptisis, aerosol |
| `diarrhea_active` | bool | `true`/`false` | Diare cair akut (>3x/hari) |
| `icu_stay` | bool | `true`/`false` | Sedang dirawat di ruang rawat intensif |

## 3. Rumus dan Pembobotan
Total skor dihitung melalui akumulasi bobot klinis berikut:
$$\text{Score} = \sum W_i$$
- Pemasangan alat invasif: $+15$ poin ($>3$ hari: $+10$, $>7$ hari: $+15$).
- Status imunokompromais: $+20$ poin.
- Riwayat kuman resistan ganda (MDRO): $+25$ poin.
- Luka terbuka luas: $+10$ poin.
- Penggunaan antibiotik lama: $\ge 7$ hari $+10$ poin, $\ge 14$ hari $+15$ poin.
- Gejala respirasi infeksius: $+15$ poin.
- Diare aktif (indikasi *C. difficile*): $+15$ poin.
- Rawat ICU: $+10$ poin.

## 4. Klasifikasi dan Rekomendasi
| Rentang Skor | Kategori Risiko | Rekomendasi Isolasi | Protokol |
| :--- | :--- | :--- | :--- |
| $\ge 60$ | `VERY_HIGH` | `AIRBORNE_OR_NEGATIVE_PRESSURE` | Isolasi tekanan negatif, masker N95/FFP3, skrining aktif |
| $40 - 59$ | `HIGH` | `CONTACT_ISOLATION_SINGLE_ROOM` | Kamar terpisah, APD gaun & sarung tangan, instrumen khusus |
| $20 - 39$ | `MODERATE` | `COHORT_COMPATIBLE` | Kohorting pasien dengan profil patogen identik |
| $< 20$ | `LOW` | `STANDARD_WARD` | Kewaspadaan standar, kebersihan tangan 5 momen WHO |

## 5. Sertifikasi Kriptografis
Setiap kalkulasi menghasilkan sertifikat dengan struktur:
```text
SigPayload = "HICR-V1:{score}:{category}:{isolation}:{input_hash}"
Signature = HMAC_SHA256(SigPayload, SECRET_KEY)
```
Invarian: Nilai skor, rekomendasi ruang isolasi, dan bundle kewaspadaan tidak dapat diubah tanpa merusak verifikasi HMAC.
