<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\PlannedOutage;
use App\Models\User;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\PlannedOutageEditPage;
use Tests\DuskTestCase;

class PlannedOutageEditTest extends DuskTestCase
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
            'planned_outage_name' => 'browser-po-edit-001',
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
        ]);
    }

    protected function tearDown(): void
    {
        PlannedOutage::where('planned_outage_name', 'like', 'browser-po-edit%')->delete();
        $this->plannedOutage->delete();
        $this->adminUser->delete();
        parent::tearDown();
    }

    public function test_フォームに既存値が表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PlannedOutageEditPage($this->plannedOutage->planned_outage_id))
                ->assertInputValue('@planned_outage_name', 'browser-po-edit-001')
                ->assertPresent('@start_time')
                ->assertPresent('@end_time')
                ->assertPresent('@submit_button');
        });
    }

    public function test_更新すると一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PlannedOutageEditPage($this->plannedOutage->planned_outage_id))
                ->clear('@planned_outage_name')
                ->type('@planned_outage_name', 'browser-po-edit-002')
                ->type('@start_time', '14:00')
                ->type('@end_time', '15:00')
                ->click('@submit_button')
                ->assertPathIs('/yokakit/planned-outage');
        });
    }

    public function test_必須項目未入力で送信がブロックされる(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PlannedOutageEditPage($this->plannedOutage->planned_outage_id))
                ->clear('@planned_outage_name')
                ->click('@submit_button')
                ->assertPathIs("/yokakit/planned-outage/{$this->plannedOutage->planned_outage_id}/edit");
        });
    }

    public function test_戻るボタンで一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PlannedOutageEditPage($this->plannedOutage->planned_outage_id))
                ->clickLink(__('yokakit.back'))
                ->assertPathIs('/yokakit/planned-outage');
        });
    }
}
