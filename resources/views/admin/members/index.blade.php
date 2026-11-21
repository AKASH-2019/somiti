<x-layouts.app title="সদস্য ব্যবস্থাপনা">
    <x-page-header title="সদস্য ব্যবস্থাপনা" :description="'মোট ' . bn_digits($members->total()) . ' জন সদস্য'">
        <x-slot:actions>
            <button type="button" class="btn-quiet" onclick="window.print()">প্রিন্ট করুন</button>
            <a href="{{ route('admin.members.create') }}" class="btn-primary">নতুন সদস্য</a>
        </x-slot:actions>
    </x-page-header>

    <x-search-bar>
        <div>
            <label for="collector" class="sr-only">কালেক্টর</label>
            <select id="collector" name="collector" class="field w-auto">
                <option value="">সব কালেক্টর</option>
                @foreach ($collectors as $c)
                    <option value="{{ $c->id }}" @selected(request('collector') == $c->id)>{{ $c->name }}{{ $c->is_active ? '' : ' (নিষ্ক্রিয়)' }}</option>
                @endforeach
                <option value="none" @selected(request('collector') === 'none')>কালেক্টর নেই</option>
            </select>
        </div>
        <div>
            <label for="status" class="sr-only">অবস্থা</label>
            <select id="status" name="status" class="field w-auto">
                <option value="">সব অবস্থা</option>
                <option value="active" @selected(request('status') === 'active')>সক্রিয়</option>
                <option value="inactive" @selected(request('status') === 'inactive')>নিষ্ক্রিয়</option>
            </select>
        </div>
    </x-search-bar>

    @include('members.partials.table', ['routePrefix' => 'admin.members', 'showCollector' => true])
</x-layouts.app>
