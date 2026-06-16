<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Tests\TestCase;

/**
 * @mixin TestCase
 * @phpstan-require-extends TestCase
 * @method \Illuminate\Testing\TestResponse get(string $uri, array $headers = [])
 * @method static withoutExceptionHandling(array $except = [])
 * @method static actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, string|null $guard = null)
 */
trait TestCommon
{
    private function asAuthenticatable(User $user): Authenticatable
    {
        if ($user instanceof Authenticatable) {
            return $user;
        }

        throw new \Exception('User is not authenticatable');
    }

    private function assertOk(string $route, $parameters = []): void
    {
        $response = $this->get(route($route, $parameters));
        $response->assertOk();
    }

    private function expect403(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(AuthorizationException::class);
    }

    // private function createSystem(): Authenticatable
    // {
    //     $user = User::factory()->create(['role' => 1]);
    //     $authenticatable = $this->asAuthenticatable($user);
    //     $this->actingAs($authenticatable);
    //     return $authenticatable;
    // }

    private function createAdmin(): Authenticatable
    {
        $user = User::factory()->create(['role' => 5]);
        $authenticatable = $this->asAuthenticatable($user);
        $this->actingAs($authenticatable);
        return $authenticatable;
    }

    private function createUser(): Authenticatable
    {
        $user = User::factory()->create();
        $authenticatable = $this->asAuthenticatable($user);
        $this->actingAs($authenticatable);
        return $authenticatable;
    }
}
