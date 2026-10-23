@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Research Grants & Scholarship Escrow</h2>
            <p class="mt-1 text-sm text-gray-400">Pencairan Dana Riset Milestone & Rekonsiliasi Dana Hibah Pendidikan</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Research Grants -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Dana Riset Aktif ({{ $grants->count() }})</h3>
                    <div class="space-y-3">
                        @forelse($grants as $grant)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="text-white font-medium">{{ $grant->title }}</div>
                                        <div class="text-xs text-gray-400">Kode: {{ $grant->grant_code }} • PI: {{ $grant->principal_investigator_name }}</div>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded bg-indigo-900 text-indigo-200">{{ $grant->status }}</span>
                                </div>
                                <div class="text-xs text-gray-300 mt-2">
                                    Total: Rp {{ number_format($grant->total_grant_minor, 0, ',', '.') }} | Dicairkan: Rp {{ number_format($grant->disbursed_grant_minor, 0, ',', '.') }}
                                </div>
                                <a href="{{ route('edu.grants.show', $grant->id) }}" class="inline-block mt-2 text-xs text-indigo-400 hover:underline">Kelola Milestone Riset &rarr;</a>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada dana riset.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Scholarship Escrow -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Rekonsiliasi Escrow Beasiswa ({{ $escrows->count() }})</h3>
                    <div class="space-y-3">
                        @forelse($escrows as $escrow)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="flex justify-between items-center">
                                    <span class="font-mono text-sm text-white">{{ $escrow->escrow_code }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded bg-emerald-900 text-emerald-200">{{ $escrow->status }}</span>
                                </div>
                                <div class="text-xs text-gray-400 mt-1">Sponsor: {{ $escrow->sponsor_name }}</div>
                                <div class="text-xs text-gray-300 mt-1">
                                    Total Escrow: Rp {{ number_format($escrow->total_escrow_minor, 0, ',', '.') }} | Tersalurkan: Rp {{ number_format($escrow->disbursed_minor, 0, ',', '.') }}
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada akun escrow beasiswa.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
