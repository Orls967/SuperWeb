# Katalog Prediksi Kerumunan & Eskalasi Gerbang — Fase 117

## 1. Tingkat Risiko Kepadatan Kerumunan
| Level | Densitas ($org/m^2$) | Okupansi Legal | Indikator Visual | Aksi Otonom Sistem |
|---|---|---|---|---|
| **LEVEL 1: NORMAL** | $< 2.0$ | $< 70\%$ | Hijau | Gerbang turnstile dibuka penuh dengan laju normal. |
| **LEVEL 2: WASPADA** | $2.0 - 3.5$ | $70\% - 85\%$ | Kuning | Pengurangan kecepatan rotasi turnstile (15 orang/menit). |
| **LEVEL 3: TINGGI** | $3.5 - 4.5$ | $85\% - 95\%$ | Oranye | Pembatasan gerbang masuk (`GATE_RESTRICTED`), siagakan pintu sekunder. |
| **LEVEL 4: KRITIS** | $\ge 4.5$ | $\ge 95\%$ | Merah Flash | Penguncian gerbang masuk total, buka gerbang keluar sekunder dan evakuasi. |

## 2. Prosedur Eskalasi Gerbang
1. **Pendeteksian Dini (T-15 Menit):** Prediktor V14 memproyeksikan headcount menembus batas oranye/merah dalam 15 menit ke depan.
2. **Notifikasi Alarm Operator:** Operator keselamatan kerumunan (`crowd_safety_officer`) menerima peringatan audio dan visual di dashboard Live Heatmap.
3. **Pengalihan Arus Massa (Crowd Diversion):** Layar digital di atas gerbang beralih menampilkan petunjuk arah ke gerbang alternatif yang masih berkapasitas hijau.
4. **Pembukaan Gerbang Sekunder:** Jika gerbang alternatif berstatus terkunci, sistem mengirimkan instruksi pembukaan magnetik secara digital ke petugas gerbang.

