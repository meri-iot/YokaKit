<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class UpdateProfileRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeはログイン済みユーザーを許可する(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = new UpdateProfileRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは未ログインユーザーを拒否する(): void
    {
        $request = new UpdateProfileRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationはログインユーザーIDを補完する(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = UpdateProfileRequest::create('/dummy', 'PUT');

        $this->invokePrepareForValidation($request);

        $this->assertSame($user->id, $request->input('user_id'));
    }

    public function test_prepareForValidationは未ログイン時にuser_idをnullで補完する(): void
    {
        $request = UpdateProfileRequest::create('/dummy', 'PUT');

        $this->invokePrepareForValidation($request);

        $this->assertNull($request->input('user_id'));
    }

    public function test_rulesは更新対象の同一メールアドレスを許可する(): void
    {
        $user = User::factory()->create([
            'name' => 'Current User',
            'email' => 'current@example.com',
        ]);

        $request = new UpdateProfileRequest();
        $request->merge(['user_id' => $user->id]);

        $validator = Validator::make([
            'name' => 'Updated Name',
            'email' => 'current@example.com',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesは他ユーザーと重複するメールアドレスを拒否する(): void
    {
        $existing = User::factory()->create(['email' => 'duplicate@example.com']);
        $target = User::factory()->create(['email' => 'target@example.com']);

        $request = new UpdateProfileRequest();
        $request->merge(['user_id' => $target->id]);

        $validator = Validator::make([
            'name' => 'Target User',
            'email' => 'duplicate@example.com',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
        $this->assertNotNull($existing->id);
    }

    public function test_rulesはname未入力で失敗する(): void
    {
        $target = User::factory()->create();

        $request = new UpdateProfileRequest();
        $request->merge(['user_id' => $target->id]);

        $validator = Validator::make([
            'name' => '',
            'email' => 'valid@example.com',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_rulesはemail形式不正で失敗する(): void
    {
        $target = User::factory()->create();

        $request = new UpdateProfileRequest();
        $request->merge(['user_id' => $target->id]);

        $validator = Validator::make([
            'name' => 'Valid Name',
            'email' => 'invalid-email',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    private function invokePrepareForValidation(UpdateProfileRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }
}
