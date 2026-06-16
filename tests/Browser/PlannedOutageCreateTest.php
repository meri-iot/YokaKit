<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\PlannedOutage;
use App\Models\User;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\PlannedOutageCreatePage;
use Tests\DuskTestCase;

class PlannedOutageCreateTest extends DuskTestCase
{
    private User $adminUser;
    private ?PlannedOutage $plannedOutage = null;

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
        $this->adminUser = User::factory()->create(['role' => 5]);
    }

    protected function tearDown(): void
    {
        $this->plannedOutage?->delete();
        PlannedOutage::where('planned_outage_name', 'like', 'browser-po-create%')->delete();
        $this->adminUser->delete();
        parent::tearDown();
    }

    public function test_フォームフィールドが表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PlannedOutageCreatePage())
                ->assertPresent('@planned_outage_name')
                ->assertPresent('@start_time')
                ->assertPresent('@end_time')
                ->assertPresent('@submit_button');
        });
    }

    public function test_全フィールドを入力して登録すると一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PlannedOutageCreatePage())
                ->type('@planned_outage_name', 'browser-po-create-001')
                ->type('@start_time', '08:00')
                ->type('@end_time', '09:00')
                ->click('@submit_button')
                ->assertPathIs('/yokakit/planned-outage');

            $this->plannedOutage = PlannedOutage::where('planned_outage_name', 'browser-po-create-001')->first();
        });
    }

    public function test_必須項目未入力で送信がブロックされる(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PlannedOutageCreatePage())
                ->click('@submit_button')
                ->assertPathIs('/yokakit/planned-outage/create');
        });
    }

    public function test_戻るボタンで一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PlannedOutageCreatePage())
                ->clickLink(__('yokakit.back'))
                ->assertPathIs('/yokakit/planned-outage');
        });
    }
}
