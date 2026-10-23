@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Verifikasi Sertifikasi & Bukti Kredensial</h2>
            <p class="mt-1 text-sm text-gray-400">Penerbitan Sertifikat Kompetensi Terverifikasi Hash SHA-256</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 space-y-3">
                @forelse($certificates as $cert)
                    <div class="p-4 bg-gray-700/50 rounded-lg border border-gray-600 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-medium text-white">{{ $cert->certificate_number }}</span>
                                <span class="text-xs px-2 py-0.5 rounded bg-emerald-900 text-emerald-200">{{ $cert->status }}</span>
                            </div>
                            <div class="text-xs text-gray-400 mt-1">Siswa: {{ $cert->student_party_id }} • Kompetensi: {{ $cert->skill_competency_code }} • Berlaku s/d: {{ $cert->expires_at }}</div>
                            <div class="text-xs font-mono text-indigo-400 break-all mt-1">SHA-256: {{ $cert->certificate_hash }}</div>
                        </div>
                        <a href="{{ route('edu.certification.verify', $cert->certificate_hash) }}" class="py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded transition">
                            Verifikasi Hash
                        </a>
                    </div>
                @empty
                    <div class="text-gray-400 text-sm">Belum ada sertifikat diterbitkan.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
