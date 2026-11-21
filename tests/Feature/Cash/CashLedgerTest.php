<?php

namespace Tests\Feature\Cash;

use App\Enums\LedgerType;
use App\Models\AuditLog;
use App\Models\CashLedgerEntry;
use App\Models\User;
use App\Services\Cash\CashLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_collector_opening_cash_is_posted_to_ledger(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.collectors.store'), [
            'name' => 'রহিম', 'username' => 'rahim', 'mobile' => '01711223344',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'opening_cash' => '1500',
        ]);

        $rahim = User::query()->where('username', 'rahim')->firstOrFail();
        $this->assertSame('1500.00', app(CashLedgerService::class)->balance($rahim));
        $this->assertSame(LedgerType::Opening, CashLedgerEntry::query()->firstOrFail()->transaction_type);
    }

    public function test_only_admin_adjusts_cash_with_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $rahim = User::factory()->create();
        app(CashLedgerService::class)->credit($rahim, LedgerType::Collection, '1000', null, 'x');

        $this->actingAs($rahim)->post(route('admin.cash.adjust', $rahim), ['direction' => 'in', 'amount' => '100', 'reason' => 'x'])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.cash.adjust', $rahim), ['direction' => 'out', 'amount' => '200'])
            ->assertSessionHasErrorsIn('adjust', 'reason');
        $this->actingAs($admin)->post(route('admin.cash.adjust', $rahim), ['direction' => 'out', 'amount' => '২০০', 'reason' => 'গণনায় ভুল'])
            ->assertSessionHas('success');

        $this->assertSame('800.00', app(CashLedgerService::class)->balance($rahim));
        $this->assertTrue(AuditLog::query()->where('action', 'cash.adjusted')->exists());
    }

    public function test_ledger_rows_cannot_be_edited_or_deleted(): void
    {
        $rahim = User::factory()->create();
        $entry = app(CashLedgerService::class)->credit($rahim, LedgerType::Collection, '100', null, 'x');

        $this->expectException(\LogicException::class);
        $entry->update(['credit' => '999']);
    }

    public function test_cash_pages_render(): void
    {
        $admin = User::factory()->admin()->create();
        $rahim = User::factory()->create(['name' => 'রহিম']);
        app(CashLedgerService::class)->credit($rahim, LedgerType::Collection, '2500', null, 'রসিদ RCP-1');

        $this->actingAs($admin)->get(route('admin.cash.index'))->assertOk()->assertSee('রহিম')->assertSee('৳২,৫০০');
        $this->actingAs($admin)->get(route('admin.cash.show', $rahim))->assertOk()->assertSee('শেষ ব্যালেন্স');
        $this->actingAs($rahim)->get(route('collector.cash.mine'))->assertOk()->assertSee('আমার ক্যাশ হিসাব')->assertSee('৳২,৫০০');
        $this->actingAs($rahim)->get(route('admin.cash.index'))->assertForbidden();
    }
}
