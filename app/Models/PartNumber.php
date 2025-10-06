<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 品番モデルクラス
 *
 * @property integer $part_number_id 主キー
 * @property string $part_number_name 品番名
 * @property string|null $barcode バーコード
 * @property string|null $remark 備考
 */
class PartNumber extends Model
{
    use HasFactory;

    /**
     * モデルの主キー名
     *
     * @var string
     */
    protected $primaryKey = 'part_number_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'part_number_name', // 品番名
        'barcode',          // バーコード
        'remark',           // 備考
    ];

    /**
     * シリアライズ時に隠す属性
     *
     * @var array<int,string>
     */
    protected $hidden = [
        'remark',       // 備考を隠す
        'created_at',   // 作成時刻を隠す
        'updated_at',   // 更新時刻を隠す
    ];
}
