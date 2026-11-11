# ADR-0041: Peta Dependensi Modul, Directed Acyclic Graph & Algoritma Tarjan (W8-D41)

## Status
Diterima (Accepted)

## Konteks
Dengan skala platform SuperWeb yang mencakup lebih dari 65 modul bisnis dan kernel, dependensi kode yang tidak terkendali dapat menciptakan kopling sirkular (*circular dependency / cyclic dependencies*). Sirkularitas antar modul melanggar *Acyclic Dependencies Principle* (Robert C. Martin, 1996), mempersulit pengujian independen, membuat migrasi basis data rentan terhadap kegagalan urutan, serta menghancurkan isolasi batas modul yang bersih. Sistem membutuhkan pemindai dependensi statis deterministik dan mekanisme penegakan topologi Directed Acyclic Graph (DAG) di tingkat kondensasi modul.

## Keputusan
1. **Pemodelan Graf Dependensi Statis**:
   Simpul graf didefinisikan sebagai modul bisnis di bawah direktori `modules/*`. Busur (*directed edges*) $u \to v$ dibangun dari analisis statis berkas PHP pada modul $u$ yang mereferensikan namespace modul $v$ (melalui pernyataan `use`, pemanggilan `DB::table`, `Schema::*`, atau penulisan nama kelas berkualifikasi penuh `\Modules\V\...`).
2. **Algoritma Tarjan Strongly Connected Components (SCC)**:
   Mengimplementasikan algoritma Tarjan SCC deterministik pada `Modules\Core\Domain\Algorithms\Boundaries\StronglyConnectedComponents`:
   - Penelusuran Depth-First Search (DFS) memproses modul secara deterministik berdasarkan urutan alfabetis simpul nama modul.
   - Komponen terhubung kuat (SCC) diurutkan berdasarkan simpul representatif terkecil secara leksikografis.
   - Komponen berukuran $|SCC| > 1$ dinyatakan sebagai siklus dependensi terlarang (*forbidden cycle*).
3. **Kondensasi DAG & Verifikasi Sertifikat**:
   Mengubah graf modul menjadi DAG kondensasi simpul super (*component graph*). Kelas `SccCertificateVerifier` memvalidasi topologi DAG kondensasi untuk memastikan tidak ada busur mundur (*back-edge*) atau dependensi berulang tanpa izin.
4. **Alat Pemindai Command `arch:boundaries`**:
   Command `php artisan arch:boundaries` menghasilkan representasi graf dependensi modul dalam format `--format=table|json|md|svg`. Keluaran bersifat deterministik murni tanpa stempel waktu pada isi keluaran (menjamin verifikasi byte-identik pada input yang sama).

## Konsekuensi
- Mencegah dependensi siklis antar modul secara dini pada tahap CI dan pre-push.
- Memfasilitasi generasi otomatis katalog dependensi arsitektur di `docs/catalog/dependensi-modul.md`.
- Setiap pelanggaran siklus dependensi harus diurai melalui pemisahan kontrak ke `Modules\Core\Contracts` atau komunikasi asinkron via `EventSpine`.

