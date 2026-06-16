<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * 計画停止時間追加リクエスト
 */
class StorePlannedOutageRequest extends FormRequest
{
    /**
     * 計画停止時間追加操作の実行権限を判定する。
     *
     * 管理者権限を持つユーザーのみ許可する。
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * 計画停止時間追加リクエストのバリデーションルールを返す。
     *
     * 名称の重複禁止と、開始時刻・終了時刻の形式と差異を検証する。
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'planned_outage_name' => 'required|string|unique:planned_outages,planned_outage_name|max:32',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|different:start_time'
        ];
    }
}
