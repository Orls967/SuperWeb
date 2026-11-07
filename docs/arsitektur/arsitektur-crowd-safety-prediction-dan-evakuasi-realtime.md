# Arsitektur Crowd Safety Prediction & Evakuasi Real-Time — Fase 117

```mermaid
flowchart TD
    subgraph Pengumpulan Sinyal Sensor
        S1[Turnstile Scanners Masuk/Keluar] --> AGG[Agregator Telemetri Aliran Zona]
        S2[Sensor Overhead Density LiDAR/Kamera] --> AGG
        S3[Data Prakiraan Cuaca & Pintu Darurat] --> AGG
    end

    subgraph Mesin Prediksi Deterministik
        AGG --> V14[CrowdDensitySurgePredictor V14]
        V14 --> CALC[Kalkulasi Headcount & Densitas 15 Menit ke Depan]
        CALC --> EVAL{Evaluasi Ambang Batas Keselamatan}
    end

    subgraph Keputusan & Tindakan Otonom
        EVAL -->|< 70% Kapasitas| NORM[Normal Operation - Gerbang Hijau]
        EVAL -->|70% - 85% Kapasitas| WARN[Warning Surge - Perlambat Laju Masuk]
        EVAL -->|85% - 95% Kapasitas| RESTR[Kunci Gerbang Masuk Utama - GATE_RESTRICTED]
        EVAL -->|> 95% / Potensi Crush| SAGA[CrowdEmergencyEvacuationSaga]
    end

    subgraph Eksekusi Penyelamatan & Evakuasi
        SAGA --> E1[Kunci Turnstile Masuk Total]
        SAGA --> E2[Buka Gerbang Sekunder & Pintu Darurat]
        SAGA --> E3[Kirim Sinyal Tim Medis Lintas Modul G7-K23]
        SAGA --> E4[Kompensasi: Pemulihan Headcount Saat Terkendali]
    end
```

## 1. Pencegahan Bencana Kerumunan (Anti-Crush Engineering)
Bencana crowd crush umumnya terjadi karena fenomena penumpukan asimetris pada pintu masuk yang terus menerima arus orang baru saat kapasitas aula atau lantai dansa telah melebihi batas elastisitas fisik:
- **Ambang Kritis Kepadatan:** Pada densitas $> 4.0 \text{ orang/m}^2$, pergerakan bebas individu mulai terhambat. Pada $> 5.0 \text{ orang/m}^2$, gelombang kejut kerumunan (shockwaves) terjadi.
- **Intervensi 15 Menit Mendahului:** Prediktor V14 membaca kecepatan aliran ($V_{in}$) dan memproyeksikan headcount 15 menit ke depan. Tindakan pembatasan gerbang diambil saat proyeksi menyentuh $4.5 \text{ orang/m}^2$, sebelum kerumunan fisik menyentuh titik jenuh.
- **Saga Evakuasi Darurat Terkoordinasi:** Mengamankan pintu darurat dan memanggil ambulans via kontrak aditif kesehatan secara simultan dengan log audit yang tidak dapat diubah.

