@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
    <div class="mb-4">
        <a href="{{ route('syariah.waqf.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Kembali ke Wakaf</a>
    </div>

    <div class="bg-white border-2 border-teal-600 rounded-xl p-8 shadow-md relative overflow-hidden">
        <div class="absolute -right-12 -top-12 w-40 h-40 bg-teal-50 rounded-full pointer-events-none opacity-50"></div>
        <div class="text-center pb-6 border-b border-slate-200">
            <span class="text-xs font-bold uppercase tracking-widest text-teal-700">Akta Ikrar Wakaf Uang Digital</span>
            <h1 class="text-2xl font-bold text-slate-900 mt-1">Sertifikat Wakaf Uang Abadi</h1>
            <p class="font-mono text-xs text-slate-500 mt-1">Nomor AIW Gapless: {{ $certificate->certificate_number }}</p>
        </div>

        <div class="py-6 space-y-4 text-sm">
            <div class="flex justify-between items-center py-2 border-b border-slate-100">
                <span class="text-slate-600">Nama Wakif:</span>
                <span class="font-semibold text-slate-900">{{ $certificate->recipient?->name ?? "User #{$certificate->recipient_user_id}" }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-100">
                <span class="text-slate-600">Tipe Filantropi:</span>
                <span class="font-medium text-slate-800">{{ $certificate->certificate_type }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-100">
                <span class="text-slate-600">Nominal Pokok Wakaf Abadi:</span>
                <span class="text-xl font-bold text-teal-800 font-mono">Rp {{ number_format($certificate->amount_idr) }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-100">
                <span class="text-slate-600">Waktu Ikrar:</span>
                <span class="text-slate-800">{{ $certificate->issued_at?->format('d F Y, H:i:s') }} WITA</span>
            </div>
            <div class="pt-2">
                <span class="text-xs text-slate-500">Hash Integritas Kriptografis (SHA-256):</span>
                <p class="font-mono text-xs break-all text-slate-600 bg-slate-50 p-2 rounded mt-1 border border-slate-200">{{ $certificate->integrity_hash }}</p>
            </div>
        </div>

        <div class="text-center pt-6 border-t border-slate-200 text-xs text-slate-500">
            Diterbitkan oleh Nazhir Wakaf Uang Superweb terdaftar Badan Wakaf Indonesia (BWI). Pokok wakaf dijaga keabadiannya secara hukum dan syariat.
        </div>
    </div>
</div>
@endsection
