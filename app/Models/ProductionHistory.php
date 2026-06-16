<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\FromTo;
use App\Data\PayloadData;
use App\Enums\ProductionStatus;
use App\Services\Utility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * 生産履歴モデルクラス
 *
 * @property integer $production_history_id 主キー
 * @property integer|null $process_id 工程ID(外部キー)
 * @property integer|null $part_number_id 品番ID(外部キー)
 * @property string $process_name 工程名
 * @property string $part_number_name 品番名
 * @property string $plan_color 計画値色
 * @property bool $count_switch カウント切替
 * @property float $cycle_time サイクルタイム
 * @property float $over_time オーバータイム
 * @property integer|null $goal 目標値
 * @property Carbon $start 生産開始時刻
 * @property Carbon|null $stop 生産終了時刻
 * @property ProductionStatus $status ステータス
 * @property string $status_name ステータス名
 * @property Collection<int,ProductionLine> $productionLines 生産ライン
 * @property ProductionLine|null $indicatorLine 指標となるライン
 */
class ProductionHistory extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * モデルの主キー名
     *
     * @var string
     */
    protected $primaryKey = 'production_history_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'process_id',       // 工程ID
        'part_number_id',   // 品番ID
        'process_name',     // 工程名
        'part_number_name', // 品番名
        'plan_color',       // 計画値色
        'count_switch',     // カウント切替
        'cycle_time',       // サイクルタイム
        'over_time',        // オーバータイム
        'goal',             // 目標値
        'start',            // 生産開始時刻
        'stop',             // 生産終了時刻
        'status',           // ステータス
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
        'count_switch' => 'boolean',
        'start' => 'datetime',
        'stop' => 'datetime',
        'status' => ProductionStatus::class,
    ];

    /**
     * モデルに追加するアクセサ
     *
     * @var array<int,string>
     */
    protected $appends = ['status_name'];

    /**
     * 生産履歴と1対多で関連する生産ラインを取得する
     *
     * @return HasMany
     */
    public function productionLines(): HasMany
    {
        return $this->hasMany(ProductionLine::class, $this->primaryKey)
            ->where('defective', '=', false);
    }

    /**
     * 生産履歴と1対1で関連する指標となる生産ラインを取得する
     *
     * @return HasOne
     */
    public function indicatorLine(): HasOne
    {
        return $this->hasOne(ProductionLine::class, $this->primaryKey)
            ->where('indicator', '=', true);
    }

    /**
     * 生産履歴と1対多で関連する計画停止時間を取得する
     *
     * @return HasMany
     */
    public function productionPlannedOutages(): HasMany
    {
        return $this->hasMany(ProductionPlannedOutage::class, $this->primaryKey);
    }

    /**
     * 生産履歴が属する工程を取得する
     *
     * @return BelongsTo
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    /**
     * ステータス名を取得する
     *
     * @return string
     */
    public function getStatusNameAttribute(): string
    {
        return $this->status->key;
    }

    /**
     * 生産が完了したかどうか
     *
     * @return bool trueであれば完了
     */
    public function isComplete(): bool
    {
        return $this->status->is(ProductionStatus::COMPLETE());
    }

    /**
     * 生産期間を文字列で取得する
     *
     * @return string
     */
    public function period(): string
    {
        $stop = $this->stop ?? Utility::now();
        $totalSeconds = $this->start->diffInSeconds($stop);
        $s = str_pad((string)($totalSeconds % 60), 2, '0', STR_PAD_LEFT);
        $i = str_pad((string)((int)($totalSeconds / 60) % 60), 2, '0', STR_PAD_LEFT);
        $h = (int)($totalSeconds / 3600);
        return "{$h}:{$i}:{$s}";
    }

    /**
     * 最終生産数を取得する
     *
     * @return int 最終生産数
     */
    public function lastProductCount(): int
    {
        return $this->indicatorLine?->count ?? 0;
    }

    /**
     * サイクルタイム[ms]を取得する
     *
     * @return int サイクルタイム
     */
    public function cycleTimeMs(): int
    {
        return (int)($this->cycle_time * 1000);
    }

    /**
     * オーバータイム[ms]を取得する
     *
     * @return int オーバータイム
     */
    public function overTimeMs(): int
    {
        return (int)($this->over_time * 1000);
    }

    /**
     * 指標のサマリーを取得する
     *
     * @return array<string,mixed>|null サマリー
     */
    public function summary(): ?array
    {
        return $this->indicatorLine?->summary();
    }

    /**
     * 現在計画停止時間中かどうかを取得する
     *
     * @return bool trueなら計画停止時間中
     */
    public function inPlannedOutage(): bool
    {
        return $this->payloadData()?->inPlannedOutage(Utility::now()) ?? false;
    }

    /**
     * チョコ停回数を取得する
     *
     * @return int チョコ停回数
     */
    public function breakDownCount(): int
    {
        return $this->payloadData()?->breakDownCount() ?? 0;
    }

    /**
     * チョコ停区間を取得する
     *
     * @return SupportCollection<FromTo> チョコ停区間
     */
    public function breakDownSections(): SupportCollection
    {
        return $this->payloadData()?->breakDownSections() ?? collect();
    }

    /**
     * 生産ステータスを取得する
     *
     * @return ProductionStatus 生産ステータス
     */
    public function status(): ProductionStatus
    {
        return $this->payloadData()?->status() ?? ProductionStatus::COMPLETE();
    }

    /**
     * 指標計算のデータを取得する
     *
     * @return PayloadData|null 指標計算のデータ
     */
    private function payloadData(): ?PayloadData
    {
        return $this->indicatorLine?->payload?->getPayloadData();
    }
}
