<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Psychologist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_admin_created_by_command_can_login_and_access_administration_while_a_psychologist_cannot(): void
    {
        $password = 'local-admin-password';
        $this->artisan('app:create-admin')
            ->expectsQuestion('Prénom', 'Alice')
            ->expectsQuestion('Nom', 'Admin')
            ->expectsQuestion('Email', 'admin@example.test')
            ->expectsQuestion('Mot de passe', $password)
            ->assertSuccessful();

        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $this->assertSame(UserRole::ADMIN, $admin->role);
        $this->assertSame(UserStatus::ACTIVE, $admin->status);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->assertNull($admin->psychologist);

        $this->postJson('/api/login', ['email' => $admin->email, 'password' => $password])->assertOk();
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.role', 'admin');
        $this->getJson('/api/admin/psychologists')->assertOk();

        $psychologist = Psychologist::factory()->create();
        $this->actingAs($psychologist->user, 'sanctum')->getJson('/api/admin/psychologists')->assertForbidden();
    }
}
