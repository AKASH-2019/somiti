{{-- Register-style member list. $routePrefix = 'admin.members' or 'collector.members'; $showCollector bool. --}}
<div class="panel overflow-hidden">
    @if ($members->isEmpty())
        <p class="px-5 py-8 text-center text-ink-soft">
            @if (request()->hasAny(['q', 'status', 'collector']))
                এই খোঁজের সাথে মেলে এমন কোনো সদস্য পাওয়া যায়নি।
            @else
                এখনো কোনো সদস্য নেই। <a href="{{ route($routePrefix . '.create') }}" class="font-semibold text-ledger-700 underline">নতুন সদস্য যোগ করুন</a>।
            @endif
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="register">
                <thead>
                <tr>
                    <th>ক্রমিক নং</th>
                    <th>সদস্য আইডি</th>
                    <th>নাম</th>
                    <th>প্রতিষ্ঠানের নাম/ঠিকানা</th>
                    <th>মোবাইল</th>
                    @if ($showCollector)<th>কালেক্টর</th>@endif
                    <th>যোগদান</th>
                    <th>অবস্থা</th>
                    <th class="no-print"></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($members as $member)
                    <tr>
                        <td>{{ bn_digits($members->firstItem() + $loop->index) }}</td>
                        <td class="whitespace-nowrap">{{ $member->member_code }}</td>
                        <td class="font-semibold">{{ $member->name }}</td>
                        <td>
                            {{ $member->business_name ?: '—' }}
                            @if ($member->business_address)
                                <span class="block text-sm text-ink-soft">{{ $member->business_address }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">{{ bn_digits($member->mobile) }}</td>
                        @if ($showCollector)<td>{{ $member->collector?->name ?? '—' }}</td>@endif
                        <td class="whitespace-nowrap">{{ bn_date($member->joined_at) }}</td>
                        <td><x-status-badge :tone="$member->status->tone()">{{ $member->status->label() }}</x-status-badge></td>
                        <td class="no-print text-right">
                            <a href="{{ route($routePrefix . '.show', $member) }}" class="text-sm font-semibold text-ledger-700 hover:underline">বিস্তারিত</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
{{ $members->links() }}
