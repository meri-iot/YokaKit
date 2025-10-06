<?php

namespace App\Models;

use App\Data\PayloadData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 生産ラインモデルクラス
 *
 * @property integer $production_line_id 主キー
 * @property integer $production_history_id 生産履歴ID(外部キー)
 * @property integer|null $line_id ラインID(外部キー)
 * @property integer|null $parent_id 関連のあるラインID(外部キー)
 * @property string $line_name ライン名
 * @property string $chart_color チャート色
 * @property string $ip_address IPアドレス
 * @property integer $pin_number ピン番号
 * @property boolean $defective 不良品フラグ
 * @property integer $order 順序
 * @property boolean $indicator 指標フラグ
 * @property integer|null $offset_count オフセットカウント
 * @property integer $count カウント
 * @property ProductionHistory $productionHistory 生産履歴
 * @property Payload $payload ペイロード
 * @property Collection<int,Production> $productions
 */
class ProductionLine extends Model
{
    use HasFactory;

    /**
     * モデルの主キー名
     *
     * @var string
     */
    protected $primaryKey = 'production_line_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'production_history_id',    // 生産履歴ID
        'line_id',                  // ラインID
        'parent_id',                // 関連のあるラインID
        'line_name',                // ライン名
        'chart_color',              // チャート色
        'ip_address',               // IPアドレス
        'pin_number',               // ピン番号
        'defective',                // 不良品フラグ
        'order',                    // 順序
        'indicator',                // 指標フラグ
        'offset_count',             // オフセットカウント
        'count',                    // カウント
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
        'defective' => 'boolean',
        'indicator' => 'boolean',
    ];

    /**
     * 生産ラインに関連する生産履歴を取得する
     *
     * @return BelongsTo
     */
    public function productionHistory(): BelongsTo
    {
        return $this->belongsTo(ProductionHistory::class, 'production_history_id');
    }

    /**
     * 生産ラインに1対多で関連する生産データを取得する
     *
     * @return HasMany
     */
    public function productions(): HasMany
    {
        return $this->hasMany(Production::class, $this->primaryKey)
            ->orderBy('at', 'asc')
            ->orderBy('production_id', 'asc');
    }

    /**
     * 生産ラインに1対多で関連する不良品ラインを取得する
     *
     * @return HasMany
     */
    public function defectiveProductions(): HasMany
    {
        return $this->hasMany(DefectiveProduction::class, $this->primaryKey)
            ->orderBy('at', 'asc');
    }

    /**
     * 生産ラインに1対多で関連する生産者を取得する
     *
     * @return HasMany
     */
    public function producers(): HasMany
    {
        return $this->hasMany(Producer::class, $this->primaryKey)
            ->whereNull('stop');
    }

    /**
     * 生産ラインと1対1で関連するペイロードを取得する
     *
     * @return HasOne
     */
    public function payload(): HasOne
    {
        return $this->hasOne(Payload::class, $this->primaryKey);
    }

    /**
     * 不良品ラインの親となるラインとの関連
     *
     * @return BelongsTo
     */
    public function parentLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class, 'parent_id', $this->primaryKey);
    }

    /**
     * サマリー
     *
     * @return array<string,mixed>|null
     */
    public function summary(): ?array
    {
        /** @var ?Payload */
        $payload = $this->payload;
        if (is_null($payload)) {
            return null;
        }
        /** @var PayloadData */
        $payloadData = $payload->getPayloadData();
        return [
            'goodCount' => $payloadData->goodCount(),
            'goodRate' => round($payloadData->goodRate() * 100.0) . ' [%]',
            'defectiveCount' => $payloadData->defectiveCount(),
            'defectiveRate' => round($payloadData->defectiveRate() * 100.0) . ' [%]',
            'planCount' => $payloadData->planCount(),
            'achievementRate' => round($payloadData->achievementRate() * 100.0) . ' [%]',
            'cycleTime' => round($payloadData->cycleTime()) . ' [sec]',
            'timeOperatingRate' => round($payloadData->timeOperatingRate() * 100.0) . ' [%]',
            'performanceOperatingRate' => round($payloadData->performanceOperatingRate() * 100.0) . ' [%]',
            'overallEquipmentEffectiveness' => round($payloadData->overallEquipmentEffectiveness() * 100.0) . ' [%]',
        ];
    }
}
