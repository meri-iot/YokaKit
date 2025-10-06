<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * サイクルタイムモデルクラス
 *
 * @property integer $cycle_time_id 主キー
 * @property integer $process_id 工程ID(外部キー)
 * @property integer $part_number_id 品番ID(外部キー)
 * @property integer $cycle_time_name サイクルタイム名
 * @property float $cycle_time サイクルタイム[秒]
 * @property float $over_time オーバータイム[秒]
 * @property PartNumber $partNumber 品番
 */
class CycleTime extends Pivot
{
    use HasFactory;

    /**
     * モデルに関連付けられたテーブル名
     *
     * @var string
     */
    protected $table = 'cycle_times';

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
    protected $primaryKey = 'cycle_time_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'process_id',       // 工程ID
        'part_number_id',   // 品番ID
        'cycle_time_name',  // サイクルタイム名
        'cycle_time',       // サイクルタイム[秒]
        'over_time',        // オーバータイム[秒]
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
     * サイクルタイムに関連する品番
     *
     * @return BelongsTo
     */
    public function partNumber(): BelongsTo
    {
        return $this->belongsTo(PartNumber::class, 'part_number_id');
    }

    /**
     * サイクルタイムに関連する工程
     *
     * @return BelongsTo
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }
}
