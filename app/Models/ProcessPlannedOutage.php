<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * 工程と計画停止時間の関連モデルクラス
 *
 * @property integer $process_planned_outage_id 主キー
 * @property integer $process_id 工程ID(外部キー)
 * @property integer $planned_outage_id 計画停止時間ID(外部キー)
 */
class ProcessPlannedOutage extends Pivot
{
    use HasFactory;

    /**
     * モデルに関連付けられたテーブル名
     *
     * @var string
     */
    protected $table = 'process_planned_outages';

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
    public $primaryKey = 'process_planned_outage_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'process_id',           // 工程ID
        'planned_outage_id',    // 計画停止時間ID
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
}
