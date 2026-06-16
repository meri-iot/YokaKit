<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\RaspberryPi;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\RaspberryPiCreatePage;
use Tests\DuskTestCase;

class RaspberryPiCreateTest extends DuskTestCase
{
    private User $adminUser;
    private ?RaspberryPi $raspberryPi = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 5]);
    }

    protected function tearDown(): void
    {
        $this->raspberryPi?->delete();
        RaspberryPi::where('raspberry_pi_name', 'like', 'browser-raspi-create%')->delete();
        $this->adminUser->delete();
        parent::tearDown();
    }

    public function test_フォームフィールドが表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new RaspberryPiCreatePage())
                ->assertPresent('@raspberry_pi_name')
                ->assertPresent('@ip_address')
                ->assertPresent('@submit_button');
        });
    }

    public function test_全フィールドを入力して登録すると一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new RaspberryPiCreatePage())
                ->type('@raspberry_pi_name', 'browser-raspi-create-001')
                ->type('@ip_address', '192.168.10.101')
                ->click('@submit_button')
                ->assertPathIs('/yokakit/raspberry-pi');

            $this->raspberryPi = RaspberryPi::where('raspberry_pi_name', 'browser-raspi-create-001')->first();
        });
    }

    public function test_必須項目未入力で送信がブロックされる(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new RaspberryPiCreatePage())
                ->click('@submit_button')
                ->assertPathIs('/yokakit/raspberry-pi/create');
        });
    }

    public function test_戻るボタンで一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new RaspberryPiCreatePage())
                ->clickLink(__('yokakit.back'))
                ->assertPathIs('/yokakit/raspberry-pi');
        });
    }
}
