<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\PlannedOutage;
use App\Models\User;
use App\Services\PlannedOutageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PlannedOutageControllerTest extends TestCase
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
        $response = $this->get(route('planned-outage.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_認証ユーザーはindexを表示できる(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('planned-outage.index'));

        $response->assertStatus(200);
    }

    public function test_indexページに計画停止時間一覧が表示される(): void
    {
        PlannedOutage::factory()->create([
            'planned_outage_name' => 'break-1',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
        ]);
        PlannedOutage::factory()->create([
            'planned_outage_name' => 'break-2',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);

        $response = $this->actingAs($this->normalUser)->get(route('planned-outage.index'));

        $response->assertStatus(200);
        $response->assertSee('break-1', false);
        $response->assertSee('08:00', false);
        $response->assertSee('09:00', false);
        $response->assertSee('break-2', false);
        $response->assertSee('10:00', false);
        $response->assertSee('11:00', false);
    }

    public function test_管理者はindexに作成ボタンが表示される(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('planned-outage.index'));

        $response->assertStatus(200);
        $response->assertSee(__('yokakit.target_add', ['target' => __('yokakit.planned_outage')]), false);
    }

    public function test_一般ユーザーは編集削除ボタンが表示されない(): void
    {
        $plannedOutage = PlannedOutage::factory()->create();

        $response = $this->actingAs($this->normalUser)->get(route('planned-outage.index'));

        $response->assertStatus(200);
        $response->assertDontSee(route('planned-outage.edit', $plannedOutage), false);
        $response->assertDontSee("planned_outage_{$plannedOutage->planned_outage_id}", false);
    }

    public function test_管理者は編集削除ボタンが表示される(): void
    {
        $plannedOutage = PlannedOutage::factory()->create();

        $response = $this->actingAs($this->adminUser)->get(route('planned-outage.index'));

        $response->assertStatus(200);
        $response->assertSee(route('planned-outage.edit', $plannedOutage), false);
        $response->assertSee("planned_outage_{$plannedOutage->planned_outage_id}", false);
    }

    public function test_一般ユーザーはcreateにアクセスできない(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('planned-outage.create'));

        $response->assertStatus(403);
    }

    public function test_管理者はcreateにアクセスできる(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('planned-outage.create'));

        $response->assertStatus(200);
    }

    public function test_一般ユーザーはstoreを実行できない(): void
    {
        $response = $this->actingAs($this->normalUser)->post(route('planned-outage.store'), $this->payload());

        $response->assertStatus(403);
    }

    public function test_storeでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(PlannedOutageService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(false);
        });

        $response = $this->actingAs($this->adminUser)->post(route('planned-outage.store'), $this->payload());

        $response->assertRedirect(route('planned-outage.index'));
        $response->assertSessionHas('toast_danger');
    }

    public function test_storeでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(PlannedOutageService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(true);
        });

        $response = $this->actingAs($this->adminUser)->post(route('planned-outage.store'), $this->payload());

        $response->assertRedirect(route('planned-outage.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_storeでplanned_outage_nameが未入力の場合はバリデーションエラーになる(): void
    {
        $payload = $this->payload();
        unset($payload['planned_outage_name']);

        $response = $this->actingAs($this->adminUser)->post(route('planned-outage.store'), $payload);

        $response->assertSessionHasErrors('planned_outage_name');
    }

    public function test_storeでplanned_outage_nameが32文字を超える場合はバリデーションエラーになる(): void
    {
        $payload = $this->payload();
        $payload['planned_outage_name'] = str_repeat('a', 33);

        $response = $this->actingAs($this->adminUser)->post(route('planned-outage.store'), $payload);

        $response->assertSessionHasErrors('planned_outage_name');
    }

    public function test_storeでplanned_outage_nameが重複する場合はバリデーションエラーになる(): void
    {
        PlannedOutage::factory()->create(['planned_outage_name' => 'duplicate-name']);
        $payload = $this->payload();
        $payload['planned_outage_name'] = 'duplicate-name';

        $response = $this->actingAs($this->adminUser)->post(route('planned-outage.store'), $payload);

        $response->assertSessionHasErrors('planned_outage_name');
    }

    public function test_storeでstart_timeが未入力の場合はバリデーションエラーになる(): void
    {
        $payload = $this->payload();
        unset($payload['start_time']);

        $response = $this->actingAs($this->adminUser)->post(route('planned-outage.store'), $payload);

        $response->assertSessionHasErrors('start_time');
    }

    public function test_storeでend_timeが未入力の場合はバリデーションエラーになる(): void
    {
        $payload = $this->payload();
        unset($payload['end_time']);

        $response = $this->actingAs($this->adminUser)->post(route('planned-outage.store'), $payload);

        $response->assertSessionHasErrors('end_time');
    }

    public function test_storeでstart_timeとend_timeが同じ場合はバリデーションエラーになる(): void
    {
        $payload = $this->payload();
        $payload['end_time'] = $payload['start_time'];

        $response = $this->actingAs($this->adminUser)->post(route('planned-outage.store'), $payload);

        $response->assertSessionHasErrors('end_time');
    }

    public function test_createページにフォーム項目が含まれる(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('planned-outage.create'));

        $response->assertStatus(200);
        $response->assertSee('planned_outage_name', false);
        $response->assertSee('start_time', false);
        $response->assertSee('end_time', false);
    }

    public function test_未認証ユーザーはeditでログイン画面へリダイレクトされる(): void
    {
        $plannedOutage = PlannedOutage::factory()->create();

        $response = $this->get(route('planned-outage.edit', $plannedOutage));

        $response->assertRedirect(route('login'));
    }

    public function test_一般ユーザーはeditにアクセスできない(): void
    {
        $plannedOutage = PlannedOutage::factory()->create();

        $response = $this->actingAs($this->normalUser)->get(route('planned-outage.edit', $plannedOutage));

        $response->assertStatus(403);
    }

    public function test_管理者はeditにアクセスできる(): void
    {
        $plannedOutage = PlannedOutage::factory()->create();

        $response = $this->actingAs($this->adminUser)->get(route('planned-outage.edit', $plannedOutage));

        $response->assertStatus(200);
    }

    public function test_editページに現在の値が表示される(): void
    {
        $plannedOutage = PlannedOutage::factory()->create([
            'planned_outage_name' => 'edit-target',
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('planned-outage.edit', $plannedOutage));

        $response->assertStatus(200);
        $response->assertSee('edit-target', false);
        $response->assertSee('12:00', false);
        $response->assertSee('13:00', false);
    }

    public function test_一般ユーザーはupdateを実行できない(): void
    {
        $plannedOutage = PlannedOutage::factory()->create();

        $response = $this->actingAs($this->normalUser)->put(
            route('planned-outage.update', $plannedOutage),
            $this->payload($plannedOutage->planned_outage_name)
        );

        $response->assertStatus(403);
    }

    public function test_updateでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(PlannedOutageService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(false);
        });
        $plannedOutage = PlannedOutage::factory()->create();

        $response = $this->actingAs($this->adminUser)->put(
            route('planned-outage.update', $plannedOutage),
            $this->payload($plannedOutage->planned_outage_name)
        );

        $response->assertRedirect(route('planned-outage.index'));
        $response->assertSessionHas('toast_danger');
    }

    public function test_updateでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(PlannedOutageService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(true);
        });
        $plannedOutage = PlannedOutage::factory()->create();

        $response = $this->actingAs($this->adminUser)->put(
            route('planned-outage.update', $plannedOutage),
            $this->payload($plannedOutage->planned_outage_name)
        );

        $response->assertRedirect(route('planned-outage.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_updateでplanned_outage_nameが未入力の場合はバリデーションエラーになる(): void
    {
        $plannedOutage = PlannedOutage::factory()->create();
        $payload = $this->payload($plannedOutage->planned_outage_name);
        unset($payload['planned_outage_name']);

        $response = $this->actingAs($this->adminUser)->put(route('planned-outage.update', $plannedOutage), $payload);

        $response->assertSessionHasErrors('planned_outage_name');
    }

    public function test_updateでplanned_outage_nameが32文字を超える場合はバリデーションエラーになる(): void
    {
        $plannedOutage = PlannedOutage::factory()->create();
        $payload = $this->payload($plannedOutage->planned_outage_name);
        $payload['planned_outage_name'] = str_repeat('a', 33);

        $response = $this->actingAs($this->adminUser)->put(route('planned-outage.update', $plannedOutage), $payload);

        $response->assertSessionHasErrors('planned_outage_name');
    }

    public function test_updateでplanned_outage_nameが他レコードと重複する場合はバリデーションエラーになる(): void
    {
        PlannedOutage::factory()->create(['planned_outage_name' => 'other-name']);
        $plannedOutage = PlannedOutage::factory()->create(['planned_outage_name' => 'my-name']);
        $payload = $this->payload($plannedOutage->planned_outage_name);
        $payload['planned_outage_name'] = 'other-name';

        $response = $this->actingAs($this->adminUser)->put(route('planned-outage.update', $plannedOutage), $payload);

        $response->assertSessionHasErrors('planned_outage_name');
    }

    public function test_updateで自身のplanned_outage_nameは重複チェックから除外される(): void
    {
        $this->mock(PlannedOutageService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(true);
        });
        $plannedOutage = PlannedOutage::factory()->create(['planned_outage_name' => 'same-name']);

        $response = $this->actingAs($this->adminUser)->put(route('planned-outage.update', $plannedOutage), $this->payload('same-name'));

        $response->assertRedirect(route('planned-outage.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_updateでstart_timeが未入力の場合はバリデーションエラーになる(): void
    {
        $plannedOutage = PlannedOutage::factory()->create();
        $payload = $this->payload($plannedOutage->planned_outage_name);
        unset($payload['start_time']);

        $response = $this->actingAs($this->adminUser)->put(route('planned-outage.update', $plannedOutage), $payload);

        $response->assertSessionHasErrors('start_time');
    }

    public function test_updateでend_timeが未入力の場合はバリデーションエラーになる(): void
    {
        $plannedOutage = PlannedOutage::factory()->create();
        $payload = $this->payload($plannedOutage->planned_outage_name);
        unset($payload['end_time']);

        $response = $this->actingAs($this->adminUser)->put(route('planned-outage.update', $plannedOutage), $payload);

        $response->assertSessionHasErrors('end_time');
    }

    public function test_updateでstart_timeとend_timeが同じ場合はバリデーションエラーになる(): void
    {
        $plannedOutage = PlannedOutage::factory()->create();
        $payload = $this->payload($plannedOutage->planned_outage_name);
        $payload['end_time'] = $payload['start_time'];

        $response = $this->actingAs($this->adminUser)->put(route('planned-outage.update', $plannedOutage), $payload);

        $response->assertSessionHasErrors('end_time');
    }

    public function test_一般ユーザーはdestroyを実行できない(): void
    {
        $plannedOutage = PlannedOutage::factory()->create();

        $response = $this->actingAs($this->normalUser)->delete(route('planned-outage.destroy', $plannedOutage));

        $response->assertStatus(403);
    }

    public function test_destroyでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(PlannedOutageService::class, function (MockInterface $mock) {
            $mock->shouldReceive('destroy')->once()->andReturn(true);
        });
        $plannedOutage = PlannedOutage::factory()->create();

        $response = $this->actingAs($this->adminUser)->delete(route('planned-outage.destroy', $plannedOutage));

        $response->assertRedirect(route('planned-outage.index'));
        $response->assertSessionHas('toast_success');
    }

    private function payload(?string $name = null): array
    {
        return [
            'planned_outage_name' => $name ?? 'planned-outage-new',
            'start_time' => '08:00',
            'end_time' => '09:00',
        ];
    }
}
