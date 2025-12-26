<?php

declare(strict_types=1);

namespace HPlus\Corp\Scope;

use HPlus\Corp\Context\CorpContext;
use HPlus\Corp\CorpManager;
use HPlus\Corp\Model\Collaborator;
use HPlus\Corp\Model\Role;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\Scope;
use Hyperf\DbConnection\Db;
use Psr\SimpleCache\CacheInterface;
use function Hyperf\Support\make;

/**
 * 数据范围过滤作用域
 * 
 * 用户级数据隔离，支持：
 * 1. 角色数据范围（auth_range）：仅本人/本部门/本部门及下属/全部
 * 2. 协作者权限（可选）：通过协作者表授权访问
 * 
 * 最终可见数据 = 角色数据范围内的数据 ∪ 被授权协作的数据
 */
class DataScope implements Scope
{
    /**
     * 字段检测缓存（静态，进程级 - 表结构很少变）
     */
    private static array $columnCache = [];

    /**
     * 部门子树缓存配置
     */
    protected const DEPT_TREE_CACHE_PREFIX = 'corp:dept_tree:';
    protected const DEPT_TREE_CACHE_TTL = 300; // 5分钟

    public function apply(Builder $builder, Model $model): void
    {
        // 跳过过滤
        if (CorpContext::isSkipDataScope()) {
            return;
        }

        $corpId = CorpContext::getCorpId();
        $employeeId = CorpContext::getEmployeeId();

        // 没有上下文，不过滤
        if (!$corpId || !$employeeId) {
            return;
        }

        $table = $model->getTable();

        // 1. 先过滤 corp_id（企业隔离是基础）
        if ($this->hasColumn($model, 'corp_id')) {
            $builder->where("{$table}.corp_id", $corpId);
        }

        // 2. 管理员只过滤 corp_id，不过滤数据范围
        if (CorpContext::isAdmin()) {
            return;
        }

        // 3. 获取模型配置
        $enableCollaborator = $model->enableCollaborator ?? false;
        $resourceType = $model->resourceType ?? 0;
        $resourceIdColumn = $model->resourceIdColumn ?? $model->getKeyName();

        // 4. 构建用户可见范围
        $this->applyUserScope($builder, $model, $enableCollaborator, $resourceType, $resourceIdColumn);
    }

