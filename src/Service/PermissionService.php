<?php

declare(strict_types=1);

namespace HPlus\Corp\Service;

use HPlus\Corp\CorpManager;
use HPlus\Corp\Event\RoleEvent;
use Hyperf\Database\Model\Collection;

/**
 * 权限服务
 */
class PermissionService
{
    public function __construct(
        protected PermissionCacheService $permissionCacheService
    ) {}

    protected function permissionModel(): string
    {
        return CorpManager::permissionModel();
    }

    protected function rolePermissionModel(): string
    {
        return CorpManager::rolePermissionModel();
    }

    protected function roleModel(): string
    {
        return CorpManager::roleModel();
    }

    /**
     * 获取权限树
     */
    public function getTree(?string $appCode = null): Collection
    {
        $model = $this->permissionModel();
        $query = $model::query()->where('parent_id', 0);
        
        if ($appCode) {
            $query->where('app_code', $appCode);
        }
        
        return $query->orderBy('order')->with('childrenRecursive')->get();
    }

    /**
     * 获取角色权限ID列表
     */
    public function getRolePermissionIds(int $roleId): array
    {
        $model = $this->rolePermissionModel();
        return $model::query()
            ->where('role_id', $roleId)
            ->pluck('permission_id')
            ->toArray();
    }

    /**
     * 设置角色权限
     */
    public function setRolePermissions(int $roleId, array $permissionIds): void
    {
        $rolePermModel = $this->rolePermissionModel();
        $roleModel = $this->roleModel();
        
        $role = $roleModel::find($roleId);
        if (!$role) {
            return;
        }
        
        $oldPermissionIds = $this->getRolePermissionIds($roleId);
        
        $rolePermModel::query()->where('role_id', $roleId)->delete();
        
        foreach ($permissionIds as $permissionId) {
            $rolePermModel::create([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }

        // 清除角色权限缓存
        $this->permissionCacheService->clearRolePermissionCache($roleId);

        RoleEvent::dispatch([
            'type' => RoleEvent::PERMISSION_CHANGED,
            'corp_id' => $role->corp_id,
            'role_id' => $roleId,
            'name' => $role->name,
            'slug' => $role->slug,
            'old_permission_ids' => $oldPermissionIds,
            'new_permission_ids' => $permissionIds,
        ]);
    }

    /**
     * 获取员工权限ID列表
     */
    public function getEmployeePermissionIds(int $employeeId): array
    {
        $roleUserModel = CorpManager::roleUserModel();
        $roleModel = $this->roleModel();
        $rolePermModel = $this->rolePermissionModel();
        
        $roleIds = $roleUserModel::query()
            ->where('employee_id', $employeeId)
            ->pluck('role_id')
            ->toArray();
        
        if (empty($roleIds)) {
            return [];
        }
        
        $enabledRoleIds = $roleModel::query()
            ->whereIn('role_id', $roleIds)
            ->where('status', 1)
            ->pluck('role_id')
            ->toArray();
        
        if (empty($enabledRoleIds)) {
            return [];
        }
        
        return $rolePermModel::query()
            ->whereIn('role_id', $enabledRoleIds)
            ->pluck('permission_id')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * 检查员工是否有指定权限
     */
    public function hasPermission(int $employeeId, string $slug): bool
    {
        $permissions = $this->permissionCacheService->getEmployeePermissions($employeeId);
        return in_array($slug, $permissions, true);
    }
}
