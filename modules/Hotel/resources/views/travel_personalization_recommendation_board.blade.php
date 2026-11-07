@extends('layouts.app')

@section('title', 'Travel Personalization & Recommendation Board — Travel Pass')

@section('content')
<div class="min-h-screen bg-slate-50 dark:bg-slate-950 py-8 px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="max-w-7xl mx-auto mb-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-200 dark:border-slate-800">
            <div>
                <div class="flex items-center space-x-3 mb-1">
                    <span class="px-3 py-1 bg-purple-100 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 font-extrabold text-xs rounded-full uppercase tracking-wider border border-purple-200 dark:border-purple-800">
                        Profil Preferensi 360° & Kepatuhan PDP/GDPR
                    </span>
                    <span class="text-xs text-slate-500">Deterministik Rule-Based Engine</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                    Papan Rekomendasi Perjalanan Personalisasi
                </h1>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                    Kurasi paket destinasi, festival musik, dan santapan kuliner berdasarkan riwayat pembelanjaan tamu dengan kepatuhan opt-out penuh.
                </p>
            </div>
            <a href="{{ route('hotel.travel_pass.dashboard') }}"
               class="px-4 py-2 bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl hover:bg-slate-300 dark:hover:bg-slate-700 transition text-xs flex items-center space-x-1.5 self-start">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Kembali ke Dasbor</span>
            </a>
        </div>
    </div>

    <div class="max-w-7xl mx-auto space-y-8">
        <!-- 3 Offer Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-hotel::personalization_offer_card
                campaignCode="CAMP-BALI-VIP-01"
                destinationTitle="Grand Nusantara Resort Nusa Dua"
                packageTitle="Bali Sunset Festival & Oceanfront Suite Package"
                :pointsDiscounted="25000"
                :affinityScore="0.95"
                :validUntil="now()->addDays(30)" />

            <x-hotel::personalization_offer_card
                campaignCode="CAMP-KOMODO-02"
                destinationTitle="Labuan Bajo Catamaran & Island Resort"
                packageTitle="Komodo Snorkeling Cruise & Private Villa Weekend"
                :pointsDiscounted="38000"
                :affinityScore="0.89"
                :validUntil="now()->addDays(20)" />

            <x-hotel::personalization_offer_card
                campaignCode="CAMP-JOGJA-03"
                destinationTitle="Heritage Royal Palace Yogyakarta"
                packageTitle="Javanese Aromatherapy Spa & Cultural Tour Retreat"
                :pointsDiscounted="18000"
                :affinityScore="0.82"
                :validUntil="now()->addDays(14)" />
        </div>

        <!-- Privacy & PDP Consent Notice -->
        <div class="bg-slate-50 dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 flex items-start space-x-4">
            <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-600 flex items-center justify-center font-bold text-lg flex-shrink-0">
                🛡️
            </div>
            <div class="space-y-1 text-xs text-slate-600 dark:text-slate-400">
                <h4 class="font-bold text-slate-900 dark:text-white text-sm">Kebijakan Privasi & Perlindungan Data Tamu</h4>
                <p>
                    Seluruh preferensi kamar, histori transaksi, dan rekomendasi paket dienkripsi menggunakan standar perbankan AES-256. Tamu dapat memilih untuk menonaktifkan kampanye personalisasi (opt-out) kapan saja melalui menu Pengaturan Profil Akun tanpa mengurangi akumulasi perolehan poin reguler.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

