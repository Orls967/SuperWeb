@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Validasi Integritas Kredensial Hash</h2>
            <p class="mt-1 text-sm text-gray-400">Verifikasi Kriptografis Sertifikat Kompetensi Digital</p>

            <div class="mt-6 p-4 bg-gray-900 rounded border border-gray-700">
                <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Target Hash</div>
                <div class="text-sm font-mono text-indigo-400 break-all">{{ $hash }}</div>
            </div>

            @if($certificate)
                <div class="mt-6 p-6 bg-emerald-950/40 border border-emerald-500 rounded-lg">
                    <div class="flex items-center gap-2 text-emerald-400 font-semibold mb-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        SERTIFIKAT RESMI VALID & TERDAFTAR
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-xs text-gray-300 mt-4">
                        <div><span class="text-gray-400">Nomor Sertifikat:</span> {{ $certificate->certificate_number }}</div>
                        <div><span class="text-gray-400">Pemilik (Student):</span> {{ $certificate->student_party_id }}</div>
                        <div><span class="text-gray-400">Kompetensi:</span> {{ $certificate->skill_competency_code }}</div>
                        <div><span class="text-gray-400">Masa Berlaku:</span> {{ $certificate->issued_at }} s/d {{ $certificate->expires_at }}</div>
                    </div>
                </div>
            @else
                <div class="mt-6 p-6 bg-rose-950/40 border border-rose-500 rounded-lg text-rose-300 text-sm">
                    Hash ini tidak ditemukan dalam rantai audit kredensial resmi.
                </div>
            @endif

            <div class="mt-6">
                <a href="{{ route('edu.certification.index') }}" class="text-xs text-indigo-400 hover:underline">&larr; Kembali ke Daftar Sertifikat</a>
            </div>
        </div>
    </div>
</div>
@endsection
