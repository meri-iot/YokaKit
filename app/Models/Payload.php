<?php

namespace App\Models;

use App\Data\PayloadData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 指標計算のデータ保持用
 *
 * @property integer $payload_id 主キー
 * @property integer $production_line_id 生産ラインID(外部キー)
 * @property array<string,mixed> $payload ペイロード
 */
class Payload extends Model
{
    use HasFactory;

    /**
     * モデルの主キー名
     *
     * @var string
     */
    protected $primaryKey = 'payload_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'production_line_id',   // 生産ラインID
        'payload',              // ペイロード
    ];

    /**
     * キャストする属性
     *
     * @var array<string,string>
     */
    protected $casts = [
        'payload' => 'json',
    ];

    /**
     * シリアライズ時に隠す属性
     *
     * @var array<int,string>
     */
    protected $hidden = [
        'payload',      // ペイロードを隠す
        'created_at',   // 作成時刻を隠す
        'updated_at',   // 更新時刻を隠す
    ];

    /**
     * 指標計算用データを取得する
     *
     * @return PayloadData 指標計算用データ
     */
    public function getPayloadData(): PayloadData
    {
        return PayloadData::from($this->payload);
    }

    /**
     * 指標計算用データを設定する
     *
     * @param PayloadData $payloadData 指標計算用データ
     * @return void
     */
    public function setPayloadData(PayloadData $payloadData): void
    {
        $this->payload = $payloadData->toArray();
    }
}
