<?php

namespace Tests\Feature\Console;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Tests\TestCase;

class CreateSystemUserCommandFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_make_userコマンドはシステムユーザーを作成できる(): void
    {
        $name = 'System Test User';
        $email = 'system-test@example.com';
        $password = 'password123';

        $this->artisan('make:user', [
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ])->assertSuccessful();

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame($name, $user->name);
        $this->assertTrue($user->role->is(RoleType::SYSTEM()));
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function test_make_userコマンドはメールアドレス重複時に失敗する(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        // CommandValidator は検証失敗時に終了コードではなく例外を送出する。
        try {
            $this->artisan('make:user', [
                'name' => 'Another User',
                'email' => 'existing@example.com',
                'password' => 'password123',
            ]);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('メールアドレスの値は既に存在しています。', $e->getMessage());
        }

        $this->assertSame(1, User::where('email', 'existing@example.com')->count());
    }

    public function test_make_userコマンドはパスワードが短い場合に失敗する(): void
    {
        // 失敗時にユーザーが作成されないこともあわせて検証する。
        try {
            $this->artisan('make:user', [
                'name' => 'Short Password User',
                'email' => 'short-pass@example.com',
                'password' => '1234567',
            ]);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('パスワードは、8文字以上で指定してください。', $e->getMessage());
        }

        $this->assertDatabaseMissing('users', [
            'email' => 'short-pass@example.com',
        ]);
    }
}
