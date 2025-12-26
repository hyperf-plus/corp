<?php

declare(strict_types=1);

namespace HPlus\Corp\Crud;

use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Model;
use Hyperf\DbConnection\Db;

/**
 * 通用 CRUD 服务基类
 * 
 * 纯业务逻辑层，不涉及权限验证（权限在控制器层通过 #[Permission] 注解完成）
 * 
 * 使用方式：
 * ```php
 * class OrderService extends CrudService
 * {
 *     protected string $model = Order::class;
 *     
 *     protected array $searchable = ['order_no', 'customer_name'];
 *     protected array $filterable = ['status', 'type'];
 *     protected array $with = ['customer'];
 * }
 * ```
 */
abstract class CrudService
{
    /**
     * 模型类名（必须定义）
     */
    protected string $model;

    /**
     * 可搜索字段（模糊匹配）
     */
    protected array $searchable = [];

    /**
     * 可过滤字段（精确匹配）
     */
    protected array $filterable = [];

    /**
     * 可排序字段
     */
    protected array $sortable = ['created_at', 'id'];

    /**
     * 默认排序
     */
    protected array $defaultSort = ['created_at' => 'desc'];

    /**
     * 默认关联
     */
    protected array $with = [];

    /**
     * 每页数量
     */
    protected int $perPage = 15;

    /**
     * 最大每页数量
     */
    protected int $maxPerPage = 100;

    // ==================== 查询方法 ====================

