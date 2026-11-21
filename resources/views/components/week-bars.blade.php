{{-- Simple 7-day bar chart, no library. $days: list of ['date' => Carbon, 'total' => '123.00'] --}}
@props(['days', 'title' => 'গত ৭ দিনের সংগ্রহ'])
@php($max = max(1, ...array_map(fn ($d) => (float) $d['total'], $days)))
<section {{ $attributes->merge(['class' => 'panel p-4']) }}>
    <h2 class="mb-4 text-lg font-bold text-ledger-900">{{ $title }}</h2>
    <div class="flex h-40 items-end gap-2" role="img" aria-label="{{ $title }}">
        @foreach ($days as $d)
            @php($pct = round((float) $d['total'] / $max * 100))
            <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                <span class="text-[11px] text-ink-soft tabular-nums">{{ (float) $d['total'] > 0 ? bn_digits(number_format((float) $d['total'] / 1000, (float) $d['total'] >= 10000 ? 0 : 1)) . 'হা' : '' }}</span>
                <div @class(['w-full rounded-t', 'bg-ledger-700' => $d['date']->isToday(), 'bg-ledger-200' => ! $d['date']->isToday()])
                     style="height: {{ max($pct, (float) $d['total'] > 0 ? 3 : 0) }}%"
                     title="{{ bn_date($d['date']) }}: {{ bn_money($d['total']) }}"></div>
                <span @class(['text-xs', 'font-bold text-ledger-900' => $d['date']->isToday(), 'text-ink-soft' => ! $d['date']->isToday()])>{{ ['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহঃ', 'শুক্র', 'শনি'][$d['date']->dayOfWeek] }}</span>
            </div>
        @endforeach
    </div>
    <p class="mt-2 text-xs text-ink-soft">হা = হাজার টাকা</p>
</section>
