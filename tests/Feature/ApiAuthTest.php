<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Message;
use App\Models\MessageImage;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private User $carol;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
        $this->carol = User::factory()->create();
    }

    protected function tearDown(): void
    {
        foreach (MessageImage::pluck('image') as $file) {
            @unlink(public_path('images/message/'.$file));
        }

        parent::tearDown();
    }

    private function as(User $user): static
    {
        // the test app is reused between requests; drop the user the token
        // guard cached on the previous request
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->api_token);
    }

    private function makePost(User $owner): Post
    {
        return Post::create([
            'user_id' => $owner->id,
            'title' => 'Sepeda',
            'content' => 'Masih mulus',
            'type' => 'barang',
            'status' => 'active',
            'price' => 100000,
            'is_premium' => 0,
            'can_trade_in' => 0,
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);
    }

    private function makeChat(): Chat
    {
        return Chat::create(['user_id' => $this->alice->id, 'receiver_id' => $this->bob->id]);
    }

    // --- authentication ---

    public function test_protected_routes_return_401_json_without_a_token(): void
    {
        // no Accept header on purpose: API errors must still be JSON
        $this->get('/api/user/get/'.$this->alice->id)
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $this->withToken('not-a-real-token')
            ->getJson('/api/post/category/all')
            ->assertStatus(401);
    }

    public function test_a_valid_token_is_accepted(): void
    {
        $this->as($this->alice)
            ->getJson('/api/user/get/'.$this->bob->id)
            ->assertOk()
            ->assertJsonPath('user.id', $this->bob->id);
    }

    public function test_api_tokens_are_not_exposed_in_user_payloads(): void
    {
        $this->as($this->alice)
            ->getJson('/api/user/get/'.$this->bob->id)
            ->assertOk()
            ->assertJsonMissingPath('user.api_token');
    }

    public function test_register_returns_a_working_token(): void
    {
        $token = $this->postJson('/api/user/register', [
            'name' => 'Dina',
            'email' => 'dina@example.com',
            'password' => 'secret123',
            'phone' => '081234567890',
        ])->assertCreated()->json('user.api_token');

        $this->assertNotEmpty($token);
        $this->withToken($token)->getJson('/api/post/category/all')->assertOk();
    }

    public function test_login_returns_the_token(): void
    {
        $this->postJson('/api/user/login', [
            'email' => $this->alice->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.api_token', $this->alice->api_token);
    }

    public function test_login_issues_a_token_to_users_without_one(): void
    {
        $this->alice->forceFill(['api_token' => null])->save();

        $token = $this->postJson('/api/user/login', [
            'email' => $this->alice->email,
            'password' => 'password',
        ])->assertOk()->json('user.api_token');

        $this->assertNotEmpty($token);
        $this->assertSame($token, $this->alice->fresh()->api_token);
    }

    public function test_login_rejects_a_wrong_password(): void
    {
        $this->postJson('/api/user/login', [
            'email' => $this->alice->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    // --- acting on someone else's data ---

    public function test_users_cannot_update_another_users_profile(): void
    {
        $this->as($this->alice)->postJson('/api/user/update', [
            'id' => $this->bob->id,
            'name' => 'Hacked',
            'email' => 'hacked@example.com',
        ])->assertForbidden();

        $this->assertNotSame('Hacked', $this->bob->fresh()->name);
    }

    public function test_users_can_update_their_own_profile(): void
    {
        $this->as($this->alice)->postJson('/api/user/update', [
            'id' => $this->alice->id,
            'name' => 'Alice Baru',
            'email' => $this->alice->email,
        ])->assertOk();

        $this->assertSame('Alice Baru', $this->alice->fresh()->name);
    }

    public function test_users_cannot_delete_another_users_post(): void
    {
        $post = $this->makePost($this->bob);

        $this->as($this->alice)->deleteJson('/api/post/delete/'.$post->id)->assertForbidden();
        $this->assertNotSoftDeleted($post);

        $this->as($this->bob)->deleteJson('/api/post/delete/'.$post->id)->assertOk();
        $this->assertSoftDeleted($post);
    }

    public function test_users_cannot_save_posts_for_someone_else(): void
    {
        $post = $this->makePost($this->bob);

        $this->as($this->alice)->postJson('/api/post/save', [
            'post_id' => $post->id,
            'user_id' => $this->carol->id,
        ])->assertForbidden();
    }

    public function test_only_participants_can_read_a_chat(): void
    {
        $chat = $this->makeChat();

        $this->as($this->carol)->getJson('/api/message/messages/'.$chat->id)->assertForbidden();
        $this->as($this->bob)->getJson('/api/message/messages/'.$chat->id)->assertOk();
    }

    public function test_users_cannot_send_messages_as_someone_else(): void
    {
        $chat = $this->makeChat();

        $this->as($this->carol)->postJson('/api/message/send', [
            'chat_id' => $chat->id,
            'sender_id' => $this->alice->id,
            'receiver_id' => $this->bob->id,
            'message' => 'hi',
        ])->assertForbidden();

        $this->assertSame(0, Message::count());
    }

    // --- PUT /message/read/{chatId}/{userId} ---

    public function test_read_message_takes_the_chat_id_then_the_user_id(): void
    {
        $chat = $this->makeChat();
        $message = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $this->alice->id,
            'receiver_id' => $this->bob->id,
            'message' => 'hi',
        ]);

        $this->as($this->bob)
            ->putJson("/api/message/read/{$chat->id}/{$this->bob->id}")
            ->assertOk();

        $this->assertTrue((bool) $message->fresh()->is_read);
    }

    public function test_users_cannot_mark_someone_elses_messages_as_read(): void
    {
        $chat = $this->makeChat();

        $this->as($this->alice)
            ->putJson("/api/message/read/{$chat->id}/{$this->bob->id}")
            ->assertForbidden();
    }

    // --- POST /message/image/upload ---

    public function test_the_sender_can_attach_images_to_a_message(): void
    {
        $chat = $this->makeChat();
        $message = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $this->alice->id,
            'receiver_id' => $this->bob->id,
        ]);

        $response = $this->as($this->alice)->post('/api/message/image/upload', [
            'message_id' => $message->id,
            'image' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')],
        ])->assertCreated()->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $image) {
            $this->assertSame($message->id, $image['message_id']);
            $this->assertFileExists(public_path('images/message/'.$image['image']));
        }

        $this->as($this->bob)
            ->getJson('/api/message/messages/'.$chat->id)
            ->assertJsonCount(2, 'data.0.attachments');
    }

    public function test_only_the_sender_can_attach_images_to_a_message(): void
    {
        $chat = $this->makeChat();
        $message = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $this->alice->id,
            'receiver_id' => $this->bob->id,
        ]);

        $this->as($this->bob)->post('/api/message/image/upload', [
            'message_id' => $message->id,
            'image' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertForbidden();

        $this->assertSame(0, MessageImage::count());
    }

    public function test_image_upload_requires_an_image(): void
    {
        $chat = $this->makeChat();
        $message = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $this->alice->id,
            'receiver_id' => $this->bob->id,
        ]);

        $this->as($this->alice)->postJson('/api/message/image/upload', [
            'message_id' => $message->id,
        ])->assertStatus(422)->assertJsonValidationErrors('image');
    }
}
