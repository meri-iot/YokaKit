<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * ON-OFFメッセージイベントモデルクラス
 *
 * @property integer $on_off_event_id 主キー
 * @property integer $process_id 工程ID(外部キー)
 * @property integer $on_off_id ON/OFF ID(外部キー)
 * @property string $event_name イベント名
 * @property string|null $message メッセージ
 * @property boolean $on_off ON/OFFの状態
 * @property integer $pin_number ピン番号
 * @property Carbon $at イベント発生日時
 */
class OnOffEvent extends Model
{
    use HasFactory;

    /**
     * モデルの主キー名
     *
     * @var string
     */
    protected $primaryKey = 'on_off_event_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'process_id',   // 工程ID
        'on_off_id',    // ON/OFF ID
        'event_name',   // イベント名
        'message',      // メッセージ
        'on_off',       // ON/OFFの状態
        'pin_number',   // ピン番号
        'at',           // イベント発生日時
    ];

    /**
     * シリアライズ時に隠す属性
     *
     * @var array<int,string>
     */
    protected $hidden = [
        'on_off_event_id',  // ON/OFFイベントIDを隠す
    ];

    /**
     * キャストする属性
     *
     * @var array<string,string>
     */
    protected $casts = [
        'on_off' => 'boolean',              // ON/OFFの状態をbooleanにキャスト
        'at' => 'datetime:Y-m-d H:i:s.u',   // イベント発生日時を指定フォーマットでキャスト
    ];

    /**
     * モデルにタイムスタンプを付けるかどうか
     *
     * @var bool
     */
    public $timestamps = false;
}
