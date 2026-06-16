<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * アンドン設定モデルクラス
 *
 * @property integer $andon_config_id 主キー
 * @property integer $user_id ユーザーID(外部キー)
 * @property integer $row_count 行数
 * @property integer $column_count 列数
 * @property boolean $auto_play 自動再生フラグ
 * @property integer $auto_play_speed 自動再生速度[ms]
 * @property integer $slide_speed スライド速度[ms]
 * @property string $easing スライドアニメーション
 * @property boolean $fade フェードフラグ
 * @property double $font_ratio フォント倍率
 * @property integer $item_column_count アイテム表示列数
 * @property boolean $is_show_part_number 品番表示フラグ
 * @property boolean $is_show_start 開始時間表示フラグ
 * @property boolean $is_show_good_count 良品数表示フラグ
 * @property boolean $is_show_good_rate 良品率表示フラグ
 * @property boolean $is_show_defective_count 不良品数表示フラグ
 * @property boolean $is_show_defective_rate 不良品率表示フラグ
 * @property boolean $is_show_plan_count 計画値表示フラグ
 * @property boolean $is_show_achievement_rate 達成率表示フラグ
 * @property boolean $is_show_cycle_time サイクルタイム表示フラグ
 * @property boolean $is_show_time_operating_rate 時間稼働率表示フラグ
 * @property boolean $is_show_performance_operating_rate 性能稼働率表示フラグ
 * @property boolean $is_show_overall_equipment_effectiveness 設備総合効率表示フラグ
 * @property boolean $is_show_goal 目標値表示フラグ
 */
class AndonConfig extends Model
{
    use HasFactory;

    /**
     * モデルの主キー名
     *
     * @var string
     */
    protected $primaryKey = 'andon_config_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'user_id',                                  // ユーザーID
        'row_count',                                // 行数
        'column_count',                             // 列数
        'auto_play',                                // 自動再生フラグ
        'auto_play_speed',                          // 自動再生速度[ms]
        'slide_speed',                              // スライド速度[ms]
        'easing',                                   // スライドアニメーション
        'fade',                                     // フェードフラグ
        'font_ratio',                               // フォント倍率
        'item_column_count',                        // アイテム表示列数
        'is_show_part_number',                      // 品番表示フラグ
        'is_show_start',                            // 開始時間表示フラグ
        'is_show_good_count',                       // 良品数表示フラグ
        'is_show_good_rate',                        // 良品率表示フラグ
        'is_show_defective_count',                  // 不良品数表示フラグ
        'is_show_defective_rate',                   // 不良品率表示フラグ
        'is_show_plan_count',                       // 計画値表示フラグ
        'is_show_achievement_rate',                 // 達成率表示フラグ
        'is_show_cycle_time',                       // サイクルタイム表示フラグ
        'is_show_time_operating_rate',              // 時間稼働率表示フラグ
        'is_show_performance_operating_rate',       // 性能稼働率表示フラグ
        'is_show_overall_equipment_effectiveness',  // 設備総合効率表示フラグ
        'is_show_goal',                             // 目標値表示フラグ
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
        'auto_play' => 'boolean',                               // 自動再生フラグをbooleanにキャスト
        'fade' => 'boolean',                                    // フェードフラグをbooleanにキャスト
        'is_show_part_number' => 'boolean',                     // 品番表示フラグをbooleanにキャスト
        'is_show_start' => 'boolean',                           // 開始時間表示フラグをbooleanにキャスト
        'is_show_good_count' => 'boolean',                      // 良品数表示フラグをbooleanにキャスト
        'is_show_good_rate' => 'boolean',                       // 良品率表示フラグをbooleanにキャスト
        'is_show_defective_count' => 'boolean',                 // 不良品数表示フラグをbooleanにキャスト
        'is_show_defective_rate' => 'boolean',                  // 不良品率表示フラグをbooleanにキャスト
        'is_show_plan_count' => 'boolean',                      // 計画値表示フラグをbooleanにキャスト
        'is_show_achievement_rate' => 'boolean',                // 達成率表示フラグをbooleanにキャスト
        'is_show_cycle_time' => 'boolean',                      // サイクルタイム表示フラグをbooleanにキャスト
        'is_show_time_operating_rate' => 'boolean',             // 時間稼働率表示フラグをbooleanにキャスト
        'is_show_performance_operating_rate' => 'boolean',      // 性能稼働率表示フラグをbooleanにキャスト
        'is_show_overall_equipment_effectiveness' => 'boolean', // 設備総合効率表示フラグをbooleanにキャスト
        'is_show_goal' => 'boolean',                            // 目標値表示フラグをbooleanにキャスト
    ];

    /**
     * モデルのデフォルト属性値
     *
     * @var array<string,mixed>
     */
    protected $attributes = [
        'row_count' => 3,
        'column_count' => 4,
        'auto_play' => true,
        'auto_play_speed' => 3000,
        'slide_speed' => 300,
        'easing' => 'ease',
        'fade' => false,
        'font_ratio' => 1.0,
        'item_column_count' => 3,
        'is_show_part_number' => true,
        'is_show_start' => true,
        'is_show_good_count' => false,
        'is_show_good_rate' => false,
        'is_show_defective_count' => false,
        'is_show_defective_rate' => false,
        'is_show_plan_count' => false,
        'is_show_achievement_rate' => false,
        'is_show_cycle_time' => false,
        'is_show_time_operating_rate' => false,
        'is_show_performance_operating_rate' => false,
        'is_show_overall_equipment_effectiveness' => false,
        'is_show_goal' => false,
    ];

    /**
     * 要素の分配数を返す
     *
     * @return int
     */
    public function chunkLength(): int
    {
        return $this->row_count * $this->column_count;
    }
}
