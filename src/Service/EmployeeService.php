<?php

declare(strict_types=1);

namespace HPlus\Corp\Service;

use HPlus\Corp\CorpManager;
use HPlus\Corp\Event\EmployeeEvent;
use Hyperf\Database\Model\Collection;

/**
 * 员工服务
 */
class EmployeeService
{
    public function __construct(
        protected DepartmentService $departmentService,
        protected PermissionCacheService $permissionCacheService
    ) {}

    protected function employeeModel(): string
    {
        return CorpManager::employeeModel();
    }

    protected function roleUserModel(): string
    {
        return CorpManager::roleUserModel();
    }

    /**
     * 获取部门及子部门下的所有员工（含兼职）
     */
    public function getByDepartmentWithChildren(int $departmentId, bool $includePartTime = true): Collection
    {
        $model = $this->employeeModel();
        $deptIds = $this->departmentService->getDescendantIds($departmentId);
        
        if ($includePartTime) {
            return $model::query()
                ->inDepartments($deptIds)
                ->active()
                ->get();
        }
        
        return $model::query()
            ->whereIn('department_id', $deptIds)
            ->active()
            ->get();
    }

    /**
     * 分配角色
     */
    public function assignRoles(int $employeeId, array $roleIds): void
    {
        $empModel = $this->employeeModel();
        $roleUserModel = $this->roleUserModel();
        
        $employee = $empModel::find($employeeId);
        if (!$employee) {
            return;
        }

        // 获取旧角色
        $oldRoleIds = $roleUserModel::query()
            ->where('employee_id', $employeeId)
            ->pluck('role_id')
            ->toArray();

        // 先删除旧的
        $roleUserModel::query()
            ->where('employee_id', $employeeId)
            ->delete();

        // 添加新的
        foreach ($roleIds as $roleId) {
            $roleUserModel::create([
                'role_id' => $roleId,
                'employee_id' => $employeeId,
                'corp_id' => $employee->corp_id,
            ]);
        }

        // 清除员工角色缓存
        $this->permissionCacheService->clearEmployeeRoleCache($employeeId);

        // 派发事件
        EmployeeEvent::dispatch([
            'type' => EmployeeEvent::ROLE_CHANGED,
            'corp_id' => $employee->corp_id,
            'employee_id' => $employeeId,
            'name' => $employee->name,
            'old_role_ids' => $oldRoleIds,
            'new_role_ids' => $roleIds,
        ]);
    }

    /**
     * 获取员工角色ID列表
     */
    public function getRoleIds(int $employeeId): array
    {
        $model = $this->roleUserModel();
        return $model::query()
            ->where('employee_id', $employeeId)
            ->pluck('role_id')
            ->toArray();
    }

    /**
     * 变更主部门
     */
    public function changePrimaryDepartment(int $employeeId, int $newDepartmentId): bool
    {
        $model = $this->employeeModel();
        $employee = $model::find($employeeId);
        if (!$employee) {
            return false;
        }

        $oldDepartmentId = $employee->department_id;
        $oldDeptIds = $employee->getAllDepartmentIds();
        
        $employee->setPrimaryDepartment($newDepartmentId);

        if ($oldDepartmentId > 0) {
            $this->departmentService->refreshEmployeeCount($oldDepartmentId);
        }
        if ($newDepartmentId > 0) {
            $this->departmentService->refreshEmployeeCount($newDepartmentId);
        }

        EmployeeEvent::dispatch([
            'type' => EmployeeEvent::DEPARTMENT_CHANGED,
            'corp_id' => $employee->corp_id,
            'employee_id' => $employeeId,
            'name' => $employee->name,
            'department_id' => $newDepartmentId,
            'old_department_ids' => $oldDeptIds,
            'new_department_ids' => $employee->getAllDepartmentIds(),
        ]);

        return true;
    }

    /**
     * 设置员工所属部门（批量）
     */
    public function setDepartments(int $employeeId, array $departmentIds, ?int $primaryDepartmentId = null): bool
    {
        $model = $this->employeeModel();
        $employee = $model::find($employeeId);
        if (!$employee) {
            return false;
        }

        $oldDeptIds = $employee->getAllDepartmentIds();

        if ($primaryDepartmentId !== null && in_array($primaryDepartmentId, $departmentIds)) {
            $employee->department_id = $primaryDepartmentId;
        } elseif (!empty($departmentIds)) {
            $employee->department_id = $departmentIds[0];
        }

        $employee->department_ids = $departmentIds;
        $employee->save();

        $affectedDeptIds = array_unique(array_merge($oldDeptIds, $departmentIds));
        foreach ($affectedDeptIds as $deptId) {
            if ($deptId > 0) {
                $this->departmentService->refreshEmployeeCount($deptId);
            }
        }

        EmployeeEvent::dispatch([
            'type' => EmployeeEvent::DEPARTMENT_CHANGED,
            'corp_id' => $employee->corp_id,
            'employee_id' => $employeeId,
            'name' => $employee->name,
            'department_id' => $employee->department_id,
            'old_department_ids' => $oldDeptIds,
            'new_department_ids' => $departmentIds,
        ]);

        return true;
    }
}
