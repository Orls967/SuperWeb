# ADR-0033: Pembangkit Bilangan Acak Deterministik MINSTD Park-Miller dengan Pemisahan Aliran (Algoritma A3)

- **Status:** DITERIMA
- **Tanggal:** 2026-11-10
- **Pengambil Keputusan:** Tim Arsitektur Core Platform & Pelaksana W8-P3
- **Konteks:** Menghasilkan data sintetis multi-domain tanpa pergeseran deret acak silang.

---

## 1. Konteks & Masalah

Penggunaan generator bawaan sistem operasi (`rand()`, `mt_rand()`) bersifat non-deterministik dan berbeda hasilnya antar arsitektur hardware (x86_64 vs ARM64) atau versi PHP. Selain itu, jika modul Hospital meminta 5 angka acak, modul Hotel akan menerima angka acak ke-6, sehingga perubahan kecil pada satu modul merusak seluruh riwayat acak modul lain (*cross-stream pollution*).

## 2. Pilihan yang Dipertimbangkan

1. **Opsi A:** Menggunakan fungsi `mt_rand()` dengan re-seeding `mt_srand()`. (DITOLAK: State global PHP tidak terisolasi dan non-deterministik antar versi runtime).
2. **Opsi B:** Memakai Lehmer LCG MINSTD Park-Miller ($a=48271, m=2^{31}-1$) murni dengan fungsi derivasi sub-seed independen `split(seed, stream_name)`. (DITERIMA).

## 3. Keputusan

Kami memutuskan untuk mengimplementasikan Algoritma A3 `StreamSeedAndFingerprint` menggunakan rumus MINSTD baku:
$$X_{n+1} = (48271 \times X_n) \pmod{2147483647}$$
Sub-stream dihasilkan melalui hashing kanonikal nama stream:
$$\text{sub\_seed} = (\text{hexdec}(\text{substr}(\text{SHA256}(\text{"stream:"} \cdot \text{seed} \cdot \text{":"} \cdot \text{name}), 0, 8)) \pmod{2147483646}) + 1$$

## 4. Konsekuensi

- **Positif:** Deret acak semu terdistribusi seragam dengan periode $2^{31}-2$ bilangan unik. Setiap modul bisnis memiliki alur bilangan acak yang terisolasi sempurna.
- **Negatif:** Seluruh modul domain wajib meminta generator acak melalui antarmuka resmi kernel simulasi dan dilarang menggunakan fungsi acak PHP global.

