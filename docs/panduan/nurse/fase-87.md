# Panduan Peran: Perawat Bangsal & IGD (Fase 87)

Panduan operasional lapangan untuk peran `nurse` di modul Rumah Sakit (Hospital). Dirancang khusus agar optimal digunakan pada perangkat genggam (layar 375 px).

## 1. Rute & Akses
- `/hospital/nursing`: Papan tugas perawat (Nurse Board) 375 px: daftar tugas harian, input vital signs, daftar obat terjadwal, dan peringatan keterlambatan order.
- `/hospital/er`: Papan triase dan antrean IGD.
- `/hospital/critical-care`: Monitor status pasien kritis dan alarm Code Blue.

## 2. Prosedur Tugas Lapangan

### 2.1 Pencatatan Tanda Vital & Evaluasi NEWS2
1. Pada papan bangsal `/hospital/nursing`, pilih nama pasien yang sedang dirawat.
2. Masukkan parameter tanda vital: Laju Napas, SpO2, Oksigen Tambahan (Ya/Tidak), Tekanan Sistolik, Nadi, Kesadaran (Alert / CVPU), dan Suhu Tubuh (°C).
3. Tekan **Simpan Vital Sign**. Sistem seketika menghitung skor NEWS2:
   - Skor 0–4: Risiko Rendah (pemantauan per shift).
   - Skor 3 tunggal / 5–6: Risiko Sedang (laporkan dokter ruangan).
   - Skor >= 7: Risiko Tinggi (persiapkan peralatan resusitasi darurat).

### 2.2 Merespons Alarm Keterlambatan Clinical Pathway
1. Jika order dokter (misal: pengambilan darah lab cito) tertunda lebih dari 15 menit, kartu tugas pada papan perawat akan berubah warna kuning dengan badge `TERLAMBAT`.
2. Segera tindak lanjuti order tersebut ke unit laboratorium atau diagnostik terkait.
3. Setelah tindakan diambil, tandai order sebagai `In Progress`.

### 2.3 Tanggap Pertama Code Blue (Menit 0–2)
1. Apabila monitor telemetri atau tombol darurat memicu Code Blue, banner merah berkedip muncul di bagian atas layar perawat.
2. Tekan tombol **Acknowledge Code Blue** dalam waktu < 2 menit untuk mencegah eskalasi liar ke seluruh rumah sakit.
3. Lakukan bantuan hidup dasar (BHD) hingga dokter tiba di lokasi.