    /**
     * 应用用户数据范围
     */
    protected function applyUserScope(
        Builder $builder,
        Model $model,
        bool $enableCollaborator,
        int $resourceType,
        string $resourceIdColumn
    ): void {
        $table = $model->getTable();
        $employeeId = CorpContext::getEmployeeId();
        $authRange = CorpContext::getAuthRange();

        // 全部权限，且没有启用协作者，直接返回
        if ($authRange === Role::AUTH_RANGE_ALL && !$enableCollaborator) {
            return;
        }

        // 构建可见范围条件
        $builder->where(function ($query) use (
            $table, $model, $employeeId, $authRange,
            $enableCollaborator, $resourceType, $resourceIdColumn
        ) {
            $hasCondition = false;

            // A. 角色数据范围
            if ($authRange !== Role::AUTH_RANGE_ALL) {
                $this->applyAuthRangeCondition($query, $model, $authRange, $hasCondition);
            } else {
                // 全部权限，不添加角色范围条件（相当于 OR 1=1，但需要配合协作者）
                // 如果有协作者，角色权限是"全部"，则所有数据都可见
                if ($enableCollaborator) {
                    return; // 不添加任何条件
                }
            }

            // B. 协作者权限（OR 关系）
            if ($enableCollaborator && $resourceType > 0) {
                $collaboratorIds = Collaborator::getUserResourceIds($employeeId, $resourceType);
                if (!empty($collaboratorIds)) {
                    $column = "{$table}.{$resourceIdColumn}";
                    if ($hasCondition) {
                        $query->orWhereIn($column, $collaboratorIds);
                    } else {
                        $query->whereIn($column, $collaboratorIds);
                        $hasCondition = true;
                    }
                }
            }

            // 如果没有任何条件且不是全部权限，返回空结果
            if (!$hasCondition && $authRange !== Role::AUTH_RANGE_ALL) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    /**
     * 应用角色数据范围条件
     */
    protected function applyAuthRangeCondition(Builder $query, Model $model, int $authRange, bool &$hasCondition): void
    {
        $table = $model->getTable();
        $employeeId = CorpContext::getEmployeeId();

        switch ($authRange) {
            case Role::AUTH_RANGE_SELF:
                // 仅本人
                if ($this->hasColumn($model, 'employee_id')) {
                    $query->where("{$table}.employee_id", $employeeId);
                    $hasCondition = true;
                }
                break;

            case Role::AUTH_RANGE_DEPARTMENT:
                // 本部门
                $this->applyDepartmentCondition($query, $model, false, $hasCondition);
                break;

            case Role::AUTH_RANGE_DEPARTMENT_SUB:
                // 本部门及下属
                $this->applyDepartmentCondition($query, $model, true, $hasCondition);
                break;
        }
    }

    /**
     * 应用部门范围条件
     */
    protected function applyDepartmentCondition(Builder $query, Model $model, bool $includeSubDepts, bool &$hasCondition): void
    {
        $table = $model->getTable();
        $employeeId = CorpContext::getEmployeeId();
        $deptIds = $this->getAccessibleDeptIds($includeSubDepts);

        // 没有部门权限时，只能看自己的
        if (empty($deptIds)) {
            if ($this->hasColumn($model, 'employee_id')) {
                $query->where("{$table}.employee_id", $employeeId);
                $hasCondition = true;
            }
            return;
        }

        // 构建部门 + 自己的条件
        $query->where(function ($subQuery) use ($table, $model, $employeeId, $deptIds) {
            $added = false;

            // 自己的数据
            if ($this->hasColumn($model, 'employee_id')) {
                $subQuery->where("{$table}.employee_id", $employeeId);
                $added = true;
            }

            // 部门范围内的数据
            if ($this->hasColumn($model, 'department_id')) {
                if ($added) {
                    $subQuery->orWhereIn("{$table}.department_id", $deptIds);
                } else {
                    $subQuery->whereIn("{$table}.department_id", $deptIds);
                    $added = true;
                }
            }

            // 多部门字段（JSON）
            if ($this->hasColumn($model, 'department_ids') && !empty($deptIds)) {
                $this->applyJsonDepartmentCondition($subQuery, $table, $deptIds, $added);
            }
        });

        $hasCondition = true;
    }

    /**
     * 应用 JSON 多部门字段条件
     */
    protected function applyJsonDepartmentCondition(Builder $query, string $table, array $deptIds, bool $useOr): void
    {
        // 优化：批量构建 JSON 条件，避免多次 orWhereJsonContains
        $jsonConditions = [];
        foreach ($deptIds as $deptId) {
            $jsonConditions[] = "JSON_CONTAINS({$table}.department_ids, '{$deptId}')";
        }

        if (!empty($jsonConditions)) {
            $rawCondition = '(' . implode(' OR ', $jsonConditions) . ')';
            if ($useOr) {
                $query->orWhereRaw($rawCondition);
            } else {
                $query->whereRaw($rawCondition);
            }
        }
    }

    /**
     * 获取可访问的部门ID列表（带缓存）
     */
    protected function getAccessibleDeptIds(bool $includeSubDepts): array
    {
        // 1. 优先从上下文获取（请求级缓存 - 最快）
        $cached = CorpContext::getAccessibleDeptIds();
        if (!empty($cached)) {
            return $cached;
        }

        $corpId = CorpContext::getCorpId();
        $departmentId = CorpContext::getDepartmentId();

        if (!$departmentId) {
            return [];
        }

        $deptIds = [$departmentId];

        if ($includeSubDepts) {
            // 2. 尝试从 Redis 缓存获取子树（分布式缓存 - 避免重复计算）
            $cacheKey = self::DEPT_TREE_CACHE_PREFIX . "{$corpId}:{$departmentId}";
            
            try {
                $cache = make(CacheInterface::class);
                $cachedTree = $cache->get($cacheKey);
                if ($cachedTree !== null) {
                    $deptIds = $cachedTree;
                    CorpContext::setAccessibleDeptIds($deptIds);
                    return $deptIds;
                }
            } catch (\Throwable $e) {
                // 缓存不可用，继续查库
            }

            // 3. 从数据库查询子树（使用物化路径）
            $deptModel = CorpManager::departmentModel();
            $dept = $deptModel::findFromCache($departmentId);

            if ($dept && $dept->full_path) {
                $subDeptIds = $deptModel::query()
                    ->where('corp_id', $corpId)
                    ->where('full_path', 'like', $dept->full_path . '%')
                    ->pluck('department_id')
                    ->toArray();
                $deptIds = array_values(array_unique(array_merge($deptIds, $subDeptIds)));
            }

            // 4. 缓存到 Redis（有 TTL，数据变更后会自动过期）
            try {
                $cache = make(CacheInterface::class);
                $cache->set($cacheKey, $deptIds, self::DEPT_TREE_CACHE_TTL);
            } catch (\Throwable $e) {
                // 忽略缓存写入失败
            }
        }

        // 5. 缓存到上下文（单请求内有效）
        CorpContext::setAccessibleDeptIds($deptIds);

        return $deptIds;
    }

    /**
     * 检查模型是否有指定字段（带静态缓存）
     */
    protected function hasColumn(Model $model, string $column): bool
    {
        $table = $model->getTable();
        $key = "{$table}:{$column}";

        if (!isset(self::$columnCache[$key])) {
            // 优先从 fillable 检查（避免查库）
            if (in_array($column, $model->getFillable())) {
                self::$columnCache[$key] = true;
            } else {
                try {
                    $columns = Db::getSchemaBuilder()->getColumnListing($table);
                    self::$columnCache[$key] = in_array($column, $columns);
                } catch (\Throwable $e) {
                    self::$columnCache[$key] = false;
                }
            }
        }

        return self::$columnCache[$key];
    }

    /**
     * 清除部门树缓存（部门变更时调用）
     */
    public static function clearDeptTreeCache(int $corpId, ?int $departmentId = null): void
    {
        try {
            $cache = make(CacheInterface::class);
            
            if ($departmentId !== null) {
                // 清除指定部门的缓存
                $cache->delete(self::DEPT_TREE_CACHE_PREFIX . "{$corpId}:{$departmentId}");
            } else {
                // 清除企业所有部门缓存（当部门结构大变动时）
                // 注意：需要 Redis 支持 scan 或 keys 命令
                $pattern = self::DEPT_TREE_CACHE_PREFIX . "{$corpId}:*";
                // 这里简化处理，实际应该用 scan 遍历删除
                // 或者等待 TTL 自动过期（5分钟）
            }
        } catch (\Throwable $e) {
            // 忽略缓存服务不可用
        }
    }

    /**
     * 清除字段检测缓存（表结构变更时调用，一般不需要）
     */
    public static function clearColumnCache(?string $table = null): void
    {
        if ($table === null) {
            self::$columnCache = [];
        } else {
            foreach (array_keys(self::$columnCache) as $key) {
                if (str_starts_with($key, "{$table}:")) {
                    unset(self::$columnCache[$key]);
                }
            }
        }
    }
}
