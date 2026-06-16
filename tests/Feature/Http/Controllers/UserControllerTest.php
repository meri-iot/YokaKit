<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $systemUser;
    private User $adminUser;
    private User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->systemUser = User::factory()->create(['role' => 1]);
        $this->adminUser = User::factory()->create(['role' => 5]);
        $this->normalUser = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_未認証ユーザーはindexでログイン画面へリダイレクトされる(): void
    {
        $response = $this->get(route('user.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_一般ユーザーはindexにアクセスできない(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('user.index'));

        $response->assertStatus(403);
    }

    public function test_システム管理者はindexにアクセスできる(): void
    {
        $response = $this->actingAs($this->systemUser)->get(route('user.index'));

        $response->assertStatus(200);
    }

    public function test_indexページにユーザー一覧が表示される(): void
    {
        User::factory()->create([
            'name' => 'display-user-1',
            'email' => 'display-user-1@example.com',
        ]);
        User::factory()->create([
            'name' => 'display-user-2',
            'email' => 'display-user-2@example.com',
        ]);

        $response = $this->actingAs($this->systemUser)->get(route('user.index'));

        $response->assertStatus(200);
        $response->assertSee('display-user-1', false);
        $response->assertSee('display-user-1@example.com', false);
        $response->assertSee('display-user-2', false);
        $response->assertSee('display-user-2@example.com', false);
    }

    public function test_indexページに作成ボタンが表示される(): void
    {
        $response = $this->actingAs($this->systemUser)->get(route('user.index'));

        $response->assertStatus(200);
        $response->assertSee(__('yokakit.target_add', ['target' => __('yokakit.user')]), false);
    }

    public function test_システム管理者はcreateにアクセスできる(): void
    {
        $response = $this->actingAs($this->systemUser)->get(route('user.create'));

        $response->assertStatus(200);
    }

    public function test_管理者はcreateにアクセスできない(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('user.create'));

        $response->assertStatus(403);
    }

    public function test_createページにフォーム項目が含まれる(): void
    {
        $response = $this->actingAs($this->systemUser)->get(route('user.create'));

        $response->assertStatus(200);
        $response->assertSee('name', false);
        $response->assertSee('email', false);
        $response->assertSee('role', false);
        $response->assertSee('password', false);
        $response->assertSee('password_confirmation', false);
    }

    public function test_管理者はstoreを実行できない(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('user.store'), $this->storePayload());

        $response->assertStatus(403);
    }

    public function test_storeでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(UserService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(false);
        });

        $response = $this->actingAs($this->systemUser)->post(route('user.store'), $this->storePayload());

        $response->assertRedirect(route('user.index'));
        $response->assertSessionHas('toast_danger');
    }

    public function test_storeでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(UserService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(true);
        });

        $response = $this->actingAs($this->systemUser)->post(route('user.store'), $this->storePayload());

        $response->assertRedirect(route('user.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_storeでnameが未入力の場合はバリデーションエラーになる(): void
    {
        $payload = $this->storePayload();
        unset($payload['name']);

        $response = $this->actingAs($this->systemUser)->post(route('user.store'), $payload);

        $response->assertSessionHasErrors('name');
    }

    public function test_storeでemailが不正形式の場合はバリデーションエラーになる(): void
    {
        $payload = $this->storePayload();
        $payload['email'] = 'invalid-email';

        $response = $this->actingAs($this->systemUser)->post(route('user.store'), $payload);

        $response->assertSessionHasErrors('email');
    }

    public function test_storeでemailが重複する場合はバリデーションエラーになる(): void
    {
        User::factory()->create(['email' => 'duplicate@example.com']);
        $payload = $this->storePayload();
        $payload['email'] = 'duplicate@example.com';

        $response = $this->actingAs($this->systemUser)->post(route('user.store'), $payload);

        $response->assertSessionHasErrors('email');
    }

    public function test_storeでroleが未入力の場合はバリデーションエラーになる(): void
    {
        $payload = $this->storePayload();
        unset($payload['role']);

        $response = $this->actingAs($this->systemUser)->post(route('user.store'), $payload);

        $response->assertSessionHasErrors('role');
    }

    public function test_storeでpasswordが8文字未満の場合はバリデーションエラーになる(): void
    {
        $payload = $this->storePayload();
        $payload['password'] = 'short';
        $payload['password_confirmation'] = 'short';

        $response = $this->actingAs($this->systemUser)->post(route('user.store'), $payload);

        $response->assertSessionHasErrors('password');
    }

    public function test_storeでpassword_confirmationが不一致の場合はバリデーションエラーになる(): void
    {
        $payload = $this->storePayload();
        $payload['password_confirmation'] = 'not-matched-password';

        $response = $this->actingAs($this->systemUser)->post(route('user.store'), $payload);

        $response->assertSessionHasErrors('password');
    }

    public function test_storeバリデーションエラー後もpasswordは再表示されない(): void
    {
        $payload = $this->storePayload();
        $payload['email'] = 'invalid-email';
        $payload['password'] = 'secret-pass-001';
        $payload['password_confirmation'] = 'secret-pass-001';

        $response = $this->actingAs($this->systemUser)
            ->from(route('user.create'))
            ->post(route('user.store'), $payload);

        $response->assertRedirect(route('user.create'));
        $response->assertSessionHasErrors('email');

        $create = $this->actingAs($this->systemUser)->get(route('user.create'));
        $create->assertStatus(200);
        $create->assertDontSee('secret-pass-001', false);
    }

    public function test_showとeditは認証ユーザーが表示できる(): void
    {
        $show = $this->actingAs($this->normalUser)->get(route('user.show'));
        $edit = $this->actingAs($this->normalUser)->get(route('user.edit'));

        $show->assertStatus(200);
        $edit->assertStatus(200);
    }

    public function test_editページに現在の値が表示される(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('user.edit'));

        $response->assertStatus(200);
        $response->assertSee($this->normalUser->name, false);
        $response->assertSee($this->normalUser->email, false);
    }

    public function test_profile更新でサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(UserService::class, function (MockInterface $mock) {
            $mock->shouldReceive('updateProfile')->once()->andReturn(false);
        });

        $response = $this->actingAs($this->normalUser)->put(route('user.update'), [
            'name' => $this->normalUser->name,
            'email' => $this->normalUser->email,
        ]);

        $response->assertRedirect(route('user.show', ['user' => $this->normalUser]));
        $response->assertSessionHas('toast_danger');
    }

    public function test_profile更新でサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(UserService::class, function (MockInterface $mock) {
            $mock->shouldReceive('updateProfile')->once()->andReturn(true);
        });

        $response = $this->actingAs($this->normalUser)->put(route('user.update'), [
            'name' => $this->normalUser->name,
            'email' => $this->normalUser->email,
        ]);

        $response->assertRedirect(route('user.show', ['user' => $this->normalUser]));
        $response->assertSessionHas('toast_success');
    }

    public function test_profile更新でnameが未入力の場合はバリデーションエラーになる(): void
    {
        $response = $this->actingAs($this->normalUser)->put(route('user.update'), [
            'email' => $this->normalUser->email,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_profile更新でemailが重複する場合はバリデーションエラーになる(): void
    {
        User::factory()->create(['email' => 'other-user@example.com']);

        $response = $this->actingAs($this->normalUser)->put(route('user.update'), [
            'name' => $this->normalUser->name,
            'email' => 'other-user@example.com',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_tokenは管理者のみ実行できる(): void
    {
        $forbidden = $this->actingAs($this->normalUser)->post(route('user.token'));
        $forbidden->assertStatus(403);

        $ok = $this->actingAs($this->adminUser)->post(route('user.token'));
        $ok->assertRedirect(route('user.show'));
        $ok->assertSessionHas('token');
        $token = (string)$ok->getSession()->get('token');
        $this->assertStringStartsWith('Bearer ', $token);
    }

    public function test_password変更でサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(UserService::class, function (MockInterface $mock) {
            $mock->shouldReceive('updatePassword')->once()->andReturn(false);
        });

        $response = $this->actingAs($this->normalUser)->put(route('user.password.change'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect(route('user.show', ['user' => $this->normalUser]));
        $response->assertSessionHas('toast_danger');
    }

    public function test_password画面の各入力にautocomplete属性が設定される(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('user.password'));

        $response->assertStatus(200);
        $response->assertSee('autocomplete="new-password"', false);
        $response->assertDontSee('autocomplete="current-password"', false);
    }

    public function test_自分自身を削除した場合はログアウトしてhomeへ遷移する(): void
    {
        $this->mock(UserService::class, function (MockInterface $mock) {
            $mock->shouldReceive('destroy')->once()->andReturn(true);
        });

        $response = $this->actingAs($this->systemUser)->delete(route('user.destroy', $this->systemUser));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }

    private function storePayload(): array
    {
        return [
            'name' => 'test user',
            'email' => 'test-user@example.com',
            'role' => 5,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }
}
