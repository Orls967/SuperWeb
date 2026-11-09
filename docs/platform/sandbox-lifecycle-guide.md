# Panduan Arsitektur & Siklus Hidup Sandbox Developer (Fase 237)

## 1. Pendahuluan & Latar Belakang
Dalam arsitektur monorepo modular berorientasi multi-pilar bisnis (terdiri atas 65 modul dan ratusan migrasi), penyediaan lingkungan uji yang cepat, terisolasi, dan aman merupakan kebutuhan kritis tim pengembang. Tanpa mekanisme manajemen lingkungan yang terkendali, pengujian branch fitur berisiko mengalami:
1. Benturan skema database antar-developer (schema collisions).
2. Kebocoran data sensitif produksi ke lingkungan staging lokal (PII leakage).
3. Penumpukan resource komputasi dan database tak terpakai (resource exhaustion).

Modul `Platform` (`plt_`) menghadirkan subsistem **Sandbox Environment Lifecycle** dengan mesin status 7 tingkat (`SandboxEnvironmentStateMachine`) yang mengawal seluruh siklus hidup lingkungan developer dari penciptaan hingga terminasi.

---

## 2. Model Status & Diagram Transisi

State Machine `SandboxEnvironmentStateMachine` menerapkan 7 status diskret:

| Status | Kode Status | Deskripsi Operasional |
|---|---|---|
| **1. Requested** | `requested` | Permintaan lingkungan baru diterima dari developer/pipeline CI. Alokasi kuota divalidasi. |
| **2. Provisioning** | `provisioning` | Skema database SQLite/MySQL diisolasi, migrasi modular dijalankan hingga versi terkini. |
| **3. Seeded** | `seeded` | Injeksi dataset sintesis bebas PII dan konfigurasi pilar yang dipilih (domain slice). |
| **4. Ready** | `ready` | Lingkungan sehat, aktif, dan dapat diakses langsung oleh pengguna atau unit testing. |
| **5. Expiring** | `expiring` | Masa aktif (TTL) memasuki jendela peringatan (1 jam sebelum kedaluwarsa). |
| **6. Expired** | `expired` | Batas waktu TTL habis. Akses ke sandbox dikunci, proses write diblokir. |
| **7. Destroyed** | `destroyed` | Resource dibersihkan, file database dihapus, entri transisi ditutup permanen. |

### Matriks Transisi yang Diizinkan:
```
requested     -> [provisioning, destroyed]
provisioning  -> [seeded, destroyed]
seeded        -> [ready, destroyed]
ready         -> [expiring, expired, destroyed]
expiring      -> [ready, expired, destroyed]
expired       -> [destroyed, provisioning]
destroyed     -> [] (Terminal State)
```

Setiap transisi status dicatat secara **append-only** ke dalam tabel `plt_sandbox_transitions` yang menyimpan `from_status`, `to_status`, `reason`, `context` JSON, dan stempel waktu transisi.

---

## 3. Tiering & Manajemen Batas Waktu TTL

Tersedia tiga tier lingkungan sandbox (`SandboxTier`):
- **Ephemeral (`ephemeral`)**:
  - Digunakan untuk pipeline CI otomatis, unit test PR, dan pengetesan cepat branch.
  - Default TTL: 4 jam.
  - Maksimum perpanjangan: 12 jam.
- **Standard (`standard`)**:
  - Digunakan untuk pengembangan fitur aktif harian oleh pengembang individual.
  - Default TTL: 24 jam.
  - Maksimum perpanjangan: 72 jam.
- **Isolated (`isolated`)**:
  - Digunakan untuk pengujian integrasi skala besar, simulasi beban, atau pengujian CAB.
  - Default TTL: 72 jam.
  - Memerlukan tinjauan kapasitas sebelum diperpanjang.

Command terjadwal `platform:sandbox-expire` secara berkala mendeteksi sandbox yang melampaui `expires_at` dan memindahkannya ke status `expired`.

---

## 4. Jaminan Idempotensi & Anti-IDOR

### Idempotensi Transaksional (K-12 / A4):
Pembuatan sandbox dilindungi oleh kunci idempotensi wajib (`idempotency_key`). Kunci idempotensi dibangun secara deterministik dari identitas bisnis:
$$\text{IdempotencyKey} = \text{plt:create\_sandbox:}\{\text{slug}\}:\{\text{owner\_id}\}$$
Hal ini memastikan pengulangan request (misalnya retry akibat jaringan terputus) tidak akan menduplikasi alokasi database, melainkan mengembalikan instance yang telah ada.

### Proteksi Akses Obyek Langsung (Anti-IDOR):
Akses HTTP terhadap rute `/platform/dev/sandboxes/{sandbox}` dipagari oleh `SandboxEnvironmentPolicy`. Kebijakan ini menegakkan bahwa:
- Developer hanya dapat melihat, memperbarui, atau menghapus sandbox miliknya sendiri (`$user->id === $sandbox->owner_id`).
- Permintaan dari pengguna lain ditolak secara eksplisit dengan kode respon HTTP `403 Forbidden`.

---

## 5. Teardown dan Pemulihan Snapshot

Subsistem isolasi menyediakan kapabilitas **Snapshot Rollback**:
1. Developer dapat membekukan status sandbox yang telah ter-seed sebagai checkpoint.
2. Ketika pengujian merusak data atau mengubah state secara ireversibel, aksi `rollbackToSnapshot` mengembalikan sandbox ke state awal tanpa perlu membuat lingkungan dari nol.
3. Seluruh proses dekomisi menghapus file skema fisik dan mencatat jejak audit komprehensif.

