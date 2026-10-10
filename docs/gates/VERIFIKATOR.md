# Panduan Verifikator Fase (P7)

Panduan langkah demi langkah untuk **verifikator independen**, yaitu sesi agent yang tidak ikut mengerjakan fase, atau pemilik. Verifikator membuktikan atau menolak klaim sebuah fase berstatus 🔵 memakai checklist C1–C14 (`docs/PROGRESS.md` §P7), lalu menulis hasilnya di bagian Verifikasi `docs/gates/fase-{N}.md`.

Isi panduan ini diambil dari verifikasi Fase R0 (10–11 Okt 2026). Setiap contoh temuan di bawah benar-benar terjadi.

---

## 0. Prinsip

1. **Jangan percaya laporan.** Jalankan sendiri setiap command. Laporan pelaksana, termasuk tabel "sabotase terbukti", hanya petunjuk tempat mulai memeriksa.
2. **Bandingkan dengan teks asli item**, bukan dengan Bukti atau ringkasan pelaksana. Teks item di `PROGRESS.md` adalah kontrak (P10).
3. **Verifikator tidak menulis fitur.** Yang boleh dibuat verifikator:
   - catatan dan bagian Verifikasi;
   - test yang menunjukkan kekurangan (merah), dengan perbaikannya diserahkan ke pelaksana;
   - perbaikan kecil yang menghalangi verifikasi.

   Semua itu dibuat di **branch terpisah** (mis. `docs/…`, `tools/…`) dan diserahkan ke pelaksana untuk di-merge dengan **merge commit**. Verifikator tidak pernah push ke branch pelaksana.
4. **Setiap butir C butuh bukti**: command, exit code, potongan output asli, atau hash commit. "Sudah dicek" tanpa bukti = belum dicek.
5. **Ragu → 🔁**, bukan ✅. Minus P0/P1 yang masih terbuka otomatis menghasilkan 🔁 (P9).

---

## 1. Persiapan

```bash
# Ambil branch fase tanpa mengganggu salinan kerja sendiri
git fetch origin <branch-fase>
git worktree add ../verifikasi-<fase> origin/<branch-fase>
cd ../verifikasi-<fase>

# Dependensi persis seperti lock. JANGAN pakai --ignore-platform-reqs.
composer install
cp .env.example .env && php artisan key:generate
npm ci && npm run build            # bila npm tidak tersedia, catat di laporan; CI tetap wajib
```

**Status CI** (REST API; `gh` login dengan token milik pemilik/verifikator, lihat `RUNBOOK.md` §7):

```bash
gh run list --repo Orls967/superweb --branch <branch-fase> --limit 5
gh api repos/Orls967/superweb/actions/runs/<run-id>/jobs \
  --jq '.jobs[] | "\(.name): \(.conclusion) | gagal di: \([.steps[] | select(.conclusion=="failure") | .name] | join(","))"'
gh run download <run-id> --repo Orls967/superweb --dir /tmp/ci-<run-id>
```

Yang dicek: ketiga job (`PHP 8.4 × SQLite (Full Gate Suite)`, `PHP 8.4 × MySQL 8 (DB Portability)`, `PHP 8.4 × Pest Mutation Testing (min 60%)`) hijau pada **commit kode terakhir** fase. Run yang hijau di commit lain tidak dihitung.

> **Larangan keamanan:** jangan pernah memanggil `git credential fill`, `gh auth token`, atau cara lain untuk membaca/mencetak token. Bila `gh` belum login, minta pemilik login (K-39).

---

## 2. Checklist C1–C14 dengan langkah konkret

### C1 — `ProgressIntegrityTest` hijau dan tidak bisa dikelabui

```bash
vendor/bin/pest tests/Architecture/ProgressIntegrityTest.php
```

Hijau saja belum cukup. Jalankan **katalog sabotase** di §3 pada salinan `PROGRESS.md`. Setiap sabotase wajib memerahkan test, lalu pulihkan dengan `git checkout -- docs/PROGRESS.md`.

Contoh nyata R0: lima dari tujuh sabotase pertama lolos, misalnya mencentang item fase lama tanpa Bukti dan mengubah status fase lama menjadi ✅. Test tetap hijau.

