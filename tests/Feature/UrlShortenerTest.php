<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UrlShortenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_create_link_with_random_slug(): void
    {
        $registerResponse = $this->postJson('/api/register', [
            'name' => 'Gustavo',
            'email' => 'gustavo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $registerResponse->assertCreated()
            ->assertJsonStructure(['user', 'token']);

        $token = $registerResponse->json('token');

        $linkResponse = $this->withToken($token)->postJson('/api/links', [
            'original_url' => 'https://example.com/long-url',
            'title' => 'Example',
        ]);

        $linkResponse->assertCreated()
            ->assertJsonPath('data.original_url', 'https://example.com/long-url')
            ->assertJsonPath('data.title', 'Example');

        $this->assertMatchesRegularExpression(
            '/^[a-zA-Z0-9]{7}$/',
            $linkResponse->json('data.slug')
        );
    }

    public function test_user_can_create_link_with_custom_slug(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/links', [
            'original_url' => 'https://example.com',
            'slug' => 'my-link',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'my-link');
    }

    public function test_public_redirect_records_click_and_increments_counter(): void
    {
        $link = Link::factory()->create([
            'original_url' => 'https://destination.test',
        ]);

        $response = $this->get('/'.$link->slug);

        $response->assertRedirect('https://destination.test');

        $link->refresh();

        $this->assertSame(1, $link->clicks_count);
        $this->assertDatabaseCount('clicks', 1);
    }

    public function test_expired_link_returns_gone(): void
    {
        $link = Link::factory()->expired()->create([
            'original_url' => 'https://destination.test',
        ]);

        $this->get('/'.$link->slug)->assertStatus(410);
    }

    public function test_soft_deleted_link_is_not_accessible(): void
    {
        $link = Link::factory()->create([
            'original_url' => 'https://destination.test',
        ]);

        $link->delete();

        $this->get('/'.$link->slug)->assertNotFound();
    }

    public function test_user_can_view_stats_for_own_link(): void
    {
        $user = User::factory()->create();
        $link = Link::factory()->for($user)->create([
            'clicks_count' => 2,
        ]);

        $link->clicks()->createMany([
            ['clicked_at' => now()->subDay()],
            ['clicked_at' => now()],
        ]);

        $response = $this->actingAs($user)->getJson('/api/links/'.$link->id.'/stats');

        $response->assertOk()
            ->assertJsonPath('total_clicks', 2)
            ->assertJsonStructure(['clicks_by_day']);
    }

    public function test_user_cannot_view_another_users_link(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $link = Link::factory()->for($owner)->create();

        $this->actingAs($otherUser)
            ->getJson('/api/links/'.$link->id)
            ->assertForbidden();
    }

    public function test_soft_delete_preserves_click_history(): void
    {
        $user = User::factory()->create();
        $link = Link::factory()->for($user)->create([
            'clicks_count' => 1,
        ]);

        $link->clicks()->create(['clicked_at' => now()]);

        $this->actingAs($user)
            ->deleteJson('/api/links/'.$link->id)
            ->assertOk();

        $this->assertSoftDeleted($link);
        $this->assertDatabaseCount('clicks', 1);
    }
}
