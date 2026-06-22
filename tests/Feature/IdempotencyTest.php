<?php

namespace Tests\Feature;

use App\Jobs\RecordClick;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_link_with_same_idempotency_key_returns_cached_response(): void
    {
        $user = User::factory()->create();
        $payload = ['original_url' => 'https://example.com'];

        $first = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'create-link-1')
            ->postJson('/api/links', $payload);

        $second = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'create-link-1')
            ->postJson('/api/links', $payload);

        $first->assertCreated();
        $second->assertCreated()
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.slug', $first->json('data.slug'));

        $this->assertDatabaseCount('links', 1);
    }

    public function test_create_link_with_same_idempotency_key_and_different_payload_returns_conflict(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'create-link-2')
            ->postJson('/api/links', ['original_url' => 'https://example.com'])
            ->assertCreated();

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'create-link-2')
            ->postJson('/api/links', ['original_url' => 'https://other.com'])
            ->assertStatus(409);

        $this->assertDatabaseCount('links', 1);
    }

    public function test_record_click_job_does_not_duplicate_on_retry(): void
    {
        $link = Link::factory()->create();

        $job = new RecordClick(
            linkId: $link->id,
            idempotencyKey: 'click-abc-123',
            ipAddress: '127.0.0.1',
            userAgent: 'Test Agent',
            referer: null,
        );

        $job->handle();
        $job->handle();

        $link->refresh();

        $this->assertSame(1, $link->clicks_count);
        $this->assertDatabaseCount('clicks', 1);
    }
}
