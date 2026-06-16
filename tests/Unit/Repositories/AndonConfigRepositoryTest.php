<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\AndonConfig;
use App\Models\User;
use App\Repositories\AndonConfigRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AndonConfigRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_modelはAndonConfigクラスを返す(): void
    {
        $repository = new AndonConfigRepository();

        $this->assertSame(AndonConfig::class, $repository->model());
    }

    public function test_findOrCreateByUserIdは既存設定を返す(): void
    {
        $user = User::factory()->create();
        $config = AndonConfig::query()->create([
            'user_id' => $user->id,
            'row_count' => 5,
            'column_count' => 6,
        ]);

        $repository = new AndonConfigRepository();
        $resolved = $repository->findOrCreateByUserId($user->id);

        $this->assertTrue($config->is($resolved));
        $this->assertSame(1, AndonConfig::query()->where('user_id', $user->id)->count());
    }

    public function test_findOrCreateByUserIdは未作成ならデフォルト設定を新規作成する(): void
    {
        $user = User::factory()->create();

        $repository = new AndonConfigRepository();
        $resolved = $repository->findOrCreateByUserId($user->id);

        $this->assertDatabaseHas('andon_configs', [
            'andon_config_id' => $resolved->andon_config_id,
            'user_id' => $user->id,
            'row_count' => 3,
            'column_count' => 4,
        ]);
        $this->assertTrue($resolved->auto_play);
        $this->assertFalse($resolved->fade);
    }

    public function test_findOrCreateByUserIdは同一ユーザーで複数回呼んでも1件だけ作成する(): void
    {
        $user = User::factory()->create();
        $repository = new AndonConfigRepository();

        $first = $repository->findOrCreateByUserId($user->id);
        $second = $repository->findOrCreateByUserId($user->id);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, AndonConfig::query()->where('user_id', $user->id)->count());
    }
}
