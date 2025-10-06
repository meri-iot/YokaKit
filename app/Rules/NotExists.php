<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class NotExists implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(private $table, private $column, private $value, private array $conditions = [], private array $notConditions = []) {}

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $query = DB::table($this->table)
            ->where($this->column, $value)
            ->where($this->column, $this->value);

        // 条件がある場合は追加
        foreach ($this->conditions as $key => $val) {
            $query->where($key, $val);
        }
        foreach ($this->notConditions as $key => $val) {
            $query->whereNot($key, $val);
        }
        return !$query->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'この値は既に存在しています。';
    }
}
