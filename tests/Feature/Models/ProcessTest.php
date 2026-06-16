<?php

namespace Tests\Feature\Models;

use App\Models\Process;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class ProcessTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;
    use WithFaker;

    public function test_工程一覧ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $response = $this->get(route('process.index'));
        $response->assertRedirect('login');
    }

    public function test_工程一覧ページにログインしてる状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $this->assertOk('process.index');
    }

    public function test_工程一覧ページにログインしてる状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $this->assertOk('process.index');
    }

    public function test_工程追加ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $response = $this->get(route('process.create'));
        $response->assertRedirect('login');
    }

    public function test_工程追加ページにログインしている状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $this->get(route('process.create'));
    }

    public function test_工程追加ページにログインしている状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $this->assertOk('process.create');
    }

    public function test_工程追加をログインしていない状態ではログインページへリダイレクト()
    {
        $response = $this->post(route('process.store'), [
            'process_name' => $this->faker->realText(32)
        ]);
        $response->assertRedirect('login');
    }

    public function test_工程追加のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $this->post(route('process.store'), [
            'process_name' => $this->faker->realText(32)
        ]);
    }

    public function test_工程追加の成功1()
    {
        $this->createAdmin();
        $expectedProcessName = $this->faker->realText(32);
        $expectedColor = $this->faker->hexColor;
        $response = $this->post(route('process.store'), [
            'process_name' => $expectedProcessName,
            'plan_color' => $expectedColor,
            'count_switch' => 'on',
            'range' => 60,
        ]);
        $response->assertRedirect(route('process.index'))
            ->assertSessionHas('toast_success', '工程の登録に成功しました。');

        $storedProcess = Process::where('process_name', $expectedProcessName)->first();
        $this->assertEquals($expectedProcessName, $storedProcess->process_name);
        $this->assertEquals($expectedColor, $storedProcess->plan_color);
        $this->assertNull($storedProcess->remark);
        $this->assertTrue($storedProcess->count_switch);
        $this->assertEquals(60, $storedProcess->range);
    }

    public function test_工程追加の成功2()
    {
        $this->createAdmin();
        $expectedProcessName = $this->faker->realText(32);
        $expectedColor = $this->faker->hexColor;
        $expectedRemark = $this->faker->realText(256);
        $response = $this->post(route('process.store'), [
            'process_name' => $expectedProcessName,
            'plan_color' => $expectedColor,
            'count_switch' => 'on',
            'range' => 60,
            'remark' => $expectedRemark,
        ]);
        $response->assertRedirect(route('process.index'))
            ->assertSessionHas('toast_success', '工程の登録に成功しました。');

        $storedProcess = Process::where('process_name', $expectedProcessName)->first();
        $this->assertEquals($expectedProcessName, $storedProcess->process_name);
        $this->assertEquals($expectedColor, $storedProcess->plan_color);
        $this->assertEquals($expectedRemark, $storedProcess->remark);
        $this->assertTrue($storedProcess->count_switch);
        $this->assertEquals(60, $storedProcess->range);
    }

    public function test_工程追加の工程名が空であるため追加失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('process.store'), [
            // 'process_name' => ''
            'plan_color' => $this->faker->hexColor,
        ]);
        $response->assertSessionHasErrors(['process_name' => '工程名は必ず指定してください。']);
    }

    public function test_工程追加の工程名が長すぎるため追加失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('process.store'), [
            'process_name' => $this->faker->realText(33),
            'plan_color' => $this->faker->hexColor,
        ]);
        $response->assertSessionHasErrors(['process_name' => '工程名は、32文字以下で指定してください。']);
    }

    public function test_工程追加の工程名が重複しているため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $response = $this->post(route('process.store'), [
            'process_name' => $process->process_name,
            'plan_color' => $this->faker->hexColor,
        ]);
        $response->assertSessionHasErrors(['process_name' => '工程名の値は既に存在しています。']);
    }

    public function test_工程追加の計画値色が空であるため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $response = $this->post(route('process.store'), [
            'process_name' => $process->process_name,
        ]);
        $response->assertSessionHasErrors(['plan_color' => '計画値色は必ず指定してください。']);
    }

    public function test_工程追加の計画値色のフォーマットを誤っているため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $response = $this->post(route('process.store'), [
            'process_name' => $process->process_name,
            'plan_color' => '#00001G',
        ]);
        $response->assertSessionHasErrors(['plan_color' => '計画値色に正しい形式を指定してください。']);
    }

    public function test_工程追加の備考が長すぎるため追加失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('process.store'), [
            'process_name' => $this->faker->realText(32),
            'plan_color' => $this->faker->hexColor,
            'remark' => $this->faker->realText(257)
        ]);
        $response->assertSessionHasErrors(['remark' => '備考は、256文字以下で指定してください。']);
    }

    public function test_工程詳細ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $response = $this->get(route('process.show', ['process' => $process]));
        $response->assertRedirect('login');
    }

    public function test_工程詳細ページにログインしている状態でアクセスしたguestユーザーはok()
    {
        $this->createUser();
        $process = Process::factory()->create();
        $this->assertOk('process.show', ['process' => $process]);
    }

    public function test_工程詳細ページにログインしている状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $this->assertOk('process.show', ['process' => $process]);
    }

    public function test_工程編集ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $response = $this->get(route('process.edit', ['process' => $process]));
        $response->assertRedirect('login');
    }

    public function test_工程編集ページにログインしている状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $this->get(route('process.edit', ['process' => $process]));
    }

    public function test_工程編集ページにログインしている状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $this->assertOk('process.edit', ['process' => $process]);
    }

    public function test_工程編集をログインしていない状態ではログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $response = $this->put(route('process.update', ['process' => $process]), [
            'process_name' => $this->faker->realText(32),
            'plan_color' => $this->faker->hexColor,
        ]);
        $response->assertRedirect('login');
    }

    public function test_工程編集のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $this->put(route('process.update', ['process' => $process]), [
            'process_name' => $this->faker->realText(32),
            'plan_color' => $this->faker->hexColor,
        ]);
    }

    public function test_工程編集の工程名の更新成功()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $expectedProcessName = $this->faker->realText(32);
        $response = $this->put(route('process.update', ['process' => $process]), [
            'process_name' => $expectedProcessName,
            'plan_color' => $process->plan_color,
            'count_switch' => 'on',
            'range' => 60,
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process]))
            ->assertSessionHas('toast_success', '工程の更新に成功しました。');

        $updatedProcess = Process::find($process->process_id);
        $this->assertEquals($expectedProcessName, $updatedProcess->process_name);
        $this->assertEquals($process->plan_color, $updatedProcess->plan_color);
        $this->assertEquals($process->remark, $updatedProcess->remark);
    }

    public function test_工程編集の計画値色の更新成功()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $expectedColor = $this->faker->hexColor;
        $response = $this->put(route('process.update', ['process' => $process]), [
            'process_name' => $process->process_name,
            'plan_color' => $expectedColor,
            'count_switch' => 'on',
            'range' => 60,
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process]))
            ->assertSessionHas('toast_success', '工程の更新に成功しました。');

        $updatedProcess = Process::find($process->process_id);
        $this->assertEquals($process->process_name, $updatedProcess->process_name);
        $this->assertEquals($expectedColor, $updatedProcess->plan_color);
        $this->assertEquals($process->remark, $updatedProcess->remark);
    }

    public function test_工程編集の備考の更新成功()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $expectedRemark = $this->faker->realText(256);
        $response = $this->put(route('process.update', ['process' => $process]), [
            'process_name' => $process->process_name,
            'plan_color' => $process->plan_color,
            'count_switch' => 'on',
            'range' => 60,
            'remark' => $expectedRemark,
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process]))
            ->assertSessionHas('toast_success', '工程の更新に成功しました。');

        $updatedProcess = Process::find($process->process_id);
        $this->assertEquals($process->process_name, $updatedProcess->process_name);
        $this->assertEquals($process->plan_color, $updatedProcess->plan_color);
        $this->assertEquals($expectedRemark, $updatedProcess->remark);
    }

    public function test_工程編集の工程名が同じでも更新成功()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $response = $this->put(route('process.update', ['process' => $process]), [
            'process_name' => $process->process_name,
            'plan_color' => $process->plan_color,
            'count_switch' => 'on',
            'range' => 60,
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process]))
            ->assertSessionHas('toast_success', '工程の更新に成功しました。');

        $updatedProcess = Process::find($process->process_id);
        $this->assertEquals($process->process_name, $updatedProcess->process_name);
        $this->assertEquals($process->plan_color, $updatedProcess->plan_color);
        $this->assertEquals($process->remark, $updatedProcess->remark);
    }

    public function test_工程編集の工程名が空であるため更新失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $response = $this->put(route('process.update', ['process' => $process]), [
            'process_name' => '',
            'plan_color' => $this->faker->hexColor,
        ]);
        $response->assertSessionHasErrors(['process_name' => '工程名は必ず指定してください。']);
    }

    public function test_工程編集の工程名が長すぎるため更新失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $response = $this->put(route('process.update', ['process' => $process]), [
            'process_name' => $this->faker->realText(33),
            'plan_color' => $this->faker->hexColor,
        ]);
        $response->assertSessionHasErrors(['process_name' => '工程名は、32文字以下で指定してください。']);
    }

    public function test_工程編集の工程名が重複しているため更新失敗()
    {
        $this->createAdmin();
        $process1 = Process::factory()->create();
        $process2 = Process::factory()->create();
        $response = $this->put(route('process.update', ['process' => $process1]), [
            'process_name' => $process2->process_name,
            'plan_color' => $this->faker->hexColor,
        ]);
        $response->assertSessionHasErrors(['process_name' => '工程名の値は既に存在しています。']);
    }

    public function test_工程編集の計画値色が空であるため更新失敗()
    {
        $this->createAdmin();
        $process1 = Process::factory()->create();
        $process2 = Process::factory()->create();
        $response = $this->put(route('process.update', ['process' => $process1]), [
            'process_name' => $process2->process_name,
            // 'plan_color' => $this->faker->hexColor,
        ]);
        $response->assertSessionHasErrors(['plan_color' => '計画値色は必ず指定してください。']);
    }

    public function test_工程編集の計画値色のフォーマットを誤っているため更新失敗()
    {
        $this->createAdmin();
        $process1 = Process::factory()->create();
        $process2 = Process::factory()->create();
        $response = $this->put(route('process.update', ['process' => $process1]), [
            'process_name' => $process2->process_name,
            'plan_color' => '012345',
        ]);
        $response->assertSessionHasErrors(['plan_color' => '計画値色に正しい形式を指定してください。']);
    }

    public function test_工程編集の備考が長すぎるためため更新失敗()
    {
        $this->createAdmin();
        $process1 = Process::factory()->create();
        $process2 = Process::factory()->create();
        $response = $this->put(route('process.update', ['process' => $process1]), [
            'process_name' => $process2->process_name,
            'plan_color' => $this->faker->hexColor,
            'remark' => $this->faker->realText(257),
        ]);
        $response->assertSessionHasErrors(['remark' => '備考は、256文字以下で指定してください。']);
    }

    public function test_工程削除をログインしていない状態ではログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $response = $this->delete(route('process.destroy', ['process' => $process]));
        $response->assertRedirect('login');
    }

    public function test_工程削除のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $this->delete(route('process.destroy', ['process' => $process]));
    }

    public function test_工程削除の成功()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $response = $this->delete(route('process.destroy', ['process' => $process]));
        $response->assertRedirect(route('process.index'))
            ->assertSessionHas('toast_success', '工程の削除に成功しました。');

        $deletedProcess = Process::find($process->process_id);
        $this->assertNull($deletedProcess);
    }
}
