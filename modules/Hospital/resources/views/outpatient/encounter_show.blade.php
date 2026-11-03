@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-white">Detail Kunjungan Rawat Jalan</h2>
                    <p class="mt-1 text-sm text-gray-400">Kode Kunjungan: <span class="font-mono text-white font-bold">{{ $encounter->encounter_code }}</span></p>
                </div>
                <a href="{{ url()->previous() }}" class="text-gray-400 hover:text-white text-sm">← Kembali</a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                <div class="p-4 bg-gray-900/60 rounded border border-gray-700 space-y-2">
                    <div class="text-gray-400 font-semibold mb-2">Informasi Pasien & Layanan</div>
                    <div><span class="text-gray-400">Pasien:</span> <span class="text-white font-medium">{{ $encounter->patient?->name }}</span></div>
                    <div><span class="text-gray-400">Poliklinik:</span> <span class="text-white">{{ $encounter->polyclinic?->name }}</span></div>
                    <div><span class="text-gray-400">Dokter:</span> <span class="text-white">{{ $encounter->doctor?->user?->name }}</span></div>
                    <div><span class="text-gray-400">Metode Bayar:</span> <span class="text-white">{{ $encounter->payment_method }}</span></div>
                    <div><span class="text-gray-400">Status:</span> <span class="text-indigo-400 font-bold">{{ $encounter->status }}</span></div>
                </div>

                <div class="p-4 bg-gray-900/60 rounded border border-gray-700 space-y-2">
                    <div class="text-gray-400 font-semibold mb-2">Rekam Medis (Hanya Berhak)</div>
                    @if(auth()->user()->role === 'doctor' || auth()->user()->role === 'nurse' || auth()->user()->role === 'admin' || (auth()->user()->role === 'patient' && $encounter->patient->user_id === auth()->id()))
                        <div><span class="text-gray-400">Diagnosis (ICD-10):</span> <span class="text-white font-mono font-bold">{{ $encounter->encrypted_icd10_code ?? '-' }}</span></div>
                        <div><span class="text-gray-400">Catatan Dokter:</span> <span class="text-white">{{ $encounter->encrypted_doctor_notes ?? '-' }}</span></div>
                    @else
                        <div class="text-xs text-rose-400 italic">Informasi medis dilindungi enkripsi dan hak akses terbatas.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
