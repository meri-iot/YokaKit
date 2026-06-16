<?php

namespace Tests\Feature\Models;

use App\Models\CycleTime;
use App\Models\PartNumber;
use App\Models\Process;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class CycleTimeTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;
    use WithFaker;

    public function test_サイクルタイム追加ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $response = $this->get(route('cycle-time.create', ['process' => $process]));
        $response->assertRedirect('login');
    }

    public function test_サイクルタイム追加ページにログインしてる状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $this->get(route('cycle-time.create', ['process' => $process]));
    }

    public function test_サイクルタイム追加ページにログインしてる状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $this->assertOk('cycle-time.create', ['process' => $process]);
    }

    public function test_サイクルタイム追加をログインしていない状態ではログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $response = $this->post(route('cycle-time.store', ['process' => $process]), [
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 0.001,
            'over_time' => 86400,
        ]);
        $response->assertRedirect('login');
    }

    public function test_サイクルタイム追加のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $this->post(route('cycle-time.store', ['process' => $process]), [
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 0.001,
            'over_time' => 86400,
        ]);
    }

    public function test_サイクルタイム追加の成功()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $response = $this->post(route('cycle-time.store', ['process' => $process]), [
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 2,
            'over_time' => 86400,
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process, 'tab' => 'part-number']))
            ->assertSessionHas('toast_success', 'サイクルタイムの登録に成功しました。');

        $storedCycleTime = CycleTime::latest()->first();
        $this->assertEquals($process->process_id, $storedCycleTime->process_id);
        $this->assertEquals($partNumber->part_number_id, $storedCycleTime->part_number_id);
        $this->assertEquals(2, $storedCycleTime->cycle_time);
        $this->assertEquals(86400, $storedCycleTime->over_time);
    }

    public function test_サイクルタイム追加の品番idが不正であるため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $response = $this->post(route('cycle-time.store', ['process' => $process]), [
            'part_number_id' => 'abc',
            'cycle_time' => 0.001,
            'over_time' => 86400,
        ]);
        $response->assertSessionHasErrors(['part_number_id' => '品番は整数で指定してください。']);
    }

    public function test_サイクルタイム追加の品番idが重複しているため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->post(route('cycle-time.store', ['process' => $process]), [
            'part_number_id' => $cycleTime->part_number_id,
            'cycle_time' => 0.001,
            'over_time' => 86400,
        ]);
        $response->assertSessionHasErrors(['part_number_id' => '品番の値は既に存在しています。']);
    }

    public function test_サイクルタイム追加のサイクルタイムが空であるため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $response = $this->post(route('cycle-time.store', ['process' => $process]), [
            'part_number_id' => $partNumber->part_number_id,
            'over_time' => 86400,
        ]);
        $response->assertSessionHasErrors([
            'cycle_time' => 'サイクルタイムは必ず指定してください。',
            'over_time' => 'オーバータイムには、サイクルタイムより大きな値を指定してください。',
        ]);
    }

    public function test_サイクルタイム追加のサイクルタイムが0以下であるため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $response = $this->post(route('cycle-time.store', ['process' => $process]), [
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 0,
            'over_time' => 86400,
        ]);
        $response->assertSessionHasErrors(['cycle_time' => 'サイクルタイムには、2.000以上の数字を指定してください。']);
    }

    public function test_サイクルタイム追加のサイクルタイムが86399．999以上であるため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $response = $this->post(route('cycle-time.store', ['process' => $process]), [
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 86400,
            'over_time' => 86400,
        ]);
        $response->assertSessionHasErrors([
            'cycle_time' => 'サイクルタイムには、86399.999以下の数字を指定してください。',
            'over_time' => 'オーバータイムには、86400より大きな値を指定してください。',
        ]);
    }

    public function test_サイクルタイム追加のサイクルタイムが文字列であるため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $response = $this->post(route('cycle-time.store', ['process' => $process]), [
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 'abc',
            'over_time' => 86400,
        ]);
        $response->assertSessionHasErrors(['cycle_time' => 'サイクルタイムには、数字を指定してください。']);
    }

    public function test_サイクルタイム追加のオーバータイムが空であるため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $response = $this->post(route('cycle-time.store', ['process' => $process]), [
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 86400,
        ]);
        $response->assertSessionHasErrors(['over_time' => 'オーバータイムは必ず指定してください。']);
    }

    public function test_サイクルタイム追加のオーバータイムが0．001以下であるため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $response = $this->post(route('cycle-time.store', ['process' => $process]), [
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 2,
            'over_time' => 2,
        ]);
        $response->assertSessionHasErrors(['over_time' => 'オーバータイムには、2.001以上の数字を指定してください。']);
    }

    public function test_サイクルタイム追加のオーバータイムが86400．001以上であるため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $response = $this->post(route('cycle-time.store', ['process' => $process]), [
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 1,
            'over_time' => 86400.001,
        ]);
        $response->assertSessionHasErrors(['over_time' => 'オーバータイムには、86400以下の数字を指定してください。']);
    }

    public function test_サイクルタイム追加のオーバータイムが文字列であるため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $response = $this->post(route('cycle-time.store', ['process' => $process]), [
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 10,
            'over_time' => 'abc',
        ]);
        $response->assertSessionHasErrors(['over_time' => 'オーバータイムには、数字を指定してください。']);
    }

    public function test_サイクルタイム追加のオーバータイムがサイクルタイムより小さいため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $response = $this->post(route('cycle-time.store', ['process' => $process]), [
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 10,
            'over_time' => 5,
        ]);
        $response->assertSessionHasErrors(['over_time' => 'オーバータイムには、10より大きな値を指定してください。']);
    }

    public function test_サイクルタイム編集ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->get(route('cycle-time.edit', ['process' => $process, 'cycleTime' => $cycleTime]));
        $response->assertRedirect('login');
    }

    public function test_サイクルタイム編集ページにログインしている状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $this->get(route('cycle-time.edit', ['process' => $process, 'cycleTime' => $cycleTime]));
    }

    public function test_サイクルタイム編集ページにログインしている状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $this->assertOk('cycle-time.edit', ['process' => $process, 'cycleTime' => $cycleTime]);
    }

    public function test_サイクルタイム編集の成功()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->put(route('cycle-time.update', ['process' => $process, 'cycleTime' => $cycleTime]), [
            'cycle_time' => 5,
            'over_time' => 10,
        ]);

        $response->assertRedirect(route('process.show', ['process' => $process, 'tab' => 'part-number']))
            ->assertSessionHas('toast_success', 'サイクルタイムの更新に成功しました。');

        $updatedCycleTime = CycleTime::find($cycleTime->cycle_time_id);
        $this->assertEquals(5, $updatedCycleTime->cycle_time);
        $this->assertEquals(10, $updatedCycleTime->over_time);
    }

    public function test_サイクルタイム編集のサイクルタイムが空であるため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->put(route('cycle-time.update', ['process' => $process, 'cycleTime' => $cycleTime]), [
            'over_time' => 86400,
        ]);
        $response->assertSessionHasErrors([
            'cycle_time' => 'サイクルタイムは必ず指定してください。',
            'over_time' => 'オーバータイムには、サイクルタイムより大きな値を指定してください。',
        ]);
    }

    public function test_サイクルタイム編集のサイクルタイムが0以下であるため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->put(route('cycle-time.update', ['process' => $process, 'cycleTime' => $cycleTime]), [
            'cycle_time' => 0,
            'over_time' => 86400,
        ]);
        $response->assertSessionHasErrors(['cycle_time' => 'サイクルタイムには、2.000以上の数字を指定してください。']);
    }

    public function test_サイクルタイム編集のサイクルタイムが86399．999以上であるため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->put(route('cycle-time.update', ['process' => $process, 'cycleTime' => $cycleTime]), [
            'cycle_time' => 86400,
            'over_time' => 86400,
        ]);
        $response->assertSessionHasErrors([
            'cycle_time' => 'サイクルタイムには、86399.999以下の数字を指定してください。',
            'over_time' => 'オーバータイムには、86400より大きな値を指定してください。',
        ]);
    }

    public function test_サイクルタイム編集のサイクルタイムが文字列であるため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->put(route('cycle-time.update', ['process' => $process, 'cycleTime' => $cycleTime]), [
            'cycle_time' => 'あいう',
            'over_time' => 86400,
        ]);
        $response->assertSessionHasErrors(['cycle_time' => 'サイクルタイムには、数字を指定してください。']);
    }

    public function test_サイクルタイム編集のオーバータイムが空であるため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->put(route('cycle-time.update', ['process' => $process, 'cycleTime' => $cycleTime]), [
            'cycle_time' => 100,
        ]);
        $response->assertSessionHasErrors(['over_time' => 'オーバータイムは必ず指定してください。']);
    }

    public function test_サイクルタイム編集のオーバータイムが0．001以下であるため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->put(route('cycle-time.update', ['process' => $process, 'cycleTime' => $cycleTime]), [
            'cycle_time' => 2,
            'over_time' => 2,
        ]);
        $response->assertSessionHasErrors(['over_time' => 'オーバータイムには、2.001以上の数字を指定してください。']);
    }

    public function test_サイクルタイム編集のオーバータイムが86400．001以上であるため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->put(route('cycle-time.update', ['process' => $process, 'cycleTime' => $cycleTime]), [
            'cycle_time' => 10,
            'over_time' => 86400.001,
        ]);
        $response->assertSessionHasErrors(['over_time' => 'オーバータイムには、86400以下の数字を指定してください。']);
    }

    public function test_サイクルタイム編集のオーバータイムが文字列であるため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->put(route('cycle-time.update', ['process' => $process, 'cycleTime' => $cycleTime]), [
            'cycle_time' => 10,
            'over_time' => 'あああ',
        ]);
        $response->assertSessionHasErrors(['over_time' => 'オーバータイムには、数字を指定してください。']);
    }

    public function test_サイクルタイム編集のオーバータイムがサイクルタイムより小さいため編集失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->put(route('cycle-time.update', ['process' => $process, 'cycleTime' => $cycleTime]), [
            'cycle_time' => 10,
            'over_time' => 5,
        ]);
        $response->assertSessionHasErrors(['over_time' => 'オーバータイムには、10より大きな値を指定してください。']);
    }

    public function test_サイクルタイム削除をログインしていない状態ではログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->delete(route('cycle-time.destroy', ['process' => $process, 'cycleTime' => $cycleTime]));
        $response->assertRedirect('login');
    }

    public function test_サイクルタイム削除のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $this->delete(route('cycle-time.destroy', ['process' => $process, 'cycleTime' => $cycleTime]));
    }

    public function test_サイクルタイム削除の成功()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->delete(route('cycle-time.destroy', ['process' => $process, 'cycleTime' => $cycleTime]));
        $response->assertRedirect(route('process.show', ['process' => $process, 'tab' => 'part-number']))
            ->assertSessionHas('toast_success', 'サイクルタイムの削除に成功しました。');

        $deletedCycleTime = CycleTime::find($cycleTime->cycle_time_id);
        $this->assertNull($deletedCycleTime);
    }
}
