<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $email) {}

    public function handle(): void
    {
        // ジョブ内で重い処理がある想定 (sleep + DB クエリ)
        usleep(80_000);
        Log::info('SendWelcomeEmail: pretending to send', ['to' => $this->email]);
    }
}
