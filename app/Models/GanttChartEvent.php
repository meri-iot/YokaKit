<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ガントチャートイベントモデルクラス
 *
 * @property integer $gantt_chart_event_id 主キー
 * @property integer $process_id 工程ID(外部キー)
 * @property integer $gantt_chart_id ガントチャートID(外部キー)
 * @property boolean $signal 信号
 * @property Carbon $at 時刻
 */
class GanttChartEvent extends Model
{
    use HasFactory;

    /**
     * モデルの主キー名
     *
     * @var string
     */
    protected $primaryKey = 'gantt_chart_event_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'process_id',       // 工程ID
        'gantt_chart_id',   // ガントチャートID
        'signal',           // 信号
        'at',               // 時刻
    ];

    /**
     * シリアライズ時に隠す属性
     *
     * @var array<int,string>
     */
    protected $hidden = [
        'gantt_chart_event_id',
    ];

    /**
     * キャストする属性
     *
     * @var array<string,string>
     */
    protected $casts = [
        'signal' => 'boolean',
        'at' => 'datetime:Y-m-d H:i:s.u',
    ];

    /**
     * モデルにタイムスタンプを付けるかどうか
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * ガントチャートイベントと関連する工程を取得する
     *
     * @return BelongsTo
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }
}
