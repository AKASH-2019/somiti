<x-layouts.app title="আমার সদস্য">
    <x-page-header title="আমার সদস্য" :description="'আপনার দায়িত্বে মোট ' . bn_digits($members->total()) . ' জন সদস্য'">
        <x-slot:actions>
            <button type="button" class="btn-quiet" onclick="window.print()">প্রিন্ট করুন</button>
            <a href="{{ route('collector.members.create') }}" class="btn-primary">নতুন সদস্য</a>
        </x-slot:actions>
    </x-page-header>

    <x-search-bar>
        <div>
            <label for="status" class="sr-only">অবস্থা</label>
            <select id="status" name="status" class="field w-auto">
                <option value="">সব অবস্থা</option>
                <option value="active" @selected(request('status') === 'active')>সক্রিয়</option>
                <option value="inactive" @selected(request('status') === 'inactive')>নিষ্ক্রিয়</option>
            </select>
        </div>
    </x-search-bar>

    @include('members.partials.table', ['routePrefix' => 'collector.members', 'showCollector' => false])
</x-layouts.app>
