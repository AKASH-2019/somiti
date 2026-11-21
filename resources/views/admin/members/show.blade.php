<x-layouts.app :title="$member->name">
    <x-page-header :title="$member->name" :description="$member->business_name">
        <x-slot:actions>
            <a href="{{ route('admin.members.edit', $member) }}" class="btn-quiet">সম্পাদনা</a>

            <x-modal title="কালেক্টর পরিবর্তন" :open="$errors->assign->any()">
                <x-slot:trigger><button type="button" class="btn-quiet">কালেক্টর পরিবর্তন</button></x-slot:trigger>
                <form method="POST" action="{{ route('admin.members.assign', $member) }}" class="space-y-4">
                    @csrf
                    <p class="text-[15px] text-ink-soft">বর্তমান কালেক্টর: <span class="font-semibold text-ink">{{ $member->collector?->name ?? '—' }}</span></p>
                    <div>
                        <label for="assign_collector" class="field-label">নতুন কালেক্টর</label>
                        <select id="assign_collector" name="collector_id" class="field" required>
                            <option value="">— নির্বাচন করুন —</option>
                            @foreach ($collectors as $c)
                                @continue($c->id === $member->collector_id)
                                <option value="{{ $c->id }}" @selected(old('collector_id') == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        @error('collector_id', 'assign') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <x-field name="reason" label="কারণ (ঐচ্ছিক)" bag="assign" placeholder="যেমন: এলাকা পরিবর্তন" />
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn-quiet" @click="open = false">বাতিল</button>
                        <button type="submit" class="btn-primary">পরিবর্তন করুন</button>
                    </div>
                </form>
            </x-modal>

            <x-confirm :action="route('admin.members.status', $member)" method="PATCH"
                       :title="$member->isActive() ? 'সদস্য নিষ্ক্রিয় করবেন?' : 'সদস্য সক্রিয় করবেন?'"
                       :message="$member->isActive() ? 'নিষ্ক্রিয় সদস্যের কাছ থেকে নতুন টাকা সংগ্রহ করা যাবে না। পুরনো সব হিসাব থাকবে।' : 'সদস্য আবার সক্রিয় হবে।'"
                       :tone="$member->isActive() ? 'danger' : 'primary'"
                       :confirm-label="$member->isActive() ? 'হ্যাঁ, নিষ্ক্রিয় করুন' : 'হ্যাঁ, সক্রিয় করুন'"
                       class="btn-quiet">{{ $member->isActive() ? 'নিষ্ক্রিয় করুন' : 'সক্রিয় করুন' }}</x-confirm>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="lg:col-span-3">
            @include('members.partials.details')
            @include('members.partials.accounts')
        </div>

        <section class="panel h-fit overflow-hidden lg:col-span-2">
            <h2 class="border-b border-rule px-4 py-3 text-lg font-bold text-ledger-900">কালেক্টর দায়িত্বের ইতিহাস</h2>
            <ol>
                @forelse ($member->assignments as $a)
                    <li class="border-b border-rule px-4 py-3 last:border-b-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold">{{ $a->collector->name }}</span>
                            @if (is_null($a->assigned_to))
                                <x-status-badge tone="success">বর্তমান</x-status-badge>
                            @endif
                        </div>
                        <p class="text-sm text-ink-soft">
                            {{ bn_date($a->assigned_from) }} থেকে {{ $a->assigned_to ? bn_date($a->assigned_to) . ' পর্যন্ত' : 'এখন পর্যন্ত' }}
                        </p>
                        @if ($a->reason || $a->assigner)
                            <p class="text-sm text-ink-soft">{{ $a->reason }}@if ($a->reason && $a->assigner) — @endif{{ $a->assigner?->name }}</p>
                        @endif
                    </li>
                @empty
                    <li class="px-4 py-6 text-ink-soft">কোনো ইতিহাস নেই।</li>
                @endforelse
            </ol>
        </section>
    </div>
</x-layouts.app>
