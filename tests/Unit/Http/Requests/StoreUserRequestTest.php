<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreUserRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeはsystemユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => 1]);
        $this->actingAs($user);

        $request = new StoreUserRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeはadminユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => 5]);
        $this->actingAs($user);

        $request = new StoreUserRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_rulesは正常な入力を許可する(): void
    {
        $request = new StoreUserRequest();

        $validator = Validator::make([
            'name' => 'test user',
            'email' => 'store-user-ok@example.com',
            'role' => 5,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesはemail重複を拒否する(): void
    {
        User::factory()->create(['email' => 'dup-user@example.com']);
        $request = new StoreUserRequest();

        $validator = Validator::make([
            'name' => 'dup user',
            'email' => 'dup-user@example.com',
            'role' => 5,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_rulesは不正なroleを拒否する(): void
    {
        $request = new StoreUserRequest();

        $validator = Validator::make([
            'name' => 'invalid role user',
            'email' => 'invalid-role@example.com',
            'role' => 999,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('role', $validator->errors()->toArray());
    }

    public function test_rulesはpassword_confirmationが文字列以外だと失敗する(): void
    {
        $request = new StoreUserRequest();

        $validator = Validator::make([
            'name' => 'invalid confirmation',
            'email' => 'invalid-confirm@example.com',
            'role' => 10,
            'password' => 'password123',
            'password_confirmation' => ['password123'],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password_confirmation', $validator->errors()->toArray());
    }
}