    /**
     * 列表查询（分页）
     */
    public function list(array $params = []): array
    {
        $query = $this->buildQuery($params);
        
        $perPage = min($params['per_page'] ?? $this->perPage, $this->maxPerPage);
        $page = max($params['page'] ?? 1, 1);
        
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        
        return [
            'items' => $this->transformList($paginator->items()),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * 获取全部（不分页）
     */
    public function all(array $params = []): array
    {
        return $this->transformList($this->buildQuery($params)->get()->all());
    }

    /**
     * 详情
     */
    public function detail(int $id, array $with = []): ?array
    {
        $model = $this->find($id, $with);
        return $model ? $this->transform($model) : null;
    }

    /**
     * 根据 ID 查找
     */
    public function find(int $id, array $with = []): ?Model
    {
        return $this->query()
            ->with(array_merge($this->with, $with))
            ->find($id);
    }

    /**
     * 根据条件查找
     */
    public function findBy(array $conditions, array $with = []): ?Model
    {
        return $this->query()
            ->with(array_merge($this->with, $with))
            ->where($conditions)
            ->first();
    }

    /**
     * 检查是否存在
     */
    public function exists(array $conditions): bool
    {
        return $this->query()->where($conditions)->exists();
    }

    /**
     * 统计
     */
    public function count(array $params = []): int
    {
        return $this->buildQuery($params)->count();
    }

    // ==================== 写入方法 ====================

    /**
     * 创建
     */
    public function create(array $data): Model
    {
        $data = $this->beforeCreate($data);
        
        Db::beginTransaction();
        try {
            $model = $this->newModel();
            $model->fill($data);
            $model->save();
            
            $this->afterCreate($model, $data);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
        
        return $model->fresh($this->with);
    }

    /**
     * 更新
     */
    public function update(int $id, array $data): ?Model
    {
        $model = $this->find($id);
        if (!$model) {
            return null;
        }

        $data = $this->beforeUpdate($model, $data);
        
        Db::beginTransaction();
        try {
            $model->fill($data);
            $model->save();
            
            $this->afterUpdate($model, $data);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
        
        return $model->fresh($this->with);
    }

    /**
     * 删除
     */
    public function delete(int $id): bool
    {
        $model = $this->find($id);
        if (!$model || !$this->beforeDelete($model)) {
            return false;
        }

        Db::beginTransaction();
        try {
            $result = (bool) $model->delete();
            if ($result) {
                $this->afterDelete($model);
            }
            Db::commit();
            return $result;
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }

    /**
     * 批量删除（高性能）
     * 
     * @param array $ids ID 数组
     * @param int $chunkSize 分批大小，避免一次查询过多数据
     */
    public function batchDelete(array $ids, int $chunkSize = 100): int
    {
        if (empty($ids)) {
            return 0;
        }

        $count = 0;
        $chunks = array_chunk($ids, $chunkSize);
        
        Db::beginTransaction();
        try {
            foreach ($chunks as $chunkIds) {
                $models = $this->query()
                    ->whereIn($this->getPrimaryKey(), $chunkIds)
                    ->get();
                
                foreach ($models as $model) {
                    if ($this->beforeDelete($model)) {
                        $model->delete();
                        $this->afterDelete($model);
                        $count++;
                    }
                }
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
        
        return $count;
    }
    
    /**
     * 强制批量删除（跳过钩子，直接删除）
     * 
     * 适用于不需要钩子检查的场景，性能更高
     */
    public function forceDelete(array $ids, int $chunkSize = 500): int
    {
        if (empty($ids)) {
            return 0;
        }
        
        $count = 0;
        $chunks = array_chunk($ids, $chunkSize);
        
        foreach ($chunks as $chunkIds) {
            $count += $this->query()
                ->whereIn($this->getPrimaryKey(), $chunkIds)
                ->delete();
        }
        
        return $count;
    }

    /**
     * 更新状态
     */
    public function updateStatus(int $id, int $status): bool
    {
        $model = $this->find($id);
        if (!$model) {
            return false;
        }
        $model->status = $status;
        return $model->save();
    }

    /**
     * 批量更新状态
     */
    public function batchUpdateStatus(array $ids, int $status, int $chunkSize = 500): int
    {
        if (empty($ids)) {
            return 0;
        }
        
        $count = 0;
        $chunks = array_chunk($ids, $chunkSize);
        
        foreach ($chunks as $chunkIds) {
            $count += $this->query()
                ->whereIn($this->getPrimaryKey(), $chunkIds)
                ->update(['status' => $status]);
        }
        
        return $count;
    }
    
    /**
     * 批量更新
     */
    public function batchUpdate(array $ids, array $data, int $chunkSize = 500): int
    {
        if (empty($ids) || empty($data)) {
            return 0;
        }
        
        $count = 0;
        $chunks = array_chunk($ids, $chunkSize);
        
        foreach ($chunks as $chunkIds) {
            $count += $this->query()
                ->whereIn($this->getPrimaryKey(), $chunkIds)
                ->update($data);
        }
        
        return $count;
    }

    // ==================== 查询构建 ====================

    /**
     * 构建查询
     */
    protected function buildQuery(array $params = []): Builder
    {
        $query = $this->query();
        
        // 关联
        $with = array_merge($this->with, $params['with'] ?? []);
        if ($with) {
            $query->with($with);
        }
        
        // 搜索
        if (!empty($params['keyword']) && $this->searchable) {
            $keyword = $params['keyword'];
            $query->where(function ($q) use ($keyword) {
                foreach ($this->searchable as $field) {
                    if (str_contains($field, '.')) {
                        // 关联字段搜索
                        [$relation, $column] = explode('.', $field, 2);
                        $q->orWhereHas($relation, fn($sub) => $sub->where($column, 'like', "%{$keyword}%"));
                    } else {
                        $q->orWhere($field, 'like', "%{$keyword}%");
                    }
                }
            });
        }
        
        // 过滤
        foreach ($this->filterable as $field) {
            if (isset($params[$field]) && $params[$field] !== '') {
                is_array($params[$field]) 
                    ? $query->whereIn($field, $params[$field])
                    : $query->where($field, $params[$field]);
            }
        }
        
        // 时间范围
        if (!empty($params['start_time'])) {
            $query->where('created_at', '>=', $params['start_time']);
        }
        if (!empty($params['end_time'])) {
            $query->where('created_at', '<=', $params['end_time']);
        }
        
        // 自定义查询
        $this->applyCustomQuery($query, $params);
        
        // 排序
        $sortField = $params['sort_field'] ?? null;
        $sortOrder = strtolower($params['sort_order'] ?? 'desc');
        
        if ($sortField && in_array($sortField, $this->sortable)) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            foreach ($this->defaultSort as $field => $order) {
                $query->orderBy($field, $order);
            }
        }
        
        return $query;
    }

    /**
     * 自定义查询（子类重写）
     */
    protected function applyCustomQuery(Builder $query, array $params): void
    {
    }

    // ==================== 数据转换 ====================

    /**
     * 转换单条（子类可重写）
     */
    protected function transform(Model $model): array
    {
        return $model->toArray();
    }

    /**
     * 转换列表
     */
    protected function transformList(array $items): array
    {
        return array_map(fn($item) => $this->transform($item), $items);
    }

    // ==================== 钩子方法 ====================

    protected function beforeCreate(array $data): array
    {
        return $data;
    }

    protected function afterCreate(Model $model, array $data): void
    {
    }

    protected function beforeUpdate(Model $model, array $data): array
    {
        return $data;
    }

    protected function afterUpdate(Model $model, array $data): void
    {
    }

    protected function beforeDelete(Model $model): bool
    {
        return true;
    }

    protected function afterDelete(Model $model): void
    {
    }

    // ==================== 辅助方法 ====================

    protected function query(): Builder
    {
        return $this->newModel()->newQuery();
    }

    protected function newModel(): Model
    {
        return new $this->model();
    }

    protected function getPrimaryKey(): string
    {
        return $this->newModel()->getKeyName();
    }
}
