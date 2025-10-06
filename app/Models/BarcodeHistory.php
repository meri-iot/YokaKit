<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * バーコード読み取り履歴モデルクラス
 *
 * @property integer $barcode_history_id 主キー
 * @property string $ip_address IPアドレス
 * @property string $mac_address MACアドレス
 * @property string $barcode バーコード
 */
class BarcodeHistory extends Model
{
    use HasFactory;

    /**
     * モデルの主キー名
     *
     * @var string
     */
    protected $primaryKey = 'barcode_history_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'ip_address',   // IPアドレス
        'mac_address',  // MACアドレス
        'barcode',      // バーコード
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
