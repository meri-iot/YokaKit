<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StorePlannedOutageRequest;
use App\Models\PlannedOutage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StorePlannedOutageRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => 5]);
        $this->actingAs($user);

        $request = new StorePlannedOutageRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => 10]);
        $this->actingAs($user);

        $request = new StorePlannedOutageRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_rulesは正常な入力を許可する(): void
    {
        $request = new StorePlannedOutageRequest();

        $validator = Validator::make([
            'planned_outage_name' => '昼休憩',
            'start_time' => '12:00',
            'end_time' => '13:00',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesはplanned_outage_nameの重複を拒否する(): void
    {
        PlannedOutage::factory()->create(['planned_outage_name' => 'duplicate-name']);
        $request = new StorePlannedOutageRequest();

        $validator = Validator::make([
            'planned_outage_name' => 'duplicate-name',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('planned_outage_name', $validator->errors()->toArray());
    }

    public function test_rulesはplanned_outage_nameが配列だと失敗する(): void
    {
        $request = new StorePlannedOutageRequest();

        $validator = Validator::make([
            'planned_outage_name' => ['array-name'],
            'start_time' => '10:00',
            'end_time' => '11:00',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('planned_outage_name', $validator->errors()->toArray());
    }

    public function test_rulesはend_timeがstart_timeと同一だと失敗する(): void
    {
        $request = new StorePlannedOutageRequest();

        $validator = Validator::make([
            'planned_outage_name' => 'same-time',
            'start_time' => '12:00',
            'end_time' => '12:00',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('end_time', $validator->errors()->toArray());
    }

    public function test_rulesは時刻形式が不正なら失敗する(): void
    {
        $request = new StorePlannedOutageRequest();

        $validator = Validator::make([
            'planned_outage_name' => 'invalid-time-format',
            'start_time' => '25:00',
            'end_time' => '13:00',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('start_time', $validator->errors()->toArray());
    }
}
