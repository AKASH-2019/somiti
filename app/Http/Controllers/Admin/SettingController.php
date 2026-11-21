<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\AuditService;
use App\Services\Kisti\KistiScheduleService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    private const KEYS = ['org_name', 'org_address', 'org_mobile', 'share_value', 'weekly_off_days', 'receipt_footer'];

    public function edit(SettingService $settings): View
    {
        $values = collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => $settings->get($k)])->all();

        return view('admin.settings.edit', ['settings' => $values]);
    }

    public function update(UpdateSettingsRequest $request, SettingService $settings, AuditService $audit, KistiScheduleService $kisti): RedirectResponse
    {
        $data = $request->validated();
        $data['weekly_off_days'] = array_values(array_unique(array_map('intval', $data['weekly_off_days'] ?? [])));
        if (count($data['weekly_off_days']) >= 7) {
            return back()->withInput()->withErrors(['weekly_off_days' => 'সপ্তাহের সব দিন ছুটি হতে পারে না।']);
        }
        sort($data['weekly_off_days']);

        $old = [];
        $new = [];
        foreach (self::KEYS as $key) {
            $before = $settings->get($key);
            $after = $data[$key] ?? '';
            if ($key === 'share_value') {
                $after = number_format((float) $after, 2, '.', '');
            }
            if ($before != $after) {
                $old[$key] = $before;
                $new[$key] = $after;
                $settings->set($key, $after, $request->user()->id);
            }
        }

        if (array_key_exists('weekly_off_days', $new)) {
            // Unpaid future Kisti installments move off the new off-days.
            $new['kisti_installments_moved'] = $kisti->reflowAll();
        }
        if ($new) {
            $audit->log('settings.updated', null, $old, $new);
        }

        return back()->with('success', 'সেটিংস সংরক্ষণ করা হয়েছে।');
    }
}
