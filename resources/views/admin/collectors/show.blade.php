<x-layouts.app :title="$collector->name">
    <x-page-header :title="$collector->name" :description="'ইউজারনেম: ' . $collector->username">
        <x-slot:actions>
            <a href="{{ route('admin.collectors.edit', $collector) }}" class="btn-quiet">সম্পাদনা</a>

            <x-modal title="পাসওয়ার্ড রিসেট" :open="$errors->resetPassword->any()">
                <x-slot:trigger><button type="button" class="btn-quiet">পাসওয়ার্ড রিসেট</button></x-slot:trigger>
                <form method="POST" action="{{ route('admin.collectors.password', $collector) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <p class="text-[15px] text-ink-soft">নতুন পাসওয়ার্ড সেট হলে {{ $collector->name }} সব ডিভাইস থেকে লগআউট হয়ে যাবেন।</p>
                    <x-field name="password" label="নতুন পাসওয়ার্ড" type="password" bag="resetPassword" required autocomplete="new-password" help="কমপক্ষে ৮ অক্ষর" />
                    <x-field name="password_confirmation" label="নতুন পাসওয়ার্ড আবার লিখুন" type="password" bag="resetPassword" required autocomplete="new-password" />
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn-quiet" @click="open = false">বাতিল</button>
                        <button type="submit" class="btn-primary">পাসওয়ার্ড সেট করুন</button>
                    </div>
                </form>
            </x-modal>

            <x-confirm :action="route('admin.collectors.status', $collector)" method="PATCH"
                       :title="$collector->is_active ? 'কালেক্টর নিষ্ক্রিয় করবেন?' : 'কালেক্টর সক্রিয় করবেন?'"
                       :message="$collector->is_active ? 'নিষ্ক্রিয় কালেক্টর লগইন করতে পারবেন না এবং সাথে সাথে লগআউট হয়ে যাবেন। তার সব হিসাব সংরক্ষিত থাকবে।' : 'কালেক্টর আবার লগইন করতে পারবেন।'"
                       :tone="$collector->is_active ? 'danger' : 'primary'"
                       :confirm-label="$collector->is_active ? 'হ্যাঁ, নিষ্ক্রিয় করুন' : 'হ্যাঁ, সক্রিয় করুন'"
                       class="btn-quiet">{{ $collector->is_active ? 'নিষ্ক্রিয় করুন' : 'সক্রিয় করুন' }}</x-confirm>
        </x-slot:actions>
    </x-page-header>

    <section class="panel mb-6 grid grid-cols-2 lg:grid-cols-4 lg:divide-x lg:divide-rule">
        <x-stat label="অবস্থা" :value="$collector->is_active ? 'সক্রিয়' : 'নিষ্ক্রিয়'" />
        <x-stat label="মোবাইল" :value="bn_digits($collector->mobile)" />
        <x-stat label="দায়িত্বে থাকা সদস্য" :value="bn_digits($collector->members_count)" />
        <x-stat label="শেষ লগইন" :value="$collector->last_login_at ? bn_date($collector->last_login_at) : 'কখনো না'" />
    </section>

    @if ($collector->members_count > 0)
        <section class="panel mb-6 p-5">
            <h2 class="text-lg font-bold text-ledger-900">সব সদস্য অন্য কালেক্টরের কাছে স্থানান্তর</h2>
            <p class="field-help mb-4">কালেক্টর চাকরি ছাড়লে বা এলাকা বদলালে তার {{ bn_digits($collector->members_count) }} জন সদস্যকে একসাথে অন্য কালেক্টরের দায়িত্বে দিন। প্রত্যেক সদস্যের ইতিহাসে এটি লেখা থাকবে।</p>
            @if ($otherCollectors->isEmpty())
                <p class="text-ink-soft">স্থানান্তরের জন্য অন্য কোনো সক্রিয় কালেক্টর নেই।</p>
            @else
                <form method="POST" action="{{ route('admin.collectors.transfer', $collector) }}" class="flex flex-wrap items-end gap-3"
                      x-data @submit="if (!confirm('{{ bn_digits($collector->members_count) }} জন সদস্য স্থানান্তর করবেন?')) $event.preventDefault()">
                    @csrf
                    <div>
                        <label for="to_collector_id" class="field-label">নতুন কালেক্টর</label>
                        <select id="to_collector_id" name="to_collector_id" class="field w-auto" required>
                            <option value="">— নির্বাচন করুন —</option>
                            @foreach ($otherCollectors as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-field name="reason" label="কারণ (ঐচ্ছিক)" bag="transfer" class="min-w-56 flex-1" />
                    <button type="submit" class="btn-primary">স্থানান্তর করুন</button>
                </form>
                @error('to_collector_id', 'transfer') <p class="field-error">{{ $message }}</p> @enderror
            @endif
        </section>
    @endif

    <h2 class="mb-3 text-lg font-bold text-ledger-900">দায়িত্বে থাকা সদস্য</h2>
    <div class="panel overflow-hidden">
        @if ($members->isEmpty())
            <p class="px-5 py-6 text-ink-soft">এই কালেক্টরের দায়িত্বে কোনো সদস্য নেই।</p>
        @else
            <div class="overflow-x-auto">
                <table class="register">
                    <thead><tr><th>সদস্য আইডি</th><th>নাম</th><th>প্রতিষ্ঠান</th><th>মোবাইল</th><th>অবস্থা</th></tr></thead>
                    <tbody>
                    @foreach ($members as $m)
                        <tr>
                            <td><a href="{{ route('admin.members.show', $m) }}" class="text-ledger-700 hover:underline">{{ $m->member_code }}</a></td>
                            <td class="font-semibold">{{ $m->name }}</td>
                            <td>{{ $m->business_name ?: '—' }}</td>
                            <td class="whitespace-nowrap">{{ bn_digits($m->mobile) }}</td>
                            <td><x-status-badge :tone="$m->status->tone()">{{ $m->status->label() }}</x-status-badge></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    {{ $members->links() }}
</x-layouts.app>
