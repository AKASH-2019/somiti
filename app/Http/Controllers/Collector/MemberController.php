<?php

namespace App\Http\Controllers\Collector;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Member;
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
            ->visibleTo($request->user())
            ->search($request->query('q'))
            ->when(in_array($request->query('status'), ['active', 'inactive'], true), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('collector.members.index', compact('members'));
    }

    public function create(\App\Services\Members\MemberCodeService $codes, \App\Services\SettingService $settings): View
    {
        return view('collector.members.create', [
            'suggestedCode' => $codes->next(),
            'shareValue' => $settings->shareValue(),
        ]);
    }

    public function store(StoreMemberRequest $request, MemberOnboardingService $service): RedirectResponse
    {
        [$member, $account] = $service->create($request->validated(), $request->user());

        return redirect()->route('collector.members.show', $member)
            ->with('success', MemberOnboardingService::message($member, $account));
    }

    public function show(Member $member): View
    {
        $this->authorize('view', $member);

        return view('collector.members.show', ['member' => $member->load(['collector', 'creator', 'accounts.somitiDetail', 'accounts.kistiDetail'])]);
    }

    public function edit(Member $member): View
    {
        $this->authorize('update', $member);

        return view('collector.members.edit', compact('member'));
    }

    public function update(UpdateMemberRequest $request, Member $member, MemberService $service): RedirectResponse
    {
        $service->update($member, $request->validated(), $request->user());

        return redirect()->route('collector.members.show', $member)->with('success', 'সদস্যের তথ্য সংরক্ষণ করা হয়েছে।');
    }
}
