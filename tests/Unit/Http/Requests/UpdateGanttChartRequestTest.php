<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Enums\GanttChartType;
use App\Enums\RoleType;
use App\Http\Requests\UpdateGanttChartRequest;
use App\Models\GanttChart;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Helper function to create a valid GanttChart instance
 */
function createGanttChart(array $attributes = []): GanttChart
{
    $raspberryPi = RaspberryPi::factory()->create();
    $process = Process::factory()->create();

    $defaults = [
        'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
        'process_id' => $process->process_id,
        'pin_number' => 10,
        'chart_name' => 'Test Chart',
        'chart_color' => '#FF5733',
        'chart_type' => GanttChartType::WORK,
        'trigger' => true,
        'signal' => false,
    ];

    return GanttChart::factory()->create(array_merge($defaults, $attributes));
}

class UpdateGanttChartRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Request の route() が参照するモデルをテスト用に差し込む
     */
    private function bindRouteModels(UpdateGanttChartRequest $request, Process $process, GanttChart $ganttChart): void
    {
        $request->setRouteResolver(static fn() => new class($process, $ganttChart)
        {
            private Process $process;
            private GanttChart $ganttChart;

            public function __construct(Process $process, GanttChart $ganttChart)
            {
                $this->process = $process;
                $this->ganttChart = $ganttChart;
            }

            public function parameter($key, $default = null)
            {
                return match ($key) {
                    'process' => $this->process,
                    'ganttChart' => $this->ganttChart,
                    default => $default,
                };
            }
        });
    }

    /**
     * 管理者はガントチャート設定を更新する権限がある
     */
    public function test_管理者ユーザーは權限がある(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => RoleType::ADMIN]);
        $this->actingAs($admin);
        $request = new UpdateGanttChartRequest();

        // Act & Assert
        $this->assertTrue($request->authorize());
    }

    /**
     * 一般ユーザーはガントチャート設定を更新する権限がない
     */
    public function test_一般ユーザーは權限がない(): void
    {
        // Arrange
        $user = User::factory()->create(['role' => RoleType::USER]);
        $this->actingAs($user);
        $request = new UpdateGanttChartRequest();

        // Act & Assert
        $this->assertFalse($request->authorize());
    }

    /**
     * 有効なデータでバリデーションが成功する
     */
    public function test_有効なデータでバリデーション成功(): void
    {
        // Arrange
        $raspberryPi = RaspberryPi::factory()->create();
        $process = Process::factory()->create();
        $existingChart = createGanttChart([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'process_id' => $process->process_id,
            'chart_type' => GanttChartType::WORK,
        ]);

        $data = [
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 10,
            'chart_name' => 'Updated Chart',
            'chart_color' => '#FF5733',
            'chart_type' => GanttChartType::BASE,
            'trigger' => true,
        ];

        $request = new UpdateGanttChartRequest();
        $request->setRouteResolver(function () use ($process, $existingChart) {
            return app('router')->getRoutes()->match(
                app('request')->create('/', 'GET', [], [], [], ['SERVER_NAME' => 'localhost'])
            );
        });
        // Manually merge route parameters
        $request->merge([
            'process_id' => $process->process_id,
            'gantt_chart_id' => $existingChart->gantt_chart_id,
        ]);

        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertTrue($validator->passes());
    }

    /**
     * raspberry_pi_id が存在しないの場合はバリデーション失敗
     */
    public function test_ラズパイが存在しない空惍はバリデーション失敗(): void
    {
        // Arrange
        $data = [
            'raspberry_pi_id' => 99999,
            'pin_number' => 10,
            'chart_name' => 'Test Chart',
            'chart_color' => '#FF5733',
            'chart_type' => GanttChartType::WORK,
            'trigger' => true,
        ];

        $request = new UpdateGanttChartRequest();
        $request->merge(['process_id' => 1, 'gantt_chart_id' => 1]);

        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('raspberry_pi_id', $validator->errors()->toArray());
    }

    /**
     * pin_number が 0 未満の場合はバリデーション失敗
     */
    public function test_ピン番号が負数の空惍はバリデーション失敗(): void
    {
        // Arrange
        $raspberryPi = RaspberryPi::factory()->create();
        $data = [
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => -1,
            'chart_name' => 'Test Chart',
            'chart_color' => '#FF5733',
            'chart_type' => GanttChartType::WORK,
            'trigger' => true,
        ];

        $request = new UpdateGanttChartRequest();
        $request->merge(['process_id' => 1, 'gantt_chart_id' => 1]);

        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('pin_number', $validator->errors()->toArray());
    }

    /**
     * pin_number が 127 を超える場合はバリデーション失敗
     */
    public function test_ピン番号の最大値オーバーはバリデーション失敗(): void
    {
        // Arrange
        $raspberryPi = RaspberryPi::factory()->create();
        $data = [
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 128,
            'chart_name' => 'Test Chart',
            'chart_color' => '#FF5733',
            'chart_type' => GanttChartType::WORK,
            'trigger' => true,
        ];

        $request = new UpdateGanttChartRequest();
        $request->merge(['process_id' => 1, 'gantt_chart_id' => 1]);

        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('pin_number', $validator->errors()->toArray());
    }

    /**
     * pin_number が 0 でバリデーション成功（最小値）
     */
    public function test_ピン番号0でバリデーション成功(): void
    {
        // Arrange
        $raspberryPi = RaspberryPi::factory()->create();
        $process = Process::factory()->create();
        $ganttChart = createGanttChart([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'process_id' => $process->process_id,
            'pin_number' => 50,  // Use a different pin for the existing chart
        ]);

        $data = [
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 0,
            'chart_name' => 'Test Chart',
            'chart_color' => '#FF5733',
            'chart_type' => GanttChartType::WORK,
            'trigger' => true,
        ];

        $request = new UpdateGanttChartRequest();
        $request->merge([
            'process_id' => $process->process_id,
            'gantt_chart_id' => $ganttChart->gantt_chart_id,
        ]);

        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertTrue($validator->passes());
    }

    /**
     * pin_number が 127 でバリデーション成功（最大値）
     */
    public function test_ピン番号最大値でバリデーション成功(): void
    {
        // Arrange
        $raspberryPi = RaspberryPi::factory()->create();
        $process = Process::factory()->create();
        $ganttChart = createGanttChart([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'process_id' => $process->process_id,
            'pin_number' => 50,  // Use a different pin for the existing chart
        ]);

        $data = [
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 127,
            'chart_name' => 'Test Chart',
            'chart_color' => '#FF5733',
            'chart_type' => GanttChartType::WORK,
            'trigger' => true,
        ];

        $request = new UpdateGanttChartRequest();
        $request->merge([
            'process_id' => $process->process_id,
            'gantt_chart_id' => $ganttChart->gantt_chart_id,
        ]);

        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertTrue($validator->passes());
    }

    /**
     * chart_name が 32 文字を超える場合はバリデーション失敗
     */
    public function test_チャート名最大文字数超過はバリデーション失敗(): void
    {
        // Arrange
        $raspberryPi = RaspberryPi::factory()->create();
        $data = [
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 10,
            'chart_name' => str_repeat('a', 33),
            'chart_color' => '#FF5733',
            'chart_type' => GanttChartType::WORK,
            'trigger' => true,
        ];

        $request = new UpdateGanttChartRequest();
        $request->merge(['process_id' => 1, 'gantt_chart_id' => 1]);

        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('chart_name', $validator->errors()->toArray());
    }

    /**
     * chart_name が 32 文字でバリデーション成功
     */
    public function test_チャート名最大文字数正矧でバリデーション成功(): void
    {
        // Arrange
        $raspberryPi = RaspberryPi::factory()->create();
        $process = Process::factory()->create();
        $ganttChart = createGanttChart([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'process_id' => $process->process_id,
        ]);

        $data = [
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 10,
            'chart_name' => str_repeat('a', 32),
            'chart_color' => '#FF5733',
            'chart_type' => GanttChartType::WORK,
            'trigger' => true,
        ];

        $request = new UpdateGanttChartRequest();
        $request->merge([
            'process_id' => $process->process_id,
            'gantt_chart_id' => $ganttChart->gantt_chart_id,
        ]);

        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertTrue($validator->passes());
    }

    /**
     * chart_color が無効な CSS 色の場合はバリデーション失敗
     */
    public function test_チャート色不正はバリデーション失敗(): void
    {
        // Arrange
        $raspberryPi = RaspberryPi::factory()->create();
        $data = [
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 10,
            'chart_name' => 'Test Chart',
            'chart_color' => 'not-a-color',
            'chart_type' => GanttChartType::WORK,
            'trigger' => true,
        ];

        $request = new UpdateGanttChartRequest();
        $request->merge(['process_id' => 1, 'gantt_chart_id' => 1]);

        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('chart_color', $validator->errors()->toArray());
    }

    /**
     * chart_color が有効な 16 進数色でバリデーション成功
     */
    public function test_上位氏合法的な16進抗计新でバリデーション成功(): void
    {
        // Arrange
        $raspberryPi = RaspberryPi::factory()->create();
        $process = Process::factory()->create();
        $ganttChart = createGanttChart([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'process_id' => $process->process_id,
        ]);

        $data = [
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 10,
            'chart_name' => 'Test Chart',
            'chart_color' => '#FFFFFF',
            'chart_type' => GanttChartType::WORK,
            'trigger' => true,
        ];

        $request = new UpdateGanttChartRequest();
        $request->merge([
            'process_id' => $process->process_id,
            'gantt_chart_id' => $ganttChart->gantt_chart_id,
        ]);

        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertTrue($validator->passes());
    }

    /**
     * chart_type が無効な値の場合はバリデーション失敗
     */
    public function test_チャートタイプ不正はバリデーション失敗(): void
    {
        // Arrange
        $raspberryPi = RaspberryPi::factory()->create();
        $data = [
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 10,
            'chart_name' => 'Test Chart',
            'chart_color' => '#FF5733',
            'chart_type' => 'invalid-type',
            'trigger' => true,
        ];

        $request = new UpdateGanttChartRequest();
        $request->merge(['process_id' => 1, 'gantt_chart_id' => 1]);

        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('chart_type', $validator->errors()->toArray());
    }

    /**
     * trigger が null の場合は false として正規化される
     */
    public function test_真trigger_nullをfalseに正規化(): void
    {
        // Arrange
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $ganttChart = createGanttChart([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
        ]);
        $request = new UpdateGanttChartRequest();
        $this->bindRouteModels($request, $process, $ganttChart);

        $request->merge([
            'process_id' => $process->process_id,
            'gantt_chart_id' => $ganttChart->gantt_chart_id,
            'trigger' => null,
        ]);

        // Act
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        // Assert
        $this->assertFalse($request->get('trigger'));
    }

    /**
     * trigger が '1' 文字列の場合は true として正規化される
     */
    public function test_真trigger_文字1をtrueに正規化(): void
    {
        // Arrange
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $ganttChart = createGanttChart([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
        ]);
        $request = new UpdateGanttChartRequest();
        $this->bindRouteModels($request, $process, $ganttChart);

        $request->merge([
            'process_id' => $process->process_id,
            'gantt_chart_id' => $ganttChart->gantt_chart_id,
            'trigger' => '1',
        ]);

        // Act
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        // Assert
        $this->assertTrue($request->get('trigger'));
    }

    /**
     * trigger が '0' 文字列の場合は false として正規化される
     */
    public function test_真trigger_文字0をfalseに正規化(): void
    {
        // Arrange
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $ganttChart = createGanttChart([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
        ]);
        $request = new UpdateGanttChartRequest();
        $this->bindRouteModels($request, $process, $ganttChart);

        $request->merge([
            'process_id' => $process->process_id,
            'gantt_chart_id' => $ganttChart->gantt_chart_id,
            'trigger' => '0',
        ]);

        // Act
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        // Assert
        $this->assertFalse($request->get('trigger'));
    }

    /**
     * trigger が必須の場合はバリデーション失敗
     */
    public function test_trigger欠落はバリデーション失敗(): void
    {
        // Arrange
        $raspberryPi = RaspberryPi::factory()->create();
        $data = [
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 10,
            'chart_name' => 'Test Chart',
            'chart_color' => '#FF5733',
            'chart_type' => GanttChartType::WORK,
        ];

        $request = new UpdateGanttChartRequest();
        $request->merge(['process_id' => 1, 'gantt_chart_id' => 1]);

        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('trigger', $validator->errors()->toArray());
    }

    /**
     * prepareForValidation でルート安全性が確保される
     */
    public function test_prepareForValidationはルートパラメータ欠落時スキップ(): void
    {
        // Arrange
        $request = new UpdateGanttChartRequest();
        $request->merge(['process_id' => null, 'gantt_chart_id' => null]);

        // Act - should not throw exception
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        // Assert - no exception thrown, processing skipped safely
        $this->assertTrue(true);
    }
}
