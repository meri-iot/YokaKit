<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Process;
use App\Models\ProcessPlannedOutage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * 計画停止時間モデルクラス
 *
 * @property integer $planned_outage_id 主キー
 * @property string $planned_outage_name 計画停止時間名
 * @property Carbon $start_time 開始時間
 * @property Carbon $end_time 終了時間
 */
class PlannedOutage extends Model
{
    use HasFactory;

    /**
     * モデルの主キー名
     *
     * @var string
     */
    protected $primaryKey = 'planned_outage_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'planned_outage_name',  // 計画停止時間名
        'start_time',           // 開始時間
        'end_time',             // 終了時間
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

    /**
     * 計画停止時間と多対多で関連する工程を取得する
     *
     * @return BelongsToMany
     */
    public function processes(): BelongsToMany
    {
        return $this->belongsToMany(Process::class, 'process_planned_outages', 'planned_outage_id', 'process_id')
            ->using(ProcessPlannedOutage::class)
            ->withPivot('process_planned_outage_id');
    }

    /**
     * 休憩開始時間のフォーマット済み文字列を取得する
     *
     * @return string フォーマット済み文字列
     */
    public function formatStartTime(): string
    {
        return $this->start_time->format('H:i');
    }

    /**
     * 休憩終了時間のフォーマット済み文字列を取得する
     *
     * @return string フォーマット済み文字列
     */
    public function formatEndTime(): string
    {
        return $this->end_time->format('H:i');
    }
}
