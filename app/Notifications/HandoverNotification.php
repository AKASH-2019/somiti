<?php

namespace App\Notifications;

use App\Models\CashHandover;
use App\Support\BanglaFormatter;
use Illuminate\Notifications\Notification;

class HandoverNotification extends Notification
{
    public function __construct(public CashHandover $handover, public string $event, public ?string $actorName = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $h = $this->handover;
        $amount = BanglaFormatter::money((string) $h->amount);
        $sender = $h->sender->name;
        $receiver = $h->receiver->name;

        $message = match ($this->event) {
            'requested' => "{$sender} আপনার কাছে {$amount} টাকা হস্তান্তরের অনুরোধ পাঠিয়েছেন।",
            'received' => "{$receiver} আপনার {$amount} টাকা গ্রহণ নিশ্চিত করেছেন।",
            'rejected' => "{$receiver} আপনার {$amount} টাকার হস্তান্তর প্রত্যাখ্যান করেছেন।",
            'cancelled' => "{$sender} {$amount} টাকার হস্তান্তর অনুরোধ বাতিল করেছেন।",
            'disputed' => "{$this->actorName} হস্তান্তর {$h->handover_number} ({$amount}) নিয়ে বিরোধ জানিয়েছেন।",
            'resolved' => "এডমিন হস্তান্তর {$h->handover_number} ({$amount}) এর বিরোধ নিষ্পত্তি করেছেন: {$h->status->label()}।",
            default => "হস্তান্তর {$h->handover_number} হালনাগাদ হয়েছে।",
        };

        return [
            'kind' => 'handover',
            'event' => $this->event,
            'handover_id' => $h->id,
            'handover_number' => $h->handover_number,
            'message' => $message,
        ];
    }
}
