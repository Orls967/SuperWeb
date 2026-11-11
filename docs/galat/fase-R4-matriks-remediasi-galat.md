# Katalog Galat & Matriks Remediasi — Batas Arsitektur Modular (Fase R4)

## 1. Ikhtisar Penanganan Galat
Dokumen ini merangkum kode galat arsitektur, penyebab akar (*root causes*), dan prosedur penyelesaian terstandarisasi untuk memastikan batas arsitektur modular tetap kokoh.

---

## 2. Matriks Kode Galat

| Kode Galat | Nama / Kategori | Tingkat Keparahan | Penyebab Akar | Tindakan Remediasi Terstandarisasi |
| :--- | :--- | :--- | :--- | :--- |
| `ERR_A1_DOMAIN_IMPORT` | Pelanggaran Domain Import | Kritis (Blocker) | Modul memanggil kelas di bawah `Modules/{Other}/Domain/*` secara langsung | Ganti dengan interface kontrak di `Modules/{Other}/Contracts/*` atau gunakan `EventSpine` |
| `ERR_A2_TABLE_PREFIX` | Pelanggaran Prefiks Tabel | Kritis (Blocker) | Modul membuat/mengakses tabel dengan prefiks modul lain | Ajukan waiver bertenggat waktu ($\le 90$ hari) di ADR-0043 atau buat migrasi pemindahan tabel |
| `ERR_SCC_CYCLE` | Siklus Dependensi Modular | Fatal (CI Fail) | Dua modul atau lebih saling bergantung secara melingkar | Urai siklus dengan memindahkan kontrak bersama ke `Modules/Core/Contracts` |
| `ERR_WAIVER_NO_ANCHOR` | Waiver Tanpa Anchor | Tinggi (Audit Fail) | Record waiver tidak mencantumkan tautan `adr_anchor` di `docs/DECISIONS.md` | Daftarkan keputusan arsitektur di `docs/DECISIONS.md` dan tautkan anchor `#w8-d...` |
| `ERR_WAIVER_EXPIRED` | Waiver Kedaluwarsa | Tinggi (Audit Fail) | Masa berlaku waiver telah terlewati (`now() > expires_at`) | Jalankan `boundary:expire-waivers`, tinjau kebutuhan perpanjangan atau selesaikan refaktorisasi |
| `ERR_PREFIX_CONFLICT` | Konflik Prefiks Tabel | Fatal (Config Fail) | Dua modul berbeda mendaftarkan prefiks tabel yang identik di `config/modules.php` | Harmonisasi prefiks tabel dengan menambahkan namespace yang unik |
| `ERR_CLOSED_IN_BASELINE` | Pelanggaran Closed Masih di Baseline | Sedang (Ratchet Fail) | Status pelanggaran telah `closed` tetapi masih tercatat di `arch-scan.json` | Jalankan `php artisan arch:scan --update-baseline` untuk meratchet baseline ke bawah |
| `ERR_CONTRACT_DRIFT` | Pergeseran Kontrak | Rendah (Peringatan) | Interface kontrak tidak memiliki pemanggil atau tidak terikat di container | Daftarkan binding di ServiceProvider atau bersihkan interface yang usang |

---

## 3. Matriks Eskalasi Insiden Arsitektur
1. **Tingkat 1 (Peringatan Lokal)**: Penganalisis mendeteksi drift atau pemanggilan kontrak tidak terikat saat pengujian unit.
2. **Tingkat 2 (Gerbang Pre-Push)**: `boundary:audit` gagal mendeteksi diskrepansi database vs scan atau baseline JSON.
3. **Tingkat 3 (CI Blocker)**: Siklus SCC terdeteksi pada rantai modul bisnis di lingkungan build integrasi.

