@extends('layouts.app')

@section('title', 'Member Passport Portal — Global Travel Pass')

@section('content')
<div class="min-h-screen bg-slate-50 dark:bg-slate-950 py-8 px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="max-w-5xl mx-auto mb-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-200 dark:border-slate-800">
            <div>
                <div class="flex items-center space-x-3 mb-1">
                    <span class="px-3 py-1 bg-indigo-100 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300 font-extrabold text-xs rounded-full uppercase tracking-wider border border-indigo-200 dark:border-indigo-800">
                        Portal Tamu Mandiri
                    </span>
                    <span class="text-xs text-slate-500">Paspor Digital NFC & QR Penebusan</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                    Paspor Keanggotaan Global Travel Pass
                </h1>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                    Kelola saldo poin, nikmati hak istimewa tier, dan tukarkan voucher di 17 lini ekosistem.
                </p>
            </div>
            <a href="{{ route('hotel.travel_pass.dashboard') }}"
               class="px-4 py-2 bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl hover:bg-slate-300 dark:hover:bg-slate-700 transition text-xs flex items-center space-x-1.5 self-start">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Dasbor Eksekutif</span>
            </a>
        </div>
    </div>

    <div class="max-w-5xl mx-auto space-y-8">
        <!-- Digital Card & QR Scan Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
            <!-- Digital Card Component -->
            <div class="flex justify-center">
                <x-hotel::travel_pass_nfc_card_preview
                    passNumber="TP-DEMO-0001"
                    memberName="Sutan Iskandar Syah"
                    tier="BLACK"
                    :ptsBalance="85000"
                    enrolledDate="10/24" />
            </div>

            <!-- Dynamic QR Code Card -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col items-center text-center space-y-3">
                <span class="px-3 py-1 bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 font-bold text-xs rounded-full border border-amber-200 dark:border-amber-800">
                    Kode QR Dinamis Satu Kali Pakai (TOTP 60 Detik)
                </span>

                <!-- QR Simulation Box -->
                <div class="w-40 h-40 bg-slate-950 p-3 rounded-2xl border-2 border-dashed border-indigo-400 flex items-center justify-center shadow-inner">
                    <div class="w-32 h-32 bg-white rounded-xl flex items-center justify-center font-mono font-black text-xs text-slate-900">
                        [QR-PASS-CODE]
                    </div>
                </div>

                <div class="text-xs text-slate-500 max-w-xs">
                    Tunjukkan kode QR ini kepada kasir restoran hotel, pintu masuk festival venue, atau konter front desk untuk identifikasi instan.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

