<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Utility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * ON-OFFメッセージモデルクラス
 *
 * @property integer $on_off_id 主キー
 * @property integer $process_id 工程ID(外部キー)
 * @property integer $raspberry_pi_id ラズベリーパイID(外部キー)
 * @property string $event_name イベント名
 * @property string $on_message ON時のメッセージ
 * @property string|null $off_message OFF時のメッセージ
 * @property integer $pin_number ピン番号
 */
class OnOff extends Pivot
{
    use HasFactory;

    /**
     * モデルに関連付けられたテーブル名
     *
     * @var string
     */
    protected $table = 'on_offs';

    /**
     * モデルの主キーが自動インクリメントされるかどうかを示すフラグ
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * モデルの主キー名
     *
     * @var string
     */
    protected $primaryKey = 'on_off_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'process_id',       // 工程ID
        'raspberry_pi_id',  // ラズベリーパイID
        'event_name',       // イベント名
        'on_message',       // ON時のメッセージ
        'off_message',      // OFF時のメッセージ
        'pin_number',       // ピン番号
    ];

    /**
     * シリアライズ時に隠す属性
     *
     * @var array<int,string>
     */
    protected $hidden = [
        'created_at',   // 作成時刻を隠す
        'updated_at',   // 更新時刻を隠す
    ];

    /**
     * ON-OFFメッセージに関連するラズベリーパイ
     *
     * @return BelongsTo
     */
    public function raspberryPi(): BelongsTo
    {
        return $this->belongsTo(RaspberryPi::class, 'raspberry_pi_id');
    }

    /**
     * ON-OFFメッセージに関連する工程を取得する
     *
     * @return BelongsTo
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    /**
     * ピン番号文字列
     *
     * @return string
     */
    public function pinNumber(): string
    {
        return Utility::padPinNumber($this->pin_number);
    }
}
