# Arsitektur Observabilitas & Tumpukan Metrik Operasional

Dokumen ini menjelaskan tumpukan observabilitas (observability stack), pengumpulan telemetri metrik, dan integrasi penelusuran terdistribusi pada modul Platform.

---

## 1. Tiga Pilar Observabilitas Platform

Modul Platform menyatukan tiga pilar observabilitas untuk memberikan visibilitas komprehensif terhadap performa rilis:
1. **Metrik (Metrics)**: Deret waktu numerik (timeseries) yang mengukur laju permintaan (QPS), persentase galat (error rate), dan latensi respons.
2. **Catatan Peristiwa (Logs)**: Catatan peristiwa terstruktur dalam format JSON yang memuat konteks eksekusi, trace ID, dan tingkat keparahan (severity).
3. **Penelusuran Terdistribusi (Distributed Traces)**: Jejak alur eksekusi kueri lintas subsistem yang merekam durasi dan rentang waktu (spans) setiap panggilan layanan.

---

## 2. Pengumpul Metrik Sampel Canary (CanaryMetricSampleCollector)

Komponen `CanaryMetricSampleCollector` mengumpulkan telemetri dari armada aplikasi secara berkala dengan interval 60 detik:
- Mengagregasi metrik per kluster pod: `canary` vs `baseline` (stable).
- Menghitung rasio galat per kode status HTTP: $4\text{xx}$ vs $5\text{xx}$.
- Merekam persentil latensi: p50, p90, p95, dan p99 dalam satuan milidetik.
- Meneruskan agregat ke penyimpanan telemetri lokal untuk konsumsi algoritma A6.

---

## 3. Integrasi OpenTelemetry & Trace Context Propagation

Setiap permintaan yang masuk melalui gateway diberi header `traceparent` (W3C Trace Context):
- Header ini diteruskan ke panggilan basis data, antrean background worker, dan layanan eksternal.
- Jika terjadi anomali selama fase canary deployment, `DeploymentFailureForensicsEngine` menggunakan trace ID terkait untuk mengekstrak rekaman error log lengkap secara instan.

---

## 4. Dasbor Operasional & Peringatan Proaktif

Dasbor metrik rilis dapat diakses melalui antarmuka web di `/platform/metrics` yang menyajikan visualisasi grafis real-time mengenai:
- Rasio pembagian lalu lintas canary aktif.
- Perbandingan langsung kurva latensi canary terhadap baseline.
- Sisa anggaran kesalahan (Error Budget Burn Rate).
- Status antrean pemrosesan job backfill data.

