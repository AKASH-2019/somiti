<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\Collector;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\HandoverController;
use App\Http\Controllers\KistiAccountController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SomitiAccountController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard');

    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.change');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::get('/settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [Admin\SettingController::class, 'update'])->name('settings.update');

        Route::get('/holidays', [Admin\HolidayController::class, 'index'])->name('holidays.index');
        Route::post('/holidays', [Admin\HolidayController::class, 'store'])->name('holidays.store');
        Route::delete('/holidays/{holiday}', [Admin\HolidayController::class, 'destroy'])->name('holidays.destroy');

        // Collectors: no destroy route — collectors are deactivated, never deleted.
        Route::resource('collectors', Admin\CollectorController::class)->except('destroy');
        Route::put('/collectors/{collector}/password', [Admin\CollectorController::class, 'resetPassword'])->name('collectors.password');
        Route::patch('/collectors/{collector}/status', [Admin\CollectorController::class, 'toggleStatus'])->name('collectors.status');
        Route::post('/collectors/{collector}/transfer', [Admin\CollectorController::class, 'transferMembers'])->name('collectors.transfer');

        // Members: no destroy route — members are deactivated, never deleted.
        Route::resource('members', Admin\MemberController::class)->except('destroy');
        Route::post('/members/{member}/assign', [Admin\MemberController::class, 'assign'])->name('members.assign');
        Route::patch('/members/{member}/status', [Admin\MemberController::class, 'toggleStatus'])->name('members.status');

        Route::prefix('somiti')->name('somiti.')->controller(SomitiAccountController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/register', 'register')->name('register');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{account}', 'show')->name('show');
            Route::post('/{account}/shares', 'changeShares')->name('shares');
            Route::put('/{account}/opening-balance', 'correctOpeningBalance')->name('opening');
        });

        Route::prefix('kisti')->name('kisti.')->controller(KistiAccountController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{account}', 'show')->name('show');
            Route::post('/{account}/cancel', 'cancel')->name('cancel');
        });
        Route::prefix('collections')->name('collections.')->controller(CollectionController::class)->group(function () {
            Route::redirect('/', '/admin/collections/somiti')->name('index');
            Route::get('/somiti', 'somiti')->name('somiti');
            Route::get('/kisti', 'kisti')->name('kisti');
            Route::get('/{account}', 'create')->whereNumber('account')->name('create');
            Route::post('/{account}', 'store')->whereNumber('account')->name('store')->middleware('throttle:30,1');
        });

        Route::prefix('transactions')->name('transactions.')->controller(TransactionController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{transaction}', 'show')->name('show');
            Route::post('/{transaction}/reverse', 'reverse')->name('reverse');
        });

        Route::prefix('handovers')->name('handovers.')->controller(HandoverController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/incoming', 'incoming')->name('incoming');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store')->middleware('throttle:20,1');
            Route::get('/{handover}', 'show')->whereNumber('handover')->name('show');
            Route::post('/{handover}/confirm', 'confirm')->name('confirm');
            Route::post('/{handover}/reject', 'reject')->name('reject');
            Route::post('/{handover}/cancel', 'cancel')->name('cancel');
            Route::post('/{handover}/dispute', 'dispute')->name('dispute');
            Route::post('/{handover}/resolve', 'resolve')->name('resolve');
        });

        Route::get('/cash', [CashController::class, 'index'])->name('cash.index');
        Route::get('/cash/{holder}', [CashController::class, 'show'])->whereNumber('holder')->name('cash.show');
        Route::post('/cash/{holder}/adjust', [CashController::class, 'adjust'])->whereNumber('holder')->name('cash.adjust');

        Route::prefix('reports')->name('reports.')->controller(ReportController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{key}', 'show')->where('key', '[a-z-]+')->name('show');
            Route::get('/{key}/pdf', 'pdf')->where('key', '[a-z-]+')->name('pdf')->middleware('throttle:30,1');
            Route::get('/{key}/excel', 'excel')->where('key', '[a-z-]+')->name('excel')->middleware('throttle:30,1');
        });
    });

    Route::prefix('collector')->name('collector.')->middleware('role:collector')->group(function () {
        Route::get('/', Collector\DashboardController::class)->name('dashboard');
        Route::resource('members', Collector\MemberController::class)->except('destroy');

        Route::prefix('somiti')->name('somiti.')->controller(SomitiAccountController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/register', 'register')->name('register');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{account}', 'show')->name('show');
        });

        Route::prefix('kisti')->name('kisti.')->controller(KistiAccountController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{account}', 'show')->name('show');
        });
        Route::prefix('collections')->name('collections.')->controller(CollectionController::class)->group(function () {
            Route::redirect('/', '/collector/collections/somiti')->name('index');
            Route::get('/somiti', 'somiti')->name('somiti');
            Route::get('/kisti', 'kisti')->name('kisti');
            Route::get('/{account}', 'create')->whereNumber('account')->name('create');
            Route::post('/{account}', 'store')->whereNumber('account')->name('store')->middleware('throttle:30,1');
        });

        Route::prefix('transactions')->name('transactions.')->controller(TransactionController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{transaction}', 'show')->name('show');
        });

        Route::prefix('handovers')->name('handovers.')->controller(HandoverController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/incoming', 'incoming')->name('incoming');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store')->middleware('throttle:20,1');
            Route::get('/{handover}', 'show')->whereNumber('handover')->name('show');
            Route::post('/{handover}/confirm', 'confirm')->name('confirm');
            Route::post('/{handover}/reject', 'reject')->name('reject');
            Route::post('/{handover}/cancel', 'cancel')->name('cancel');
            Route::post('/{handover}/dispute', 'dispute')->name('dispute');
        });

        Route::get('/cash', [CashController::class, 'mine'])->name('cash.mine');

        Route::prefix('reports')->name('reports.')->controller(ReportController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{key}', 'show')->where('key', '[a-z-]+')->name('show');
            Route::get('/{key}/pdf', 'pdf')->where('key', '[a-z-]+')->name('pdf')->middleware('throttle:30,1');
            Route::get('/{key}/excel', 'excel')->where('key', '[a-z-]+')->name('excel')->middleware('throttle:30,1');
        });
    });
});
