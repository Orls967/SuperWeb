# Panduan Telemetri, Pemantauan Runtime & Metrik Prometheus (Fase 237)

## 1. Arsitektur Observabilitas Modul Platform
Observabilitas sistem mencakup tiga pilar utama (The Three Pillars of Observability):
1. **Metrics (Metrik Numerik)**: Representasi statistik kuantitatif terhadap performa dan sumber daya sistem.
2. **Logs (Catatan Peristiwa)**: Catatan berstempel waktu yang mendokumentasikan kejadian diskret.
3. **Traces (Jejak Penelusuran)**: Alur penelusuran request yang mengalir melalui berbagai komponen terdistribusi.

Modul `Platform` mengintegrasikan ketiga pilar ini melalui subsistem telemetri di `Modules\Platform\Domain\Telemetry` dan exporter Prometheus di `App\Quality\Reporting\PrometheusMetricExporter`.

---

## 2. Format Eksposisi Prometheus OpenTelemetry

Endpoint `/platform/dev/api/metrics/prometheus` menyajikan metrik sistem dalam format teks standar Prometheus (RFC 004):

```prometheus
# HELP quality_phase_b1_loc Total pure logic lines of code per phase
# TYPE quality_phase_b1_loc gauge
quality_phase_b1_loc{phase="237",status="compliant"} 7543

# HELP quality_phase_decision_density Ratio of decision branches to executable statements
# TYPE quality_phase_decision_density gauge
quality_phase_decision_density{phase="237"} 0.1600

# HELP quality_phase_duplicate_ratio Token duplication ratio
# TYPE quality_phase_duplicate_ratio gauge
quality_phase_duplicate_ratio{phase="237"} 0.0000

# HELP platform_service_burn_rate_1h 1-hour error budget burn rate
# TYPE platform_service_burn_rate_1h gauge
platform_service_burn_rate_1h{service="Banking",tier="tier_1_critical"} 0.15
platform_service_burn_rate_1h{service="Hospital",tier="tier_2_high"} 0.42

# HELP platform_service_error_budget_mins Total error budget in minutes per month
# TYPE platform_service_error_budget_mins gauge
platform_service_error_budget_mins{service="Banking"} 4
platform_service_error_budget_mins{service="Hospital"} 43
```

Format ini memungkinkan scraping langsung oleh Prometheus Server atau OpenTelemetry Collector tanpa memerlukan adaptor proprietary pihak ketiga.

---

## 3. Koleksi Telemetri Runtime Server

Kelas `ServiceTelemetryCollector` memantau konsumsi sumber daya komputasi PHP pada lingkungan runtime:
- `memory_usage_mb`: Memori aktual yang dialokasikan oleh PHP runtime.
- `memory_peak_mb`: Puncak alokasi memori tertinggi (peak memory usage) yang pernah dicapai proses.
- `latency_ms`: Durasi eksekusi operasi database atau perutean HTTP dalam milidetik.
- `is_error`: Flag status kesalahan untuk perhitungan SLI tingkat layanan.

---

## 4. Verifikasi Rantai Kriptografis Log Audit

Untuk mencegah manipulasi log audit dari pihak luar, kelas `AuditTrailIntegrityVerifier` menerapkan **Cryptographic Hash Chain**:
- Setiap entri log $E_i$ menyimpan `prev_hash` yang merupakan hash dari entri sebelumnya $H(E_{i-1})$.
- Hash entri dihitung menggunakan fungsi ringkasan aman:
$$H(E_i) = \text{SHA-256}(\text{prev\_hash} \parallel \text{payload})$$
- Jika terdapat modifikasi ilegal pada riwayat log, rantai hash akan putus pada titik manipulasi dan langsung dilaporkan oleh sistem verifier.

