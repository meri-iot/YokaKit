<?php

namespace App\Http\Requests;

use App\Models\PartNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * 品番更新リクエスト
 *
 * @property integer $part_number_id 品番ID
 */
class UpdatePartNumberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Gate::check('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string,mixed>
     */
    public function rules()
    {
        return [
            'part_number_name' => "required|string|max:32|unique:part_numbers,part_number_name,{$this->part_number_id},part_number_id",
            'barcode' => "nullable|string|max:64|unique:part_numbers,barcode,{$this->part_number_id},part_number_id",
            'remark' => 'max:256',
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        /** @var PartNumber */
        $partNumber = $this->route('partNumber');
        // パラメータをマージ
        $this->merge(['part_number_id' => $partNumber->part_number_id]);
    }
}
