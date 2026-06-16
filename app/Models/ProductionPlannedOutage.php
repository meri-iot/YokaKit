<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 生産時の計画停止時間モデルクラス
 *
 * @property integer $production_planned_outage_id 主キー
 * @property integer $production_history_id 生産履歴ID(外部キー)
 * @property string $planned_outage_name 計画停止時間名
 * @property Carbon $start_time 開始時間
 * @property Carbon $end_time 終了時間
 */
class ProductionPlannedOutage extends Model
{
    use HasFactory;

    /**
     * モデルの主キー名
     *
     * @var string
     */
    protected $primaryKey = 'production_planned_outage_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'production_history_id',    // 生産履歴ID
        'planned_outage_name',      // 計画停止時間名
        'start_time',               // 開始時間
        'end_time',                 // 終了時間
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
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];
}
