# Runbook Operasional: Kegagalan Liveness Biometrik & Override Manual Kios — Fase 117

## 1. Identifikasi Masalah
- **Gejala:** Tamu VIP atau member tidak dapat melewati turnstile wajah, muncul pesan *"Liveness Verification Failed (Skor < 85%)"*, atau lampu merah turnstile menyala terus-menerus.

## 2. Prosedur Investigasi di Kios
1. **Langkah 1: Cek Kondisi Fisik Pengunjung**
   - Pastikan pengunjung tidak menggunakan topi lebar, kacamata hitam dengan filter polarisasi ekstrem, atau masker bedah.
   - Minta pengunjung menghadap kamera kios dengan jarak ideal 50–80 cm dalam pencahayaan yang memadai.
2. **Langkah 2: Uji Ulang Liveness (Retry)**
   - Jalankan ulang tantangan liveness di layar (instruksikan pengunjung berkedip atau menoleh perlahan).
   - Maksimal 3 kali percobaan liveness dalam 2 menit.
3. **Langkah 3: Jalur Manual Override Petugas (Staff Fallback)**
   - Jika 3 kali percobaan gagal, petugas pintu masuk (`gate_operator`) memindai kartu staf supervisor di kios turnstile.
   - Periksa identitas fisik resmi tamu (KTP / Paspor / Member Digital QR di ponsel).
   - Tekan **"MANUAL QR ENTRY OVERRIDE"** di layar kios.
   - Sistem mencatat override manual ini dengan log audit supervisor untuk mencegah kolusi.

