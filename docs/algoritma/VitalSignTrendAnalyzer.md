# Algoritma VitalSignTrendAnalyzer

## 1. Definisi dan Tujuan
Algoritma `VitalSignTrendAnalyzer` mendeteksi perburukan kondisi klinis pasien secara dini (*early clinical deterioration*) berbasis analisis regresi linier kemiringan tren (*slope*) terhadap rentet waktu tanda vital kontinual telemetri rawat inap.

## 2. Metodologi Analisis Tren
Untuk setiap deret waktu observasi tanda vital $y_i$ pada waktu $x_i$:
$$\text{Slope} = \frac{n \sum (x_i y_i) - (\sum x_i)(\sum y_i)}{n \sum x_i^2 - (\sum x_i)^2}$$

Kemiringan dihitung secara simultan untuk tiga indikator hemodinamik utama:
1. Kemiringan Frekuensi Denyut Jantung ($\text{Slope}_{HR}$).
2. Kemiringan Tekanan Darah Sistolik ($\text{Slope}_{SBP}$).
3. Kemiringan Saturasi Oksigen Perifer ($\text{Slope}_{SpO2}$).

## 3. Pola Dekompensasi Kritis
- **Divergensi Syok / Sepsis**: Dideteksi apabila $\text{Slope}_{HR} > 2.0$ denyut/menit per interval bersamaan dengan $\text{Slope}_{SBP} < -2.0$ mmHg per interval (*Shock Index Divergence*).
- **Gagal Napas Mendatang**: Dideteksi apabila $\text{Slope}_{SpO2} < -0.8\%$ per interval (*Oxygen Desaturation Trend*).
- **Takikardia Akseleratif**: Dideteksi apabila $\text{Slope}_{HR} > 4.0$ denyut/menit per interval.

## 4. Eskalasi Kegawatan
- `STABLE`: Tanpa pola divergensi bahaya.
- `ELEVATED_WATCH`: Terdeteksi 1 anomali tren; perawat meningkatkan frekuensi pemantauan menjadi tiap 30 menit.
- `CRITICAL_RRT_ESCALATION`: Terdeteksi $\ge 2$ sinyal divergensi; aktivasi otomatis Tim Respons Cepat (*Rapid Response Team*).
