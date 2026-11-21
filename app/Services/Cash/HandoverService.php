<?php

namespace App\Services\Cash;

use App\Enums\HandoverStatus;
use App\Enums\LedgerType;
use App\Models\CashHandover;
use App\Models\User;
use App\Notifications\HandoverNotification;
use App\Services\AuditService;
use App\Services\NumberSequenceService;
use App\Support\BanglaFormatter;
use App\Support\Money;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Handover workflow: request → (receiver) confirm | reject, (sender) cancel, (either) dispute → (admin) resolve.
 * Money moves in the ledger only on confirm / resolve-as-received, as two linked entries written together.
 */
class HandoverService
{
    public function __construct(
        private CashLedgerService $ledger,
        private NumberSequenceService $sequences,
        private AuditService $audit,
    ) {}

    /** Ledger balance minus everything this person has sent that is still pending or disputed. */
    public function available(User $holder): string
    {
        $reserved = CashHandover::query()->where('sender_collector_id', $holder->id)
            ->whereIn('status', HandoverStatus::reserving())->sum('amount');

        return Money::sub($this->ledger->balance($holder), number_format((float) $reserved, 2, '.', ''));
    }

    public function request(User $sender, User $receiver, string $amount, DateTimeInterface $at, ?string $notes = null): CashHandover
    {
        $amount = Money::fromPaisa(Money::toPaisa($amount));
        if (Money::toPaisa($amount) <= 0) {
            throw new DomainException('হস্তান্তরের পরিমাণ শূন্যের বেশি হতে হবে।');
        }
        if ($sender->id === $receiver->id) {
            throw new DomainException('নিজের কাছে টাকা হস্তান্তর করা যায় না।');
        }
        if (! $receiver->is_active) {
            throw new DomainException('প্রাপক নিষ্ক্রিয় — তার কাছে হস্তান্তর করা যাবে না।');
        }

        $handover = DB::transaction(function () use ($sender, $receiver, $amount, $at, $notes) {
            User::query()->whereKey($sender->id)->lockForUpdate()->first(); // serialise this sender's requests

            $available = $this->available($sender);
            if (Money::compare($amount, $available) > 0) {
                throw new DomainException('আপনার হাতে হস্তান্তরযোগ্য টাকা '.BanglaFormatter::money($available)
                    .'। এর বেশি হস্তান্তর করা যাবে না।');
            }

            $h = CashHandover::query()->create([
                'handover_number' => $this->sequences->next('handover', 'HO'),
                'sender_collector_id' => $sender->id,
                'receiver_collector_id' => $receiver->id,
                'amount' => $amount,
                'handover_datetime' => $at,
                'status' => HandoverStatus::Pending,
                'notes' => $notes,
            ]);

            $this->audit->log('handover.requested', $h, [], $h->only('handover_number', 'sender_collector_id', 'receiver_collector_id', 'amount'), $sender->id);

            return $h;
        });

        $receiver->notify(new HandoverNotification($handover->load('sender', 'receiver'), 'requested'));

        return $handover;
    }

    public function confirm(CashHandover $handover, User $actor): CashHandover
    {
        $h = DB::transaction(function () use ($handover, $actor) {
            $h = $this->lockPending($handover);
            if ($actor->id !== $h->receiver_collector_id) {
                throw new DomainException('শুধু যার কাছে টাকা পাঠানো হয়েছে তিনিই গ্রহণ নিশ্চিত করতে পারেন।');
            }
            $this->transfer($h, $actor);
            $h->forceFill([
                'status' => HandoverStatus::Received,
                'received_datetime' => now(),
                'confirmed_by' => $actor->id,
            ])->save();
            $this->audit->log('handover.received', $h, ['status' => 'pending'], ['status' => 'received'], $actor->id);

            return $h;
        });

        $h->sender->notify(new HandoverNotification($h->load('sender', 'receiver'), 'received'));

        return $h;
    }

    public function reject(CashHandover $handover, User $actor, string $reason): CashHandover
    {
        $h = DB::transaction(function () use ($handover, $actor, $reason) {
            $h = $this->lockPending($handover);
            if ($actor->id !== $h->receiver_collector_id) {
                throw new DomainException('শুধু প্রাপক হস্তান্তর প্রত্যাখ্যান করতে পারেন।');
            }
            $h->forceFill(['status' => HandoverStatus::Rejected, 'rejection_reason' => $reason])->save();
            $this->audit->log('handover.rejected', $h, ['status' => 'pending'], ['status' => 'rejected', 'reason' => $reason], $actor->id);

            return $h;
        });

        $h->sender->notify(new HandoverNotification($h->load('sender', 'receiver'), 'rejected'));

        return $h;
    }

    public function cancel(CashHandover $handover, User $actor): CashHandover
    {
        $h = DB::transaction(function () use ($handover, $actor) {
            $h = $this->lockPending($handover, 'শুধু অপেক্ষমাণ হস্তান্তর বাতিল করা যায় — গৃহীত হওয়ার পর আর বাতিল করা যাবে না।');
            if ($actor->id !== $h->sender_collector_id) {
                throw new DomainException('শুধু প্রেরক নিজের অনুরোধ বাতিল করতে পারেন।');
            }
            $h->forceFill(['status' => HandoverStatus::Cancelled, 'cancelled_at' => now()])->save();
            $this->audit->log('handover.cancelled', $h, ['status' => 'pending'], ['status' => 'cancelled'], $actor->id);

            return $h;
        });

        $h->receiver->notify(new HandoverNotification($h->load('sender', 'receiver'), 'cancelled'));

        return $h;
    }

