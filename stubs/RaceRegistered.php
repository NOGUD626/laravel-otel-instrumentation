<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RaceRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $raceName,
        public int $participantCount,
    ) {}
}
