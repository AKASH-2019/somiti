<x-layouts.app title="কালেক্টর ব্যবস্থাপনা">
    <x-page-header title="কালেক্টর ব্যবস্থাপনা" :description="'মোট ' . bn_digits($collectors->total()) . ' জন কালেক্টর'">
        <x-slot:actions>
            <a href="{{ route('admin.collectors.create') }}" class="btn-primary">নতুন কালেক্টর</a>
        </x-slot:actions>
    </x-page-header>

    <x-search-bar placeholder="নাম, ইউজারনেম বা মোবাইল নম্বর দিয়ে খুঁজুন">
        <div>
            <label for="status" class="sr-only">অবস্থা</label>
            <select id="status" name="status" class="field w-auto">
                <option value="">সব অবস্থা</option>
                <option value="active" @selected($status === 'active')>সক্রিয়</option>
                <option value="inactive" @selected($status === 'inactive')>নিষ্ক্রিয়</option>
            </select>
        </div>
    </x-search-bar>

    <div class="panel overflow-hidden">
        @if ($collectors->isEmpty())
            <p class="px-5 py-8 text-center text-ink-soft">কোনো কালেক্টর পাওয়া যায়নি।</p>
        @else
            <div class="overflow-x-auto">
                <table class="register">
                    <thead>
                    <tr><th>নাম</th><th>ইউজারনেম</th><th>মোবাইল</th><th class="num">সদস্য</th><th>শেষ লগইন</th><th>অবস্থা</th><th></th></tr>
                    </thead>
                    <tbody>
                    @foreach ($collectors as $collector)
                        <tr>
                            <td class="font-semibold">{{ $collector->name }}</td>
                            <td>{{ $collector->username }}</td>
                            <td class="whitespace-nowrap">{{ bn_digits($collector->mobile) }}</td>
                            <td class="num">{{ bn_digits($collector->members_count) }}</td>
                            <td class="whitespace-nowrap text-ink-soft">{{ $collector->last_login_at ? bn_datetime($collector->last_login_at) : 'কখনো না' }}</td>
                            <td><x-status-badge :tone="$collector->is_active ? 'success' : 'muted'">{{ $collector->is_active ? 'সক্রিয়' : 'নিষ্ক্রিয়' }}</x-status-badge></td>
                            <td class="text-right"><a href="{{ route('admin.collectors.show', $collector) }}" class="text-sm font-semibold text-ledger-700 hover:underline">বিস্তারিত</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    {{ $collectors->links() }}
</x-layouts.app>
