<?php

namespace Tests\Feature\Reports;

use App\Models\Account;
use App\Models\Member;
use App\Models\User;
use App\Reports\ReportRegistry;
use App\Services\Cash\HandoverService;
use App\Services\Collection\CollectionService;
use App\Services\Kisti\KistiAccountService;
use App\Services\Somiti\SomitiAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $rahim;

    private User $karim;

    private Account $somiti;

    private Account $kisti;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->admin = User::factory()->admin()->create();
        $this->rahim = User::factory()->create(['name' => 'রহিম']);
        $this->karim = User::factory()->create(['name' => 'করিম']);

        $m1 = Member::factory()->create(['collector_id' => $this->rahim->id, 'joined_at' => '2026-01-01', 'name' => 'রহিমের সদস্য']);
        $m2 = Member::factory()->create(['collector_id' => $this->karim->id, 'joined_at' => '2026-01-01', 'name' => 'করিমের সদস্য']);
        $this->somiti = app(SomitiAccountService::class)->open($m1, 5, Carbon::parse('2026-11-16'), $this->admin);
        $karimSomiti = app(SomitiAccountService::class)->open($m2, 2, Carbon::parse('2026-11-16'), $this->admin);
        $this->kisti = app(KistiAccountService::class)->open($m1, [
            'frequency' => 'daily', 'loan_amount' => '1000', 'service_charge' => '200', 'installment_amount' => '200',
            'start_date' => Carbon::parse('2026-11-14'), 'first_installment_date' => Carbon::parse('2026-11-16'),
        ], $this->admin);

        $c = app(CollectionService::class);
        $c->collect($this->somiti, $this->rahim, ['2026-11-16', '2026-11-17', '2026-11-18'], '300', (string) Str::uuid());
        $c->collect($this->somiti, $this->rahim, ['2026-11-25'], '100', (string) Str::uuid()); // advance
        $c->collect($this->kisti, $this->rahim, $this->kisti->installments()->where('sequence_no', 1)->pluck('id')->map(fn ($i) => (string) $i)->all(), '200', (string) Str::uuid());
        $c->collect($karimSomiti, $this->karim, ['2026-11-16'], '40', (string) Str::uuid());

        app(HandoverService::class)->request($this->rahim, $this->karim, '100', now());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_open_all_thirteen_reports(): void
    {
        $this->assertCount(13, ReportRegistry::for($this->admin));
        $this->actingAs($this->admin)->get(route('admin.reports.index'))->assertOk()->assertSee('দৈনিক ক্যাশ ক্লোজিং রিপোর্ট');

        foreach (array_keys(ReportRegistry::for($this->admin)) as $key) {
            $this->actingAs($this->admin)->get(route('admin.reports.show', $key))->assertOk();
        }
    }

    public function test_daily_somiti_collection_totals(): void
    {
        $this->actingAs($this->admin)->get(route('admin.reports.show', 'daily-somiti'))
            ->assertOk()->assertSee('৳৪৪০')->assertSee('১৬ – ১৮ নভেম্বর ২০২৬ (৩ দিন)');
        $this->actingAs($this->admin)->get(route('admin.reports.show', ['daily-somiti', 'collector' => $this->karim->id]))
            ->assertOk()->assertSee('৳৪০')->assertDontSee('রহিমের সদস্য');
    }

    public function test_collector_reports_are_limited_to_own_data(): void
    {
        $keys = array_keys(ReportRegistry::for($this->rahim));
        $this->assertContains('daily-somiti', $keys);
        $this->assertNotContains('collector-balance', $keys);

        // Even when asking for Karim, Rahim only sees his own.
        $this->actingAs($this->rahim)->get(route('collector.reports.show', ['member-accounts', 'collector' => $this->karim->id]))
            ->assertOk()->assertSee('রহিমের সদস্য')->assertDontSee('করিমের সদস্য');
        $this->actingAs($this->rahim)->get(route('collector.reports.show', 'collector-balance'))->assertNotFound();
        $this->actingAs($this->rahim)->get(route('admin.reports.show', 'daily-somiti'))->assertForbidden();
    }

    public function test_cash_closing_and_balance_figures(): void
    {
        // Rahim collected 300 + 100 + 200 = 600; Karim 40; the handover is still pending, so nothing moved.
        $this->actingAs($this->admin)->get(route('admin.reports.show', 'daily-cash-closing'))
            ->assertOk()->assertSee('৳৬৪০')->assertSee('অপেক্ষমাণ হস্তান্তর');
        $this->actingAs($this->admin)->get(route('admin.reports.show', 'collector-balance'))->assertOk()->assertSee('৳৬০০');
    }

    public function test_advance_dues_and_shares_reports(): void
    {
        $this->actingAs($this->admin)->get(route('admin.reports.show', 'advance'))->assertOk()->assertSee($this->somiti->account_no)->assertSee('৳১০০');
        $this->actingAs($this->admin)->get(route('admin.reports.show', 'kisti-dues'))->assertOk()->assertSee($this->kisti->account_no);
        $this->actingAs($this->admin)->get(route('admin.reports.show', 'somiti-shares'))->assertOk()->assertSee('৫ শেয়ার');
    }

    public function test_excel_export(): void
    {
        Excel::fake();
        $this->actingAs($this->admin)->get(route('admin.reports.excel', ['monthly', 'month' => '2026-11']))->assertOk();
        Excel::assertDownloaded('monthly_2026-11.xlsx');
    }

    public function test_pdf_export_is_a_pdf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.pdf', 'daily-somiti'));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_dashboards_show_todays_figures(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('আজকের মোট সংগ্রহ')->assertSee('৳৬৪০')->assertSee('আজকের কালেক্টর কার্যক্রম');
        $this->actingAs($this->karim)->get(route('collector.dashboard'))
            ->assertOk()->assertSee('৳৪০')->assertSee('টাকা গ্রহণ নিশ্চিত করুন'); // Rahim's pending handover to Karim
    }
}
