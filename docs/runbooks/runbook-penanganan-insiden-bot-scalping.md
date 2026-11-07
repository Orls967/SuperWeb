# Runbook Tanggap Darurat Insiden Bot Scalping

## 1. Definisi Insiden & Indikator Pemicu
Insiden bot scalping didefinisikan sebagai aktivitas anomali otomatisasi terdistribusi yang bertujuan untuk memborong tiket, memanipulasi kuota bursa sekunder, atau mengeksploitasi antrean daftar tunggu.

### Indikator Pemicu (Alert Triggers)
1. Tingkat permintaan checkout melebihi 200 transaksi per detik pada satu acara tertentu.
2. Tingkat penolakan skor risiko velocity Core (G7-K22) melonjak di atas $15\%$ dari total lalu lintas.
3. Lonjakan pembuatan akun baru dari rentang alamat IP subnet yang sama dalam kurun waktu 10 menit.
4. Alarm deteksi *headless crawler* pada tabel `ven_scalper_blacklist_logs` mencatat lebih dari 50 peristiwa per jam.

---

## 2. Tingkat Keparahan Insiden (Severity Matrix)

| Tingkat | Kriteria Dampak | Waktu Respons | Tim Penanggung Jawab |
| :---: | :--- | :---: | :--- |
| **SEV-1** | Kegagalan sistem transaksi, latensi checkout $> 5$ detik, kebocoran kuota ke bot | $< 15$ menit | SRE Lead, Security Officer, Venue Tech Lead |
| **SEV-2** | Lonjakan lalu lintas bot tinggi, beberapa pengguna sah mengalami penundaan | $< 30$ menit | SRE Engineer, Venue Backend Dev |
| **SEV-3** | Upaya scraping pasif dari IP pusat data tanpa dampak signifikan pada pengguna | $< 2$ jam | On-call Security Analyst |

---

## 3. Langkah-Langkah Penanganan SEV-1
### Langkah 1: Isolasi & Pemblokiran Jaringan Cepat
Jalankan mitigasi perimeter pada lapisan load balancer / web application firewall (WAF):
1. Blokir akses dari daftar subnet proxy pusat data yang teridentifikasi:
   ```bash
   php artisan venue:block-subnet --cidr="103.145.22.0/24" --reason="SEV-1 Scalper Bot Flood"
   ```
2. Aktifkan mode verifikasi tantangan ketat (*strict proof-of-work gate*) pada titik akhir checkout sekunder.

### Langkah 2: Audit Listing Sekunder Aktif
Periksa apakah terdapat tiket yang berhasil didaftarkan oleh akun bot yang mencurigakan:
1. Jalankan perintah audit darurat:
   ```bash
   php artisan venue:audit-secondary --strict
   ```
2. Bila ditemukan listing yang dibuat oleh akun berbahaya, bekukan listing seketika:
   ```bash
   php artisan venue:freeze-suspicious-listings --threshold-rpm=60
   ```

### Langkah 3: Evaluasi & Pembersihan Antrean Waitlist
1. Jalankan rekalkulasi prioritas antrean daftar tunggu untuk membersihkan akun bot:
   ```bash
   php artisan venue:purge-bot-waitlist --event-id=<EVENT_ID>
   ```
2. Pulihkan prioritas pengguna komunitas asli berdasarkan poin loyalitas historis terverifikasi.

---

## 4. Analisis Pasca Insiden (Post-Mortem Runbook)
1. Ekspor seluruh log dari tabel `ven_scalper_blacklist_logs` untuk dianalisis oleh tim data science.
2. Perbarui heuristik sidik jari perangkat pada `AntiScalpingFingerprintRegistry`.
3. Laporkan temuan ke promotor acara dan pemangku kepentingan dalam kurun waktu 24 jam setelah insiden terkendali.

