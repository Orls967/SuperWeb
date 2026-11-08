# Runbook Operasional: Alokasi Bridal Suite & Blok Kamar Keluarga Pengantin

## 1. Konteks Layanan
Runbook ini memandu Duty Manager, Executive Housekeeper, dan Wedding Specialist dalam memastikan standar layanan kelas utama bagi pasangan pengantin dan keluarga inti pada hari pernikahan:
- Service Terkait: `Modules\Hotel\Application\Services\HotelWeddingContractEscalationService`
- Action Terkait: `Modules\Hotel\Application\Actions\ProcessWeddingMilestonePaymentAction`

## 2. Kriteria Kelayakan Upgrade Bridal Suite
1. **Tier Kontrak:**
   - Kontrak Wedding bernilai $\ge \text{IDR } 250.000.000$ (Tier Platinum): Berhak atas 1 unit Presidential Suite / Penthouse Suite selama 2 malam berturut-turut.
   - Kontrak Wedding bernilai IDR $150.000.000$ s/d $249.999.999$ (Tier Gold): Berhak atas 1 unit Executive Suite selama 1 malam.
   - Kontrak di bawah IDR $150.000.000$: Tarif kamar suite diskon khusus keluarga 30% dari BAR.
2. **Kondisi Finansial:**
   - Fasilitas complimentary kamar suite hanya dapat dibuka kuncinya (*unlocked*) apabila Termin 1 (DP 20%) dan Termin 2 (30%) telah terbayar lunas.

## 3. Checklist Persiapan Kamar Pengantin (H-24 Jam Menuju Acara)
| No | Item Fasilitas | PIC Penanggung Jawab | Status Verifikasi |
|---|---|---|---|
| 1 | Pembersihan mendalam (*Deep Cleaning*) & sterilisasi aroma mawar aromaterapi | Floor Supervisor Housekeeping | [ ] |
| 2 | Penataan hiasan kelopak bunga mawar di ranjang (*honeymoon swan towel art*) | Senior Room Attendant | [ ] |
| 3 | Piring buah segar tropis (*fresh fruit platter*) & kue cokelat ucapan selamat | Pastry Chef | [ ] |
| 4 | 1 botol Champagne Moët & Chandon / sparkling grape juice dingin dalam ice bucket | In-Room Dining Captain | [ ] |
| 5 | Dua set gaun mandi kimono sutra (*monogrammed bathrobes*) berinisial nama pengantin | Laundry Manager | [ ] |
| 6 | Dua gantungan baju panjang khusus gaun pengantin (*wedding gown heavy-duty hangers*) | Concierge | [ ] |
| 7 | Cermin rias berdiri penuh (*full-length mirror*) dengan pencahayaan LED daylight | Engineering Lead | [ ] |
| 8 | Akses kunci cadangan (*VIP master key*) diserahkan kepada Wedding Organizer utama | Duty Manager | [ ] |

## 4. Penanganan Blok Kamar Keluarga Inti
1. Seluruh kamar keluarga inti (maksimal 10 kamar) ditempatkan pada lantai yang sama dengan Bridal Suite untuk kemudahan mobilisasi.
2. Jadwal sarapan pagi diantar langsung ke kamar (*in-room family breakfast*) jika diminta oleh keluarga pengantin tanpa biaya tambahan (*complimentary tray charge*).

