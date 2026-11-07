# Katalog Galat Sensor Telemetri & IoT Gerbang Venue (Fase 117)

## 1. Daftar Kode Galat Perangkat Keras Gerbang & Sensor

Dokumen ini melengkapi katalog penanganan insiden perangkat keras untuk sensor LiDAR, kamera Face-ID, dan gerbang turnstile (modul `modules/Venue/`).

| Kode Galat | Tingkat Keparahan | Deskripsi Gangguan | Prosedur Pemulihan Otomatis |
|---|---|---|---|
| `ERR-IOT-001` | CRITICAL | Kehilangan denyut jantung (heartbeat loss) gerbang turnstile $> 5\text{ detik}$ | Alihkan ke mode fail-safe lokal, bunyikan alarm pos keamanan |
| `ERR-IOT-002` | HIGH | Oklusi optik kamera (lensa tertutup debu/stiker/tangan) | Aktifkan peringatan audio ke pengantre untuk membersihkan area lensa |
| `ERR-IOT-003` | HIGH | Ketidaksesuaian fase sensor hall-effect (lengan tersangkut) | Matikan arus solenoid selama 2 detik untuk merilis tekanan mekanis |
| `ERR-IOT-004` | MEDIUM | Suhu aktuator motor melebihi ambang batas $65^\circ\text{C}$ | Turunkan kecepatan rotasi berbantuan motor, nyalakan kipas pendingin |
| `ERR-IOT-005` | LOW | Latensi jaringan edge-to-cloud melebihi $500\text{ ms}$ | Gunakan verifikasi basis data SQLite lokal (offline fallback cache) |

---

## 2. Format Telemetri Galat (Diagnostic Alert JSON)

```json
{
  "error_code": "ERR-IOT-003",
  "hardware_identifier": "TURNSTILE-GATE-04",
  "timestamp": "2026-11-08T02:40:12Z",
  "severity": "HIGH",
  "hall_effect_sensor_phase": [1, 0, 0],
  "solenoid_voltage_v": 24.1,
  "remediation_status": "AUTOMATIC_SOLENOID_RELEASE_ATTEMPTED"
}
```
