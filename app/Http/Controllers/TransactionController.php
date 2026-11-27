<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\Admin\ReverseTransactionRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Collection\CollectionException;
use App\Services\Collection\ReversalService;
use App\Services\Kisti\KistiStatementService;
use App\Services\Somiti\SomitiStatementService;
use App\Support\BanglaFormatter;
use App\Support\Money;
use App\Support\ReceiptPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** লেনদেন তালিকা ও রসিদ — serves admin.transactions.* and collector.transactions.* */
class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $from = $this->date($request->query('from')) ?? today();
        $to = $this->date($request->query('to')) ?? $from->copy();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $base = Transaction::query()->visibleTo($user)
            ->whereBetween('collected_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when(in_array($request->query('status'), ['completed', 'reversed'], true), fn ($q) => $q->where('status', $request->query('status')))
            ->when(in_array($request->query('type'), ['somiti', 'kisti'], true), fn ($q) => $q->whereHas('account', fn ($a) => $a->where('type', $request->query('type'))))
            ->when($user->isAdmin() && ctype_digit((string) $request->query('collector')), fn ($q) => $q->where('collector_id', $request->query('collector')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('receipt_no', 'like', '%'.addcslashes(BanglaFormatter::toAsciiDigits($request->query('q')), '%_\\').'%')
                ->orWhereHas('member', fn ($m) => $m->search($request->query('q')))));

        $transactions = (clone $base)->with(['member:id,member_code,name', 'account:id,account_no,type', 'collector:id,name'])
            ->orderByDesc('collected_at')->orderByDesc('id')->paginate(30)->withQueryString();

        $sum = (clone $base)->where('status', 'completed')->selectRaw('COUNT(*) as n, COALESCE(SUM(amount),0) as total')->first();

        return view('transactions.index', [
            'transactions' => $transactions,
            'from' => $from, 'to' => $to,
            'count' => (int) $sum->n,
            'total' => number_format((float) $sum->total, 2, '.', ''),
            'collectors' => $user->isAdmin() ? User::query()->cashHolders()->orderBy('name')->get(['id', 'name']) : collect(),
            'rp' => $this->rp($request),
        ]);
    }

    public function show(Request $request, Transaction $transaction): View
    {
        $this->authorize('view', $transaction);
        $transaction->load(['account.somitiDetail', 'account.kistiDetail', 'member', 'collector', 'allocations', 'reverser', 'creator']);

        $account = $transaction->account;
        $paidThrough = ReceiptPresenter::paidThrough($transaction);
        $balance = $account->type === AccountType::Somiti
            ? ['label' => 'এই রসিদ পর্যন্ত মোট সঞ্চয়', 'value' => Money::add($paidThrough, (string) ($account->somitiDetail->opening_balance ?? '0'))]
            : ['label' => 'এই রসিদের পর অবশিষ্ট ঋণ', 'value' => $this->kistiLeft($transaction, $paidThrough)];

        $settlement = \App\Models\KistiSettlement::query()->where('transaction_id', $transaction->id)->first();

        return view('transactions.show', [
            'tx' => $transaction,
            'covered' => ReceiptPresenter::covered($transaction),
            'balance' => $balance,
            'compact' => $request->query('layout') === 'compact',
            'settlement' => $settlement,
            'rp' => $this->rp($request),
        ]);
    }

    public function reverse(ReverseTransactionRequest $request, Transaction $transaction, ReversalService $service): RedirectResponse
    {
        try {
            $service->reverse($transaction, $request->validated('reason'), $request->user());
        } catch (CollectionException $e) {
            return back()->withErrors(['reason' => $e->getMessage()], 'reverse');
        }

        return back()->with('success', "রসিদ {$transaction->receipt_no} বাতিল করা হয়েছে। সংশ্লিষ্ট দিন/কিস্তি আবার বকেয়া হিসেবে দেখাবে।");
    }

    /** Loan left after this receipt; a settlement's waived amount counts as cleared from that receipt on. */
    private function kistiLeft(Transaction $tx, string $paidThrough): string
    {
        $left = Money::sub((string) $tx->account->kistiDetail->total_payable, $paidThrough);
        $settlement = \App\Models\KistiSettlement::query()->where('account_id', $tx->account_id)
            ->where('status', 'active')->where('transaction_id', '<=', $tx->id)->first();

        return $settlement ? Money::sub($left, (string) $settlement->waived_amount) : $left;
    }

    private function date(?string $value): ?Carbon
    {
        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? Carbon::parse($value)->startOfDay() : null;
    }

    private function rp(Request $request): string
    {
        return $request->user()->isAdmin() ? 'admin.transactions' : 'collector.transactions';
    }
}
