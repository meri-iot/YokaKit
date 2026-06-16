<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\PartNumber;
use App\Models\User;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\PartNumberCreatePage;
use Tests\DuskTestCase;

/**
 * 品番追加画面のブラウザテスト
 *
 * テスト対象の動作:
 * - フォームフィールドが表示される
 * - 必須フィールド未入力時にブラウザバリデーションが機能する
 * - 正常入力で登録が完了し一覧画面へ遷移する
 * - barcode・remark が任意入力であること
 *
 * 注意: このクラスは Web サーバー(LAMPP)と同一の yokakit DB に一時ユーザーと
 * 一時品番を作成し、テスト終了後に削除する。
 */
class PartNumberCreateTest extends DuskTestCase
{
    private User $adminUser;
    private ?PartNumber $partNumber = null;

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
    }

    protected function tearDown(): void
    {
        $this->partNumber?->delete();
        $this->adminUser->delete();
        parent::tearDown();
    }

    /**
     * 品番追加フォームの全フィールドが表示される
     */
    public function test_フォームフィールドが表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PartNumberCreatePage())
                ->assertPresent('@part_number_name')
                ->assertPresent('@barcode')
                ->assertPresent('@remark')
                ->assertPresent('@submit_button');
        });
    }

    /**
     * 品番名・バーコード・備考を入力して登録すると一覧画面へ遷移する
     */
    public function test_全フィールドを入力して登録すると一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PartNumberCreatePage())
                ->type('@part_number_name', 'テスト品番')
                ->type('@barcode', 'TEST-BARCODE-001')
                ->type('@remark', 'テスト備考')
                ->click('@submit_button')
                ->assertPathIs('/yokakit/part-number');

            $this->partNumber = PartNumber::where('part_number_name', 'テスト品番')->first();
        });
    }

    /**
     * バーコードと備考を省略して品番名のみ入力でも登録できる
     */
    public function test_品番名のみ入力で登録できる(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PartNumberCreatePage())
                ->type('@part_number_name', '品番名のみ登録')
                ->click('@submit_button')
                ->assertPathIs('/yokakit/part-number');

            $this->partNumber = PartNumber::where('part_number_name', '品番名のみ登録')->first();
        });
    }

    /**
     * 品番名が未入力の場合はブラウザのHTML5バリデーションで送信がブロックされる
     */
    public function test_品番名が未入力の場合は送信がブロックされる(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PartNumberCreatePage())
                ->click('@submit_button')
                // 送信がブロックされ、create ページに留まる
                ->assertPathIs('/yokakit/part-number/create');
        });
    }

    /**
     * 戻るボタンを押すと一覧画面へ遷移する
     */
    public function test_戻るボタンで一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PartNumberCreatePage())
                ->clickLink(__('yokakit.back'))
                ->assertPathIs('/yokakit/part-number');
        });
    }
}
