<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IdempotencyService
{
    private const TTL_SECONDS = 86400;

    public function execute(Request $request, string $scope, Closure $action): Response
    {
        $key = $request->header('Idempotency-Key');

        if ($key === null || $key === '') {
            return $action();
        }

        $this->validateKey($key);

        $userId = $request->user()?->id ?? 'guest';
        $cacheKey = "idempotency:{$scope}:{$userId}:{$key}";
        $requestHash = hash('sha256', $request->getContent());

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            if ($cached['request_hash'] !== $requestHash) {
                throw new HttpException(409, 'Idempotency key was already used with a different request payload.');
            }

            return response()->json($cached['body'], $cached['status']);
        }

        $response = $action();

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            Cache::put($cacheKey, [
                'request_hash' => $requestHash,
                'body' => json_decode($response->getContent(), true),
                'status' => $response->getStatusCode(),
            ], self::TTL_SECONDS);
        }

        return $response;
    }

    private function validateKey(string $key): void
    {
        if (strlen($key) > 255 || ! preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
            throw new HttpException(422, 'Invalid Idempotency-Key header.');
        }
    }
}
