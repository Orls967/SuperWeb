# Runbook Operasional: Penanganan Kerumunan Kritis & Pengalihan Gerbang — Fase 117

## 1. Identifikasi Insiden Kerumunan Kritis
- **Gejala:** Live Heatmap menampilkan warna Merah Flash, densitas $\ge 4.5 \text{ org/m}^2$, atau headcount zona melebihi $95\%$ kapasitas legal.
- **Waktu Tanggap:** Maksimal 60 detik sejak alarm otomatis AI berbunyi.

## 2. Langkah-Langkah Penanganan (SOP)
1. **Langkah 1: Verifikasi Visual Cepat (30 Detik)**
   - Operator membuka feed visual zona terkait di konsol admin.
   - Konfirmasi apakah penumpukan bersifat lokal di depan panggung atau di lorong akses masuk.
2. **Langkah 2: Eksekusi Gate Restriction**
   - Tekan tombol **"RESTRICT ACCESS"** pada konsol, atau biarkan sistem AI mengunci turnstile masuk secara otomatis.
   - Papan petunjuk digital (VMS) di gerbang masuk utama otomatis beralih menampilkan pesan: *"Gerbang Penuh — Silakan Gunakan Gerbang Timur / Gate B"*.
3. **Langkah 3: Pembukaan Gerbang Sekunder / Pintu Darurat**
   - Kirim sinyal pembukaan magnetik ke Gerbang B (Sekunder) via konsol.
   - Petugas keamanan lapangan (`crowd_safety_guard`) mengarahkan arus pengunjung dengan pengeras suara portabel.
4. **Langkah 4: Siagakan Paramedis (Bila Diperlukan)**
   - Jika terdeteksi pengunjung pingsan atau terjatuh, sistem memicu kontrak aditif G7-K23 (`MedicalEmergencyResponseDispatcher`) untuk mengirimkan tim medis ke koordinat zona.
5. **Langkah 5: Resolusi & Normalisasi**
   - Setelah densitas turun kembali di bawah $3.0 \text{ org/m}^2$ selama 10 menit, ubah status insiden menjadi `RESOLVED_CLEAR` dan pulihkan gerbang ke mode normal.

