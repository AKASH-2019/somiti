<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\KistiFrequency;
use App\Http\Requests\Admin\CancelKistiRequest;
use App\Http\Requests\StoreKistiAccountRequest;
use App\Models\Account;
use App\Models\Member;
use App\Models\User;
use App\Services\Kisti\KistiAccountService;
use App\Services\Kisti\KistiStatementService;
use App\Support\Money;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Serves both admin.kisti.* and collector.kisti.* routes; queries are scoped with visibleTo(). */
class KistiAccountController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $status = in_array($request->query('status'), ['active', 'closed', 'settled', 'cancelled'], true) ? $request->query('status') : 'active';

        $base = Account::query()->kisti()->visibleTo($user)->where('accounts.status', $status)
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('account_no', 'like', '%'.addcslashes(\App\Support\BanglaFormatter::toAsciiDigits($request->query('q')), '%_\\').'%')
                ->orWhereHas('member', fn ($m) => $m->search($request->query('q')))))
            ->when(in_array($request->query('frequency'), ['daily', 'weekly'], true),
                fn ($q) => $q->whereHas('kistiDetail', fn ($d) => $d->where('frequency', $request->query('frequency'))))
            ->when($user->isAdmin() && ctype_digit((string) $request->query('collector')),
                fn ($q) => $q->where('assigned_collector_id', $request->query('collector')));

        $accounts = (clone $base)->with(['member:id,member_code,name,business_name', 'collector:id,name', 'kistiDetail'])
            ->withSum('installments as paid_sum', 'amount_paid')
            ->withSum('installments as waived_sum', 'amount_waived')
            ->orderByDesc('accounts.id')->paginate(25)->withQueryString();

        $ids = (clone $base)->pluck('accounts.id');
        $totals = [
            'count' => $ids->count(),
            'loan' => (string) DB::table('kisti_account_details')->whereIn('account_id', $ids)->sum('loan_amount'),
            'payable' => (string) DB::table('kisti_account_details')->whereIn('account_id', $ids)->sum('total_payable'),
            'paid' => (string) DB::table('installments')->whereIn('account_id', $ids)->sum('amount_paid'),
            'waived' => (string) DB::table('installments')->whereIn('account_id', $ids)->sum('amount_waived'),
        ];
        $totals = array_map(fn ($v) => is_int($v) ? $v : number_format((float) $v, 2, '.', ''), $totals);
        $totals['remaining'] = Money::sub(Money::sub($totals['payable'], $totals['paid']), $totals['waived']);

        return view('kisti.index', [
            'accounts' => $accounts,
            'totals' => $totals,
            'status' => $status,
            'collectors' => $user->isAdmin() ? User::collectors()->orderBy('name')->get(['id', 'name']) : collect(),
            'rp' => $this->routePrefix($request),
        ]);
    }

    public function create(Request $request, \App\Services\Members\MemberCodeService $codes): View
    {
        return view('kisti.create', [
            'members' => Member::query()->visibleTo($request->user())->where('status', 'active')
                ->orderBy('name')->get(['id', 'member_code', 'name', 'business_name']),
            'collectors' => $request->user()->isAdmin() ? User::collectors()->active()->orderBy('name')->get(['id', 'name']) : null,
            'suggestedCode' => $codes->next(),
            'rp' => $this->routePrefix($request),
        ]);
    }

    public function store(StoreKistiAccountRequest $request, KistiAccountService $service, \App\Services\Members\MemberService $members): RedirectResponse
    {
        try {
            $account = DB::transaction(function () use ($request, $service, $members) {
                $member = $request->isNewMember()
                    ? $members->create($request->memberData(), $request->user())
                    : Member::query()->findOrFail($request->validated('member_id'));

                return $service->open($member, [
                    ...$request->validated(),
                    'start_date' => $request->date('start_date'),
                    'first_installment_date' => $request->date('first_installment_date'),
                ], $request->user());
            });
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['installment_amount' => $e->getMessage()]);
        }
        $d = $account->kistiDetail;

        $prefix = $request->isNewMember() ? "নতুন সদস্য {$account->member->name} ({$account->member->member_code}) যোগ হয়েছে। " : '';

        return redirect()->route($this->routePrefix($request).'.show', $account)
            ->with('success', $prefix."কিস্তি অ্যাকাউন্ট {$account->account_no} খোলা হয়েছে: ".bn_digits($d->total_installments).'টি '
                .$d->frequency->label().' কিস্তি, প্রতিটি '.bn_money($d->installment_amount).'।');
    }

    public function show(Request $request, Account $account, KistiStatementService $statement): View
    {
        abort_unless($account->type === AccountType::Kisti, 404);
        $this->authorize('view', $account);
        $account->load(['member', 'collector', 'kistiDetail', 'creator']);

        return view('kisti.show', [
            'account' => $account,
            'summary' => $statement->summary($account),
            'settlement' => $account->settlements()->with(['transaction', 'receiver', 'settler'])->where('status', 'active')->first(),
            'receivers' => User::query()->active()->where(fn ($q) => $q->where('role', 'admin')->orWhere('id', $account->assigned_collector_id))
                ->orderByRaw('id = ? desc', [$account->assigned_collector_id ?? 0])->get(['id', 'name', 'role']),
            'rp' => $this->routePrefix($request),
            'recent' => \App\Models\Transaction::query()->where('account_id', $account->id)->with('collector:id,name')
                ->orderByDesc('collected_at')->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    public function cancel(CancelKistiRequest $request, Account $account, KistiAccountService $service): RedirectResponse
    {
        try {
            $service->cancel($account, $request->validated('reason'), $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['reason' => $e->getMessage()], 'cancel');
        }

        return back()->with('success', "ঋণ {$account->account_no} বাতিল করা হয়েছে।");
    }

    public function settle(\App\Http\Requests\Admin\SettleKistiRequest $request, Account $account, \App\Services\Kisti\KistiSettlementService $service): RedirectResponse
    {
        try {
            $settlement = $service->settle($account, (string) $request->validated('amount'),
                User::query()->findOrFail($request->validated('receiver_id')), $request->validated('reason'), $request->user());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()], 'settle');
        }

        return redirect()->route('admin.transactions.show', $settlement->transaction_id)
            ->with('success', 'সেটেলমেন্ট সম্পন্ন: '.bn_money($settlement->settled_amount).' গ্রহণ, '
                .bn_money($settlement->waived_amount).' মওকুফ। ঋণ '.$account->account_no.' বন্ধ হয়েছে।');
    }

    private function routePrefix(Request $request): string
    {
        return $request->user()->isAdmin() ? 'admin.kisti' : 'collector.kisti';
    }
}
