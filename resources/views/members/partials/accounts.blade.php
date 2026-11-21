{{-- Member's accounts, shown on admin and collector member pages. $member->accounts must be loaded. --}}
@php($role = auth()->user()->isAdmin() ? 'admin' : 'collector')
<section class="panel mt-6 overflow-hidden">
    <div class="flex items-center justify-between border-b border-rule px-5 py-3">
        <h2 class="text-lg font-bold text-ledger-900">অ্যাকাউন্টসমূহ</h2>
        @if ($member->isActive())
            <div class="flex flex-wrap gap-2">
                <a href="{{ route($role . '.somiti.create', ['member' => $member->id]) }}" class="btn-primary px-3 py-1.5 text-sm">নতুন সমিতি অ্যাকাউন্ট</a>
                <a href="{{ route($role . '.kisti.create', ['member' => $member->id]) }}" class="btn-quiet px-3 py-1.5 text-sm">নতুন কিস্তি অ্যাকাউন্ট</a>
            </div>
        @endif
    </div>
    @if ($member->accounts->isEmpty())
        <p class="px-5 py-6 text-ink-soft">এই সদস্যের কোনো অ্যাকাউন্ট নেই।</p>
    @else
        <div class="overflow-x-auto">
            <table class="register">
                <thead><tr><th>অ্যাকাউন্ট নম্বর</th><th>ধরন</th><th>বিবরণ</th><th>অবস্থা</th><th></th></tr></thead>
                <tbody>
                @foreach ($member->accounts as $a)
                    <tr>
                        <td class="font-semibold">{{ $a->account_no }}</td>
                        <td>{{ $a->type->label() }}</td>
                        <td>
                            @if ($a->somitiDetail)
                                {{ bn_digits($a->somitiDetail->number_of_shares) }} শেয়ার, দৈনিক {{ bn_money($a->somitiDetail->daily_deposit_amount) }}
                            @elseif ($a->kistiDetail)
                                ঋণ {{ bn_money($a->kistiDetail->loan_amount) }} — {{ $a->kistiDetail->frequency->label() }} {{ bn_money($a->kistiDetail->installment_amount) }} × {{ bn_digits($a->kistiDetail->total_installments) }}
                            @endif
                        </td>
                        <td><x-status-badge :tone="$a->isActive() ? 'success' : 'muted'">{{ $a->status->label() }}</x-status-badge></td>
                        <td class="text-right">
                            <a href="{{ route($role . '.' . $a->type->value . '.show', $a) }}" class="text-sm font-semibold text-ledger-700 hover:underline">বিস্তারিত</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
