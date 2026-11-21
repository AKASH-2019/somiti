<x-layouts.app title="ড্যাশবোর্ড">
    <x-page-header title="ড্যাশবোর্ড">
        <x-slot:actions>
            <a href="{{ route('admin.reports.show', 'daily-cash-closing') }}" class="btn-quiet">আজকের ক্যাশ ক্লোজিং</a>
            <a href="{{ route('admin.collections.somiti') }}" class="btn-primary">টাকা সংগ্রহ</a>
        </x-slot:actions>
    </x-page-header>

    <div @class([
        'mb-6 rounded-md border px-4 py-3 text-[15px]',
        'border-ledger-200 bg-ledger-100 text-ledger-900' => $today['working'],
        'border-pending/30 bg-pending-50 text-pending' => ! $today['working'],
    ])>
        @if ($today['working']) আজ কার্যদিবস — সংগ্রহ চলবে।
        @else আজ ছুটির দিন ({{ $today['reason'] }})। @endif
        @if ($d['disputed'] > 0)
            <a href="{{ route('admin.handovers.index', ['status' => 'disputed']) }}" class="ml-2 font-semibold text-danger underline">{{ bn_digits($d['disputed']) }}টি বিরোধপূর্ণ হস্তান্তর তদন্তের অপেক্ষায়</a>
        @endif
    </div>

    {{-- Today's money: the headline --}}
    <section class="panel mb-4 grid grid-cols-1 sm:grid-cols-3 sm:divide-x sm:divide-rule">
        <div class="bg-ledger-900 px-5 py-4 text-white sm:rounded-l-lg">
            <p class="text-sm text-ledger-200">আজকের মোট সংগ্রহ</p>
            <p class="text-3xl font-bold tabular-nums">{{ bn_money($d['today']['total']) }}</p>
            <p class="text-sm text-ledger-200">{{ bn_digits($d['today']['receipts']) }}টি রসিদ</p>
        </div>
        <x-stat label="আজকের সমিতি সংগ্রহ" :value="bn_money($d['today']['somiti'])" class="px-5 py-4" />
        <x-stat label="আজকের কিস্তি সংগ্রহ" :value="bn_money($d['today']['kisti'])" class="px-5 py-4" />
    </section>

    <section class="panel mb-4 grid grid-cols-2 lg:grid-cols-4 lg:divide-x lg:divide-rule">
        <a href="{{ route('admin.reports.show', 'kisti-dues') }}" class="hover:bg-ledger-50"><x-stat label="মোট বকেয়া" :value="bn_money($d['dues']['due'])" note="সমিতি ও কিস্তি, আজ পর্যন্ত" /></a>
        <a href="{{ route('admin.reports.show', 'advance') }}" class="hover:bg-ledger-50"><x-stat label="মোট অগ্রিম জমা" :value="bn_money($d['dues']['advance'])" /></a>
        <a href="{{ route('admin.cash.index') }}" class="hover:bg-ledger-50"><x-stat label="কালেক্টরদের হাতে থাকা টাকা" :value="bn_money($d['cashInHands'])" /></a>
        <a href="{{ route('admin.handovers.index', ['status' => 'pending']) }}" class="hover:bg-ledger-50"><x-stat label="অপেক্ষমাণ টাকা হস্তান্তর" :value="bn_digits($d['pendingCount']) . 'টি'" :note="bn_money($d['pendingAmount'])" /></a>
    </section>

    <section class="panel mb-6 grid grid-cols-2 lg:grid-cols-4 lg:divide-x lg:divide-rule">
        <x-stat label="মোট সদস্য" :value="bn_digits($d['members'])" :note="'সক্রিয় ' . bn_digits($d['activeMembers']) . ' জন'" />
        <x-stat label="মোট সমিতি অ্যাকাউন্ট" :value="bn_digits($d['somitiAccounts'])" :note="'শেয়ার মূল্য ' . bn_money($shareValue)" />
        <x-stat label="মোট কিস্তি অ্যাকাউন্ট" :value="bn_digits($d['kistiAccounts'])" note="চালু ঋণ" />
        <x-stat label="মোট কালেক্টর" :value="bn_digits($d['collectors'])" :note="'সক্রিয় ' . bn_digits($d['activeCollectors']) . ' জন'" />
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="panel overflow-hidden xl:col-span-2">
            <div class="flex items-center justify-between border-b border-rule px-4 py-3">
                <h2 class="text-lg font-bold text-ledger-900">আজকের কালেক্টর কার্যক্রম</h2>
                <a href="{{ route('admin.reports.show', 'collector-collection') }}" class="text-sm font-semibold text-ledger-700 hover:underline">রিপোর্ট</a>
            </div>
            <div class="overflow-x-auto">
                <table class="register">
                    <thead><tr><th>নাম</th><th class="num">রসিদ</th><th class="num">সমিতি</th><th class="num">কিস্তি</th><th class="num">মোট</th><th>শেষ সংগ্রহ</th><th class="num">হাতে</th></tr></thead>
                    <tbody>
                    @forelse ($d['activity'] as $a)
                        <tr @class(['text-ink-soft' => $a->receipts === 0])>
                            <td class="font-semibold">{{ $a->user->name }}@if ($a->user->isAdmin()) <span class="text-xs font-normal text-ink-soft">(এডমিন)</span>@endif</td>
                            <td class="num">{{ bn_digits($a->receipts) }}</td>
                            <td class="num whitespace-nowrap">{{ bn_money($a->somiti) }}</td>
                            <td class="num whitespace-nowrap">{{ bn_money($a->kisti) }}</td>
                            <td class="num whitespace-nowrap font-semibold">{{ bn_money($a->total) }}</td>
                            <td class="whitespace-nowrap">{{ $a->last ? bn_digits(\Illuminate\Support\Carbon::parse($a->last)->format('g:i')) : '—' }}</td>
                            <td class="num whitespace-nowrap"><a href="{{ route('admin.cash.show', $a->user) }}" class="hover:underline">{{ bn_money($a->balance) }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-ink-soft">কোনো সক্রিয় কালেক্টর নেই।</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="space-y-6">
            <x-week-bars :days="$d['week']" />
            <section class="panel overflow-hidden">
                <div class="flex items-center justify-between border-b border-rule px-4 py-3">
                    <h2 class="text-lg font-bold text-ledger-900">আসন্ন ছুটির দিন</h2>
                    <a href="{{ route('admin.holidays.index') }}" class="text-sm font-semibold text-ledger-700 hover:underline">সব দেখুন</a>
                </div>
                @forelse ($upcomingHolidays as $holiday)
                    <div class="flex justify-between gap-4 border-b border-rule px-4 py-2.5 last:border-b-0">
                        <span>{{ $holiday->title }}</span>
                        <span class="text-ink-soft">{{ bn_date($holiday->date) }}</span>
                    </div>
                @empty
                    <p class="px-4 py-5 text-ink-soft">কোনো আসন্ন ছুটি যোগ করা হয়নি।</p>
                @endforelse
            </section>
        </div>
    </div>
</x-layouts.app>
