<?php

declare(strict_types=1);

namespace HPlus\Corp\Service;

use HPlus\Corp\CorpManager;
use HPlus\Corp\Event\DepartmentEvent;
use Hyperf\Database\Model\Collection;

/**
 * 部门服务
 */
class DepartmentService
{
    protected function departmentModel(): string
    {
        return CorpManager::departmentModel();
    }

    protected function employeeModel(): string
    {
        return CorpManager::employeeModel();
    }

    /**
     * 获取部门树
     */
    public function getTree(int $corpId, int $parentId = 0): Collection
    {
        $model = $this->departmentModel();
        return $model::query()
            ->where('corp_id', $corpId)
            ->where('parent_id', $parentId)
            ->enabled()
            ->ordered()
            ->with('childrenRecursive')
            ->get();
    }

    /**
     * 获取部门及所有子部门ID
     */
    public function getDescendantIds(int $departmentId): array
    {
        $model = $this->departmentModel();
        $dept = $model::find($departmentId);
        if (!$dept) {
            return [];
        }

        return $model::query()
            ->where('full_path', 'like', $dept->full_path . '%')
            ->pluck('department_id')
            ->toArray();
    }

    /**
     * 创建部门（自动维护 full_path）
     */
    public function create(array $data): object
    {
        $model = $this->departmentModel();
        $parentId = $data['parent_id'] ?? 0;
        
        if ($parentId > 0) {
            $parent = $model::findFromCache($parentId);
            $data['full_path'] = $parent ? $parent->full_path : '/';
            $data['level'] = $parent ? $parent->level + 1 : 1;
        } else {
            $data['full_path'] = '/';
            $data['level'] = 1;
        }

        $dept = $model::create($data);
        
        // 更新 full_path 包含自己
        $dept->full_path = $data['full_path'] . $dept->department_id . '/';
        $dept->save();

        // 派发事件
        DepartmentEvent::dispatch([
            'type' => DepartmentEvent::CREATED,
            'corp_id' => $dept->corp_id,
            'department_id' => $dept->department_id,
            'name' => $dept->name,
            'parent_id' => $parentId,
            'after' => $dept->toArray(),
        ]);

        return $dept;
    }

    /**
     * 移动部门（更新子树路径）
     */
    public function move(int $departmentId, int $newParentId): bool
    {
        $model = $this->departmentModel();
        $dept = $model::find($departmentId);
        if (!$dept) {
            return false;
        }

        $oldParentId = $dept->parent_id;
        $oldPath = $dept->full_path;

        if ($newParentId > 0) {
            $newParent = $model::find($newParentId);
            if (!$newParent) {
                return false;
            }
            $newPath = $newParent->full_path . $dept->department_id . '/';
            $newLevel = $newParent->level + 1;
        } else {
            $newPath = '/' . $dept->department_id . '/';
            $newLevel = 1;
        }

        $levelDiff = $newLevel - $dept->level;

        // 更新自己
        $dept->parent_id = $newParentId;
        $dept->full_path = $newPath;
        $dept->level = $newLevel;
        $dept->save();

        // 更新所有子部门的路径
        $model::query()
            ->where('full_path', 'like', $oldPath . '%')
            ->where('department_id', '!=', $departmentId)
            ->each(function ($child) use ($oldPath, $newPath, $levelDiff) {
                $child->full_path = str_replace($oldPath, $newPath, $child->full_path);
                $child->level += $levelDiff;
                $child->save();
            });

        // 派发事件
        DepartmentEvent::dispatch([
            'type' => DepartmentEvent::MOVED,
            'corp_id' => $dept->corp_id,
            'department_id' => $departmentId,
            'name' => $dept->name,
            'old_parent_id' => $oldParentId,
            'new_parent_id' => $newParentId,
        ]);

        return true;
    }

    /**
     * 更新部门员工数量（含兼职）
     */
    public function refreshEmployeeCount(int $departmentId): void
    {
        $deptModel = $this->departmentModel();
        $empModel = $this->employeeModel();
        
        $count = $empModel::query()
            ->inDepartment($departmentId)
            ->where('status', $empModel::STATUS_ACTIVE)
            ->count();

        $deptModel::query()
            ->where('department_id', $departmentId)
            ->update(['employee_count' => $count]);
    }

    /**
     * 删除部门
     */
    public function delete(int $departmentId): bool
    {
        $deptModel = $this->departmentModel();
        $empModel = $this->employeeModel();
        
        $dept = $deptModel::find($departmentId);
        if (!$dept) {
            return false;
        }

        // 检查是否有子部门
        $hasChildren = $deptModel::query()
            ->where('parent_id', $departmentId)
            ->exists();

        if ($hasChildren) {
            return false;
        }

        // 检查是否有员工
        $hasEmployees = $empModel::query()
            ->inDepartment($departmentId)
            ->exists();

        if ($hasEmployees) {
            return false;
        }

        $deptData = $dept->toArray();
        $result = (bool) $dept->delete();

        if ($result) {
            // 派发事件
            DepartmentEvent::dispatch([
                'type' => DepartmentEvent::DELETED,
                'corp_id' => $dept->corp_id,
                'department_id' => $departmentId,
                'name' => $dept->name,
                'before' => $deptData,
            ]);
        }

        return $result;
    }
}
