# Katalog Anomali Turnstile & Sensor Kepadatan Kerumunan — Fase 117

## 1. Klasifikasi Anomali Gerbang Turnstile
| Kode Anomali | Gejala & Deskripsi | Penyebab Potensial | Tindakan Mitigasi |
|---|---|---|---|
| **ANO-GATE-001** | *Tailgating Detected:* 2 orang masuk pada 1 putaran gerbang. | Pengunjung menyusup di belakang tamu valid. | Kamera overhead mendeteksi dual-head; bunyikan buzzer turnstile dan kunci otomatis. |
| **ANO-GATE-002** | *Biometric Liveness Challenge Timeout:* Wajah terdeteksi namun liveness gagal. | Pengunjung memakai masker tebal / kacamata hitam reflektif. | Instruksikan tamu melepas kacamata atau arahkan ke jalur manual barcode. |
| **ANO-GATE-003** | *Anti-Passback Breach:* Kredensial biometrik yang sama discan 2x dalam 3 menit di pintu masuk. | Upaya berbagi token atau duplikasi akses. | Tolak scan kedua seketika dengan pesan `ALREADY_CHECKED_IN`. |
| **ANO-GATE-004** | *Hardware Flap Jam:* Pintu gerbang tertahan benda fisik. | Hambatan mekanik atau tas pengunjung tersangkut. | Putuskan arus solenoid; alihkan ke turnstile sebelah dalam waktu 5 detik. |

## 2. Klasifikasi Anomali Sensor Kepadatan (LiDAR/Optik)
| Kode Anomali | Gejala & Deskripsi | Tindakan Mitigasi |
|---|---|---|
| **ANO-SENS-101** | *Sensor Occlusion:* Lensa sensor tertutup debu/kabut panggung (smoke machine). | Bandingkan laju scan masuk turnstile dengan pembacaan sensor; gunakan scan turnstile sebagai sumber kebenaran (source of truth). |
| **ANO-SENS-102** | *Headcount Negative Drift:* Headcount terhitung $< 0$. | Normalisasi paksa nilai minimum ke 0; jadwalkan rekalibrasi zona saat acara selesai. |

