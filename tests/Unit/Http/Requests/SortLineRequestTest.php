<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\SortLineRequest;
use App\Models\Line;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SortLineRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => 5]);
        $this->actingAs($user);

        $request = new SortLineRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => 10]);
        $this->actingAs($user);

        $request = new SortLineRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_rulesは実在するID配列を許可する(): void
    {
        $line = $this->createLine();
        $request = new SortLineRequest();

        $validator = Validator::make([
            'order' => [$line->line_id],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesはorderが未指定の場合に失敗する(): void
    {
        $request = new SortLineRequest();

        $validator = Validator::make([], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('order', $validator->errors()->toArray());
    }

    public function test_rulesは存在しないIDを拒否する(): void
    {
        $request = new SortLineRequest();

        $validator = Validator::make([
            'order' => [999999],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('order.0', $validator->errors()->toArray());
    }

    public function test_rulesは重複IDを拒否する(): void
    {
        $line = $this->createLine();
        $request = new SortLineRequest();

        $validator = Validator::make([
            'order' => [$line->line_id, $line->line_id],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('order.1', $validator->errors()->toArray());
    }

    private function createLine(): Line
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();

        return Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'worker_id' => null,
            'line_name' => 'sort-test-line',
            'chart_color' => '#123456',
            'pin_number' => 1,
            'defective' => false,
        ]);
    }
}
