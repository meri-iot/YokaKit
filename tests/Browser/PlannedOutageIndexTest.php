<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\PlannedOutage;
use App\Models\User;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\PlannedOutageIndexPage;
use Tests\DuskTestCase;

class PlannedOutageIndexTest extends DuskTestCase
{
    private User $adminUser;
    private PlannedOutage $plannedOutage;

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
        $this->plannedOutage = PlannedOutage::factory()->create([
            'planned_outage_name' => 'browser-po-index-001',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
        ]);
    }

    protected function tearDown(): void
    {
        $this->plannedOutage->delete();
        $this->adminUser->delete();
        parent::tearDown();
    }

    public function test_一覧画面が表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PlannedOutageIndexPage())
                ->assertPresent('@table');
        });
    }

    public function test_計画停止時間が一覧に表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PlannedOutageIndexPage())
                ->assertSee('browser-po-index-001')
                ->assertSee('08:00')
                ->assertSee('09:00');
        });
    }

    public function test_作成ボタンで作成画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PlannedOutageIndexPage())
                ->click('@create_button')
                ->assertPathIs('/yokakit/planned-outage/create');
        });
    }

    public function test_編集ボタンで編集画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PlannedOutageIndexPage())
                ->click("a[href*='planned-outage/{$this->plannedOutage->planned_outage_id}/edit']")
                ->assertPathIs("/yokakit/planned-outage/{$this->plannedOutage->planned_outage_id}/edit");
        });
    }

    public function test_削除ボタンで削除確認ダイアログが表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PlannedOutageIndexPage())
                ->click("button[data-target='#planned_outage_{$this->plannedOutage->planned_outage_id}']")
                ->assertPresent("#planned_outage_{$this->plannedOutage->planned_outage_id}")
                ->assertSee(__('yokakit.confirm_delete', ['target' => __('yokakit.planned_outage')]));
        });
    }
}
