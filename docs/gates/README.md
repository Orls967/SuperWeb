# Laporan Quality Gate (`docs/gates/`)

Folder ini berisi laporan gate resmi per fase (`docs/gates/fase-{N}.md`, mis. `fase-r0.md`) sesuai `docs/PROGRESS.md` §P4 (gate) dan §P7 (verifikasi silang). Halaman ini menjelaskan dari mana laporan berasal, cara membacanya, dan cara memeriksanya.

---

## 1. Aturan

1. **Laporan dihasilkan command, bukan ditulis tangan** (X13, X24). Isinya hanya boleh berasal dari:
   ```bash
   php artisan gate:report --fase=N
   ```
   Satu-satunya bagian yang ditulis manusia/verifikator adalah **Verifikasi** (§5).
2. **Laporan resmi berasal dari CI**, bukan dari laptop. Job `PHP 8.4 × SQLite (Full Gate Suite)` menjalankan `composer gate` lalu `gate:report`, kemudian mengunggah laporan, manifest, dan JUnit sebagai artefak. File di repo harus **identik** dengan artefak itu (§3).
3. **`gate:report` menolak membuat laporan** bila:
   - ada langkah gate yang gagal;
   - ada langkah wajib yang tidak tercatat di manifest;
   - commit di manifest ≠ HEAD;
   - working tree kotor saat gate berjalan;
   - JUnit mencatat failure/error;
   - test yang dirujuk blok `Bukti:` fase itu tidak lulus atau tidak ditemukan di JUnit.
4. **Antara commit yang digate dan HEAD hanya boleh ada perubahan di `docs/`.** Perubahan kode setelah gate membuat laporan basi; jalankan ulang CI.

---

## 2. Langkah gate (`composer gate` → `php artisan gate:run`)

| # | Langkah | Gagal bila |
|---|---|---|
| 1 | `composer validate --strict` | `composer.json`/`composer.lock` tidak sinkron |
| 2 | `vendor/bin/pest --parallel --log-junit=…` (suite **penuh**) | ada test gagal/error |
| 3 | `vendor/bin/pint --test` (semua file, **tanpa** `--dirty`) | ada file yang belum diformat |
| 4 | `php artisan arch:scan --json` | jumlah pelanggaran suatu aturan A1–A13 naik di atas baseline |
| 5 | `php artisan migrate:fresh --seed` | migrasi/seeder gagal |
| 6 | `php artisan bank:reconcile` | saldo cache ≠ jumlah entri, atau Σ per aset ≠ 0 |
| 7 | `php artisan chain:audit-all` | salah satu audit rantai nilai menemukan selisih |
| 8 | `php artisan super:health-check` | salah satu pemeriksaan kesehatan gagal |
| 9 | `npm run build` | build aset Vite gagal |

Portabilitas database diperiksa job CI terpisah, `PHP 8.4 × MySQL 8 (DB Portability)`: `migrate:fresh --seed` di MySQL 8.4 lalu `pest --group=db-portability`. Mutation testing berjalan di job `PHP 8.4 × Pest Mutation Testing (min 60%)` pada setiap pull request. Kedua job ini tidak masuk laporan SQLite, tetapi wajib hijau sebelum merge.

---

## 3. Mengambil laporan resmi dari CI

Lakukan setelah ketiga job CI hijau pada **commit kode terakhir** fase:

```bash
# 1. Cari run untuk commit itu
gh run list --repo Orls967/superweb --branch <branch> --limit 5

# 2. Unduh artefak laporan gate
gh run download <run-id> --repo Orls967/superweb --name gate-report-fase-<n> --dir /tmp/gate-<run-id>

# 3. Salin tanpa mengubah apa pun, lalu buktikan identik
cp /tmp/gate-<run-id>/docs/gates/fase-<n>.md docs/gates/fase-<n>.md
diff /tmp/gate-<run-id>/docs/gates/fase-<n>.md docs/gates/fase-<n>.md   # harus kosong

# 4. Commit hanya docs/
git add docs/gates/fase-<n>.md
git commit -m "docs(fase-<N>): laporan gate dari CI run <run-id>"
```

Tulis run-id CI di Bukti dan di Register Minus bila ada job yang belum hijau. Menjalankan `composer gate` di laptop tetap berguna sebagai pemeriksaan awal, tetapi hasilnya bukan laporan resmi.

---

## 4. Struktur Laporan Quality Gate & cara membacanya

| Bagian laporan | Yang diperiksa pembaca |
|---|---|
| Metadata (commit, tanggal, PHP, DB, status) | `Commit` sama dengan commit kode terakhir fase (`git log`); PHP 8.4.x; status `PASS 🟢`. |
| 1. Ringkasan test suite | Jumlah test tidak turun dibanding laporan fase sebelumnya tanpa penjelasan (test dihapus = red flag P12/X17). `0 failures, 0 errors`. |
| 2. Hasil perintah protokol P4 | Kesembilan langkah §2 ada, exit code 0. Durasi yang janggal (mis. suite penuh beberapa detik) adalah red flag. |
| 3. Status test blok Bukti | Setiap test yang dirujuk `Bukti:` muncul dengan status `PASS`. Test yang tidak ada di tabel berarti tidak pernah dijalankan. |
| 4. Ringkasan `arch:scan` | Angka per aturan ≤ baseline di `tests/Architecture/baselines/arch-scan.json`; angka yang turun harus diikuti penurunan baseline di PR yang sama. |
| 5. Verifikasi | Kosong sampai verifikator independen mengisinya (§5 di bawah). |

Laporan hanya membuktikan bahwa gate hijau pada commit itu, bukan bahwa fitur sesuai teks item. Kesesuaian dengan teks item diperiksa verifikator (C3).

---

## 5. Verifikasi Silang (Wajib untuk Status ✅)

- Ditulis oleh **verifikator independen**, yaitu sesi agent yang tidak ikut mengerjakan fase, atau pemilik. Pelaksana tidak menulis bagian ini dan tidak memberi status ✅ (X20).
- Memakai checklist **C1–C14 dari `docs/PROGRESS.md` §P7**, sama dengan `.github/pull_request_template.md`. Setiap butir ditandai lulus/gagal **beserta alasan dan buktinya** (command yang dijalankan, sabotase yang dilakukan, temuan).
- Wajib memuat: tanggal, commit yang diverifikasi, identitas verifikator, hasil C1–C14, minus yang ditambahkan verifikator, dan keputusan akhir (✅, atau 🔁 dengan alasan).
- Template kosong yang dibuat `gate:report` **bukan** verifikasi. Status ✅ di `PROGRESS.md` hanya sah bila bagian ini benar-benar terisi dan Register Minus fase tidak punya minus P0/P1 terbuka.
- Membuat ulang laporan dengan `gate:report` mempertahankan bagian Verifikasi yang sudah ada.

---

## 6. Proteksi branch (pengaturan pemilik)

Di GitHub, *Settings → Branches → Branch protection rules* untuk `master`:

- **Require status checks to pass before merging:** pilih ketiga job CI di atas, dengan nama **persis** seperti di run terakhir.
- **Require a pull request before merging**, dengan metode merge **merge commit** agar hash `commit:` di blok Bukti tetap ada di riwayat.
- **Require review from Code Owners** (`.github/CODEOWNERS`): GitHub tidak mengizinkan penulis PR menyetujui PR-nya sendiri. Bila semua commit agent di-push dengan akun pemilik, review code owner tidak bisa dipenuhi. Pilihannya: (a) agent memakai akun/bot terpisah sehingga pemilik bisa me-review, atau (b) pemilik memakai hak admin untuk merge setelah verifikasi silang tercatat di §5.