### C2 — Gate dijalankan sendiri

```bash
composer gate                       # manifest + JUnit di storage/logs/
php artisan arch:scan
php artisan bank:reconcile
php artisan chain:audit-all
```

Bandingkan dengan laporan `docs/gates/fase-{N}.md` dan artefak CI:
- `diff /tmp/ci-<run-id>/docs/gates/fase-<n>.md docs/gates/fase-<n>.md` harus kosong. Laporan tidak boleh disunting tangan (X24).
- Commit di laporan sama dengan commit kode terakhir. `git diff <commit-laporan> HEAD --stat` hanya boleh menyentuh `docs/`.
- Sembilan langkah gate ada dengan exit code 0. `pint` dijalankan tanpa `--dirty` (R0: `pint --test --dirty` membuat pemeriksaan format selalu lulus).
- Jumlah test tidak turun tanpa penjelasan.

### C3 — Implementasi sesuai teks asli item

Untuk setiap item:
1. Baca teks item, termasuk sub-butir "Test wajib" dan "Kriteria terima".
2. Buka setiap file di blok Bukti.
3. Tandai bagian teks yang tidak punya padanan di kode.

Pola penyempitan yang ditemukan di R0:
- "rute baru tanpa entri → gagal" dipenuhi dengan memetakan 299 rute operasional sebagai `'auth'`;
- "role tak berhak → 403" diuji dengan role palsu (`unauthorized_tester`) alih-alih role nyata;
- "setiap audit punya fixture korupsi: bersih → exit 0" diuji pada database **kosong**;
- status sukses tanpa aksi (X9).

### C4 — Jalan masuk dan otorisasi

```bash
vendor/bin/pest tests/Architecture/RouteAuthorizationMatrixTest.php
php artisan route:list --json | php -r '$r=json_decode(stream_get_contents(STDIN),true); foreach($r as $x){ if(str_contains($x["uri"],"<prefix>")) echo $x["method"]," ",$x["uri"]," ",implode(",",$x["middleware"]),PHP_EOL; }'
```

Untuk rute fitur fase ini: role berhak harus bukan 403/500, role nyata yang tidak berhak harus 403, dan menu harus muncul. Halaman 500 sering baru ketahuan di sini (R0: `contract.clauses.create`, `contract.reports`).

### C5 — Uang

```bash
vendor/bin/pest tests/Architecture/LedgerAccountRegistryTest.php tests/Architecture/LedgerNormalBalanceTest.php
```

Periksa empat hal:
- akun ada di `{M}Accounts` dan di seeder;
- arah posting sesuai `KONSEP.md` §A2.1 (kredit +, debit −);
- aksi yang memposting dipanggil **dua kali** menghasilkan satu transaksi ledger **dan** satu perubahan state;
- tidak ada `LedgerAccount::create` di test baru. TestHygiene T2 harus tetap hijau tanpa menaikkan baseline.

### C6 — Audit bisa gagal

Jalankan audit modul pada database hasil seed (harus exit 0). Lalu rusak satu baris data sumber, dan audit harus exit ≠ 0 serta menyebut entitas yang rusak. Contoh untuk ledger:

```bash
php artisan migrate:fresh --seed
php artisan bank:reconcile; echo "exit $?"            # 0
php artisan tinker --execute 'DB::table("bank_ledger_accounts")->where("id", 1)->increment("cached_balance", 1);'
php artisan bank:reconcile; echo "exit $?"            # ≠ 0
```

### C7 — Test benar-benar menguji

```bash
# Assertion yang tidak bisa gagal / test yang dilemahkan
git diff <base>..HEAD -U0 -- '*Test.php' | grep -E '^-.*(assert|expect)'
grep -rn "assertTrue(true)\|assertNotNull(\$[a-z_]*->[a-z_]*_id)" <file-test-fase>

# Mutation testing pada kelas yang diubah. Butuh driver coverage (PCOV/Xdebug) dan vendor/pest-plugins.json
# (dibuat plugin Composer pestphp/pest-plugin saat composer install; bila hilang, --mutate dianggap opsi tak dikenal).
# Jalankan pest langsung agar driver coverage aktif di proses yang memutasi.
php -d extension=pcov vendor/bin/pest --mutate --class='<FQCN>' --min=60
# Di CI: php artisan test:mutate --git-diff --base=<branch-dasar> --min=60
```

