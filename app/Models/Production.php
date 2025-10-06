<?php

namespace App\Models;

use App\Enums\ProductionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 生産データモデルクラス
 *
 * @property integer $production_id 主キー
 * @property integer $production_line_id 生産ラインID(外部キー)
 * @property Carbon $at 生産時刻
 * @property integer $count 生産数
 * @property integer $defective_count 不良品数
 * @property ProductionStatus $status 生産ステータス
 * @property boolean $in_planned_outage 計画停止
 * @property integer $working_time 操業時間
 * @property integer $loading_time 負荷時間
 * @property integer $operating_time 稼働時間
 * @property integer $net_time 正味稼働時間
 * @property integer $breakdown_count チョコ停回数
 * @property integer $auto_resume_count 段取り替え自動復帰回数
 * @property string $status_name ステータス名
 */
class Production extends Model
{
    use HasFactory;

    /**
     * モデルの主キー名
     *
     * @var string
     */
    protected $primaryKey = 'production_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'production_line_id',   // 生産ラインID
        'at',                   // 生産時刻
        'count',                // 生産数
        'defective_count',      // 不良品数
        'status',               // 生産ステータス
        'in_planned_outage',    // 計画停止
        'working_time',         // 操業時間
        'loading_time',         // 負荷時間
        'operating_time',       // 稼働時間
        'net_time',             // 正味稼働時間
        'breakdown_count',      // チョコ停回数
        'auto_resume_count',    // 段取り替え自動復帰回数
    ];

    /**
     * キャストする属性
     *
     * @var array<string,string>
     */
    protected $casts = [
        'at' => 'datetime:Y-m-d H:i:s.u',
        'status' => ProductionStatus::class,
        'in_planned_outage' => 'boolean',
    ];

    /**
     * シリアライズ時に隠す属性
     *
     * @var array<int,string>
     */
    protected $hidden = [
        'production_id',
    ];

    /**
     * モデルに追加するアクセサ
     *
     * @var array<int,string>
     */
    protected $appends = ['status_name'];

    /**
     * モデルにタイムスタンプを付けるかどうか
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * ステータス名を取得する
     *
     * @return string
     */
    public function getStatusNameAttribute(): string
    {
        return $this->status->key;
    }
}
