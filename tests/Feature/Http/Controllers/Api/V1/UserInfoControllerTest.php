<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class UserInfoControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;
    use WithFaker;

    public function test_未認証ユーザーはunauthorized()
    {
        $response = $this->getJson('/api/v1/user');

        $response->assertUnauthorized();
    }

    public function test_guestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $this->get('/api/v1/user');
    }

    public function test_adminユーザーはok()
    {
        $user = $this->createAdmin();
        $response = $this->get('/api/v1/user');
        $response->assertStatus(200);
        $response->assertJson([
            'name' => $user->name,
            'email' => $user->email,
            'role' => 5,
        ]);
    }
}