    /** Sender or receiver flags a pending or rejected handover for the admin to investigate. */
    public function dispute(CashHandover $handover, User $actor, string $reason): CashHandover
    {
        $h = DB::transaction(function () use ($handover, $actor, $reason) {
            $h = CashHandover::query()->lockForUpdate()->findOrFail($handover->id);
            if (! in_array($actor->id, [$h->sender_collector_id, $h->receiver_collector_id], true)) {
                throw new DomainException('শুধু প্রেরক বা প্রাপক বিরোধ জানাতে পারেন।');
            }
            if (! in_array($h->status, [HandoverStatus::Pending, HandoverStatus::Rejected], true)) {
                throw new DomainException('শুধু অপেক্ষমাণ বা প্রত্যাখ্যাত হস্তান্তর নিয়ে বিরোধ জানানো যায়।');
            }
            $old = $h->status->value;
            $h->forceFill([
                'status' => HandoverStatus::Disputed, 'disputed_by' => $actor->id,
                'disputed_at' => now(), 'dispute_reason' => $reason,
            ])->save();
            $this->audit->log('handover.disputed', $h, ['status' => $old], ['status' => 'disputed', 'reason' => $reason], $actor->id);

            return $h;
        });

        $h->load('sender', 'receiver');
        $notice = new HandoverNotification($h, 'disputed', $actor->name);
        User::query()->where('role', 'admin')->active()->get()->each->notify($notice);
        $other = $actor->id === $h->sender_collector_id ? $h->receiver : $h->sender;
        $other->notify($notice);

        return $h;
    }

    /** Admin decides a disputed handover: received (money moves now) or rejected (no money moves). */
    public function resolve(CashHandover $handover, User $admin, HandoverStatus $outcome, string $note): CashHandover
    {
        if (! $admin->isAdmin()) {
            throw new DomainException('শুধু এডমিন বিরোধ নিষ্পত্তি করতে পারেন।');
        }
        if (! in_array($outcome, [HandoverStatus::Received, HandoverStatus::Rejected], true)) {
            throw new DomainException('নিষ্পত্তি হবে "গৃহীত" অথবা "প্রত্যাখ্যাত"।');
        }

        $h = DB::transaction(function () use ($handover, $admin, $outcome, $note) {
            $h = CashHandover::query()->lockForUpdate()->findOrFail($handover->id);
            if ($h->status !== HandoverStatus::Disputed) {
                throw new DomainException('শুধু বিরোধপূর্ণ হস্তান্তর নিষ্পত্তি করা যায়।');
            }
            if ($outcome === HandoverStatus::Received) {
                $this->transfer($h, $admin);
                $h->received_datetime = now();
                $h->confirmed_by = $admin->id;
            } else {
                $h->rejection_reason ??= $note;
            }
            $h->forceFill([
                'status' => $outcome, 'resolved_by' => $admin->id, 'resolved_at' => now(), 'resolution_note' => $note,
            ])->save();
            $this->audit->log('handover.resolved', $h, ['status' => 'disputed'], ['status' => $outcome->value, 'note' => $note], $admin->id);

            return $h;
        });

        $h->load('sender', 'receiver');
        $notice = new HandoverNotification($h, 'resolved');
        $h->sender->notify($notice);
        $h->receiver->notify($notice);

        return $h;
    }

    private function lockPending(CashHandover $handover, ?string $message = null): CashHandover
    {
        $h = CashHandover::query()->lockForUpdate()->findOrFail($handover->id);
        if ($h->status !== HandoverStatus::Pending) {
            throw new DomainException($message ?? 'এই হস্তান্তর আর অপেক্ষমাণ নেই (বর্তমান অবস্থা: '.$h->status->label().')।');
        }

        return $h;
    }

    /** Debit sender, credit receiver — two linked ledger rows in the caller's DB transaction. */
    private function transfer(CashHandover $h, User $by): void
    {
        $users = User::query()->whereKey([$h->sender_collector_id, $h->receiver_collector_id])
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $sender = $users[$h->sender_collector_id];
        $receiver = $users[$h->receiver_collector_id];

        $balance = $this->ledger->balance($sender);
        if (Money::compare((string) $h->amount, $balance) > 0) {
            throw new DomainException("প্রেরক {$sender->name}-এর হাতে এখন ".BanglaFormatter::money($balance)
                .' আছে, যা হস্তান্তরের পরিমাণের চেয়ে কম। প্রত্যাখ্যান করুন অথবা বিরোধ জানান।');
        }

        $group = (string) Str::uuid();
        $amount = (string) $h->amount;
        $this->ledger->debit($sender, LedgerType::HandoverOut, $amount, $h, "হস্তান্তর {$h->handover_number} — {$receiver->name}-কে প্রদান", $by, now(), $group);
        $this->ledger->credit($receiver, LedgerType::HandoverIn, $amount, $h, "হস্তান্তর {$h->handover_number} — {$sender->name}-এর কাছ থেকে গ্রহণ", $by, now(), $group);
    }
}
