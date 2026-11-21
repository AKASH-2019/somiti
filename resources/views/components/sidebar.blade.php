@php
    $user = auth()->user();
    // route = null → screen arrives in a later phase (shown greyed with its phase number).
    // children → expandable group; it opens automatically when one of its pages is active.
    $items = $user->isAdmin()
        ? [
            ['label' => 'ড্যাশবোর্ড', 'route' => 'admin.dashboard', 'match' => 'admin.dashboard'],
            ['label' => 'সদস্য ব্যবস্থাপনা', 'route' => 'admin.members.index', 'match' => 'admin.members.*'],
            ['label' => 'সমিতি ব্যবস্থাপনা', 'route' => 'admin.somiti.index', 'match' => 'admin.somiti.*'],
            ['label' => 'কিস্তি ব্যবস্থাপনা', 'route' => 'admin.kisti.index', 'match' => 'admin.kisti.*'],
            ['label' => 'টাকা সংগ্রহ', 'match' => ['admin.collections.*', 'admin.transactions.*'], 'children' => [
                ['label' => 'সমিতি সংগ্রহ', 'route' => 'admin.collections.somiti', 'match' => 'admin.collections.somiti'],
                ['label' => 'কিস্তি সংগ্রহ', 'route' => 'admin.collections.kisti', 'match' => 'admin.collections.kisti'],
            ]],
            ['label' => 'কালেক্টর ব্যবস্থাপনা', 'route' => 'admin.collectors.index', 'match' => 'admin.collectors.*'],
            ['label' => 'টাকা হস্তান্তর', 'route' => 'admin.handovers.index', 'match' => 'admin.handovers.*', 'badge' => $pendingIncoming ?? 0],
            ['label' => 'কালেক্টর ক্যাশ হিসাব', 'route' => 'admin.cash.index', 'match' => 'admin.cash.*'],
            ['label' => 'রিপোর্ট', 'route' => 'admin.reports.index', 'match' => 'admin.reports.*'],
            ['label' => 'অডিট লগ', 'route' => null, 'phase' => 8],
            ['label' => 'ছুটির দিন', 'route' => 'admin.holidays.index', 'match' => 'admin.holidays.*'],
            ['label' => 'সেটিংস', 'route' => 'admin.settings.edit', 'match' => 'admin.settings.*'],
        ]
        : [
            ['label' => 'ড্যাশবোর্ড', 'route' => 'collector.dashboard', 'match' => 'collector.dashboard'],
            ['label' => 'আমার সদস্য', 'route' => 'collector.members.index', 'match' => ['collector.members.index', 'collector.members.show', 'collector.members.edit']],
            ['label' => 'নতুন সদস্য', 'route' => 'collector.members.create', 'match' => 'collector.members.create'],
            ['label' => 'সমিতি', 'route' => 'collector.somiti.index', 'match' => 'collector.somiti.*'],
            ['label' => 'কিস্তি', 'route' => 'collector.kisti.index', 'match' => 'collector.kisti.*'],
            ['label' => 'টাকা সংগ্রহ', 'match' => ['collector.collections.*', 'collector.transactions.*'], 'children' => [
                ['label' => 'সমিতি সংগ্রহ', 'route' => 'collector.collections.somiti', 'match' => 'collector.collections.somiti'],
                ['label' => 'কিস্তি সংগ্রহ', 'route' => 'collector.collections.kisti', 'match' => 'collector.collections.kisti'],
            ]],
            ['label' => 'টাকা হস্তান্তর', 'route' => 'collector.handovers.index', 'match' => ['collector.handovers.index', 'collector.handovers.create', 'collector.handovers.show']],
            ['label' => 'টাকা গ্রহণ নিশ্চিতকরণ', 'route' => 'collector.handovers.incoming', 'match' => 'collector.handovers.incoming', 'badge' => $pendingIncoming ?? 0],
            ['label' => 'আমার ক্যাশ হিসাব', 'route' => 'collector.cash.mine', 'match' => 'collector.cash.*'],
            ['label' => 'সংগ্রহের রিপোর্ট', 'route' => 'collector.reports.index', 'match' => 'collector.reports.*'],
        ];
    $pendingIncoming = \App\Models\CashHandover::query()->where('receiver_collector_id', $user->id)->where('status', 'pending')->count();
    foreach ($items as &$i) {
        if (array_key_exists('badge', $i)) {
            $i['badge'] = $pendingIncoming;
        }
    }
    unset($i);
    $link = 'flex items-center justify-between rounded-md border-l-4 px-3 py-2 text-[15px] transition-colors';
    $on = 'border-ledger-200 bg-ledger-800 font-semibold text-white';
    $off = 'border-transparent text-ledger-100 hover:bg-ledger-800/60 hover:text-white';
@endphp

<div x-show="nav" x-cloak x-transition.opacity class="no-print fixed inset-0 z-30 bg-ledger-950/50 lg:hidden" @click="nav = false"></div>

<aside
    class="no-print fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 -translate-x-full flex-col bg-ledger-900 text-ledger-50 transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
    :class="nav && 'translate-x-0!'"
    aria-label="প্রধান মেনু"
>
    <div class="border-b border-ledger-800 px-5 py-5">
        <p class="text-lg leading-snug font-bold text-white">{{ setting('org_name') }}</p>
        <p class="mt-0.5 text-sm text-ledger-200">সমিতি ও কিস্তি হিসাব</p>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4">
        <ul class="space-y-0.5">
            @foreach ($items as $item)
                <li>
                    @if (isset($item['children']))
                        @php($groupActive = request()->routeIs($item['match']))
                        <div x-data="{ open: @js($groupActive) }">
                            <button type="button" @click="open = !open" :aria-expanded="open.toString()"
                                    class="{{ $link }} w-full text-left {{ $groupActive ? 'border-transparent font-semibold text-white' : $off }}">
                                {{ $item['label'] }}
                                <svg class="h-4 w-4 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                            </button>
                            <ul x-show="open" x-cloak class="mt-0.5 space-y-0.5 pl-4">
                                @foreach ($item['children'] as $child)
                                    @php($active = request()->routeIs($child['match']))
                                    <li>
                                        <a href="{{ route($child['route']) }}" class="{{ $link }} {{ $active ? $on : $off }}" @if ($active) aria-current="page" @endif>{{ $child['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @elseif ($item['route'])
                        @php($active = request()->routeIs($item['match']))
                        <a href="{{ route($item['route']) }}" class="{{ $link }} {{ $active ? $on : $off }}" @if ($active) aria-current="page" @endif>
                            <span>{{ $item['label'] }}</span>
                            @if (! empty($item['badge']))
                                <span class="rounded-full bg-pending px-2 text-xs font-bold text-white">{{ bn_digits($item['badge']) }}</span>
                            @endif
                        </a>
                    @else
                        <span class="{{ $link }} border-transparent text-ledger-200/50" title="ধাপ {{ bn_digits($item['phase']) }}-এ চালু হবে">
                            {{ $item['label'] }}
                            <span class="text-xs">ধাপ {{ bn_digits($item['phase']) }}</span>
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="border-t border-ledger-800 px-3 py-3 sm:hidden">
        <a href="{{ route('password.edit') }}" class="block rounded-md px-3 py-2 text-[15px] text-ledger-100 hover:bg-ledger-800/60">পাসওয়ার্ড পরিবর্তন</a>
    </div>
</aside>
