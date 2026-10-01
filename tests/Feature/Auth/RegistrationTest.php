<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * O cadastro público do Breeze foi fechado: todo usuário do master é admin
 * (vê os api_tokens das lojas, dados dos donos, cobrança). Usuário novo só
 * pelo servidor.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_does_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_cannot_register_by_post(): void
    {
        $response = $this->post('/register', [
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
            'password' => 'Senha-forte-123',
            'password_confirmation' => 'Senha-forte-123',
        ]);

        $this->assertContains($response->status(), [404, 405]);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_page_has_no_register_link(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Criar conta')
            ->assertDontSee('/register');
    }

    public function test_password_reset_does_not_create_users_or_send_links_to_unknown_emails(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'intruso@example.com'])
            ->assertSessionHasErrors('email');

        $this->post('/reset-password', [
            'token' => 'token-inventado',
            'email' => 'intruso@example.com',
            'password' => 'Senha-forte-123',
            'password_confirmation' => 'Senha-forte-123',
        ])->assertSessionHasErrors('email');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_password_reset_with_a_forged_token_does_not_change_an_existing_password(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;

        $this->post('/reset-password', [
            'token' => 'token-inventado',
            'email' => $user->email,
            'password' => 'Senha-forte-123',
            'password_confirmation' => 'Senha-forte-123',
        ])->assertSessionHasErrors('email');

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertGuest();
    }
}
