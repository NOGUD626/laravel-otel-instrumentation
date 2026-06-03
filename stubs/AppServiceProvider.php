<?php

namespace App\Providers;

use App\Events\RaceRegistered;
use App\Listeners\NotifyParticipants;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(RaceRegistered::class, NotifyParticipants::class);
    }
}
