@use('App\Services\Somiti\SomitiStatementService', 'S')
@php
    $d = $account->somitiDetail;
    $cells = $grid['cells'][$account->id] ?? [];
    $firstWeekday = $month->copy()->startOfMonth()->dayOfWeek; // 0 = Sunday
@endphp
<x-layouts.app :title="$account->account_no">
    <x-page-header :title="'সমিতি অ্যাকাউন্ট ' . $account->account_no" :description="$account->member->name . ' — ' . ($account->member->business_name ?: $account->member->member_code)">
        <x-slot:actions>
            @can('collect', $account)
                <a href="{{ route((auth()->user()->isAdmin() ? 'admin' : 'collector') . '.collections.create', $account) }}" class="btn-primary">টাকা নিন</a>
            @endcan
            <a href="{{ route(auth()->user()->isAdmin() ? 'admin.members.show' : 'collector.members.show', $account->member) }}" class="btn-quiet">সদস্যের তথ্য</a>
            @can('changeShares', $account)
                <x-modal title="শেয়ারের সংখ্যা পরিবর্তন" :open="$errors->shares->any()">
                    <x-slot:trigger><button type="button" class="btn-quiet">শেয়ার পরিবর্তন</button></x-slot:trigger>
                    <form method="POST" action="{{ route('admin.somiti.shares', $account) }}" class="space-y-4"
                          x-data="{ n: {{ (int) old('new_shares', $d->number_of_shares) }}, v: {{ (float) $d->share_value }} }">
                        @csrf
                        <p class="text-[15px] text-ink-soft">বর্তমান: {{ bn_digits($d->number_of_shares) }} শেয়ার, দৈনিক {{ bn_money($d->daily_deposit_amount) }}। আগের দিনের হিসাব অপরিবর্তিত থাকবে।</p>
                        <div>
                            <label for="new_shares" class="field-label">নতুন শেয়ারের সংখ্যা</label>
                            <input id="new_shares" name="new_shares" type="number" min="1" max="1000" class="field" x-model.number="n" required>
                            <p class="field-help">নতুন দৈনিক জমা: <span class="font-semibold text-ink" x-text="bnMoney((n || 0) * v)"></span></p>
                            @error('new_shares', 'shares') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <x-field name="effective_date" label="কার্যকর হওয়ার তারিখ" type="date" bag="shares" required
                                 :value="today()->toDateString()" :min="today()->toDateString()"
                                 help="এই তারিখের আগে অগ্রিম জমা থাকলে, পার্থক্যের টাকা বকেয়া হিসেবে দেখাবে।" />
                        <x-field name="reason" label="কারণ (ঐচ্ছিক)" bag="shares" />
                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn-quiet" @click="open = false">বাতিল</button>
                            <button type="submit" class="btn-primary">পরিবর্তন করুন</button>
                        </div>
                    </form>
                </x-modal>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <section class="panel mb-6 grid grid-cols-2 lg:grid-cols-6 lg:divide-x lg:divide-rule">
        <x-stat label="শেয়ারের সংখ্যা" :value="bn_digits($d->number_of_shares)" :note="'প্রতি শেয়ার ' . bn_money($d->share_value)" />
        <x-stat label="দৈনিক জমা" :value="bn_money($d->daily_deposit_amount)" />
        <x-stat label="মোট সঞ্চয়" :value="bn_money($summary['total_saved'])"
                :note="\App\Support\Money::toPaisa($summary['opening_balance']) > 0 ? 'প্রারম্ভিক ' . bn_money($summary['opening_balance']) . ' + সংগ্রহ ' . bn_money($summary['collected']) : null" />
        <x-stat label="বকেয়া" :value="bn_money($summary['due'])" :note="bn_digits($summary['due_days']) . ' দিন'" />
        <x-stat label="অগ্রিম জমা" :value="bn_money($summary['advance'])" />
        <x-stat label="শুরুর তারিখ" :value="bn_date($d->start_date)" :note="'কালেক্টর: ' . ($account->collector?->name ?? '—')" />
    </section>

    <div class="grid gap-6 lg:grid-cols-5">
        <section class="panel p-4 lg:col-span-3">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-ledger-900">জমার ক্যালেন্ডার</h2>
                @include('somiti.partials.month-nav')
            </div>
            <div class="grid grid-cols-7 gap-1 text-center text-sm">
                @foreach (['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহঃ', 'শুক্র', 'শনি'] as $w)
                    <div class="py-1 font-semibold text-ink-soft">{{ $w }}</div>
                @endforeach
                @for ($i = 0; $i < $firstWeekday; $i++)<div></div>@endfor
                @foreach ($grid['days'] as $day)
                    @php($st = $cells[$day['date']] ?? null)
                    <div @class([
                        'flex aspect-square flex-col items-center justify-center rounded-md border text-[15px]',
                        'border-ledger-200 bg-ledger-100 text-ledger-900' => $st === 'paid',
                        'border-pending/30 bg-pending-50 text-pending' => $st === 'partial',
                        'border-danger/20 bg-danger-50 text-danger' => $st === 'unpaid' && ! $day['future'],
                        'border-rule bg-white text-ink-soft' => $st === 'unpaid' && $day['future'],
                        'border-transparent bg-rule/60 text-ink-soft' => ! $st && $day['off'],
                        'border-transparent text-ink-soft/40' => ! $st && ! $day['off'],
                    ]) title="{{ $day['holiday'] ?? '' }}">
                        <span>{{ bn_digits($day['day']) }}</span>
                        <span class="text-xs leading-none font-bold">{{ S::statusSymbol($st) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-3">@include('somiti.partials.legend')</div>
        </section>

        <div class="space-y-6 lg:col-span-2">
        <section class="panel p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-ledger-900">প্রারম্ভিক সঞ্চয়</h2>
                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ bn_money($d->opening_balance) }}</p>
                    @if ($d->opening_balance_note)<p class="text-sm text-ink-soft">{{ $d->opening_balance_note }}</p>@endif
                    @if ($d->openingBalanceUser)<p class="text-sm text-ink-soft">লিখেছেন: {{ $d->openingBalanceUser->name }}</p>@endif
                </div>
                @can('correctOpeningBalance', $account)
                    <x-modal title="প্রারম্ভিক সঞ্চয় সংশোধন" :open="$errors->opening->any()">
                        <x-slot:trigger><button type="button" class="btn-quiet px-3 py-1.5 text-sm">সংশোধন</button></x-slot:trigger>
                        <form method="POST" action="{{ route('admin.somiti.opening', $account) }}" class="space-y-4">
                            @csrf
                            @method('PUT')
                            <p class="text-[15px] text-ink-soft">ভুল লেখা হলে সঠিক পরিমাণ দিন। আগের পরিমাণ ও কারণ অডিট লগে সংরক্ষিত থাকবে।</p>
                            <x-field name="opening_balance" label="সঠিক প্রারম্ভিক সঞ্চয় (৳)" type="number" step="0.01" min="0" bag="opening" required :value="(string) $d->opening_balance" />
                            <x-field name="reason" label="সংশোধনের কারণ" bag="opening" required />
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn-quiet" @click="open = false">বাতিল</button>
                                <button type="submit" class="btn-primary">সংশোধন করুন</button>
                            </div>
                        </form>
                    </x-modal>
                @endcan
            </div>
        </section>

        <section class="panel h-fit overflow-hidden">
            <h2 class="border-b border-rule px-4 py-3 text-lg font-bold text-ledger-900">শেয়ারের ইতিহাস</h2>
            <ol>
                @foreach ($account->shareChanges->reverse() as $c)
                    <li class="border-b border-rule px-4 py-3 last:border-b-0">
                        <div class="flex justify-between gap-2">
                            <span class="font-semibold">
                                @if (is_null($c->old_shares))
                                    {{ bn_digits($c->new_shares) }} শেয়ার — অ্যাকাউন্ট খোলা
                                @else
                                    {{ bn_digits($c->old_shares) }} → {{ bn_digits($c->new_shares) }} শেয়ার
                                @endif
                            </span>
                            <span class="whitespace-nowrap">{{ bn_money($c->new_daily_amount) }}/দিন</span>
                        </div>
                        <p class="text-sm text-ink-soft">{{ bn_date($c->effective_date) }} থেকে কার্যকর — {{ $c->changer?->name }}</p>
                        @if ($c->reason && ! is_null($c->old_shares))<p class="text-sm text-ink-soft">{{ $c->reason }}</p>@endif
                    </li>
                @endforeach
            </ol>
        </section>
        </div>
    </div>
    @include('transactions.partials.recent')
</x-layouts.app>
