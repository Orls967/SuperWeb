# Spesifikasi Algoritma — Table Prefix Ownership Resolver (Longest Prefix Match)

**ID Algoritma**: `R4-ALG-02`  
**Kelas Implementasi**: `Modules\Core\Domain\Algorithms\Boundaries\TablePrefixOwnershipResolver`  
**Kelas Verifier**: `Modules\Core\Domain\Algorithms\Boundaries\PrefixCertificateVerifier`  

---

## 1. Masalah & Tujuan
Dalam arsitektur monolit modular dengan basis data bersama (*shared database*), kegagalan isolasi skema dapat menyebabkan benturan nama tabel (*naming collisions*) dan akses lintas modul tanpa izin.
Tujuan algoritma ini adalah:
1. Menentukan modul pemilik tunggal dari setiap nama tabel secara deterministik berdasarkan aturan kecocokan prefiks terpanjang (*Longest Prefix Match*).
2. Mendukung prefiks bertumpuk/hierarkis (*nested prefixes*), di mana prefiks yang lebih panjang mendominasi prefiks yang lebih pendek (contoh: `hcm_pay_` mendominasi `hcm_`).
3. Mendeteksi dan menolak konflik prefiks (*PREFIX_CONFLICT*), yaitu ketika dua modul berbeda mendaftarkan prefiks string yang identik.
4. Memvalidasi daftar pengecualian resmi (`legacy_tables` dan `shared_tables`) untuk memastikan tidak ada akses liar tak berizin.

---

## 2. Pseudokode Algoritma

```text
Algorithm ResolveTableOwnership(tableName, registeredPrefixes, legacyTables, sharedTables, requestingModule):
    Input:
        tableName: Name of table (string)
        registeredPrefixes: Map of prefix -> ownerModule
        legacyTables: Map of exactTableName -> ownerModule
        sharedTables: Map of module -> List of allowedTableNames
        requestingModule: Module attempting access (string or null)
    Output:
        ownerModule: Module owning the table, or null if unregistered
        isAllowed: Boolean indicating whether requestingModule is authorized
        matchedPrefix: The specific prefix matched (string or null)

    1. Check legacy tables (exact match has highest precedence):
       if tableName in legacyTables:
           owner = legacyTables[tableName]
           allowed = (requestingModule == owner) or (requestingModule in sharedTables and tableName in sharedTables[requestingModule])
           return Result(owner = owner, isAllowed = allowed, matchedPrefix = null, isLegacy = true)

    2. Sort registered prefixes by length descending, then alphabetically:
       sortedPrefixes = sort(registeredPrefixes, by = [length DESC, prefix ASC])

    3. Find longest matching prefix:
       matchedPrefix = null
       owner = null
       for each (prefix, module) in sortedPrefixes:
           if tableName.startsWith(prefix):
               matchedPrefix = prefix
               owner = module
               break

    4. Evaluate authorization for requesting module:
       if owner is null:
           return Result(owner = null, isAllowed = false, matchedPrefix = null, reason = "UNREGISTERED_PREFIX")

       if requestingModule == owner:
           isAllowed = true
       else if requestingModule in sharedTables and tableName in sharedTables[requestingModule]:
           isAllowed = true
       else if "*" in sharedTables and tableName in sharedTables["*"]:
           isAllowed = true
       else:
           isAllowed = false

       return Result(owner = owner, isAllowed = isAllowed, matchedPrefix = matchedPrefix)
```

---

## 3. Kompleksitas & Analisis
- **Pengurutan Prefiks**: Dilakukan sekali saat inisialisasi pada waktu $\mathcal{O}(P \log P)$, di mana $P$ adalah jumlah prefiks terdaftar ($P \approx 70$).
- **Resolusi Tabel**: Untuk setiap kueri tabel, pencarian linier pada prefiks terurut membutuhkan waktu $\mathcal{O}(P \cdot L)$, di mana $L \le 20$ adalah panjang prefiks. Dengan $P \approx 70$, resolusi membutuhkan waktu $< 2$ mikrodetik per tabel.
- **Kompleksitas Ruang**: $\mathcal{O}(P + T)$ di mana $T$ adalah jumlah tabel dalam skema basis data.

---

## 4. Sertifikat & Verifikasi (`PrefixCertificateVerifier`)
`PrefixCertificateVerifier` memvalidasi integritas registrasi dan resolusi:
1. **Bebas Konflik**: Tidak ada dua pasangan $(p_1, m_1)$ dan $(p_2, m_2)$ dengan $p_1 = p_2$ dan $m_1 \neq m_2$.
2. **Kesesuaian Format Prefiks**: Setiap prefiks wajib berhuruf kecil, alfanumerik, dan diakhiri dengan garis bawah (`_`).
3. **Deterministik Longest Match**: Jika suatu tabel cocok dengan beberapa prefiks (misal `hcm_pay_bonuses` cocok dengan `hcm_` dan `hcm_pay_`), resolver terbukti memilih prefiks dengan panjang karakter terbesar (`hcm_pay_`).

