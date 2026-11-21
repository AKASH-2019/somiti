<x-layouts.app title="ড্যাশবোর্ড">
    <x-page-header :title="'স্বাগতম, ' . auth()->user()->name">
        <x-slot:actions>
            <a href="{{ route('collector.collections.somiti') }}" class="btn-primary">সমিতি সংগ্রহ</a>
            <a href="{{ route('collector.collections.kisti') }}" class="btn-quiet">কিস্তি সংগ্রহ</a>
        </x-slot:actions>
    </x-page-header>

    <div @class([
        'mb-6 rounded-md border px-4 py-3 text-[15px]',
        'border-ledger-200 bg-ledger-100 text-ledger-900' => $today['working'],
        'border-pending/30 bg-pending-50 text-pending' => ! $today['working'],
    ])>
        @if ($today['working']) আজ কার্যদিবস — সংগ্রহ চলবে। @else আজ ছুটির দিন ({{ $today['reason'] }})। @endif
    </div>

    {{-- Money waiting for my confirmation comes first --}}
    @if ($d['incoming']->isNotEmpty())
        @include('handovers.partials.incoming', ['incoming' => $d['incoming'], 'rp' => 'collector.handovers'])
    @endif

    <section class="panel mb-4 grid grid-cols-1 sm:grid-cols-4 sm:divide-x sm:divide-rule">
        <div class="bg-ledger-900 px-5 py-4 text-white sm:rounded-l-lg">
            <p class="text-sm text-ledger-200">আজকের সংগ্রহ</p>
            <p class="text-3xl font-bold tabular-nums">{{ bn_money($d['today']['total']) }}</p>
            <p class="text-sm text-ledger-200">{{ bn_digits($d['today']['receipts']) }}টি রসিদ</p>
        </div>
        <x-stat label="সমিতি সংগ্রহ" :value="bn_money($d['today']['somiti'])" class="px-5 py-4" />
        <x-stat label="কিস্তি সংগ্রহ" :value="bn_money($d['today']['kisti'])" class="px-5 py-4" />
        <a href="{{ route('collector.cash.mine') }}" class="hover:bg-ledger-50"><x-stat label="বর্তমান ক্যাশ ব্যালেন্স" :value="bn_money($d['balance'])" :note="'হস্তান্তরযোগ্য ' . bn_money($d['available'])" class="px-5 py-4" /></a>
    </section>

    <section class="panel mb-6 grid grid-cols-2 lg:grid-cols-4 lg:divide-x lg:divide-rule">
        <a href="{{ route('collector.members.index') }}" class="hover:bg-ledger-50"><x-stat label="মোট সদস্য" :value="bn_digits($d['members'])" :note="'সক্রিয় ' . bn_digits($d['activeMembers']) . ' জন'" /></a>
        <x-stat label="বকেয়া সদস্য" :value="bn_digits($d['dueMemberCount']) . ' জন'" :note="'মোট বকেয়া ' . bn_money($d['dues']['due'])" />
        <a href="{{ route('collector.handovers.index') }}" class="hover:bg-ledger-50"><x-stat label="টাকা হস্তান্তরের অনুরোধ" :value="bn_digits($d['outgoing']->count()) . 'টি'" :note="bn_money((string) $d['outgoing']->sum('amount')) . ' অপেক্ষমাণ'" /></a>
        <a href="{{ route('collector.handovers.incoming') }}" class="hover:bg-ledger-50"><x-stat label="টাকা গ্রহণের অনুরোধ" :value="bn_digits($d['incoming']->count()) . 'টি'" :note="bn_money((string) $d['incoming']->sum('amount'))" /></a>
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-rule px-4 py-3">
                <h2 class="text-lg font-bold text-ledger-900">বকেয়া সদস্য</h2>
                <a href="{{ route('collector.reports.show', 'kisti-dues') }}" class="text-sm font-semibold text-ledger-700 hover:underline">রিপোর্ট</a>
            </div>
            @forelse ($d['dueMembers'] as $m)
                <a href="{{ route('collector.members.show', $m->id) }}" class="flex items-center justify-between gap-3 border-b border-rule px-4 py-2.5 last:border-b-0 hover:bg-ledger-50">
                    <span><span class="font-semibold">{{ $m->name }}</span><span class="block text-xs text-ink-soft">{{ $m->member_code }} · {{ bn_digits($m->mobile) }}</span></span>
                    <span class="text-right"><span class="font-semibold text-danger">{{ bn_money(number_format((float) $m->due, 2, '.', '')) }}</span><span class="block text-xs text-ink-soft">{{ bn_digits($m->n) }}টি</span></span>
                </a>
            @empty
                <p class="px-4 py-5 text-ledger-700">কোনো বকেয়া নেই।</p>
            @endforelse
        </section>

        <section class="panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-rule px-4 py-3">
                <h2 class="text-lg font-bold text-ledger-900">সাম্প্রতিক লেনদেন</h2>
                <a href="{{ route('collector.transactions.index') }}" class="text-sm font-semibold text-ledger-700 hover:underline">সব দেখুন</a>
            </div>
            @forelse ($d['recent'] as $t)
                <a href="{{ route('collector.transactions.show', $t) }}" @class(['flex items-center justify-between gap-3 border-b border-rule px-4 py-2.5 last:border-b-0 hover:bg-ledger-50', 'text-ink-soft line-through' => $t->isReversed()])>
                    <span><span class="font-semibold">{{ $t->member->name }}</span><span class="block text-xs text-ink-soft">{{ $t->account->type->label() }} · {{ bn_datetime($t->collected_at) }}</span></span>
                    <span class="font-semibold tabular-nums">{{ bn_money($t->amount) }}</span>
                </a>
            @empty
                <p class="px-4 py-5 text-ink-soft">এখনো কোনো লেনদেন নেই।</p>
            @endforelse
        </section>

        <x-week-bars :days="$d['week']" title="আমার গত ৭ দিনের সংগ্রহ" />
    </div>
</x-layouts.app>
