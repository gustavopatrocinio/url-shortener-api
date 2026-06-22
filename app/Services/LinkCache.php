<?php

namespace App\Services;

use App\Models\Link;
use Illuminate\Support\Facades\Cache;

class LinkCache
{
    private const TTL_SECONDS = 3600;

    public function key(string $slug): string
    {
        return 'link:slug:'.$slug;
    }

    /**
     * @return array{id: int, original_url: string, is_active: bool, expires_at: ?string}|null
     */
    public function get(string $slug): ?array
    {
        $cached = Cache::get($this->key($slug));

        return is_array($cached) ? $cached : null;
    }

    public function put(Link $link): void
    {
        Cache::put($this->key($link->slug), $this->payloadFromLink($link), self::TTL_SECONDS);
    }

    public function forget(string $slug): void
    {
        Cache::forget($this->key($slug));
    }

    /**
     * @return array{id: int, original_url: string, is_active: bool, expires_at: ?string}
     */
    public function payloadFromLink(Link $link): array
    {
        return [
            'id' => $link->id,
            'original_url' => $link->original_url,
            'is_active' => $link->is_active,
            'expires_at' => $link->expires_at?->toIso8601String(),
        ];
    }
}
