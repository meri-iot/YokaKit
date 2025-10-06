<?php

namespace App\Models;

use App\Services\Utility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * ラインモデルクラス
 *
 * @property integer $line_id 主キー
 * @property integer $process_id 工程ID(外部キー)
 * @property integer $raspberry_pi_id ラズベリーパイID(外部キー)
 * @property integer|null $worker_id 作業者ID(外部キー)
 * @property integer|null $parent_id 親となる工程ID(外部キー)
 * @property string $line_name ライン名
 * @property string $chart_color チャート色
 * @property integer $pin_number ピン番号
 * @property boolean $defective 不良品フラグ
 * @property integer $order 順序
 * @property Collection<int,Line> $defectiveLines 不良品ライン
 * @property RaspberryPi $raspberryPi ラズパイ
 */
class Line extends Pivot
{
    use HasFactory;

    /**
     * モデルに関連付けられたテーブル名
     *
     * @var string
     */
    protected $table = 'lines';

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
    protected $primaryKey = 'line_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'process_id',       // 工程ID
        'raspberry_pi_id',  // ラズベリーパイID
        'worker_id',        // 作業者ID
        'parent_id',        // 親となる工程ID
        'line_name',        // ライン名
        'chart_color',      // チャート色
        'pin_number',       // ピン番号
        'defective',        // 不良品フラグ
        'order',            // 順序
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
     * キャストする属性
     *
     * @var array<string,string>
     */
    protected $casts = [
        'defective' => 'boolean',   // 不良品フラグをbooleanにキャスト
    ];

    /**
     * ラインに関連する作業者を取得する
     *
     * @return BelongsTo
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'worker_id');
    }

    /**
     * 不良品ライン(defective==true)に関連する加工数量ライン
     *
     * @return BelongsTo
     */
    public function parentLine(): BelongsTo
    {
        return $this->belongsTo(Line::class, 'parent_id', $this->primaryKey);
    }

    /**
     * 加工数量ライン(defective==false)に関連する不良品ライン
     *
     * @return HasMany
     */
    public function defectiveLines(): HasMany
    {
        return $this->hasMany(Line::class, 'parent_id', $this->primaryKey);
    }

    /**
     * ラインに関連する工程を取得する
     *
     * @return BelongsTo
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    /**
     * ラインに関連するラズパイを取得する
     *
     * @return BelongsTo
     */
    public function raspberryPi(): BelongsTo
    {
        return $this->belongsTo(RaspberryPi::class, 'raspberry_pi_id');
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
