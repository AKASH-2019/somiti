<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->latest()->paginate(30),
        ]);
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $n = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $n->markAsRead();
        $role = $request->user()->isAdmin() ? 'admin' : 'collector';

        return isset($n->data['handover_id'])
            ? redirect()->route($role.'.handovers.show', $n->data['handover_id'])
            : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'সব বিজ্ঞপ্তি পড়া হয়েছে হিসেবে চিহ্নিত।');
    }
}
