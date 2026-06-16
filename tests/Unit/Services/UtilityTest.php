<?php

namespace Tests\Unit\Services;

use App\Models\Process;
use App\Services\Utility;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UtilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_nowはミリ秒精度で時刻を返す(): void
    {
        $now = Utility::now();

        $this->assertSame(0, $now->microsecond % 1000);
    }

    public function test_formatは指定フォーマットで文字列化する(): void
    {
        $date = Carbon::create(2026, 4, 8, 12, 34, 56, config('app.timezone'));

        $this->assertSame('2026-04-08', Utility::format($date, 'Y-m-d'));
    }

    public function test_mergeは年月日と時分秒を結合する(): void
    {
        $ymd = Carbon::create(2026, 4, 8, 0, 0, 0, config('app.timezone'));
        $hms = Carbon::create(2000, 1, 1, 9, 10, 11, config('app.timezone'));

        $merged = Utility::merge($ymd, $hms);

        $this->assertSame('2026-04-08 09:10:11', $merged->format('Y-m-d H:i:s'));
    }

    public function test_parseは指定フォーマットの文字列をCarbonへ変換する(): void
    {
        $parsed = Utility::parse('2026-04-08', 'Y-m-d');

        $this->assertSame('2026-04-08', $parsed->format('Y-m-d'));
    }

    public function test_parseはnull入力でInvalidArgumentExceptionを投げる(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $parsed = Utility::parse(null);
    }

    public function test_parseは空文字入力でInvalidArgumentExceptionを投げる(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Utility::parse('');
    }

    public function test_sanitizeFileNameで不正文字が除去される(): void
    {
        $actual = Utility::sanitizeFileName('a/b:c*?"<>|test');

        $this->assertSame('abctest', $actual);
    }

    public function test_sanitizeFileNameで不正文字のみの場合はdownloadにフォールバックする(): void
    {
        $actual = Utility::sanitizeFileName("\\/:*?\"<>|\r\n\t");

        $this->assertSame('download', $actual);
    }

    public function test_sanitizeFileNameで空白とドットだけの場合はdownloadにフォールバックする(): void
    {
        $actual = Utility::sanitizeFileName(' .  ');

        $this->assertSame('download', $actual);
    }

    public function test_ensureModelExistsはモデルがnullの場合に例外を投げる(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Utility::ensureModelExists(null);
    }

    public function test_ensureModelExistsはモデルが存在する場合に例外を投げない(): void
    {
        $process = Process::factory()->create();

        Utility::ensureModelExists($process);

        $this->assertTrue(true);
    }

    public function test_ensureOperationSucceededは失敗時に例外を投げる(): void
    {
        $process = Process::factory()->create();
        $this->expectException(ModelNotFoundException::class);

        Utility::ensureOperationSucceeded($process, false);
    }

    public function test_ganttChartDisplayRangeMinutesは表示範囲一覧を返す(): void
    {
        $actual = Utility::ganttChartDisplayRangeMinutes();

        $this->assertSame([60, 120, 180, 240, 360, 720, 1440], $actual);
    }

    public function test_ganttChartDisplayRangeOptionsは表示文言付き配列を返す(): void
    {
        $actual = Utility::ganttChartDisplayRangeOptions();

        $this->assertArrayHasKey(60, $actual);
        $this->assertArrayHasKey(1440, $actual);
    }

    public function test_pinNumberOptionsは0から127までの配列を返す(): void
    {
        $actual = Utility::pinNumberOptions();

        $this->assertCount(128, $actual);
        $this->assertSame('pinNumber 0', $actual[0]);
        $this->assertSame('pinNumber 127', $actual[127]);
    }

    public function test_ensureOperationSucceededは成功時に例外を投げない(): void
    {
        $process = Process::factory()->create();

        Utility::ensureOperationSucceeded($process, true);

        $this->assertTrue(true);
    }
}
