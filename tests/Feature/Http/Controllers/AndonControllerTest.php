<?php

namespace Tests\Feature\Http\Controllers;

use App\Http\Requests\UpdateAndonConfigRequest;
use App\Models\AndonConfig;
use App\Models\AndonLayout;
use App\Models\Process;
use App\Services\AndonService;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class AndonControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_未認証ユーザーはhomeアクセス時にログインへリダイレクトされる(): void
    {
        $response = $this->get('/home');

        $response->assertRedirect('login');
    }

    public function test_認証済みユーザーはhome画面を表示できる(): void
    {
        $user = $this->createUser();

        $service = Mockery::mock(AndonService::class);
        $service->shouldReceive('processes')->once()->andReturn(new EloquentCollection());
        $service->shouldReceive('andonConfig')->once()->with($user->getAuthIdentifier())->andReturn(new AndonConfig());
        $this->app->instance(AndonService::class, $service);

        $response = $this->get('/home');

        $response->assertOk();
        $response->assertViewIs('home');
        $response->assertViewHasAll(['processes', 'config']);
    }

    public function test_一般ユーザーは設定編集画面を表示できる(): void
    {
        $user = $this->createUser();

        $service = Mockery::mock(AndonService::class);
        $service->shouldReceive('processes')->once()->andReturn(new EloquentCollection());
        $service->shouldReceive('andonConfig')->once()->with($user->getAuthIdentifier())->andReturn(new AndonConfig());
        $this->app->instance(AndonService::class, $service);

        $response = $this->get('/home/edit');

        $response->assertOk();
        $response->assertViewIs('andon.config');
        $response->assertViewHasAll(['processes', 'config', 'columns', 'easing']);
    }

    public function test_管理者は設定編集画面を表示できる(): void
    {
        $user = $this->createAdmin();

        $service = Mockery::mock(AndonService::class);
        $service->shouldReceive('processes')->once()->andReturn(new EloquentCollection());
        $service->shouldReceive('andonConfig')->once()->with($user->getAuthIdentifier())->andReturn(new AndonConfig());
        $this->app->instance(AndonService::class, $service);

        $response = $this->get('/home/edit');

        $response->assertOk();
        $response->assertViewIs('andon.config');
        $response->assertViewHasAll(['processes', 'config', 'columns', 'easing']);
    }

    public function test_管理者の設定更新成功時はhomeへ成功トースト付きで遷移する(): void
    {
        $user = $this->createAdmin();

        $service = Mockery::mock(AndonService::class);
        $service->shouldReceive('update')->once()->with(Mockery::type(UpdateAndonConfigRequest::class), $user->getAuthIdentifier());
        $this->app->instance(AndonService::class, $service);

        $response = $this->put(route('andon.update'), $this->validPayload());

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('toast_success');
    }

    public function test_管理者の設定更新失敗時はhomeへ失敗トースト付きで遷移する(): void
    {
        $user = $this->createAdmin();

        $service = Mockery::mock(AndonService::class);
        $service->shouldReceive('update')->once()->with(Mockery::type(UpdateAndonConfigRequest::class), $user->getAuthIdentifier())->andThrow(new Exception('failed'));
        $this->app->instance(AndonService::class, $service);

        $response = $this->put(route('andon.update'), $this->validPayload());

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('toast_danger');
    }

    public function test_一般ユーザーの設定更新成功時はhomeへ成功トースト付きで遷移する(): void
    {
        $user = $this->createUser();

        $service = Mockery::mock(AndonService::class);
        $service->shouldReceive('update')->once()->with(Mockery::type(UpdateAndonConfigRequest::class), $user->getAuthIdentifier());
        $this->app->instance(AndonService::class, $service);

        $response = $this->put(route('andon.update'), $this->validPayload());

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('toast_success');
    }

    public function test_設定フォームの入力フィールドと選択フィールドが描画される(): void
    {
        $this->createUser();

        $service = Mockery::mock(AndonService::class);
        $service->shouldReceive('processes')->once()->andReturn(new EloquentCollection());
        $service->shouldReceive('andonConfig')->once()->with(Mockery::type('int'))->andReturn(new AndonConfig());
        $this->app->instance(AndonService::class, $service);

        $response = $this->get(route('andon.config'));

        foreach (['row_count', 'auto_play_speed', 'slide_speed', 'font_ratio', 'column_count', 'item_column_count', 'easing'] as $field) {
            $response->assertSee("name=\"{$field}\"", false);
        }
    }

    public function test_設定値がフォームに反映される(): void
    {
        $this->createUser();

        $config = new AndonConfig([
            'row_count'         => 7,
            'column_count'      => 2,
            'auto_play_speed'   => 6000,
            'slide_speed'       => 800,
            'font_ratio'        => 1.5,
            'item_column_count' => 2,
        ]);

        $service = Mockery::mock(AndonService::class);
        $service->shouldReceive('processes')->once()->andReturn(new EloquentCollection());
        $service->shouldReceive('andonConfig')->once()->with(Mockery::type('int'))->andReturn($config);
        $this->app->instance(AndonService::class, $service);

        $response = $this->get(route('andon.config'));

        $response->assertSee('value="7"', false);
        $response->assertSee('value="6000"', false);
        $response->assertSee('value="800"', false);
        $response->assertSee('value="1.5"', false);
    }

    public function test_is_show_スイッチが全13項目描画される(): void
    {
        $this->createUser();

        $service = Mockery::mock(AndonService::class);
        $service->shouldReceive('processes')->once()->andReturn(new EloquentCollection());
        $service->shouldReceive('andonConfig')->once()->with(Mockery::type('int'))->andReturn(new AndonConfig());
        $this->app->instance(AndonService::class, $service);

        $response = $this->get(route('andon.config'));

        foreach (
            [
                'is_show_part_number',
                'is_show_start',
                'is_show_good_count',
                'is_show_good_rate',
                'is_show_defective_count',
                'is_show_defective_rate',
                'is_show_plan_count',
                'is_show_achievement_rate',
                'is_show_cycle_time',
                'is_show_time_operating_rate',
                'is_show_performance_operating_rate',
                'is_show_overall_equipment_effectiveness',
                'is_show_goal',
            ] as $field
        ) {
            $response->assertSee("name=\"{$field}\"", false);
        }
    }

    public function test_表示ONのプロセスはopacity指定なし(): void
    {
        $this->createUser();

        $process = Process::factory()->create();
        $process->setRelation('andonLayout', new AndonLayout(['is_display' => true]));

        $service = Mockery::mock(AndonService::class);
        $service->shouldReceive('processes')->once()->andReturn(new EloquentCollection([$process]));
        $service->shouldReceive('andonConfig')->once()->with(Mockery::type('int'))->andReturn(new AndonConfig());
        $this->app->instance(AndonService::class, $service);

        $response = $this->get(route('andon.config'));

        $this->assertDoesNotMatchRegularExpression(
            '/id="process-' . $process->process_id . '"[^>]*style="[^"]*0\.25/',
            $response->getContent()
        );
    }

    public function test_表示OFFのプロセスはopacity_025が設定される(): void
    {
        $this->createUser();

        $process = Process::factory()->create();
        $process->setRelation('andonLayout', new AndonLayout(['is_display' => false]));

        $service = Mockery::mock(AndonService::class);
        $service->shouldReceive('processes')->once()->andReturn(new EloquentCollection([$process]));
        $service->shouldReceive('andonConfig')->once()->with(Mockery::type('int'))->andReturn(new AndonConfig());
        $this->app->instance(AndonService::class, $service);

        $response = $this->get(route('andon.config'));

        $this->assertMatchesRegularExpression(
            '/id="process-' . $process->process_id . '"[^>]*style="[^"]*0\.25/',
            $response->getContent()
        );
    }

    public function test_表示ONのプロセスのチェックボックスはchecked属性あり(): void
    {
        $this->createUser();

        $process = Process::factory()->create();
        $process->setRelation('andonLayout', new AndonLayout(['is_display' => true]));

        $service = Mockery::mock(AndonService::class);
        $service->shouldReceive('processes')->once()->andReturn(new EloquentCollection([$process]));
        $service->shouldReceive('andonConfig')->once()->with(Mockery::type('int'))->andReturn($this->allIsShowFalseConfig());
        $this->app->instance(AndonService::class, $service);

        $response = $this->get(route('andon.config'));

        $this->assertMatchesRegularExpression(
            '/name="layouts\[' . $process->process_id . '\]\[display\]"[^>]*checked/',
            $response->getContent()
        );
    }

    public function test_表示OFFのプロセスのチェックボックスはchecked属性なし(): void
    {
        $this->createUser();

        $process = Process::factory()->create();
        $process->setRelation('andonLayout', new AndonLayout(['is_display' => false]));

        $service = Mockery::mock(AndonService::class);
        $service->shouldReceive('processes')->once()->andReturn(new EloquentCollection([$process]));
        $service->shouldReceive('andonConfig')->once()->with(Mockery::type('int'))->andReturn($this->allIsShowFalseConfig());
        $this->app->instance(AndonService::class, $service);

        $response = $this->get(route('andon.config'));

        $this->assertDoesNotMatchRegularExpression(
            '/name="layouts\[' . $process->process_id . '\]\[display\]"[^>]*checked/',
            $response->getContent()
        );
    }

    public function test_column_countに基づくプロセスの列クラスが適用される(): void
    {
        $this->createUser();

        $process = Process::factory()->create();
        $process->setRelation('andonLayout', new AndonLayout(['is_display' => true]));

        $config = new AndonConfig(['column_count' => 2, 'item_column_count' => 1]);

        $service = Mockery::mock(AndonService::class);
        $service->shouldReceive('processes')->once()->andReturn(new EloquentCollection([$process]));
        $service->shouldReceive('andonConfig')->once()->with(Mockery::type('int'))->andReturn($config);
        $this->app->instance(AndonService::class, $service);

        $response = $this->get(route('andon.config'));

        $response->assertSee('col-6 sortable-process', false);
    }

    public function test_JSにcolumn_countとitem_column_countがJSON出力される(): void
    {
        $this->createUser();

        $config = new AndonConfig(['column_count' => 2, 'item_column_count' => 3]);

        $service = Mockery::mock(AndonService::class);
        $service->shouldReceive('processes')->once()->andReturn(new EloquentCollection());
        $service->shouldReceive('andonConfig')->once()->with(Mockery::type('int'))->andReturn($config);
        $this->app->instance(AndonService::class, $service);

        $response = $this->get(route('andon.config'));

        $response->assertSee('currentColumn = 2', false);
        $response->assertSee('currentItemColumn = 3', false);
    }

    /**
     * 全 is_show_* フラグを false にした AndonConfig を返す
     *
     * @return AndonConfig
     */
    private function allIsShowFalseConfig(): AndonConfig
    {
        return new AndonConfig([
            'column_count'                            => 1,
            'item_column_count'                       => 1,
            'is_show_part_number'                     => false,
            'is_show_start'                           => false,
            'is_show_good_count'                      => false,
            'is_show_good_rate'                       => false,
            'is_show_defective_count'                 => false,
            'is_show_defective_rate'                  => false,
            'is_show_plan_count'                      => false,
            'is_show_achievement_rate'                => false,
            'is_show_cycle_time'                      => false,
            'is_show_time_operating_rate'             => false,
            'is_show_performance_operating_rate'      => false,
            'is_show_overall_equipment_effectiveness' => false,
            'is_show_goal'                            => false,
        ]);
    }

    /**
     * Andon設定更新API向けの最小有効ペイロードを返す
     *
     * @return array<string,mixed>
     */
    private function validPayload(): array
    {
        return [
            'row_count' => 1,
            'column_count' => 1,
            'auto_play_speed' => 0,
            'slide_speed' => 0,
            'easing' => 'linear',
            'font_ratio' => 1.0,
            'item_column_count' => 1,
            'is_show_part_number' => true,
            'is_show_start' => true,
            'is_show_good_count' => true,
            'is_show_good_rate' => true,
            'is_show_defective_count' => true,
            'is_show_defective_rate' => true,
            'is_show_plan_count' => true,
            'is_show_achievement_rate' => true,
            'is_show_cycle_time' => true,
            'is_show_time_operating_rate' => true,
            'is_show_performance_operating_rate' => true,
            'is_show_overall_equipment_effectiveness' => true,
            'is_show_goal' => true,
        ];
    }
}
