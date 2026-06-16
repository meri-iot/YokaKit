<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\User;
use App\Models\Worker;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\WorkerEditPage;
use Tests\DuskTestCase;

class WorkerEditTest extends DuskTestCase
{
    private User $adminUser;
    private Worker $worker;

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
        $this->worker = Worker::factory()->create([
            'identification_number' => 'W-EDIT-001',
            'worker_name' => 'worker-edit-target',
            'mac_address' => '00:11:22:33:44:66',
        ]);
    }

    protected function tearDown(): void
    {
        Worker::where('identification_number', 'like', 'W-EDIT-%')->delete();
        $this->worker->delete();
        $this->adminUser->delete();
        parent::tearDown();
    }

    public function test_フォームに既存値が表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new WorkerEditPage($this->worker->worker_id))
                ->assertInputValue('@identification_number', 'W-EDIT-001')
                ->assertInputValue('@worker_name', 'worker-edit-target')
                ->assertInputValue('@mac_address', '00:11:22:33:44:66')
                ->assertPresent('@submit_button');
        });
    }

    public function test_全フィールドを更新すると一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new WorkerEditPage($this->worker->worker_id))
                ->clear('@identification_number')
                ->type('@identification_number', 'W-EDIT-002')
                ->clear('@worker_name')
                ->type('@worker_name', 'worker-edit-updated')
                ->clear('@mac_address')
                ->type('@mac_address', '00:11:22:33:44:67')
                ->click('@submit_button')
                ->assertPathIs('/yokakit/worker');
        });
    }

    public function test_mac_addressを空にして更新できる(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new WorkerEditPage($this->worker->worker_id))
                ->clear('@mac_address')
                ->click('@submit_button')
                ->assertPathIs('/yokakit/worker');
        });
    }

    public function test_必須項目未入力で送信がブロックされる(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new WorkerEditPage($this->worker->worker_id))
                ->clear('@identification_number')
                ->click('@submit_button')
                ->assertPathIs("/yokakit/worker/{$this->worker->worker_id}/edit");
        });
    }

    public function test_戻るボタンで一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new WorkerEditPage($this->worker->worker_id))
                ->clickLink(__('yokakit.back'))
                ->assertPathIs('/yokakit/worker');
        });
    }
}
