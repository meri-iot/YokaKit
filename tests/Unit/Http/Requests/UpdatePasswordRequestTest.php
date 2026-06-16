<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Enums\RoleType;
use App\Http\Requests\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdatePasswordRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeはログイン済みユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => RoleType::USER]);
        $this->actingAs($user);

        $request = new UpdatePasswordRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは未ログインユーザーを拒否する(): void
    {
        $request = new UpdatePasswordRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_rulesは有効な入力でバリデーション成功する(): void
    {
        $user = User::factory()->create(['password' => bcrypt('current-password')]);
        $this->actingAs($user);

        $request = new UpdatePasswordRequest();
        $validator = Validator::make([
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesは現在のパスワードが不一致の場合に失敗する(): void
    {
        $user = User::factory()->create(['password' => bcrypt('current-password')]);
        $this->actingAs($user);

        $request = new UpdatePasswordRequest();
        $validator = Validator::make([
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('current_password', $validator->errors()->toArray());
    }

    public function test_rulesは新しいパスワードが未入力の場合に失敗する(): void
    {
        $user = User::factory()->create(['password' => bcrypt('current-password')]);
        $this->actingAs($user);

        $request = new UpdatePasswordRequest();
        $validator = Validator::make([
            'current_password' => 'current-password',
            'password' => '',
            'password_confirmation' => '',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_rulesは新しいパスワードが8文字未満の場合に失敗する(): void
    {
        $user = User::factory()->create(['password' => bcrypt('current-password')]);
        $this->actingAs($user);

        $request = new UpdatePasswordRequest();
        $validator = Validator::make([
            'current_password' => 'current-password',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_rulesは確認用パスワードが不一致の場合に失敗する(): void
    {
        $user = User::factory()->create(['password' => bcrypt('current-password')]);
        $this->actingAs($user);

        $request = new UpdatePasswordRequest();
        $validator = Validator::make([
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-456',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_rulesは確認用パスワードが未入力の場合に失敗する(): void
    {
        $user = User::factory()->create(['password' => bcrypt('current-password')]);
        $this->actingAs($user);

        $request = new UpdatePasswordRequest();
        $validator = Validator::make([
            'current_password' => 'current-password',
            'password' => 'new-password-123',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password_confirmation', $validator->errors()->toArray());
    }
}
