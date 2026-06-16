<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\PartNumber;
use App\Models\User;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\PartNumberEditPage;
use Tests\DuskTestCase;

/**
 * 品番編集画面のブラウザテスト
 *
 * テスト対象の動作:
 * - フォームフィールドが既存値で表示される
 * - 必須フィールド未入力時にブラウザバリデーションが機能する
 * - 正常入力で更新が完了し一覧画面へ遷移する
 * - barcode・remark が任意入力であること
 * - 戻るボタンで一覧画面へ遷移する
 *
 * 注意: このクラスは Web サーバー(LAMPP)と同一の yokakit DB に一時ユーザーと
 * 一時品番を作成し、テスト終了後に削除する。
 */
class PartNumberEditTest extends DuskTestCase
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
            'part_number_name' => 'edit-browser-target',
            'barcode'          => 'edit-browser-barcode',
            'remark'           => 'edit-browser-remark',
        ]);
    }

    protected function tearDown(): void
    {
        PartNumber::where('part_number_name', 'like', 'edit-browser%')->delete();
        $this->partNumber->delete();
        $this->adminUser->delete();
        parent::tearDown();
    }

    /**
     * 品番編集フォームの全フィールドが既存値で表示される
     */
    public function test_フォームフィールドが既存値で表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PartNumberEditPage($this->partNumber->part_number_id))
                ->assertInputValue('@part_number_name', 'edit-browser-target')
                ->assertInputValue('@barcode', 'edit-browser-barcode')
                ->assertInputValue('@remark', 'edit-browser-remark')
                ->assertPresent('@submit_button');
        });
    }

    /**
     * 品番名・バーコード・備考を変更して更新すると一覧画面へ遷移する
     */
    public function test_全フィールドを変更して更新すると一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PartNumberEditPage($this->partNumber->part_number_id))
                ->clear('@part_number_name')
                ->type('@part_number_name', 'edit-browser-updated')
                ->clear('@barcode')
                ->type('@barcode', 'edit-browser-barcode-updated')
                ->clear('@remark')
                ->type('@remark', '更新後備考')
                ->click('@submit_button')
                ->assertPathIs('/yokakit/part-number');
        });
    }

    /**
     * バーコードと備考を削除し品番名のみで更新できる
     */
    public function test_品番名のみで更新できる(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PartNumberEditPage($this->partNumber->part_number_id))
                ->clear('@barcode')
                ->clear('@remark')
                ->click('@submit_button')
                ->assertPathIs('/yokakit/part-number');
        });
    }

    /**
     * 品番名が未入力の場合はブラウザのHTML5バリデーションで送信がブロックされる
     */
    public function test_品番名が未入力の場合は送信がブロックされる(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PartNumberEditPage($this->partNumber->part_number_id))
                ->clear('@part_number_name')
                ->click('@submit_button')
                // 送信がブロックされ、edit ページに留まる
                ->assertPathIs("/yokakit/part-number/{$this->partNumber->part_number_id}/edit");
        });
    }

    /**
     * 戻るボタンを押すと一覧画面へ遷移する
     */
    public function test_戻るボタンで一覧画面へ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->adminUser)
                ->visit(new PartNumberEditPage($this->partNumber->part_number_id))
                ->clickLink(__('yokakit.back'))
                ->assertPathIs('/yokakit/part-number');
        });
    }
}
