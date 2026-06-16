<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\PartNumber;
use App\Models\User;
use App\Services\PartNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PartNumberControllerTest extends TestCase
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
        $response = $this->get(route('part-number.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_認証ユーザーはindexを表示できる(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('part-number.index'));

        $response->assertStatus(200);
    }

    public function test_indexページに品番一覧が表示される(): void
    {
        PartNumber::factory()->create(['part_number_name' => 'display-pn-001', 'barcode' => 'display-barcode-001']);
        PartNumber::factory()->create(['part_number_name' => 'display-pn-002', 'barcode' => 'display-barcode-002']);

        $response = $this->actingAs($this->normalUser)->get(route('part-number.index'));

        $response->assertStatus(200);
        $response->assertSee('display-pn-001', false);
        $response->assertSee('display-barcode-001', false);
        $response->assertSee('display-pn-002', false);
        $response->assertSee('display-barcode-002', false);
    }

    public function test_indexページに作成ボタンが表示される(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('part-number.index'));

        $response->assertStatus(200);
        // 作成ボタンのテキストが表示される（管理者のみ見える）
        $response->assertSee(__('yokakit.target_add', ['target' => __('yokakit.part_number')]), false);
    }

    public function test_一般ユーザーは編集・削除ボタンが見えていない(): void
    {
        $partNumber = PartNumber::factory()->create(['part_number_name' => 'hidden-pn']);

        $response = $this->actingAs($this->normalUser)->get(route('part-number.index'));

        $response->assertStatus(200);
        // 編集ボタンのルートは見えない（削除確認ダイアログのIDも見えない）
        $response->assertDontSee(route('part-number.edit', $partNumber), false);
        $response->assertDontSee("part_number_{$partNumber->part_number_id}", false);
    }

    public function test_管理者は編集・削除ボタンが見えている(): void
    {
        $partNumber = PartNumber::factory()->create(['part_number_name' => 'visible-pn']);

        $response = $this->actingAs($this->adminUser)->get(route('part-number.index'));

        $response->assertStatus(200);
        // 編集ボタンのルートが見える
        $response->assertSee(route('part-number.edit', $partNumber), false);
        // 削除ダイアログのIDが見える
        $response->assertSee("part_number_{$partNumber->part_number_id}", false);
    }

    public function test_一般ユーザーはcreateにアクセスできない(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('part-number.create'));

        $response->assertStatus(403);
    }

    public function test_管理者はcreateにアクセスできる(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('part-number.create'));

        $response->assertStatus(200);
    }

    public function test_一般ユーザーはstoreを実行できない(): void
    {
        $response = $this->actingAs($this->normalUser)->post(route('part-number.store'), $this->storePayload());

        $response->assertStatus(403);
    }

    public function test_storeでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(PartNumberService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(false);
        });

        $response = $this->actingAs($this->adminUser)->post(route('part-number.store'), $this->storePayload());

        $response->assertRedirect(route('part-number.index'));
        $response->assertSessionHas('toast_danger');
    }

    public function test_storeでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(PartNumberService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(true);
        });

        $response = $this->actingAs($this->adminUser)->post(route('part-number.store'), $this->storePayload());

        $response->assertRedirect(route('part-number.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_storeでpart_number_nameが未入力の場合はバリデーションエラーになる(): void
    {
        $payload = $this->storePayload();
        unset($payload['part_number_name']);

        $response = $this->actingAs($this->adminUser)->post(route('part-number.store'), $payload);

        $response->assertSessionHasErrors('part_number_name');
    }

    public function test_storeでpart_number_nameが32文字を超える場合はバリデーションエラーになる(): void
    {
        $payload = $this->storePayload();
        $payload['part_number_name'] = str_repeat('a', 33);

        $response = $this->actingAs($this->adminUser)->post(route('part-number.store'), $payload);

        $response->assertSessionHasErrors('part_number_name');
    }

    public function test_storeでpart_number_nameが重複する場合はバリデーションエラーになる(): void
    {
        PartNumber::factory()->create(['part_number_name' => 'duplicate-name']);
        $payload = $this->storePayload();
        $payload['part_number_name'] = 'duplicate-name';

        $response = $this->actingAs($this->adminUser)->post(route('part-number.store'), $payload);

        $response->assertSessionHasErrors('part_number_name');
    }

    public function test_storeでbarcodeが64文字を超える場合はバリデーションエラーになる(): void
    {
        $payload = $this->storePayload();
        $payload['barcode'] = str_repeat('b', 65);

        $response = $this->actingAs($this->adminUser)->post(route('part-number.store'), $payload);

        $response->assertSessionHasErrors('barcode');
    }

    public function test_storeでbarcodeが重複する場合はバリデーションエラーになる(): void
    {
        PartNumber::factory()->create(['barcode' => 'duplicate-barcode']);
        $payload = $this->storePayload();
        $payload['barcode'] = 'duplicate-barcode';

        $response = $this->actingAs($this->adminUser)->post(route('part-number.store'), $payload);

        $response->assertSessionHasErrors('barcode');
    }

    public function test_storeでbarcodeとremarkが省略可能(): void
    {
        $this->mock(PartNumberService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(true);
        });

        $response = $this->actingAs($this->adminUser)->post(route('part-number.store'), [
            'part_number_name' => 'name-only',
        ]);

        $response->assertRedirect(route('part-number.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_storeでremarkが256文字を超える場合はバリデーションエラーになる(): void
    {
        $payload = $this->storePayload();
        $payload['remark'] = str_repeat('r', 257);

        $response = $this->actingAs($this->adminUser)->post(route('part-number.store'), $payload);

        $response->assertSessionHasErrors('remark');
    }

    public function test_createページにフォームの必須項目が含まれる(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('part-number.create'));

        $response->assertStatus(200);
        $response->assertSee('part_number_name', false);
        $response->assertSee('barcode', false);
        $response->assertSee('remark', false);
    }

    public function test_未認証ユーザーはeditでログイン画面へリダイレクトされる(): void
    {
        $partNumber = PartNumber::factory()->create();

        $response = $this->get(route('part-number.edit', $partNumber));

        $response->assertRedirect(route('login'));
    }

    public function test_一般ユーザーはeditにアクセスできない(): void
    {
        $partNumber = PartNumber::factory()->create();

        $response = $this->actingAs($this->normalUser)->get(route('part-number.edit', $partNumber));

        $response->assertStatus(403);
    }

    public function test_管理者はeditにアクセスできる(): void
    {
        $partNumber = PartNumber::factory()->create();

        $response = $this->actingAs($this->adminUser)->get(route('part-number.edit', $partNumber));

        $response->assertStatus(200);
    }

    public function test_editページに現在の値が表示される(): void
    {
        $partNumber = PartNumber::factory()->create([
            'part_number_name' => 'edit-target-name',
            'barcode' => 'edit-target-barcode',
            'remark' => 'edit-target-remark',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('part-number.edit', $partNumber));

        $response->assertStatus(200);
        $response->assertSee('edit-target-name', false);
        $response->assertSee('edit-target-barcode', false);
        $response->assertSee('edit-target-remark', false);
    }

    public function test_updateでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(PartNumberService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(true);
        });
        $partNumber = PartNumber::factory()->create([
            'part_number_name' => 'pn-update-success',
            'barcode' => 'bar-update-success',
        ]);

        $response = $this->actingAs($this->adminUser)->put(
            route('part-number.update', $partNumber),
            $this->updatePayload($partNumber)
        );

        $response->assertRedirect(route('part-number.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_updateでpart_number_nameが未入力の場合はバリデーションエラーになる(): void
    {
        $partNumber = PartNumber::factory()->create();
        $payload = $this->updatePayload($partNumber);
        unset($payload['part_number_name']);

        $response = $this->actingAs($this->adminUser)->put(route('part-number.update', $partNumber), $payload);

        $response->assertSessionHasErrors('part_number_name');
    }

    public function test_updateでpart_number_nameが32文字を超える場合はバリデーションエラーになる(): void
    {
        $partNumber = PartNumber::factory()->create();
        $payload = $this->updatePayload($partNumber);
        $payload['part_number_name'] = str_repeat('a', 33);

        $response = $this->actingAs($this->adminUser)->put(route('part-number.update', $partNumber), $payload);

        $response->assertSessionHasErrors('part_number_name');
    }

    public function test_updateでpart_number_nameが他レコードと重複する場合はバリデーションエラーになる(): void
    {
        PartNumber::factory()->create(['part_number_name' => 'other-name']);
        $partNumber = PartNumber::factory()->create(['part_number_name' => 'my-name']);
        $payload = $this->updatePayload($partNumber);
        $payload['part_number_name'] = 'other-name';

        $response = $this->actingAs($this->adminUser)->put(route('part-number.update', $partNumber), $payload);

        $response->assertSessionHasErrors('part_number_name');
    }

    public function test_updateで自身のpart_number_nameは重複チェックから除外される(): void
    {
        $this->mock(PartNumberService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(true);
        });
        $partNumber = PartNumber::factory()->create(['part_number_name' => 'same-name']);
        $payload = $this->updatePayload($partNumber);

        $response = $this->actingAs($this->adminUser)->put(route('part-number.update', $partNumber), $payload);

        $response->assertRedirect(route('part-number.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_updateでbarcodeが64文字を超える場合はバリデーションエラーになる(): void
    {
        $partNumber = PartNumber::factory()->create();
        $payload = $this->updatePayload($partNumber);
        $payload['barcode'] = str_repeat('b', 65);

        $response = $this->actingAs($this->adminUser)->put(route('part-number.update', $partNumber), $payload);

        $response->assertSessionHasErrors('barcode');
    }

    public function test_updateでbarcodeが他レコードと重複する場合はバリデーションエラーになる(): void
    {
        PartNumber::factory()->create(['barcode' => 'other-barcode']);
        $partNumber = PartNumber::factory()->create(['part_number_name' => 'my-pn', 'barcode' => 'my-barcode']);
        $payload = $this->updatePayload($partNumber);
        $payload['barcode'] = 'other-barcode';

        $response = $this->actingAs($this->adminUser)->put(route('part-number.update', $partNumber), $payload);

        $response->assertSessionHasErrors('barcode');
    }

    public function test_updateでbarcodeとremarkが省略可能(): void
    {
        $this->mock(PartNumberService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(true);
        });
        $partNumber = PartNumber::factory()->create(['part_number_name' => 'name-only-update']);

        $response = $this->actingAs($this->adminUser)->put(route('part-number.update', $partNumber), [
            'part_number_name' => 'name-only-update',
        ]);

        $response->assertRedirect(route('part-number.index'));
        $response->assertSessionHas('toast_success');
    }

    public function test_updateでremarkが256文字を超える場合はバリデーションエラーになる(): void
    {
        $partNumber = PartNumber::factory()->create();
        $payload = $this->updatePayload($partNumber);
        $payload['remark'] = str_repeat('r', 257);

        $response = $this->actingAs($this->adminUser)->put(route('part-number.update', $partNumber), $payload);

        $response->assertSessionHasErrors('remark');
    }

    public function test_一般ユーザーはupdateを実行できない(): void
    {
        $partNumber = PartNumber::factory()->create([
            'part_number_name' => 'pn-update-target',
            'barcode' => 'bar-update-target',
        ]);

        $response = $this->actingAs($this->normalUser)->put(
            route('part-number.update', $partNumber),
            $this->updatePayload($partNumber)
        );

        $response->assertStatus(403);
    }

    public function test_updateでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(PartNumberService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(false);
        });
        $partNumber = PartNumber::factory()->create([
            'part_number_name' => 'pn-update-target',
            'barcode' => 'bar-update-target',
        ]);

        $response = $this->actingAs($this->adminUser)->put(
            route('part-number.update', $partNumber),
            $this->updatePayload($partNumber)
        );

        $response->assertRedirect(route('part-number.index'));
        $response->assertSessionHas('toast_danger');
    }

    public function test_一般ユーザーはdestroyを実行できない(): void
    {
        $partNumber = PartNumber::factory()->create();

        $response = $this->actingAs($this->normalUser)->delete(route('part-number.destroy', $partNumber));

        $response->assertStatus(403);
    }

    public function test_destroyでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(PartNumberService::class, function (MockInterface $mock) {
            $mock->shouldReceive('destroy')->once()->andReturn(true);
        });
        $partNumber = PartNumber::factory()->create();

        $response = $this->actingAs($this->adminUser)->delete(route('part-number.destroy', $partNumber));

        $response->assertRedirect(route('part-number.index'));
        $response->assertSessionHas('toast_success');
    }

    private function storePayload(): array
    {
        return [
            'part_number_name' => 'new-part-number',
            'barcode' => 'new-barcode',
            'remark' => 'test remark',
        ];
    }

    private function updatePayload(PartNumber $partNumber): array
    {
        return [
            'part_number_name' => $partNumber->part_number_name,
            'barcode' => $partNumber->barcode,
            'remark' => $partNumber->remark,
        ];
    }
}
