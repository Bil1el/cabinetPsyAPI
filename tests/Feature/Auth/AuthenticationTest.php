<?php

namespace Tests\Feature\Auth;

use App\Models\Psychologist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_me_request_always_returns_a_json_401_without_redirecting(): void
    {
        $this->get('/api/me')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.'])
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeaderMissing('Location');
    }

    public function test_both_psychologists_can_login_independently_and_logout(): void
    {
        $this->withHeader('Origin', 'http://localhost');
        $first = $this->professional('first-secret');
        $second = $this->professional('second-secret');

        $this->postJson('/api/login', ['email' => $first->user->email, 'password' => 'first-secret'])
            ->assertOk()
            ->assertJsonPath('user.email', $first->user->email);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.email', $first->user->email);
        $this->postJson('/api/logout')->assertOk();
        $this->getJson('/api/me')->assertUnauthorized();

        $this->postJson('/api/login', ['email' => $second->user->email, 'password' => 'second-secret'])
            ->assertOk()
            ->assertJsonPath('user.email', $second->user->email);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.email', $second->user->email);
    }

    public function test_non_professional_credentials_are_rejected_without_a_session(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonMissingPath('user');

        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_bad_password_is_rejected_and_private_route_requires_authentication(): void
    {
        $psychologist = $this->professional('secret-password');

        $this->postJson('/api/login', ['email' => $psychologist->user->email, 'password' => 'wrong'])->assertUnprocessable();
        $this->getJson('/api/patients')->assertUnauthorized();
    }

    public function test_login_throttling_still_applies_to_failed_attempts(): void
    {
        $psychologist = $this->professional('secret-password');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', ['email' => $psychologist->user->email, 'password' => 'wrong'])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => $psychologist->user->email, 'password' => 'secret-password'])
            ->assertTooManyRequests()
            ->assertJsonMissingPath('user');

        $this->getJson('/api/me')->assertUnauthorized();
    }

    private function professional(string $password): Psychologist
    {
        $psychologist = Psychologist::factory()->create();
        $psychologist->user->update(['password' => $password]);

        return $psychologist;
    }
}
