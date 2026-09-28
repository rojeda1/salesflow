<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessionAuthenticationTest extends TestCase
{
    public function test_it_logs_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'seller@example.test',
            'password' => Hash::make('Test-password-2026!'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Test-password-2026!',
        ])->assertNoContent();

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_it_rejects_an_incorrect_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Test-password-2026!'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Incorrect-password!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath(
                'errors.email.0',
                'Las credenciales no son válidas.'
            );

        $this->assertGuest('web');
    }

    public function test_it_rejects_an_unknown_email_with_the_same_message(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'unknown@example.test',
            'password' => 'Test-password-2026!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath(
                'errors.email.0',
                'Las credenciales no son válidas.'
            );

        $this->assertGuest('web');
    }

    public function test_it_requires_email_and_password(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->assertGuest('web');
    }

    public function test_guests_cannot_access_the_current_user(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_it_returns_only_the_expected_user_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
    }

    public function test_it_logs_out_and_clears_session_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->withSession(['private_marker' => 'session-data'])
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent()
            ->assertSessionMissing('private_marker');

        $this->assertGuest('web');
    }

    public function test_guests_cannot_log_out(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    }

    public function test_it_limits_login_attempts(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'unknown@example.test',
                'password' => 'Incorrect-password!',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'unknown@example.test',
            'password' => 'Incorrect-password!',
        ])
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        $this->assertGuest('web');
    }
}
