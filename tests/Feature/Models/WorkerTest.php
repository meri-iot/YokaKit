<?php

namespace Tests\Feature\Models;

use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class WorkerTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;
    use WithFaker;

    public function test_作業者一覧ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $response = $this->get(route('worker.index'));
        $response->assertRedirect('login');
    }

    public function test_作業者一覧ページにログインしてる状態でアクセスしたguestユーザーはok()
    {
        $this->createUser();
        $this->assertOk('worker.index');
    }

    public function test_作業者一覧ページにログインしてる状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $this->assertOk('worker.index');
    }

    public function test_作業者追加ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $response = $this->get(route('worker.create'));
        $response->assertRedirect('login');
    }

    public function test_作業者追加ページにログインしている状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $this->get(route('worker.create'));
    }

    public function test_作業者追加ページにログインしている状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $this->assertOk('worker.create');
    }

    public function test_作業者追加をログインしていない状態ではログインページへリダイレクト()
    {
        $response = $this->post(route('worker.store'), [
            'worker_name' => $this->faker->unique()->realText(32)
        ]);
        $response->assertRedirect('login');
    }

    public function test_作業者追加のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $this->post(route('worker.store'), [
            'identification_number' => $this->faker->unique()->realText(32),
            'worker_name' => $this->faker->unique()->realText(32)
        ]);
    }

    public function test_作業者追加の成功1()
    {
        $this->createAdmin();
        $expectedWorkerName = $this->faker->realText(32);
        $expectedId = $this->faker->unique()->realText(32);
        $response = $this->post(route('worker.store'), [
            'identification_number' => $expectedId,
            'worker_name' => $expectedWorkerName,
        ]);
        $response->assertRedirect(route('worker.index'))
            ->assertSessionHas('toast_success', '作業者の登録に成功しました。');

        $storedWorker = Worker::where('identification_number', $expectedId)->first();
        $this->assertEquals($expectedId, $storedWorker->identification_number);
        $this->assertEquals($expectedWorkerName, $storedWorker->worker_name);
        $this->assertNull($storedWorker->mac_address);
    }

    public function test_作業者追加の成功2()
    {
        $this->createAdmin();
        $expectedWorkerName = $this->faker->realText(32);
        $expectedId = $this->faker->unique()->realText(32);
        $expectedMacAddress = $this->faker->unique()->macAddress;
        $response = $this->post(route('worker.store'), [
            'identification_number' => $expectedId,
            'worker_name' => $expectedWorkerName,
            'mac_address' => $expectedMacAddress,
        ]);
        $response->assertRedirect(route('worker.index'))
            ->assertSessionHas('toast_success', '作業者の登録に成功しました。');

        $storedWorker = Worker::where('identification_number', $expectedId)->first();
        $this->assertEquals($expectedId, $storedWorker->identification_number);
        $this->assertEquals($expectedWorkerName, $storedWorker->worker_name);
        $this->assertEquals($expectedMacAddress, $storedWorker->mac_address);
    }

    public function test_作業者追加の作業者名が重複していても成功()
    {
        $this->createAdmin();
        $worker = Worker::factory()->create();
        $expectedId = $this->faker->unique()->realText(32);
        $response = $this->post(route('worker.store'), [
            'identification_number' => $expectedId,
            'worker_name' => $worker->worker_name,
        ]);
        $response->assertRedirect(route('worker.index'))
            ->assertSessionHas('toast_success', '作業者の登録に成功しました。');

        $storedWorker = Worker::where('identification_number', $expectedId)->first();
        $this->assertEquals($expectedId, $storedWorker->identification_number);
        $this->assertEquals($worker->worker_name, $storedWorker->worker_name);
    }

    public function test_作業者追加の識別番号が空であるため追加失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('worker.store'), [
            'worker_name' => $this->faker->realText(32),
        ]);
        $response->assertSessionHasErrors(['identification_number' => '識別番号は必ず指定してください。']);
    }

    public function test_作業者追加の識別番号が長過ぎるため追加失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('worker.store'), [
            'identification_number' => $this->faker->unique()->realText(33),
            'worker_name' => $this->faker->realText(32),
        ]);
        $response->assertSessionHasErrors(['identification_number' => '識別番号は、32文字以下で指定してください。']);
    }

    public function test_作業者追加の識別番号が重複しているため追加失敗()
    {
        $this->createAdmin();
        $worker = Worker::factory()->create();
        $response = $this->post(route('worker.store'), [
            'identification_number' => $worker->identification_number,
            'worker_name' => $this->faker->realText(32),
        ]);
        $response->assertSessionHasErrors(['identification_number' => '識別番号の値は既に存在しています。']);
    }

    public function test_作業者追加の作業者名が空であるため追加失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('worker.store'), [
            'identification_number' => $this->faker->unique()->realText(32),
        ]);
        $response->assertSessionHasErrors(['worker_name' => '作業者名は必ず指定してください。']);
    }

    public function test_作業者追加の作業者名が長過ぎるため追加失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('worker.store'), [
            'identification_number' => $this->faker->unique()->realText(32),
            'worker_name' => $this->faker->realText(33),
        ]);
        $response->assertSessionHasErrors(['worker_name' => '作業者名は、32文字以下で指定してください。']);
    }

    public function test_作業者追加のMACアドレスのフォーマットを誤っているため追加失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('worker.store'), [
            'identification_number' => $this->faker->unique()->realText(32),
            'worker_name' => $this->faker->realText(33),
            'mac_address' => 'A',
        ]);
        $response->assertSessionHasErrors(['mac_address' => '有効なMACアドレスを指定してください。']);
    }

    public function test_作業者編集ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $worker = Worker::factory()->create();
        $response = $this->get(route('worker.edit', ['worker' => $worker]));
        $response->assertRedirect('login');
    }

    public function test_作業者編集ページにログインしている状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $worker = Worker::factory()->create();
        $this->get(route('worker.edit', ['worker' => $worker]));
    }

    public function test_作業者編集ページにログインしている状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $worker = Worker::factory()->create();
        $this->assertOk('worker.edit', ['worker' => $worker]);
    }

    public function test_作業者編集をログインしていない状態ではログインページへリダイレクト()
    {
        $worker = Worker::factory()->create();
        $response = $this->put(route('worker.update', ['worker' => $worker]), [
            'identification_number' => $this->faker->unique()->realText(32),
            'worker_name' => $this->faker->realText(32),
        ]);
        $response->assertRedirect('login');
    }

    public function test_作業者編集のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $worker = Worker::factory()->create();
        $this->put(route('worker.update', ['worker' => $worker]), [
            'identification_number' => $this->faker->unique()->realText(32),
            'worker_name' => $this->faker->realText(32),
        ]);
    }

    public function test_作業者編集の識別番号の更新成功()
    {
        $this->createAdmin();
        $worker = Worker::factory()->create();
        $expectedId = $this->faker->unique()->realText(32);
        $response = $this->put(route('worker.update', ['worker' => $worker]), [
            'identification_number' => $expectedId,
            'worker_name' => $worker->worker_name,
        ]);
        $response->assertRedirect(route('worker.index'))
            ->assertSessionHas('toast_success', '作業者の更新に成功しました。');

        $updatedWorker = Worker::find($worker->worker_id);
        $this->assertEquals($expectedId, $updatedWorker->identification_number);
        $this->assertEquals($worker->worker_name, $updatedWorker->worker_name);
    }

    public function test_作業者編集の作業者名の更新成功()
    {
        $this->createAdmin();
        $worker = Worker::factory()->create();
        $expectedWorkerName = $this->faker->realText(32);
        $response = $this->put(route('worker.update', ['worker' => $worker]), [
            'identification_number' => $worker->identification_number,
            'worker_name' => $expectedWorkerName,
        ]);
        $response->assertRedirect(route('worker.index'))
            ->assertSessionHas('toast_success', '作業者の更新に成功しました。');

        $updatedWorker = Worker::find($worker->worker_id);
        $this->assertEquals($worker->identification_number, $updatedWorker->identification_number);
        $this->assertEquals($expectedWorkerName, $updatedWorker->worker_name);
    }

    public function test_作業者編集の作業者名が同じでも更新成功()
    {
        $this->createAdmin();
        $worker1 = Worker::factory()->create();
        $worker2 = Worker::factory()->create();
        $response = $this->put(route('worker.update', ['worker' => $worker2]), [
            'identification_number' => $worker2->identification_number,
            'worker_name' => $worker1->worker_name,
        ]);
        $response->assertRedirect(route('worker.index'))
            ->assertSessionHas('toast_success', '作業者の更新に成功しました。');

        $updatedWorker = Worker::find($worker2->worker_id);
        $this->assertEquals($worker2->identification_number, $updatedWorker->identification_number);
        $this->assertEquals($worker1->worker_name, $updatedWorker->worker_name);
    }

    public function test_作業者編集の識別番号が空であるため更新失敗()
    {
        $this->createAdmin();
        $worker = Worker::factory()->create();
        $response = $this->put(route('worker.update', ['worker' => $worker]), [
            'worker_name' => $this->faker->realText(32),
        ]);
        $response->assertSessionHasErrors(['identification_number' => '識別番号は必ず指定してください。']);
    }

    public function test_作業者編集の識別番号が長すぎるため更新失敗()
    {
        $this->createAdmin();
        $worker = Worker::factory()->create();
        $response = $this->put(route('worker.update', ['worker' => $worker]), [
            'identification_number' => $this->faker->realText(33),
            'worker_name' => $this->faker->realText(32),
        ]);
        $response->assertSessionHasErrors(['identification_number' => '識別番号は、32文字以下で指定してください。']);
    }

    public function test_作業者編集の識別番号が重複しているため更新失敗()
    {
        $this->createAdmin();
        $worker1 = Worker::factory()->create();
        $worker2 = Worker::factory()->create();
        $response = $this->put(route('worker.update', ['worker' => $worker2]), [
            'identification_number' => $worker1->identification_number,
            'worker_name' => $this->faker->realText(32),
        ]);
        $response->assertSessionHasErrors(['identification_number' => '識別番号の値は既に存在しています。']);
    }

    public function test_作業者編集の作業者名が空であるため更新失敗()
    {
        $this->createAdmin();
        $worker = Worker::factory()->create();
        $response = $this->put(route('worker.update', ['worker' => $worker]), [
            'identification_number' => $this->faker->realText(32),
        ]);
        $response->assertSessionHasErrors(['worker_name' => '作業者名は必ず指定してください。']);
    }

    public function test_作業者編集の作業者名が長すぎるため更新失敗()
    {
        $this->createAdmin();
        $worker = Worker::factory()->create();
        $response = $this->put(route('worker.update', ['worker' => $worker]), [
            'identification_number' => $this->faker->realText(32),
            'worker_name' => $this->faker->realText(33),
        ]);
        $response->assertSessionHasErrors(['worker_name' => '作業者名は、32文字以下で指定してください。']);
    }

    public function test_作業者削除をログインしていない状態ではログインページへリダイレクト()
    {
        $worker = Worker::factory()->create();
        $response = $this->delete(route('worker.destroy', ['worker' => $worker]));
        $response->assertRedirect('login');
    }

    public function test_作業者削除のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $worker = Worker::factory()->create();
        $this->delete(route('worker.destroy', ['worker' => $worker]));
    }

    public function test_作業者削除の成功()
    {
        $this->createAdmin();
        $worker = Worker::factory()->create();
        $response = $this->delete(route('worker.destroy', ['worker' => $worker]));
        $response->assertRedirect(route('worker.index'))
            ->assertSessionHas('toast_success', '作業者の削除に成功しました。');

        $deletedWorker = Worker::find($worker->worker_id);
        $this->assertNull($deletedWorker);
    }
}
