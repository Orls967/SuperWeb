@extends('layouts.app')

@section('title', 'Ajukan Usulan Komponen Baru — Design System')

@section('content')
    <div class="max-w-2xl mx-auto space-y-6">
        <x-ds.breadcrumb :items="[
            ['label' => 'Beranda', 'url' => route('dashboard')],
            ['label' => 'Design System', 'url' => route('design-system.index')],
            ['label' => 'Tata Kelola Rilis', 'url' => route('admin.design-system.releases.index')],
            ['label' => 'Proposal Baru', 'url' => '#', 'active' => true],
        ]" />

        <div class="space-y-1">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Ajukan Proposal Komponen Baru</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">Setiap komponen baru dimulai dari status Draft dan wajib melewati proses audit aksesibilitas sebelum disetujui.</p>
        </div>

        <x-ds.card title="Formulir Usulan Komponen">
            <form method="POST" action="{{ route('admin.design-system.releases.store') }}" class="space-y-4">
                @csrf

                <x-ds.input
                    name="name"
                    label="Nama Komponen (Slug Kanonikal)"
                    placeholder="misal: timeline, stepper, rating-star"
                    :value="old('name')"
                    hint="Hanya huruf kecil, angka, dan tanda hubung (-). Komponen akan dapat dipanggil via &lt;x-ds.[nama]&gt;."
                    required
                />

                <x-ds.input
                    name="display_name"
                    label="Nama Tampilan (Display Name)"
                    placeholder="misal: Timeline Navigasi & Status"
                    :value="old('display_name')"
                    required
                />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ds.select
                        name="category"
                        label="Kategori Komponen"
                        :value="old('category', 'action')"
                        :options="[
                            'action' => 'Action (Tombol, Tautan)',
                            'form' => 'Form (Input, Select, Picker)',
                            'navigation' => 'Navigation (Breadcrumb, Tabs, Menu)',
                            'feedback' => 'Feedback (Toast, Alert, Modal)',
                            'data-display' => 'Data Display (Table, Card, Stat, Chart)',
                            'layout' => 'Layout (Shell, Sidebar, Topbar)',
                            'foundation' => 'Foundation (Token, Typography)',
                        ]"
                        required
                    />

                    <x-ds.select
                        name="wcag_level"
                        label="Target Tingkat WCAG 2.1"
                        :value="old('wcag_level', 'AA')"
                        :options="[
                            'AA' => 'WCAG 2.1 Level AA (Standar SuperWeb)',
                            'AAA' => 'WCAG 2.1 Level AAA (Tertinggi)',
                            'A' => 'WCAG 2.1 Level A (Dasar)',
                        ]"
                        required
                    />
                </div>

                <x-ds.input
                    name="spec_doc_url"
                    label="Tautan Spesifikasi Desain (Markdown)"
                    placeholder="docs/design/components/nama-komponen.md"
                    :value="old('spec_doc_url')"
                    hint="Lokasi dokumen DoR dan spesifikasi arsitektur di repositori."
                />

                <x-ds.input
                    name="notes"
                    label="Catatan / Rasional Usulan"
                    placeholder="Jelaskan kebutuhan modul dan kasus penggunaan antarmuka..."
                    :value="old('notes')"
                />

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <a href="{{ route('admin.design-system.releases.index') }}" class="px-4 py-2 text-xs font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 transition">Batal</a>
                    <x-ds.button type="submit" variant="primary">Kirim Proposal</x-ds.button>
                </div>
            </form>
        </x-ds.card>
    </div>
@endsection
