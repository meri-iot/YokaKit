<?php

namespace App\Data;

use App\Services\Utility;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * 時間区間データオブジェクト
 */
class FromTo extends Data
{
    /**
     * コンストラクタ
     *
     * @param Carbon $from 開始時間
     * @param Carbon|null $to 終了時間
     */
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon|null $to = null,
    ) {}

    /**
     * 時間区間のスパンのミリ秒を取得する
     *
     * @param Carbon|null $date toがnullである場合に埋める日付
     * @return integer 区間のミリ秒
     */
    public function span(Carbon|null $date = null): int
    {
        if (is_null($this->to)) {
            if (is_null($date)) {
                return 0;
            } else {
                return $this->from->diffInMilliseconds($date);
            }
        } else {
            return $this->from->diffInMilliseconds($this->to);
        }
    }

    /**
     * JSONに変換する
     *
     * @param integer $options json_encodeオプション
     * @return string JSON文字列
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * 連想配列に変換する
     *
     * @return array<string, string|null> 連想配列
     */
    public function toArray(): array
    {
        return [
            'from' => Utility::format($this->from),
            'to' => is_null($this->to) ? null : Utility::format($this->to),
        ];
    }
}
