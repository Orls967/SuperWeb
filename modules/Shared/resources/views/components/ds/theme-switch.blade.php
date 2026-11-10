@props([
    'currentTheme' => 'light',
])

@php
$userTheme = auth()->check()
    ? \Modules\Core\Domain\Models\DsUserPref::getThemeForUser(auth()->id())
    : $currentTheme;
@endphp

<div
    x-data="{
        theme: '{{ $userTheme }}',
        setTheme(t) {
            this.theme = t;
            document.documentElement.setAttribute('data-theme', t);
            if (t === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            fetch('/design-system/preferences/theme', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
                },
                body: JSON.stringify({ theme: t })
            }).catch(() => {});
        }
    }"
    class="inline-flex rounded-lg p-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700"
    role="radiogroup"
    aria-label="Pilih Tema Tampilan"
>
    {{-- Terang (Light) --}}
    <button
        type="button"
        @click="setTheme('light')"
        :class="theme === 'light' ? 'bg-white dark:bg-slate-700 text-blue-600 shadow-sm font-semibold' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400'"
        class="flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
        role="radio"
        :aria-checked="theme === 'light'"
        aria-label="Mode Terang"
    >
        <span>☀️</span>
        <span class="hidden sm:inline">Terang</span>
    </button>

    {{-- Gelap (Dark) --}}
    <button
        type="button"
        @click="setTheme('dark')"
        :class="theme === 'dark' ? 'bg-white dark:bg-slate-700 text-blue-600 shadow-sm font-semibold' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400'"
        class="flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
        role="radio"
        :aria-checked="theme === 'dark'"
        aria-label="Mode Gelap"
    >
        <span>🌙</span>
        <span class="hidden sm:inline">Gelap</span>
    </button>

    {{-- Kontras Tinggi (HC) --}}
    <button
        type="button"
        @click="setTheme('hc')"
        :class="theme === 'hc' ? 'bg-white dark:bg-slate-700 text-blue-600 shadow-sm font-semibold' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400'"
        class="flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
        role="radio"
        :aria-checked="theme === 'hc'"
        aria-label="Mode Kontras Tinggi"
    >
        <span>⚡</span>
        <span class="hidden sm:inline">Kontras</span>
    </button>
</div>
