<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreRaspberryPiRequest extends FormRequest
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
            'raspberry_pi_name' => 'required|string|max:32|unique:raspberry_pis,raspberry_pi_name',
            'ip_address' => 'required|ip|unique:raspberry_pis,ip_address',
        ];
    }
}
