<?php

namespace Tests\Feature\Models;

use App\Models\PartNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class PartNumberTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;
    use WithFaker;

    public function test_品番一覧ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $response = $this->get(route('part-number.index'));
        $response->assertRedirect('login');
    }

    public function test_品番一覧ページにログインしてる状態でアクセスしたguestユーザーはok()
    {
        $this->createUser();
        $this->assertOk('part-number.index');
    }

    public function test_品番一覧ページにログインしてる状態でアクセスした場合はok()
    {
        $this->createAdmin();
        $this->assertOk('part-number.index');
    }

    public function test_品番追加ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $response = $this->get(route('part-number.create'));
        $response->assertRedirect('login');
    }

    public function test_品番追加ページにログインしている状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $this->get(route('part-number.create'));
    }

    public function test_品番追加ページにログインしている状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $this->assertOk('part-number.create');
    }

    public function test_品番追加をログインしていない状態ではログインページへリダイレクト()
    {
        $response = $this->post(route('part-number.store'), [
            'part_number_name' => $this->faker->unique()->realText(16),
        ]);
        $response->assertRedirect('login');
    }

    public function test_品番追加のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $this->post(route('part-number.store'), [
            'part_number_name' => $this->faker->unique()->realText(16),
        ]);
    }

    public function test_品番追加の成功1()
    {
        $this->createAdmin();
        $expectedName = $this->faker->unique()->realText(16);
        $response = $this->post(route('part-number.store'), [
            'part_number_name' => $expectedName,
        ]);
        $response->assertRedirect(route('part-number.index'))
            ->assertSessionHas('toast_success', '品番の登録に成功しました。');

        $storedPartNumber = PartNumber::where('part_number_name', $expectedName)->first();
        $this->assertEquals($expectedName, $storedPartNumber->part_number_name);
        $this->assertNull($storedPartNumber->remark);
    }

    public function test_品番追加の成功2()
    {
        $this->createAdmin();
        $expectedName = $this->faker->unique()->realText(16);
        $expectedRemark = $this->faker->realText(256);
        $response = $this->post(route('part-number.store'), [
            'part_number_name' => $expectedName,
            'remark' => $expectedRemark,
        ]);
        $response->assertRedirect(route('part-number.index'))
            ->assertSessionHas('toast_success', '品番の登録に成功しました。');

        $storedPartNumber = PartNumber::where('part_number_name', $expectedName)->first();
        $this->assertEquals($expectedName, $storedPartNumber->part_number_name);
        $this->assertEquals($expectedRemark, $storedPartNumber->remark);
    }

    public function test_品番追加の品番名が空であるため追加失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('part-number.store'), [
            'part_number_name' => ''
        ]);
        $response->assertSessionHasErrors(['part_number_name' => '品番名は必ず指定してください。']);
    }

    public function test_品番追加の品番名が重複しているため追加失敗()
    {
        $this->createAdmin();
        $partNumber = PartNumber::factory()->create();
        $response = $this->post(route('part-number.store'), [
            'part_number_name' => $partNumber->part_number_name
        ]);
        $response->assertSessionHasErrors(['part_number_name' => '品番名の値は既に存在しています。']);
    }

    public function test_品番追加の備考が長すぎるため追加失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('process.store'), [
            'part_number_name' => $this->faker->unique()->realText(16),
            'remark' => $this->faker->realText(257)
        ]);
        $response->assertSessionHasErrors(['remark' => '備考は、256文字以下で指定してください。']);
    }

    public function test_品番編集ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $partNumber = PartNumber::factory()->create();
        $response = $this->get(route('part-number.edit', ['partNumber' => $partNumber]));
        $response->assertRedirect('login');
    }

    public function test_品番編集ページにログインしている状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $partNumber = PartNumber::factory()->create();
        $this->get(route('part-number.edit', ['partNumber' => $partNumber]));
    }

    public function test_品番編集ページにログインしている状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $partNumber = PartNumber::factory()->create();
        $this->assertOk('part-number.edit', ['partNumber' => $partNumber]);
    }

    public function test_品番編集をログインしていない状態ではログインページへリダイレクト()
    {
        $partNumber = PartNumber::factory()->create();
        $response = $this->put(route('part-number.update', ['partNumber' => $partNumber]), [
            'part_number_name' => $this->faker->unique()->realText(16),
        ]);
        $response->assertRedirect('login');
    }

    public function test_品番編集のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $partNumber = PartNumber::factory()->create();
        $this->put(route('part-number.update', ['partNumber' => $partNumber]), [
            'part_number_name' => $this->faker->unique()->realText(16),
        ]);
    }

    public function test_品番編集の品番名の更新成功()
    {
        $this->createAdmin();
        $partNumber = PartNumber::factory()->create();
        $expectedName = $this->faker->unique()->realText(16);
        $response = $this->put(route('part-number.update', ['partNumber' => $partNumber]), [
            'part_number_name' => $expectedName,
        ]);
        $response->assertRedirect(route('part-number.index'))
            ->assertSessionHas('toast_success', '品番の更新に成功しました。');

        $updatedPartNumber = PartNumber::find($partNumber->part_number_id);
        $this->assertEquals($expectedName, $updatedPartNumber->part_number_name);
    }

    public function test_品番編集の品番名が同じでも更新成功()
    {
        $this->createAdmin();
        $partNumber = PartNumber::factory()->create();
        $response = $this->put(route('part-number.update', ['partNumber' => $partNumber]), [
            'part_number_name' => $partNumber->part_number_name
        ]);
        $response->assertRedirect(route('part-number.index'))
            ->assertSessionHas('toast_success', '品番の更新に成功しました。');

        $updatedPartNumber = PartNumber::find($partNumber->part_number_id);
        $this->assertEquals($partNumber->part_number_name, $updatedPartNumber->part_number_name);
    }

    public function test_品番編集の備考の更新成功()
    {
        $this->createAdmin();
        $partNumber = PartNumber::factory()->create();
        $expectedRemark = $this->faker->realText(256);
        $response = $this->put(route('part-number.update', ['partNumber' => $partNumber]), [
            'part_number_name' => $partNumber->part_number_name,
            'remark' => $expectedRemark,
        ]);
        $response->assertRedirect(route('part-number.index'))
            ->assertSessionHas('toast_success', '品番の更新に成功しました。');

        $updatedPartNumber = PartNumber::find($partNumber->part_number_id);
        $this->assertEquals($partNumber->part_number_name, $updatedPartNumber->part_number_name);
        $this->assertEquals($expectedRemark, $updatedPartNumber->remark);
    }

    public function test_品番編集の品番名が空であるため更新失敗()
    {
        $this->createAdmin();
        $partNumber = PartNumber::factory()->create();
        $response = $this->put(route('part-number.update', ['partNumber' => $partNumber]), [
            'part_number_name' => ''
        ]);
        $response->assertSessionHasErrors(['part_number_name' => '品番名は必ず指定してください。']);
    }

    public function test_品番編集の品番名が長すぎるため更新失敗()
    {
        $this->createAdmin();
        $partNumber = PartNumber::factory()->create();
        $response = $this->put(route('part-number.update', ['partNumber' => $partNumber]), [
            'part_number_name' => str_repeat('a', 33),
        ]);
        $response->assertSessionHasErrors(['part_number_name' => '品番名は、32文字以下で指定してください。']);
    }

    public function test_品番編集の品番名が重複しているため更新失敗()
    {
        $this->createAdmin();
        $partNumber1 = PartNumber::factory()->create();
        $partNumber2 = PartNumber::factory()->create();
        $response = $this->put(route('part-number.update', ['partNumber' => $partNumber1]), [
            'part_number_name' => $partNumber2->part_number_name
        ]);
        $response->assertSessionHasErrors(['part_number_name' => '品番名の値は既に存在しています。']);
    }

    public function test_品番編集の備考が長過ぎるため更新失敗()
    {
        $this->createAdmin();
        $partNumber = PartNumber::factory()->create();
        $response = $this->put(route('part-number.update', ['partNumber' => $partNumber]), [
            'part_number_name' => $this->faker->unique()->realText(16),
            'remark' => $this->faker->realText(257),
        ]);
        $response->assertSessionHasErrors(['remark' => '備考は、256文字以下で指定してください。']);
    }

    public function test_品番削除をログインしていない状態ではログインページへリダイレクト()
    {
        $partNumber = PartNumber::factory()->create();
        $response = $this->delete(route('part-number.destroy', ['partNumber' => $partNumber]));
        $response->assertRedirect('login');
    }

    public function test_品番削除のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $partNumber = PartNumber::factory()->create();
        $this->delete(route('part-number.destroy', ['partNumber' => $partNumber]));
    }

    public function test_品番削除の成功()
    {
        $this->createAdmin();
        $partNumber = PartNumber::factory()->create();
        $response = $this->delete(route('part-number.destroy', ['partNumber' => $partNumber]));
        $response->assertRedirect(route('part-number.index'))
            ->assertSessionHas('toast_success', '品番の削除に成功しました。');

        $deletedPartNumber = PartNumber::find($partNumber->part_number_name);
        $this->assertNull($deletedPartNumber);
    }
}
