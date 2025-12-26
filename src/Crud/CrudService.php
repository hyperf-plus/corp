<?php

declare(strict_types=1);

namespace HPlus\Corp\Crud;

use HPlus\Corp\Context\CorpContext;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Model;
use Hyperf\DbConnection\Db;

/**
 * 通用 CRUD 服务基类
 * 
 * 提供标准的增删改查功能，支持：
 * - 分页、搜索、过滤、排序
 * - 数据隔离（配合 HasDataScope）
 * - 关联查询
 * - 事件派发
 * 
 * 使用方式：
 * ```php
 * class OrderService extends CrudService
 * {
 *     protected string $model = Order::class;
 *     
 *     protected array $searchable = ['order_no', 'customer_name'];
 *     protected array $filterable = ['status', 'type'];
 *     protected array $sortable = ['created_at', 'amount'];
 *     protected array $with = ['customer', 'items'];
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

    /**
     * 创建时可填充字段（为空则使用模型的 fillable）
     */
    protected array $createFields = [];

    /**
     * 更新时可填充字段（为空则使用模型的 fillable）
     */
    protected array $updateFields = [];

    /**
     * 是否自动注入上下文字段
     */
    protected bool $autoInjectContext = true;

    // ==================== 查询方法 ====================

    /**
     * 列表查询（分页）
     */
    public function list(array $params = []): array
    {
        $query = $this->buildQuery($params);
        
        // 分页
        $perPage = min($params['per_page'] ?? $this->perPage, $this->maxPerPage);
        $page = max($params['page'] ?? 1, 1);
        
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        
        return [
            'items' => $this->transformCollection($paginator->items()),
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
        $query = $this->buildQuery($params);
        $items = $query->get();
        
        return $this->transformCollection($items->all());
    }

    /**
     * 详情查询
     */
    public function detail(int $id, array $with = []): ?array
    {
        $model = $this->findById($id, $with);
        
        return $model ? $this->transform($model) : null;
    }

    /**
     * 根据 ID 查找模型
     */
    public function findById(int $id, array $with = []): ?Model
    {
        $with = array_merge($this->with, $with);
        
        return $this->query()
            ->with($with)
            ->find($id);
    }

    /**
     * 根据条件查找单条
     */
    public function findBy(array $conditions, array $with = []): ?Model
    {
        $with = array_merge($this->with, $with);
        
        return $this->query()
            ->with($with)
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
     * 统计数量
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
        $data = $this->filterCreateData($data);
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
        $model = $this->findById($id);
        if (!$model) {
            return null;
        }

        $data = $this->filterUpdateData($data);
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
        $model = $this->findById($id);
        if (!$model) {
            return false;
        }

        if (!$this->beforeDelete($model)) {
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
     * 批量删除
     */
    public function batchDelete(array $ids): int
    {
        $count = 0;
        
        Db::beginTransaction();
        try {
            foreach ($ids as $id) {
                if ($this->delete($id)) {
                    $count++;
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
     * 更新状态
     */
    public function updateStatus(int $id, int $status): bool
    {
        $model = $this->findById($id);
        if (!$model) {
            return false;
        }

        $model->status = $status;
        return $model->save();
    }

    /**
     * 批量更新状态
     */
    public function batchUpdateStatus(array $ids, int $status): int
    {
        return $this->query()
            ->whereIn($this->getPrimaryKey(), $ids)
            ->update(['status' => $status]);
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
        if (!empty($with)) {
            $query->with($with);
        }
        
        // 搜索
        if (!empty($params['keyword']) && !empty($this->searchable)) {
            $this->applySearch($query, $params['keyword']);
        }
        
        // 过滤
        $this->applyFilters($query, $params);
        
        // 自定义条件
        $this->applyCustomQuery($query, $params);
        
        // 排序
        $this->applySort($query, $params);
        
        return $query;
    }

    /**
     * 应用搜索
     */
    protected function applySearch(Builder $query, string $keyword): void
    {
        $query->where(function ($q) use ($keyword) {
            foreach ($this->searchable as $field) {
                $q->orWhere($field, 'like', "%{$keyword}%");
            }
        });
    }

    /**
     * 应用过滤
     */
    protected function applyFilters(Builder $query, array $params): void
    {
        foreach ($this->filterable as $field) {
            if (isset($params[$field]) && $params[$field] !== '') {
                $value = $params[$field];
                
                // 支持数组（IN 查询）
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, $value);
                }
            }
        }
        
        // 时间范围过滤
        if (!empty($params['start_time'])) {
            $query->where('created_at', '>=', $params['start_time']);
        }
        if (!empty($params['end_time'])) {
            $query->where('created_at', '<=', $params['end_time']);
        }
    }

    /**
     * 应用排序
     */
    protected function applySort(Builder $query, array $params): void
    {
        $sortField = $params['sort_field'] ?? null;
        $sortOrder = strtolower($params['sort_order'] ?? 'desc');
        
        if ($sortField && in_array($sortField, $this->sortable)) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            // 默认排序
            foreach ($this->defaultSort as $field => $order) {
                $query->orderBy($field, $order);
            }
        }
    }

    /**
     * 自定义查询（子类重写）
     */
    protected function applyCustomQuery(Builder $query, array $params): void
    {
        // 子类重写以添加自定义查询逻辑
    }

    // ==================== 数据处理 ====================

    /**
     * 过滤创建数据
     */
    protected function filterCreateData(array $data): array
    {
        if (!empty($this->createFields)) {
            $data = array_intersect_key($data, array_flip($this->createFields));
        }
        
        return $data;
    }

    /**
     * 过滤更新数据
     */
    protected function filterUpdateData(array $data): array
    {
        if (!empty($this->updateFields)) {
            $data = array_intersect_key($data, array_flip($this->updateFields));
        }
        
        // 移除不可更新的字段
        unset($data['id'], $data['corp_id'], $data['created_at']);
        
        return $data;
    }

    /**
     * 转换单条数据（子类可重写）
     */
    protected function transform(Model $model): array
    {
        return $model->toArray();
    }

    /**
     * 转换集合数据
     */
    protected function transformCollection(array $items): array
    {
        return array_map(fn($item) => $this->transform($item), $items);
    }

    // ==================== 钩子方法（子类重写） ====================

    /**
     * 创建前处理
     */
    protected function beforeCreate(array $data): array
    {
        return $data;
    }

    /**
     * 创建后处理
     */
    protected function afterCreate(Model $model, array $data): void
    {
        // 子类重写
    }

    /**
     * 更新前处理
     */
    protected function beforeUpdate(Model $model, array $data): array
    {
        return $data;
    }

    /**
     * 更新后处理
     */
    protected function afterUpdate(Model $model, array $data): void
    {
        // 子类重写
    }

    /**
     * 删除前检查（返回 false 阻止删除）
     */
    protected function beforeDelete(Model $model): bool
    {
        return true;
    }

    /**
     * 删除后处理
     */
    protected function afterDelete(Model $model): void
    {
        // 子类重写
    }

    // ==================== 辅助方法 ====================

    /**
     * 获取查询构建器
     */
    protected function query(): Builder
    {
        return $this->newModel()->newQuery();
    }

    /**
     * 创建模型实例
     */
    protected function newModel(): Model
    {
        return new $this->model();
    }

    /**
     * 获取主键名
     */
    protected function getPrimaryKey(): string
    {
        return $this->newModel()->getKeyName();
    }

    /**
     * 获取表名
     */
    protected function getTable(): string
    {
        return $this->newModel()->getTable();
    }
}

