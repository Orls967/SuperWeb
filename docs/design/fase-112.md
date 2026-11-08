# Dokumen Desain Sistem — Fase 112: Hospitality & Entertainment: Global Loyalty & Travel Pass

## 1. Arsitektur Domain & Struktur Modul
Modul Hotel (`Modules\Hotel`) memperluas kapabilitas loyalitas global melalui arsitektur decoupled yang bersih dan terisolasi:

```
Modules/Hotel/
├── Application/
│   ├── Actions/
│   │   ├── IssueGlobalTravelPassAction.php
│   │   ├── AccrueCrossEcosystemPointsAction.php
│   │   ├── RedeemAwardNightWithDynamicPricingAction.php
│   │   ├── TransferPointsToAlliancePartnerAction.php
│   │   ├── RecognizeLoyaltyBreakageRevenueAction.php
│   │   ├── ReconcileMultiLineLoyaltyLiabilityAction.php
│   │   └── GeneratePersonalizedTravelRecommendationsAction.php
│   └── DTOs/
│       ├── TravelPassRegistrationDTO.php
│       ├── CrossEcosystemPointsAccrualDTO.php
│       ├── AwardNightBookingDTO.php
│       ├── AlliancePartnerTransferDTO.php
│       └── PersonalizedRecommendationDTO.php
├── Domain/
│   ├── Algorithms/
│   │   ├── PointsLiabilityFifo.php
│   │   ├── PointsLiabilityFifoCertificate.php
│   │   ├── PointsLiabilityFifoCertificateVerifier.php
│   │   ├── AwardNightPricer.php
│   │   ├── AwardNightPriceCertificate.php
│   │   ├── AwardNightPriceCertificateVerifier.php
│   │   ├── PartnerPointConverter.php
│   │   ├── PartnerPointConversionCertificate.php
│   │   ├── PartnerPointConversionCertificateVerifier.php
│   │   ├── GlobalTierQualifier.php
│   │   ├── GlobalTierQualificationCertificate.php
│   │   └── GlobalTierQualificationCertificateVerifier.php
│   ├── Events/
│   │   ├── TravelPassIssuedEvent.php
│   │   ├── PointsAccruedFromEcosystemEvent.php
│   │   ├── AwardNightRedeemedEvent.php
│   │   ├── AlliancePointsTransferredEvent.php
│   │   ├── LoyaltyTierUpgradedEvent.php
│   │   └── PointsBreakageRecognizedEvent.php
│   ├── Models/
│   │   ├── HtlTravelPass.php
│   │   ├── HtlTravelPassPointBatch.php
│   │   ├── HtlAlliancePartner.php
│   │   ├── HtlAllianceTransferLog.php
│   │   ├── HtlAwardNightPricingRule.php
│   │   └── HtlPersonalizedTravelCampaign.php
│   ├── Services/
│   │   ├── HotelPartnerPointTransferSaga.php
│   │   ├── HotelLoyaltyBreakageAuditor.php
│   │   ├── HotelAllianceReconciliationService.php
│   │   ├── HotelPersonalizationRecommendationEngine.php
│   │   └── HotelLoyaltyDynamicAwardPricingService.php
│   └── StateMachines/
│       ├── HotelTravelPassTierStateMachine.php
│       └── HotelPointBatchStateMachine.php
├── Http/
│   └── Controllers/
│       ├── HotelTravelPassDashboardController.php
│       ├── HotelAwardNightBookingController.php
│       ├── HotelAllianceExchangeController.php
│       ├── HotelLoyaltyBreakageAuditController.php
│       └── HotelTravelPersonalizationController.php
└── resources/
    └── views/
        ├── travel_pass_global_dashboard.blade.php
        ├── award_night_dynamic_booking_portal.blade.php
        ├── partner_point_exchange_hub.blade.php
        ├── loyalty_liability_breakage_control_center.blade.php
        ├── travel_personalization_recommendation_board.blade.php
        └── components/
            ├── tier_progress_radar_gauge.blade.php
            ├── award_calendar_heatmap.blade.php
            ├── points_expiry_fifo_timeline.blade.php
            ├── partner_conversion_rate_card.blade.php
            ├── breakage_waterfall_chart.blade.php
            └── personalization_offer_card.blade.php
```

## 2. Diagram State Machine & Alur Transaksi

### 2.1 State Machine Batch Poin (`HotelPointBatchStateMachine`)
1. **ACTIVE:** Poin baru diterbitkan dari salah satu lini, siap digunakan untuk penukaran.
2. **PARTIALLY_REDEEMED:** Sebagian poin telah dipotong melalui FIFO, sisa poin masih aktif.
3. **FULLY_REDEEMED:** Seluruh poin dalam batch telah digunakan (saldo = 0).
4. **EXPIRED:** Masa berlaku telah terlampaui saat audit berkala dijalankan.
5. **BREAKAGE_WRITTEN_OFF:** Poin kedaluwarsa telah dibukukan menjadi pendapatan breakage resmi di buku besar.

### 2.2 Saga Berkompensasi: Transfer Poin Aliansi (`HotelPartnerPointTransferSaga`)
1. **Step 1 - Valuasi & Verifikasi:** Panggil `PartnerPointConverter` (H12), verifikasi kecukupan saldo pass.
2. **Step 2 - Hold Saldo & Pemotongan Liabilitas:** Kurangi saldo poin tamu, debit `htl:loyalty_points_liability:IDR`, kredit akun escrow sementara `clearing:external:IDR`.
3. **Step 3 - Transmisi API Mitra:** Kirim instruksi kredit reward ke API maskapai mitra.
4. **Step 4A - Sukses:** Pindahkan kredit dari escrow sementara ke `htl:partner_points_payable:IDR` dan catat fee ke `htl:transfer_fee_revenue:IDR`.
5. **Step 4B - Gagal / Timeout (Kompensasi):** Kembalikan saldo poin ke pass tamu, batalkan jurnal debit liabilitas (reverse posting), dan catat audit kegagalan.

## 3. Matriks Hak Akses & Autentikasi Rute (RBAC)
- `/hotel/travel-pass/dashboard`: `[admin, hotel_admin, front_desk, guest]`
- `/hotel/travel-pass/award-booking`: `[admin, hotel_admin, front_desk, guest]`
- `/hotel/travel-pass/alliance-exchange`: `[admin, hotel_admin, front_desk, guest]`
- `/hotel/travel-pass/breakage-audit`: `[admin, hotel_admin, finance]`
- `/hotel/travel-pass/personalization`: `[admin, hotel_admin, sales_mgr]`

