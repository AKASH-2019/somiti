<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignCollectorRequest;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Member;
use App\Models\User;
use App\Services\Members\MemberAssignmentService;
use App\Services\Members\MemberOnboardingService;
use App\Services\Members\MemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $members = Member::query()
            ->with('collector:id,name')
            ->search($request->query('q'))
            ->when(in_array($request->query('status'), ['active', 'inactive'], true), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->query('collector') === 'none', fn ($q) => $q->whereNull('collector_id'))
            ->when(ctype_digit((string) $request->query('collector')), fn ($q) => $q->where('collector_id', $request->query('collector')))
            ->orderBy('member_code')
            ->paginate(25)->withQueryString();

        return view('admin.members.index', [
            'members' => $members,
            'collectors' => User::collectors()->orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }

    public function create(\App\Services\Members\MemberCodeService $codes, \App\Services\SettingService $settings): View
    {
        return view('admin.members.create', [
            'collectors' => User::collectors()->active()->orderBy('name')->get(['id', 'name']),
            'suggestedCode' => $codes->next(),
            'shareValue' => $settings->shareValue(),
        ]);
    }

    public function store(StoreMemberRequest $request, MemberOnboardingService $service): RedirectResponse
    {
        [$member, $account] = $service->create($request->validated(), $request->user());

        return redirect()->route('admin.members.show', $member)
            ->with('success', MemberOnboardingService::message($member, $account));
    }

    public function show(Member $member): View
    {
        return view('admin.members.show', [
            'member' => $member->load(['collector', 'creator', 'assignments.collector', 'assignments.assigner', 'accounts.somitiDetail', 'accounts.kistiDetail']),
            'collectors' => User::collectors()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Member $member): View
    {
        return view('admin.members.edit', compact('member'));
    }

    public function update(UpdateMemberRequest $request, Member $member, MemberService $service): RedirectResponse
    {
        $service->update($member, $request->validated(), $request->user());

        return redirect()->route('admin.members.show', $member)->with('success', 'সদস্যের তথ্য সংরক্ষণ করা হয়েছে।');
    }

    public function assign(AssignCollectorRequest $request, Member $member, MemberAssignmentService $service): RedirectResponse
    {
        $collector = User::query()->findOrFail($request->validated('collector_id'));
        $service->assign($member, $collector, $request->user(), $request->validated('reason'));

        return back()->with('success', "দায়িত্বপ্রাপ্ত কালেক্টর এখন {$collector->name}।");
    }

    public function toggleStatus(Request $request, Member $member, MemberService $service): RedirectResponse
    {
        $this->authorize('changeStatus', $member);
        $next = $member->isActive() ? MemberStatus::Inactive : MemberStatus::Active;
        $service->setStatus($member, $next, $request->user());

        return back()->with('success', "সদস্যের অবস্থা এখন: {$next->label()}।");
    }
}
