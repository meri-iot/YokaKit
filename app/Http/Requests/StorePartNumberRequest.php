<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StorePartNumberRequest extends FormRequest
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
            'part_number_name' => 'required|string|max:32|unique:part_numbers,part_number_name',
            'barcode' => 'nullable|string|max:64|unique:part_numbers,barcode',
            'remark' => 'nullable|max:256',
        ];
    }
}
