<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\User;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\UserIndexPage;
use Tests\DuskTestCase;

class UserIndexTest extends DuskTestCase
{
    private User $systemUser;
    private User $targetUser;

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
        $this->targetUser = User::factory()->create([
            'name' => 'browser-index-user',
            'email' => 'browser-index-user@example.com',
            'role' => 5,
        ]);
    }

    protected function tearDown(): void
    {
        $this->targetUser->delete();
        $this->systemUser->delete();
        parent::tearDown();
    }

    public function test_一覧画面が表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->systemUser)
                ->visit(new UserIndexPage())
                ->assertPresent('@table');
        });
    }

    public function test_ユーザーが一覧に表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->systemUser)
                ->visit(new UserIndexPage())
                ->assertSee('browser-index-user')
                ->assertSee('browser-index-user@example.com');
        });
    }

    public function test_作成ボタンで作成画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->systemUser)
                ->visit(new UserIndexPage())
                ->click('@create_button')
                ->assertPathIs('/yokakit/user/create');
        });
    }

    public function test_削除ボタンで削除確認ダイアログが表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->systemUser)
                ->visit(new UserIndexPage())
                ->click("button[data-target='#user_{$this->targetUser->id}']")
                ->assertPresent("#user_{$this->targetUser->id}")
                ->assertSee(__('yokakit.confirm_delete', ['target' => __('yokakit.user')]));
        });
    }
}
