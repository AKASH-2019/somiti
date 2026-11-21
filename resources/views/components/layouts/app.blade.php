@props(['title' => 'ড্যাশবোর্ড'])
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | {{ setting('org_name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <meta name="theme-color" content="#0E3B2B">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper font-sans text-ink antialiased" x-data="{ nav: false }" @keydown.escape.window="nav = false">
<div class="min-h-screen lg:flex">
    <x-sidebar />

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="no-print sticky top-0 z-20 flex items-center gap-3 border-b border-rule bg-white/95 px-4 py-3 backdrop-blur sm:px-6">
            <button type="button" class="btn-quiet px-2.5 py-1.5 lg:hidden" @click="nav = true" aria-label="মেনু খুলুন">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 5h14a1 1 0 010 2H3a1 1 0 110-2zm0 4h14a1 1 0 010 2H3a1 1 0 110-2zm0 4h14a1 1 0 010 2H3a1 1 0 010-2z" clip-rule="evenodd"/></svg>
            </button>
            <p class="hidden text-sm text-ink-soft sm:block">{{ bn_date(today(), true) }}</p>

            <div class="ml-auto flex items-center gap-3">
                <div class="text-right leading-tight">
                    <p class="text-[15px] font-semibold">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-ink-soft">{{ auth()->user()->role->label() }}</p>
                </div>
                @php($unread = auth()->user()->unreadNotifications()->count())
                <a href="{{ route('notifications.index') }}" class="relative rounded-md p-2 text-ink-soft hover:bg-ledger-50 hover:text-ink"
                   aria-label="বিজ্ঞপ্তি{{ $unread ? ' — ' . bn_digits($unread) . 'টি নতুন' : '' }}">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a6 6 0 00-6 6v2.586l-.707.707A1 1 0 004 13h12a1 1 0 00.707-1.707L16 10.586V8a6 6 0 00-6-6zm0 16a2.5 2.5 0 01-2.45-2h4.9A2.5 2.5 0 0110 18z"/></svg>
                    @if ($unread)
                        <span class="absolute -top-0.5 -right-0.5 min-w-5 rounded-full bg-pending px-1 text-center text-xs font-bold text-white">{{ bn_digits($unread) }}</span>
                    @endif
                </a>
                <a href="{{ route('password.edit') }}" class="hidden text-sm font-semibold text-ledger-700 hover:underline sm:inline">পাসওয়ার্ড পরিবর্তন</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-quiet px-3 py-1.5 text-sm">লগআউট</button>
                </form>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
