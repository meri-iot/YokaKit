<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\User;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\UserEditPage;
use Tests\DuskTestCase;

class UserEditTest extends DuskTestCase
{
    private User $normalUser;

    /**
     * @beforeClass
     */
    public static function prepare(): void {}

    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments([
            '--disable-gpu',
            '--headless',
            '--window-size=1920,1080',
            '--no-sandbox',
        ]);

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? 'http://10.4.5.209:4444/wd/hub',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY,
                $options
            )
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalUser = User::factory()->create([
            'name' => 'browser-edit-user',
            'email' => 'browser-edit-user@example.com',
        ]);
    }

    protected function tearDown(): void
    {
        $this->normalUser->delete();
        parent::tearDown();
    }

    public function test_フォームに既存値が表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->normalUser)
                ->visit(new UserEditPage())
                ->assertInputValue('@name', 'browser-edit-user')
                ->assertInputValue('@email', 'browser-edit-user@example.com')
                ->assertPresent('@submit_button');
        });
    }

    public function test_プロフィール更新で詳細画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->normalUser)
                ->visit(new UserEditPage())
                ->clear('@name')
                ->type('@name', 'browser-edit-updated')
                ->clear('@email')
                ->type('@email', 'browser-edit-updated@example.com')
                ->click('@submit_button')
                ->assertPathIs('/yokakit/user/profile');
        });
    }

    public function test_必須項目未入力で送信がブロックされる(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->normalUser)
                ->visit(new UserEditPage())
                ->clear('@name')
                ->click('@submit_button')
                ->assertPathIs('/yokakit/user/edit');
        });
    }

    public function test_戻るボタンで詳細画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->normalUser)
                ->visit(new UserEditPage())
                ->clickLink(__('yokakit.back'))
                ->assertPathIs('/yokakit/user/profile');
        });
    }
}
