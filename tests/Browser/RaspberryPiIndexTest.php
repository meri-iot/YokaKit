<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\RaspberryPi;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\RaspberryPiIndexPage;
use Tests\DuskTestCase;

class RaspberryPiIndexTest extends DuskTestCase
{
    private User $adminUser;
    private RaspberryPi $raspberryPi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 5]);
        $this->raspberryPi = RaspberryPi::factory()->create([
            'raspberry_pi_name' => 'browser-raspi-index-001',
            'ip_address' => '192.168.10.51',
        ]);
    }

    protected function tearDown(): void
    {
        $this->raspberryPi->delete();
        $this->adminUser->delete();
        parent::tearDown();
    }

    public function test_一覧画面が表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new RaspberryPiIndexPage())
                ->assertPresent('@table');
        });
    }

    public function test_ラズパイが一覧に表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new RaspberryPiIndexPage())
                ->assertSee('browser-raspi-index-001')
                ->assertSee('192.168.10.51');
        });
    }

    public function test_作成ボタンで作成画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new RaspberryPiIndexPage())
                ->click('@create_button')
                ->assertPathIs('/yokakit/raspberry-pi/create');
        });
    }

    public function test_編集ボタンで編集画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new RaspberryPiIndexPage())
                ->click("a[href*='raspberry-pi/{$this->raspberryPi->raspberry_pi_id}/edit']")
                ->assertPathIs("/yokakit/raspberry-pi/{$this->raspberryPi->raspberry_pi_id}/edit");
        });
    }

    public function test_削除ボタンで削除確認ダイアログが表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new RaspberryPiIndexPage())
                ->click("button[data-target='#raspberry_pi_{$this->raspberryPi->raspberry_pi_id}']")
                ->assertPresent("#raspberry_pi_{$this->raspberryPi->raspberry_pi_id}")
                ->assertSee(__('yokakit.confirm_delete', ['target' => __('yokakit.raspberry_pi')]));
        });
    }
}
