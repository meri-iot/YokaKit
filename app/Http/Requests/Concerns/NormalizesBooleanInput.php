<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait NormalizesBooleanInput
{
    /**
     * 単一キーの値を boolean へ正規化する。
     *
     * 変換不能な値はそのまま返し、validation で検出させる。
     *
     * @return mixed
     */
    protected function normalizeBooleanInput(string $key)
    {
        $rawValue = $this->input($key);
        $normalizedValue = filter_var($rawValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $normalizedValue ?? $rawValue;
    }

    /**
     * 複数キーの値を boolean へ正規化して返す。
     *
     * @param array<int,string> $keys
     * @return array<string,mixed>
     */
    protected function normalizeBooleanInputs(array $keys): array
    {
        $normalized = [];

        foreach ($keys as $key) {
            $normalized[$key] = $this->normalizeBooleanInput($key);
        }

        return $normalized;
    }
}
