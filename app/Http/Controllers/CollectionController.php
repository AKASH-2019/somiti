<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\EntryType;
use App\Http\Requests\StoreCollectionRequest;
use App\Models\Account;
use App\Models\Installment;
use App\Models\Transaction;
use App\Services\Cash\CashLedgerService;
use App\Services\Collection\CollectionException;
use App\Services\Collection\CollectionOptionsService;
use App\Services\Collection\CollectionService;
use App\Services\Somiti\SomitiScheduleService;
use App\Support\BanglaFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** টাকা সংগ্রহ — serves admin.collections.* and collector.collections.* */
class CollectionController extends Controller
{
    public function somiti(Request $request, SomitiScheduleService $schedule, CashLedgerService $ledger): View
    {
        return $this->listing($request, AccountType::Somiti, $schedule, $ledger);
    }

    public function kisti(Request $request, SomitiScheduleService $schedule, CashLedgerService $ledger): View
    {
        return $this->listing($request, AccountType::Kisti, $schedule, $ledger);
    }

    private function listing(Request $request, AccountType $type, SomitiScheduleService $schedule, CashLedgerService $ledger): View
    {
        $schedule->rollAll(today());
        $user = $request->user();

        $accounts = Account::query()->visibleTo($user)->where('accounts.status', 'active')
            ->whereHas('member', fn ($m) => $m->where('status', 'active'))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('account_no', 'like', '%'.addcslashes(BanglaFormatter::toAsciiDigits($request->query('q')), '%_\\').'%')
                ->orWhereHas('member', fn ($m) => $m->search($request->query('q')))))
            ->where('type', $type->value)
            ->with(['member:id,member_code,name,business_name,mobile', 'somitiDetail', 'kistiDetail'])
            ->orderBy('member_id')->orderBy('account_no')
            ->paginate(30)->withQueryString();

        $dues = Installment::query()->whereIn('account_id', $accounts->pluck('id'))
            ->whereDate('due_date', '<=', today()->toDateString())
            ->whereColumn('amount_paid', '<', 'amount_due')
            ->groupBy('account_id')
            ->selectRaw('account_id, SUM(amount_due - amount_paid) as due, COUNT(*) as n')
            ->get()->keyBy('account_id');

        $today = Transaction::query()->where('collector_id', $user->id)->where('status', 'completed')
            ->where('entry_type', EntryType::Cash->value)->whereDate('collected_at', today()->toDateString())
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(amount),0) as total')->first();

        return view('collections.index', [
            'type' => $type,
            'accounts' => $accounts,
            'dues' => $dues,
            'todayCount' => (int) $today->n,
            'todayTotal' => number_format((float) $today->total, 2, '.', ''),
            'cashBalance' => $ledger->balance($user),
            'rp' => $this->rp($request),
        ]);
    }

    public function create(Request $request, Account $account, CollectionOptionsService $options): View
    {
        $this->authorize('collect', $account);
        $account->load(['member', 'somitiDetail', 'kistiDetail', 'collector']);

        return view('collections.create', [
            'account' => $account,
            'items' => $options->forAccount($account),
            'idempotencyKey' => old('idempotency_key', (string) Str::uuid()),
            'rp' => $this->rp($request),
        ]);
    }

    public function store(StoreCollectionRequest $request, Account $account, CollectionService $service): RedirectResponse
    {
        $data = $request->validated();
        $entryType = EntryType::from($data['entry_type']);

        try {
            $tx = $service->collect(
                $account, $request->user(), $data['keys'], (string) $data['amount'], $data['idempotency_key'],
                $data['notes'] ?? null, $entryType,
                $entryType === EntryType::Historical ? $request->date('collected_at')?->setTimeFrom(now()) : null,
            );
        } catch (CollectionException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        $route = ($request->user()->isAdmin() ? 'admin' : 'collector').'.transactions.show';
        if (! $tx->wasRecentlyCreated) {
            return redirect()->route($route, $tx)->with('success', 'এই সংগ্রহটি আগেই সংরক্ষিত হয়েছে — একই টাকা দ্বিতীয়বার নেওয়া হয়নি।');
        }

        return redirect()->route($route, $tx)
            ->with('success', bn_money($tx->amount).' জমা নেওয়া হয়েছে। রসিদ নম্বর '.$tx->receipt_no.'।');
    }

    private function rp(Request $request): string
    {
        return $request->user()->isAdmin() ? 'admin.collections' : 'collector.collections';
    }
}
