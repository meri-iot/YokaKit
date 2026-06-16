<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\User;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\UserCreatePage;
use Tests\DuskTestCase;

class UserCreateTest extends DuskTestCase
{
    private User $systemUser;
    private ?User $createdUser = null;

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
        $this->systemUser = User::factory()->create(['role' => 1]);
    }

    protected function tearDown(): void
    {
        $this->createdUser?->delete();
        User::where('email', 'like', 'browser-create-user%@example.com')->delete();
        $this->systemUser->delete();
        parent::tearDown();
    }

    public function test_フォームフィールドが表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->systemUser)
                ->visit(new UserCreatePage())
                ->assertPresent('@name')
                ->assertPresent('@email')
                ->assertPresent('@role')
                ->assertPresent('@password')
                ->assertPresent('@password_confirmation')
                ->assertPresent('@submit_button');
        });
    }

    public function test_全フィールドを入力して登録すると一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->systemUser)
                ->visit(new UserCreatePage())
                ->type('@name', 'browser-create-user')
                ->type('@email', 'browser-create-user@example.com')
                ->select('@role', '5')
                ->type('@password', 'password123')
                ->type('@password_confirmation', 'password123')
                ->click('@submit_button')
                ->assertPathIs('/yokakit/user');

            $this->createdUser = User::where('email', 'browser-create-user@example.com')->first();
        });
    }

    public function test_必須項目未入力で送信がブロックされる(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->systemUser)
                ->visit(new UserCreatePage())
                ->click('@submit_button')
                ->assertPathIs('/yokakit/user/create');
        });
    }

    public function test_戻るボタンで一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->systemUser)
                ->visit(new UserCreatePage())
                ->clickLink(__('yokakit.back'))
                ->assertPathIs('/yokakit/user');
        });
    }
}
