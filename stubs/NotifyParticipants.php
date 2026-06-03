<?php

namespace App\Listeners;

use App\Events\RaceRegistered;
use Illuminate\Support\Facades\Log;

class NotifyParticipants
{
    public function handle(RaceRegistered $event): void
    {
        // Listener 内の処理を span として記録するために sleep
        usleep(40_000);
        Log::info('NotifyParticipants: pretending to notify', [
            'race' => $event->raceName,
            'count' => $event->participantCount,
        ]);
    }
}
