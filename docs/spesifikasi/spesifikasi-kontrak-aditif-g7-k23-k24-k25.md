# Spesifikasi Kontrak Aditif Lintas Modul G7-K23, G7-K24, G7-K25 — Fase 117

## 1. Kontrak Aditif G7-K23: `MedicalEmergencyResponseDispatcher`
- **Modul Pemilik:** `Healthcare` (atau `Shared` bila Healthcare belum vertical slice)
- **Namespace:** `Modules\Healthcare\Contracts\MedicalEmergencyResponseDispatcher`
- **Tujuan:** Mengirimkan sinyal panggilan darurat medis dan menyiagakan paramedis/ambulans di area venue saat terjadi insiden kerumunan atau kecelakaan pengunjung.
- **Metode:**
  ```php
  public function dispatchMedicalTeam(int $venueId, int $zoneId, string $triageLevel, string $reason): array;
  ```

## 2. Kontrak Aditif G7-K24: `VenueZoneSurveillanceFeedGateway`
- **Modul Pemilik:** `Security` (atau `Core`)
- **Namespace:** `Modules\Security\Contracts\VenueZoneSurveillanceFeedGateway`
- **Tujuan:** Mengambil telemetri sensor kepadatan LiDAR/CCTV optik zona secara berkala tanpa menyentuh rekaman video mentah (privasi terjaga).
- **Metode:**
  ```php
  public function getZoneTelemetrySnapshot(int $venueId, int $zoneId): array;
  ```

## 3. Kontrak Aditif G7-K25: `MemberPersonalizationProfileProvider`
- **Modul Pemilik:** `Crm` (atau `Core`)
- **Namespace:** `Modules\Crm\Contracts\MemberPersonalizationProfileProvider`
- **Tujuan:** Menyediakan profil preferensi minuman/makanan favorit tamu VIP dan riwayat belanja untuk mempersonalisasi promo on-site.
- **Metode:**
  ```php
  public function getMemberPreferences(int $userId): array;
  ```

