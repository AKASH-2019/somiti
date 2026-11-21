<x-layouts.app title="ছুটির দিন">
    <x-page-header title="ছুটির দিন" description="এসব তারিখে দৈনিক সমিতি জমা ও দৈনিক কিস্তি নির্ধারিত হবে না।">
        <x-slot:actions>
            <form method="GET" class="flex items-center gap-2">
                <label for="year" class="text-sm text-ink-soft">বছর</label>
                <select id="year" name="year" class="field w-auto py-1.5" onchange="this.form.submit()">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($y === $year)>{{ bn_digits($y) }}</option>
                    @endforeach
                </select>
            </form>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="panel h-fit p-5">
            <h2 class="mb-4 text-lg font-bold text-ledger-900">নতুন ছুটি যোগ করুন</h2>
            <form method="POST" action="{{ route('admin.holidays.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="date" class="field-label">তারিখ</label>
                    <input id="date" name="date" type="date" class="field" value="{{ old('date') }}" required>
                    @error('date') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="title" class="field-label">ছুটির নাম</label>
                    <input id="title" name="title" class="field" value="{{ old('title') }}" placeholder="যেমন: বিজয় দিবস" required>
                    @error('title') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-primary w-full">যোগ করুন</button>
            </form>
        </section>

        <section class="panel overflow-hidden lg:col-span-2">
            <h2 class="border-b border-rule px-4 py-3 text-lg font-bold text-ledger-900">{{ bn_digits($year) }} সালের ছুটি ({{ bn_digits($holidays->count()) }}টি)</h2>
            @if ($holidays->isEmpty())
                <p class="px-4 py-6 text-ink-soft">এই বছরের কোনো ছুটি যোগ করা হয়নি। বাম পাশের ফর্ম থেকে যোগ করুন।</p>
            @else
                <div class="overflow-x-auto">
                    <table class="register">
                        <thead><tr><th>ক্রমিক নং</th><th>তারিখ</th><th>বার</th><th>ছুটির নাম</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($holidays as $holiday)
                            @php($past = $holiday->date->lt(today()))
                            <tr @class(['text-ink-soft' => $past])>
                                <td>{{ bn_digits($loop->iteration) }}</td>
                                <td class="whitespace-nowrap">{{ bn_date($holiday->date) }}</td>
                                <td>{{ \App\Support\BanglaFormatter::DAYS[$holiday->date->dayOfWeek] }}</td>
                                <td class="font-medium">{{ $holiday->title }}</td>
                                <td class="text-right">
                                    @if ($past)
                                        <x-status-badge>অতীত</x-status-badge>
                                    @else
                                        <x-confirm :action="route('admin.holidays.destroy', $holiday)"
                                                   title="ছুটির দিন মুছে ফেলবেন?"
                                                   :message="bn_date($holiday->date) . ' — ' . $holiday->title . ' তালিকা থেকে মুছে যাবে।'"
                                                   confirm-label="হ্যাঁ, মুছে ফেলুন">মুছুন</x-confirm>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-layouts.app>
