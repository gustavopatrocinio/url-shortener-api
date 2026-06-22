<?php

namespace App\Http\Controllers;

use App\Jobs\RecordClick;
use App\Models\Link;
use App\Services\LinkCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RedirectController extends Controller
{
    public function __construct(
        private LinkCache $linkCache,
    ) {}

    public function __invoke(Request $request, string $slug): RedirectResponse|Response
    {
        $payload = $this->linkCache->get($slug);

        if ($payload === null) {
            $link = Link::query()->where('slug', $slug)->first();

            if ($link === null) {
                abort(404);
            }

            $payload = $this->linkCache->payloadFromLink($link);
            $this->linkCache->put($link);
        }

        if (! $payload['is_active']) {
            abort(404);
        }

        if ($payload['expires_at'] !== null && now()->greaterThan($payload['expires_at'])) {
            abort(410, 'This link has expired.');
        }

        RecordClick::dispatch(
            linkId: $payload['id'],
            idempotencyKey: (string) Str::uuid(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            referer: $request->headers->get('referer'),
        );

        return redirect()->away($payload['original_url'], 302);
    }
}
