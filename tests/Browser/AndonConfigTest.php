<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Process;
use App\Models\User;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\AndonConfigPage;
use Tests\DuskTestCase;

/**
 * アンドン設定編集画面のブラウザテスト
 *
 * テスト対象の JavaScript 動作:
 * - column_count 変更時にソート可能プロセスの列クラスが更新される
 * - item_column_count 変更時に表示アイテムの列クラスが更新される
 * - チェックボックスOFF でプロセスコンテナが半透明(opacity: 0.25)になる
 * - チェックボックスON でプロセスコンテナのopacityが除去される
 *
 * 注意: このクラスは Web サーバー(LAMPP)と同一の yokakit DB に一時ユーザーと
 * 一時プロセスを作成し、テスト終了後に削除する。
 */
class AndonConfigTest extends DuskTestCase
{
    private User $user;
    private ?Process $process = null;

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
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        $this->process?->delete();
        $this->user->delete();
        parent::tearDown();
    }

    /**
     * 設定編集画面の主要フォームフィールドが表示される
     */
    public function test_設定編集画面のフォームフィールドが表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->user)
                ->visit(new AndonConfigPage())
                ->assertPresent('@column_count')
                ->assertPresent('@item_column_count')
                ->assertPresent('input[name="row_count"]')
                ->assertPresent('input[name="auto_play_speed"]')
                ->assertPresent('input[name="slide_speed"]')
                ->assertPresent('#easing')
                ->assertPresent('input[name="font_ratio"]')
                ->assertPresent('@save_button');
        });
    }

    /**
     * is_show_* スイッチが全 13 項目表示される
     */
    public function test_is_show_スイッチが全13項目表示される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->user)
                ->visit(new AndonConfigPage());

            foreach (
                [
                    'is_show_part_number',
                    'is_show_start',
                    'is_show_good_count',
                    'is_show_good_rate',
                    'is_show_defective_count',
                    'is_show_defective_rate',
                    'is_show_plan_count',
                    'is_show_achievement_rate',
                    'is_show_cycle_time',
                    'is_show_time_operating_rate',
                    'is_show_performance_operating_rate',
                    'is_show_overall_equipment_effectiveness',
                    'is_show_goal',
                ] as $field
            ) {
                $browser->assertPresent("input[name=\"{$field}\"]");
            }
        });
    }

    /**
     * column_count を変更すると sortable-process の列クラスが動的に更新される
     *
     * デフォルト column_count=4 → col-3、変更後 column_count=2 → col-6
     */
    public function test_column_count変更でプロセスの列クラスが更新される(): void
    {
        $this->process = Process::factory()->create();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->user)
                ->visit(new AndonConfigPage())
                // デフォルト(column_count=4)では col-3 が付いている
                ->assertHasClass('.sortable-process', 'col-3')
                // column_count を 2 に変更
                ->select('@column_count', '2')
                ->pause(200)
                // col-3 が除去されて col-6 が付与される
                ->assertHasClass('.sortable-process', 'col-6')
                ->assertClassMissing('.sortable-process', 'col-3');
        });
    }

    /**
     * item_column_count を変更すると display-item の列クラスが動的に更新される
     *
     * デフォルト item_column_count=3 → col-4、変更後 item_column_count=1 → col-12
     */
    public function test_item_column_count変更で表示アイテムの列クラスが更新される(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->user)
                ->visit(new AndonConfigPage())
                // デフォルト(item_column_count=3)では col-4 が付いている
                ->assertHasClass('.display-item', 'col-4')
                // item_column_count を 1 に変更
                ->select('@item_column_count', '1')
                ->pause(200)
                // col-4 が除去されて col-12 が付与される
                ->assertHasClass('.display-item', 'col-12')
                ->assertClassMissing('.display-item', 'col-4');
        });
    }

    /**
     * チェックボックスをOFFにするとプロセスコンテナに opacity: 0.25 が設定される
     */
    public function test_チェックボックスOFFでプロセスが半透明になる(): void
    {
        $this->process = Process::factory()->create();
        $pid = $this->process->process_id;

        $this->browse(function (Browser $browser) use ($pid): void {
            $browser->loginAs($this->user)
                ->visit(new AndonConfigPage())
                // デフォルトで表示ON(checked)→ opacity なし
                ->assertAttributeMissing(AndonConfigPage::processContainer($pid), 'style')
                // チェックボックスをOFFにする
                ->uncheck(AndonConfigPage::displayCheckbox($pid))
                ->pause(200)
                // opacity: 0.25 が設定される
                ->assertAttributeContains(
                    AndonConfigPage::processContainer($pid),
                    'style',
                    '0.25'
                );
        });
    }

    /**
     * チェックボックスをONにすると opacity が除去される
     */
    public function test_チェックボックスONでプロセスのopacityが除去される(): void
    {
        $this->process = Process::factory()->create();
        $pid = $this->process->process_id;

        $this->browse(function (Browser $browser) use ($pid): void {
            $browser->loginAs($this->user)
                ->visit(new AndonConfigPage())
                // まずチェックをOFFにして半透明にする
                ->uncheck(AndonConfigPage::displayCheckbox($pid))
                ->pause(200)
                ->assertAttributeContains(
                    AndonConfigPage::processContainer($pid),
                    'style',
                    '0.25'
                )
                // チェックをONに戻す
                ->check(AndonConfigPage::displayCheckbox($pid))
                ->pause(200)
                // opacity が除去される(style属性が空またはopacity無し)
                ->assertScript(
                    "return document.querySelector('" . AndonConfigPage::processContainer($pid) . "').style.opacity",
                    ''
                );
        });
    }

    /**
     * フォームを送信すると home へリダイレクトしてトースト通知が表示される
     */
    public function test_フォーム送信後にトースト通知とともにhomeへ遷移する(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->user)
                ->visit(new AndonConfigPage())
                ->press('@save_button')
                ->waitForLocation('/yokakit/home', 5)
                ->assertPathIs('/yokakit/home')
                // トーストメッセージが表示される(成功または失敗どちらかが出ること)
                ->assertPresent('.toast, .alert');
        });
    }
}
