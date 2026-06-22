<?php

namespace App\Services;

use App\Models\Link;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SlugGenerator
{
    private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private const DEFAULT_LENGTH = 7;

    public function generate(?string $customSlug = null): string
    {
        if ($customSlug !== null) {
            $this->validateCustomSlug($customSlug);

            return $customSlug;
        }

        return $this->generateRandom();
    }

    public function validateCustomSlug(string $slug): void
    {
        if (! preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $slug)) {
            throw new InvalidArgumentException(
                'The slug must be 3-20 characters and contain only letters, numbers, underscores, or hyphens.'
            );
        }

        if ($this->isReserved($slug)) {
            throw new InvalidArgumentException('This slug is reserved.');
        }
    }

    public function generateRandom(): string
    {
        $maxAttempts = 10;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $slug = $this->randomSlug();

            if (! Link::where('slug', $slug)->exists()) {
                return $slug;
            }
        }

        throw new InvalidArgumentException('Unable to generate a unique slug. Please try again.');
    }

    private function randomSlug(): string
    {
        $alphabetLength = strlen(self::ALPHABET);
        $bytes = random_bytes(self::DEFAULT_LENGTH);
        $slug = '';

        for ($i = 0; $i < self::DEFAULT_LENGTH; $i++) {
            $slug .= self::ALPHABET[ord($bytes[$i]) % $alphabetLength];
        }

        return $slug;
    }

    private function isReserved(string $slug): bool
    {
        return in_array(Str::lower($slug), [
            'api',
            'up',
            'login',
            'register',
            'logout',
            'links',
            'stats',
        ], true);
    }
}
