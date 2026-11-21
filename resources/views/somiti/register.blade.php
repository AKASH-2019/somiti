@use('App\Services\Somiti\SomitiStatementService', 'S')
@php
    $monthName = \App\Support\BanglaFormatter::MONTHS[$month->month] . ' ' . bn_digits($month->year);
@endphp
<x-layouts.app title="সমিতি রেজিস্টার">
    <style>@media print { @page { size: A4 landscape; margin: 8mm; } .register-sheet { font-size: 10px; } }</style>

    <x-page-header title="সমিতি রেজিস্টার" :description="$monthName . ' — ' . setting('org_name')">
        <x-slot:actions>
            @include('somiti.partials.month-nav')
            <button type="button" class="btn-quiet" onclick="window.print()">প্রিন্ট করুন</button>
        </x-slot:actions>
    </x-page-header>

    <x-search-bar placeholder="সদস্যের নাম, আইডি বা মোবাইল">
        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
        @if ($collectors->isNotEmpty())
            <div>
                <label for="collector" class="sr-only">কালেক্টর</label>
                <select id="collector" name="collector" class="field w-auto">
                    <option value="">সব কালেক্টর</option>
                    @foreach ($collectors as $c)
                        <option value="{{ $c->id }}" @selected(request('collector') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </x-search-bar>

    <div class="mb-3 no-print">@include('somiti.partials.legend')</div>

    <div class="panel register-sheet overflow-x-auto">
        @if ($accounts->isEmpty())
            <p class="px-5 py-8 text-center text-ink-soft">এই মাসে কোনো চালু সমিতি অ্যাকাউন্ট নেই।</p>
        @else
            <table class="register">
                <thead>
                <tr>
                    <th>ক্রমিক নং</th>
                    <th>নাম</th>
                    <th>প্রতিষ্ঠানের নাম/ঠিকানা</th>
                    <th class="num">শেয়ারের সংখ্যা</th>
                    <th class="num">দৈনিক সঞ্চয়</th>
                    <th class="num">কিস্তি</th>
                    @foreach ($grid['days'] as $day)
                        <th @class(['px-1! text-center', 'bg-rule!' => $day['off']]) title="{{ $day['holiday'] ?? '' }}">{{ bn_digits($day['day']) }}</th>
                    @endforeach
                    <th class="num">মাসে জমা</th>
                    <th class="num">বকেয়া</th>
                    <th>মাসের নাম/মন্তব্য</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($accounts as $a)
                    @php($cells = $grid['cells'][$a->id] ?? [])
                    @php($tot = $grid['totals'][$a->id] ?? ['paid' => '0.00', 'due' => '0.00'])
                    <tr>
                        <td>{{ bn_digits($loop->iteration) }}</td>
                        <td class="whitespace-nowrap">
                            <a href="{{ route($rp . '.show', [$a, 'month' => $month->format('Y-m')]) }}" class="font-semibold hover:underline">{{ $a->member->name }}</a>
                            <span class="block text-xs text-ink-soft">{{ $a->account_no }}</span>
                        </td>
                        <td class="min-w-40">
                            {{ $a->member->business_name ?: '—' }}
                            @if ($a->member->business_address)<span class="block text-xs text-ink-soft">{{ $a->member->business_address }}</span>@endif
                        </td>
                        <td class="num">{{ bn_digits($a->somitiDetail->number_of_shares) }}</td>
                        <td class="num whitespace-nowrap">{{ bn_money($a->somitiDetail->daily_deposit_amount) }}</td>
                        <td class="num whitespace-nowrap">
                            @forelse ($a->member->accounts as $loan)
                                <span class="block">{{ bn_money($loan->kistiDetail?->installment_amount) }}<span class="text-xs text-ink-soft"> {{ $loan->kistiDetail?->frequency->label() }}</span></span>
                            @empty
                                <span class="text-ink-soft">—</span>
                            @endforelse
                        </td>
                        @foreach ($grid['days'] as $day)
                            @php($st = $cells[$day['date']] ?? null)
                            <td @class([
                                'px-1! text-center font-bold',
                                'bg-rule/60' => $day['off'] && ! $st,
                                'text-ledger-700' => $st === 'paid',
                                'text-pending' => $st === 'partial',
                                'text-danger' => $st === 'unpaid' && ! $day['future'],
                                'text-ink-soft/50' => $st === 'unpaid' && $day['future'],
                            ])>{{ S::statusSymbol($st) }}</td>
                        @endforeach
                        <td class="num whitespace-nowrap">{{ bn_money($tot['paid']) }}</td>
                        <td @class(['num whitespace-nowrap', 'text-danger font-semibold' => \App\Support\Money::toPaisa($tot['due']) > 0])>{{ bn_money($tot['due']) }}</td>
                        <td class="min-w-32 text-sm">{{ $a->notes }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-layouts.app>
