{{-- Month switcher. $month (Carbon), keeps other query params. --}}
@php
    $prev = $month->copy()->subMonth()->format('Y-m');
    $next = $month->copy()->addMonth()->format('Y-m');
@endphp
<div class="no-print flex items-center gap-2">
    <a href="{{ request()->fullUrlWithQuery(['month' => $prev]) }}" class="btn-quiet px-3 py-1.5" aria-label="আগের মাস">‹</a>
    <span class="min-w-36 text-center text-[17px] font-semibold">{{ \App\Support\BanglaFormatter::MONTHS[$month->month] }} {{ bn_digits($month->year) }}</span>
    <a href="{{ request()->fullUrlWithQuery(['month' => $next]) }}" class="btn-quiet px-3 py-1.5" aria-label="পরের মাস">›</a>
</div>
