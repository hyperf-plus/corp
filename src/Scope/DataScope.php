<?php

declare(strict_types=1);

namespace HPlus\Corp\Scope;

use HPlus\Corp\Context\CorpContext;
use HPlus\Corp\CorpManager;
use HPlus\Corp\Model\Role;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\Scope;
use Hyperf\DbConnection\Db;

/**
 * 数据范围过滤作用域
 * 
 * 基于角色的 auth_range 自动过滤数据：
 * - 1: 仅本人（employee_id）
 * - 2: 本部门（department_id）
 * - 3: 本部门及下属（department_id + full_path）
 * - 4: 全部（不过滤）
 */
class DataScope implements Scope
{
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

        // 管理员不过滤
        if (CorpContext::isAdmin()) {
            // 只过滤 corp_id
            if ($this->hasColumn($model, 'corp_id')) {
                $builder->where($model->getTable() . '.corp_id', $corpId);
            }
            return;
        }

        $table = $model->getTable();

        // 先过滤 corp_id
        if ($this->hasColumn($model, 'corp_id')) {
            $builder->where($table . '.corp_id', $corpId);
        }

        // 再根据 auth_range 过滤
        $authRange = CorpContext::getAuthRange();
        
        switch ($authRange) {
            case Role::AUTH_RANGE_SELF:
                // 仅本人
                if ($this->hasColumn($model, 'employee_id')) {
                    $builder->where($table . '.employee_id', $employeeId);
                }
                break;

            case Role::AUTH_RANGE_DEPARTMENT:
                // 本部门
                $this->applyDepartmentScope($builder, $model, false);
                break;

            case Role::AUTH_RANGE_DEPARTMENT_SUB:
                // 本部门及下属
                $this->applyDepartmentScope($builder, $model, true);
                break;

            case Role::AUTH_RANGE_ALL:
                // 全部，不额外过滤
                break;

            default:
                // 默认仅本人
                if ($this->hasColumn($model, 'employee_id')) {
                    $builder->where($table . '.employee_id', $employeeId);
                }
        }
    }

    /**
     * 应用部门范围过滤
     */
    protected function applyDepartmentScope(Builder $builder, Model $model, bool $includeSubDepts): void
    {
        $table = $model->getTable();
        $employeeId = CorpContext::getEmployeeId();
        
        // 获取可访问的部门ID
        $deptIds = $this->getAccessibleDeptIds($includeSubDepts);
        
        if (empty($deptIds)) {
            // 没有部门权限，只能看自己的
            if ($this->hasColumn($model, 'employee_id')) {
                $builder->where($table . '.employee_id', $employeeId);
            }
            return;
        }

        // 特殊标记 [-1] 表示全部
        if ($deptIds === [-1]) {
            return;
        }

        $builder->where(function ($query) use ($table, $deptIds, $employeeId, $model) {
            // 自己的数据
            if ($this->hasColumn($model, 'employee_id')) {
                $query->where($table . '.employee_id', $employeeId);
            }
            
            // 或者部门范围内的数据
            if ($this->hasColumn($model, 'department_id')) {
                $query->orWhereIn($table . '.department_id', $deptIds);
            }
            
            // 支持多部门字段
            if ($this->hasColumn($model, 'department_ids')) {
                foreach ($deptIds as $deptId) {
                    $query->orWhereJsonContains($table . '.department_ids', $deptId);
                }
            }
        });
    }

    /**
     * 获取可访问的部门ID列表
     */
    protected function getAccessibleDeptIds(bool $includeSubDepts): array
    {
        // 优先从上下文获取（已缓存）
        $cached = CorpContext::getAccessibleDeptIds();
        if (!empty($cached)) {
            return $cached;
        }

        $corpId = CorpContext::getCorpId();
        $employeeId = CorpContext::getEmployeeId();
        $departmentId = CorpContext::getDepartmentId();

        if (!$departmentId) {
            return [];
        }

        $deptIds = [$departmentId];

        if ($includeSubDepts) {
            // 获取子部门
            $deptModel = CorpManager::departmentModel();
            $dept = $deptModel::find($departmentId);
            
            if ($dept && $dept->full_path) {
                $subDeptIds = $deptModel::query()
                    ->where('corp_id', $corpId)
                    ->where('full_path', 'like', $dept->full_path . '%')
                    ->pluck('department_id')
                    ->toArray();
                $deptIds = array_unique(array_merge($deptIds, $subDeptIds));
            }
        }

        // 缓存到上下文
        CorpContext::setAccessibleDeptIds($deptIds);
        
        return $deptIds;
    }

    /**
     * 检查模型是否有指定字段
     */
    protected function hasColumn(Model $model, string $column): bool
    {
        static $cache = [];
        $table = $model->getTable();
        $key = $table . ':' . $column;
        
        if (!isset($cache[$key])) {
            try {
                $columns = Db::getSchemaBuilder()->getColumnListing($table);
                $cache[$key] = in_array($column, $columns);
            } catch (\Throwable $e) {
                $cache[$key] = false;
            }
        }
        
        return $cache[$key];
    }
}

