<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Process;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class ProcessInfoControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;
    use WithFaker;

    public function test_guestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $this->get('/api/v1/processes');
    }

    public function test_adminユーザーはok()
    {
        $this->createAdmin();
        $processA = Process::factory()->create();
        $processB = Process::factory()->create();

        $response = $this->getJson('/api/v1/processes');

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['process_id' => $processA->process_id]);
        $response->assertJsonFragment(['process_id' => $processB->process_id]);
    }
}
