<?php

namespace Tests\Feature\Models;

use App\Models\PlannedOutage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class PlannedOutageTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;
    use WithFaker;

    public function test_計画停止時間一覧ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $response = $this->get(route('planned-outage.index'));
        $response->assertRedirect('login');
    }

    public function test_計画停止時間一覧ページにログインしてる状態でアクセスしたguestユーザーはok()
    {
        $this->createUser();
        $this->assertOk('planned-outage.index');
    }

    public function test_計画停止時間一覧ページにログインしてる状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $this->assertOk('planned-outage.index');
    }

    public function test_計画停止時間追加ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $response = $this->get(route('planned-outage.create'));
        $response->assertRedirect('login');
    }

    public function test_計画停止時間追加ページにログインしている状態でアクセスしたadminユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $this->get(route('planned-outage.create'));
    }

    public function test_計画停止時間追加ページにログインしている状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $response = $this->get(route('planned-outage.create'));
        $response->assertok();
    }

    public function test_計画停止時間追加をログインしていない状態ではログインページへリダイレクト()
    {
        $response = $this->post(route('planned-outage.store'), [
            'planned_outage_name' => $this->faker->unique()->realText(16),
            'start_time' => '00:00',
            'end_time' => '23:59'
        ]);
        $response->assertRedirect('login');
    }

    public function test_計画停止時間追加のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $this->post(route('planned-outage.store'), [
            'planned_outage_name' => $this->faker->unique()->realText(16),
            'start_time' => '00:00',
            'end_time' => '23:59'
        ]);
    }

    public function test_計画停止時間追加の成功1()
    {
        $this->createAdmin();
        $expectedName = $this->faker->unique()->realText(16);
        $response = $this->post(route('planned-outage.store'), [
            'planned_outage_name' => $expectedName,
            'start_time' => '00:00',
            'end_time' => '23:59'
        ]);
        $response->assertRedirect(route('planned-outage.index'))
            ->assertSessionHas('toast_success', '計画停止時間の登録に成功しました。');

        $storedPlannedOutage = PlannedOutage::where('planned_outage_name', $expectedName)->first();
        $this->assertEquals($expectedName, $storedPlannedOutage->planned_outage_name);
        $this->assertEquals('00:00', $storedPlannedOutage->formatStartTime());
        $this->assertEquals('23:59', $storedPlannedOutage->formatEndTime());
    }

    public function test_計画停止時間追加の成功2()
    {
        $this->createAdmin();
        $expectedName = $this->faker->unique()->realText(16);
        $response = $this->post(route('planned-outage.store'), [
            'planned_outage_name' => $expectedName,
            'start_time' => '23:59',
            'end_time' => '00:00'
        ]);
        $response->assertRedirect(route('planned-outage.index'))
            ->assertSessionHas('toast_success', '計画停止時間の登録に成功しました。');

        $storedPlannedOutage = PlannedOutage::where('planned_outage_name', $expectedName)->first();
        $this->assertEquals($expectedName, $storedPlannedOutage->planned_outage_name);
        $this->assertEquals('23:59', $storedPlannedOutage->formatStartTime());
        $this->assertEquals('00:00', $storedPlannedOutage->formatEndTime());
    }

    public function test_計画停止時間追加の計画停止時間名が空であるため失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('planned-outage.store'), [
            'start_time' => '00:00',
            'end_time' => '23:59'
        ]);
        $response->assertSessionHasErrors(['planned_outage_name' => '計画停止時間名は必ず指定してください。']);
    }

    public function test_計画停止時間追加の名前が長すぎるため失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('planned-outage.store'), [
            'planned_outage_name' => str_repeat('a', 33),
            'start_time' => '23:59',
            'end_time' => '00:00'
        ]);
        $response->assertSessionHasErrors(['planned_outage_name' => '計画停止時間名は、32文字以下で指定してください。']);
    }

    public function test_計画停止時間追加の名前が重複しているため失敗()
    {
        $this->createAdmin();
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->post(route('planned-outage.store'), [
            'planned_outage_name' => $plannedOutage->planned_outage_name,
            'start_time' => '23:59',
            'end_time' => '00:00'
        ]);
        $response->assertSessionHasErrors(['planned_outage_name' => '計画停止時間名の値は既に存在しています。']);
    }

    public function test_計画停止時間追加の開始時間が空であるため失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('planned-outage.store'), [
            'planned_outage_name' => $this->faker->unique()->realText(16),
            'start_time' => '',
            'end_time' => '23:59'
        ]);
        $response->assertSessionHasErrors(['start_time' => '開始時間は必ず指定してください。']);
    }

    public function test_計画停止時間追加の開始時間のフォーマットを誤っているため失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('planned-outage.store'), [
            'planned_outage_name' => $this->faker->unique()->realText(16),
            'start_time' => '0000',
            'end_time' => '23:59'
        ]);
        $response->assertSessionHasErrors(['start_time' => '開始時間はH:i形式で指定してください。']);
    }

    public function test_計画停止時間追加の終了時間が空であるため失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('planned-outage.store'), [
            'planned_outage_name' => $this->faker->unique()->realText(16),
            'start_time' => '0:00',
            'end_time' => ''
        ]);
        $response->assertSessionHasErrors(['end_time' => '終了時間は必ず指定してください。']);
    }

    public function test_計画停止時間追加の終了時間のフォーマットを誤っているため失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('planned-outage.store'), [
            'planned_outage_name' => $this->faker->unique()->realText(16),
            'start_time' => '00:00',
            'end_time' => '2359'
        ]);
        $response->assertSessionHasErrors(['end_time' => '終了時間はH:i形式で指定してください。']);
    }

    public function test_計画停止時間追加の開始時間と終了時間が等しいため失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('planned-outage.store'), [
            'planned_outage_name' => $this->faker->unique()->realText(16),
            'start_time' => '00:00',
            'end_time' => '00:00'
        ]);
        $response->assertSessionHasErrors(['end_time' => '終了時間と開始時間には、異なった内容を指定してください。']);
    }

    public function test_計画停止時間編集ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->get(route('planned-outage.edit', ['plannedOutage' => $plannedOutage]));
        $response->assertRedirect('login');
    }

    public function test_計画停止時間編集ページにログインしている状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $plannedOutage = PlannedOutage::factory()->create();
        $this->get(route('planned-outage.edit', ['plannedOutage' => $plannedOutage]));
    }

    public function test_計画停止時間編集ページにログインしている状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->get(route('planned-outage.edit', ['plannedOutage' => $plannedOutage]));
        $response->assertok();
    }

    public function test_計画停止時間編集をログインしていない状態ではログインページへリダイレクト()
    {
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->put(route('planned-outage.update', ['plannedOutage' => $plannedOutage]), [
            'planned_outage_name' => $this->faker->unique()->realText(16),
            'start_time' => '00:00',
            'end_time' => '23:59'
        ]);
        $response->assertRedirect('login');
    }

    public function test_計画停止時間更新のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->put(route('planned-outage.update', ['plannedOutage' => $plannedOutage]), [
            'planned_outage_name' => $this->faker->unique()->realText(16),
            'start_time' => '00:00',
            'end_time' => '23:59'
        ]);
    }

    public function test_計画停止時間更新の成功1()
    {
        $this->createAdmin();
        $plannedOutage = PlannedOutage::factory()->create();
        $expectedName = $this->faker->unique()->realText(16);
        $response = $this->put(route('planned-outage.update', ['plannedOutage' => $plannedOutage]), [
            'planned_outage_name' => $expectedName,
            'start_time' => '00:00',
            'end_time' => '23:59'
        ]);
        $response->assertRedirect(route('planned-outage.index'))
            ->assertSessionHas('toast_success', '計画停止時間の更新に成功しました。');

        $updatePlannedOutage = PlannedOutage::find($plannedOutage->planned_outage_id);
        $this->assertEquals($expectedName, $updatePlannedOutage->planned_outage_name);
        $this->assertEquals('00:00', $updatePlannedOutage->formatStartTime());
        $this->assertEquals('23:59', $updatePlannedOutage->formatEndTime());
    }

    public function test_計画停止時間更新の成功2()
    {
        $this->createAdmin();
        $plannedOutage = PlannedOutage::factory()->create();
        $expectedName = $this->faker->unique()->realText(16);
        $response = $this->put(route('planned-outage.update', ['plannedOutage' => $plannedOutage]), [
            'planned_outage_name' => $expectedName,
            'start_time' => '23:59',
            'end_time' => '00:00'
        ]);
        $response->assertRedirect(route('planned-outage.index'))
            ->assertSessionHas('toast_success', '計画停止時間の更新に成功しました。');

        $updatePlannedOutage = PlannedOutage::find($plannedOutage->planned_outage_id);
        $this->assertEquals($expectedName, $updatePlannedOutage->planned_outage_name);
        $this->assertEquals('23:59', $updatePlannedOutage->formatStartTime());
        $this->assertEquals('00:00', $updatePlannedOutage->formatEndTime());
    }

    public function test_計画停止時間更新の成功3()
    {
        $this->createAdmin();
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->put(route('planned-outage.update', ['plannedOutage' => $plannedOutage]), [
            'planned_outage_name' => $plannedOutage->planned_outage_name,
            'start_time' => '23:59',
            'end_time' => '00:00'
        ]);
        $response->assertRedirect(route('planned-outage.index'))
            ->assertSessionHas('toast_success', '計画停止時間の更新に成功しました。');

        $updatePlannedOutage = PlannedOutage::find($plannedOutage->planned_outage_id);
        $this->assertEquals($plannedOutage->planned_outage_name, $updatePlannedOutage->planned_outage_name);
        $this->assertEquals('23:59', $updatePlannedOutage->formatStartTime());
        $this->assertEquals('00:00', $updatePlannedOutage->formatEndTime());
    }

    public function test_計画停止時間更新の計画停止時間名が空であるため失敗()
    {
        $this->createAdmin();
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->put(route('planned-outage.update', ['plannedOutage' => $plannedOutage]), [
            'start_time' => '00:00',
            'end_time' => '23:59'
        ]);
        $response->assertSessionHasErrors(['planned_outage_name' => '計画停止時間名は必ず指定してください。']);
    }

    public function test_計画停止時間更新の名前が長過ぎるため更新失敗()
    {
        $this->createAdmin();
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->put(route('planned-outage.update', ['plannedOutage' => $plannedOutage]), [
            'planned_outage_name' => str_repeat('a', 33),
            'start_time' => '00:00',
            'end_time' => '23:59'
        ]);
        $response->assertSessionHasErrors(['planned_outage_name' => '計画停止時間名は、32文字以下で指定してください。']);
    }

    public function test_計画停止時間更新の名前が重複しているため更新失敗()
    {
        $this->createAdmin();
        $plannedOutage1 = PlannedOutage::factory()->create();
        $plannedOutage2 = PlannedOutage::factory()->create();
        $response = $this->put(route('planned-outage.update', ['plannedOutage' => $plannedOutage1]), [
            'planned_outage_name' => $plannedOutage2->planned_outage_name,
            'start_time' => '00:00',
            'end_time' => '23:59'
        ]);
        $response->assertSessionHasErrors(['planned_outage_name' => '計画停止時間名の値は既に存在しています。']);
    }

    public function test_計画停止時間更新の開始時間が空であるため失敗()
    {
        $this->createAdmin();
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->put(route('planned-outage.update', ['plannedOutage' => $plannedOutage]), [
            'planned_outage_name' => $this->faker->unique()->realText(16),
            'start_time' => '',
            'end_time' => '23:59'
        ]);
        $response->assertSessionHasErrors(['start_time' => '開始時間は必ず指定してください。']);
    }

    public function test_計画停止時間更新の開始時間のフォーマットを誤っているため失敗()
    {
        $this->createAdmin();
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->put(route('planned-outage.update', ['plannedOutage' => $plannedOutage]), [
            'planned_outage_name' => $this->faker->unique()->realText(16),
            'start_time' => '0000',
            'end_time' => '23:59'
        ]);
        $response->assertSessionHasErrors(['start_time' => '開始時間はH:i形式で指定してください。']);
    }

    public function test_計画停止時間更新の終了時間が空であるため失敗()
    {
        $this->createAdmin();
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->put(route('planned-outage.update', ['plannedOutage' => $plannedOutage]), [
            'planned_outage_name' => $this->faker->unique()->realText(16),
            'start_time' => '00:00',
            'end_time' => ''
        ]);
        $response->assertSessionHasErrors(['end_time' => '終了時間は必ず指定してください。']);
    }

    public function test_計画停止時間更新の終了時間のフォーマットを誤っているため失敗()
    {
        $this->createAdmin();
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->put(route('planned-outage.update', ['plannedOutage' => $plannedOutage]), [
            'planned_outage_name' => $this->faker->unique()->realText(16),
            'start_time' => '00:00',
            'end_time' => '2359'
        ]);
        $response->assertSessionHasErrors(['end_time' => '終了時間はH:i形式で指定してください。']);
    }

    public function test_計画停止時間更新の開始時間と終了時間が等しいため失敗()
    {
        $this->createAdmin();
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->put(route('planned-outage.update', ['plannedOutage' => $plannedOutage]), [
            'planned_outage_name' => $this->faker->unique()->realText(16),
            'start_time' => '9:00',
            'end_time' => '9:00'
        ]);
        $response->assertSessionHasErrors(['end_time' => '終了時間と開始時間には、異なった内容を指定してください。']);
    }

    public function test_計画停止時間削除をログインしていない状態ではログインページへリダイレクト()
    {
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->delete(route('planned-outage.destroy', ['plannedOutage' => $plannedOutage]));
        $response->assertRedirect('login');
    }

    public function test_計画停止時間削除のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $plannedOutage = PlannedOutage::factory()->create();
        $this->delete(route('planned-outage.destroy', ['plannedOutage' => $plannedOutage]));
    }

    public function test_計画停止時間削除の成功()
    {
        $this->createAdmin();
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->delete(route('planned-outage.destroy', ['plannedOutage' => $plannedOutage]));
        $response->assertRedirect(route('planned-outage.index'))
            ->assertSessionHas('toast_success', '計画停止時間の削除に成功しました。');

        $deletedPlannedOutage = PlannedOutage::find($plannedOutage->planned_outage_id);
        $this->assertNull($deletedPlannedOutage);
    }
}
