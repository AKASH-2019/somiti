<?php

namespace App\Http\Controllers;

use App\Enums\HandoverStatus;
use App\Http\Requests\Cash\DisputeHandoverRequest;
use App\Http\Requests\Cash\RejectHandoverRequest;
use App\Http\Requests\Cash\ResolveHandoverRequest;
use App\Http\Requests\Cash\StoreHandoverRequest;
use App\Models\CashHandover;
use App\Models\User;
use App\Services\Cash\CashLedgerService;
use App\Services\Cash\HandoverService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** টাকা হস্তান্তর — serves admin.handovers.* and collector.handovers.* */
class HandoverController extends Controller
{
    public function __construct(private HandoverService $service, private CashLedgerService $ledger) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $status = in_array($request->query('status'), array_column(HandoverStatus::cases(), 'value'), true) ? $request->query('status') : null;

        $handovers = CashHandover::query()->visibleTo($user)
            ->with(['sender:id,name', 'receiver:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($user->isAdmin() && ctype_digit((string) $request->query('person')),
                fn ($q) => $q->involving(User::query()->findOrNew($request->query('person'))))
            ->orderByRaw("CASE status WHEN 'disputed' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderByDesc('handover_datetime')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('handovers.index', [
            'handovers' => $handovers,
            'status' => $status,
            'incoming' => $this->incomingFor($user),
            'balance' => $this->ledger->balance($user),
            'available' => $this->service->available($user),
            'people' => $user->isAdmin() ? User::query()->cashHolders()->orderBy('name')->get(['id', 'name']) : collect(),
            'rp' => $this->rp($request),
        ]);
    }

    public function incoming(Request $request): View
    {
        return view('handovers.incoming', [
            'incoming' => $this->incomingFor($request->user()),
            'recent' => CashHandover::query()->where('receiver_collector_id', $request->user()->id)
                ->whereNot('status', HandoverStatus::Pending->value)->with('sender:id,name')
                ->orderByDesc('updated_at')->limit(10)->get(),
            'rp' => $this->rp($request),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        return view('handovers.create', [
            'receivers' => User::query()->cashHolders()->active()->whereKeyNot($user->id)
                ->orderBy('role')->orderBy('name')->get(['id', 'name', 'role']),
            'balance' => $this->ledger->balance($user),
            'available' => $this->service->available($user),
            'rp' => $this->rp($request),
        ]);
    }

    public function store(StoreHandoverRequest $request): RedirectResponse
    {
        try {
            $h = $this->service->request($request->user(), User::query()->findOrFail($request->validated('receiver_id')),
                (string) $request->validated('amount'), $request->date('handover_datetime'), $request->validated('notes'));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route($this->rp($request).'.show', $h)
            ->with('success', "হস্তান্তরের অনুরোধ পাঠানো হয়েছে (নম্বর {$h->handover_number})। {$h->receiver->name} গ্রহণ নিশ্চিত করলে আপনার ক্যাশ থেকে টাকা বাদ যাবে।");
    }

    public function show(Request $request, CashHandover $handover): View
    {
        $this->authorize('view', $handover);
        $request->user()->unreadNotifications()->where('data->handover_id', $handover->id)->update(['read_at' => now()]);

        return view('handovers.show', [
            'h' => $handover->load(['sender', 'receiver', 'confirmer', 'disputer', 'resolver']),
            'rp' => $this->rp($request),
        ]);
    }

    public function confirm(Request $request, CashHandover $handover): RedirectResponse
    {
        $this->authorize('confirm', $handover);

        return $this->run(fn () => $this->service->confirm($handover, $request->user()),
            'টাকা গ্রহণ নিশ্চিত করা হয়েছে। '.bn_money($handover->amount).' আপনার ক্যাশে যোগ হয়েছে।', 'confirm');
    }

    public function reject(RejectHandoverRequest $request, CashHandover $handover): RedirectResponse
    {
        return $this->run(fn () => $this->service->reject($handover, $request->user(), $request->validated('reason')),
            'হস্তান্তর প্রত্যাখ্যান করা হয়েছে। কোনো টাকা স্থানান্তর হয়নি।', 'reject');
    }

    public function cancel(Request $request, CashHandover $handover): RedirectResponse
    {
        $this->authorize('cancel', $handover);

        return $this->run(fn () => $this->service->cancel($handover, $request->user()), 'হস্তান্তর অনুরোধ বাতিল করা হয়েছে।', 'cancel');
    }

    public function dispute(DisputeHandoverRequest $request, CashHandover $handover): RedirectResponse
    {
        return $this->run(fn () => $this->service->dispute($handover, $request->user(), $request->validated('reason')),
            'বিরোধ জানানো হয়েছে। এডমিন বিষয়টি তদন্ত করবেন।', 'dispute');
    }

    public function resolve(ResolveHandoverRequest $request, CashHandover $handover): RedirectResponse
    {
        $outcome = HandoverStatus::from($request->validated('outcome'));

        return $this->run(fn () => $this->service->resolve($handover, $request->user(), $outcome, $request->validated('note')),
            'বিরোধ নিষ্পত্তি হয়েছে: '.$outcome->label().'।', 'resolve');
    }

    private function run(callable $action, string $success, string $bag): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->withErrors(['reason' => $e->getMessage()], $bag)->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }

    private function incomingFor(User $user)
    {
        return CashHandover::query()->where('receiver_collector_id', $user->id)->where('status', HandoverStatus::Pending->value)
            ->with('sender:id,name')->orderBy('handover_datetime')->get();
    }

    private function rp(Request $request): string
    {
        return $request->user()->isAdmin() ? 'admin.handovers' : 'collector.handovers';
    }
}
