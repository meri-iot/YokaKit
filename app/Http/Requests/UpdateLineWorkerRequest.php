<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * 生産ライン作業者の一括更新リクエスト
 *
 * 工程に紐づく複数ラインの作業者割り当てを更新するための入力を検証します。
 *
 * @property integer $process_id 工程ID
 * @property array<int, array<string,mixed>> $lines 生産ラインデータ
 */
class UpdateLineWorkerRequest extends FormRequest
{
    /**
     * ユーザーがこのリクエストを実行する権限があるかを判定
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * 本リクエストに適用される検証ルール
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

        foreach ((array) $this->input('lines', []) as $key => $line) {
            $lineId = $line['line_id'] ?? null;
            $raspberryPiId = $line['raspberry_pi_id'] ?? null;
            $rules["lines.{$key}.worker_id"] = "nullable|exists:workers,worker_id|unique:lines,worker_id,{$lineId},line_id,raspberry_pi_id,{$raspberryPiId}";
        }

        return $rules;
    }

    /**
     * バリデーションエラーメッセージ用の属性名
     *
     * @return array<string,mixed>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach ((array) $this->input('lines', []) as $key => $line) {
            $attributes["lines.$key.worker_id"] = __('yokakit.worker');
            $attributes["lines.$key.line_id"] = __('yokakit.target_name', ['target' => __('yokakit.line')]);
            $attributes["lines.$key.raspberry_pi_id"] = __('yokakit.raspberry_pi');
        }

        return $attributes;
    }

    protected function getRedirectUrl(): string
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
        $process = $this->route('process');

        if (!($process instanceof Process)) {
            return;
        }

        // パラメータをマージ
        $this->merge(['process_id' => $process->process_id]);
    }
}
