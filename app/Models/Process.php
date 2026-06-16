<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductionStatus;
use App\Models\PlannedOutage;
use App\Models\ProcessPlannedOutage;
use App\Services\Utility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 工程モデルクラス
 *
 * @property integer $process_id 主キー
 * @property integer|null $production_history_id 生産履歴ID
 * @property string $process_name 工程名
 * @property string $plan_color 計画値色
 * @property bool $count_switch カウント切替
 * @property string|null $remark 備考
 * @property integer $range ガントチャート表示範囲[分]
 * @property Collection<int,RaspberryPi> $raspberryPis ラズパイ
 * @property Collection<int,PlannedOutage> $plannedOutages 計画停止時間
 * @property ProductionHistory|null $productionHistory 生産履歴
 */
class Process extends Model
{
    use HasFactory;

    /**
     * モデルの主キー名
     *
     * @var string
     */
    protected $primaryKey = 'process_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'production_history_id',    // 生産履歴ID
        'process_name',             // 工程名
        'plan_color',               // 計画値色
        'count_switch',             // カウント切替
        'remark',                   // 備考
        'range',                    // ガントチャート表示範囲[分]
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
        'count_switch' => 'boolean',   // カウント切替をbooleanにキャスト
    ];

    /**
     * 工程と多対多で関連する計画停止時間を取得
     *
     * @return BelongsToMany
     */
    public function plannedOutages(): BelongsToMany
    {
        return $this->belongsToMany(PlannedOutage::class, 'process_planned_outages', 'process_id', 'planned_outage_id')
            ->using(ProcessPlannedOutage::class)
            ->withPivot('process_planned_outage_id');
    }

    /**
     * 工程と多対多で関連する品番を取得
     *
     * @return BelongsToMany
     */
    public function partNumbers(): BelongsToMany
    {
        return $this->belongsToMany(PartNumber::class, 'cycle_times', 'process_id', 'part_number_id')
            ->using(CycleTime::class)
            ->withPivot(['cycle_time_id', 'cycle_time', 'over_time']);
    }

    /**
     * 工程と多対多で関連するラズパイを取得
     *
     * @return BelongsToMany
     */
    public function raspberryPis(): BelongsToMany
    {
        return $this->belongsToMany(RaspberryPi::class, 'lines', 'process_id', 'raspberry_pi_id')
            ->using(Line::class)
            ->withPivot(['line_id', 'line_name', 'chart_color', 'pin_number', 'defective', 'worker_id', 'parent_id', 'order'])
            ->orderBy('order', 'asc')
            ->orderBy('line_id', 'asc');
    }

    /**
     * 工程と1対多で関連するラインを取得
     *
     * @return HasMany
     */
    public function lines(): HasMany
    {
        return $this->hasMany(Line::class, 'process_id');
    }

    /**
     * 工程と1対多で関連するセンサーを取得
     *
     * @return HasMany
     */
    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class, 'process_id');
    }

    /**
     * 工程と1対多で関連するガントチャートを取得
     *
     * @return HasMany
     */
    public function ganttCharts(): HasMany
    {
        return $this->hasMany(GanttChart::class, 'process_id')
            ->orderBy('order', 'asc')
            ->orderBy('gantt_chart_id', 'asc');
    }

    /**
     * 工程と1対多で関連するセンサーイベントを取得
     *
     * @return HasMany
     */
    public function sensorEvents(): HasMany
    {
        return $this->hasMany(SensorEvent::class, 'process_id')
            ->whereIn('sensor_event_id', function ($query) {
                // センサーごとの最新イベントのみを抽出して、一覧の重複を防ぐ
                $query->select(DB::raw('MAX(sensor_event_id)'))
                    ->from('sensor_events')
                    ->where('at', '>=', today()->addDays(-7))
                    ->groupBy('process_id', 'sensor_id');
            })
            ->whereColumn('trigger', 'signal')
            ->orderBy('at', 'asc');
    }

    /**
     * 工程と1対多で関連するON-OFFメッセージを取得
     *
     * @return HasMany
     */
    public function onOffs(): HasMany
    {
        return $this->hasMany(OnOff::class, 'process_id');
    }

    /**
     * 工程と1対多で関連するON-OFFメッセージイベントを取得
     *
     * @return HasMany
     */
    public function onOffEvents(): HasMany
    {
        return $this->hasMany(OnOffEvent::class, 'process_id')
            ->where('at', '>=', Utility::now()->addDays(-1))
            ->orderBy('at', 'desc')
            ->limit(10);
    }

    /**
     * 工程と1対1で関連する現在稼働中の生産履歴を取得
     *
     * @return BelongsTo
     */
    public function productionHistory(): BelongsTo
    {
        return $this->belongsTo(ProductionHistory::class, 'production_history_id');
    }

    /**
     * 工程と1対1で関連するアンドンレイアウト設定を取得
     *
     * @return HasOne
     */
    public function andonLayout(): HasOne
    {
        $userId = Auth::id();
        $processId = $this->process_id;
        return $this->hasOne(AndonLayout::class, 'process_id')
            ->withDefault(function (AndonLayout $model) use ($userId, $processId) {
                $model->process_id = $processId;
                $model->user_id = $userId;
                $model->order = PHP_INT_MAX;
                $model->is_display = true;
            })
            ->where('user_id', $userId);
    }

    /**
     * 生産が稼働しているかどうか
     *
     * @return bool trueであれば停止
     */
    public function isRunning(): bool
    {
        return !$this->isStopped();
    }

    /**
     * 生産が停止しているかどうか
     *
     * @return bool trueであれば停止
     */
    public function isStopped(): bool
    {
        return $this->status()->is(ProductionStatus::COMPLETE());
    }

    /**
     * 段取替え中かどうか
     *
     * @return bool trueであれば段取替え
     */
    public function isChangeover(): bool
    {
        return $this->status()->is(ProductionStatus::CHANGEOVER());
    }

    /**
     * 工程の生産ステータスを取得する
     *
     * @return ProductionStatus 生産ステータス
     */
    public function status(): ProductionStatus
    {
        return $this->productionHistory?->status ?? ProductionStatus::COMPLETE();
    }

    /**
     * 工程情報を取得する
     *
     * @return array<string,mixed>
     */
    public function info(): array
    {
        return $this->mapProcess();
    }

    /**
     * 工程データをマップし、不要なフィールドを除去します。
     *
     * @return array<string,mixed>
     */
    private function mapProcess(): array
    {
        $info = $this->toArray();
        $info['status'] = $this->status()->key;
        $info['production_history'] = $this->mapProductionHistory($this->productionHistory);
        unset($info['plan_color']);
        unset($info['production_history_id']);
        unset($info['remark']);
        return $info;
    }

    /**
     * 製造履歴のデータをマップし、不要なフィールドを除去します。
     *
     * @param ProductionHistory|null $productionHistory
     * @return array<string,mixed>|null
     */
    private function mapProductionHistory(?ProductionHistory $productionHistory): array|null
    {
        if (is_null($productionHistory)) {
            return null;
        }
        $ph = $productionHistory->toArray();
        unset($ph['production_history_id']);
        unset($ph['status']);
        unset($ph['status_name']);
        unset($ph['plan_color']);
        unset($ph['process_id']);
        unset($ph['process_name']);
        return $ph;
    }
}
