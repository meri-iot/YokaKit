<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\User;
use App\Models\Worker;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\WorkerIndexPage;
use Tests\DuskTestCase;

class WorkerIndexTest extends DuskTestCase
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
            'identification_number' => 'W-INDEX-001',
            'worker_name' => 'worker-index-target',
            'mac_address' => '00:11:22:33:44:77',
        ]);
    }

    protected function tearDown(): void
    {
        Worker::where('identification_number', 'like', 'W-INDEX-%')->delete();
        $this->worker->delete();
        $this->adminUser->delete();
        parent::tearDown();
    }

    public function test_一覧画面が表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new WorkerIndexPage())
                ->assertPresent('@table');
        });
    }

    public function test_作業者が一覧に表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new WorkerIndexPage())
                ->assertSee('W-INDEX-001')
                ->assertSee('worker-index-target')
                ->assertSee('00:11:22:33:44:77');
        });
    }

    public function test_作成ボタンで作成画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new WorkerIndexPage())
                ->click('@create_button')
                ->assertPathIs('/yokakit/worker/create');
        });
    }

    public function test_編集ボタンで編集画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new WorkerIndexPage())
                ->click("a[href*='worker/{$this->worker->worker_id}/edit']")
                ->assertPathIs("/yokakit/worker/{$this->worker->worker_id}/edit");
        });
    }

    public function test_削除ボタンで削除確認ダイアログが表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new WorkerIndexPage())
                ->click("button[data-target='#worker_{$this->worker->worker_id}']")
                ->assertPresent("#worker_{$this->worker->worker_id}")
                ->assertSee(__('yokakit.confirm_delete', ['target' => __('yokakit.worker')]));
        });
    }
}
