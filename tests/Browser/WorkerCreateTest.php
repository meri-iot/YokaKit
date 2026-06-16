<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\User;
use App\Models\Worker;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\WorkerCreatePage;
use Tests\DuskTestCase;

class WorkerCreateTest extends DuskTestCase
{
    private User $adminUser;
    private ?Worker $worker = null;

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
        $this->worker?->delete();
        $this->adminUser->delete();
        parent::tearDown();
    }

    public function test_フォームフィールドが表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new WorkerCreatePage())
                ->assertPresent('@identification_number')
                ->assertPresent('@worker_name')
                ->assertPresent('@mac_address')
                ->assertPresent('@submit_button');
        });
    }

    public function test_全フィールドを入力して登録すると一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new WorkerCreatePage())
                ->type('@identification_number', 'W-CREATE-001')
                ->type('@worker_name', 'worker-create')
                ->type('@mac_address', '00:11:22:33:44:88')
                ->click('@submit_button')
                ->assertPathIs('/yokakit/worker');

            $this->worker = Worker::where('identification_number', 'W-CREATE-001')->first();
        });
    }

    public function test_mac_address省略でも登録できる(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new WorkerCreatePage())
                ->type('@identification_number', 'W-CREATE-002')
                ->type('@worker_name', 'worker-create-no-mac')
                ->click('@submit_button')
                ->assertPathIs('/yokakit/worker');

            $this->worker = Worker::where('identification_number', 'W-CREATE-002')->first();
        });
    }

    public function test_必須項目未入力で送信がブロックされる(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new WorkerCreatePage())
                ->click('@submit_button')
                ->assertPathIs('/yokakit/worker/create');
        });
    }

    public function test_戻るボタンで一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new WorkerCreatePage())
                ->clickLink(__('yokakit.back'))
                ->assertPathIs('/yokakit/worker');
        });
    }
}
