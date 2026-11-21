<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetPasswordRequest;
use App\Http\Requests\Admin\StoreCollectorRequest;
use App\Http\Requests\Admin\TransferMembersRequest;
use App\Http\Requests\Admin\UpdateCollectorRequest;
use App\Models\User;
use App\Services\CollectorService;
use App\Services\Members\MemberAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectorController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $collectors = User::collectors()
            ->withCount('members')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.addcslashes(\App\Support\BanglaFormatter::toAsciiDigits($request->query('q')), '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('username', 'like', $term)->orWhere('mobile', 'like', $term));
            })
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('admin.collectors.index', compact('collectors', 'status'));
    }

    public function create(): View
    {
        return view('admin.collectors.create');
    }

    public function store(StoreCollectorRequest $request, CollectorService $service): RedirectResponse
    {
        $collector = $service->create($request->validated(), $request->user());

        return redirect()->route('admin.collectors.show', $collector)
            ->with('success', "কালেক্টর {$collector->name} তৈরি করা হয়েছে। ইউজারনেম: {$collector->username}");
    }

    public function show(Request $request, User $collector): View
    {
        $this->ensureCollector($collector);

        return view('admin.collectors.show', [
            'collector' => $collector->loadCount('members'),
            'members' => $collector->members()->orderBy('name')->paginate(20),
            'otherCollectors' => User::collectors()->active()->whereKeyNot($collector->id)->orderBy('name')->get(),
        ]);
    }

    public function edit(User $collector): View
    {
        $this->ensureCollector($collector);

        return view('admin.collectors.edit', compact('collector'));
    }

    public function update(UpdateCollectorRequest $request, User $collector, CollectorService $service): RedirectResponse
    {
        $this->ensureCollector($collector);
        $service->update($collector, $request->validated(), $request->user());

        return redirect()->route('admin.collectors.show', $collector)->with('success', 'কালেক্টরের তথ্য সংরক্ষণ করা হয়েছে।');
    }

    public function resetPassword(ResetPasswordRequest $request, User $collector, CollectorService $service): RedirectResponse
    {
        $this->ensureCollector($collector);
        $service->resetPassword($collector, $request->validated('password'), $request->user());

        return back()->with('success', "{$collector->name}-এর পাসওয়ার্ড পরিবর্তন করা হয়েছে। নতুন পাসওয়ার্ডটি তাকে জানিয়ে দিন।");
    }

    public function toggleStatus(Request $request, User $collector, CollectorService $service): RedirectResponse
    {
        $this->ensureCollector($collector);
        $activate = ! $collector->is_active;
        $service->setActive($collector, $activate, $request->user());

        if ($activate) {
            return back()->with('success', "{$collector->name} এখন সক্রিয়।");
        }

        $count = $collector->members()->count();
        $message = "{$collector->name} নিষ্ক্রিয় করা হয়েছে। তিনি আর লগইন করতে পারবেন না।";
        if ($count > 0) {
            $message .= ' তার অধীনে '.bn_digits($count).' জন সদস্য আছে — নিচের ফর্ম থেকে তাদের অন্য কালেক্টরের কাছে স্থানান্তর করুন।';
        }

        return back()->with($count > 0 ? 'error' : 'success', $message);
    }

    public function transferMembers(TransferMembersRequest $request, User $collector, MemberAssignmentService $service): RedirectResponse
    {
        $this->ensureCollector($collector);
        $to = User::query()->findOrFail($request->validated('to_collector_id'));
        $count = $service->transferAll($collector, $to, $request->user(), $request->validated('reason'));

        return back()->with('success', bn_digits($count)." জন সদস্য {$to->name}-এর কাছে স্থানান্তর করা হয়েছে।");
    }

    /** These routes manage collectors only — never another admin. */
    private function ensureCollector(User $user): void
    {
        abort_unless($user->isCollector(), 404);
    }
}
