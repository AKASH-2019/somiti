<?php

namespace Tests\Feature\Cash;

use App\Enums\HandoverStatus;
use App\Enums\LedgerType;
use App\Models\CashHandover;
use App\Models\CashLedgerEntry;
use App\Models\User;
use App\Notifications\HandoverNotification;
use App\Services\Cash\CashLedgerService;
use App\Services\Cash\HandoverService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HandoverTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $rahim;

    private User $karim;

    private CashLedgerService $ledger;

    private HandoverService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['name' => 'এডমিন']);
        $this->rahim = User::factory()->create(['name' => 'রহিম']);
        $this->karim = User::factory()->create(['name' => 'করিম']);
        $this->ledger = app(CashLedgerService::class);
        $this->service = app(HandoverService::class);

        // Brief's starting position: রহিম ৳10,000, করিম ৳2,000
        $this->ledger->credit($this->rahim, LedgerType::Collection, '10000', null, 'সংগ্রহ');
        $this->ledger->credit($this->karim, LedgerType::Collection, '2000', null, 'সংগ্রহ');
    }

    private function balance(User $u): string
    {
        return $this->ledger->balance($u);
    }

    public function test_brief_scenario_end_to_end_over_http(): void
    {
        $this->actingAs($this->rahim)->post(route('collector.handovers.store'), [
            'receiver_id' => $this->karim->id, 'amount' => '১০,০০০', 'handover_datetime' => now()->format('Y-m-d H:i'), 'notes' => 'আজকের সংগ্রহ',
        ])->assertRedirect()->assertSessionHas('success');

        $h = CashHandover::query()->firstOrFail();
        $this->assertSame('HO-'.now()->year.'-000001', $h->handover_number);
        $this->assertSame(HandoverStatus::Pending, $h->status);
        // Pending: nothing moves.
        $this->assertSame('10000.00', $this->balance($this->rahim));
        $this->assertSame('2000.00', $this->balance($this->karim));
        $this->assertSame('0.00', $this->service->available($this->rahim));

        // Karim is notified in Bengali.
        $note = $this->karim->notifications()->firstOrFail();
        $this->assertSame('রহিম আপনার কাছে ৳১০,০০০ টাকা হস্তান্তরের অনুরোধ পাঠিয়েছেন।', $note->data['message']);
        $this->actingAs($this->karim)->get(route('collector.handovers.incoming'))->assertOk()->assertSee('টাকা গ্রহণ নিশ্চিত করুন');

        $this->actingAs($this->karim)->post(route('collector.handovers.confirm', $h))->assertSessionHas('success');

        $h->refresh();
        $this->assertSame(HandoverStatus::Received, $h->status);
        $this->assertSame($this->karim->id, $h->confirmed_by);
        $this->assertNotNull($h->received_datetime);
        $this->assertSame('0.00', $this->balance($this->rahim));
        $this->assertSame('12000.00', $this->balance($this->karim));

        $legs = CashLedgerEntry::query()->where('reference_type', $h->getMorphClass())->where('reference_id', $h->id)->get();
        $this->assertCount(2, $legs);
        $this->assertCount(1, $legs->pluck('entry_group')->unique());
    }

    public function test_cannot_hand_over_more_than_available_including_pending(): void
    {
        $this->expectException(DomainException::class);
        $this->service->request($this->rahim, $this->karim, '6000', now());
        $this->service->request($this->rahim, $this->karim, '6000', now()); // only 4,000 left available
    }

    public function test_only_the_receiver_can_confirm(): void
    {
        $h = $this->service->request($this->rahim, $this->karim, '1000', now());
        $other = User::factory()->create();

        $this->actingAs($this->rahim)->post(route('collector.handovers.confirm', $h))->assertForbidden();
        $this->actingAs($other)->post(route('collector.handovers.confirm', $h))->assertForbidden();
        $this->actingAs($other)->get(route('collector.handovers.show', $h))->assertForbidden();

        $this->expectException(DomainException::class);
        $this->service->confirm($h, $this->rahim);
    }

    public function test_reject_moves_no_money_and_frees_the_amount(): void
    {
        $h = $this->service->request($this->rahim, $this->karim, '3000', now());

        $this->actingAs($this->karim)->post(route('collector.handovers.reject', $h), ['reason' => 'টাকা পাইনি'])->assertSessionHas('success');

        $this->assertSame(HandoverStatus::Rejected, $h->fresh()->status);
        $this->assertSame('টাকা পাইনি', $h->fresh()->rejection_reason);
        $this->assertSame('10000.00', $this->balance($this->rahim));
        $this->assertSame('10000.00', $this->service->available($this->rahim));
    }

    public function test_sender_can_cancel_only_while_pending(): void
    {
        $pending = $this->service->request($this->rahim, $this->karim, '1000', now());
        $this->actingAs($this->karim)->post(route('collector.handovers.cancel', $pending))->assertForbidden();
        $this->actingAs($this->rahim)->post(route('collector.handovers.cancel', $pending))->assertSessionHas('success');
        $this->assertSame(HandoverStatus::Cancelled, $pending->fresh()->status);

        $done = $this->service->request($this->rahim, $this->karim, '1000', now());
        $this->service->confirm($done, $this->karim);
        $this->actingAs($this->rahim)->post(route('collector.handovers.cancel', $done))->assertForbidden();
        $this->expectException(DomainException::class);
        $this->service->cancel($done, $this->rahim);
    }

    public function test_dispute_then_admin_resolves_as_received(): void
    {
        Notification::fake();
        $h = $this->service->request($this->rahim, $this->karim, '5000', now());
        $this->service->reject($h, $this->karim, 'টাকা পাইনি');

        $this->actingAs($this->rahim)->post(route('collector.handovers.dispute', $h), ['reason' => 'আমি নিজ হাতে দিয়েছি'])->assertSessionHas('success');
        $this->assertSame(HandoverStatus::Disputed, $h->fresh()->status);
        $this->assertSame('5000.00', $this->service->available($this->rahim)); // disputed amount stays reserved
        Notification::assertSentTo($this->admin, HandoverNotification::class);

        $this->actingAs($this->rahim)->post(route('admin.handovers.resolve', $h), ['outcome' => 'received', 'note' => 'x'])->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.handovers.resolve', $h), ['outcome' => 'received', 'note' => 'সাক্ষী আছে'])->assertSessionHas('success');

        $this->assertSame(HandoverStatus::Received, $h->fresh()->status);
        $this->assertSame('5000.00', $this->balance($this->rahim));
        $this->assertSame('7000.00', $this->balance($this->karim));
    }

    public function test_dispute_resolved_as_rejected_moves_nothing(): void
    {
        $h = $this->service->request($this->rahim, $this->karim, '5000', now());
        $this->service->dispute($h, $this->karim, 'কম পেয়েছি');
        $this->service->resolve($h, $this->admin, HandoverStatus::Rejected, 'টাকা দেওয়া হয়নি');

        $this->assertSame(HandoverStatus::Rejected, $h->fresh()->status);
        $this->assertSame('10000.00', $this->balance($this->rahim));
        $this->assertSame('10000.00', $this->service->available($this->rahim));
    }

    public function test_confirm_refused_if_sender_no_longer_has_the_cash(): void
    {
        $h = $this->service->request($this->rahim, $this->karim, '10000', now());
        $this->ledger->debit($this->rahim, LedgerType::CollectionReversal, '500', null, 'বাতিল'); // e.g. a reversal after the request

        $this->expectException(DomainException::class);
        $this->service->confirm($h, $this->karim);
    }

    public function test_admin_can_receive_cash_and_sees_all_handovers(): void
    {
        $h = $this->service->request($this->rahim, $this->admin, '4000', now());
        $this->service->request($this->karim, $this->rahim, '100', now());

        $this->actingAs($this->admin)->post(route('admin.handovers.confirm', $h))->assertSessionHas('success');
        $this->assertSame('4000.00', $this->balance($this->admin));

        $this->actingAs($this->admin)->get(route('admin.handovers.index'))->assertOk()->assertSee('HO-'.now()->year.'-000002');
        $this->actingAs($this->karim)->get(route('collector.handovers.index'))->assertOk()->assertDontSee($h->handover_number);
    }

    public function test_completed_handover_cannot_be_deleted_and_there_is_no_delete_route(): void
    {
        $h = $this->service->request($this->rahim, $this->karim, '100', now());
        $this->service->confirm($h, $this->karim);

        $this->assertFalse(app('router')->has('admin.handovers.destroy'));
        $this->expectException(\LogicException::class);
        $h->delete();
    }

    public function test_cannot_hand_over_to_self_or_inactive_person(): void
    {
        $inactive = User::factory()->inactive()->create();

        $this->actingAs($this->rahim)->post(route('collector.handovers.store'), [
            'receiver_id' => $this->rahim->id, 'amount' => '100', 'handover_datetime' => now()->format('Y-m-d H:i'),
        ])->assertSessionHasErrors('receiver_id');
        $this->actingAs($this->rahim)->post(route('collector.handovers.store'), [
            'receiver_id' => $inactive->id, 'amount' => '100', 'handover_datetime' => now()->format('Y-m-d H:i'),
        ])->assertSessionHasErrors('receiver_id');
    }

    public function test_notification_opens_the_handover_and_is_marked_read(): void
    {
        $h = $this->service->request($this->rahim, $this->karim, '100', now());
        $n = $this->karim->unreadNotifications()->firstOrFail();

        $this->actingAs($this->karim)->get(route('notifications.open', $n->id))->assertRedirect(route('collector.handovers.show', $h));
        $this->assertSame(0, $this->karim->unreadNotifications()->count());
    }
}
