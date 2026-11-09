# Spesifikasi Matematis & Algoritma: ClassReachabilityGraph (A3)

## 1. Landasan Teoretis

Deteksi kode mati (*dead code*) dan kelas tak terpakai (*orphan classes*) pada skala arsitektur monolit modular diselesaikan melalui pemodelan graf terarah ketergantungan kelas (Directed Class Dependency Graph).

### Definisi Formal Graf

Diberikan graf terarah $G = (V, E)$, di mana:
- $V = \{c_1, c_2, \dots, c_n\}$ adalah himpunan semua kelas, antarmuka (interface), trait, dan enum yang terdefinisi dalam basis kode.
- $E \subseteq V \times V$ adalah himpunan busur berarah, di mana busur $(c_i, c_j) \in E$ menyatakan bahwa kelas $c_i$ memiliki ketergantungan langsung terhadap kelas $c_j$ (melalui import `use`, perluasan `extends`, implementasi `implements`, type-hint parameter, atau instansiasi langsung `new $c_j`).

### Ketercapaian (Reachability) dari Titik Masuk (Entry Points)

Himpunan titik masuk $S \subset V$ didefinisikan sebagai elemen sistem yang dieksekusi secara eksternal:
- HTTP Controllers (`App\Http\Controllers\*`, `Modules\*\Http\Controllers\*`)
- Artisan Console Commands (`App\Console\Commands\*`, `Modules\*\Console\Commands\*`)
- Service Providers (`App\Providers\*`, `Modules\*\Providers\*`)
- Middleware (`App\Http\Middleware\*`)
- Job & Event Listeners (`App\Jobs\*`, `App\Listeners\*`)

Sebuah kelas $c \in V$ dikatakan **tercapai** (*reachable*) jika terdapat lintasan berarah $p = (v_0, v_1, \dots, v_k)$ di mana $v_0 \in S$ dan $v_k = c$.

Sebuah kelas $c \in V$ diklasifikasikan sebagai **kelas yatim (orphan / unreachable)** jika:
$$\forall s \in S, \quad s \not\rightsquigarrow c$$
artinya tidak ada lintasan berarah dari sembarang titik masuk sistem menuju kelas tersebut.

## 2. Algoritma Penelusuran Graf (Breadth-First Search)

Penelusuran ketercapaian diimplementasikan menggunakan antrean Breadth-First Search (BFS) multi-sumber:

```
Inisialisasi antrean Q = himpunan semua s in S
Inisialisasi set Terkunjungi = himpunan semua s in S

Selama Q tidak kosong:
    c = Q.pop_front()
    Untuk setiap tetangga t dari c (di mana (c, t) in E):
        Jika t belum ada dalam Terkunjungi:
            Terkunjungi.add(t)
            Q.push_back(t)

Himpunan Yatim = V \ Terkunjungi
```

## 3. Pengecualian dan Batas Cakupan

Beberapa kelas memiliki sifat meta-programming atau dipanggil secara reflektif oleh framework:
- Migration classes (`Create*Table`): dipanggil oleh runner migrasi Laravel.
- Database Seeders (`*Seeder`): dipanggil via CLI seeding.
- Test suites (`*Test`): dijalankan oleh runner Pest/PHPUnit.
- Model Eloquent yang dipanggil melalui polimorfisme string.

Graf ketergantungan kelas memberikan bobot khusus atau penandaan entrypoint sekunder untuk kelas-kelas infrastruktur tersebut agar tidak salah diklasifikasikan sebagai kode yatim.

## 4. Sertifikat dan Verifikasi Ketercapaian

- `ClassReachabilityCertificate`: Merekam seluruh matriks ketergantungan kelas, daftar entry points, persentase ketercapaian, dan daftar kelas yatim yang terdeteksi.
- `ClassReachabilityCertificateVerifier`: Memastikan tidak ada kelas yatim baru yang masuk ke dalam modul domain tanpa justifikasi entry point yang valid.

