<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\RaspberryPi;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\RaspberryPiEditPage;
use Tests\DuskTestCase;

class RaspberryPiEditTest extends DuskTestCase
{
    private User $adminUser;
    private RaspberryPi $raspberryPi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 5]);
        $this->raspberryPi = RaspberryPi::factory()->create([
            'raspberry_pi_name' => 'browser-raspi-edit-001',
            'ip_address' => '192.168.10.201',
        ]);
    }

    protected function tearDown(): void
    {
        RaspberryPi::where('raspberry_pi_name', 'like', 'browser-raspi-edit%')->delete();
        $this->adminUser->delete();
        parent::tearDown();
    }

    public function test_フォームに既存値が表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new RaspberryPiEditPage($this->raspberryPi->raspberry_pi_id))
                ->assertInputValue('@raspberry_pi_name', 'browser-raspi-edit-001')
                ->assertInputValue('@ip_address', '192.168.10.201')
                ->assertPresent('@submit_button');
        });
    }

    public function test_更新すると一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new RaspberryPiEditPage($this->raspberryPi->raspberry_pi_id))
                ->clear('@raspberry_pi_name')
                ->type('@raspberry_pi_name', 'browser-raspi-edit-002')
                ->clear('@ip_address')
                ->type('@ip_address', '192.168.10.202')
                ->click('@submit_button')
                ->assertPathIs('/yokakit/raspberry-pi');
        });
    }

    public function test_必須項目未入力で送信がブロックされる(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new RaspberryPiEditPage($this->raspberryPi->raspberry_pi_id))
                ->clear('@raspberry_pi_name')
                ->click('@submit_button')
                ->assertPathIs("/yokakit/raspberry-pi/{$this->raspberryPi->raspberry_pi_id}/edit");
        });
    }

    public function test_戻るボタンで一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new RaspberryPiEditPage($this->raspberryPi->raspberry_pi_id))
                ->clickLink(__('yokakit.back'))
                ->assertPathIs('/yokakit/raspberry-pi');
        });
    }
}
