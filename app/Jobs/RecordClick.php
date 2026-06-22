<?php

namespace App\Jobs;

use App\Models\Click;
use App\Models\Link;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordClick implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $linkId,
        public string $idempotencyKey,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $referer,
    ) {}

    public function uniqueId(): string
    {
        return $this->idempotencyKey;
    }

    public function handle(): void
    {
        if (! Link::whereKey($this->linkId)->exists()) {
            return;
        }

        $click = Click::query()->firstOrCreate(
            ['idempotency_key' => $this->idempotencyKey],
            [
                'link_id' => $this->linkId,
                'clicked_at' => now(),
                'ip_address' => $this->ipAddress,
                'user_agent' => $this->userAgent,
                'referer' => $this->referer,
            ],
        );

        if ($click->wasRecentlyCreated) {
            Link::whereKey($this->linkId)->increment('clicks_count');
        }
    }
}
