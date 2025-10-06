<?php

namespace App\Http\Requests;

use App\Models\RaspberryPi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * ラズパイ更新リクエスト
 *
 * @property integer $raspberry_pi_id ラズパイID
 */
class UpdateRaspberryPiRequest extends FormRequest
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
            'raspberry_pi_name' => "required|string|max:32|unique:raspberry_pis,raspberry_pi_name,{$this->raspberry_pi_id},raspberry_pi_id",
            'ip_address' => "required|ip|unique:raspberry_pis,ip_address,{$this->raspberry_pi_id},raspberry_pi_id",
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        /** @var RaspberryPi */
        $raspberryPi = $this->route('raspberryPi');
        // パラメータをマージ
        $this->merge(['raspberry_pi_id' => $raspberryPi->raspberry_pi_id]);
    }
}
