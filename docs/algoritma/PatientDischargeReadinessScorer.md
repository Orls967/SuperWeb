# Algoritma PatientDischargeReadinessScorer

## 1. Definisi dan Tujuan
Algoritma `PatientDischargeReadinessScorer` mengevaluasi kesiapan klinis dan logistik pasien rawat inap untuk dipulangkan (*discharge readiness*) guna meminimalkan risiko readmisi tidak terencana dalam tempo 30 hari pasca pulang (*unplanned 30-day readmission*).

## 2. Dimensi Penilaian (7 Pilar Kesiapan)
1. **Stabilitas Hemodinamik & Vital 24 Jam (25 Poin)**: Tanda vital stabil tanpa fluktuasi kritis dan skor NEWS2 $\le 2$.
2. **Toleransi Nutrisi & Hidrasi Oral (15 Poin)**: Asupan oral adekuat tanpa mual/muntah berulang.
3. **Kemandirian Fungsional / Ambulasi (15 Poin)**: Kemampuan mobilisasi dasar aman mandiri atau dengan bantuan minimal.
4. **Kontrol Nyeri Terkendali (15 Poin)**: Skor nyeri VAS $\le 3$ terkontrol dengan analgesik oral standar.
5. **Konversi Medikasi Oral (10 Poin)**: Seluruh obat intravena esensial telah beralih ke rute per oral.
6. **Pelatihan & Kesiapan Pendamping / Caregiver (10 Poin)**: Keluarga telah teredukasi perawatan luka, jadwal obat, dan tanda bahaya.
7. **Pengaturan Transportasi & Logistik Rumah (10 Poin)**: Kepastian kendaraan pulang dan ketersediaan sarana pendukung medis di rumah.

## 3. Aturan Pemblokir Keras (*Hard Blocker*)
Terlepas dari jumlah poin yang diperoleh, pemulangan pasien DILARANG (`is_ready_for_discharge = false`) apabila:
- Terdapat hasil pemeriksaan penunjang kritis (lab/radiologi cito) yang belum ditinjau oleh DPJP.
- Tanda vital belum stabil dalam 24 jam terakhir atau skor NEWS2 $> 2$.
- Nyeri hebat yang belum terkendali dengan analgesik oral.

## 4. Sertifikasi Integritas
```text
SigPayload = "PDRS-V1:{points}:{status}:{isReady}:{input_hash}"
Signature = HMAC_SHA256(SigPayload, SECRET_KEY)
```
Sertifikat ini disertakan ke dalam *Discharge Summary* dan resume medis elektronik pasien.
