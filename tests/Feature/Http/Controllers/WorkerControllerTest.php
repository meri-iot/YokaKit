<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use App\Models\Worker;
use App\Services\WorkerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class WorkerControllerTest extends TestCase
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
        $response = $this->get(route('worker.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_認証ユーザーはindexを表示できる(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('worker.index'));

        $response->assertStatus(200);
    }

    public function test_indexページに作業者一覧が表示される(): void
    {
        Worker::factory()->create([
            'identification_number' => 'W-0001',
            'worker_name' => 'worker-1',
            'mac_address' => '00:11:22:33:44:01',
        ]);
        Worker::factory()->create([
            'identification_number' => 'W-0002',
            'worker_name' => 'worker-2',
            'mac_address' => '00:11:22:33:44:02',
        ]);

        $response = $this->actingAs($this->normalUser)->get(route('worker.index'));

        $response->assertStatus(200);
        $response->assertSee('W-0001', false);
        $response->assertSee('worker-1', false);
        $response->assertSee('00:11:22:33:44:01', false);
        $response->assertSee('W-0002', false);
        $response->assertSee('worker-2', false);
        $response->assertSee('00:11:22:33:44:02', false);
    }

    public function test_管理者はindexに作成ボタンが表示される(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('worker.index'));

        $response->assertStatus(200);
        $response->assertSee(__('yokakit.target_add', ['target' => __('yokakit.worker')]), false);
    }

    public function test_一般ユーザーは編集削除ボタンが表示されない(): void
    {
        $worker = Worker::factory()->create();

        $response = $this->actingAs($this->normalUser)->get(route('worker.index'));

        $response->assertStatus(200);
        $response->assertDontSee(route('worker.edit', $worker), false);
        $response->assertDontSee("worker_{$worker->worker_id}", false);
    }

    public function test_管理者は編集削除ボタンが表示される(): void
    {
        $worker = Worker::factory()->create();

        $response = $this->actingAs($this->adminUser)->get(route('worker.index'));

        $response->assertStatus(200);
        $response->assertSee(route('worker.edit', $worker), false);
        $response->assertSee("worker_{$worker->worker_id}", false);
    }

    public function test_一般ユーザーはcreateにアクセスできない(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('worker.create'));

        $response->assertStatus(403);
    }

    public function test_管理者はcreateにアクセスできる(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('worker.create'));

        $response->assertStatus(200);
    }

    public function test_一般ユーザーはstoreを実行できない(): void
    {
        $response = $this->actingAs($this->normalUser)->post(route('worker.store'), $this->payload());

        $response->assertStatus(403);
    }

    public function test_storeでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(WorkerService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(false);
        });

        $response = $this->actingAs($this->adminUser)->post(route('worker.store'), $this->payload());

        $response->assertRedirect(route('worker.index'));
        $response->assertSessionHas('toast_danger');
    }

    public function test_storeでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(WorkerService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(true);
        });

        $response = $this->actingAs($this->adminUser)->post(route('worker.store'), $this->payload());

        $response->assertRedirect(route('worker.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_storeでidentification_numberが未入力の場合はバリデーションエラーになる(): void
    {
        $payload = $this->payload();
        unset($payload['identification_number']);

        $response = $this->actingAs($this->adminUser)->post(route('worker.store'), $payload);

        $response->assertSessionHasErrors('identification_number');
    }

    public function test_storeでidentification_numberが32文字を超える場合はバリデーションエラーになる(): void
    {
        $payload = $this->payload();
        $payload['identification_number'] = str_repeat('a', 33);

        $response = $this->actingAs($this->adminUser)->post(route('worker.store'), $payload);

        $response->assertSessionHasErrors('identification_number');
    }

    public function test_storeでidentification_numberが重複する場合はバリデーションエラーになる(): void
    {
        Worker::factory()->create(['identification_number' => 'dup-id']);
        $payload = $this->payload();
        $payload['identification_number'] = 'dup-id';

        $response = $this->actingAs($this->adminUser)->post(route('worker.store'), $payload);

        $response->assertSessionHasErrors('identification_number');
    }

    public function test_storeでworker_nameが未入力の場合はバリデーションエラーになる(): void
    {
        $payload = $this->payload();
        unset($payload['worker_name']);

        $response = $this->actingAs($this->adminUser)->post(route('worker.store'), $payload);

        $response->assertSessionHasErrors('worker_name');
    }

    public function test_storeでworker_nameが32文字を超える場合はバリデーションエラーになる(): void
    {
        $payload = $this->payload();
        $payload['worker_name'] = str_repeat('w', 33);

        $response = $this->actingAs($this->adminUser)->post(route('worker.store'), $payload);

        $response->assertSessionHasErrors('worker_name');
    }

    public function test_storeでmac_addressが不正形式の場合はバリデーションエラーになる(): void
    {
        $payload = $this->payload();
        $payload['mac_address'] = 'invalid-mac';

        $response = $this->actingAs($this->adminUser)->post(route('worker.store'), $payload);

        $response->assertSessionHasErrors('mac_address');
    }

    public function test_storeでmac_addressが重複する場合はバリデーションエラーになる(): void
    {
        Worker::factory()->create(['mac_address' => '00:aa:bb:cc:dd:ee']);
        $payload = $this->payload();
        $payload['mac_address'] = '00:aa:bb:cc:dd:ee';

        $response = $this->actingAs($this->adminUser)->post(route('worker.store'), $payload);

        $response->assertSessionHasErrors('mac_address');
    }

    public function test_storeでmac_addressが省略可能(): void
    {
        $this->mock(WorkerService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(true);
        });

        $response = $this->actingAs($this->adminUser)->post(route('worker.store'), [
            'identification_number' => 'ID-1000',
            'worker_name' => 'worker only',
        ]);

        $response->assertRedirect(route('worker.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_createページにフォーム項目が含まれる(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('worker.create'));

        $response->assertStatus(200);
        $response->assertSee('identification_number', false);
        $response->assertSee('worker_name', false);
        $response->assertSee('mac_address', false);
    }

    public function test_未認証ユーザーはeditでログイン画面へリダイレクトされる(): void
    {
        $worker = Worker::factory()->create();

        $response = $this->get(route('worker.edit', $worker));

        $response->assertRedirect(route('login'));
    }

    public function test_一般ユーザーはeditにアクセスできない(): void
    {
        $worker = Worker::factory()->create();

        $response = $this->actingAs($this->normalUser)->get(route('worker.edit', $worker));

        $response->assertStatus(403);
    }

    public function test_管理者はeditにアクセスできる(): void
    {
        $worker = Worker::factory()->create();

        $response = $this->actingAs($this->adminUser)->get(route('worker.edit', $worker));

        $response->assertStatus(200);
    }

    public function test_editページに現在の値が表示される(): void
    {
        $worker = Worker::factory()->create([
            'identification_number' => 'W-9999',
            'worker_name' => 'worker-edit-target',
            'mac_address' => '00:11:22:33:44:99',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('worker.edit', $worker));

        $response->assertStatus(200);
        $response->assertSee('W-9999', false);
        $response->assertSee('worker-edit-target', false);
        $response->assertSee('00:11:22:33:44:99', false);
    }

    public function test_一般ユーザーはupdateを実行できない(): void
    {
        $worker = Worker::factory()->create();

        $response = $this->actingAs($this->normalUser)->put(route('worker.update', $worker), $this->payload($worker));

        $response->assertStatus(403);
    }

    public function test_updateでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(WorkerService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(false);
        });
        $worker = Worker::factory()->create();

        $response = $this->actingAs($this->adminUser)->put(route('worker.update', $worker), $this->payload($worker));

        $response->assertRedirect(route('worker.index'));
        $response->assertSessionHas('toast_danger');
    }

    public function test_updateでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(WorkerService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(true);
        });
        $worker = Worker::factory()->create();

        $response = $this->actingAs($this->adminUser)->put(route('worker.update', $worker), $this->payload($worker));

        $response->assertRedirect(route('worker.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_updateでidentification_numberが未入力の場合はバリデーションエラーになる(): void
    {
        $worker = Worker::factory()->create();
        $payload = $this->payload($worker);
        unset($payload['identification_number']);

        $response = $this->actingAs($this->adminUser)->put(route('worker.update', $worker), $payload);

        $response->assertSessionHasErrors('identification_number');
    }

    public function test_updateでidentification_numberが32文字を超える場合はバリデーションエラーになる(): void
    {
        $worker = Worker::factory()->create();
        $payload = $this->payload($worker);
        $payload['identification_number'] = str_repeat('a', 33);

        $response = $this->actingAs($this->adminUser)->put(route('worker.update', $worker), $payload);

        $response->assertSessionHasErrors('identification_number');
    }

    public function test_updateでidentification_numberが他レコードと重複する場合はバリデーションエラーになる(): void
    {
        Worker::factory()->create(['identification_number' => 'other-id']);
        $worker = Worker::factory()->create(['identification_number' => 'my-id']);
        $payload = $this->payload($worker);
        $payload['identification_number'] = 'other-id';

        $response = $this->actingAs($this->adminUser)->put(route('worker.update', $worker), $payload);

        $response->assertSessionHasErrors('identification_number');
    }

    public function test_updateで自身のidentification_numberは重複チェックから除外される(): void
    {
        $this->mock(WorkerService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(true);
        });
        $worker = Worker::factory()->create(['identification_number' => 'same-id']);

        $response = $this->actingAs($this->adminUser)->put(route('worker.update', $worker), $this->payload($worker));

        $response->assertRedirect(route('worker.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_updateでworker_nameが未入力の場合はバリデーションエラーになる(): void
    {
        $worker = Worker::factory()->create();
        $payload = $this->payload($worker);
        unset($payload['worker_name']);

        $response = $this->actingAs($this->adminUser)->put(route('worker.update', $worker), $payload);

        $response->assertSessionHasErrors('worker_name');
    }

    public function test_updateでworker_nameが32文字を超える場合はバリデーションエラーになる(): void
    {
        $worker = Worker::factory()->create();
        $payload = $this->payload($worker);
        $payload['worker_name'] = str_repeat('w', 33);

        $response = $this->actingAs($this->adminUser)->put(route('worker.update', $worker), $payload);

        $response->assertSessionHasErrors('worker_name');
    }

    public function test_updateでmac_addressが不正形式の場合はバリデーションエラーになる(): void
    {
        $worker = Worker::factory()->create();
        $payload = $this->payload($worker);
        $payload['mac_address'] = 'invalid-mac';

        $response = $this->actingAs($this->adminUser)->put(route('worker.update', $worker), $payload);

        $response->assertSessionHasErrors('mac_address');
    }

    public function test_updateでmac_addressが他レコードと重複する場合はバリデーションエラーになる(): void
    {
        Worker::factory()->create(['mac_address' => '00:aa:bb:cc:dd:ee']);
        $worker = Worker::factory()->create(['mac_address' => '00:11:22:33:44:55']);
        $payload = $this->payload($worker);
        $payload['mac_address'] = '00:aa:bb:cc:dd:ee';

        $response = $this->actingAs($this->adminUser)->put(route('worker.update', $worker), $payload);

        $response->assertSessionHasErrors('mac_address');
    }

    public function test_updateでmac_addressは省略可能(): void
    {
        $this->mock(WorkerService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(true);
        });
        $worker = Worker::factory()->create(['mac_address' => '00:11:22:33:44:10']);
        $payload = $this->payload($worker);
        $payload['mac_address'] = null;

        $response = $this->actingAs($this->adminUser)->put(route('worker.update', $worker), $payload);

        $response->assertRedirect(route('worker.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_一般ユーザーはdestroyを実行できない(): void
    {
        $worker = Worker::factory()->create();

        $response = $this->actingAs($this->normalUser)->delete(route('worker.destroy', $worker));

        $response->assertStatus(403);
    }

    public function test_destroyでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(WorkerService::class, function (MockInterface $mock) {
            $mock->shouldReceive('destroy')->once()->andReturn(true);
        });
        $worker = Worker::factory()->create();

        $response = $this->actingAs($this->adminUser)->delete(route('worker.destroy', $worker));

        $response->assertRedirect(route('worker.index'));
        $response->assertSessionHas('toast_success');
    }

    private function payload(?Worker $worker = null): array
    {
        return [
            'identification_number' => $worker?->identification_number ?? 'ID-0001',
            'worker_name' => $worker?->worker_name ?? 'worker name',
            'mac_address' => $worker?->mac_address ?? '00:11:22:33:44:55',
        ];
    }
}
