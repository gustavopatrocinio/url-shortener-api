<?php

namespace App\Http\Requests;

use App\Models\Link;
use App\Services\SlugGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Link $link */
        $link = $this->route('link');

        return [
            'original_url' => ['sometimes', 'url', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:20',
                'regex:/^[a-zA-Z0-9_-]{3,20}$/',
                Rule::unique('links', 'slug')->ignore($link->id),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    try {
                        app(SlugGenerator::class)->validateCustomSlug($value);
                    } catch (\InvalidArgumentException $exception) {
                        $fail($exception->getMessage());
                    }
                },
            ],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
