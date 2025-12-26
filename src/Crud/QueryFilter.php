<?php

declare(strict_types=1);

namespace HPlus\Corp\Crud;

use Hyperf\Database\Model\Builder;

/**
 * 查询过滤器
 * 
 * 提供灵活的查询构建能力
 * 
 * 使用方式：
 * ```php
 * $filter = new QueryFilter($request->all());
 * $query = Order::query();
 * $filter->apply($query);
 * ```
 */
class QueryFilter
{
    protected array $params;
    protected array $searchable = [];
    protected array $filterable = [];
    protected array $sortable = [];
    protected array $relations = [];
    protected array $defaultSort = ['created_at' => 'desc'];

    public function __construct(array $params = [])
    {
        $this->params = $params;
    }

    /**
     * 设置可搜索字段
     */
    public function searchable(array $fields): static
    {
        $this->searchable = $fields;
        return $this;
    }

    /**
     * 设置可过滤字段
     */
    public function filterable(array $fields): static
    {
        $this->filterable = $fields;
        return $this;
    }

    /**
     * 设置可排序字段
     */
    public function sortable(array $fields): static
    {
        $this->sortable = $fields;
        return $this;
    }

    /**
     * 设置关联
     */
    public function with(array $relations): static
    {
        $this->relations = $relations;
        return $this;
    }

    /**
     * 设置默认排序
     */
    public function defaultSort(array $sort): static
    {
        $this->defaultSort = $sort;
        return $this;
    }

    /**
     * 应用过滤
     */
    public function apply(Builder $query): Builder
    {
        // 关联
        if (!empty($this->relations)) {
            $query->with($this->relations);
        }

        // 搜索
        $this->applySearch($query);

        // 过滤
        $this->applyFilters($query);

        // 时间范围
        $this->applyDateRange($query);

        // 排序
        $this->applySort($query);

        return $query;
    }

    /**
     * 分页查询
     */
    public function paginate(Builder $query, int $perPage = 15, int $maxPerPage = 100): array
    {
        $this->apply($query);

        $perPage = min($this->params['per_page'] ?? $perPage, $maxPerPage);
        $page = max($this->params['page'] ?? 1, 1);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * 获取全部
     */
    public function get(Builder $query): array
    {
        $this->apply($query);
        return $query->get()->all();
    }

    /**
     * 应用搜索
     */
    protected function applySearch(Builder $query): void
    {
        $keyword = $this->params['keyword'] ?? null;
        
        if (empty($keyword) || empty($this->searchable)) {
            return;
        }

        $query->where(function ($q) use ($keyword) {
            foreach ($this->searchable as $field) {
                // 支持关联字段搜索 (如 'customer.name')
                if (str_contains($field, '.')) {
                    [$relation, $column] = explode('.', $field, 2);
                    $q->orWhereHas($relation, function ($sub) use ($column, $keyword) {
                        $sub->where($column, 'like', "%{$keyword}%");
                    });
                } else {
                    $q->orWhere($field, 'like', "%{$keyword}%");
                }
            }
        });
    }

    /**
     * 应用过滤
     */
    protected function applyFilters(Builder $query): void
    {
        foreach ($this->filterable as $field => $config) {
            // 简单配置: ['status', 'type']
            if (is_int($field)) {
                $field = $config;
                $config = [];
            }

            $value = $this->params[$field] ?? null;
            
            if ($value === null || $value === '') {
                continue;
            }

            $operator = $config['operator'] ?? '=';
            $column = $config['column'] ?? $field;

            // 支持数组（IN 查询）
            if (is_array($value)) {
                $query->whereIn($column, $value);
                continue;
            }

            // 支持各种操作符
            match ($operator) {
                '=' => $query->where($column, $value),
                '!=' => $query->where($column, '!=', $value),
                '>' => $query->where($column, '>', $value),
                '>=' => $query->where($column, '>=', $value),
                '<' => $query->where($column, '<', $value),
                '<=' => $query->where($column, '<=', $value),
                'like' => $query->where($column, 'like', "%{$value}%"),
                'in' => $query->whereIn($column, is_array($value) ? $value : [$value]),
                'not_in' => $query->whereNotIn($column, is_array($value) ? $value : [$value]),
                'null' => $value ? $query->whereNull($column) : $query->whereNotNull($column),
                'json' => $query->whereJsonContains($column, $value),
                default => $query->where($column, $operator, $value),
            };
        }
    }

    /**
     * 应用时间范围
     */
    protected function applyDateRange(Builder $query): void
    {
        // 通用时间范围
        if (!empty($this->params['start_time'])) {
            $query->where('created_at', '>=', $this->params['start_time']);
        }
        if (!empty($this->params['end_time'])) {
            $query->where('created_at', '<=', $this->params['end_time']);
        }

        // 支持自定义字段时间范围 (如 start_pay_time, end_pay_time)
        foreach ($this->params as $key => $value) {
            if (empty($value)) continue;
            
            if (preg_match('/^start_(.+)$/', $key, $matches)) {
                $field = $matches[1];
                if ($field !== 'time') {
                    $query->where($field, '>=', $value);
                }
            } elseif (preg_match('/^end_(.+)$/', $key, $matches)) {
                $field = $matches[1];
                if ($field !== 'time') {
                    $query->where($field, '<=', $value);
                }
            }
        }
    }

    /**
     * 应用排序
     */
    protected function applySort(Builder $query): void
    {
        $sortField = $this->params['sort_field'] ?? null;
        $sortOrder = strtolower($this->params['sort_order'] ?? 'desc');
        $sortOrder = in_array($sortOrder, ['asc', 'desc']) ? $sortOrder : 'desc';

        if ($sortField && (empty($this->sortable) || in_array($sortField, $this->sortable))) {
            $query->orderBy($sortField, $sortOrder);
        } else {
            foreach ($this->defaultSort as $field => $order) {
                $query->orderBy($field, $order);
            }
        }
    }

    /**
     * 静态构造
     */
    public static function make(array $params = []): static
    {
        return new static($params);
    }
}

