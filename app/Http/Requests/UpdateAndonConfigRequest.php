<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AndonColumnSize;
use App\Enums\EasingType;
use App\Http\Requests\Concerns\NormalizesBooleanInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Andon（安全灯）設定の更新リクエスト
 *
 * 表示行数、表示列数、自動再生速度、スライド速度、イージング、
 * レイアウト、表示フラグを検証します。
 */
class UpdateAndonConfigRequest extends FormRequest
{
    use NormalizesBooleanInput;

    /**
     * ユーザーがこのリクエストを実行する権限があるかを判定
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * 本リクエストに適用される検証ルール
     *
     * - row_count: 1～100 の整数
     * - column_count: AndonColumnSize の列数値のいずれか
     * - auto_play_speed: 0～3600000 ミリ秒
     * - slide_speed: 0～3600000 ミリ秒
     * - easing: EasingType の値のいずれか
     * - font_ratio: 0.0 以上の数値
     * - layouts: オプションの配列、各アイテムは process_id を必須とします
     * - item_column_count: AndonColumnSize の列数値のいずれか
     * - is_show_*: 各表示フラグは必須の boolean
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'row_count' => 'required|integer|between:1,100',
            'column_count' => ['required', 'integer', Rule::in(AndonColumnSize::getValues())],
            'auto_play_speed' => 'required|integer|between:0,3600000',
            'slide_speed' => 'required|integer|between:0,3600000',
            'easing' => ['required', 'string', Rule::in(EasingType::getValues())],
            'font_ratio' => 'required|numeric|min:0',
            'layouts' => 'nullable|array',
            'layouts.*.display' => 'nullable|integer',
            'layouts.*.process_id' => 'required|integer|exists:processes,process_id',
            'item_column_count' => ['required', 'integer', Rule::in(AndonColumnSize::getValues())],
            'is_show_part_number' => 'required|boolean',
            'is_show_start' => 'required|boolean',
            'is_show_good_count' => 'required|boolean',
            'is_show_good_rate' => 'required|boolean',
            'is_show_defective_count' => 'required|boolean',
            'is_show_defective_rate' => 'required|boolean',
            'is_show_plan_count' => 'required|boolean',
            'is_show_achievement_rate' => 'required|boolean',
            'is_show_cycle_time' => 'required|boolean',
            'is_show_time_operating_rate' => 'required|boolean',
            'is_show_performance_operating_rate' => 'required|boolean',
            'is_show_overall_equipment_effectiveness' => 'required|boolean',
            'is_show_goal' => 'required|boolean',
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * 表示フラグ群を boolean として正規化し、
     * 変換不能値はそのまま残してバリデーションで検出する。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // パラメータをマージ
        $this->merge($this->normalizeBooleanInputs([
            'is_show_part_number',
            'is_show_start',
            'is_show_good_count',
            'is_show_good_rate',
            'is_show_defective_count',
            'is_show_defective_rate',
            'is_show_plan_count',
            'is_show_achievement_rate',
            'is_show_cycle_time',
            'is_show_time_operating_rate',
            'is_show_performance_operating_rate',
            'is_show_overall_equipment_effectiveness',
            'is_show_goal',
        ]));
    }
}
