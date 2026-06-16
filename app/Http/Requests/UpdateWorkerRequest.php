<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Worker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * 作業者更新リクエスト
 *
 * 識別番号、作業者名、MACアドレスの更新入力を検証します。
 * 管理者権限が必要です。
 *
 * @property integer $worker_id 作業者ID
 */
class UpdateWorkerRequest extends FormRequest
{
    /**
     * ユーザーがこのリクエストを実行する権限があるかを判定
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * 本リクエストに適用される検証ルール
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'identification_number' => "required|string|max:32|unique:workers,identification_number,{$this->worker_id},worker_id",
            'worker_name' => 'required|string|max:32',
            'mac_address' => "nullable|mac_address|unique:workers,mac_address,{$this->worker_id},worker_id",
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * ルートから更新対象の作業者IDを補完する。
     * ルートパラメータが欠落している場合は処理をスキップする。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $worker = $this->route('worker');

        if (!($worker instanceof Worker)) {
            return;
        }

        // パラメータをマージ
        $this->merge(['worker_id' => $worker->worker_id]);
    }
}
