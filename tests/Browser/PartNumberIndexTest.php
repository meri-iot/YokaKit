<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\PartNumber;
use App\Models\User;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\PartNumberIndexPage;
use Tests\DuskTestCase;

/**
 * 品番一覧画面のブラウザテスト
 *
 * テスト対象の動作:
 * - 一覧画面が表示される
 * - 品番一覧が表示される
 * - 作成ボタンで作成画面へ遷移する
 * - 編集ボタンで編集画面へ遷移する
 * - 削除ボタンで削除確認ダイアログが表示される
 *
 * 注意: このクラスは Web サーバー(LAMPP)と同一の yokakit DB に一時ユーザーと
 * 一時品番を作成し、テスト終了後に削除する。
 */
class PartNumberIndexTest extends DuskTestCase
{
    private User $adminUser;
    private PartNumber $partNumber;

    /**
     * ChromeDriver の自動起動をスキップする。
     * Selenium Grid を使用するためローカルの ChromeDriver は不要。
     *
     * @beforeClass
     */
    public static function prepare(): void
    {
        // Selenium Grid を直接使用するため何もしない
    }

    /**
     * Selenium Grid に接続する RemoteWebDriver を返す
     *
     * @return RemoteWebDriver
     */
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
        $this->partNumber = PartNumber::factory()->create([
            'part_number_name' => 'index-browser-test-pn',
            'barcode'          => 'index-browser-test-code',
        ]);
    }

    protected function tearDown(): void
    {
        PartNumber::where('part_number_name', 'like', 'index-browser%')->delete();
        $this->partNumber->delete();
        $this->adminUser->delete();
        parent::tearDown();
    }

    /**
     * 品番一覧画面が表示される
     */
    public function test_一覧画面が表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PartNumberIndexPage())
                ->assertPresent('@table');
        });
    }

    /**
     * 品番が一覧に表示される
     */
    public function test_品番が一覧に表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PartNumberIndexPage())
                ->assertSee('index-browser-test-pn')
                ->assertSee('index-browser-test-code');
        });
    }

    /**
     * 作成ボタンで作成画面へ遷移する
     */
    public function test_作成ボタンで作成画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PartNumberIndexPage())
                ->click('@create_button')
                ->assertPathIs('/yokakit/part-number/create');
        });
    }

    /**
     * 編集ボタンで編集画面へ遷移する
     */
    public function test_編集ボタンで編集画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PartNumberIndexPage())
                // 編集ボタンのセレクタ：品番の編集ページへのリンク
                ->click("a[href*='part-number/{$this->partNumber->part_number_id}/edit']")
                ->assertPathIs("/yokakit/part-number/{$this->partNumber->part_number_id}/edit");
        });
    }

    /**
     * 削除ボタンで削除確認ダイアログが表示される
     */
    public function test_削除ボタンで削除確認ダイアログが表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PartNumberIndexPage())
                // 削除ボタンはデータターゲットが設定されているので、そのセレクタをクリック
                ->click("button[data-target='#part_number_{$this->partNumber->part_number_id}']")
                // 削除確認ダイアログが表示される
                ->assertPresent("#part_number_{$this->partNumber->part_number_id}")
                ->assertSee(__('yokakit.confirm_delete', ['target' => __('yokakit.part_number')]));
        });
    }
}
