<?php

namespace App\Services;

use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

/**
 * 汎用処理をまとめたクラス
 */
class Utility
{
    /**
     * 現在時刻を取得する
     *
     * @return Carbon
     */
    public static function now(): Carbon
    {
        $now = Carbon::now(new DateTimeZone(config('app.timezone')));
        $millisecond = $now->millisecond;
        return $now->microseconds(0)->milliseconds($millisecond);
    }

    /**
     * 時刻を文字列に変換する
     *
     * @param Carbon $date 時刻
     * @param string $format フォーマット
     * @return string
     */
    public static function format(Carbon $date, string $format = 'Y-m-d H:i:s.u'): string
    {
        return $date->format($format);
    }

    /**
     * 年月日と時分秒のデータを結合する
     *
     * @param Carbon $ymd 年月日
     * @param Carbon $hms 時分秒
     * @return Carbon
     */
    public static function merge(Carbon $ymd, Carbon $hms): Carbon
    {
        return $ymd->copy()
            ->hours($hms->hour)
            ->minutes($hms->minute)
            ->seconds($hms->second);
    }

    /**
     * 指定した文字列を時刻へ変換する
     *
     * @param string|null $datetime 時刻文字列
     * @param string $format フォーマット
     * @return Carbon
     */
    public static function parse(string|null $datetime, string $format = 'Y-m-d H:i:s.u'): Carbon
    {
        return Carbon::createFromFormat($format, $datetime, new DateTimeZone(config('app.timezone')));
    }

    /**
     * ピン番号を文字列化
     *
     * @param int $pinNumber ピン番号
     * @return string
     */
    public static function padPinNumber(int $pinNumber): string
    {
        return "pinNumber $pinNumber";
    }

    /**
     * ピン番号選択用のオプションを取得する
     *
     * @return array<int,string> ピン番号選択用のオプション
     */
    public static function pinNumberOptions(): array
    {
        return array_reduce(range(0, 127), function (array $carry, int $item) {
            $carry[$item] = self::padPinNumber($item);
            return $carry;
        }, []);
    }

    /**
     * 表示範囲選択用のオプションを取得する
     *
     * @return array<int,string> ピン番号選択用のオプション
     */
    public static function rangeOptions(): array
    {
        return array_reduce(self::ranges(), function (array $carry, int $item) {
            $carry[$item] = __('yokakit.target_hour', ['target' => $item / 60]);
            return $carry;
        }, []);
    }

    /**
     * ガントチャートの表示範囲の時間[分]
     *
     * @return array
     */
    public static function ranges(): array
    {
        return [60, 120, 180, 240, 360, 720, 1440];
    }

    /**
     * 指定したモデルがnullの場合に例外を投げる。
     *
     * @param Model|null $model モデル
     * @return void
     * @throws ModelNotFoundException
     */
    public static function throwIfNullException(?Model $model = null)
    {
        is_null($model) && throw new ModelNotFoundException();
    }

    /**
     * 指定した結果がfalseの場合に例外を投げる。
     *
     * @param Model $model モデル
     * @param boolean $result falseの場合に例外を投げる
     * @return void
     * @throws ModelNotFoundException
     */
    public static function throwIfException(Model $model, bool $result)
    {
        $result || throw (new ModelNotFoundException())->setModel(get_class($model));
    }

    /**
     * 指定した文字列をファイル名に使用できる文字列に変換する
     *
     * @param string $filename ファイル名
     * @return string ファイル名に使用できる文字列
     */
    public static function sanitizeFileName(string $filename): string
    {
        // ファイル名に使用できない文字を正規表現で除去
        $sanitized = preg_replace('/[\\\\\\/:\*\?"<>\|\r\n\t]/', '', $filename);

        // トリムして、空白やドットで始まったり終わったりしないようにする
        return trim($sanitized, " .\t\n\r\0\x0B");
    }
}
