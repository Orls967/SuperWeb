@extends('layouts.app')

@section('title', 'Konsol Verifikasi Qabdh & Dokumen Custody')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="text-xs text-gray-500 mb-1"><a href="{{ route('syariah.trade.index') }}" class="hover:underline">Trade Finance</a> / Custody</div>
            <h1 class="text-2xl font-bold text-gray-900">Konsol Verifikasi Qabdh & Dokumen Custody</h1>
            <p class="text-xs text-gray-500 mt-1">Pemenuhan rukun qabdh riil (larangan bai' qabla al-qabdh) melalui verifikasi digest BL, AWB, dan Resi Gudang</p>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Tipe Dokumen</th>
                        <th class="px-4 py-3">No. Dokumen</th>
                        <th class="px-4 py-3">Penerbit & Pengangkut</th>
                        <th class="px-4 py-3">Deskripsi Muatan (Cargo)</th>
                        <th class="px-4 py-3">Hash Digest (SHA-256)</th>
                        <th class="px-4 py-3">Status Qabdh</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($documents as $doc)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-semibold text-gray-900">{{ $doc->document_type }}</td>
                        <td class="px-4 py-3 font-mono text-gray-700">{{ $doc->document_number }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $doc->issuer_name }} / {{ $doc->carrier_name }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ Str::limit($doc->cargo_description, 40) }}</td>
                        <td class="px-4 py-3">
                            <span class="font-mono text-xs text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
                                {{ substr($doc->document_hash_sha256, 0, 12) }}...
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if($doc->verified_qabdh_at)
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                Verified ({{ $doc->verified_qabdh_at->format('d/m/Y') }})
                            </span>
                            @else
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
                                Pending Qabdh
                            </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-400">Belum ada dokumen custody terdaftar</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($documents->hasPages())
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $documents->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
