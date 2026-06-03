<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_rejects_invalid_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jan Kowalski',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_register_rejects_privilege_escalation_fields(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_admin' => true,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['is_admin']);

        $this->assertDatabaseMissing('users', ['email' => 'jan@example.com']);
    }

    public function test_register_rejects_short_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jan Kowalski',
            'email' => 'jan2@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_report_rejects_invalid_title_characters(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->post('/api/reports', [
            'title' => '<script>alert(1)</script>',
            'description' => 'Opis zgloszenia testowego z odpowiednia dlugoscia.',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)->assertJsonValidationErrors(['title']);
    }

    public function test_report_rejects_invalid_image_type(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->post('/api/reports', [
            'title' => 'Uszkodzony przystanek',
            'description' => 'Opis zgloszenia testowego z odpowiednia dlugoscia.',
            'images' => [
                UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)->assertJsonValidationErrors(['images.0']);
    }

    public function test_users_list_requires_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_pagination_rejects_invalid_per_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/ride-history?per_page=9999')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }
}
