@props([
    'title' => 'Dashboard',
    'subtitle' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ auth()->check() ? \Modules\Core\Domain\Models\DsUserPref::getThemeForUser(auth()->id()) : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="SuperWeb - Experience Design System & Enterprise Navigation">

    <title>{{ config('app.name', 'SuperWeb') }} - {{ $title }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="antialiased bg-slate-900 text-slate-100 min-h-screen">
    <div class="min-h-screen flex" x-data="{
        sidebarOpen: true,
        searchOpen: false,
        searchQuery: '',
        searchResults: [],
        searchLoading: false,
        executeSearch() {
            let url = '{{ route('api.global-search') }}';
            if (this.searchQuery && this.searchQuery.trim().length >= 2) {
                url += '?q=' + encodeURIComponent(this.searchQuery.trim());
            }
            this.searchLoading = true;
            fetch(url)
                .then(res => res.json())
                .then(data => {
                    this.searchResults = data.results || [];
                    this.searchLoading = false;
                })
                .catch(() => { this.searchLoading = false; });
        }
    }"
    @keydown.window.prevent.cmd.k="searchOpen = true; $nextTick(() => { if($refs.globalSearchInput) { $refs.globalSearchInput.focus(); executeSearch(); } })"
    @keydown.window.prevent.ctrl.k="searchOpen = true; $nextTick(() => { if($refs.globalSearchInput) { $refs.globalSearchInput.focus(); executeSearch(); } })"
    @keydown.window.escape="searchOpen = false"
    >
        {{-- Modular Sidebar --}}
        <x-ds.sidebar />

        {{-- Main Container --}}
        <div class="flex-1 transition-all duration-300" :class="sidebarOpen ? 'ml-64' : 'ml-20'">
            {{-- Modular Topbar --}}
            <x-ds.topbar />

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="mx-6 mt-4">
                    <x-ds.toast type="success" title="Sukses" :message="session('success')" />
                </div>
            @endif

            @if(session('error'))
                <div class="mx-6 mt-4">
                    <x-ds.toast type="error" title="Galat" :message="session('error')" />
                </div>
            @endif

            @if($errors->any())
                <div class="mx-6 mt-4">
                    <x-ds.error title="Validasi Gagal" :message="$errors->first()" />
                </div>
            @endif

            {{-- Main Content Slot --}}
            <main class="p-6">
                <x-enterprise.role-matrix-banner />
                {{ $slot }}
            </main>
        </div>

        {{-- Modular Command Palette --}}
        <x-ds.command-palette />
    </div>
</body>
</html>

