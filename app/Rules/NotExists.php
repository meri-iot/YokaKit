<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class NotExists implements Rule
{
    /**
     * 指定した値を選んだときだけ、追加条件付きで重複の有無を検証する。
     *
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $notConditions
     */
    public function __construct(
        private string $table,
        private string $column,
        private mixed $value,
        private array $conditions = [],
        private array $notConditions = [],
    ) {}

    /**
     * 対象値以外はそのまま許可し、対象値のときだけ既存レコードの有無を確認する。
     */
    public function passes($attribute, $value): bool
    {
        $targetValue = $this->normalizeComparableValue($this->value);
        $actualValue = $this->normalizeComparableValue($value);

        if ($actualValue != $targetValue) {
            return true;
        }

        $query = DB::table($this->table)
            ->where($this->column, $targetValue);

        foreach ($this->conditions as $key => $val) {
            $query->where($key, $val);
        }

        foreach ($this->notConditions as $key => $val) {
            $query->whereNot($key, $val);
        }

        return !$query->exists();
    }

    /**
     * エラーメッセージを返す。
     */
    public function message(): string
    {
        return __('validation.not_exists');
    }

    /**
     * Enum オブジェクトも比較できるように検証値を正規化する。
     */
    private function normalizeComparableValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if (is_object($value) && property_exists($value, 'value')) {
            return $value->value;
        }

        return $value;
    }
}
