@use('App\Reports\ReportFormatter')
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: hindsiliguri; font-size: 10.5pt; color: #1C2A23; }
    h1 { font-size: 15pt; margin: 0; text-align: center; color: #0E3B2B; }
    h2 { font-size: 12.5pt; margin: 2pt 0 0; text-align: center; }
    .meta { text-align: center; color: #5A6B62; margin-bottom: 8pt; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th { background: #F2F7F3; border-bottom: 1.5pt solid #17603F; padding: 4pt; text-align: left; font-weight: bold; }
    table.data td { border-bottom: 0.5pt solid #D9E2DB; padding: 3.5pt 4pt; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    tr.total td { font-weight: bold; background: #F2F7F3; border-top: 1pt solid #17603F; }
    table.summary { width: 100%; margin: 6pt 0 10pt; border-collapse: collapse; }
    table.summary td { border: 0.5pt solid #D9E2DB; padding: 4pt 6pt; }
    .label { color: #5A6B62; font-size: 9pt; }
    .note { color: #5A6B62; font-size: 9pt; margin-top: 6pt; }
</style>
</head>
<body>
    <h1>{{ setting('org_name') }}</h1>
    <h2>{{ $report->title() }}</h2>
    <div class="meta">
        {{ $report->periodLabel($filters) }}@if ($report->scopeLabel($filters)) — {{ $report->scopeLabel($filters) }}@endif
    </div>

    @if ($result->summary)
        <table class="summary"><tr>
            @foreach ($result->summary as $label => $value)
                <td><div class="label">{{ $label }}</div><b>{{ $value }}</b></td>
                @if ($loop->iteration % 5 === 0 && ! $loop->last)</tr><tr>@endif
            @endforeach
        </tr></table>
    @endif

    <table class="data">
        <thead>
        <tr>
            <th>ক্রমিক</th>
            @foreach ($result->columns as $c)<th class="{{ $c->isNumeric() ? 'num' : '' }}">{{ $c->label }}</th>@endforeach
        </tr>
        </thead>
        <tbody>
        @forelse ($result->rows as $row)
            <tr>
                <td>{{ bn_digits($loop->iteration) }}</td>
                @foreach ($result->columns as $c)<td class="{{ $c->isNumeric() ? 'num' : '' }}">{{ ReportFormatter::display($row[$c->key] ?? null, $c) }}</td>@endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($result->columns) + 1 }}">কোনো তথ্য নেই।</td></tr>
        @endforelse
        @if ($result->totals && ! empty($result->rows))
            <tr class="total">
                <td></td>
                @foreach ($result->columns as $c)
                    <td class="{{ $c->isNumeric() ? 'num' : '' }}">{{ array_key_exists($c->key, $result->totals) ? ($c->isNumeric() ? ReportFormatter::display($result->totals[$c->key], $c) : $result->totals[$c->key]) : '' }}</td>
                @endforeach
            </tr>
        @endif
        </tbody>
    </table>

    @if ($result->note)<p class="note">{{ $result->note }}</p>@endif
    <p class="note">তৈরি: {{ bn_datetime(now()) }} — {{ auth()->user()->name }}</p>
</body>
</html>
