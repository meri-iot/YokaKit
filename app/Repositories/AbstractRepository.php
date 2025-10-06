<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

/**
 * 抽象リポジトリクラス
 *
 * @template TModel of Model
 */
abstract class AbstractRepository
{
    /**
     * リポジトリが対象とするモデルクラス
     *
     * @var TModel
     */
    protected $model;

    /**
     * モデルクラス
     *
     * @return class-string
     */
    public abstract function model(): string;

    /**
     * コンストラクタ
     *
     * @param TModel|null $model
     */
    public function __construct(Model $model = null)
    {
        if (is_null($model)) {
            $this->model = app()->make($this->model());
        } else {
            $this->model = $model;
        }
    }

    /**
     * すべてのモデルを取得する
     *
     * @param string|array<int,string>|null $with 関連して取得するモデル
     * @param string|null $order 順序
     * @return Collection
     */
    public function all(string|array $with = null, string $order = null): Collection
    {
        if (is_null($with)) {
            if (is_null($order)) {
                return $this->model->all();
            } else {
                return $this->model->orderBy($order)->get();
            }
        } else {
            if (is_null($order)) {
                return $this->model->with($with)->get();
            } else {
                return $this->model->with($with)->orderBy($order)->get();
            }
        }
    }

    /**
     * 指定したIDのモデルを取得する
     *
     * @param integer $id 主キーのID
     * @param string|array<int,string>|null $with 関連して取得するモデル
     * @return TModel|null
     */
    public function find(int $id, string|array $with = null): ?Model
    {
        if (is_null($with)) {
            return $this->model->find($id);
        } else {
            return $this->model->with($with)->find($id);
        }
    }

    /**
     * 指定したカラムが値に一致する最初のモデルを取得する
     *
     * @param array<string,mixed> $condition 条件(カラム名と値の連想配列)
     * @param string|array<int,string>|null $with 関連して取得するモデル
     * @return TModel|null
     */
    public function first(array $condition, string|array $with = null): ?Model
    {
        return $this->where($condition, $with)->first();
    }

    /**
     * 指定したカラムが値に一致するモデルを取得する
     *
     * @param array<string,mixed> $condition 条件(カラム名と値の連想配列)
     * @param string|array<int,string>|null $with 関連して取得するモデル
     * @param array<int,string> $column 取得カラム
     * @param string|null $order 順序
     * @return Collection
     */
    public function get(array $condition, string|array $with = null, array $column = ['*'], string $order = null): Collection
    {
        $where = $this->where($condition, $with);
        if (is_null($order)) {
            return $where->get($column);
        } else {
            return $where->orderBy($order)->get($column);
        }
    }

    /**
     * 与えられた条件に基づいて SQL クエリを構築する。
     *
     * @param array<string,mixed> $condition キーと値のペアとして与えられた検索条件
     * @param string|array<int,string>|null $with モデルに対するリレーションの指定
     * @return Builder
     */
    private function where(array $condition, string|array|null $with = null): Builder
    {
        $builder = $this->model;
        if (!is_null($with)) {
            $builder = $builder->with($with);
        }
        foreach ($condition as $column => $value) {
            $builder = $builder->where($column, $value);
        }
        return $builder;
    }

    /**
     * モデルを新規作成する
     *
     * @param FormRequest $request リクエスト
     * @return boolean 成否
     */
    public function store(FormRequest $request): bool
    {
        $model = app()->make($this->model());
        return $this->storeModel($model->fill($request->all()));
    }

    /**
     * モデルを更新する
     *
     * @param FormRequest $request リクエスト
     * @param TModel $model 更新対象モデル
     * @return boolean 成否
     */
    public function update(FormRequest $request, Model $model): bool
    {
        return $this->updateModel($model->fill($request->all()));
    }

    /**
     * モデルを削除する
     *
     * @param TModel $model 削除対象モデル
     * @return boolean 成否
     */
    public function destroy(Model $model): bool
    {
        $result = $model->delete();
        if ($result) {
            Log::debug("{$this->model()} is destroyed.", $model->getRawOriginal());
        } else {
            Log::warning("Failed to destroy {$this->model()}.", $model->getRawOriginal());
        }
        return $result;
    }

    /**
     * 指定したモデルを更新する。
     *
     * @param TModel|Builder $obj 対象モデル
     * @param array<string,mixed> $attributes 更新データ
     * @return boolean 成否
     */
    protected function updateModel(Model|Builder $obj, array $attributes = []): bool
    {
        if ($obj instanceof Model) {
            $result = $obj->update($attributes);
            if ($result) {
                Log::debug("{$this->model()} is updated.", $obj->toArray());
            } else {
                Log::warning("Failed to update {$this->model()}.", $obj->toArray());
            }
            return $result;
        } else {
            $result = $obj->update($attributes);
            if ($result !== 0) {
                Log::debug("{$this->model()} is updated.", [$obj->getQuery()]);
            } else {
                Log::warning("Failed to update {$this->model()}.", [$obj->getQuery()]);
            }
            return $result !== 0;
        }
    }

    /**
     * 指定したモデルを追加する。
     *
     * @param Model $model モデル
     * @return boolean 成否
     */
    protected function storeModel(Model $model): bool
    {
        $result = $model->save();
        if ($result) {
            Log::debug("{$this->model()} is stored.", $model->toArray());
        } else {
            Log::warning("Failed to store {$this->model()}.", $model->toArray());
        }
        return $result;
    }
}
