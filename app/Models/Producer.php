<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 生産者モデルクラス
 *
 * @property integer $producer_id 主キー
 * @property integer|null $worker_id 作業者ID(外部キー)
 * @property integer $production_line_id 生産ラインID(外部キー)
 * @property string $identification_number 識別番号
 * @property string $worker_name 作業者名
 * @property Carbon $start 開始時間
 * @property Carbon|null $stop 終了時間
 */
class Producer extends Model
{
    use HasFactory;

    /**
     * モデルの主キー名
     *
     * @var string
     */
    protected $primaryKey = 'producer_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'worker_id',                // 作業者ID
        'production_line_id',       // 生産ラインID
        'identification_number',    // 識別番号
        'worker_name',              // 作業者名
        'start',                    // 開始時間
        'stop',                     // 終了時間
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
        'start' => 'datetime',
        'stop' => 'datetime',
    ];
}
