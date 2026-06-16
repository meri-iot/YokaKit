<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Enums\AndonColumnSize;
use App\Enums\EasingType;
use App\Enums\RoleType;
use App\Http\Requests\UpdateAndonConfigRequest;
use App\Models\Process;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class UpdateAndonConfigRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者は Andon 設定を更新する権限がある
     */
    public function test_管理者ユーザーは權限がある(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => RoleType::ADMIN]);
        $this->actingAs($admin);
        $request = new UpdateAndonConfigRequest();

        // Act & Assert
        $this->assertTrue($request->authorize());
    }

    /**
     * 一般ユーザーも Andon 設定を更新する権限がある
     */
    public function test_一般ユーザーは權限がある(): void
    {
        // Arrange
        $user = User::factory()->create(['role' => RoleType::USER]);
        $this->actingAs($user);
        $request = new UpdateAndonConfigRequest();

        // Act & Assert
        $this->assertTrue($request->authorize());
    }

    /**
     * 有効なデータでバリデーションが成功する
     */
    public function test_有効なデータでバリデーション成功(): void
    {
        // Arrange
        $process = Process::factory()->create();
        $data = [
            'row_count' => 10,
            'column_count' => AndonColumnSize::ONE,
            'auto_play_speed' => 5000,
            'slide_speed' => 3000,
            'easing' => EasingType::LINEAR,
            'font_ratio' => 1.0,
            'layouts' => [
                ['display' => 1, 'process_id' => $process->process_id],
                ['display' => 2, 'process_id' => $process->process_id],
            ],
            'item_column_count' => AndonColumnSize::TWO,
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

        $request = new UpdateAndonConfigRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertTrue($validator->passes());
    }

    /**
     * row_count が範囲外の場合はバリデーション失敗
     */
    public function test_更新時に現在値を許可する(): void
    {
        // Arrange
        $data = [
            'row_count' => 0,
            'column_count' => AndonColumnSize::ONE,
            'auto_play_speed' => 5000,
            'slide_speed' => 3000,
            'easing' => EasingType::LINEAR,
            'item_column_count' => AndonColumnSize::TWO,
        ];

        $request = new UpdateAndonConfigRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('row_count', $validator->errors()->toArray());
    }

    /**
     * column_count が無効な値の場合はバリデーション失敗
     */
    public function test_段数正矧バリデーション失敗(): void
    {
        // Arrange
        $data = [
            'row_count' => 10,
            'column_count' => 999,
            'auto_play_speed' => 5000,
            'slide_speed' => 3000,
            'easing' => EasingType::LINEAR,
            'item_column_count' => AndonColumnSize::TWO,
        ];

        $request = new UpdateAndonConfigRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('column_count', $validator->errors()->toArray());
    }

    /**
     * easing が無効な値の場合はバリデーション失敗
     */
    public function test_イーズトイベ不正はバリデーション失敗(): void
    {
        // Arrange
        $data = [
            'row_count' => 10,
            'column_count' => AndonColumnSize::ONE,
            'auto_play_speed' => 5000,
            'slide_speed' => 3000,
            'easing' => 'invalid-easing',
            'item_column_count' => AndonColumnSize::TWO,
        ];

        $request = new UpdateAndonConfigRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('easing', $validator->errors()->toArray());
    }

    /**
     * layouts.*.process_id が存在しないプロセスの場合はバリデーション失敗
     */
    public function test_工程が存在しないとバリデーション失敗(): void
    {
        // Arrange
        $data = [
            'row_count' => 10,
            'column_count' => AndonColumnSize::ONE,
            'auto_play_speed' => 5000,
            'slide_speed' => 3000,
            'easing' => EasingType::LINEAR,
            'layouts' => [
                ['display' => 1, 'process_id' => 99999],
            ],
            'item_column_count' => AndonColumnSize::TWO,
        ];

        $request = new UpdateAndonConfigRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('layouts.0.process_id', $validator->errors()->toArray());
    }

    /**
     * boolean フラグが null の場合は false として正規化される
     */
    public function test_真理論数値nullをfalseに正規化(): void
    {
        // Arrange
        $process = Process::factory()->create();
        $request = new UpdateAndonConfigRequest();
        $request->merge([
            'row_count' => 10,
            'column_count' => AndonColumnSize::ONE,
            'auto_play_speed' => 5000,
            'slide_speed' => 3000,
            'easing' => EasingType::LINEAR,
            'layouts' => [
                ['display' => 1, 'process_id' => $process->process_id],
            ],
            'item_column_count' => AndonColumnSize::TWO,
            'is_show_part_number' => null,
            'is_show_start' => null,
            'is_show_good_count' => null,
            'is_show_good_rate' => null,
            'is_show_defective_count' => null,
            'is_show_defective_rate' => null,
            'is_show_plan_count' => null,
            'is_show_achievement_rate' => null,
            'is_show_cycle_time' => null,
            'is_show_time_operating_rate' => null,
            'is_show_performance_operating_rate' => null,
            'is_show_overall_equipment_effectiveness' => null,
            'is_show_goal' => null,
        ]);

        // Act
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        // Assert
        $this->assertFalse($request->get('is_show_part_number'));
        $this->assertFalse($request->get('is_show_start'));
        $this->assertFalse($request->get('is_show_goal'));
    }

    /**
     * boolean フラグが '1' 文字列の場合は true として正規化される
     */
    public function test_真理論数値文字1をtrueに正規化(): void
    {
        // Arrange
        $process = Process::factory()->create();
        $request = new UpdateAndonConfigRequest();
        $request->merge([
            'row_count' => 10,
            'column_count' => AndonColumnSize::ONE,
            'auto_play_speed' => 5000,
            'slide_speed' => 3000,
            'easing' => EasingType::LINEAR,
            'layouts' => [
                ['display' => 1, 'process_id' => $process->process_id],
            ],
            'item_column_count' => AndonColumnSize::TWO,
            'is_show_part_number' => '1',
            'is_show_start' => '1',
            'is_show_good_count' => '1',
            'is_show_good_rate' => '1',
            'is_show_defective_count' => '1',
            'is_show_defective_rate' => '1',
            'is_show_plan_count' => '1',
            'is_show_achievement_rate' => '1',
            'is_show_cycle_time' => '1',
            'is_show_time_operating_rate' => '1',
            'is_show_performance_operating_rate' => '1',
            'is_show_overall_equipment_effectiveness' => '1',
            'is_show_goal' => '1',
        ]);

        // Act
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        // Assert
        $this->assertTrue($request->get('is_show_part_number'));
        $this->assertTrue($request->get('is_show_start'));
        $this->assertTrue($request->get('is_show_goal'));
    }

    /**
     * boolean フラグが '0' 文字列の場合は false として正規化される
     */
    public function test_真理論数値文字0をfalseに正規化(): void
    {
        // Arrange
        $request = new UpdateAndonConfigRequest();
        $request->merge([
            'is_show_part_number' => '0',
            'is_show_start' => '0',
            'is_show_good_count' => '0',
            'is_show_good_rate' => '0',
            'is_show_defective_count' => '0',
            'is_show_defective_rate' => '0',
            'is_show_plan_count' => '0',
            'is_show_achievement_rate' => '0',
            'is_show_cycle_time' => '0',
            'is_show_time_operating_rate' => '0',
            'is_show_performance_operating_rate' => '0',
            'is_show_overall_equipment_effectiveness' => '0',
            'is_show_goal' => '0',
        ]);

        // Act
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        // Assert
        $this->assertFalse($request->get('is_show_part_number'));
        $this->assertFalse($request->get('is_show_start'));
        $this->assertFalse($request->get('is_show_goal'));
    }

    /**
     * layouts が null の場合は正規化の際にそのまま保たれる
     */
    public function test_レイアウトヌルを正規化后もヌルのまま(): void
    {
        // Arrange
        $request = new UpdateAndonConfigRequest();
        $request->merge([
            'layouts' => null,
            'is_show_part_number' => true,
        ]);

        // Act
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        // Assert
        $this->assertNull($request->get('layouts'));
        $this->assertTrue($request->get('is_show_part_number'));
    }

    /**
     * auto_play_speed が上限を超えた場合はバリデーション失敗
     */
    public function test_auto_play_speedが上限超過でバリデーション失敗(): void
    {
        // Arrange
        $data = [
            'row_count' => 10,
            'column_count' => AndonColumnSize::ONE,
            'auto_play_speed' => 3600001,
            'slide_speed' => 3000,
            'easing' => EasingType::LINEAR,
            'item_column_count' => AndonColumnSize::TWO,
        ];

        $request = new UpdateAndonConfigRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('auto_play_speed', $validator->errors()->toArray());
    }

    /**
     * auto_play_speed が負の値の場合はバリデーション失敗
     */
    public function test_auto_play_speedが負値でバリデーション失敗(): void
    {
        // Arrange
        $data = [
            'row_count' => 10,
            'column_count' => AndonColumnSize::ONE,
            'auto_play_speed' => -1,
            'slide_speed' => 3000,
            'easing' => EasingType::LINEAR,
            'item_column_count' => AndonColumnSize::TWO,
        ];

        $request = new UpdateAndonConfigRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('auto_play_speed', $validator->errors()->toArray());
    }

    /**
     * slide_speed が上限を超えた場合はバリデーション失敗
     */
    public function test_slide_speedが上限超過でバリデーション失敗(): void
    {
        // Arrange
        $data = [
            'row_count' => 10,
            'column_count' => AndonColumnSize::ONE,
            'auto_play_speed' => 5000,
            'slide_speed' => 3600001,
            'easing' => EasingType::LINEAR,
            'item_column_count' => AndonColumnSize::TWO,
        ];

        $request = new UpdateAndonConfigRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('slide_speed', $validator->errors()->toArray());
    }

    /**
     * 表示フラグが欠落している場合はバリデーション失敗
     */
    public function test_is_show_part_numberが欠落でバリデーション失敗(): void
    {
        // Arrange
        $data = [
            'row_count' => 10,
            'column_count' => AndonColumnSize::ONE,
            'auto_play_speed' => 5000,
            'slide_speed' => 3000,
            'easing' => EasingType::LINEAR,
            'item_column_count' => AndonColumnSize::TWO,
            // is_show_part_number 欠落
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

        $request = new UpdateAndonConfigRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('is_show_part_number', $validator->errors()->toArray());
    }

    /**
     * item_column_count が無効な値の場合はバリデーション失敗
     */
    public function test_item_column_countが不正値でバリデーション失敗(): void
    {
        // Arrange
        $data = [
            'row_count' => 10,
            'column_count' => AndonColumnSize::ONE,
            'auto_play_speed' => 5000,
            'slide_speed' => 3000,
            'easing' => EasingType::LINEAR,
            'item_column_count' => 999,
        ];

        $request = new UpdateAndonConfigRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('item_column_count', $validator->errors()->toArray());
    }

    /**
     * font_ratio が負値の場合はバリデーション失敗
     */
    public function test_font_ratioが負値でバリデーション失敗(): void
    {
        // Arrange
        $data = [
            'row_count' => 10,
            'column_count' => AndonColumnSize::ONE,
            'auto_play_speed' => 5000,
            'slide_speed' => 3000,
            'easing' => EasingType::LINEAR,
            'font_ratio' => -1,
            'item_column_count' => AndonColumnSize::TWO,
        ];

        $request = new UpdateAndonConfigRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('font_ratio', $validator->errors()->toArray());
    }

    /**
     * font_ratio が 0 の場合はバリデーション成功
     */
    public function test_font_ratioが0でバリデーション成功(): void
    {
        // Arrange
        $data = [
            'row_count' => 10,
            'column_count' => AndonColumnSize::ONE,
            'auto_play_speed' => 5000,
            'slide_speed' => 3000,
            'easing' => EasingType::LINEAR,
            'font_ratio' => 0,
            'layouts' => null,
            'item_column_count' => AndonColumnSize::TWO,
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

        $request = new UpdateAndonConfigRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertTrue($validator->passes());
    }
}
