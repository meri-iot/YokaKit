<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * サイクルタイムの更新リクエスト
 *
 * 標準サイクルタイムと警告時間の上限を検証します。
 * サイクルタイムは 2.0 秒以上で、警告時間上限はサイクルタイムより大きい値を指定します。
 * 管理者権限が必要です。
 */
class UpdateCycleTimeRequest extends FormRequest
{
    /**
     * ユーザーがこのリクエストを実行する権限があるかを判定
     *
     * 管理者のみがサイクルタイム設定を更新できます。
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * 本リクエストに適用される検証ルール
     *
     * - cycle_time: 2.000～86399.999 秒の数値
     * - over_time: 2.001～86400 秒の数値で、cycle_time より大きい値
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'cycle_time' => 'required|numeric|min:2.000|max:86399.999',
            'over_time' => 'required|numeric|min:2.001|max:86400|gt:cycle_time',
        ];
    }
}
