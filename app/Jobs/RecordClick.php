<?php

namespace App\Jobs;

use App\Models\Click;
use App\Models\Link;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordClick implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $linkId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $referer,
    ) {}

    public function handle(): void
    {
        if (! Link::whereKey($this->linkId)->exists()) {
            return;
        }

        Click::create([
            'link_id' => $this->linkId,
            'clicked_at' => now(),
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'referer' => $this->referer,
        ]);

        Link::whereKey($this->linkId)->increment('clicks_count');
    }
}
