<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $buyer;

    private User $stranger;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->buyer = User::factory()->create();
        $this->stranger = User::factory()->create();

        $this->post = Post::create([
            'user_id' => $this->owner->id,
            'title' => 'Sepeda',
            'content' => 'Masih mulus',
            'type' => 'barang',
            'status' => 'active',
            'price' => 100000,
            'is_premium' => 0,
            'can_trade_in' => 0,
            'latitude' => -6.2,
            'longitude' => 106.8,
            'is_published' => Post::STATUS_PUBLISHED,
        ]);
    }

    private function changeStatus(User $as, int $status, ?int $peerId = null)
    {
        // the test app is reused between requests; drop the cached user
        $this->app['auth']->forgetGuards();

        return $this->withToken($as->api_token)->postJson('/api/post/update/publish/status', [
            'post_id' => $this->post->id,
            'peer_id' => $peerId ?? $as->id,
            'publish_status_id' => $status,
        ]);
    }

    public function test_a_buyer_can_start_a_trade_on_someone_elses_post(): void
    {
        $this->changeStatus($this->buyer, Post::STATUS_TRANSACTION_PENDING)->assertOk();

        $this->post->refresh();
        $this->assertSame(Post::STATUS_TRANSACTION_PENDING, (int) $this->post->is_published);
        $this->assertSame($this->buyer->id, (int) $this->post->peer_id);
    }

    public function test_the_trading_peer_can_close_the_trade(): void
    {
        $this->changeStatus($this->buyer, Post::STATUS_TRANSACTION_PENDING)->assertOk();
        $this->changeStatus($this->buyer, Post::STATUS_CLOSED)->assertOk();

        $this->assertSame(Post::STATUS_CLOSED, (int) $this->post->fresh()->is_published);
    }

    public function test_other_users_cannot_close_someone_elses_trade(): void
    {
        $this->changeStatus($this->buyer, Post::STATUS_TRANSACTION_PENDING)->assertOk();

        $this->changeStatus($this->stranger, Post::STATUS_CLOSED)->assertForbidden();

        $this->post->refresh();
        $this->assertSame(Post::STATUS_TRANSACTION_PENDING, (int) $this->post->is_published);
        $this->assertSame($this->buyer->id, (int) $this->post->peer_id);
    }

    public function test_other_users_cannot_unpublish_a_post(): void
    {
        $this->changeStatus($this->stranger, Post::STATUS_NOT_PUBLISHED)->assertForbidden();

        $this->assertSame(Post::STATUS_PUBLISHED, (int) $this->post->fresh()->is_published);
    }

    public function test_the_owner_can_change_the_status(): void
    {
        $this->changeStatus($this->owner, Post::STATUS_NOT_PUBLISHED)->assertOk();

        $this->assertSame(Post::STATUS_NOT_PUBLISHED, (int) $this->post->fresh()->is_published);
    }

    public function test_peer_id_must_be_the_logged_in_user(): void
    {
        $this->changeStatus($this->stranger, Post::STATUS_TRANSACTION_PENDING, $this->buyer->id)
            ->assertForbidden();

        $this->assertNull($this->post->fresh()->peer_id);
    }

    public function test_unknown_statuses_are_rejected(): void
    {
        $this->changeStatus($this->owner, 9)
            ->assertStatus(422)
            ->assertJsonValidationErrors('publish_status_id');
    }

    public function test_a_missing_post_returns_404(): void
    {
        $this->withToken($this->owner->api_token)->postJson('/api/post/update/publish/status', [
            'post_id' => 999,
            'peer_id' => $this->owner->id,
            'publish_status_id' => Post::STATUS_CLOSED,
        ])->assertNotFound();
    }
}
