<x-layouts.app title="টাকা হস্তান্তর">
    <x-page-header title="টাকা হস্তান্তর" :description="auth()->user()->isAdmin() ? 'সকল কালেক্টরের হস্তান্তর' : 'আপনার পাঠানো ও পাওয়া হস্তান্তর'">
        <x-slot:actions>
            <a href="{{ route($rp . '.create') }}" class="btn-primary">নতুন হস্তান্তর</a>
        </x-slot:actions>
    </x-page-header>

    @include('handovers.partials.incoming')

    <section class="panel mb-6 grid grid-cols-2 divide-x divide-rule">
        <x-stat label="আমার হাতে থাকা টাকা" :value="bn_money($balance)" />
        <x-stat label="হস্তান্তরযোগ্য" :value="bn_money($available)" note="অপেক্ষমাণ হস্তান্তর বাদে" />
    </section>

    <form method="GET" class="no-print mb-4 flex flex-wrap items-end gap-2">
        <div>
            <label for="status" class="field-label">অবস্থা</label>
            <select id="status" name="status" class="field w-auto">
                <option value="">সব</option>
                @foreach (\App\Enums\HandoverStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        @if ($people->isNotEmpty())
            <div>
                <label for="person" class="field-label">কালেক্টর</label>
                <select id="person" name="person" class="field w-auto">
                    <option value="">সবাই</option>
                    @foreach ($people as $p)
                        <option value="{{ $p->id }}" @selected(request('person') == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <button type="submit" class="btn-primary">দেখুন</button>
    </form>

    <div class="panel overflow-hidden">
        @if ($handovers->isEmpty())
            <p class="px-5 py-8 text-center text-ink-soft">কোনো হস্তান্তর নেই।</p>
        @else
            @include('handovers.partials.table')
        @endif
    </div>
    {{ $handovers->links() }}
</x-layouts.app>
