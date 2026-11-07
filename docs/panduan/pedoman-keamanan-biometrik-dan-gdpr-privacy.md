# Pedoman Keamanan Biometrik & Kepatuhan Privasi Data — Fase 117

## 1. Landasan Hukum & Regulasi
Penerapan otentikasi wajah (Face-ID) pada gerbang turnstile venue wajib tunduk pada kerangka regulasi perlindungan data pribadi:
1. **Undang-Undang Perlindungan Data Pribadi (UU PDP No. 27/2022 Indonesia):**
   - Pasal 4: Data biometrik diklasifikasikan sebagai **Data Pribadi yang Bersifat Spesifik**.
   - Pasal 20: Pemrosesan data spesifik wajib memiliki dasar hukum persetujuan eksplisit tertulis atau terekam secara sah.
   - Pasal 35: Pengendali data wajib melindungi dan memastikan keamanan data melalui enkripsi dan pemusnahan data mentah.
2. **General Data Protection Regulation (GDPR) Uni Eropa:**
   - Pasal 9(1): Larangan umum pemrosesan data biometrik untuk tujuan identifikasi unik tanpa dasar Pasal 9(2)(a) (Explicit Consent).
   - Pasal 17: Hak Penghapusan (Right to Erasure / Right to be Forgotten).

## 2. Implementasi Teknis Kepatuhan Privasi
- **Zero Raw Data Policy:** Citra foto kamera liveness sensor atau vektor koordinat 128/512 dimensi diproses semata-mata pada chip perangkat keras gerbang (edge computing) atau volatile RAM server.
- **Kriptografi One-Way Hash:** Templat biometrik dienkripsi menggunakan fungsi hash satu arah HMAC-SHA256 dengan salt rahasia per-venue.
- **Transparansi Tamu:** Tamu wajib mencentang persetujuan digital di aplikasi ponsel sebelum mendaftarkan wajah.
- **Mekanisme Pencabutan (Revocation Mechanism):** Tamu dapat menonaktifkan biometrik mereka kapan saja, yang seketika menghapus hash templat dan beralih ke tiket QR berputar.

