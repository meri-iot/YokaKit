<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Enums\RoleType;
use App\Http\Requests\UpdateCycleTimeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateCycleTimeRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者はサイクルタイム設定を更新する権限がある
     */
    public function test_管理者ユーザーは權限がある(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => RoleType::ADMIN]);
        $this->actingAs($admin);
        $request = new UpdateCycleTimeRequest();

        // Act & Assert
        $this->assertTrue($request->authorize());
    }

    /**
     * 一般ユーザーはサイクルタイム設定を更新する権限がない
     */
    public function test_一般ユーザーは權限がない(): void
    {
        // Arrange
        $user = User::factory()->create(['role' => RoleType::USER]);
        $this->actingAs($user);
        $request = new UpdateCycleTimeRequest();

        // Act & Assert
        $this->assertFalse($request->authorize());
    }

    /**
     * 有効なデータでバリデーションが成功する
     */
    public function test_有効なデータでバリデーション成功(): void
    {
        // Arrange
        $data = [
            'cycle_time' => 5.0,
            'over_time' => 10.0,
        ];

        $request = new UpdateCycleTimeRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertTrue($validator->passes());
    }

    /**
     * cycle_time が最小値より小さい場合はバリデーション失敗
     */
    public function test_下状新り上限最低時バリデーション失敗(): void
    {
        // Arrange
        $data = [
            'cycle_time' => 1.999,
            'over_time' => 10.0,
        ];

        $request = new UpdateCycleTimeRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('cycle_time', $validator->errors()->toArray());
    }

    /**
     * cycle_time が正確に最小値（2.000）でバリデーション成功
     */
    public function test_下状新り上限最低正矧でバリデーション成功(): void
    {
        // Arrange
        $data = [
            'cycle_time' => 2.0,
            'over_time' => 10.0,
        ];

        $request = new UpdateCycleTimeRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertTrue($validator->passes());
    }

    /**
     * cycle_time が最大値を超える場合はバリデーション失敗
     */
    public function test_下状新り上限最大時バリデーション失敗(): void
    {
        // Arrange
        $data = [
            'cycle_time' => 86400.0,
            'over_time' => 86400.0,
        ];

        $request = new UpdateCycleTimeRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('cycle_time', $validator->errors()->toArray());
    }

    /**
     * cycle_time が正確に最大値（86399.999）でバリデーション成功
     */
    public function test_下状新り上限最大正矧でバリデーション成功(): void
    {
        // Arrange
        $data = [
            'cycle_time' => 86399.999,
            'over_time' => 86400.0,
        ];

        $request = new UpdateCycleTimeRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertTrue($validator->passes());
    }

    /**
     * over_time が最小値より小さい場合はバリデーション失敗
     */
    public function test_超過時間計時肉体上限最低時バリデーション失敗(): void
    {
        // Arrange
        $data = [
            'cycle_time' => 5.0,
            'over_time' => 2.0,
        ];

        $request = new UpdateCycleTimeRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('over_time', $validator->errors()->toArray());
    }

    /**
     * over_time が正確に最小値（2.001）でバリデーション成功
     */
    public function test_超過時間計時肉体上限最低正矧でバリデーション成功(): void
    {
        // Arrange
        $data = [
            'cycle_time' => 2.0,
            'over_time' => 2.001,
        ];

        $request = new UpdateCycleTimeRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertTrue($validator->passes());
    }

    /**
     * over_time が最大値を超える場合はバリデーション失敗
     */
    public function test_超過時間計時肉体最大時バリデーション失敗(): void
    {
        // Arrange
        $data = [
            'cycle_time' => 5.0,
            'over_time' => 86400.001,
        ];

        $request = new UpdateCycleTimeRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('over_time', $validator->errors()->toArray());
    }

    /**
     * over_time が正確に最大値（86400）でバリデーション成功
     */
    public function test_超過時間計時肉体最大正矧でバリデーション成功(): void
    {
        // Arrange
        $data = [
            'cycle_time' => 86399.0,
            'over_time' => 86400.0,
        ];

        $request = new UpdateCycleTimeRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertTrue($validator->passes());
    }

    /**
     * over_time が cycle_time と同じ値の場合はバリデーション失敗（gt: greater than）
     */
    public function test_超過時間計時が下状新りと一致しけれ成功(): void
    {
        // Arrange
        $data = [
            'cycle_time' => 5.0,
            'over_time' => 5.0,
        ];

        $request = new UpdateCycleTimeRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('over_time', $validator->errors()->toArray());
    }

    /**
     * over_time が cycle_time より小さい場合はバリデーション失敗
     */
    public function test_超過時間計時が下状新り下低けれ失敗(): void
    {
        // Arrange
        $data = [
            'cycle_time' => 10.0,
            'over_time' => 5.0,
        ];

        $request = new UpdateCycleTimeRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('over_time', $validator->errors()->toArray());
    }

    /**
     * cycle_time が必須の場合はバリデーション失敗
     */
    public function test_下状新り欠落けれ失敗(): void
    {
        // Arrange
        $data = [
            'over_time' => 10.0,
        ];

        $request = new UpdateCycleTimeRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('cycle_time', $validator->errors()->toArray());
    }

    /**
     * over_time が必須の場合はバリデーション失敗
     */
    public function test_超過時間計時欠落けれ失敗(): void
    {
        // Arrange
        $data = [
            'cycle_time' => 5.0,
        ];

        $request = new UpdateCycleTimeRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('over_time', $validator->errors()->toArray());
    }

    /**
     * cycle_time が数値でない場合はバリデーション失敗
     */
    public function test_下状新り時汰不正欠落けれ失敗(): void
    {
        // Arrange
        $data = [
            'cycle_time' => 'not-a-number',
            'over_time' => 10.0,
        ];

        $request = new UpdateCycleTimeRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('cycle_time', $validator->errors()->toArray());
    }

    /**
     * over_time が数値でない場合はバリデーション失敗
     */
    public function test_超過時間計時汰不正欠落けれ失敗(): void
    {
        // Arrange
        $data = [
            'cycle_time' => 5.0,
            'over_time' => 'invalid',
        ];

        $request = new UpdateCycleTimeRequest();
        $validator = Validator::make($data, $request->rules());

        // Act & Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('over_time', $validator->errors()->toArray());
    }
}
