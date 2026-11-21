@use('App\Reports\ReportFormatter')
@php
    $q = $filters->query();
    $period = $report->period();
@endphp
<x-layouts.app :title="$report->title()">
    <style>@media print { @page { size: A4 {{ $report->landscape() ? 'landscape' : 'portrait' }}; margin: 10mm; } .report-table { font-size: 11px; } }</style>

    <x-page-header :title="$report->title()" :description="$report->periodLabel($filters) . ($report->scopeLabel($filters) ? ' — ' . $report->scopeLabel($filters) : '')">
        <x-slot:actions>
            <a href="{{ route($rp . '.index') }}" class="btn-quiet">সব রিপোর্ট</a>
            <button type="button" class="btn-quiet" onclick="window.print()">প্রিন্ট করুন</button>
            <a href="{{ route($rp . '.pdf', [$report->key()] + $q) }}" target="_blank" class="btn-quiet">PDF</a>
            <a href="{{ route($rp . '.excel', [$report->key()] + $q) }}" class="btn-quiet">Excel</a>
        </x-slot:actions>
    </x-page-header>

    <p class="mb-4 hidden text-center text-lg font-bold print:block">{{ setting('org_name') }}</p>

    @if ($period !== 'none' || in_array('type', $report->filters(), true) || (in_array('collector', $report->filters(), true) && $collectors->isNotEmpty()))
        <form method="GET" class="no-print mb-5 flex flex-wrap items-end gap-2">
            @if ($period === 'date')
                <div><label for="date" class="field-label">তারিখ</label><input id="date" name="date" type="date" class="field" value="{{ $filters->from->toDateString() }}"></div>
            @elseif ($period === 'range')
                <div><label for="from" class="field-label">শুরু</label><input id="from" name="from" type="date" class="field" value="{{ $filters->from->toDateString() }}"></div>
                <div><label for="to" class="field-label">শেষ</label><input id="to" name="to" type="date" class="field" value="{{ $filters->to->toDateString() }}"></div>
            @elseif ($period === 'month')
                <div><label for="month" class="field-label">মাস</label><input id="month" name="month" type="month" class="field" value="{{ $filters->from->format('Y-m') }}"></div>
            @endif
            @if (in_array('type', $report->filters(), true))
                <div>
                    <label for="type" class="field-label">ধরন</label>
                    <select id="type" name="type" class="field w-auto">
                        <option value="">সমিতি ও কিস্তি</option>
                        <option value="somiti" @selected($filters->type === 'somiti')>সমিতি</option>
                        <option value="kisti" @selected($filters->type === 'kisti')>কিস্তি</option>
                    </select>
                </div>
            @endif
            @if (in_array('collector', $report->filters(), true) && $collectors->isNotEmpty())
                <div>
                    <label for="collector" class="field-label">কালেক্টর</label>
                    <select id="collector" name="collector" class="field w-auto">
                        <option value="">সবাই</option>
                        @foreach ($collectors as $c)
                            <option value="{{ $c->id }}" @selected($filters->collectorId === $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="submit" class="btn-primary">দেখুন</button>
        </form>
    @endif

    @if ($result->summary)
        <section class="panel mb-5 flex flex-wrap divide-x divide-rule">
            @foreach ($result->summary as $label => $value)
                <div class="min-w-36 flex-1 px-4 py-3">
                    <p class="text-sm text-ink-soft">{{ $label }}</p>
                    <p class="text-lg font-bold text-ledger-900">{{ $value }}</p>
                </div>
            @endforeach
        </section>
    @endif

    <div class="panel report-table overflow-x-auto">
        @if (empty($result->rows))
            <p class="px-5 py-8 text-center text-ink-soft">এই রিপোর্টে দেখানোর মতো কোনো তথ্য নেই।</p>
        @else
            <table class="register">
                <thead>
                <tr>
                    <th>ক্রমিক</th>
                    @foreach ($result->columns as $c)<th @class(['num' => $c->isNumeric()])>{{ $c->label }}</th>@endforeach
                </tr>
                </thead>
                <tbody>
                @foreach ($result->rows as $row)
                    <tr>
                        <td>{{ bn_digits($loop->iteration) }}</td>
                        @foreach ($result->columns as $c)
                            <td @class(['num whitespace-nowrap' => $c->isNumeric(), 'whitespace-nowrap' => in_array($c->type, ['date', 'datetime'], true)])>{{ ReportFormatter::display($row[$c->key] ?? null, $c) }}</td>
                        @endforeach
                    </tr>
                @endforeach
                @if ($result->totals)
                    <tr class="bg-ledger-50 font-bold">
                        <td></td>
                        @foreach ($result->columns as $c)
                            <td @class(['num whitespace-nowrap' => $c->isNumeric()])>
                                {{ array_key_exists($c->key, $result->totals) ? ($c->isNumeric() ? ReportFormatter::display($result->totals[$c->key], $c) : $result->totals[$c->key]) : '' }}
                            </td>
                        @endforeach
                    </tr>
                @endif
                </tbody>
            </table>
        @endif
    </div>
    @if ($result->note)<p class="mt-3 text-sm text-ink-soft">{{ $result->note }}</p>@endif
    <p class="mt-1 text-sm text-ink-soft">তৈরি: {{ bn_datetime(now()) }} — {{ auth()->user()->name }}</p>
</x-layouts.app>