Komentari satu baris inti implementasi. Test bukti harus merah.

Contoh R0: `assertNotNull($model->approval_id)` tetap lulus walau isinya UUID di kolom angka yang tidak merujuk apa pun (K-40).

### C8 — Baseline tidak naik, Integration tidak tumbuh

```bash
git diff <base>..HEAD --stat -- tests/Architecture/baselines/
git diff <base>..HEAD -- tests/Architecture/baselines/ | grep -E '^\+.*"(total|count)"'
vendor/bin/pest tests/Architecture/IntegrationFreezeTest.php tests/Architecture/ArchScanBaselineTest.php
```

Angka yang naik harus punya entri `approved_additions` yang menunjuk anchor `DECISIONS.md` yang ada, serta persetujuan pemilik.

### C9 — Teks item tidak berubah

```bash
git diff <base>..HEAD -- docs/PROGRESS.md | grep -E '^[-+]\s*- \[[ x]\]'
```

Baris yang berubah hanya boleh di kotak centang. Perubahan teks wajib memakai `⬇️ diturunkan: <alasan>`, punya entri DECISIONS yang menyebut ID item, dan disetujui pemilik.

### C10 — Seeder dan portabilitas database

```bash
php artisan migrate:fresh --seed                       # SQLite
vendor/bin/pest tests/Architecture/MysqlSchemaCompatibilityTest.php
```

Job CI `portability-mysql` adalah bukti bahwa migrasi dan seeder berjalan di MySQL 8.4. Bila job itu merah, gunakan perkiraan data-vs-tipe-kolom di `KNOWLEDGE.md` §9.2 untuk menemukan semua pelanggaran sekaligus.

### C11 — Register Minus lengkap dan jujur

- Angka di Register Minus harus sama dengan file baseline. R0: Register Minus menulis 7.718 pelanggaran `arch:scan`, padahal baseline 7.273.
- Setiap temuan verifikator ditambahkan sebagai minus dengan prioritas jujur.
- Minus P0/P1 terbuka → 🔁.

### C12 — Dokumen

Keputusan baru punya entri `DECISIONS.md`. Contoh R0: konvensi `approval_id`, konstanta genesis, dan kolom yang diperpendek.

Bagian faktual `CODEBASE.md`/`ARCHITECTURE.md` harus sesuai kode. Blocker yang butuh keputusan pemilik ada di `BLOCKERS.md`.

### C13 — Riwayat commit

```bash
git log --format='%h %s' <base>..HEAD
git log --merges --format='%h %s' <base>..HEAD
```

Periksa:
- satu commit per item;
- tidak ada item fase lain yang dicentang;
- perubahan detektor tidak dicampur dengan pengisian Bukti di satu commit;
- tidak ada force-push (hash Bukti masih ada di riwayat).

### C14 — Red flags P12

```bash
# Ukuran perubahan non-test per commit
git log --format='%h %s' --shortstat <base>..HEAD -- . ':!*Test.php' ':!docs'
# Test yang hijau sejak run pertama tanpa pernah merah tercatat → cari "merah" di pesan commit
git log --format='%h %s%n%b' <base>..HEAD | grep -ci 'merah\|red'
```

---

## 3. Katalog sabotase `ProgressIntegrityTest`

Lakukan pada salinan kerja verifikator. Setiap baris wajib **menambah** jumlah pelanggaran. Bila test sudah merah sebelum sabotase, bandingkan jumlah pelanggarannya, jangan hanya status merah/hijau. Pulihkan dengan `git checkout -- docs/PROGRESS.md docs/gates/`.

