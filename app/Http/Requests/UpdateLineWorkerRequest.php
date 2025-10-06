<?php

namespace App\Http\Requests;

use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 生産ラインの作業者の更新
 *
 * @property integer $process_id 工程ID
 * @property array<int, array<string,mixed>> $lines 生産ラインデータ
 */
class UpdateLineWorkerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        $rules = [
            'lines' => 'required|array',
            'lines.*.line_id' => 'required|exists:lines,line_id',
            'lines.*.raspberry_pi_id' => "nullable|exists:raspberry_pis,raspberry_pi_id",
        ];
        foreach ($this->lines as $line) {
            $rules["lines.{$line['line_id']}.worker_id"] = "nullable|exists:workers,worker_id|unique:lines,worker_id,{$this->process_id},process_id,raspberry_pi_id,{$line['raspberry_pi_id']}";
        }
        return $rules;
    }

    /**
     * Undocumented function
     *
     * @return array<string,mixed>
     */
    public function attributes(): array
    {
        $attributes = [];
        foreach ($this->lines as $line) {
            $lineId = $line['line_id'];
            $attributes["lines.$lineId.worker_id"] = __('yokakit.worker');
            $attributes["lines.$lineId.line_id"] = __('yokakit.target_name', ['target' => __('yokakit.line')]);
            $attributes["lines.$lineId.raspberry_pi_id"] = __('yokakit.raspberry_pi');
        }
        return $attributes;
    }

    protected function getRedirectUrl()
    {
        return route('switch.index', ['process' => $this->route('process')]);
    }

    /**
     * バリデーションのためのデータの準備
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        /** @var Process */
        $process = $this->route('process');
        // パラメータをマージ
        $this->merge(['process_id' => $process->process_id]);
    }
}
