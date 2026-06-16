<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\RaspberryPi;
use App\Models\User;
use App\Services\RaspberryPiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class RaspberryPiControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();

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
        $response = $this->get(route('raspberry-pi.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_認証ユーザーはindexを表示できる(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('raspberry-pi.index'));

        $response->assertStatus(200);
    }

    public function test_indexページにラズパイ一覧が表示される(): void
    {
        RaspberryPi::factory()->create([
            'raspberry_pi_name' => 'raspi-1',
            'ip_address' => '192.168.1.10',
        ]);
        RaspberryPi::factory()->create([
            'raspberry_pi_name' => 'raspi-2',
            'ip_address' => '192.168.1.11',
        ]);

        $response = $this->actingAs($this->normalUser)->get(route('raspberry-pi.index'));

        $response->assertStatus(200);
        $response->assertSee('raspi-1', false);
        $response->assertSee('192.168.1.10', false);
        $response->assertSee('raspi-2', false);
        $response->assertSee('192.168.1.11', false);
    }

    public function test_管理者はindexに作成ボタンが表示される(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('raspberry-pi.index'));

        $response->assertStatus(200);
        $response->assertSee(__('yokakit.target_add', ['target' => __('yokakit.raspberry_pi')]), false);
    }

    public function test_一般ユーザーは編集削除ボタンが表示されない(): void
    {
        $raspberryPi = RaspberryPi::factory()->create();

        $response = $this->actingAs($this->normalUser)->get(route('raspberry-pi.index'));

        $response->assertStatus(200);
        $response->assertDontSee(route('raspberry-pi.edit', $raspberryPi), false);
        $response->assertDontSee("raspberry_pi_{$raspberryPi->raspberry_pi_id}", false);
    }

    public function test_管理者は編集削除ボタンが表示される(): void
    {
        $raspberryPi = RaspberryPi::factory()->create();

        $response = $this->actingAs($this->adminUser)->get(route('raspberry-pi.index'));

        $response->assertStatus(200);
        $response->assertSee(route('raspberry-pi.edit', $raspberryPi), false);
        $response->assertSee("raspberry_pi_{$raspberryPi->raspberry_pi_id}", false);
    }

    public function test_一般ユーザーはcreateにアクセスできない(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('raspberry-pi.create'));

        $response->assertStatus(403);
    }

    public function test_管理者はcreateにアクセスできる(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('raspberry-pi.create'));

        $response->assertStatus(200);
    }

    public function test_一般ユーザーはstoreを実行できない(): void
    {
        $response = $this->actingAs($this->normalUser)->post(route('raspberry-pi.store'), $this->payload());

        $response->assertStatus(403);
    }

    public function test_storeでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(RaspberryPiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(false);
        });

        $response = $this->actingAs($this->adminUser)->post(route('raspberry-pi.store'), $this->payload());

        $response->assertRedirect(route('raspberry-pi.index'));
        $response->assertSessionHas('toast_danger');
    }

    public function test_storeでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(RaspberryPiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(true);
        });

        $response = $this->actingAs($this->adminUser)->post(route('raspberry-pi.store'), $this->payload());

        $response->assertRedirect(route('raspberry-pi.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_storeでraspberry_pi_nameが未入力の場合はバリデーションエラーになる(): void
    {
        $payload = $this->payload();
        unset($payload['raspberry_pi_name']);

        $response = $this->actingAs($this->adminUser)->post(route('raspberry-pi.store'), $payload);

        $response->assertSessionHasErrors('raspberry_pi_name');
    }

    public function test_storeでraspberry_pi_nameが32文字を超える場合はバリデーションエラーになる(): void
    {
        $payload = $this->payload();
        $payload['raspberry_pi_name'] = str_repeat('a', 33);

        $response = $this->actingAs($this->adminUser)->post(route('raspberry-pi.store'), $payload);

        $response->assertSessionHasErrors('raspberry_pi_name');
    }

    public function test_storeでraspberry_pi_nameが重複する場合はバリデーションエラーになる(): void
    {
        RaspberryPi::factory()->create(['raspberry_pi_name' => 'duplicate-raspi']);
        $payload = $this->payload();
        $payload['raspberry_pi_name'] = 'duplicate-raspi';

        $response = $this->actingAs($this->adminUser)->post(route('raspberry-pi.store'), $payload);

        $response->assertSessionHasErrors('raspberry_pi_name');
    }

    public function test_storeでip_addressが未入力の場合はバリデーションエラーになる(): void
    {
        $payload = $this->payload();
        unset($payload['ip_address']);

        $response = $this->actingAs($this->adminUser)->post(route('raspberry-pi.store'), $payload);

        $response->assertSessionHasErrors('ip_address');
    }

    public function test_storeでip_addressが不正形式の場合はバリデーションエラーになる(): void
    {
        $payload = $this->payload();
        $payload['ip_address'] = 'invalid-ip';

        $response = $this->actingAs($this->adminUser)->post(route('raspberry-pi.store'), $payload);

        $response->assertSessionHasErrors('ip_address');
    }

    public function test_storeでip_addressが重複する場合はバリデーションエラーになる(): void
    {
        RaspberryPi::factory()->create(['ip_address' => '192.168.1.50']);
        $payload = $this->payload();
        $payload['ip_address'] = '192.168.1.50';

        $response = $this->actingAs($this->adminUser)->post(route('raspberry-pi.store'), $payload);

        $response->assertSessionHasErrors('ip_address');
    }

    public function test_createページにフォーム項目が含まれる(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('raspberry-pi.create'));

        $response->assertStatus(200);
        $response->assertSee('raspberry_pi_name', false);
        $response->assertSee('ip_address', false);
    }

    public function test_未認証ユーザーはeditでログイン画面へリダイレクトされる(): void
    {
        $raspberryPi = RaspberryPi::factory()->create();

        $response = $this->get(route('raspberry-pi.edit', $raspberryPi));

        $response->assertRedirect(route('login'));
    }

    public function test_一般ユーザーはeditにアクセスできない(): void
    {
        $raspberryPi = RaspberryPi::factory()->create();

        $response = $this->actingAs($this->normalUser)->get(route('raspberry-pi.edit', $raspberryPi));

        $response->assertStatus(403);
    }

    public function test_管理者はeditにアクセスできる(): void
    {
        $raspberryPi = RaspberryPi::factory()->create();

        $response = $this->actingAs($this->adminUser)->get(route('raspberry-pi.edit', $raspberryPi));

        $response->assertStatus(200);
    }

    public function test_editページに現在の値が表示される(): void
    {
        $raspberryPi = RaspberryPi::factory()->create([
            'raspberry_pi_name' => 'edit-target-raspi',
            'ip_address' => '10.10.10.10',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('raspberry-pi.edit', $raspberryPi));

        $response->assertStatus(200);
        $response->assertSee('edit-target-raspi', false);
        $response->assertSee('10.10.10.10', false);
    }

    public function test_一般ユーザーはupdateを実行できない(): void
    {
        $raspberryPi = RaspberryPi::factory()->create();

        $response = $this->actingAs($this->normalUser)->put(
            route('raspberry-pi.update', $raspberryPi),
            $this->payload($raspberryPi->raspberry_pi_name, $raspberryPi->ip_address)
        );

        $response->assertStatus(403);
    }

    public function test_updateでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(RaspberryPiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(false);
        });
        $raspberryPi = RaspberryPi::factory()->create();

        $response = $this->actingAs($this->adminUser)->put(
            route('raspberry-pi.update', $raspberryPi),
            $this->payload($raspberryPi->raspberry_pi_name, $raspberryPi->ip_address)
        );

        $response->assertRedirect(route('raspberry-pi.index'));
        $response->assertSessionHas('toast_danger');
    }

    public function test_updateでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(RaspberryPiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(true);
        });
        $raspberryPi = RaspberryPi::factory()->create();

        $response = $this->actingAs($this->adminUser)->put(
            route('raspberry-pi.update', $raspberryPi),
            $this->payload($raspberryPi->raspberry_pi_name, $raspberryPi->ip_address)
        );

        $response->assertRedirect(route('raspberry-pi.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_updateでraspberry_pi_nameが未入力の場合はバリデーションエラーになる(): void
    {
        $raspberryPi = RaspberryPi::factory()->create();
        $payload = $this->payload($raspberryPi->raspberry_pi_name, $raspberryPi->ip_address);
        unset($payload['raspberry_pi_name']);

        $response = $this->actingAs($this->adminUser)->put(route('raspberry-pi.update', $raspberryPi), $payload);

        $response->assertSessionHasErrors('raspberry_pi_name');
    }

    public function test_updateでraspberry_pi_nameが32文字を超える場合はバリデーションエラーになる(): void
    {
        $raspberryPi = RaspberryPi::factory()->create();
        $payload = $this->payload($raspberryPi->raspberry_pi_name, $raspberryPi->ip_address);
        $payload['raspberry_pi_name'] = str_repeat('a', 33);

        $response = $this->actingAs($this->adminUser)->put(route('raspberry-pi.update', $raspberryPi), $payload);

        $response->assertSessionHasErrors('raspberry_pi_name');
    }

    public function test_updateでraspberry_pi_nameが他レコードと重複する場合はバリデーションエラーになる(): void
    {
        RaspberryPi::factory()->create(['raspberry_pi_name' => 'other-raspi']);
        $raspberryPi = RaspberryPi::factory()->create(['raspberry_pi_name' => 'my-raspi']);
        $payload = $this->payload($raspberryPi->raspberry_pi_name, $raspberryPi->ip_address);
        $payload['raspberry_pi_name'] = 'other-raspi';

        $response = $this->actingAs($this->adminUser)->put(route('raspberry-pi.update', $raspberryPi), $payload);

        $response->assertSessionHasErrors('raspberry_pi_name');
    }

    public function test_updateで自身のraspberry_pi_nameは重複チェックから除外される(): void
    {
        $this->mock(RaspberryPiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(true);
        });
        $raspberryPi = RaspberryPi::factory()->create(['raspberry_pi_name' => 'same-raspi']);

        $response = $this->actingAs($this->adminUser)->put(
            route('raspberry-pi.update', $raspberryPi),
            $this->payload('same-raspi', $raspberryPi->ip_address)
        );

        $response->assertRedirect(route('raspberry-pi.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_updateでip_addressが未入力の場合はバリデーションエラーになる(): void
    {
        $raspberryPi = RaspberryPi::factory()->create();
        $payload = $this->payload($raspberryPi->raspberry_pi_name, $raspberryPi->ip_address);
        unset($payload['ip_address']);

        $response = $this->actingAs($this->adminUser)->put(route('raspberry-pi.update', $raspberryPi), $payload);

        $response->assertSessionHasErrors('ip_address');
    }

    public function test_updateでip_addressが不正形式の場合はバリデーションエラーになる(): void
    {
        $raspberryPi = RaspberryPi::factory()->create();
        $payload = $this->payload($raspberryPi->raspberry_pi_name, $raspberryPi->ip_address);
        $payload['ip_address'] = 'invalid-ip';

        $response = $this->actingAs($this->adminUser)->put(route('raspberry-pi.update', $raspberryPi), $payload);

        $response->assertSessionHasErrors('ip_address');
    }

    public function test_updateでip_addressが他レコードと重複する場合はバリデーションエラーになる(): void
    {
        RaspberryPi::factory()->create(['ip_address' => '192.168.1.200']);
        $raspberryPi = RaspberryPi::factory()->create(['ip_address' => '192.168.1.201']);
        $payload = $this->payload($raspberryPi->raspberry_pi_name, $raspberryPi->ip_address);
        $payload['ip_address'] = '192.168.1.200';

        $response = $this->actingAs($this->adminUser)->put(route('raspberry-pi.update', $raspberryPi), $payload);

        $response->assertSessionHasErrors('ip_address');
    }

    public function test_一般ユーザーはdestroyを実行できない(): void
    {
        $raspberryPi = RaspberryPi::factory()->create();

        $response = $this->actingAs($this->normalUser)->delete(route('raspberry-pi.destroy', $raspberryPi));

        $response->assertStatus(403);
    }

    public function test_destroyでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(RaspberryPiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('destroy')->once()->andReturn(true);
        });
        $raspberryPi = RaspberryPi::factory()->create();

        $response = $this->actingAs($this->adminUser)->delete(route('raspberry-pi.destroy', $raspberryPi));

        $response->assertRedirect(route('raspberry-pi.index'));
        $response->assertSessionHas('toast_success');
    }

    private function payload(?string $name = null, ?string $ip = null): array
    {
        return [
            'raspberry_pi_name' => $name ?? 'raspi-new',
            'ip_address' => $ip ?? '192.168.10.10',
        ];
    }
}
