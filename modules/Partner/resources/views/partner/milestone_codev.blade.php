@extends('layouts.app')

@section('title', 'Partner Co-Development Milestone Settlement')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Co-Development Milestones</h1>
            <p class="text-sm text-gray-500">Pencairan komitmen anggaran bersama berdasarkan verifikasi deliverables milestone proyek.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
            Joint Co-Dev
        </span>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Milestone Progress & Budget Release</h2>
        <div class="space-y-4">
            <div class="border rounded p-4 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900">Milestone 1: Proof of Concept & Lab Stability</h3>
                    <p class="text-sm text-gray-500">Status: Disetujui Komite R&D Bersama</p>
                </div>
                <div class="text-right">
                    <span class="text-xs text-gray-400 uppercase">Payout</span>
                    <p class="text-lg font-bold text-emerald-600">Rp 250.000.000</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
