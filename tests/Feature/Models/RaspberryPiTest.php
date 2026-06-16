<?php

namespace Tests\Feature\Models;

use App\Models\RaspberryPi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class RaspberryPiTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;
    use WithFaker;

    public function test_ラズパイ一覧ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $response = $this->get(route('raspberry-pi.index'));
        $response->assertRedirect('login');
    }

    public function test_ラズパイ一覧ページにログインしてる状態でアクセスしたguestユーザーはok()
    {
        $this->createUser();
        $this->assertOk('raspberry-pi.index');
    }

    public function test_ラズパイ一覧ページにログインしてる状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $this->assertOk('raspberry-pi.index');
    }

    public function test_ラズパイ追加ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $response = $this->get(route('raspberry-pi.create'));
        $response->assertRedirect('login');
    }

    public function test_ラズパイ追加ページにログインしている状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $this->get(route('raspberry-pi.create'));
    }

    public function test_ラズパイ追加ページにログインしている状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $this->assertOk('raspberry-pi.create');
    }

    public function test_ラズパイ追加をログインしていない状態ではログインページへリダイレクト()
    {
        $response = $this->post(route('raspberry-pi.store'), [
            'raspberry_pi_name' => $this->faker->unique()->realText(32),
            'ip_address' => $this->faker->unique()->ipv4(),
        ]);
        $response->assertRedirect('login');
    }

    public function test_ラズパイ追加のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $this->post(route('raspberry-pi.store'), [
            'raspberry_pi_name' => $this->faker->unique()->realText(32),
            'ip_address' => $this->faker->unique()->ipv4(),
        ]);
    }

    public function test_ラズパイ追加の成功()
    {
        $this->createAdmin();
        $expectedName = $this->faker->unique()->realText(32);
        $expectedIpAddress = $this->faker->unique()->ipv4();
        $response = $this->post(route('raspberry-pi.store'), [
            'raspberry_pi_name' => $expectedName,
            'ip_address' => $expectedIpAddress,
        ]);
        $response->assertRedirect(route('raspberry-pi.index'))
            ->assertSessionHas('toast_success', 'ラズベリーパイの登録に成功しました。');

        $storedRaspi = RaspberryPi::where('raspberry_pi_name', $expectedName)->first();
        $this->assertEquals($expectedName, $storedRaspi->raspberry_pi_name);
        $this->assertEquals($expectedIpAddress, $storedRaspi->ip_address);
    }

    public function test_ラズパイ追加のラズパイ名が空であるため追加失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('raspberry-pi.store'), [
            'ip_address' => $this->faker->unique()->ipv4(),
        ]);
        $response->assertSessionHasErrors(['raspberry_pi_name' => 'ラズベリーパイ名は必ず指定してください。']);
    }

    public function test_ラズパイ追加のラズパイ名が長過ぎるため追加失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('raspberry-pi.store'), [
            'raspberry_pi_name' => $this->faker->unique()->realText(33),
            'ip_address' => $this->faker->unique()->ipv4(),
        ]);
        $response->assertSessionHasErrors(['raspberry_pi_name' => 'ラズベリーパイ名は、32文字以下で指定してください。']);
    }

    public function test_ラズパイ追加のラズパイ名が重複しているため追加失敗()
    {
        $this->createAdmin();
        $raspi = RaspberryPi::factory()->create();
        $response = $this->post(route('raspberry-pi.store'), [
            'raspberry_pi_name' => $raspi->raspberry_pi_name,
            'ip_address' => $this->faker->unique()->ipv4(),
        ]);
        $response->assertSessionHasErrors(['raspberry_pi_name' => 'ラズベリーパイ名の値は既に存在しています。']);
    }

    public function test_ラズパイ追加のipアドレスが空であるため追加失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('raspberry-pi.store'), [
            'raspberry_pi_name' => $this->faker->unique()->realText(32),
        ]);
        $response->assertSessionHasErrors(['ip_address' => 'IPアドレスは必ず指定してください。']);
    }

    public function test_ラズパイ追加のipアドレスのフォーマットを誤っているため追加失敗()
    {
        $this->createAdmin();
        $response = $this->post(route('raspberry-pi.store'), [
            'raspberry_pi_name' => $this->faker->unique()->realText(32),
            'ip_address' => 'aaa',
        ]);
        $response->assertSessionHasErrors(['ip_address' => '有効なIPアドレスを指定してください。']);
    }

    public function test_ラズパイ追加のipアドレスが重複しているため追加失敗()
    {
        $this->createAdmin();
        $raspi = RaspberryPi::factory()->create();
        $response = $this->post(route('raspberry-pi.store'), [
            'raspberry_pi_name' => $this->faker->unique()->realText(32),
            'ip_address' => $raspi->ip_address,
        ]);
        $response->assertSessionHasErrors(['ip_address' => 'IPアドレスの値は既に存在しています。']);
    }

    public function test_ラズパイ編集ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $raspi = RaspberryPi::factory()->create();
        $response = $this->get(route('raspberry-pi.edit', ['raspberryPi' => $raspi]));
        $response->assertRedirect('login');
    }

    public function test_ラズパイ編集ページにログインしている状態でguestユーザーがアクセスした場合はng()
    {
        $this->createUser();
        $this->expect403();
        $raspi = RaspberryPi::factory()->create();
        $this->put(route('raspberry-pi.update', ['raspberryPi' => $raspi]), [
            'raspberry_pi_name' => $this->faker->unique()->realText(32),
            'ip_address' =>  $this->faker->unique()->ipv4(),
        ]);
    }

    public function test_ラズパイ編集ページにログインしている状態でadminユーザーがアクセスした場合はok()
    {
        $this->createAdmin();
        $raspi = RaspberryPi::factory()->create();
        $this->assertOk('raspberry-pi.edit', ['raspberryPi' => $raspi]);
    }

    public function test_ラズパイ編集をログインしていない状態ではログインページへリダイレクト()
    {
        $raspi = RaspberryPi::factory()->create();
        $response = $this->put(route('raspberry-pi.update', ['raspberryPi' => $raspi]), [
            'raspberry_pi_name' => $this->faker->unique()->realText(32),
            'ip_address' =>  $this->faker->unique()->ipv4(),
        ]);
        $response->assertRedirect('login');
    }

    public function test_ラズパイ編集のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $raspi = RaspberryPi::factory()->create();
        $this->put(route('raspberry-pi.update', ['raspberryPi' => $raspi]), [
            'raspberry_pi_name' => $this->faker->unique()->realText(32),
            'ip_address' =>  $this->faker->unique()->ipv4(),
        ]);
    }

    public function test_ラズパイ編集の更新成功()
    {
        $this->createAdmin();
        $raspi = RaspberryPi::factory()->create();
        $expectedName = $this->faker->unique()->realText(32);
        $expectedIpAddress = $this->faker->unique()->ipv4();
        $response = $this->put(route('raspberry-pi.update', ['raspberryPi' => $raspi]), [
            'raspberry_pi_name' => $expectedName,
            'ip_address' => $expectedIpAddress,
        ]);
        $response->assertRedirect(route('raspberry-pi.index'))
            ->assertSessionHas('toast_success', 'ラズベリーパイの更新に成功しました。');

        $updatedRaspi = RaspberryPi::find($raspi->raspberry_pi_id);
        $this->assertEquals($expectedName, $updatedRaspi->raspberry_pi_name);
        $this->assertEquals($expectedIpAddress, $updatedRaspi->ip_address);
    }

    public function test_ラズパイ編集の更新なしでも更新成功()
    {
        $this->createAdmin();
        $raspi = RaspberryPi::factory()->create();
        $response = $this->put(route('raspberry-pi.update', ['raspberryPi' => $raspi]), [
            'raspberry_pi_name' => $raspi->raspberry_pi_name,
            'ip_address' =>  $raspi->ip_address,
        ]);
        $response->assertRedirect(route('raspberry-pi.index'))
            ->assertSessionHas('toast_success', 'ラズベリーパイの更新に成功しました。');

        $updatedRaspi = RaspberryPi::find($raspi->raspberry_pi_id);
        $this->assertEquals($raspi->raspberry_pi_name, $updatedRaspi->raspberry_pi_name);
        $this->assertEquals($raspi->ip_address, $updatedRaspi->ip_address);
    }

    public function test_ラズパイ編集のラズパイ名が空であるため更新失敗()
    {
        $this->createAdmin();
        $raspi = RaspberryPi::factory()->create();
        $response = $this->put(route('raspberry-pi.update', ['raspberryPi' => $raspi]),[
            // 'raspberry_pi_name' => $this->faker->unique()->realText(32),
            'ip_address' =>  $this->faker->unique()->ipv4(),
        ]);
        $response->assertSessionHasErrors(['raspberry_pi_name' => 'ラズベリーパイ名は必ず指定してください。']);
    }

    public function test_ラズパイ編集のラズパイ名が長過ぎるため更新失敗()
    {
        $this->createAdmin();
        $raspi = RaspberryPi::factory()->create();
        $response = $this->put(route('raspberry-pi.update', ['raspberryPi' => $raspi]),[
            'raspberry_pi_name' => $this->faker->unique()->realText(33),
            'ip_address' =>  $this->faker->unique()->ipv4(),
        ]);
        $response->assertSessionHasErrors(['raspberry_pi_name' => 'ラズベリーパイ名は、32文字以下で指定してください。']);
    }

    public function test_ラズパイ編集のラズパイ名が重複しているため更新失敗()
    {
        $this->createAdmin();
        $raspi1 = RaspberryPi::factory()->create();
        $raspi2 = RaspberryPi::factory()->create();
        $response = $this->put(route('raspberry-pi.update', ['raspberryPi' => $raspi1]),[
            'raspberry_pi_name' => $raspi2->raspberry_pi_name,
            'ip_address' =>  $this->faker->unique()->ipv4(),
        ]);
        $response->assertSessionHasErrors(['raspberry_pi_name' => 'ラズベリーパイ名の値は既に存在しています。']);
    }

    public function test_ラズパイ編集のipアドレスが空であるため更新失敗()
    {
        $this->createAdmin();
        $raspi = RaspberryPi::factory()->create();
        $response = $this->put(route('raspberry-pi.update', ['raspberryPi' => $raspi]),[
            'raspberry_pi_name' => $this->faker->unique()->realText(32),
        ]);
        $response->assertSessionHasErrors(['ip_address' => 'IPアドレスは必ず指定してください。']);
    }

    public function test_ラズパイ編集のipアドレスが重複しているため更新失敗()
    {
        $this->createAdmin();
        $raspi1 = RaspberryPi::factory()->create();
        $raspi2 = RaspberryPi::factory()->create();
        $response = $this->put(route('raspberry-pi.update', ['raspberryPi' => $raspi1]), [
            'raspberry_pi_name' => $this->faker->unique()->realText(32),
            'ip_address' => $raspi2->ip_address,
        ]);
        $response->assertSessionHasErrors(['ip_address' => 'IPアドレスの値は既に存在しています。']);
    }

    public function test_ラズパイ編集のipアドレスのフォーマットを誤っているため更新失敗()
    {
        $this->createAdmin();
        $raspi = RaspberryPi::factory()->create();
        $response = $this->put(route('raspberry-pi.update', ['raspberryPi' => $raspi]), [
            'raspberry_pi_name' => $this->faker->unique()->realText(32),
            'ip_address' => 'aaa'
        ]);
        $response->assertSessionHasErrors(['ip_address' => '有効なIPアドレスを指定してください。']);
    }

    public function test_ラズパイ削除をログインしていない状態ではログインページへリダイレクト()
    {
        $raspi = RaspberryPi::factory()->create();
        $response = $this->delete(route('raspberry-pi.destroy', ['raspberryPi' => $raspi]));
        $response->assertRedirect('login');
    }

    public function test_ラズパイ削除のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $raspi = RaspberryPi::factory()->create();
        $this->delete(route('raspberry-pi.destroy', ['raspberryPi' => $raspi]));
    }

    public function test_ラズパイ削除の成功()
    {
        $this->createAdmin();
        $raspi = RaspberryPi::factory()->create();
        $response = $this->delete(route('raspberry-pi.destroy', ['raspberryPi' => $raspi]));
        $response->assertRedirect(route('raspberry-pi.index'))
            ->assertSessionHas('toast_success', 'ラズベリーパイの削除に成功しました。');

        $deletedRaspi = RaspberryPi::find($raspi->raspberry_pi_id);
        $this->assertNull($deletedRaspi);
    }
}
