<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // LOGIN
    // -------------------------------------------------------------------------

    public function test_login_returns_token_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'expires_in']);
    }

    public function test_login_returns_error_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(400)
                 ->assertJson(['error' => 'invalid_credentials']);
    }

    public function test_login_returns_error_for_nonexistent_user(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'whatever',
        ]);

        $response->assertStatus(400)
                 ->assertJson(['error' => 'invalid_credentials']);
    }

    public function test_login_is_throttled_after_too_many_attempts(): void
    {
        Cache::flush();

        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        // A throttle limit 5 kérés/perc — 5 sikertelen kísérlet után 429-et várunk
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email'    => $user->email,
                'password' => 'wrong',
            ]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
    }

    // -------------------------------------------------------------------------
    // LOGOUT
    // -------------------------------------------------------------------------

    public function test_logout_invalidates_token(): void
    {
        $user  = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Successfully logged out']);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/logout')
             ->assertStatus(401);
    }

    public function test_cannot_use_token_after_logout(): void
    {
        $user  = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $this->withHeader('Authorization', "Bearer $token")
             ->postJson('/api/v1/auth/logout')
             ->assertStatus(200);

        // A guard cachet vissza kell állítani, hogy a következő kérés
        // ne az előző kérésből gyorsítótárazott user-t használja
        $this->app['auth']->forgetGuards();

        // Ugyanazzal a tokennel már nem lehet kijelentkezni
        $this->withHeader('Authorization', "Bearer $token")
             ->postJson('/api/v1/auth/logout')
             ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // REFRESH
    // -------------------------------------------------------------------------

    public function test_refresh_returns_new_token(): void
    {
        $user  = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->postJson('/api/v1/auth/refresh');

        $response->assertStatus(200)
                 ->assertJsonStructure(['token']);

        $this->assertNotEquals($token, $response->json('token'));
    }

    public function test_refresh_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/refresh')
             ->assertStatus(401);
    }

    public function test_refresh_returns_new_usable_token(): void
    {
        $user  = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $newToken = $this->withHeader('Authorization', "Bearer $token")
                         ->postJson('/api/v1/auth/refresh')
                         ->assertStatus(200)
                         ->json('token');

        // Az új tokennel sikeresen ki lehet jelentkezni
        $this->withHeader('Authorization', "Bearer $newToken")
             ->postJson('/api/v1/auth/logout')
             ->assertStatus(200);
    }
}
