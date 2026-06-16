<?php

namespace Tests\Unit\Data;

use App\Data\FromTo;
use App\Services\Utility;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FromToTest extends TestCase
{
    public function test_toがある場合spanは開始と終了のミリ秒差を返す()
    {
        $from = Carbon::create(2026, 4, 8, 9, 0, 0);
        $to = Carbon::create(2026, 4, 8, 9, 0, 2)->addMilliseconds(250);
        $fromTo = new FromTo($from, $to);

        $this->assertSame(2250, $fromTo->span());
    }

    public function test_toがない場合spanは補完日時未指定なら0を返す()
    {
        $from = Carbon::create(2026, 4, 8, 9, 0, 0);
        $fromTo = new FromTo($from, null);

        $this->assertSame(0, $fromTo->span());
    }

    public function test_toがない場合spanは補完日時指定でミリ秒差を返す()
    {
        $from = Carbon::create(2026, 4, 8, 9, 0, 0);
        $date = Carbon::create(2026, 4, 8, 9, 0, 1)->addMilliseconds(500);
        $fromTo = new FromTo($from, null);

        $this->assertSame(1500, $fromTo->span($date));
    }

    public function test_toArrayはfromとtoをフォーマット済み文字列で返す()
    {
        $from = Carbon::create(2026, 4, 8, 9, 0, 0)->microsecond(123456);
        $to = Carbon::create(2026, 4, 8, 10, 0, 0)->microsecond(654321);
        $fromTo = new FromTo($from, $to);

        $this->assertSame([
            'from' => Utility::format($from),
            'to' => Utility::format($to),
        ], $fromTo->toArray());
    }

    public function test_toArrayはtoがnullならnullを返す()
    {
        $from = Carbon::create(2026, 4, 8, 9, 0, 0)->microsecond(123456);
        $fromTo = new FromTo($from, null);

        $this->assertSame([
            'from' => Utility::format($from),
            'to' => null,
        ], $fromTo->toArray());
    }

    public function test_toJsonはtoArrayの内容をjson文字列化する()
    {
        $from = Carbon::create(2026, 4, 8, 9, 0, 0)->microsecond(123456);
        $to = Carbon::create(2026, 4, 8, 9, 30, 0)->microsecond(0);
        $fromTo = new FromTo($from, $to);

        $this->assertSame(
            json_encode($fromTo->toArray(), JSON_THROW_ON_ERROR),
            $fromTo->toJson()
        );
    }
}