| ID | Sabotase | Harus ditolak karena |
|---|---|---|
| S1 | Centang `[x]` satu item fase lama (mis. 55.1) tanpa blok `Bukti:` | Item yang dicentang setelah snapshot selalu diperiksa |
| S2 | Ubah ikon status fase lama (mis. Fase 55) menjadi ✅, tanggal lama dibiarkan | Fase yang statusnya berubah dari snapshot selalu diperiksa; ✅ butuh laporan + Verifikasi |
| S3 | Centang item Fase R dengan Bukti `jenis: tooling — …` tanpa `test:` | `tooling`/`konfigurasi` wajib punya test |
| S4 | Bukti `audit: inspire` (command tanpa fixture korupsi) | `audit` wajib punya fixture di `AuditCommandContractTest`; pemindai tidak boleh crash |
| S5 | Persempit teks item (mis. R1.2) lalu tambahkan "⬇️" saja | ⬇️ butuh alasan dan entri DECISIONS yang menyebut ID item |
| S6 | Centang item fitur (tanpa `jenis:`) dengan `test:` tetapi tanpa `akses:` | Item fitur wajib punya akses dan test HTTP/command |
| S7 | Ubah status fase menjadi ✅ dan salin laporan gate fase lain sebagai `fase-<n>.md` (bagian Verifikasi masih template kosong) | Template kosong bukan verifikasi; butuh tanggal, commit, verifikator, hasil C1–C14 |

Hasil R0 per 11 Okt: S1, S2, S4, S5, S6 sudah ditolak. S3 dan S7 masih lolos dan sedang diperbaiki pelaksana.

---

## 4. Templat bagian Verifikasi

Tempel di akhir `docs/gates/fase-{N}.md`, menggantikan template kosong buatan `gate:report`. `gate:report` mempertahankan bagian ini saat laporan dibuat ulang.

```markdown
## 5. Verifikasi

- **Tanggal:** 2026-10-12
- **Verifikator:** <sesi/agent atau nama pemilik>
- **Commit yang diverifikasi:** <hash lengkap> (CI run <run-id>: SQLite ✅, MySQL ✅, Mutation ✅)
- **Keputusan:** ✅ TERVERIFIKASI | 🔁 DIBUKA KEMBALI

| # | Hasil | Bukti (command / commit / output) | Catatan |
|---|---|---|---|
| C1 | lulus/gagal | `pest tests/Architecture/ProgressIntegrityTest.php` exit 0; sabotase S1–S7 merah | |
| C2 | | | |
| C3 | | | |
| C4 | | | |
| C5 | | | |
| C6 | | | |
| C7 | | | |
| C8 | | | |
| C9 | | | |
| C10 | | | |
| C11 | | | |
| C12 | | | |
| C13 | | | |
| C14 | | | |

**Minus yang ditambahkan verifikator:** M-<N>-x, M-<N>-y (lihat Register Minus).
**Alasan 🔁 (bila ada):** daftar butir C yang gagal dan minus P0/P1 terbuka.
```

---

## 5. Pola jalan pintas yang ditemukan saat verifikasi R0

Daftar ini dipakai sebagai daftar periksa cepat sebelum masuk ke C1–C14.

| Pola | Di mana terlihat | Butir |
|---|---|---|
| Pemeriksaan gate dibuat selalu lulus (`pint --test --dirty`) | script `gate`, manifest | C2 |
| Laporan gate dibuat ulang setelah kode berubah, tanpa gate ulang | commit laporan vs commit kode | C2, C13 |
| Opsi `--force` yang melewati penolakan laporan | signature `gate:report` | C2 |
| Detektor diubah di commit yang sama dengan pengisian Bukti, setelah detektor itu menolak Bukti | `git show --stat` | C1, C13 |
| Lingkup detektor bergantung pada tanggal yang ditulis tangan | sabotase S1/S2 | C1 |
| Template kosong dianggap verifikasi | sabotase S7 | C1 |
| Test fixture menyalin daftar dari `PROGRESS.md` asli sehingga harus ikut diubah setiap kali Bukti berubah | test laporan gate | C7 |
| `assertNotNull` pada kolom rujukan | test alur approval | C7 |
| `LedgerAccount::create` di test baru | TestHygiene T2 | C5, C8 |
| Kolom diperpendek / tipe diubah demi lolos MySQL tanpa memeriksa penulis nilainya | migrasi + service | C3, C10 |
| Konstanta yang tersimpan di data (genesis hash) diubah | service + DECISIONS | C3, C12 |
| Dua agent mengerjakan fase yang sama di branch yang sama | riwayat branch | C13 |
| Agent membaca token dari penyimpanan kredensial | log alat agent | keamanan (K-39) |
