<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * ガントチャート並び替えリクエスト
 *
 * ガントチャートのID配列を受け取り、表示順の更新を検証します。
 * 管理者権限が必要です。
 *
 * @property array $order 並び順を示すガントチャートID配列
 */
class SortGanttChartRequest extends FormRequest
{
    /**
     * 並べ替え操作の実行権限を判定する。
     *
     * 管理者権限を持つユーザーのみ許可する。
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * 並べ替えリクエストのバリデーションルールを返す。
     *
     * `order` は並び順を示すガントチャートID配列で、
     * 各要素は実在するIDかつ重複不可とする。
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'order' => 'required|array|min:1',
            'order.*' => 'required|int|distinct|exists:gantt_charts,gantt_chart_id',
        ];
    }
}
