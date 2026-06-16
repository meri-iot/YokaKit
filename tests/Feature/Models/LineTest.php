<?php

namespace Tests\Feature\Models;

use App\Models\Line;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class LineTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;
    use WithFaker;

    public function test_ライン追加ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $response = $this->get(route('line.create', ['process' => $process]));
        $response->assertRedirect('login');
    }

    public function test_ライン追加ページにログインしてる状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $this->get(route('line.create', ['process' => $process]));
    }

    public function test_ライン追加ページにログインしてる状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $this->assertOk('line.create', ['process' => $process]);
    }

    public function test_ライン追加をログインしていない状態ではログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $response = $this->post(route('line.store', ['process' => $process]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
        ]);
        $response->assertRedirect('login');
    }

    public function test_ライン追加のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $this->post(route('line.store', ['process' => $process]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
    }

    public function test_ライン追加の成功1()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $expectedName = $this->faker->unique()->realText(32);
        $expectedColor = $this->faker->hexColor;
        $expectedPin = $this->faker->numberBetween(2, 27);
        $response = $this->post(route('line.store', ['process' => $process]), [
            'line_name' => $expectedName,
            'chart_color' => $expectedColor,
            'pin_number' => $expectedPin,
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process, 'tab' => 'line']))
            ->assertSessionHas('toast_success', '作業の登録に成功しました。');

        $storedLine = Line::where('line_name', $expectedName)->where('process_id', $process->process_id)->first();
        $this->assertEquals($expectedName, $storedLine->line_name);
        $this->assertEquals($expectedColor, $storedLine->chart_color);
        $this->assertEquals($expectedPin, $storedLine->pin_number);
        $this->assertEquals($process->process_id, $storedLine->process_id);
        $this->assertEquals($raspi->raspberry_pi_id, $storedLine->raspberry_pi_id);
        $this->assertEquals($worker->worker_id, $storedLine->worker_id);
        $this->assertEquals(false, $storedLine->defective);
    }

    public function test_ライン追加の成功2()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $parentLine = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'defective' => false,
            'worker_id' => null,
        ]);
        $expectedName = $this->faker->unique()->realText(32);
        $expectedColor = $this->faker->hexColor;
        $expectedPin = $this->faker->numberBetween(2, 27);
        $response = $this->post(route('line.store', ['process' => $process]), [
            'line_name' => $expectedName,
            'chart_color' => $expectedColor,
            'pin_number' => $expectedPin,
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'defective' => 'on',
            'parent_id' => $parentLine->line_id,
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process, 'tab' => 'line']))
            ->assertSessionHas('toast_success', '作業の登録に成功しました。');

        $storedLine = Line::where('line_name', $expectedName)->where('process_id', $process->process_id)->first();
        $this->assertEquals($expectedName, $storedLine->line_name);
        $this->assertEquals($expectedColor, $storedLine->chart_color);
        $this->assertEquals($expectedPin, $storedLine->pin_number);
        $this->assertEquals($process->process_id, $storedLine->process_id);
        $this->assertEquals($raspi->raspberry_pi_id, $storedLine->raspberry_pi_id);
        $this->assertNull($storedLine->worker_id);
        $this->assertEquals(true, $storedLine->defective);
    }

    public function test_ライン追加の別工程に同一ライン名があっても成功()
    {
        $this->createAdmin();
        $process1 = Process::factory()->create();
        $process2 = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $expectedColor = $this->faker->hexColor;
        $expectedPin = $this->faker->numberBetween(2, 27);
        $line = Line::factory()->create([
            'process_id' => $process1->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->post(route('line.store', ['process' => $process2]), [
            'line_name' => $line->line_name,
            'chart_color' => $expectedColor,
            'pin_number' => $expectedPin,
            'process_id' => $process2->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => null,
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process2, 'tab' => 'line']))
            ->assertSessionHas('toast_success', '作業の登録に成功しました。');

        $storedLine = Line::where('line_name', $line->line_name)->where('process_id', $process2->process_id)->first();
        $this->assertEquals($line->line_name, $storedLine->line_name);
        $this->assertEquals($expectedColor, $storedLine->chart_color);
        $this->assertEquals($expectedPin, $storedLine->pin_number);
        $this->assertEquals($process2->process_id, $storedLine->process_id);
        $this->assertEquals($raspi->raspberry_pi_id, $storedLine->raspberry_pi_id);
        $this->assertNull($storedLine->worker_id);
    }

    public function test_ライン追加のライン名が空であるため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $response = $this->post(route('line.store', ['process' => $process]), [
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['line_name' => 'ライン名は必ず指定してください。']);
    }

    public function test_ライン追加のライン名が長過ぎるため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $response = $this->post(route('line.store', ['process' => $process]), [
            'line_name' => $this->faker->unique()->realText(33),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['line_name' => 'ライン名は、32文字以下で指定してください。']);
    }

    public function test_ライン追加の同一工程に同じライン名が存在するため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->post(route('line.store', ['process' => $process]), [
            'line_name' => $line->line_name,
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['line_name' => 'ライン名の値は既に存在しています。']);
    }

    public function test_ライン追加の色が空であるため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $response = $this->post(route('line.store', ['process' => $process]), [
            'line_name' => $this->faker->unique()->realText(32),
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['chart_color' => '色は必ず指定してください。']);
    }

    public function test_ライン追加の色の入力フォーマットを誤っていたため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $response = $this->post(route('line.store', ['process' => $process]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => '#00001G',
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['chart_color' => '色に正しい形式を指定してください。']);
    }

    public function test_ライン追加のラズパイidが空のため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $worker = Worker::factory()->create();
        $response = $this->post(route('line.store', ['process' => $process]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['raspberry_pi_id' => 'ラズベリーパイは必ず指定してください。']);
    }

    public function test_ライン追加のラズパイidが存在しないため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $worker = Worker::factory()->create();
        $response = $this->post(route('line.store', ['process' => $process]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => 1000000,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['raspberry_pi_id' => '選択されたラズベリーパイは正しくありません。']);
    }

    public function test_ライン追加の作業者idが存在しないため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $response = $this->post(route('line.store', ['process' => $process]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => 1000000,
        ]);
        $response->assertSessionHasErrors(['worker_id' => '選択された作業者は正しくありません。']);
    }

    public function test_ライン追加のピン番号が空であるため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $response = $this->post(route('line.store', ['process' => $process]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['pin_number' => 'ピン番号は必ず指定してください。']);
    }

    public function test_ライン追加のピン番号が数値ではないため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $response = $this->post(route('line.store', ['process' => $process]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => 'A',
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['pin_number' => 'ピン番号は整数で指定してください。']);
    }

    public function test_ライン追加のピン番号が1以下であるため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $response = $this->post(route('line.store', ['process' => $process]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => -1,
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['pin_number' => 'ピン番号には、0以上の数字を指定してください。']);
    }

    public function test_ライン追加のピン番号が28以上であるため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $response = $this->post(route('line.store', ['process' => $process]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => 128,
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['pin_number' => 'ピン番号には、127以下の数字を指定してください。']);
    }

    public function test_ライン追加のラズパイidとピン番号が重複しているため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->post(route('line.store', ['process' => $process]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $line->pin_number,
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['pin_number' => 'ピン番号の値は既に存在しています。']);
    }

    public function test_ライン編集ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->get(route('line.edit', ['process' => $process, 'line' => $line]));
        $response->assertRedirect('login');
    }

    public function test_ライン編集ページにログインしている状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $this->get(route('line.edit', ['process' => $process, 'line' => $line]));
    }

    public function test_ライン編集ページにログインしている状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $this->assertOk('line.edit', ['process' => $process, 'line' => $line]);
    }

    public function test_ライン編集をログインしていない場合はログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->put(route('line.update', ['process' => $process, 'line' => $line]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
        ]);
        $response->assertRedirect('login');
    }

    public function test_ライン編集のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $this->put(route('line.update', ['process' => $process, 'line' => $line]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
        ]);
    }

    public function test_ライン編集の成功()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
            'pin_number' => 2,
        ]);
        $parentLine = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'defective' => false,
            'worker_id' => null,
            'pin_number' => 3,
        ]);
        $expectedName = $this->faker->unique()->realText(32);
        $expectedColor = $this->faker->hexColor;
        $usedPins = Line::query()
            ->where('raspberry_pi_id', $raspi->raspberry_pi_id)
            ->pluck('pin_number')
            ->all();
        $expectedPin = collect(range(0, 127))
            ->first(fn(int $pin): bool => !in_array($pin, $usedPins, true));
        $this->assertNotNull($expectedPin);
        $expectedWorker = Worker::factory()->create();
        $response = $this->put(route('line.update', ['process' => $process, 'line' => $line]), [
            'line_name' => $expectedName,
            'chart_color' => $expectedColor,
            'pin_number' => $expectedPin,
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $expectedWorker->worker_id,
            'defective' => 'on',
            'parent_id' => $parentLine->line_id,
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process, 'tab' => 'line']))
            ->assertSessionHas('toast_success', '作業の更新に成功しました。');

        $updatedLine = Line::find($line->line_id);
        $this->assertEquals($expectedName, $updatedLine->line_name);
        $this->assertEquals($expectedColor, $updatedLine->chart_color);
        $this->assertEquals($expectedPin, $updatedLine->pin_number);
        $this->assertNull($updatedLine->worker_id);
        $this->assertEquals(true, $updatedLine->defective);
    }

    public function test_ライン編集の項目に変化がなくても成功()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->put(route('line.update', ['process' => $process, 'line' => $line]), [
            'line_name' => $line->line_name,
            'chart_color' => $line->chart_color,
            'pin_number' => $line->pin_number,
            'process_id' => $line->process_id,
            'raspberry_pi_id' => $line->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process, 'tab' => 'line']))
            ->assertSessionHas('toast_success', '作業の更新に成功しました。');

        $updatedLine = Line::find($line->line_id);
        $this->assertEquals($line->line_name, $updatedLine->line_name);
        $this->assertEquals($line->chart_color, $updatedLine->chart_color);
        $this->assertEquals($line->pin_number, $updatedLine->pin_number);
        $this->assertEquals($worker->worker_id, $updatedLine->worker_id);
        $this->assertEquals(false, $updatedLine->defective);
    }

    public function test_ライン編集の別工程に同一ライン名があっても成功()
    {
        $this->createAdmin();
        $process1 = Process::factory()->create();
        $process2 = Process::factory()->create();
        $raspi1 = RaspberryPi::factory()->create();
        $raspi2 = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line1 = Line::factory()->create([
            'process_id' => $process1->process_id,
            'raspberry_pi_id' => $raspi1->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $line2 = Line::factory()->create([
            'process_id' => $process2->process_id,
            'raspberry_pi_id' => $raspi2->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $expectedColor = $this->faker->hexColor;
        $expectedPinNumber = $this->faker->numberBetween(2, 27);
        $response = $this->put(route('line.update', ['process' => $process2, 'line' => $line2]), [
            'line_name' => $line1->line_name,
            'chart_color' => $expectedColor,
            'pin_number' => $expectedPinNumber,
            'process_id' => $process2->process_id,
            'raspberry_pi_id' => $raspi2->raspberry_pi_id,
            'worker_id' => null,
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process2, 'tab' => 'line']))
            ->assertSessionHas('toast_success', '作業の更新に成功しました。');

        $updatedLine = Line::find($line2->line_id);
        $this->assertEquals($line1->line_name, $updatedLine->line_name);
        $this->assertEquals($expectedColor, $updatedLine->chart_color);
        $this->assertEquals($expectedPinNumber, $updatedLine->pin_number);
        $this->assertNull($updatedLine->worker_id);
        $this->assertEquals(false, $updatedLine->defective);
    }

    public function test_ライン編集のライン名が空であるため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->put(route('line.update', ['process' => $process, 'line' => $line]), [
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['line_name' => 'ライン名は必ず指定してください。']);
    }

    public function test_ライン編集のライン名が長過ぎるため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->put(route('line.update', ['process' => $process, 'line' => $line]), [
            'line_name' => $this->faker->unique()->realText(33),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['line_name' => 'ライン名は、32文字以下で指定してください。']);
    }

    public function test_ライン編集の同一工程に同じライン名が存在するため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $raspi2 = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line1 = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $line2 = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi2->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->put(route('line.update', ['process' => $process, 'line' => $line2]), [
            'line_name' => $line1->line_name,
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['line_name' => 'ライン名の値は既に存在しています。']);
    }

    public function test_ライン編集の色が空であるため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->put(route('line.update', ['process' => $process, 'line' => $line]), [
            'line_name' => $this->faker->unique()->realText(32),
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['chart_color' => '色は必ず指定してください。']);
    }

    public function test_ライン編集の色の入力フォーマットを誤っていたため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->put(route('line.update', ['process' => $process, 'line' => $line]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => '# 00001F',
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['chart_color' => '色に正しい形式を指定してください。']);
    }

    public function test_ライン編集のラズパイidが空のため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->put(route('line.update', ['process' => $process, 'line' => $line]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['raspberry_pi_id' => 'ラズベリーパイは必ず指定してください。']);
    }

    public function test_ライン編集のラズパイidが存在しないため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->put(route('line.update', ['process' => $process, 'line' => $line]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => 1000000,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['raspberry_pi_id' => '選択されたラズベリーパイは正しくありません。']);
    }

    public function test_ライン編集の作業者idが存在しないため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->put(route('line.update', ['process' => $process, 'line' => $line]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(2, 27),
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => 1000000,
        ]);
        $response->assertSessionHasErrors(['worker_id' => '選択された作業者は正しくありません。']);
    }

    public function test_ライン編集のピン番号が空であるため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->put(route('line.update', ['process' => $process, 'line' => $line]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['pin_number' => 'ピン番号は必ず指定してください。']);
    }

    public function test_ライン編集のピン番号が数値ではないため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->put(route('line.update', ['process' => $process, 'line' => $line]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => 'A',
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['pin_number' => 'ピン番号は整数で指定してください。']);
    }

    public function test_ライン編集のピン番号が1以下であるため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->put(route('line.update', ['process' => $process, 'line' => $line]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => -1,
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['pin_number' => 'ピン番号には、0以上の数字を指定してください。']);
    }

    public function test_ライン編集のピン番号が28以上であるため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->put(route('line.update', ['process' => $process, 'line' => $line]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => 128,
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['pin_number' => 'ピン番号には、127以下の数字を指定してください。']);
    }

    public function test_ライン編集のラズパイidとピン番号が重複しているため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line1 = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $line2 = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->put(route('line.update', ['process' => $process, 'line' => $line1]), [
            'line_name' => $this->faker->unique()->realText(32),
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $line2->pin_number,
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response->assertSessionHasErrors(['pin_number' => 'ピン番号の値は既に存在しています。']);
    }

    public function test_ライン削除をログインしていない状態ではログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->delete(route('line.destroy', ['process' => $process, 'line' => $line]));
        $response->assertRedirect('login');
    }

    public function test_ライン削除のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $this->delete(route('line.destroy', ['process' => $process, 'line' => $line]));
    }

    public function test_ライン削除の成功()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->delete(route('line.destroy', ['process' => $process, 'line' => $line]));
        $response->assertRedirect(route('process.show', ['process' => $process, 'tab' => 'line']))
            ->assertSessionHas('toast_success', '作業の削除に成功しました。');

        $deletedLine = Line::find($line->line_id);
        $this->assertNull($deletedLine);
    }

    public function test_ライン並べ替えページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $response = $this->get(route('line.sorting', ['process' => $process]));
        $response->assertRedirect('login');
    }

    public function test_ライン並べ替えページにログインしてる状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $this->get(route('line.sorting', ['process' => $process]));
    }

    public function test_ライン並べ替えページにログインしてる状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $this->assertOk('line.sorting', ['process' => $process]);
    }

    public function test_ライン並べ替えをログインしていない状態ではログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $raspi1 = RaspberryPi::factory()->create();
        $raspi2 = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line1 = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi1->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $line2 = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi2->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->post(route('line.sort', ['process' => $process]), [
            'order' => [
                $line2->line_id,
                $line1->line_id,
            ],
        ]);
        $response->assertRedirect('login');
    }

    public function test_ライン並べ替えにログインしてる状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $raspi1 = RaspberryPi::factory()->create();
        $raspi2 = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line1 = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi1->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $line2 = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi2->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $this->post(route('line.sort', ['process' => $process]), [
            'order' => [
                $line2->line_id,
                $line1->line_id,
            ],
        ]);
    }

    public function test_ライン並べ替え成功1()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi1 = RaspberryPi::factory()->create();
        $raspi2 = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line1 = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi1->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $line2 = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi2->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->post(route('line.sort', ['process' => $process]), [
            'order' => [
                $line2->line_id,
                $line1->line_id,
            ],
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process, 'tab' => 'line']))
            ->assertSessionHas('toast_success', '並べ替えに成功しました。');

        $updatedLine1 = Line::find($line1->line_id);
        $updatedLine2 = Line::find($line2->line_id);
        $this->assertEquals(1, $updatedLine1->order);
        $this->assertEquals(0, $updatedLine2->order);
    }

    public function test_ライン並べ替え成功2()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $raspi1 = RaspberryPi::factory()->create();
        $raspi2 = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line1 = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi1->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $line2 = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi2->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
        ]);
        $response = $this->post(route('line.sort', ['process' => $process]), [
            'order' => [
                $line1->line_id,
                $line2->line_id,
            ],
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process, 'tab' => 'line']))
            ->assertSessionHas('toast_success', '並べ替えに成功しました。');

        $updatedLine1 = Line::find($line1->line_id);
        $updatedLine2 = Line::find($line2->line_id);
        $this->assertEquals(0, $updatedLine1->order);
        $this->assertEquals(1, $updatedLine2->order);
    }

    public function test_ライン並べ替えの順序が空であるため失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $response = $this->post(route('line.sort', ['process' => $process]), [
            // 'order' => [
            //     $line1->line_id,
            //     $line2->line_id,
            // ],
        ]);
        $response->assertSessionHasErrors(['order' => '順序は必ず指定してください。']);
    }

    public function test_ライン並べ替えの順序が空配列であるため失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $response = $this->post(route('line.sort', ['process' => $process]), [
            'order' => [],
        ]);
        $response->assertSessionHasErrors(['order' => '順序は必ず指定してください。']);
    }

    public function test_ライン並べ替えの要素が数値ではないため失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $response = $this->post(route('line.sort', ['process' => $process]), [
            'order' => ['a'],
        ]);
        $response->assertSessionHasErrors(['order.0' => '順序は整数で指定してください。']);
    }

    public function test_ライン並べ替えの要素が不正な数値であるため失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $response = $this->post(route('line.sort', ['process' => $process]), [
            'order' => [-1],
        ]);
        $response->assertSessionHasErrors(['order.0' => '選択された順序は正しくありません。']);
    }
}
