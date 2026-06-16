<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Process;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class AndonInfoControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;
    use WithFaker;

    public function test_guestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $this->get('/api/v1/andon');
    }

    public function test_adminユーザーはok()
    {
        $this->createAdmin();
        $processA = Process::factory()->create();
        $processB = Process::factory()->create();

        $response = $this->getJson('/api/v1/andon');

        $response->assertOk();
        $response->assertJsonPath('0.process_id', min($processA->process_id, $processB->process_id));
    }
}
