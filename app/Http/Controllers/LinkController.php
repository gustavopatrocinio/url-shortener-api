<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLinkRequest;
use App\Http\Requests\UpdateLinkRequest;
use App\Http\Resources\LinkResource;
use App\Models\Link;
use App\Services\IdempotencyService;
use App\Services\LinkCache;
use App\Services\SlugGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class LinkController extends Controller
{
    public function __construct(
        private SlugGenerator $slugGenerator,
        private LinkCache $linkCache,
        private IdempotencyService $idempotency,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Link::class);

        $links = $request->user()
            ->links()
            ->latest()
            ->paginate(15);

        return LinkResource::collection($links);
    }

    public function store(StoreLinkRequest $request): JsonResponse
    {
        $this->authorize('create', Link::class);

        /** @var JsonResponse $response */
        $response = $this->idempotency->execute($request, 'links.store', function () use ($request): JsonResponse {
            $validated = $request->validated();

            $link = DB::transaction(function () use ($request, $validated) {
                $slug = $this->slugGenerator->generate($validated['slug'] ?? null);

                return $request->user()->links()->create([
                    'slug' => $slug,
                    'original_url' => $validated['original_url'],
                    'title' => $validated['title'] ?? null,
                    'expires_at' => $validated['expires_at'] ?? null,
                    'is_active' => $validated['is_active'] ?? true,
                ]);
            });

            $this->linkCache->put($link);

            return (new LinkResource($link))
                ->response()
                ->setStatusCode(201);
        });

        return $response;
    }

    public function show(Link $link): LinkResource
    {
        $this->authorize('view', $link);

        return new LinkResource($link);
    }

    public function update(UpdateLinkRequest $request, Link $link): LinkResource
    {
        $this->authorize('update', $link);

        $validated = $request->validated();
        $previousSlug = $link->slug;

        $link->fill($validated);
        $link->save();

        if ($previousSlug !== $link->slug) {
            $this->linkCache->forget($previousSlug);
        }

        $this->linkCache->put($link);

        return new LinkResource($link);
    }

    public function destroy(Link $link): JsonResponse
    {
        $this->authorize('delete', $link);

        $this->linkCache->forget($link->slug);
        $link->delete();

        return response()->json([
            'message' => 'Link deleted successfully.',
        ]);
    }

    public function stats(Request $request, Link $link): JsonResponse
    {
        $this->authorize('view', $link);

        $days = (int) $request->query('days', 7);
        $days = max(1, min($days, 365));
        $from = now()->subDays($days - 1)->startOfDay();

        $clicksByDay = $link->clicks()
            ->selectRaw('DATE(clicked_at) as day, COUNT(*) as clicks')
            ->where('clicked_at', '>=', $from)
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day' => $row->day,
                'clicks' => (int) $row->clicks,
            ]);

        return response()->json([
            'link_id' => $link->id,
            'slug' => $link->slug,
            'total_clicks' => $link->clicks_count,
            'clicks_by_day' => $clicksByDay,
            'period_days' => $days,
        ]);
    }
}
