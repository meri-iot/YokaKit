<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PartNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * 品番更新リクエスト
 *
 * 品番名、バーコード、備考の更新入力を検証します。
 * 管理者権限が必要です。
 *
 * @property integer $part_number_id 品番ID
 */
class UpdatePartNumberRequest extends FormRequest
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
            'part_number_name' => "required|string|max:32|unique:part_numbers,part_number_name,{$this->part_number_id},part_number_id",
            'barcode' => "nullable|string|max:64|unique:part_numbers,barcode,{$this->part_number_id},part_number_id",
            'remark' => 'nullable|string|max:256',
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * ルートから品番IDを補完し、任意入力の空文字を null へ正規化する。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $partNumber = $this->route('partNumber');

        if (!($partNumber instanceof PartNumber)) {
            return;
        }

        // パラメータをマージ
        $this->merge([
            'part_number_id' => $partNumber->part_number_id,
            'barcode' => $this->filled('barcode') ? $this->input('barcode') : null,
            'remark' => $this->filled('remark') ? $this->input('remark') : null,
        ]);
    }
}
