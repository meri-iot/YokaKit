<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\RaspberryPi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * ラズパイ更新リクエスト
 *
 * ラズパイ名とIPアドレスの更新入力を検証します。
 * 管理者権限が必要です。
 *
 * @property integer $raspberry_pi_id ラズパイID
 */
class UpdateRaspberryPiRequest extends FormRequest
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
            'raspberry_pi_name' => "required|string|max:32|unique:raspberry_pis,raspberry_pi_name,{$this->raspberry_pi_id},raspberry_pi_id",
            'ip_address' => "required|ip|unique:raspberry_pis,ip_address,{$this->raspberry_pi_id},raspberry_pi_id",
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * ルートから更新対象ラズパイIDを補完する。
     * ルートパラメータが欠落している場合は処理をスキップする。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $raspberryPi = $this->route('raspberryPi');

        if (!($raspberryPi instanceof RaspberryPi)) {
            return;
        }

        // パラメータをマージ
        $this->merge(['raspberry_pi_id' => $raspberryPi->raspberry_pi_id]);
    }
}
