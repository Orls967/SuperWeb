# ADR-0047: Algoritma Pembatas Laju Integer Token Bucket Bebas Floating-Point (W8-D47 / B1)

## Status
Diterima (Accepted)

## Konteks
Pembatas laju konvensional berbasis leaky bucket atau fixed window sering mengandalkan perhitungan aritmatika titik mengambang (float/double) untuk menghitung fraksi token yang bertambah per milidetik. Pada eksekusi berkecepatan tinggi di berbagai arsitektur CPU (x86_64 vs ARM64), pembulatan desimal IEEE 754 rentan mengalami akumulasi galat pembulatan (*floating-point drift*), yang berakibat pada bocornya kuota atau kegagalan throttle yang tidak deterministik.

Selain itu, sistem perlu kebal terhadap fenomena pemutaran balik waktu jam (*NTP clock stepping / clock regression*) yang dapat menyebabkan eksploitasi kuota tak terhingga bila selisih waktu bernilai negatif.

## Keputusan Arsitektur

### 1. Representasi Skala Milli-Token Murni Integer
Algoritma `IntegerTokenBucket` mengeliminasi floating-point seutuhnya dengan menskalakan 1 token menjadi 1.000 unit diskrit:
$$\text{MILLI\_PER\_TOKEN} = 1.000$$
Kapasitas maksimum bucket dihitung sebagai bilangan bulat:
$$\text{max\_milli} = \text{capacity} \times 1.000$$

### 2. Formulasi Pengisian Ulang Deterministik
Dengan input integer stempel waktu milidetik saat ini ($\text{now\_ms}$) dan stempel waktu eksekusi sebelumnya ($\text{last\_ms}$):
$$\Delta t = \text{now\_ms} - \text{last\_ms}$$
$$\text{added\_milli} = \Delta t \times \text{rate\_per\_sec}$$
$$\text{refilled\_milli} = \min(\text{max\_milli}, \text{current\_milli} + \text{added\_milli})$$

### 3. Perlindungan Terhadap Regresi Jam (*Clock Regression Guard*)
Bila stempel waktu jam mundur ($\text{now\_ms} < \text{last\_ms}$), algoritma menolak melanjutkan kalkulasi dan melempar eksepsi deterministik:
```php
if ($nowMs < $lastMs) {
    throw new DomainException("CLOCK_REGRESSION: Waktu permintaan ({$nowMs} ms) mendahului stempel terakhir ({$lastMs} ms).");
}
```

### 4. Perhitungan Jeda Percobaan Ulang (*Retry-After*)
Bila token tidak mencukupi untuk biaya konsumsi ($\text{refilled\_milli} < \text{cost\_milli}$):
- Defisit token dihitung:
  $$\text{deficit\_milli} = \text{cost\_milli} - \text{refilled\_milli}$$
- Durasi milidetik hingga token mencukupi dihitung via pembagian integer membulat ke atas (*ceiling division*):
  $$\text{retry\_after\_ms} = \left\lfloor \frac{\text{deficit\_milli} + \text{rate\_per\_sec} - 1}{\text{rate\_per\_sec}} \right\rfloor$$
- Durasi detik untuk header HTTP standard:
  $$\text{retry\_after\_sec} = \left\lfloor \frac{\text{retry\_after\_ms} + 999}{1.000} \right\rfloor$$
- Status HTTP respons adalah `429 Too Many Requests` dengan header `Retry-After: {retry_after_sec}` dan payload JSON `retry_after_ms: {retry_after_ms}`.

### 5. Sertifikat Verifikasi SHA-256
Setiap eksekusi konsumsi menerbitkan struktur sertifikat audit mandiri:
```json
{
  "input": { "current_milli": 5000, "now_ms": 2000, "capacity": 5, "rate": 2 },
  "steps": [ ... ],
  "result": { "allowed": false, "retry_after_ms": 500 },
  "sha256": "43ba3df68ea23f05d53503a429033320c2d33454228941f176bfa2c8f86f7b15"
}
```
Integritas sertifikat diverifikasi oleh `IntegerTokenBucketCertificateVerifier` untuk memastikan tidak ada pemalsuan hasil throttling di lapisan perantara.

## Konsekuensi & Kepatuhan
- **Positif:** Determinisme mutlak pada seluruh platform, kebal terhadap ketidakakuratan float, performa tinggi ($< 5\mu\text{s}$ per evaluasi).
- **Negatif:** Laju isi ulang memiliki resolusi batas minimum 1 token per detik.
- **Verifikasi:** Diuji pada `ApiV2AlgorithmsTest` dengan vektor prompt terkunci (`t=0` s/d `t=2000` ms) dan sertifikat SHA-256.
