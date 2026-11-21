<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\ChangeSharesRequest;
use App\Http\Requests\Admin\CorrectOpeningBalanceRequest;
use App\Http\Requests\StoreSomitiAccountRequest;
use App\Models\Account;
use App\Models\Member;
use App\Models\User;
use App\Services\SettingService;
use App\Services\Somiti\SomitiAccountService;
use App\Services\Somiti\SomitiScheduleService;
use App\Services\Somiti\SomitiShareChangeService;
use App\Services\Somiti\SomitiStatementService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Serves both admin.somiti.* and collector.somiti.* routes; every query is scoped with visibleTo(). */
class SomitiAccountController extends Controller
{
    public function __construct(private SomitiScheduleService $schedule) {}

    public function index(Request $request): View
    {
        $this->schedule->rollAll(today());
        $user = $request->user();

        $base = Account::query()->somiti()->visibleTo($user)
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('account_no', 'like', '%'.addcslashes(\App\Support\BanglaFormatter::toAsciiDigits($request->query('q')), '%_\\').'%')
                ->orWhereHas('member', fn ($m) => $m->search($request->query('q')))))
            ->when($user->isAdmin() && ctype_digit((string) $request->query('collector')),
                fn ($q) => $q->where('assigned_collector_id', $request->query('collector')));

        $accounts = (clone $base)->with(['member:id,member_code,name,business_name', 'collector:id,name', 'somitiDetail'])
            ->orderBy('account_no')->paginate(25)->withQueryString();

        $totals = (clone $base)->where('accounts.status', 'active')
            ->join('somiti_account_details as d', 'd.account_id', '=', 'accounts.id')
            ->selectRaw('COUNT(*) as accounts, COALESCE(SUM(d.number_of_shares),0) as shares, COALESCE(SUM(d.daily_deposit_amount),0) as daily')
            ->first();

        return view('somiti.index', [
            'accounts' => $accounts,
            'totals' => $totals,
            'collectors' => $user->isAdmin() ? User::collectors()->orderBy('name')->get(['id', 'name']) : collect(),
            'rp' => $this->routePrefix($request),
        ]);
    }

    public function create(Request $request, SettingService $settings): View
    {
        $members = Member::query()->visibleTo($request->user())->where('status', 'active')
            ->orderBy('name')->get(['id', 'member_code', 'name', 'business_name', 'joined_at']);

        return view('somiti.create', [
            'members' => $members,
            'shareValue' => $settings->shareValue(),
            'rp' => $this->routePrefix($request),
        ]);
    }

    public function store(StoreSomitiAccountRequest $request, SomitiAccountService $service): RedirectResponse
    {
        $member = Member::query()->findOrFail($request->validated('member_id'));

        try {
            $account = $service->open($member, (int) $request->validated('number_of_shares'),
                $request->date('start_date'), $request->user(), $request->validated('notes'),
                (string) ($request->validated('opening_balance') ?? '0'), $request->validated('opening_balance_note'));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['member_id' => $e->getMessage()]);
        }

        return redirect()->route($this->routePrefix($request).'.show', $account)
            ->with('success', "সমিতি অ্যাকাউন্ট {$account->account_no} খোলা হয়েছে। দৈনিক জমা ".bn_money($account->somitiDetail->daily_deposit_amount).'।');
    }

    public function show(Request $request, Account $account, SomitiStatementService $statement): View
    {
        abort_unless($account->type === \App\Enums\AccountType::Somiti, 404);
        $this->authorize('view', $account);
        $this->schedule->ensureThrough($account, today());

        $month = $this->month($request);
        $account->load(['member', 'collector', 'somitiDetail.openingBalanceUser', 'shareChanges.changer', 'creator']);

        return view('somiti.show', [
            'account' => $account,
            'summary' => $statement->summary($account),
            'month' => $month,
            'grid' => $statement->month([$account], $month),
            'rp' => $this->routePrefix($request),
            'recent' => \App\Models\Transaction::query()->where('account_id', $account->id)->with('collector:id,name')
                ->orderByDesc('collected_at')->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    public function changeShares(ChangeSharesRequest $request, Account $account, SomitiShareChangeService $service): RedirectResponse
    {
        try {
            $change = $service->change($account, (int) $request->validated('new_shares'),
                $request->date('effective_date'), $request->user(), $request->validated('reason'));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['new_shares' => $e->getMessage()], 'shares');
        }

        return back()->with('success', 'শেয়ার '.bn_digits($change->old_shares).' থেকে '.bn_digits($change->new_shares)
            .' করা হয়েছে, '.bn_date($change->effective_date).' থেকে কার্যকর। নতুন দৈনিক জমা '.bn_money($change->new_daily_amount).'।');
    }

    public function correctOpeningBalance(CorrectOpeningBalanceRequest $request, Account $account, SomitiAccountService $service): RedirectResponse
    {
        $service->correctOpeningBalance($account, (string) $request->validated('opening_balance'),
            $request->validated('reason'), $request->user());

        return back()->with('success', 'প্রারম্ভিক সঞ্চয় সংশোধন করা হয়েছে: '.bn_money($request->validated('opening_balance')).'।');
    }

    /** Digital version of the paper Somiti register, one month per sheet. */
    public function register(Request $request, SomitiStatementService $statement): View
    {
        $this->schedule->rollAll(today());
        $user = $request->user();
        $month = $this->month($request);

        $accounts = Account::query()->somiti()->visibleTo($user)
            ->where('accounts.status', 'active')
            ->whereHas('somitiDetail', fn ($q) => $q->whereDate('start_date', '<=', $month->copy()->endOfMonth()->toDateString()))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('member', fn ($m) => $m->search($request->query('q'))))
            ->when($user->isAdmin() && ctype_digit((string) $request->query('collector')),
                fn ($q) => $q->where('assigned_collector_id', $request->query('collector')))
            ->with(['member.accounts' => fn ($q) => $q->kisti()->where('status', 'active')->with('kistiDetail'),
                'somitiDetail', 'collector:id,name'])
            ->orderBy('account_no')->get();

        return view('somiti.register', [
            'accounts' => $accounts,
            'month' => $month,
            'grid' => $statement->month($accounts, $month),
            'collectors' => $user->isAdmin() ? User::collectors()->orderBy('name')->get(['id', 'name']) : collect(),
            'rp' => $this->routePrefix($request),
        ]);
    }

    private function month(Request $request): Carbon
    {
        $value = (string) $request->query('month');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)
            ? Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfDay()
            : today()->startOfMonth();
    }

    private function routePrefix(Request $request): string
    {
        return $request->user()->isAdmin() ? 'admin.somiti' : 'collector.somiti';
    }
}
