<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * アンドンレイアウトモデルクラス
 *
 * @property integer $andon_layout_id 主キー
 * @property integer $user_id ユーザーID(外部キー)
 * @property integer $process_id 工程ID(外部キー)
 * @property boolean $is_display 表示フラグ
 * @property integer $order 順序
 */
class AndonLayout extends Pivot
{
    use HasFactory;

    /**
     * モデルに関連付けられたテーブル名
     *
     * @var string
     */
    protected $table = 'andon_layouts';

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
    protected $primaryKey = 'andon_layout_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'user_id',      // ユーザーID
        'process_id',   // 工程ID
        'is_display',   // 表示フラグ
        'order',        // 順序
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
        'is_display' => 'boolean',  // 表示フラグをbooleanにキャスト
    ];
}
