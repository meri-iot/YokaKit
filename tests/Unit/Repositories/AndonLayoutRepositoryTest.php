<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\AndonLayout;
use App\Models\Process;
use App\Models\User;
use App\Repositories\AndonLayoutRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AndonLayoutRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_modelはAndonLayoutクラスを返す(): void
    {
        $repository = new AndonLayoutRepository();

        $this->assertSame(AndonLayout::class, $repository->model());
    }

    public function test_updateLayoutsは未登録レイアウトを作成して表示順を保存する(): void
    {
        $user = User::factory()->create();
        $processA = Process::factory()->create();
        $processB = Process::factory()->create();
        $repository = new AndonLayoutRepository();

        $result = $repository->updateLayouts([
            $processA->process_id => ['display' => 1, 'process_id' => $processA->process_id],
            $processB->process_id => ['process_id' => $processB->process_id],
        ], $user->id);

        $this->assertTrue($result);
        $this->assertDatabaseHas('andon_layouts', [
            'user_id' => $user->id,
            'process_id' => $processA->process_id,
            'order' => 0,
            'is_display' => true,
        ]);
        $this->assertDatabaseHas('andon_layouts', [
            'user_id' => $user->id,
            'process_id' => $processB->process_id,
            'order' => 1,
            'is_display' => false,
        ]);
    }

    public function test_updateLayoutsは既存レイアウトの順序と表示状態を更新する(): void
    {
        $user = User::factory()->create();
        $processA = Process::factory()->create();
        $processB = Process::factory()->create();
        AndonLayout::query()->create([
            'user_id' => $user->id,
            'process_id' => $processA->process_id,
            'order' => 99,
            'is_display' => false,
        ]);
        AndonLayout::query()->create([
            'user_id' => $user->id,
            'process_id' => $processB->process_id,
            'order' => 98,
            'is_display' => true,
        ]);
        $repository = new AndonLayoutRepository();

        $result = $repository->updateLayouts([
            $processB->process_id => ['display' => 1, 'process_id' => $processB->process_id],
            $processA->process_id => ['process_id' => $processA->process_id],
        ], $user->id);

        $this->assertTrue($result);
        $this->assertDatabaseHas('andon_layouts', [
            'user_id' => $user->id,
            'process_id' => $processB->process_id,
            'order' => 0,
            'is_display' => true,
        ]);
        $this->assertDatabaseHas('andon_layouts', [
            'user_id' => $user->id,
            'process_id' => $processA->process_id,
            'order' => 1,
            'is_display' => false,
        ]);
    }

    public function test_updateLayoutsは配列キーと異なるprocess_idが渡されても入力値を優先する(): void
    {
        $user = User::factory()->create();
        $process = Process::factory()->create();
        $repository = new AndonLayoutRepository();

        $result = $repository->updateLayouts([
            999999 => ['display' => 1, 'process_id' => $process->process_id],
        ], $user->id);

        $this->assertTrue($result);
        $this->assertDatabaseHas('andon_layouts', [
            'user_id' => $user->id,
            'process_id' => $process->process_id,
            'order' => 0,
            'is_display' => true,
        ]);
        $this->assertDatabaseMissing('andon_layouts', [
            'user_id' => $user->id,
            'process_id' => 999999,
        ]);
    }
}
