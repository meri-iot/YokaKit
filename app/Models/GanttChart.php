<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GanttChartType;
use App\Services\Utility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ガントチャートモデルクラス
 *
 * @property integer $gantt_chart_id 主キー
 * @property integer $process_id 工程ID(外部キー)
 * @property integer $raspberry_pi_id ラズベリーパイID(外部キー)
 * @property integer $pin_number ピン番号
 * @property string $chart_name チャート名
 * @property string $chart_color チャート色
 * @property boolean $trigger トリガー
 * @property boolean|null $signal 信号
 * @property GanttChartType $chart_type 生産ステータス
 * @property int $base_span 基準チャートの合計時間[分]
 * @property int $work_span 稼働チャートの合計時間[分]
 * @property int $overlap_span 重複時間[分]
 */
class GanttChart extends Model
{
    use HasFactory;

    /**
     * モデルに関連付けられたテーブル名
     *
     * @var string
     */
    protected $table = 'gantt_charts';

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
    protected $primaryKey = 'gantt_chart_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'process_id',           // 工程ID
        'raspberry_pi_id',      // ラズパイID
        'pin_number',           // ピン番号
        'trigger',              // トリガー
        'chart_name',           // 名前
        'chart_color',          // チャート色
        'signal',               // 信号
        'chart_type',           // 基準信号
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
        'trigger' => 'boolean',
        'signal' => 'boolean',
        'chart_type' => GanttChartType::class,
    ];

    /**
     * ガントチャートと関連するラズベリーパイを取得する
     *
     * @return BelongsTo
     */
    public function raspberryPi(): BelongsTo
    {
        return $this->belongsTo(RaspberryPi::class, 'raspberry_pi_id');
    }

    /**
     * ガントチャートと関連する工程を取得する
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

    /**
     * ガントチャートと1対多で関連するガントチャートイベントを取得
     *
     * @return HasMany
     */
    public function ganttChartEvents(): HasMany
    {
        return $this->hasMany(GanttChartEvent::class, 'gantt_chart_id');
    }
}
