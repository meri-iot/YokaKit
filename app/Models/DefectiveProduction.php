<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 不良品データモデルクラス
 *
 * @property integer $production_line_id 生産ラインID(外部キー)
 * @property Carbon $at 生産時刻
 * @property integer $count 生産数
 */
class DefectiveProduction extends Model
{
    use HasFactory;

    /**
     * モデルの主キーが自動インクリメントされるかどうかを示すフラグ
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'production_line_id',   // 生産ラインID
        'at',                   // 生産時刻
        'count',                // 生産数
    ];

    /**
     * キャストする属性
     *
     * @var array<string,string>
     */
    protected $casts = [
        'at' => 'datetime:Y-m-d H:i:s.u',
    ];

    /**
     * モデルにタイムスタンプを付けるかどうか
     *
     * @var bool
     */
    public $timestamps = false;
}
